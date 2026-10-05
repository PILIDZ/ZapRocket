<?php
/**
 * WP-Cron for zaprocket_db_optimize_event.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron scheduler.
 */
final class ZapRocket_Module_Scheduler {

	const HOOK = 'zaprocket_db_optimize_event';

	/**
	 * Ensure weekly exists (WP 5.4+ already has it).
	 *
	 * @param array<string,array<string,mixed>> $schedules Schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public static function cron_schedules( $schedules ) {
		$schedules = is_array( $schedules ) ? $schedules : array();
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => pili__( '每周' ),
			);
		}
		return $schedules;
	}

	/**
	 * @return void
	 */
	public function hooks() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'update_option_zaprocket__dbopt', array( __CLASS__, 'reschedule' ), 20, 0 );
		add_action( 'add_option_zaprocket__dbopt', array( __CLASS__, 'reschedule' ), 20, 0 );

		$mode = sanitize_key( (string) ZapRocket_Options::get( 'dbopt', 'zr_db_schedule', 'off' ) );
		$next = wp_next_scheduled( self::HOOK );
		if ( in_array( $mode, array( 'daily', 'weekly' ), true ) ) {
			if ( ! $next ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, $mode, self::HOOK );
			}
		} elseif ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}

	/**
	 * @return void
	 */
	public function run() {
		if ( ! ZapRocket_Context::plugin_active() ) {
			return;
		}
		$mode = sanitize_key( (string) ZapRocket_Options::get( 'dbopt', 'zr_db_schedule', 'off' ) );
		if ( ! in_array( $mode, array( 'daily', 'weekly' ), true ) ) {
			return;
		}

		$keys = ZapRocket_Db_Tasks::sanitize_keys( ZapRocket_Options::get( 'dbopt', 'zr_db_clean_items', array() ) );
		if ( ! ZapRocket_Options::is_on( ZapRocket_Options::get( 'dbopt', 'zr_db_optimize_tables', false ) ) ) {
			$keys = array_values( array_diff( $keys, array( 'optimize_tables' ) ) );
		}
		if ( array() === $keys ) {
			return;
		}

		$total = 0;
		$loops = 0;
		do {
			$batch = 0;
			foreach ( $keys as $key ) {
				$n      = ZapRocket_Db_Tasks::run_one_batch( $key );
				$batch += $n;
				$total += $n;
			}
			++$loops;
		} while ( $batch > 0 && $loops < 15 );

		if ( function_exists( 'zaprocket_run_log' ) ) {
			zaprocket_run_log(
				'db',
				'info',
				sprintf(
					/* translators: %d: rows */
					pili__( '定时清理处理 %d 条。' ),
					$total
				),
				array(
					'code'   => 'schedule',
					'detail' => array(
						'keys' => $keys,
						'rows' => $total,
					),
				)
			);
		}
	}

	/**
	 * @return void
	 */
	public static function reschedule() {
		$hook = self::HOOK;
		$mode = sanitize_key( (string) ZapRocket_Options::get( 'dbopt', 'zr_db_schedule', 'off' ) );
		$next = wp_next_scheduled( $hook );
		if ( $next ) {
			wp_unschedule_event( $next, $hook );
		}
		if ( 'daily' === $mode || 'weekly' === $mode ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $mode, $hook );
		}
	}
}
