<?php
/**
 * Database cleanup tasks: count + batched delete. Keys are a whitelist.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SQL helpers for zaprocket db clean.
 */
final class ZapRocket_Db_Tasks {

	const BATCH = 200;
	const KEYS  = array(
		'revisions',
		'autodrafts',
		'trash_posts',
		'spam_comments',
		'trash_comments',
		'expired_transients',
		'orphaned_meta',
		'optimize_tables',
	);

	/**
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_key( $key ) {
		return in_array( sanitize_key( (string) $key ), self::KEYS, true );
	}

	/**
	 * @param string[] $keys Keys.
	 * @return string[]
	 */
	public static function sanitize_keys( $keys ) {
		$out = array();
		if ( ! is_array( $keys ) ) {
			return $out;
		}
		foreach ( $keys as $key ) {
			$key = sanitize_key( (string) $key );
			if ( self::is_key( $key ) ) {
				$out[] = $key;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string $key Key.
	 * @return array{count:int,label:string}
	 */
	public static function preview_one( $key ) {
		if ( ! self::is_key( $key ) ) {
			return array(
				'count' => 0,
				'label' => sprintf(
					/* translators: %d: row count */
					pili__( '%d 条' ),
					0
				),
			);
		}
		$key   = sanitize_key( (string) $key );
		$count = self::count_one( $key );
		return array(
			'count' => $count,
			'label' => sprintf(
				/* translators: %d: row count */
				pili__( '%d 条' ),
				$count
			),
		);
	}

	/**
	 * Delete up to BATCH rows. Returns deleted count.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public static function run_one_batch( $key ) {
		if ( ! self::is_key( $key ) ) {
			return 0;
		}
		$key = sanitize_key( (string) $key );
		switch ( $key ) {
			case 'revisions':
				return self::delete_revisions_batch();
			case 'autodrafts':
				return self::delete_posts_by_status( 'auto-draft', true );
			case 'trash_posts':
				return self::delete_posts_by_status( 'trash', false );
			case 'spam_comments':
				return self::delete_comments( 'spam' );
			case 'trash_comments':
				return self::delete_comments( 'trash' );
			case 'expired_transients':
				return self::delete_expired_transients();
			case 'orphaned_meta':
				return self::delete_orphaned_meta();
			case 'optimize_tables':
				return self::optimize_tables();
			default:
				return 0;
		}
	}

	/**
	 * @param string $key Key.
	 * @return int
	 */
	public static function count_one( $key ) {
		global $wpdb;
		if ( ! self::is_key( $key ) ) {
			return 0;
		}
		$key = sanitize_key( (string) $key );
		switch ( $key ) {
			case 'revisions':
				return self::count_revisions_to_drop();
			case 'autodrafts':
				return self::count_posts_status( 'auto-draft', true );
			case 'trash_posts':
				return self::count_posts_status( 'trash', false );
			case 'spam_comments':
				return (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
			case 'trash_comments':
				return (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
			case 'expired_transients':
				return (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
						$wpdb->esc_like( '_transient_timeout_' ) . '%',
						time()
					)
				);
			case 'orphaned_meta':
				return self::count_orphaned_meta();
			case 'optimize_tables':
				return 1;
			default:
				return 0;
		}
	}

	/**
	 * @return int
	 */
	private static function keep_revisions() {
		$n = (int) ZapRocket_Options::get( 'dbopt', 'zr_db_keep_revisions', 5 );
		if ( $n < 0 ) {
			$n = 0;
		}
		if ( $n > 50 ) {
			$n = 50;
		}
		return $n;
	}

	/**
	 * @return bool
	 */
	private static function purge_all_revisions() {
		return ZapRocket_Options::is_on( ZapRocket_Options::get( 'dbopt', 'zr_db_purge_all_revisions', false ) );
	}

	/**
	 * @return int
	 */
	private static function autodraft_days() {
		$n = (int) ZapRocket_Options::get( 'dbopt', 'zr_db_autodraft_days', 7 );
		if ( $n < 1 ) {
			$n = 1;
		}
		if ( $n > 90 ) {
			$n = 90;
		}
		return $n;
	}

	/**
	 * @return int
	 */
	private static function count_revisions_to_drop() {
		global $wpdb;
		if ( self::purge_all_revisions() ) {
			return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		}
		$keep = self::keep_revisions();
		if ( 0 === $keep ) {
			return 0;
		}
		$parents = $wpdb->get_col( "SELECT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent > 0 GROUP BY post_parent" );
		if ( ! is_array( $parents ) ) {
			return 0;
		}
		$total = 0;
		foreach ( $parents as $parent ) {
			$parent = (int) $parent;
			$n      = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = %d",
					$parent
				)
			);
			if ( $n > $keep ) {
				$total += ( $n - $keep );
			}
		}
		return $total;
	}

	/**
	 * @return int
	 */
	private static function delete_revisions_batch() {
		global $wpdb;
		$ids = array();
		if ( self::purge_all_revisions() ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT %d",
					self::BATCH
				)
			);
		} else {
			$keep = self::keep_revisions();
			if ( 0 === $keep ) {
				return 0;
			}
			$parents = $wpdb->get_col( "SELECT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent > 0 GROUP BY post_parent HAVING COUNT(ID) > " . (int) $keep );
			if ( ! is_array( $parents ) ) {
				return 0;
			}
			foreach ( $parents as $parent ) {
				$parent = (int) $parent;
				$extra  = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = %d ORDER BY post_date DESC, ID DESC LIMIT 100 OFFSET %d",
						$parent,
						$keep
					)
				);
				if ( is_array( $extra ) ) {
					foreach ( $extra as $id ) {
						$ids[] = (int) $id;
						if ( count( $ids ) >= self::BATCH ) {
							break 2;
						}
					}
				}
			}
		}
		return self::force_delete_posts( $ids );
	}

	/**
	 * @param string $status Status.
	 * @param bool   $age    Apply autodraft days.
	 * @return int
	 */
	private static function count_posts_status( $status, $age ) {
		global $wpdb;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'auto-draft', 'trash' ), true ) ) {
			return 0;
		}
		$sql    = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = %s";
		$args   = array( $status );
		if ( $age ) {
			$sql   .= ' AND post_modified < %s';
			$args[] = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * self::autodraft_days() ) );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * @param string $status Status.
	 * @param bool   $age    Age filter.
	 * @return int
	 */
	private static function delete_posts_by_status( $status, $age ) {
		global $wpdb;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'auto-draft', 'trash' ), true ) ) {
			return 0;
		}
		$sql    = "SELECT ID FROM {$wpdb->posts} WHERE post_status = %s";
		$args   = array( $status );
		if ( $age ) {
			$sql   .= ' AND post_modified < %s';
			$args[] = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * self::autodraft_days() ) );
		}
		$sql  .= ' LIMIT %d';
		$args[] = self::BATCH;
		$ids    = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
		return self::force_delete_posts( $ids );
	}

	/**
	 * @param int[] $ids IDs.
	 * @return int
	 */
	private static function force_delete_posts( $ids ) {
		if ( ! is_array( $ids ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id < 1 ) {
				continue;
			}
			$r = wp_delete_post( $id, true );
			if ( $r ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * @param string $approved spam|trash.
	 * @return int
	 */
	private static function delete_comments( $approved ) {
		global $wpdb;
		$approved = sanitize_key( $approved );
		if ( ! in_array( $approved, array( 'spam', 'trash' ), true ) ) {
			return 0;
		}
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT %d",
				$approved,
				self::BATCH
			)
		);
		if ( ! is_array( $ids ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_comment( (int) $id, true ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Expired transients only (timeout < now).
	 *
	 * @return int
	 */
	private static function delete_expired_transients() {
		global $wpdb;
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time(),
				self::BATCH
			)
		);
		if ( ! is_array( $names ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $names as $timeout_name ) {
			$timeout_name = (string) $timeout_name;
			$base         = ( 0 === strpos( $timeout_name, '_transient_timeout_' ) )
				? substr( $timeout_name, strlen( '_transient_timeout_' ) )
				: '';
			if ( '' === $base ) {
				continue;
			}
			delete_option( $timeout_name );
			delete_option( '_transient_' . $base );
			++$n;
		}
		$site = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT %d",
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				time(),
				self::BATCH
			)
		);
		if ( is_array( $site ) ) {
			foreach ( $site as $timeout_name ) {
				$timeout_name = (string) $timeout_name;
				$base         = ( 0 === strpos( $timeout_name, '_site_transient_timeout_' ) )
					? substr( $timeout_name, strlen( '_site_transient_timeout_' ) )
					: '';
				if ( '' === $base ) {
					continue;
				}
				delete_option( $timeout_name );
				delete_option( '_site_transient_' . $base );
				++$n;
			}
		}
		return $n;
	}

	/**
	 * @return int
	 */
	private static function count_orphaned_meta() {
		global $wpdb;
		$a = (int) $wpdb->get_var( "SELECT COUNT(m.meta_id) FROM {$wpdb->postmeta} m LEFT JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.ID IS NULL" );
		$b = (int) $wpdb->get_var( "SELECT COUNT(m.meta_id) FROM {$wpdb->commentmeta} m LEFT JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id WHERE c.comment_ID IS NULL" );
		$c = (int) $wpdb->get_var( "SELECT COUNT(m.meta_id) FROM {$wpdb->termmeta} m LEFT JOIN {$wpdb->terms} t ON t.term_id = m.term_id WHERE t.term_id IS NULL" );
		return $a + $b + $c;
	}

	/**
	 * @return int
	 */
	private static function delete_orphaned_meta() {
		global $wpdb;
		$n = 0;
		$n += self::delete_meta_ids(
			$wpdb->postmeta,
			"SELECT m.meta_id FROM {$wpdb->postmeta} m LEFT JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.ID IS NULL LIMIT " . (int) self::BATCH
		);
		$n += self::delete_meta_ids(
			$wpdb->commentmeta,
			"SELECT m.meta_id FROM {$wpdb->commentmeta} m LEFT JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id WHERE c.comment_ID IS NULL LIMIT " . (int) self::BATCH
		);
		$n += self::delete_meta_ids(
			$wpdb->termmeta,
			"SELECT m.meta_id FROM {$wpdb->termmeta} m LEFT JOIN {$wpdb->terms} t ON t.term_id = m.term_id WHERE t.term_id IS NULL LIMIT " . (int) self::BATCH
		);
		return $n;
	}

	/**
	 * @param string $table Table.
	 * @param string $sql   Select meta_id.
	 * @return int
	 */
	private static function delete_meta_ids( $table, $sql ) {
		global $wpdb;
		$allowed = array( $wpdb->postmeta, $wpdb->commentmeta, $wpdb->termmeta );
		if ( ! is_string( $table ) || ! in_array( $table, $allowed, true ) ) {
			return 0;
		}
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return 0;
		}
		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $wpdb table names.
		if ( ! is_array( $ids ) || array() === $ids ) {
			return 0;
		}
		$ids = array_map( 'intval', $ids );
		$in  = implode( ',', $ids );
		$n   = $wpdb->query( "DELETE FROM `{$table}` WHERE meta_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table whitelist, ids int.
		return is_numeric( $n ) ? (int) $n : 0;
	}

	/**
	 * OPTIMIZE selected core tables. InnoDB effect is limited.
	 *
	 * @return int
	 */
	private static function optimize_tables() {
		global $wpdb;
		$tables = array(
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->comments,
			$wpdb->commentmeta,
			$wpdb->options,
			$wpdb->terms,
			$wpdb->termmeta,
		);
		$n      = 0;
		foreach ( $tables as $table ) {
			if ( ! is_string( $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
				continue;
			}
			$wpdb->query( "OPTIMIZE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb props.
			++$n;
		}
		return $n;
	}
}
