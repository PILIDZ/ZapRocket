<?php
/**
 * Schema version store: option `{option_prefix}db_schema` => slug => version.
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-instance db schema versions.
 */
final class PILI_Db_Schema_Version {

	/**
	 * @return string
	 */
	public static function option_key() {
		$prefix = 'pili__';
		if ( class_exists( 'PILI_Config', false ) ) {
			$cfg = PILI_Config::get( 'option_prefix', 'pili__' );
			if ( is_string( $cfg ) && '' !== $cfg ) {
				$prefix = $cfg;
			}
		}
		return $prefix . 'db_schema';
	}

	/**
	 * @return array<string,int>
	 */
	public static function all() {
		$key = self::option_key();
		if ( function_exists( 'get_option' ) ) {
			$raw = get_option( $key, array() );
		} elseif ( isset( $GLOBALS['pili_db_schema_mock'] ) && is_array( $GLOBALS['pili_db_schema_mock'] ) ) {
			$raw = $GLOBALS['pili_db_schema_mock'];
		} else {
			$raw = array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $slug => $ver ) {
			$slug = PILI_Db_Schema::sanitize_slug( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = max( 0, (int) $ver );
		}
		return $out;
	}

	/**
	 * @param string $slug Slug.
	 * @return int
	 */
	public static function get_version( $slug ) {
		$slug = PILI_Db_Schema::sanitize_slug( $slug );
		$all  = self::all();
		return isset( $all[ $slug ] ) ? (int) $all[ $slug ] : 0;
	}

	/**
	 * @param string $slug    Slug.
	 * @param int    $version Version.
	 * @return bool
	 */
	public static function set_version( $slug, $version ) {
		$slug = PILI_Db_Schema::sanitize_slug( $slug );
		if ( '' === $slug ) {
			return false;
		}
		$all          = self::all();
		$all[ $slug ] = max( 0, (int) $version );
		if ( ! function_exists( 'update_option' ) ) {
			$GLOBALS['pili_db_schema_mock'] = $all;
			return true;
		}
		return (bool) update_option( self::option_key(), $all, false );
	}

	/**
	 * Upgrade registered tables whose declared version > stored.
	 * Add-only policy: re-run install/dbDelta; never drop columns.
	 * On failure for a slug, do not bump that slug's version.
	 *
	 * @return array{ok:string[],failed:array<string,string>}
	 */
	public static function upgrade_all() {
		$ok     = array();
		$failed = array();
		foreach ( PILI_Db_Schema::all() as $slug => $def ) {
			$target  = (int) $def['version'];
			$current = self::get_version( $slug );
			if ( $current >= $target ) {
				continue;
			}
			$result = PILI_Db_Schema::install( $slug );
			$is_err = ( is_object( $result ) && ( $result instanceof \WP_Error || isset( $result->errors ) ) );
			if ( $is_err ) {
				$failed[ $slug ] = is_object( $result ) && method_exists( $result, 'get_error_message' )
					? (string) $result->get_error_message()
					: 'install_failed';
				continue;
			}
			// install() already set_version; ensure target.
			self::set_version( $slug, $target );
			$ok[] = $slug;
		}
		return array(
			'ok'     => $ok,
			'failed' => $failed,
		);
	}
}
