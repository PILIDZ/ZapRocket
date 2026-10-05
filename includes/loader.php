<?php
/**
 * Feature module loader.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load and boot feature modules after options are available.
 *
 * @return void
 */
function zaprocket_load_modules() {
	require_once ZAPROCKET_DIR . 'includes/modules/slim/class-slim.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-cdn.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-preload.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-lazyload.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-js.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-minify.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-fonts.php';
	require_once ZAPROCKET_DIR . 'includes/modules/speed/class-page-cache.php';
	require_once ZAPROCKET_DIR . 'includes/modules/database/class-tasks.php';
	require_once ZAPROCKET_DIR . 'includes/modules/database/class-size-estimator.php';
	require_once ZAPROCKET_DIR . 'includes/modules/database/class-cleaner.php';
	require_once ZAPROCKET_DIR . 'includes/modules/database/class-scheduler.php';
	require_once ZAPROCKET_DIR . 'includes/modules/general/class-general.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-providers.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-regions.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-errors.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-qiniu.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-s3.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-upload.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-url.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-admin.php';
	require_once ZAPROCKET_DIR . 'includes/log/class-logger.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-migrate-db.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-migrate.php';
	require_once ZAPROCKET_DIR . 'includes/modules/oss/class-oss.php';

	ZapRocket_Module_Oss::boot();

	$scheduler = new ZapRocket_Module_Scheduler();
	$scheduler->hooks();

	$general = new ZapRocket_Module_General();
	$general->hooks();

	$cleaner = new ZapRocket_Module_Cleaner();
	$cleaner->hooks();

	( new ZapRocket_Module_Slim() )->boot();
	( new ZapRocket_Module_Page_Cache() )->boot();

	if ( ZapRocket_Context::should_skip() ) {
		return;
	}

	( new ZapRocket_Module_Cdn() )->boot();
	( new ZapRocket_Module_Preload() )->boot();
	( new ZapRocket_Module_Lazyload() )->boot();
	( new ZapRocket_Module_Js() )->boot();
	( new ZapRocket_Module_Minify() )->boot();
	( new ZapRocket_Module_Fonts() )->boot();
}
