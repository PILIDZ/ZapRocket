<?php
/**
 * Database cleanup AJAX (preview + batched delete).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX for db clean.
 */
final class ZapRocket_Module_Cleaner {

	const NONCE = 'zaprocket_db_clean';

	/**
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_ajax_zaprocket_db_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_zaprocket_db_run', array( $this, 'ajax_run' ) );
	}

	/**
	 * @return void
	 */
	public function ajax_preview() {
		$this->guard();
		$keys = $this->request_keys();
		if ( array() === $keys ) {
			$keys = ZapRocket_Db_Tasks::KEYS;
		}
		$out    = array();
		$sum    = 0;
		$counts = array();
		foreach ( $keys as $key ) {
			$row           = ZapRocket_Db_Tasks::preview_one( $key );
			$out[ $key ]   = $row;
			$counts[ $key ] = (int) $row['count'];
			$sum           += (int) $row['count'];
		}

		$pack = ( new ZapRocket_Db_Size_Estimator() )->estimate_all( $counts );
		foreach ( $out as $key => $row ) {
			$size              = isset( $pack['items'][ $key ] ) && is_array( $pack['items'][ $key ] ) ? $pack['items'][ $key ] : array();
			$row['bytes']      = isset( $size['bytes'] ) ? (int) $size['bytes'] : 0;
			$row['size_ok']    = ! empty( $size['ok'] );
			$row['size_label'] = isset( $size['label'] ) ? (string) $size['label'] : ZapRocket_Db_Size_Estimator::format_bytes( 0 );
			$out[ $key ]       = $row;
		}

		$timed_out = ! empty( $pack['timed_out'] );
		$size_note = isset( $pack['note'] ) ? (string) $pack['note'] : '';
		$payload   = array(
			'items'       => $out,
			'total_count' => $sum,
			'total_bytes' => isset( $pack['total_bytes'] ) ? (int) $pack['total_bytes'] : 0,
			'size_label'  => isset( $pack['label'] ) ? (string) $pack['label'] : ZapRocket_Db_Size_Estimator::format_bytes( 0 ),
			'size_note'   => $size_note,
			'timed_out'   => $timed_out,
			'cached'      => false,
		);
		if ( ! $timed_out ) {
			ZapRocket_Db_Size_Estimator::save_cache( $payload );
			$fresh = ZapRocket_Db_Size_Estimator::get_cache();
			if ( is_array( $fresh ) ) {
				$payload['size_note']  = isset( $fresh['size_note'] ) ? (string) $fresh['size_note'] : $payload['size_note'];
				$payload['scanned_at'] = isset( $fresh['scanned_at'] ) ? (string) $fresh['scanned_at'] : '';
				$payload['cached']     = true;
			}
		}
		if ( function_exists( 'zaprocket_run_log' ) ) {
			$scan_msg = $timed_out
				? pili__( '无法估算，站点数据量大，估算超时' )
				: sprintf(
					/* translators: 1: items, 2: size */
					pili__( '扫描完成，合计 %1$d 条，预估 %2$s。' ),
					$sum,
					isset( $pack['label'] ) ? (string) $pack['label'] : ZapRocket_Db_Size_Estimator::format_bytes( 0 )
				);
			zaprocket_run_log(
				'db',
				$timed_out ? 'warning' : 'info',
				$scan_msg,
				array(
					'code'   => 'scan',
					'detail' => array(
						'keys' => $keys,
						'rows' => $sum,
					),
				)
			);
		}
		wp_send_json_success( $payload );
	}

	/**
	 * @return void
	 */
	public function ajax_run() {
		$this->guard();
		$keys = $this->request_keys();
		if ( array() === $keys ) {
			wp_send_json_error( array( 'message' => pili__( '请先勾选要清理的项目。' ) ), 400 );
		}

		$done    = 0;
		$more    = false;
		$per_key = array();
		foreach ( $keys as $key ) {
			$n               = ZapRocket_Db_Tasks::run_one_batch( $key );
			$per_key[ $key ] = $n;
			$done           += $n;
			if ( $n >= ZapRocket_Db_Tasks::BATCH && 'optimize_tables' !== $key ) {
				$more = true;
			}
		}

		if ( function_exists( 'zaprocket_run_log' ) ) {
			$run_msg = $more
				? pili__( '本批清理完成，还有剩余，请继续点清理。' )
				: sprintf(
					/* translators: %d: rows */
					pili__( '清理完成，本批处理 %d 条。' ),
					$done
				);
			zaprocket_run_log(
				'db',
				'info',
				$run_msg,
				array(
					'code'   => 'run',
					'detail' => array(
						'keys' => $keys,
						'rows' => $done,
						'more' => $more,
					),
				)
			);
		}

		if ( $done > 0 ) {
			ZapRocket_Db_Size_Estimator::clear_cache();
		}

		wp_send_json_success(
			array(
				'deleted'      => $done,
				'more'         => $more,
				'per_key'      => $per_key,
				'cache_cleared'=> $done > 0,
			)
		);
	}

	/**
	 * @return void
	 */
	private function guard() {
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}

	/**
	 * @return string[]
	 */
	private function request_keys() {
		$raw = array();
		if ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard.
			$raw = wp_unslash( $_POST['items'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_POST['item'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = array( wp_unslash( $_POST['item'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		return ZapRocket_Db_Tasks::sanitize_keys( $raw );
	}
}
