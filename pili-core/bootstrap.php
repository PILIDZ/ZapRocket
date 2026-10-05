<?php
/**
 * 霹雳框架（PILI）入口。
 *
 * @package PILI
 * @version 0.1.0-dev
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PILI_CORE_VERSION' ) ) {
	define( 'PILI_CORE_VERSION', '0.1.0-dev' );
}
if ( ! defined( 'PILI_CORE_FILE' ) ) {
	define( 'PILI_CORE_FILE', __FILE__ );
}
if ( ! defined( 'PILI_CORE_DIR' ) ) {
	define( 'PILI_CORE_DIR', trailingslashit( dirname( __FILE__ ) ) );
}

require_once PILI_CORE_DIR . 'class-config.php';
require_once PILI_CORE_DIR . 'functions/locate-core.php';
require_once PILI_CORE_DIR . 'functions/instance-helpers.php';

// Resolve URL after locate helpers exist. Do not pre-define as '' (constants are immutable).
if ( ! defined( 'PILI_CORE_URL' ) ) {
	$url = function_exists( 'pili_locate_core_url' ) ? pili_locate_core_url( PILI_CORE_DIR ) : '';
	define( 'PILI_CORE_URL', is_string( $url ) ? $url : '' );
}

require_once PILI_CORE_DIR . 'options-sdk/bootstrap.php';
require_once PILI_CORE_DIR . 'db-sdk/bootstrap.php';
require_once PILI_CORE_DIR . 'classes/class-section-registry.php';
require_once PILI_CORE_DIR . 'functions/table-sql.php';
require_once PILI_CORE_DIR . 'classes/setup.class.php';

/**
 * Boot PILI for an instance.
 *
 * @param array<string,mixed> $config Config overrides (must include instance_id for multi-instance).
 * @return void
 */
function pili_boot( array $config = array() ) {
	$instance_id = isset( $config['instance_id'] ) ? sanitize_key( (string) $config['instance_id'] ) : 'default';
	PILI_Config::register( $instance_id, $config );
	PILI_Config::use_instance( $instance_id );

	if ( ! class_exists( '\Pili\Core\PILI_Setup', false ) ) {
		return;
	}

	\Pili\Core\PILI_Setup::init( PILI_CORE_FILE, true );
}

/**
 * Register section meta; fields load on demand when loader is set (M3).
 *
 * @param string              $unique Options unique id.
 * @param array<string,mixed> $meta   Must include id, title; optional loader callable|path; optional fields.
 * @return void
 */
function pili_register_section_meta( $unique, array $meta ) {
	/**
	 * Collect section meta for deferred field loading (M3).
	 *
	 * @param array  $meta   Meta.
	 * @param string $unique Unique.
	 */
	do_action( 'pili_register_section_meta', $meta, $unique );

	if ( ! class_exists( 'PILI_Section_Registry', false ) || ! class_exists( '\Pili\Core\PILI', false ) ) {
		return;
	}

	PILI_Section_Registry::register( $unique, $meta );

	$has_loader = isset( $meta['loader'] ) && ( is_callable( $meta['loader'] ) || ( is_string( $meta['loader'] ) && '' !== $meta['loader'] ) );
	$has_fields = ! empty( $meta['fields'] ) && is_array( $meta['fields'] );

	// Immediate: fields present and no deferred loader.
	if ( $has_fields && ! $has_loader ) {
		$section = $meta;
		unset( $section['loader'] );
		\Pili\Core\PILI::createSection( $unique, $section );
		if ( ! empty( $meta['id'] ) ) {
			PILI_Section_Registry::mark_loaded( $unique, (string) $meta['id'] );
		}
		return;
	}

	// Deferred shell: nav + placeholder; fields via loader on open.
	$shell = $meta;
	unset( $shell['fields'], $shell['loader'] );
	$shell['_deferred'] = true;
	\Pili\Core\PILI::createSection( $unique, $shell );
}
