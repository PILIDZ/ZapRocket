<?php
/**
 * Object storage module bootstrap.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OSS module.
 */
final class ZapRocket_Module_Oss {

	/**
	 * Always register admin AJAX; upload/URL need SDK.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! class_exists( 'ZapRocket_Oss_S3' ) ) {
			return;
		}

		$admin = new ZapRocket_Oss_Admin();
		$admin->init();

		$upload = new ZapRocket_Oss_Upload();
		$upload->init();
		$url = new ZapRocket_Oss_Url();
		$url->init();

		if ( class_exists( 'ZapRocket_Oss_Migrate', false ) ) {
			( new ZapRocket_Oss_Migrate() )->init();
		}
	}
}
