<?php
/**
 * 配置保存期隔离（阶段 5 / P9）。
 *
 * - 抑制 page-cron / spawn_cron 回环
 * - 禁止保存期调度新 cron 事件
 * - 软超时哨兵：第三方 update_option 后复查预算
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save guard.
 */
final class PILI_Options_Save_Guard {

	/** @var bool */
	private static $active = false;

	/** @var bool */
	private static $hooks_on = false;

	/**
	 * 进入保存隔离。
	 *
	 * @return void
	 */
	public static function begin() {
		self::$active = true;
		$GLOBALS['PILI_Options_save_in_progress'] = true;

		if ( self::$hooks_on ) {
			return;
		}
		self::$hooks_on = true;

		// 关掉本请求内的 WP-Cron 调度入口。
		remove_action( 'init', 'wp_cron' );

		add_filter( 'pre_http_request', array( __CLASS__, 'filter_block_cron_http' ), 10, 3 );
		add_filter( 'pre_schedule_event', array( __CLASS__, 'filter_block_schedule' ), 10, 2 );
		add_filter( 'pre_reschedule_event', array( __CLASS__, 'filter_block_reschedule' ), 10, 2 );
		add_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 999, 1 );
		add_action( 'added_option', array( __CLASS__, 'on_updated_option' ), 999, 1 );
	}

	/**
	 * 离开保存隔离。
	 *
	 * @return void
	 */
	public static function end() {
		self::$active = false;
		unset( $GLOBALS['PILI_Options_save_in_progress'] );

		if ( ! self::$hooks_on ) {
			return;
		}
		remove_filter( 'pre_http_request', array( __CLASS__, 'filter_block_cron_http' ), 10 );
		remove_filter( 'pre_schedule_event', array( __CLASS__, 'filter_block_schedule' ), 10 );
		remove_filter( 'pre_reschedule_event', array( __CLASS__, 'filter_block_reschedule' ), 10 );
		remove_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 999 );
		remove_action( 'added_option', array( __CLASS__, 'on_updated_option' ), 999 );
		self::$hooks_on = false;
	}

	/**
	 * @return bool
	 */
	public static function is_active() {
		return self::$active;
	}

	/**
	 * 拦截对 wp-cron.php 的回环请求。
	 *
	 * @param mixed                $pre  Preempt.
	 * @param array<string,mixed>  $args Args.
	 * @param string               $url  URL.
	 * @return mixed
	 */
	public static function filter_block_cron_http( $pre, $args, $url ) {
		if ( ! self::$active ) {
			return $pre;
		}
		if ( is_string( $url ) && false !== strpos( $url, 'wp-cron.php' ) ) {
			return new WP_Error( 'pilipost_save_skip_cron', 'Skipped page-cron during options save.' );
		}
		return $pre;
	}

	/**
	 * @param null|bool|WP_Error $pre   Pre.
	 * @param object             $event Event.
	 * @return null|bool|WP_Error
	 */
	public static function filter_block_schedule( $pre, $event ) {
		if ( ! self::$active ) {
			return $pre;
		}
		return false;
	}

	/**
	 * @param null|bool|WP_Error $pre   Pre.
	 * @param object             $event Event.
	 * @return null|bool|WP_Error
	 */
	public static function filter_block_reschedule( $pre, $event ) {
		if ( ! self::$active ) {
			return $pre;
		}
		return false;
	}

	/**
	 * 软超时哨兵：任意 option 写入后复查预算（兜住第三方钩子拖垮）。
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function on_updated_option( $option ) {
		if ( ! self::$active || ! class_exists( 'PILI_Options_Save_Budget' ) ) {
			return;
		}
		if ( ! PILI_Options_Save_Budget::is_active() ) {
			return;
		}
		PILI_Options_Save_Budget::check( 'after_option_' . sanitize_key( (string) $option ) );
	}
}
