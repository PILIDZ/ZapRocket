<?php
/**
 * PILI db-sdk bootstrap + facade.
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-schema.php';
require_once __DIR__ . '/class-schema-version.php';
require_once __DIR__ . '/class-query.php';

/**
 * Register a table (no install).
 *
 * @param string              $slug Slug without WP prefix.
 * @param array<string,mixed> $args version, columns, indexes, primary.
 * @return true|\WP_Error
 */
function pili_db_register_table( $slug, array $args ) {
	return PILI_Db_Schema::register( $slug, $args );
}

/**
 * Install one table or all registered.
 *
 * @param string|null $slug Null = all registered.
 * @return true|\WP_Error|array{ok:string[],failed:array<string,string>}
 */
function pili_db_install( $slug = null ) {
	if ( null === $slug || '' === $slug ) {
		$ok     = array();
		$failed = array();
		foreach ( array_keys( PILI_Db_Schema::all() ) as $s ) {
			$r = PILI_Db_Schema::install( $s );
			$is_err = ( is_object( $r ) && ( $r instanceof \WP_Error || isset( $r->errors ) ) );
			if ( $is_err ) {
				$failed[ $s ] = is_object( $r ) && method_exists( $r, 'get_error_message' )
					? (string) $r->get_error_message()
					: 'install_failed';
			} else {
				$ok[] = $s;
			}
		}
		return array(
			'ok'     => $ok,
			'failed' => $failed,
		);
	}
	return PILI_Db_Schema::install( (string) $slug );
}

/**
 * Upgrade registered tables behind schema version option.
 *
 * @return array{ok:string[],failed:array<string,string>}
 */
function pili_db_upgrade() {
	return PILI_Db_Schema_Version::upgrade_all();
}

/**
 * Prefixed table name for a registered slug (empty if unknown).
 *
 * @param string $slug Slug.
 * @return string
 */
function pili_db_table( $slug ) {
	return PILI_Db_Schema::table_name( $slug );
}

/**
 * Build CREATE TABLE SQL (for probes / dry-run).
 *
 * @param string $slug Slug.
 * @return string
 */
function pili_db_create_sql( $slug ) {
	return PILI_Db_Schema::build_create_sql( $slug );
}

/**
 * Paged select helper.
 *
 * @param string              $slug Table slug.
 * @param array<string,mixed> $args Args.
 * @return array<string,mixed>
 */
function pili_db_paged_select( $slug, array $args = array() ) {
	return PILI_Db_Query::paged_select( $slug, $args );
}

/**
 * Schema version option key for current instance.
 *
 * @return string
 */
function pili_db_schema_option_key() {
	return PILI_Db_Schema_Version::option_key();
}
