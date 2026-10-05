<?php
/**
 * PILI db-sdk — schema registry + InnoDB DDL builder.
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table schema registry.
 */
final class PILI_Db_Schema {

	/** @var array<string,array<string,mixed>> */
	private static $tables = array();

	/**
	 * Register a table definition (does not install).
	 *
	 * @param string               $slug Business slug without WP prefix.
	 * @param array<string,mixed>  $args version, columns, indexes, primary.
	 * @return true|\WP_Error
	 */
	public static function register( $slug, array $args ) {
		$slug = self::sanitize_slug( $slug );
		if ( '' === $slug ) {
			return self::err( 'invalid_slug', 'Invalid table slug.' );
		}
		$version = isset( $args['version'] ) ? max( 1, (int) $args['version'] ) : 1;
		$columns = isset( $args['columns'] ) && is_array( $args['columns'] ) ? $args['columns'] : array();
		if ( array() === $columns ) {
			return self::err( 'no_columns', 'Table must declare columns.' );
		}
		$indexes = isset( $args['indexes'] ) && is_array( $args['indexes'] ) ? $args['indexes'] : array();
		$primary = isset( $args['primary'] ) ? (string) $args['primary'] : 'id';

		self::$tables[ $slug ] = array(
			'slug'    => $slug,
			'version' => $version,
			'columns' => $columns,
			'indexes' => $indexes,
			'primary' => $primary,
		);
		return true;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		return self::$tables;
	}

	/**
	 * @param string $slug Slug.
	 * @return array<string,mixed>|null
	 */
	public static function get( $slug ) {
		$slug = self::sanitize_slug( $slug );
		return isset( self::$tables[ $slug ] ) ? self::$tables[ $slug ] : null;
	}

	/**
	 * Prefixed physical table name.
	 *
	 * @param string $slug Slug.
	 * @return string Empty if unknown or no $wpdb.
	 */
	public static function table_name( $slug ) {
		$slug = self::sanitize_slug( $slug );
		if ( '' === $slug || ! isset( self::$tables[ $slug ] ) ) {
			return '';
		}
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return 'wp_' . $slug;
		}
		return $wpdb->prefix . $slug;
	}

	/**
	 * Build CREATE TABLE DDL (always InnoDB + utf8mb4).
	 *
	 * @param string $slug Slug.
	 * @return string Empty on failure.
	 */
	public static function build_create_sql( $slug ) {
		$def = self::get( $slug );
		if ( null === $def ) {
			return '';
		}
		$table = self::table_name( $slug );
		if ( '' === $table ) {
			return '';
		}

		$lines = array();
		foreach ( $def['columns'] as $col => $spec ) {
			$col = self::sanitize_ident( (string) $col );
			$spec = trim( (string) $spec );
			if ( '' === $col || '' === $spec ) {
				continue;
			}
			$lines[] = '  `' . $col . '` ' . $spec;
		}
		$primary = self::sanitize_ident( (string) $def['primary'] );
		if ( '' !== $primary ) {
			$lines[] = '  PRIMARY KEY (`' . $primary . '`)';
		}
		foreach ( $def['indexes'] as $name => $cols ) {
			$iname = self::sanitize_ident( is_string( $name ) ? $name : 'idx' );
			if ( is_array( $cols ) ) {
				$col_list = array();
				$unique   = false;
				if ( isset( $cols['unique'] ) ) {
					$unique = (bool) $cols['unique'];
					unset( $cols['unique'] );
				}
				if ( isset( $cols['columns'] ) && is_array( $cols['columns'] ) ) {
					foreach ( $cols['columns'] as $c ) {
						$c = self::sanitize_ident( (string) $c );
						if ( '' !== $c ) {
							$col_list[] = '`' . $c . '`';
						}
					}
				} else {
					foreach ( $cols as $c ) {
						if ( ! is_string( $c ) && ! is_numeric( $c ) ) {
							continue;
						}
						$c = self::sanitize_ident( (string) $c );
						if ( '' !== $c ) {
							$col_list[] = '`' . $c . '`';
						}
					}
				}
				if ( array() === $col_list ) {
					continue;
				}
				$kw = $unique ? 'UNIQUE KEY' : 'KEY';
				$lines[] = '  ' . $kw . ' `' . $iname . '` (' . implode( ',', $col_list ) . ')';
			} elseif ( is_string( $cols ) ) {
				$c = self::sanitize_ident( $cols );
				if ( '' !== $c ) {
					$lines[] = '  KEY `' . $iname . '` (`' . $c . '`)';
				}
			}
		}

		if ( array() === $lines ) {
			return '';
		}

		$sql  = 'CREATE TABLE ' . self::quote_ident( $table ) . " (\n";
		$sql .= implode( ",\n", $lines );
		$sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
		return $sql;
	}

	/**
	 * Install / sync one table via dbDelta when available.
	 * Bumps version only after verify passes (docs/16).
	 *
	 * @param string $slug Slug.
	 * @return true|\WP_Error
	 */
	public static function install( $slug ) {
		$slug = self::sanitize_slug( $slug );
		$sql  = self::build_create_sql( $slug );
		if ( '' === $sql ) {
			return self::err( 'no_ddl', 'Cannot build DDL for slug.' );
		}
		// Force InnoDB even if host forgot (string already contains ENGINE=InnoDB).
		if ( false === stripos( $sql, 'ENGINE=InnoDB' ) ) {
			return self::err( 'not_innodb', 'DDL must use InnoDB.' );
		}

		if ( function_exists( 'dbDelta' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL from registry whitelist.
			dbDelta( $sql );
		} elseif ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'query' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$GLOBALS['wpdb']->query( $sql );
		}

		if ( ! self::verify_table_exists( $slug ) ) {
			return self::err( 'verify_failed', 'Table verify failed after install; version not bumped.' );
		}

		$def = self::get( $slug );
		if ( null !== $def && class_exists( 'PILI_Db_Schema_Version', false ) ) {
			PILI_Db_Schema_Version::set_version( $slug, (int) $def['version'] );
		}
		return true;
	}

	/**
	 * Confirm physical table exists (or filter for CLI mocks).
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function verify_table_exists( $slug ) {
		$slug  = self::sanitize_slug( $slug );
		$table = self::table_name( $slug );
		$ok    = true;

		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) && '' !== $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sanitized registry.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$ok    = ( is_string( $found ) && $found === $table );
		}

		/**
		 * Override install verify (CLI probes without real $wpdb).
		 * Return false to simulate verify failure (version must not bump).
		 *
		 * @param bool   $ok    Current verify result (true when no $wpdb).
		 * @param string $slug  Table slug.
		 * @param string $table Prefixed table name.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'pili_db_install_verify', $ok, $slug, $table );
		}
		return (bool) $ok;
	}

	/**
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function sanitize_slug( $slug ) {
		$slug = strtolower( (string) $slug );
		$slug = preg_replace( '/[^a-z0-9_]/', '', $slug );
		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * @param string $ident Ident.
	 * @return string
	 */
	public static function sanitize_ident( $ident ) {
		$ident = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $ident );
		return is_string( $ident ) ? $ident : '';
	}

	/**
	 * @param string $table Table.
	 * @return string
	 */
	private static function quote_ident( $table ) {
		$table = str_replace( '`', '', $table );
		return '`' . $table . '`';
	}

	/**
	 * @param string $code Code.
	 * @param string $msg  Message.
	 * @return \WP_Error|object
	 */
	private static function err( $code, $msg ) {
		if ( class_exists( 'WP_Error', false ) ) {
			return new \WP_Error( $code, $msg );
		}
		return (object) array(
			'errors' => array( $code => array( $msg ) ),
		);
	}
}
