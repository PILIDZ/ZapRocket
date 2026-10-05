<?php
/**
 * PILI options-sdk bootstrap.
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-domain-registry.php';
require_once __DIR__ . '/class-domain-store.php';
require_once __DIR__ . '/class-options-migrate.php';
require_once __DIR__ . '/class-save-budget.php';
require_once __DIR__ . '/class-save-guard.php';
require_once __DIR__ . '/facade.php';

/**
 * Global key for domain write success flag (per-instance via Config).
 *
 * @return string
 */
function pili_options_write_ok_key() {
	$key = 'pili_options_domain_write_ok';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'write_ok_key', $key );
		if ( is_string( $cfg ) && '' !== $cfg ) {
			$key = $cfg;
		}
	}
	return $key;
}

if ( ! defined( 'PILI_OPTIONS_FACADE_FROZEN' ) ) {
	define( 'PILI_OPTIONS_FACADE_FROZEN', true );
}

/**
 * Boot Save_Guard + Save_Budget for one options save request.
 * Call from save_options_handler (not global admin_init).
 *
 * @return void
 */
function pili_options_save_runtime_boot() {
	static $booted = false;
	if ( $booted ) {
		return;
	}
	$booted = true;

	if ( class_exists( 'PILI_Options_Save_Guard', false ) ) {
		PILI_Options_Save_Guard::begin();
	}
	if ( class_exists( 'PILI_Options_Save_Budget', false ) ) {
		PILI_Options_Save_Budget::begin();
	}

	register_shutdown_function( 'pili_options_save_runtime_shutdown' );
}

/**
 * Shutdown: end Budget + Guard (idempotent).
 *
 * @return void
 */
function pili_options_save_runtime_shutdown() {
	if ( class_exists( 'PILI_Options_Save_Budget', false ) ) {
		PILI_Options_Save_Budget::end();
	}
	if ( class_exists( 'PILI_Options_Save_Guard', false ) ) {
		PILI_Options_Save_Guard::end();
	}
}

/**
 * Mark migrate done for greenfield installs (demo / new hosts).
 *
 * @param string|null $option_id Path A unique option id; defaults to Config / DEMO / THEME constants.
 * @return void
 */
function pili_options_sdk_ready_greenfield( $option_id = null ) {
	if ( null === $option_id || '' === $option_id ) {
		if ( defined( 'PILI_DEMO_OPTION_ID' ) ) {
			$option_id = PILI_DEMO_OPTION_ID;
		} elseif ( defined( 'PILI_THEME_DEMO_OPTION_ID' ) ) {
			$option_id = PILI_THEME_DEMO_OPTION_ID;
		} elseif ( class_exists( 'PILI_Config', false ) ) {
			$option_id = (string) PILI_Config::get( 'option_id', '' );
		}
	}
	$option_id = is_string( $option_id ) ? $option_id : '';
	if ( '' === $option_id ) {
		return;
	}

	PILI_Options_Migrate::register();
	if ( ! PILI_Options_Migrate::is_done() ) {
		$status = PILI_Options_Migrate::status();
		if ( 'pending' === $status ) {
			$legacy = get_option( $option_id, null );
			if ( null === $legacy || ( is_array( $legacy ) && array() === $legacy ) ) {
				update_option( PILI_Options_Migrate::status_option_key(), 'done', false );
				update_option( PILI_Options_Migrate::dual_option_key(), '0', false );
			}
		}
	}
}
