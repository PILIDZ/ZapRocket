<?php
/**
 * Stock-media migrate worker (WP-Cron shards + DB queue).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * P0 one-click migrate.
 */
final class ZapRocket_Oss_Migrate {

	const CRON    = 'zaprocket_oss_migrate_tick';
	const LOCK    = 'zaprocket_oss_migrate';
	const NONCE   = 'zaprocket_oss_mig';
	const STALE   = 15;

	/** @var int */
	private static $log_task_id = 0;

	/**
	 * @return void
	 */
	public function init() {
		ZapRocket_Oss_Migrate_Db::register();
		if ( is_admin() && ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::TASK ) ) {
			ZapRocket_Oss_Migrate_Db::install();
		}
		add_action( self::CRON, array( __CLASS__, 'tick' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_preflight', array( $this, 'ajax_preflight' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_pause', array( $this, 'ajax_pause' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_resume', array( $this, 'ajax_resume' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_stop', array( $this, 'ajax_stop' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_rollback', array( $this, 'ajax_rollback' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_retry', array( $this, 'ajax_retry' ) );
		add_action( 'wp_ajax_zaprocket_oss_mig_logs', array( $this, 'ajax_logs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 35 );
	}

	/**
	 * @return bool
	 */
	public static function menu_enabled() {
		return true;
	}

	/**
	 * @param string               $level Level.
	 * @param string               $message Message.
	 * @param array<string,mixed>  $context Context.
	 * @return void
	 */
	public static function persist_log( $level, $message, array $context = array() ) {
		if ( ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::LOG ) ) {
			return;
		}
		$task = isset( $context['task_id'] ) ? absint( $context['task_id'] ) : self::$log_task_id;
		if ( $task < 1 ) {
			$active = self::active_task();
			$task   = $active ? (int) $active['id'] : 0;
		}
		global $wpdb;
		$table = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::LOG );
		$wpdb->insert(
			$table,
			array(
				'task_id'    => $task,
				'level'      => sanitize_key( (string) $level ),
				'message'    => wp_strip_all_tags( (string) $message ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param int    $task_id Task.
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @return void
	 */
	private static function note( $task_id, $level, $message ) {
		ZapRocket_Logger::write(
			ZapRocket_Logger::CHANNEL_OSS_MIGRATE,
			$level,
			$message,
			array( 'task_id' => absint( $task_id ) )
		);
	}

	/**
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private static function item_label( array $item ) {
		$rel = isset( $item['local_rel'] ) ? trim( (string) $item['local_rel'] ) : '';
		if ( '' !== $rel ) {
			return $rel;
		}
		$aid = absint( $item['attachment_id'] ?? 0 );
		return $aid > 0 ? '#' . $aid : '#0';
	}

	/**
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! ZapRocket_Context::can_manage() || ! self::menu_enabled() ) {
			return;
		}
		if ( false === strpos( (string) $hook, 'zaprocket' ) ) {
			return;
		}
		wp_register_style( 'zaprocket-admin-oss-mig', false, array(), ZAPROCKET_VERSION );
		wp_enqueue_style( 'zaprocket-admin-oss-mig' );
		wp_add_inline_style(
			'zaprocket-admin-oss-mig',
			'[data-field-id="zr_oss_mig_progress"]{display:none}'
			. '[data-field-id="zr_oss_mig_progress"].is-zr-oss-mig-progress-on{display:block}'
		);
		wp_enqueue_script(
			'zaprocket-admin-oss-mig',
			ZAPROCKET_URL . 'assets/js/admin-oss-migrate.js',
			array( 'jquery' ),
			(string) ( @filemtime( ZAPROCKET_DIR . 'assets/js/admin-oss-migrate.js' ) ?: ZAPROCKET_VERSION ) . '-p2',
			true
		);
		wp_localize_script(
			'zaprocket-admin-oss-mig',
			'zaprocketOssMig',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'fail'     => pili__( '请求失败。' ),
					'needAck'  => pili__( '请先勾选风险确认。' ),
					'needCpt'  => pili__( '请至少选择文章或页面。' ),
					'busy'     => pili__( '处理中…' ),
					'retry'    => pili__( '重试' ),
					'rollback'      => pili__( '回滚只会还原已备份的正文链接，不会删除云上文件，也不会清除附件云标记。确定继续？' ),
					'rollbackHint'  => pili__( '本次可回滚 %d 篇已备份的正文。' ),
					'rollbackNone'  => pili__( '当前没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。' ),
					'rollbackEmpty' => pili__( '当前任务没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。' ),
					'cronHint' => self::cron_notice_text(),
					'failCap'  => pili__( '失败表最多列出最近 50 条。超过后更早的失败不会出现在本表翻页里，请到下方迁移日志查看。重试只作用于本表列出的条目。' ),
					'logCap'   => pili__( '本页轮询大约展示最近 120 条迁移日志，首屏最多约 200 条；更早的记录不会出现在翻页里。' ),
					'scanLast'      => pili__( '上次已扫描' ),
					'uploadLast'    => pili__( '上次已上传' ),
					'rollbackLast'  => pili__( '上次可回滚' ),
					'failLast'      => pili__( '上次失败' ),
					'scan'          => pili__( '已扫描' ),
					'upload'        => pili__( '已上传' ),
					'rollbackBody'  => pili__( '可回滚正文' ),
					'failLabel'     => pili__( '失败' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_preflight() {
		$this->guard();
		wp_send_json_success( array( 'checks' => $this->preflight( true ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_start() {
		$this->guard();
		$pre = $this->preflight( true );
		if ( empty( $pre['ok'] ) ) {
			wp_send_json_error( array( 'message' => $pre['message'], 'checks' => $pre ) );
		}
		$ack = ! empty( $_POST['ack'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() checked nonce.
		if ( ! $ack ) {
			wp_send_json_error( array( 'message' => pili__( '请先勾选风险确认。' ) ) );
		}
		$types = $this->sanitize_types( isset( $_POST['types'] ) ? wp_unslash( $_POST['types'] ) : array( 'post', 'page' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( array() === $types ) {
			wp_send_json_error( array( 'message' => pili__( '请至少选择文章或页面。' ) ) );
		}
		$occ = self::occupying_task();
		if ( $occ ) {
			$st = (string) $occ['status'];
			if ( 'paused' === $st ) {
				wp_send_json_error( array( 'message' => pili__( '已有暂停中的迁移任务，请先继续或终止后再新建。' ) ) );
			}
			if ( 'rolling_back' === $st ) {
				wp_send_json_error( array( 'message' => pili__( '正在回滚正文，不能同时启动迁移。' ) ) );
			}
			wp_send_json_error( array( 'message' => pili__( '已有迁移任务在运行。' ) ) );
		}
		ZapRocket_Oss_Migrate_Db::install();
		$s = ZapRocket_Oss_S3::get_settings();
		global $wpdb;
		$table = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK );
		$wpdb->insert(
			$table,
			array(
				'status'        => 'scanning',
				'settings_json' => wp_json_encode(
					array(
						'types'    => $types,
						'provider' => (string) ( $s['zr_oss_provider'] ?? '' ),
						'bucket'   => (string) ( $s['zr_oss_bucket'] ?? '' ),
						'domain'   => (string) ( $s['zr_oss_custom_domain'] ?? '' ),
						'prefix'   => class_exists( 'ZapRocket_Oss_Providers', false )
							? ZapRocket_Oss_Providers::resolved_object_prefix()
							: (string) ( $s['zr_oss_object_prefix'] ?? '' ),
					)
				),
				'batch_size'    => 1,
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		self::$log_task_id = $id;
		ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'info', pili__( '迁移任务已创建，开始扫描媒体库。' ), array( 'task_id' => $id ) );
		self::schedule_soon();
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		wp_send_json_success( array( 'task_id' => $id, 'message' => pili__( '任务已启动。关闭本页不会中止后台队列。' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_pause() {
		$this->guard();
		$task = self::active_task();
		if ( ! $task || ! in_array( (string) $task['status'], array( 'queued', 'scanning', 'running' ), true ) ) {
			wp_send_json_error( array( 'message' => pili__( '没有进行中的任务。' ) ) );
		}
		self::set_status( (int) $task['id'], 'paused' );
		ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'info', pili__( '任务已暂停。' ), array( 'task_id' => (int) $task['id'] ) );
		wp_send_json_success( array( 'message' => pili__( '已暂停。' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_resume() {
		$this->guard();
		$task = self::latest_task();
		if ( ! $task || 'paused' !== (string) $task['status'] ) {
			wp_send_json_error( array( 'message' => pili__( '没有已暂停的任务。' ) ) );
		}
		self::set_status( (int) $task['id'], 'running' );
		self::schedule_soon();
		ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'info', pili__( '任务已继续。' ), array( 'task_id' => (int) $task['id'] ) );
		wp_send_json_success( array( 'message' => pili__( '已继续。' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_stop() {
		$this->guard();
		$task = self::occupying_task();
		if ( ! $task || ! in_array( (string) $task['status'], array( 'queued', 'scanning', 'running', 'paused' ), true ) ) {
			wp_send_json_error( array( 'message' => pili__( '没有可终止的迁移任务。' ) ) );
		}
		self::set_status( (int) $task['id'], 'stopped' );
		self::recycle_processing( (int) $task['id'] );
		ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'warn', pili__( '任务已终止。已上传的云文件不会自动删除。' ), array( 'task_id' => (int) $task['id'] ) );
		wp_send_json_success( array( 'message' => pili__( '已终止。' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_rollback() {
		$this->guard();
		$task = self::latest_task();
		if ( ! $task ) {
			wp_send_json_error( array( 'message' => pili__( '没有可回滚的任务。' ) ) );
		}
		if ( ! self::controls_for( $task )['rollback'] ) {
			wp_send_json_error( array( 'message' => pili__( '迁移正在运行，不能同时回滚正文。请先暂停或等待完成。' ) ) );
		}
		$id        = (int) $task['id'];
		$available = self::backup_count( $id );
		$empty_msg = pili__( '当前任务没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。' );
		self::set_status( $id, 'rolling_back' );
		$count = $available > 0 ? $this->rollback_task( $id ) : 0;
		self::set_status( $id, 'rolled_back' );
		if ( $count < 1 ) {
			ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'info', $empty_msg, array( 'task_id' => $id ) );
			wp_send_json_success(
				array(
					'message'      => $empty_msg,
					'count'        => 0,
					'backup_count' => $available,
				)
			);
		}
		$ok = sprintf( pili__( '已回滚 %d 篇内容的图片链接。附件云标记未删除。' ), $count );
		ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'info', $ok, array( 'task_id' => $id ) );
		wp_send_json_success(
			array(
				'message'      => $ok,
				'count'        => $count,
				'backup_count' => $available,
			)
		);
	}

	/**
	 * @return void
	 */
	public function ajax_status() {
		$this->guard();
		wp_send_json_success( $this->status_payload() );
	}

	/**
	 * @return void
	 */
	public function ajax_retry() {
		$this->guard();
		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $item_id < 1 ) {
			wp_send_json_error( array( 'message' => pili__( '无效条目。' ) ) );
		}
		$task = self::latest_task();
		if ( ! $task ) {
			wp_send_json_error( array( 'message' => pili__( '没有任务。' ) ) );
		}
		if ( 'rolling_back' === (string) $task['status'] ) {
			wp_send_json_error( array( 'message' => pili__( '正在回滚，不能重试。' ) ) );
		}
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$items}` WHERE id = %d", $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) || (int) $row['task_id'] !== (int) $task['id'] ) {
			wp_send_json_error( array( 'message' => pili__( '失败条目不属于当前任务。' ) ) );
		}
		$has_key = '' !== trim( (string) ( $row['object_key'] ?? '' ) ) || '' !== trim( (string) ( $row['public_url'] ?? '' ) );
		$wpdb->update(
			$items,
			array(
				'status'                => $has_key ? 'uploaded' : 'pending',
				'phase'                 => $has_key ? 'replace' : 'upload',
				'error_i18n'            => '',
				'processing_started_at' => null,
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( in_array( (string) $task['status'], array( 'done', 'paused', 'stopped', 'failed_gate' ), true ) ) {
			self::set_status( (int) $task['id'], 'running' );
		}
		self::schedule_soon();
		self::note( (int) $task['id'], 'info', sprintf( pili__( '已加入重试：%s' ), self::item_label( $row ) ) );
		wp_send_json_success( array( 'message' => pili__( '已加入重试。' ) ) );
	}

	/**
	 * @return void
	 */
	public function ajax_logs() {
		$this->guard();
		$task = self::latest_task();
		$tid  = $task ? (int) $task['id'] : 0;
		wp_send_json_success( array( 'logs' => self::recent_logs( $tid, 120 ) ) );
	}

	/**
	 * Cron tick.
	 *
	 * @return void
	 */
	public static function tick() {
		if ( ! class_exists( 'ZapRocket_Oss_S3', false ) ) {
			return;
		}
		$task = self::active_task();
		if ( ! $task ) {
			$task = self::latest_task();
			if ( ! $task || ! in_array( (string) $task['status'], array( 'queued', 'scanning', 'running' ), true ) ) {
				return;
			}
		}
		if ( in_array( (string) $task['status'], array( 'paused', 'stopped', 'done', 'rolled_back', 'rolling_back' ), true ) ) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			self::schedule_soon();
			return;
		}
		$start = microtime( true );
		$id    = (int) $task['id'];
		self::$log_task_id = $id;
		self::recycle_processing( $id );
		try {
			if ( 'scanning' === (string) $task['status'] || 'queued' === (string) $task['status'] ) {
				self::set_status( $id, 'scanning' );
				$more = self::scan_page( $id, $task );
				if ( $more ) {
					self::schedule_soon();
					return;
				}
				self::set_status( $id, 'running' );
				self::note( $id, 'info', pili__( '扫描完成，开始上传并替换正文链接。' ) );
			}
			if ( self::budget_exhausted( $start ) ) {
				self::schedule_soon();
				return;
			}
			self::process_items( $id, $task, $start );
			if ( self::remaining_work( $id ) < 1 ) {
				self::set_status( $id, 'done', true );
				ZapRocket_Logger::write( ZapRocket_Logger::CHANNEL_OSS_MIGRATE, 'info', pili__( '迁移任务已完成。' ), array( 'task_id' => $id ) );
				return;
			}
			self::schedule_soon();
		} finally {
			$ms = (int) round( ( microtime( true ) - $start ) * 1000 );
			self::touch_tick( $id, $ms );
			self::release_lock();
		}
	}

	/**
	 * @param bool $live_conn Hit the bucket.
	 * @return array<string,mixed>
	 */
	private function preflight( $live_conn = true ) {
		$checks = array();
		$ok     = true;
		$add    = static function ( $key, $pass, $label ) use ( &$checks, &$ok ) {
			$checks[ $key ] = array(
				'ok'    => (bool) $pass,
				'label' => $label,
			);
			if ( ! $pass ) {
				$ok = false;
			}
		};
		$add( 'oss', ZapRocket_Oss_S3::is_enabled(), pili__( '已启用对象存储' ) );
		$add( 'domain', ZapRocket_Oss_Url::has_public_base(), pili__( '已填写自定义公网域名' ) );
		if ( $live_conn ) {
			$test = array( 'success' => false );
			if ( ZapRocket_Oss_S3::is_enabled() ) {
				$test = ZapRocket_Oss_S3::test_connection();
			}
			$add( 'conn', ! empty( $test['success'] ), pili__( '对象存储连通' ) );
		}
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$add( 'uploads', is_dir( $base ) && is_readable( $base ), pili__( 'uploads 目录可读' ) );
		$mem = (string) ini_get( 'memory_limit' );
		$add( 'memory', true, sprintf( pili__( 'PHP 内存限制：%s' ), $mem ? $mem : '?' ) );
		$tmax = (int) ini_get( 'max_execution_time' );
		$add( 'timeout', $tmax === 0 || $tmax >= 20, sprintf( pili__( 'PHP 最大执行时间：%s' ), 0 === $tmax ? pili__( '不限制' ) : (string) $tmax ) );
		$checks['cron'] = array(
			'ok'    => true,
			'warn'  => true,
			'label' => self::cron_notice_text(),
		);
		$checks['memory']['warn'] = true;
		$busy = self::occupying_task();
		if ( $live_conn ) {
			$add( 'lock', null === $busy, pili__( '当前没有其它迁移任务占用（含暂停、回滚）' ) );
		}
		$msg = $ok ? pili__( '预检通过。' ) : pili__( '预检未通过，不能启动迁移。' );
		return array(
			'ok'      => $ok,
			'message' => $msg,
			'checks'  => $checks,
		);
	}

	/**
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! ZapRocket_Context::can_manage() || ! self::menu_enabled() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足。' ) ) );
		}
	}

	/**
	 * @param mixed $raw Raw.
	 * @return string[]
	 */
	private function sanitize_types( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$allow = get_post_types( array( 'public' => true ), 'names' );
		unset( $allow['attachment'] );
		$out = array();
		foreach ( $raw as $t ) {
			$t = sanitize_key( (string) $t );
			if ( isset( $allow[ $t ] ) ) {
				$out[] = $t;
			}
		}
		$out = array_values( array_unique( $out ) );
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function status_payload() {
		$task = self::latest_task();
		$pre  = $this->preflight( false );
		$out  = array(
			'task'         => $task,
			'preflight'    => $pre,
			'fails'        => array(),
			'logs'         => array(),
			'cron_hint'          => self::cron_notice_text(),
			'running'            => false,
			'controls'           => self::controls_for( $task ),
			'status_label'       => self::status_label( $task ),
			'stats_historical'   => self::stats_are_historical( $task ),
			'percent'            => 0,
			'counts'             => array(),
			'backup_count'       => 0,
			'rollback_hint'      => pili__( '当前没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。' ),
		);
		if ( $task ) {
			$tid                 = (int) $task['id'];
			$counts              = self::item_counts( $tid );
			$backups             = self::backup_count( $tid );
			$out['fails']        = self::fail_rows( $tid );
			$out['logs']         = self::recent_logs( $tid, 120 );
			$out['running']      = in_array( (string) $task['status'], array( 'queued', 'scanning', 'running' ), true );
			$out['counts']       = $counts;
			$out['backup_count'] = $backups;
			$task['scanned']      = $counts['scanned'];
			$task['uploaded']     = $counts['uploaded'];
			$task['replaced']     = $backups;
			$task['failed']       = $counts['failed'];
			$task['backup_count'] = $backups;
			$out['task']          = $task;
			$out['percent']       = self::progress_percent( $task, $counts );
			$out['rollback_hint'] = $backups > 0
				? sprintf( pili__( '本次可回滚 %d 篇已备份的正文。' ), $backups )
				: pili__( '当前没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。' );
		}
		return $out;
	}

	/**
	 * @param int $task_id ID.
	 * @return int
	 */
	private function rollback_task( $task_id ) {
		global $wpdb;
		$back = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::BACK );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, original FROM `{$back}` WHERE task_id = %d AND field_key = %s ORDER BY id ASC", $task_id, 'post_content' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n    = 0;
		$seen = array();
		if ( ! is_array( $rows ) ) {
			return 0;
		}
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['post_id'] ?? 0 );
			if ( $pid < 1 || isset( $seen[ $pid ] ) ) {
				continue;
			}
			$seen[ $pid ] = true;
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => (string) ( $row['original'] ?? '' ) ),
				array( 'ID' => $pid ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $pid );
			$n++;
		}
		return $n;
	}

	/**
	 * Distinct posts backed up for a task. Display / gate only; rollback SELECT is unchanged.
	 *
	 * @param int $task_id ID.
	 * @return int
	 */
	private static function backup_count( $task_id ) {
		$task_id = absint( $task_id );
		if ( $task_id < 1 || ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::BACK ) ) {
			return 0;
		}
		global $wpdb;
		$back = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::BACK );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM `{$back}` WHERE task_id = %d AND field_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id,
				'post_content'
			)
		);
	}

	/**
	 * @param array<string,mixed> $item Item.
	 * @return int
	 */
	private static function item_changed_so_far( array $item ) {
		$raw = (string) ( $item['error_i18n'] ?? '' );
		if ( 0 === strpos( $raw, '__chg:' ) ) {
			return absint( substr( $raw, 6 ) );
		}
		return 0;
	}

	/**
	 * @param int                  $task_id ID.
	 * @param array<string,mixed>  $task    Task.
	 * @return bool More pages.
	 */
	private static function scan_page( $task_id, array $task ) {
		global $wpdb;
		$last = (int) ( $task['last_scanned_id'] ?? 0 );
		$ids  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status IN ('inherit','private','publish') AND ID > %d ORDER BY ID ASC LIMIT 50",
				$last
			)
		);
		if ( ! is_array( $ids ) || array() === $ids ) {
			return false;
		}
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$n     = 0;
		$max   = $last;
		foreach ( $ids as $aid ) {
			$aid = (int) $aid;
			if ( $aid < 1 ) {
				continue;
			}
			$max = $aid;
			$rel = (string) get_post_meta( $aid, '_wp_attached_file', true );
			$dup = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$items}` WHERE task_id = %d AND attachment_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$task_id,
					$aid
				)
			);
			if ( $dup > 0 ) {
				continue;
			}
			$wpdb->insert(
				$items,
				array(
					'task_id'       => $task_id,
					'attachment_id' => $aid,
					'local_rel'     => $rel,
					'status'        => 'pending',
					'phase'         => 'upload',
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
			$n++;
		}
		if ( $n > 0 ) {
			self::note( $task_id, 'info', sprintf( pili__( '本批扫描入队 %d 个附件。' ), $n ) );
		}
		$tasks = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$tasks}` SET last_scanned_id = %d, scanned = scanned + %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$max,
				$n,
				$task_id
			)
		);
		return count( $ids ) >= 50;
	}

	/**
	 * @param int                 $task_id ID.
	 * @param array<string,mixed> $task    Task.
	 * @param float               $start   Start.
	 * @return void
	 */
	private static function process_items( $task_id, array $task, $start ) {
		$n = self::batch_size( $task );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( self::budget_exhausted( $start ) ) {
				return;
			}
			$item = self::next_item( $task_id );
			if ( ! $item ) {
				return;
			}
			self::process_one( $task_id, $item, $task, $start );
		}
	}

	/**
	 * @param int                 $task_id ID.
	 * @param array<string,mixed> $item    Item.
	 * @param array<string,mixed> $task    Task.
	 * @param float               $start   Start.
	 * @return void
	 */
	private static function process_one( $task_id, array $item, array $task, $start ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$iid   = (int) $item['id'];
		$aid   = (int) $item['attachment_id'];
		$wpdb->update(
			$items,
			array(
				'status'                => 'processing',
				'processing_started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $iid ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$label = self::item_label( $item );
		$phase = (string) ( $item['phase'] ?? 'upload' );
		$st    = (string) ( $item['status'] ?? '' );
		$has_cloud = '' !== trim( (string) ( $item['object_key'] ?? '' ) ) || '' !== trim( (string) ( $item['public_url'] ?? '' ) );
		$need_upload = ( 'replace' !== $phase && 'uploaded' !== $st && 'replaced' !== $st && ! $has_cloud );
		if ( $need_upload ) {
			$up = self::upload_attachment( $aid );
			if ( is_wp_error( $up ) ) {
				self::fail_item( $iid, $task_id, $up->get_error_message(), $label );
				return;
			}
			$wpdb->update(
				$items,
				array(
					'object_key' => (string) ( $up['key'] ?? '' ),
					'public_url' => (string) ( $up['url'] ?? '' ),
					'status'     => 'uploaded',
					'phase'      => 'replace',
				),
				array( 'id' => $iid ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$wpdb->query( $wpdb->prepare( 'UPDATE `' . ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK ) . '` SET uploaded = uploaded + 1 WHERE id = %d', $task_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$item['public_url'] = (string) ( $up['url'] ?? '' );
			$item['object_key'] = (string) ( $up['key'] ?? '' );
			$item['phase']      = 'replace';
			$key_note = (string) ( $up['key'] ?? '' );
			if ( ! empty( $up['moved'] ) ) {
				self::note( $task_id, 'info', sprintf( pili__( '已按桶内前缀重传：%1$s → %2$s' ), $label, $key_note ) );
			} elseif ( ! empty( $up['fresh'] ) ) {
				self::note( $task_id, 'info', sprintf( pili__( '已上传：%1$s → %2$s' ), $label, $key_note ) );
			} else {
				self::note( $task_id, 'info', sprintf( pili__( '云上路径已符合前缀：%s' ), $key_note ) );
			}
		}
		if ( self::budget_exhausted( $start ) ) {
			$wpdb->update( $items, array( 'status' => 'uploaded', 'phase' => 'replace' ), array( 'id' => $iid ), array( '%s', '%s' ), array( '%d' ) );
			return;
		}
		$pack = self::replace_content( $task_id, $item, $task );
		if ( is_wp_error( $pack ) ) {
			self::fail_item( $iid, $task_id, $pack->get_error_message(), $label );
			return;
		}
		$more    = ! empty( $pack['more'] );
		$changed = isset( $pack['changed'] ) ? (int) $pack['changed'] : 0;
		$total   = self::item_changed_so_far( $item ) + $changed;
		if ( $more ) {
			self::note( $task_id, 'info', sprintf( pili__( '正文替换进行中：%s' ), $label ) );
			$wpdb->update(
				$items,
				array(
					'status'     => 'uploaded',
					'phase'      => 'replace',
					'error_i18n' => '__chg:' . $total,
				),
				array( 'id' => $iid ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}
		if ( $total > 0 ) {
			self::note( $task_id, 'info', sprintf( pili__( '已替换正文链接（已修改文章数据库，支持回滚）：%s' ), $label ) );
		} else {
			self::note( $task_id, 'info', sprintf( pili__( '已设置附件云标记，前台动态替换图片，文章数据库正文未改动，无需回滚：%s' ), $label ) );
		}
		$wpdb->update(
			$items,
			array(
				'status'                => 'success',
				'phase'                 => 'replace',
				'processing_started_at' => null,
				'error_i18n'            => '',
			),
			array( 'id' => $iid ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( $total > 0 ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE `' . ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK ) . '` SET replaced = replaced + 1 WHERE id = %d', $task_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Put to the same object key media upload would use. Re-put if stored key is stale.
	 *
	 * @param string $local         Local file.
	 * @param string $key           Canonical object key.
	 * @param string $meta_key      Post meta key.
	 * @param int    $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function put_canonical_object( $local, $key, $meta_key, $attachment_id ) {
		$stored = (string) get_post_meta( $attachment_id, $meta_key, true );
		if ( '' !== $key && $stored === $key ) {
			return true;
		}
		if ( ! self::put_retry( $local, $key ) ) {
			return false;
		}
		update_post_meta( $attachment_id, $meta_key, $key );
		if ( '' !== $stored && $stored !== $key ) {
			ZapRocket_Oss_S3::delete_object( $stored );
		}
		return true;
	}

	/**
	 * @param int $attachment_id ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function upload_attachment( $attachment_id ) {
		$file = get_attached_file( $attachment_id, true );
		if ( ! $file || ! is_readable( $file ) ) {
			return new WP_Error( 'missing', pili__( '本地原图不存在或不可读。' ) );
		}
		$worker   = new ZapRocket_Oss_Upload();
		$key      = $worker->object_key_for_file( $file, $attachment_id, true );
		$existing = (string) get_post_meta( $attachment_id, ZapRocket_Oss_Upload::META_KEY, true );
		$fresh    = ( '' === $existing );
		$moved    = ( '' !== $existing && $existing !== $key );
		if ( ! self::put_canonical_object( $file, $key, ZapRocket_Oss_Upload::META_KEY, $attachment_id ) ) {
			$err = ZapRocket_Oss_S3::get_last_error();
			return new WP_Error( 'put', $err ? $err : pili__( '原图上传失败。' ) );
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		foreach ( self::extra_files( is_array( $meta ) ? $meta : array(), $file ) as $size_name => $abs ) {
			$size_key = $worker->object_key_for_file( $abs, $attachment_id, false );
			$meta_k   = '_zr_oss_key_' . sanitize_key( (string) $size_name );
			if ( ! self::put_canonical_object( $abs, $size_key, $meta_k, $attachment_id ) ) {
				return new WP_Error( 'size', sprintf( pili__( '缩略图上传失败：%s' ), (string) $size_name ) );
			}
		}
		$url = ZapRocket_Oss_Url::get_object_url( $key );
		return array(
			'key'   => $key,
			'url'   => $url,
			'fresh' => $fresh,
			'moved' => $moved,
		);
	}

	/**
	 * @param string $local Local.
	 * @param string $key   Key.
	 * @return bool
	 */
	private static function put_retry( $local, $key ) {
		$attempt = 0;
		while ( $attempt < 3 ) {
			if ( ZapRocket_Oss_S3::upload_file( $local, $key ) ) {
				return true;
			}
			$attempt++;
			if ( $attempt < 3 ) {
				usleep( 200000 * $attempt );
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $metadata Meta.
	 * @param string              $original Path.
	 * @return array<string,string>
	 */
	private static function extra_files( array $metadata, $original ) {
		$out  = array();
		$dir  = trailingslashit( dirname( $original ) );
		$seen = array( wp_normalize_path( $original ) );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $name => $info ) {
				if ( empty( $info['file'] ) ) {
					continue;
				}
				$abs = $dir . $info['file'];
				$n   = wp_normalize_path( $abs );
				if ( in_array( $n, $seen, true ) || ! is_readable( $abs ) ) {
					continue;
				}
				$seen[]       = $n;
				$out[ $name ] = $abs;
			}
		}
		if ( ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
			$abs = $dir . basename( $metadata['original_image'] );
			$n   = wp_normalize_path( $abs );
			if ( ! in_array( $n, $seen, true ) && is_readable( $abs ) ) {
				$seen[]                = $n;
				$out['original_image'] = $abs;
			}
		}
		return $out;
	}

	/**
	 * @param int                 $task_id ID.
	 * @param array<string,mixed> $item    Item.
	 * @param array<string,mixed> $task    Task.
	 * @return array{more:bool,changed:int}|\WP_Error
	 */
	private static function replace_content( $task_id, array $item, array $task ) {
		$none = array(
			'more'    => false,
			'changed' => 0,
		);
		$aid  = (int) $item['attachment_id'];
		$snap  = json_decode( (string) ( $task['settings_json'] ?? '' ), true );
		$types = ( is_array( $snap ) && ! empty( $snap['types'] ) && is_array( $snap['types'] ) ) ? $snap['types'] : array( 'post', 'page' );
		$types = array_map( 'sanitize_key', $types );
		$from  = (int) ( $item['replace_cursor'] ?? 0 );
		$map   = self::attachment_url_map( $aid, $item );
		if ( array() === $map ) {
			return $none;
		}
		$needles = self::content_search_needles( $aid );
		global $wpdb;
		$ors = array();
		foreach ( $needles as $needle ) {
			$needle = (string) $needle;
			if ( '' === $needle ) {
				continue;
			}
			$ors[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $needle ) . '%' );
		}
		if ( array() === $ors ) {
			return $none;
		}
		$in   = implode( "','", array_map( 'esc_sql', $types ) );
		$sql  = "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ('{$in}') AND post_status NOT IN ('trash','auto-draft','inherit') AND ID > %d AND (" . implode( ' OR ', $ors ) . ') ORDER BY ID ASC LIMIT 15';
		$posts = $wpdb->get_results( $wpdb->prepare( $sql, $from ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- types/ors sanitized.
		if ( ! is_array( $posts ) || array() === $posts ) {
			return $none;
		}
		$max     = $from;
		$changed = 0;
		foreach ( $posts as $row ) {
			$pid = (int) $row['ID'];
			$max = $pid;
			$old = (string) $row['post_content'];
			$new = self::apply_url_map( $old, $map );
			if ( $new === $old ) {
				continue;
			}
			if ( ! self::backup_post( $task_id, $pid, $old ) ) {
				return new WP_Error( 'backup', pili__( '正文备份失败，已跳过链接替换。' ) );
			}
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $pid ), array( '%s' ), array( '%d' ) );
			clean_post_cache( $pid );
			$changed++;
		}
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$wpdb->update( $items, array( 'replace_cursor' => $max ), array( 'id' => (int) $item['id'] ), array( '%d' ), array( '%d' ) );
		return array(
			'more'    => count( $posts ) >= 15,
			'changed' => $changed,
		);
	}

	/**
	 * @param int    $task_id ID.
	 * @param int    $post_id Post.
	 * @param string $original Content.
	 * @return bool
	 */
	private static function backup_post( $task_id, $post_id, $original ) {
		global $wpdb;
		$back = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::BACK );
		$has  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$back}` WHERE task_id = %d AND post_id = %d AND field_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id,
				$post_id,
				'post_content'
			)
		);
		if ( $has > 0 ) {
			return true;
		}
		$ok = $wpdb->insert(
			$back,
			array(
				'task_id'     => $task_id,
				'post_id'     => $post_id,
				'field_key'   => 'post_content',
				'original'    => $original,
				'replaced_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		return false !== $ok && (int) $wpdb->insert_id > 0;
	}

	/**
	 * Local URL → cloud URL for original + sizes. Keys sorted longest-first by caller.
	 *
	 * @param int                 $attachment_id ID.
	 * @param array<string,mixed> $item          Item.
	 * @return array<string,string>
	 */
	private static function attachment_url_map( $attachment_id, array $item ) {
		$map = array();
		$rel = str_replace( '\\', '/', (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$key = (string) ( $item['object_key'] ?? '' );
		if ( '' === $key ) {
			$key = (string) get_post_meta( $attachment_id, ZapRocket_Oss_Upload::META_KEY, true );
		}
		$orig_cloud = $key ? ZapRocket_Oss_Url::get_object_url( $key ) : (string) ( $item['public_url'] ?? '' );
		if ( '' !== $rel && '' !== $orig_cloud ) {
			foreach ( self::local_url_forms( $rel ) as $local ) {
				$map[ $local ] = $orig_cloud;
			}
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		$dir  = dirname( $rel );
		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $info ) {
				if ( empty( $info['file'] ) ) {
					continue;
				}
				$size_rel = ( '.' === $dir || '' === $dir ) ? (string) $info['file'] : $dir . '/' . $info['file'];
				$size_key = (string) get_post_meta( $attachment_id, '_zr_oss_key_' . sanitize_key( (string) $name ), true );
				$pub      = $size_key ? ZapRocket_Oss_Url::get_object_url( $size_key ) : '';
				if ( '' === $pub ) {
					continue;
				}
				foreach ( self::local_url_forms( $size_rel ) as $local ) {
					$map[ $local ] = $pub;
				}
			}
		}
		if ( is_array( $meta ) && ! empty( $meta['original_image'] ) && is_string( $meta['original_image'] ) ) {
			$orig_rel = ( '.' === $dir || '' === $dir ) ? basename( $meta['original_image'] ) : $dir . '/' . basename( $meta['original_image'] );
			$okey     = (string) get_post_meta( $attachment_id, '_zr_oss_key_original_image', true );
			$opub     = $okey ? ZapRocket_Oss_Url::get_object_url( $okey ) : $orig_cloud;
			if ( '' !== $opub ) {
				foreach ( self::local_url_forms( $orig_rel ) as $local ) {
					$map[ $local ] = $opub;
				}
			}
		}
		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		return $map;
	}

	/**
	 * Full, relative, protocol, JSON-escaped forms. Never basename-only.
	 *
	 * @param string $rel uploads-relative path.
	 * @return string[]
	 */
	private static function local_url_forms( $rel ) {
		$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
		if ( '' === $rel ) {
			return array();
		}
		$uploads = wp_get_upload_dir();
		$base    = untrailingslashit( (string) $uploads['baseurl'] );
		$full    = $base . '/' . $rel;
		$path    = (string) wp_parse_url( $full, PHP_URL_PATH );
		$enc_rel = class_exists( 'ZapRocket_Oss_Providers', false ) ? ZapRocket_Oss_Providers::encode_key_path( $rel ) : $rel;
		$out     = array( $full, $base . '/' . $enc_rel );
		if ( '' !== $path ) {
			$out[] = $path;
		}
		if ( 0 === strpos( $full, 'https://' ) ) {
			$out[] = 'http://' . substr( $full, 8 );
			$out[] = '//' . substr( $full, 8 );
		} elseif ( 0 === strpos( $full, 'http://' ) ) {
			$out[] = 'https://' . substr( $full, 7 );
			$out[] = '//' . substr( $full, 7 );
		}
		$out[] = str_replace( '/', '\/', $full );
		if ( '' !== $path ) {
			$out[] = str_replace( '/', '\/', $path );
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * @param int $attachment_id ID.
	 * @return string[]
	 */
	private static function content_search_needles( $attachment_id ) {
		$aid = absint( $attachment_id );
		$rel = ltrim( str_replace( '\\', '/', (string) get_post_meta( $aid, '_wp_attached_file', true ) ), '/' );
		$out = array();
		if ( '' !== $rel ) {
			$out[] = $rel;
		}
		if ( $aid > 0 ) {
			$out[] = 'wp-image-' . $aid . '"';
			$out[] = 'wp-image-' . $aid . "'";
			$out[] = 'wp-image-' . $aid . ' ';
			$out[] = 'data-id="' . $aid . '"';
			$out[] = 'data-id=\'' . $aid . '\'';
			$out[] = '"id":' . $aid;
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * @param string               $html HTML.
	 * @param array<string,string> $map  Local → cloud.
	 * @return string
	 */
	private static function apply_url_map( $html, array $map ) {
		$html = (string) $html;
		foreach ( $map as $local => $cloud ) {
			$local = (string) $local;
			$cloud = (string) $cloud;
			if ( '' === $local || '' === $cloud || $local === $cloud ) {
				continue;
			}
			if ( false !== strpos( $html, $local ) ) {
				$html = str_replace( $local, $cloud, $html );
			}
		}
		return $html;
	}

	/**
	 * @param int    $item_id ID.
	 * @param int    $task_id Task.
	 * @param string $msg     Message.
	 * @param string $label   File label.
	 * @return void
	 */
	private static function fail_item( $item_id, $task_id, $msg, $label = '' ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$wpdb->update(
			$items,
			array(
				'status'                => 'failed',
				'error_i18n'            => wp_strip_all_tags( (string) $msg ),
				'processing_started_at' => null,
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		$wpdb->query( $wpdb->prepare( 'UPDATE `' . ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK ) . '` SET failed = failed + 1 WHERE id = %d', $task_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$label = trim( (string) $label );
		$full  = '' !== $label ? $label . '：' . $msg : $msg;
		self::note( $task_id, 'error', $full );
	}

	/**
	 * @param array<string,mixed> $task Task.
	 * @return int
	 */
	private static function batch_size( array $task ) {
		$n    = (int) ( $task['batch_size'] ?? 1 );
		$last = (int) ( $task['last_tick_ms'] ?? 0 );
		if ( $last > 20000 ) {
			$n = 1;
		} elseif ( $last > 0 && $last < 4000 && $n < 3 ) {
			$n++;
		}
		$mem = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $mem > 0 && memory_get_usage( true ) > (int) ( $mem * 0.65 ) ) {
			$n = 1;
		}
		return max( 1, min( 3, $n ) );
	}

	/**
	 * @param float $start Start.
	 * @return bool
	 */
	private static function budget_exhausted( $start ) {
		$limit = (int) ini_get( 'max_execution_time' );
		if ( $limit < 1 ) {
			$limit = 60;
		}
		if ( ( microtime( true ) - $start ) > max( 8, $limit * 0.45 ) ) {
			return true;
		}
		$mem = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $mem > 0 && memory_get_usage( true ) > (int) ( $mem * 0.7 ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $task_id ID.
	 * @return int
	 */
	private static function remaining_work( $task_id ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$items}` WHERE task_id = %d AND status IN ('pending','processing','uploaded')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			)
		);
	}

	/**
	 * @param int $task_id ID.
	 * @return array<string,mixed>|null
	 */
	private static function next_item( $task_id ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$items}` WHERE task_id = %d AND status IN ('pending','uploaded') ORDER BY id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function active_task() {
		global $wpdb;
		if ( ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::TASK ) ) {
			return null;
		}
		$table = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK );
		$row   = $wpdb->get_row(
			"SELECT * FROM `{$table}` WHERE status IN ('queued','scanning','running','rolling_back') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function latest_task() {
		global $wpdb;
		if ( ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::TASK ) ) {
			return null;
		}
		$table = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK );
		$row   = $wpdb->get_row( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Running, paused, or rolling back occupies the migrate slot.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function occupying_task() {
		global $wpdb;
		if ( ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::TASK ) ) {
			return null;
		}
		$table = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK );
		$row   = $wpdb->get_row(
			"SELECT * FROM `{$table}` WHERE status IN ('queued','scanning','running','paused','rolling_back') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string,mixed>|null $task Task.
	 * @return array{start:bool,pause:bool,resume:bool,stop:bool,rollback:bool}
	 */
	public static function controls_for( $task ) {
		$st = is_array( $task ) ? (string) ( $task['status'] ?? '' ) : '';
		$occ = self::occupying_task();
		$live = in_array( $st, array( 'queued', 'scanning', 'running' ), true );
		return array(
			'start'    => null === $occ,
			'pause'    => $live,
			'resume'   => 'paused' === $st,
			'stop'     => in_array( $st, array( 'queued', 'scanning', 'running', 'paused' ), true ),
			'rollback' => in_array( $st, array( 'paused', 'stopped', 'done', 'rolled_back', 'failed_gate' ), true ),
		);
	}

	/**
	 * @param array<string,mixed>|null $task Task.
	 * @return string
	 */
	public static function status_label( $task ) {
		$st = is_array( $task ) ? (string) ( $task['status'] ?? '' ) : '';
		$map = array(
			'queued'        => pili__( '排队中' ),
			'scanning'      => pili__( '扫描中' ),
			'running'       => pili__( '上传与替换中' ),
			'paused'        => pili__( '已暂停' ),
			'stopped'       => pili__( '已终止' ),
			'done'          => pili__( '已完成' ),
			'rolling_back'  => pili__( '回滚中' ),
			'rolled_back'   => pili__( '已回滚' ),
			'failed_gate'   => pili__( '预检未通过' ),
		);
		return isset( $map[ $st ] ) ? $map[ $st ] : ( '' === $st ? pili__( '未开始' ) : $st );
	}

	/**
	 * Cron copy shared by the page callout and the preflight table.
	 *
	 * @return string
	 */
	public static function cron_notice_text() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return pili__( '注意：已关闭 WP-Cron。没有系统定时任务时，关网页后迁移可能不会继续。' );
		}
		return pili__( '注意：当前只用 WordPress 定时任务。站点没有访客访问时，迁移会暂停，有人打开网站后才会继续。' );
	}

	/**
	 * Finished or absent tasks should not look like a live run.
	 *
	 * @param array<string,mixed>|null $task Task.
	 * @return bool
	 */
	public static function stats_are_historical( $task ) {
		$st = is_array( $task ) ? (string) ( $task['status'] ?? '' ) : '';
		return ! in_array( $st, array( 'queued', 'scanning', 'running', 'paused', 'rolling_back' ), true );
	}

	/**
	 * @param int  $id     ID.
	 * @param string $status Status.
	 * @param bool $finish Finish.
	 * @return void
	 */
	private static function set_status( $id, $status, $finish = false ) {
		global $wpdb;
		$data = array( 'status' => sanitize_key( $status ) );
		$fmt  = array( '%s' );
		if ( $finish ) {
			$data['finished_at'] = current_time( 'mysql' );
			$fmt[]               = '%s';
		}
		$wpdb->update( ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK ), $data, array( 'id' => $id ), $fmt, array( '%d' ) );
	}

	/**
	 * @param int $id ID.
	 * @param int $ms Ms.
	 * @return void
	 */
	private static function touch_tick( $id, $ms ) {
		global $wpdb;
		$wpdb->update(
			ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::TASK ),
			array(
				'last_tick_at' => current_time( 'mysql' ),
				'last_tick_ms' => $ms,
				'batch_size'   => self::batch_size( array( 'batch_size' => 1, 'last_tick_ms' => $ms ) ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $task_id ID.
	 * @return void
	 */
	private static function recycle_stale( $task_id ) {
		self::recycle_processing( $task_id );
	}

	/**
	 * Single-worker: leftover processing rows are stale at tick start.
	 * Keep replace phase when the object is already on the bucket.
	 *
	 * @param int $task_id ID.
	 * @return void
	 */
	private static function recycle_processing( $task_id ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$items}` SET status = CASE WHEN (object_key IS NOT NULL AND object_key <> '') OR (public_url IS NOT NULL AND public_url <> '') THEN 'uploaded' ELSE 'pending' END, phase = CASE WHEN (object_key IS NOT NULL AND object_key <> '') OR (public_url IS NOT NULL AND public_url <> '') THEN 'replace' ELSE 'upload' END, processing_started_at = NULL WHERE task_id = %d AND status = 'processing'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			)
		);
	}

	/**
	 * @param int $task_id ID.
	 * @return array{scanned:int,uploaded:int,replaced:int,failed:int,pending:int,processing:int,awaiting_replace:int,success:int}
	 */
	private static function item_counts( $task_id ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS n FROM `{$items}` WHERE task_id = %d GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			),
			ARRAY_A
		);
		$by = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$by[ (string) ( $row['status'] ?? '' ) ] = (int) ( $row['n'] ?? 0 );
			}
		}
		$pending    = (int) ( $by['pending'] ?? 0 );
		$processing = (int) ( $by['processing'] ?? 0 );
		$uploaded   = (int) ( $by['uploaded'] ?? 0 );
		$success    = (int) ( $by['success'] ?? 0 );
		$failed     = (int) ( $by['failed'] ?? 0 );
		return array(
			'scanned'           => $pending + $processing + $uploaded + $success + $failed,
			'uploaded'          => $uploaded + $success,
			'replaced'          => $success,
			'failed'            => $failed,
			'pending'           => $pending,
			'processing'        => $processing,
			'awaiting_replace'  => $uploaded,
			'success'           => $success,
		);
	}

	/**
	 * @param array<string,mixed> $task Task.
	 * @param array<string,int>   $counts Counts.
	 * @return int
	 */
	private static function progress_percent( array $task, array $counts ) {
		$st      = (string) ( $task['status'] ?? '' );
		$scanned = max( 0, (int) ( $counts['scanned'] ?? 0 ) );
		if ( in_array( $st, array( 'queued', 'scanning' ), true ) ) {
			global $wpdb;
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status IN ('inherit','private','publish')" );
			if ( $total < 1 ) {
				return 0;
			}
			return min( 99, (int) floor( $scanned * 100 / $total ) );
		}
		if ( $scanned < 1 ) {
			return in_array( $st, array( 'done', 'stopped', 'rolled_back' ), true ) ? 100 : 0;
		}
		$finished = (int) ( $counts['success'] ?? 0 ) + (int) ( $counts['failed'] ?? 0 );
		$pct      = (int) floor( $finished * 100 / $scanned );
		if ( 'done' === $st ) {
			return 100;
		}
		return min( 99, max( 0, $pct ) );
	}

	/**
	 * @param int $task_id ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function fail_rows( $task_id ) {
		global $wpdb;
		$items = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::ITEM );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attachment_id, local_rel, error_i18n FROM `{$items}` WHERE task_id = %d AND status = 'failed' ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int $task_id ID.
	 * @param int $limit Limit.
	 * @return array<int,array<string,string>>
	 */
	private static function recent_logs( $task_id, $limit ) {
		global $wpdb;
		if ( ! ZapRocket_Oss_Migrate_Db::exists( ZapRocket_Oss_Migrate_Db::LOG ) ) {
			return array();
		}
		$table = ZapRocket_Oss_Migrate_Db::table( ZapRocket_Oss_Migrate_Db::LOG );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT level, message, created_at FROM `{$table}` WHERE task_id = %d ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? array_reverse( $rows ) : array();
	}

	/**
	 * @return bool
	 */
	private static function acquire_lock() {
		global $wpdb;
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', self::LOCK ) );
		if ( '1' === (string) $got ) {
			return true;
		}
		if ( null === $got || false === $got ) {
			$until = (int) get_option( 'zaprocket_oss_mig_lock_until', 0 );
			if ( $until > time() ) {
				return false;
			}
			update_option( 'zaprocket_oss_mig_lock_until', time() + 90, false );
			return true;
		}
		return false;
	}

	/**
	 * @return void
	 */
	private static function release_lock() {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK ) );
		delete_option( 'zaprocket_oss_mig_lock_until' );
	}

	/**
	 * @return void
	 */
	private static function schedule_soon() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + 5, self::CRON );
		}
	}

	/**
	 * Public CPT checkboxes (attachment excluded).
	 *
	 * @return array<string,string>
	 */
	public static function type_options() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$out = array();
		if ( ! is_array( $types ) ) {
			return array(
				'post' => pili__( '文章' ),
				'page' => pili__( '页面' ),
			);
		}
		foreach ( $types as $name => $obj ) {
			$label         = isset( $obj->labels->name ) ? (string) $obj->labels->name : (string) $name;
			$out[ $name ] = $label;
		}
		return $out;
	}

	/**
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public static function check_table_rows() {
		$self = new self();
		$pre  = $self->preflight( false );
		$rows = array();
		if ( ! empty( $pre['checks'] ) && is_array( $pre['checks'] ) ) {
			foreach ( $pre['checks'] as $key => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$ok      = ! empty( $row['ok'] );
				$warn    = ! empty( $row['warn'] ) || 'cron' === $key || 'memory' === $key;
				$label   = isset( $row['label'] ) ? (string) $row['label'] : (string) $key;
				if ( $warn ) {
					$cell = esc_html( pili__( '注意' ) );
				} elseif ( $ok ) {
					$cell = esc_html( pili__( '通过' ) );
				} else {
					$cell = esc_html( pili__( '未通过' ) );
				}
				$rows[] = array(
					'id'     => sanitize_key( (string) $key ),
					'item'   => $label,
					'result' => $cell,
				);
			}
		}
		return array(
			'rows'  => $rows,
			'total' => count( $rows ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function stat_cards() {
		$task     = self::latest_task();
		$counts   = $task ? self::item_counts( (int) $task['id'] ) : array();
		$scanned  = (int) ( $counts['scanned'] ?? 0 );
		$uploaded = (int) ( $counts['uploaded'] ?? 0 );
		$failed   = (int) ( $counts['failed'] ?? 0 );
		$backups  = $task ? self::backup_count( (int) $task['id'] ) : 0;
		$last     = self::stats_are_historical( $task );
		return array(
			array(
				'label' => $last ? pili__( '上次已扫描' ) : pili__( '已扫描' ),
				'value' => $scanned,
				'icon'  => 'ri-search-line',
				'tone'  => 'blue',
			),
			array(
				'label' => $last ? pili__( '上次已上传' ) : pili__( '已上传' ),
				'value' => $uploaded,
				'icon'  => 'ri-upload-cloud-2-line',
				'tone'  => 'teal',
			),
			array(
				'label' => $last ? pili__( '上次可回滚' ) : pili__( '可回滚正文' ),
				'value' => $backups,
				'icon'  => 'ri-link',
				'tone'  => 'green',
			),
			array(
				'label' => $last ? pili__( '上次失败' ) : pili__( '失败' ),
				'value' => $failed,
				'icon'  => 'ri-error-warning-line',
				'tone'  => 'red',
			),
		);
	}

	/**
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public static function fail_table_rows() {
		$task = self::latest_task();
		$tid  = $task ? (int) $task['id'] : 0;
		$raw  = $tid > 0 ? self::fail_rows( $tid ) : array();
		$rows = array();
		foreach ( $raw as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			$rows[] = array(
				'id'      => (string) $id,
				'file'    => (string) ( $row['local_rel'] ?? '' ),
				'reason'  => (string) ( $row['error_i18n'] ?? '' ),
				'actions' => self::fail_row_actions_html( $id ),
			);
		}
		return array(
			'rows'  => $rows,
			'total' => count( $rows ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function log_viewer_entries() {
		$task = self::latest_task();
		$tid  = $task ? (int) $task['id'] : 0;
		$raw  = $tid > 0 ? self::recent_logs( $tid, 200 ) : array();
		$out  = array();
		$i    = 0;
		foreach ( $raw as $row ) {
			$level = sanitize_key( (string) ( $row['level'] ?? 'info' ) );
			if ( 'warn' === $level ) {
				$level = 'warning';
			}
			++$i;
			$out[] = array(
				'id'      => (string) $i,
				'time'    => (string) ( $row['created_at'] ?? '' ),
				'level'   => $level,
				'channel' => 'oss_migrate',
				'message' => (string) ( $row['message'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * @return string
	 */
	public static function actions_html() {
		if ( ! class_exists( '\Pili\Core\PILI_Field_table' ) ) {
			ZapRocket_Oss_Admin::table_ready();
		}
		if ( ! class_exists( '\Pili\Core\PILI_Field_table' ) ) {
			return '';
		}
		$task  = self::latest_task();
		$ctl   = self::controls_for( $task );
		$n     = $task ? self::backup_count( (int) $task['id'] ) : 0;
		$hint  = $n > 0
			? sprintf( pili__( '本次可回滚 %d 篇已备份的正文。' ), $n )
			: pili__( '当前没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。' );
		$start = self::control_button(
			array(
				'label'         => pili__( '启动迁移' ),
				'action_key'    => 'oss-mig-start',
				'variant'       => 'solid',
			),
			! empty( $ctl['start'] )
		);
		$pause = self::control_button(
			array(
				'label'      => pili__( '暂停' ),
				'action_key' => 'oss-mig-pause',
				'variant'    => 'default',
			),
			! empty( $ctl['pause'] )
		);
		$resume = self::control_button(
			array(
				'label'      => pili__( '继续' ),
				'action_key' => 'oss-mig-resume',
				'variant'    => 'primary',
			),
			! empty( $ctl['resume'] )
		);
		$stop = self::control_button(
			array(
				'label'      => pili__( '终止' ),
				'action_key' => 'oss-mig-stop',
				'variant'    => 'warning',
			),
			! empty( $ctl['stop'] )
		);
		$rb = self::control_button(
			array(
				'label'      => pili__( '回滚正文链接' ),
				'action_key' => 'oss-mig-rollback',
				'variant'    => 'danger',
			),
			! empty( $ctl['rollback'] )
		);
		return '<div id="zr-oss-mig" data-zr-oss-mig="1">' . \Pili\Core\PILI_Field_table::render_action_buttons_wrap( $start . $pause . $resume . $stop . $rb ) . '<p class="mt-2 mb-0 text-sm text-gray-600" data-zr-oss-mig-rollback-hint="1">' . esc_html( $hint ) . '</p></div>';
	}

	/**
	 * @param array<string,mixed> $args Args.
	 * @param bool                $on   Enabled.
	 * @return string
	 */
	private static function control_button( array $args, $on ) {
		$args = array_merge(
			array(
				'field_id'      => 'zr_oss_mig_actions',
				'loading_label' => pili__( '处理中…' ),
				'attrs'         => array(),
			),
			$args
		);
		if ( ! $on ) {
			$args['attrs']['disabled']      = 'disabled';
			$args['attrs']['aria-disabled'] = 'true';
		}
		return \Pili\Core\PILI_Field_table::render_action_button( $args );
	}

	/**
	 * @param int $item_id Item.
	 * @return string
	 */
	public static function fail_row_actions_html( $item_id ) {
		$item_id = absint( $item_id );
		if ( $item_id < 1 || ! class_exists( '\Pili\Core\PILI_Field_table' ) ) {
			if ( class_exists( 'ZapRocket_Oss_Admin', false ) ) {
				ZapRocket_Oss_Admin::table_ready();
			}
		}
		if ( ! class_exists( '\Pili\Core\PILI_Field_table' ) ) {
			return '';
		}
		$btn = \Pili\Core\PILI_Field_table::render_action_button(
			array(
				'label'         => pili__( '重试' ),
				'action_key'    => 'oss-mig-retry',
				'field_id'      => 'zr_oss_mig_fails',
				'loading_label' => pili__( '提交中…' ),
				'variant'       => 'primary',
				'attrs'         => array(
					'data-id' => (string) $item_id,
				),
			)
		);
		return \Pili\Core\PILI_Field_table::render_action_buttons_wrap( $btn );
	}
}

/**
 * Content field callback: migrate console.
 *
 * @return string
 */
function zaprocket_oss_mig_actions_html() {
	return class_exists( 'ZapRocket_Oss_Migrate', false ) ? ZapRocket_Oss_Migrate::actions_html() : '';
}

/**
 * @return array{rows:array<int,array<string,mixed>>,total:int}
 */
function zaprocket_oss_mig_check_rows() {
	if ( ! class_exists( 'ZapRocket_Oss_Migrate', false ) ) {
		return array( 'rows' => array(), 'total' => 0 );
	}
	return ZapRocket_Oss_Migrate::check_table_rows();
}

/**
 * @return array{rows:array<int,array<string,mixed>>,total:int}
 */
function zaprocket_oss_mig_fail_rows() {
	if ( ! class_exists( 'ZapRocket_Oss_Migrate', false ) ) {
		return array( 'rows' => array(), 'total' => 0 );
	}
	return ZapRocket_Oss_Migrate::fail_table_rows();
}

/**
 * @return array<int,array<string,mixed>>
 */
function zaprocket_oss_mig_stat_cards() {
	return class_exists( 'ZapRocket_Oss_Migrate', false ) ? ZapRocket_Oss_Migrate::stat_cards() : array();
}

/**
 * @return array<int,array<string,mixed>>
 */
function zaprocket_oss_mig_log_entries() {
	return class_exists( 'ZapRocket_Oss_Migrate', false ) ? ZapRocket_Oss_Migrate::log_viewer_entries() : array();
}
