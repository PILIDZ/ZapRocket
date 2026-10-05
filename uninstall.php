<?php
/**
 * Uninstall ZapRocket.
 *
 * Only removes data when zr_gen_uninstall_cleanup was enabled before uninstall.
 * WordPress loads this file without the main plugin; read options directly.
 *
 * @package ZapRocket
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Whether user opted in to wipe data on uninstall.
 *
 * @return bool
 */
function zaprocket_uninstall_should_cleanup() {
	$general = get_option( 'zaprocket__general', array() );
	if ( ! is_array( $general ) ) {
		return false;
	}
	return ! empty( $general['zr_gen_uninstall_cleanup'] );
}

if ( function_exists( 'wp_unschedule_hook' ) ) {
	wp_unschedule_hook( 'zaprocket_oss_upload_sizes' );
}

if ( ! zaprocket_uninstall_should_cleanup() ) {
	return;
}

$option_keys = array(
	'zaprocket_options',
	'zaprocket__slim',
	'zaprocket__speed',
	'zaprocket__dbopt',
	'zaprocket__general',
	'zaprocket__oss',
	'zaprocket_oss_secret_key',
	'zaprocket_oss_error_logs',
	'zaprocket__db_schema',
	'zaprocket__migrate_status',
	'zaprocket__migrate_dual_write',
);

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

if ( function_exists( 'wp_unschedule_hook' ) ) {
	wp_unschedule_hook( 'zaprocket_db_optimize_event' );
}

global $wpdb;
if ( isset( $wpdb ) && is_object( $wpdb ) ) {
	$table = $wpdb->prefix . 'zaprocket_opt_log';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix + fixed slug.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	$run = $wpdb->prefix . 'zaprocket_run_log';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix + fixed slug.
	$wpdb->query( "DROP TABLE IF EXISTS `{$run}`" );
}

$upload = wp_upload_dir();
if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) ) {
	$fonts_dir = trailingslashit( $upload['basedir'] ) . 'zaprocket';
	if ( is_dir( $fonts_dir ) ) {
		zaprocket_uninstall_rmdir( $fonts_dir );
	}
}

if ( defined( 'WP_CONTENT_DIR' ) ) {
	$cache_dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/zaprocket';
	if ( is_dir( $cache_dir ) ) {
		zaprocket_uninstall_rmdir( $cache_dir );
	}
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory path.
 * @return void
 */
function zaprocket_uninstall_rmdir( $dir ) {
	$dir = untrailingslashit( str_replace( '\\', '/', (string) $dir ) );
	if ( ! is_dir( $dir ) || false !== strpos( $dir, '..' ) ) {
		return;
	}
	$allowed = array();
	$upload  = wp_upload_dir();
	if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) ) {
		$allowed[] = untrailingslashit( str_replace( '\\', '/', trailingslashit( $upload['basedir'] ) . 'zaprocket' ) );
	}
	if ( defined( 'WP_CONTENT_DIR' ) ) {
		$allowed[] = untrailingslashit( str_replace( '\\', '/', trailingslashit( WP_CONTENT_DIR ) . 'cache/zaprocket' ) );
	}
	$ok = false;
	foreach ( $allowed as $root ) {
		if ( $dir === $root || 0 === strpos( $dir . '/', $root . '/' ) ) {
			$ok = true;
			break;
		}
	}
	if ( ! $ok ) {
		return;
	}
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			zaprocket_uninstall_rmdir( $path );
		} else {
			wp_delete_file( $path );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	rmdir( $dir );
}
