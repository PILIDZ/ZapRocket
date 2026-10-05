<?php
/**
 * Global plugin run log table.
 *
 * Table: {$wpdb->prefix}zaprocket_run_log
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register + write + list run logs. Database scan/cleanup/schedule write here.
 */
final class ZapRocket_Run_Log {

	const SLUG        = 'zaprocket_run_log';
	const CRON_HOOK   = 'zaprocket_run_log_purge';
	const AJAX_CLEAR  = 'zaprocket_run_log_clear';
	const AJAX_EXPORT = 'zaprocket_run_log_export';
	const AJAX_REFRESH = 'zaprocket_run_log_refresh';
	const NONCE       = 'zaprocket_run_log';
	const MAX_ROWS    = 50000;

	/**
	 * @return void
	 */
	public static function hooks() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge_expired' ) );
		add_action( 'wp_ajax_' . self::AJAX_CLEAR, array( __CLASS__, 'ajax_clear' ) );
		add_action( 'wp_ajax_' . self::AJAX_EXPORT, array( __CLASS__, 'ajax_export' ) );
		add_action( 'wp_ajax_' . self::AJAX_REFRESH, array( __CLASS__, 'ajax_refresh' ) );
		add_action( 'zaprocket_log', array( __CLASS__, 'on_logger' ), 20, 4 );
		self::maybe_schedule();
		if ( is_admin() && ! self::table_exists() ) {
			self::install();
		}
	}

	/**
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'pili_db_register_table' ) ) {
			return;
		}

		pili_db_register_table(
			self::SLUG,
			array(
				'version' => 1,
				'primary' => 'id',
				'columns' => array(
					'id'          => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'created_at'  => 'datetime NOT NULL',
					'module'      => "varchar(32) NOT NULL DEFAULT ''",
					'level'       => "varchar(16) NOT NULL DEFAULT 'info'",
					'code'        => "varchar(64) NOT NULL DEFAULT ''",
					'message'     => 'text NOT NULL',
					'detail_json' => 'longtext NULL',
					'task_id'     => 'varchar(64) NULL',
					'ref_type'    => 'varchar(32) NULL',
					'ref_id'      => 'varchar(64) NULL',
					'retain_until'=> 'datetime NOT NULL',
				),
				'indexes' => array(
					'idx_created' => array( 'created_at' ),
					'idx_level'   => array( 'level', 'created_at' ),
					'idx_module'  => array( 'module', 'created_at' ),
					'idx_task'    => array( 'task_id' ),
					'idx_retain'  => array( 'retain_until' ),
				),
			)
		);
	}

	/**
	 * @return true|\WP_Error|array<string,mixed>
	 */
	public static function install() {
		self::register();
		if ( ! function_exists( 'pili_db_install' ) ) {
			return new WP_Error( 'zaprocket_db', 'pili_db_install unavailable' );
		}
		$result = pili_db_install( self::SLUG );
		if ( function_exists( 'pili_db_upgrade' ) ) {
			pili_db_upgrade();
		}
		self::maybe_schedule();
		return $result;
	}

	/**
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function unschedule() {
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( self::CRON_HOOK );
		}
	}

	/**
	 * @return string
	 */
	public static function table_name() {
		if ( function_exists( 'pili_db_table' ) ) {
			$name = pili_db_table( self::SLUG );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		global $wpdb;
		return $wpdb->prefix . self::SLUG;
	}

	/**
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
			return false;
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return is_string( $found ) && $found === $table;
	}

	/**
	 * Thin write. Failures never bubble to callers.
	 *
	 * @param string              $module slim|speed|db|oss|system|migrate.
	 * @param string              $level  info|warning|error.
	 * @param string              $message User-facing text.
	 * @param array<string,mixed> $args    code, detail, task_id, ref_type, ref_id.
	 * @return bool
	 */
	public static function write( $module, $level, $message, array $args = array() ) {
		try {
			if ( ! ZapRocket_Options::is_run_log_enabled() ) {
				return false;
			}
			$module  = self::normalize_module( $module );
			$level   = self::normalize_level( $level );
			$message = self::sanitize_text( (string) $message );
			if ( '' === $module || '' === $message ) {
				return false;
			}
			if ( ! self::table_exists() ) {
				self::install();
				if ( ! self::table_exists() ) {
					return false;
				}
			}

			global $wpdb;
			$table = self::table_name();
			if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
				return false;
			}

			$code   = self::sanitize_code( isset( $args['code'] ) ? (string) $args['code'] : '' );
			$task   = isset( $args['task_id'] ) ? substr( sanitize_text_field( (string) $args['task_id'] ), 0, 64 ) : '';
			$ref_t  = isset( $args['ref_type'] ) ? substr( sanitize_key( (string) $args['ref_type'] ), 0, 32 ) : '';
			$ref_id = isset( $args['ref_id'] ) ? substr( sanitize_text_field( (string) $args['ref_id'] ), 0, 64 ) : '';
			$detail = isset( $args['detail'] ) ? $args['detail'] : null;
			$json   = null;
			if ( null !== $detail ) {
				$clean = self::sanitize_detail( $detail );
				$enc   = wp_json_encode( $clean );
				$json  = is_string( $enc ) ? $enc : null;
			}

			$days = ZapRocket_Options::run_log_retain_days();
			$now  = self::mysql_local();
			$keep = self::mysql_local( DAY_IN_SECONDS * $days );

			$ok = $wpdb->insert(
				$table,
				array(
					'created_at'   => $now,
					'module'       => $module,
					'level'        => $level,
					'code'         => $code,
					'message'      => $message,
					'detail_json'  => $json,
					'task_id'      => '' !== $task ? $task : null,
					'ref_type'     => '' !== $ref_t ? $ref_t : null,
					'ref_id'       => '' !== $ref_id ? $ref_id : null,
					'retain_until' => $keep,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE retain_until < %s LIMIT 200", $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::trim_overflow( $table );

			return false !== $ok;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Sink for ZapRocket_Logger (migrate + future channels). Does not alter channel persist.
	 *
	 * @param string               $channel Channel.
	 * @param string               $level   Level.
	 * @param string               $message Message.
	 * @param array<string,mixed>  $context Context.
	 * @return void
	 */
	public static function on_logger( $channel, $level, $message, $context = array() ) {
		$channel = sanitize_key( (string) $channel );
		$context = is_array( $context ) ? $context : array();
		$map     = array(
			'oss_migrate' => 'migrate',
			'migrate'     => 'migrate',
			'oss'         => 'oss',
			'speed'       => 'speed',
			'slim'        => 'slim',
			'db'          => 'db',
			'system'      => 'system',
		);
		$module = isset( $map[ $channel ] ) ? $map[ $channel ] : 'system';
		$task   = isset( $context['task_id'] ) ? (string) $context['task_id'] : '';
		self::write(
			$module,
			$level,
			(string) $message,
			array(
				'code'    => $channel,
				'detail'  => $context,
				'task_id' => $task,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function purge_expired() {
		if ( ! self::table_exists() ) {
			return;
		}
		global $wpdb;
		$table = self::table_name();
		if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
			return;
		}
		$now = self::mysql_local();
		$loops = 0;
		do {
			$n = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE retain_until < %s LIMIT 500", $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			++$loops;
		} while ( $n && $n > 0 && $loops < 20 );
		self::trim_overflow( $table );
	}

	/**
	 * @param string $table Table.
	 * @return void
	 */
	private static function trim_overflow( $table ) {
		global $wpdb;
		if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
			return;
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $total <= self::MAX_ROWS ) {
			return;
		}
		$cut = $total - self::MAX_ROWS;
		if ( $cut > 500 ) {
			$cut = 500;
		}
		$wpdb->query( "DELETE FROM `{$table}` ORDER BY created_at ASC, id ASC LIMIT " . (int) $cut ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return void
	 */
	public static function ajax_clear() {
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! self::table_exists() ) {
			wp_send_json_success( array( 'message' => pili__( '日志已清空。' ) ) );
		}
		global $wpdb;
		$table = self::table_name();
		if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
			wp_send_json_error( array( 'message' => pili__( '无法清空日志。' ) ), 500 );
		}
		$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_send_json_success(
			array(
				'message' => pili__( '日志已清空。' ),
				'entries' => array(),
				'total'   => 0,
				'page'    => 1,
				'pages'   => 1,
			)
		);
	}

	/**
	 * log_viewer refresh: { entries, page, total, pages }.
	 *
	 * @return void
	 */
	public static function ajax_refresh() {
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		$page = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$per  = isset( $_POST['page_size'] ) ? (int) $_POST['page_size'] : 50; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$per  = max( 10, min( 200, $per ) );
		$pack = self::fetch_rows( self::filters_from_request(), $page, $per, true );
		$entries = array();
		foreach ( $pack['rows'] as $row ) {
			if ( is_array( $row ) ) {
				$entries[] = self::format_viewer_entry( $row );
			}
		}
		$total = $pack['total'];
		$pages = max( 1, (int) ceil( $total / $per ) );
		wp_send_json_success(
			array(
				'entries' => $entries,
				'page'    => $page,
				'total'   => $total,
				'pages'   => $pages,
			)
		);
	}

	/**
	 * log_viewer export: { content, filename, count }.
	 *
	 * @return void
	 */
	public static function ajax_export() {
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		$pack  = self::fetch_rows( self::filters_from_request(), 1, 10000, false );
		$lines = array();
		foreach ( $pack['rows'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$entry = self::format_viewer_entry( $row );
			$line  = '[' . $entry['time'] . '] [' . strtoupper( $entry['level'] ) . '] [' . $entry['channel_label'] . '] ' . $entry['message'];
			if ( '' !== $entry['context'] ) {
				$line .= "\n  " . str_replace( array( "\r\n", "\r", "\n" ), "\n  ", $entry['context'] );
			}
			$lines[] = $line;
		}
		$content = implode( "\n", $lines );
		if ( '' !== $content ) {
			$content .= "\n";
		}
		wp_send_json_success(
			array(
				'content'  => $content,
				'filename' => 'zaprocket-run-log-' . gmdate( 'Ymd-His' ) . '.log',
				'count'    => count( $lines ),
			)
		);
	}

	/**
	 * KPI cards for the log page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function summary_cards() {
		$dash  = '—';
		$empty = array(
			array(
				'label' => pili__( '近 24 小时 · 信息' ),
				'value' => $dash,
				'icon'  => 'ri-information-line',
				'tone'  => 'blue',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '运行日志' ),
					'suffix'    => '',
				),
			),
			array(
				'label' => pili__( '近 24 小时 · 警告' ),
				'value' => $dash,
				'icon'  => 'ri-alert-line',
				'tone'  => 'orange',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '运行日志' ),
					'suffix'    => '',
				),
			),
			array(
				'label' => pili__( '近 24 小时 · 错误' ),
				'value' => $dash,
				'icon'  => 'ri-error-warning-line',
				'tone'  => 'red',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '运行日志' ),
					'suffix'    => '',
				),
			),
			array(
				'label' => pili__( '表内总条数' ),
				'value' => $dash,
				'icon'  => 'ri-file-list-3-line',
				'tone'  => 'teal',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '含未过期记录' ),
					'suffix'    => '',
				),
			),
		);
		if ( ! self::table_exists() ) {
			return $empty;
		}
		global $wpdb;
		$table = self::table_name();
		$since = self::mysql_local( - DAY_IN_SECONDS );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_n,
					SUM(CASE WHEN created_at >= %s AND level = 'info' THEN 1 ELSE 0 END) AS info_n,
					SUM(CASE WHEN created_at >= %s AND level = 'warning' THEN 1 ELSE 0 END) AS warn_n,
					SUM(CASE WHEN created_at >= %s AND level = 'error' THEN 1 ELSE 0 END) AS err_n
				FROM `{$table}`", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since,
				$since,
				$since
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return $empty;
		}
		$empty[0]['value'] = (int) ( $row['info_n'] ?? 0 );
		$empty[1]['value'] = (int) ( $row['warn_n'] ?? 0 );
		$empty[2]['value'] = (int) ( $row['err_n'] ?? 0 );
		$empty[3]['value'] = (int) ( $row['total_n'] ?? 0 );
		return $empty;
	}

	/**
	 * True-paged rows for the table field.
	 *
	 * @param array<string,mixed>|null $query Page query.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public static function paged_for_table( $query ) {
		$empty = array(
			'rows'  => array(),
			'total' => 0,
		);
		if ( ! current_user_can( 'manage_options' ) || ! self::table_exists() || ! function_exists( 'pili_table_sql_paged' ) ) {
			return $empty;
		}

		$page     = 1;
		$per_page = 10;
		$search   = '';
		if ( is_array( $query ) ) {
			$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
			$per_page = max( 1, min( 100, (int) ( $query['per_page'] ?? 10 ) ) );
			$search   = isset( $query['search'] ) ? trim( (string) $query['search'] ) : '';
		}

		$filters           = self::filters_from_request();
		$filters['search'] = $search;
		$pack              = self::fetch_rows( $filters, $page, $per_page, true );
		$out               = array();
		foreach ( $pack['rows'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = self::format_table_row( $row );
		}

		return array(
			'rows'  => $out,
			'total' => $pack['total'],
		);
	}

	/**
	 * @param array<string,string> $filters Filters.
	 * @param int                  $page Page.
	 * @param int                  $per_page Per page.
	 * @param bool                 $paged Whether to page.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	private static function fetch_rows( array $filters, $page, $per_page, $paged ) {
		$empty = array(
			'rows'  => array(),
			'total' => 0,
		);
		if ( ! self::table_exists() || ! function_exists( 'pili_table_sql_paged' ) ) {
			return $empty;
		}

		$where  = array();
		$values = array();
		$levels = isset( $filters['levels'] ) && is_array( $filters['levels'] ) ? $filters['levels'] : array();
		$mods   = isset( $filters['modules'] ) && is_array( $filters['modules'] ) ? $filters['modules'] : array();
		$level  = isset( $filters['level'] ) ? self::normalize_level( $filters['level'], true ) : '';
		$module = isset( $filters['module'] ) ? self::normalize_module( $filters['module'], true ) : '';
		$search = isset( $filters['search'] ) ? trim( (string) $filters['search'] ) : '';
		$from   = isset( $filters['from'] ) ? self::normalize_date( $filters['from'], false ) : '';
		$to     = isset( $filters['to'] ) ? self::normalize_date( $filters['to'], true ) : '';

		if ( array() !== $levels ) {
			$ph      = implode( ',', array_fill( 0, count( $levels ), '%s' ) );
			$where[] = 'level IN (' . $ph . ')';
			$values  = array_merge( $values, $levels );
		} elseif ( '' !== $level ) {
			$where[]  = 'level = %s';
			$values[] = $level;
		}
		if ( array() !== $mods ) {
			$ph      = implode( ',', array_fill( 0, count( $mods ), '%s' ) );
			$where[] = 'module IN (' . $ph . ')';
			$values  = array_merge( $values, $mods );
		} elseif ( '' !== $module ) {
			$where[]  = 'module = %s';
			$values[] = $module;
		}
		if ( '' !== $from ) {
			$where[]  = 'created_at >= %s';
			$values[] = $from;
		}
		if ( '' !== $to ) {
			$where[]  = 'created_at <= %s';
			$values[] = $to;
		}
		if ( '' !== $search ) {
			global $wpdb;
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(message LIKE %s OR code LIKE %s OR module LIKE %s OR level LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$args = array(
			'select'    => 'id, created_at, module, level, code, message, detail_json, task_id, ref_type, ref_id',
			'order_sql' => 'created_at DESC, id DESC',
			'page'      => $paged ? $page : 1,
			'per_page'  => $paged ? $per_page : max( 1, min( 10000, (int) $per_page ) ),
		);
		if ( array() !== $where ) {
			$args['where_sql']    = implode( ' AND ', $where );
			$args['where_values'] = $values;
		}

		$pack = pili_table_sql_paged( self::table_name(), $args );
		if ( is_wp_error( $pack ) || ! is_array( $pack ) ) {
			return $empty;
		}

		return array(
			'rows'  => isset( $pack['rows'] ) && is_array( $pack['rows'] ) ? $pack['rows'] : array(),
			'total' => isset( $pack['total'] ) ? max( 0, (int) $pack['total'] ) : 0,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function filters_from_request() {
		$levels  = array();
		$modules = array();
		if ( isset( $_REQUEST['levels'] ) && is_array( $_REQUEST['levels'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( wp_unslash( $_REQUEST['levels'] ) as $lv ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$norm = self::normalize_level( sanitize_key( (string) $lv ), true );
				if ( '' !== $norm ) {
					$levels[] = $norm;
				}
			}
			$levels = array_values( array_unique( $levels ) );
		}
		if ( isset( $_REQUEST['channels'] ) && is_array( $_REQUEST['channels'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( wp_unslash( $_REQUEST['channels'] ) as $ch ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$norm = self::normalize_module( sanitize_key( (string) $ch ), true );
				if ( '' !== $norm ) {
					$modules[] = $norm;
				}
			}
			$modules = array_values( array_unique( $modules ) );
		}
		$level = isset( $_REQUEST['zr_logs_level'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['zr_logs_level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$module = isset( $_REQUEST['zr_logs_module'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['zr_logs_module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$from   = '';
		$to     = '';
		if ( isset( $_REQUEST['time_from'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$from = sanitize_text_field( wp_unslash( (string) $_REQUEST['time_from'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_REQUEST['zr_logs_from'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$from = sanitize_text_field( wp_unslash( (string) $_REQUEST['zr_logs_from'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_REQUEST['time_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$to = sanitize_text_field( wp_unslash( (string) $_REQUEST['time_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_REQUEST['zr_logs_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$to = sanitize_text_field( wp_unslash( (string) $_REQUEST['zr_logs_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		$search = '';
		if ( isset( $_REQUEST['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$search = sanitize_text_field( wp_unslash( (string) $_REQUEST['q'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_REQUEST['search'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$search = sanitize_text_field( wp_unslash( (string) $_REQUEST['search'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( in_array( $level, array( 'all', '0' ), true ) ) {
			$level = '';
		}
		if ( in_array( $module, array( 'all', '0' ), true ) ) {
			$module = '';
		}
		return array(
			'level'   => $level,
			'module'  => $module,
			'levels'  => $levels,
			'modules' => $modules,
			'from'    => $from,
			'to'      => $to,
			'search'  => $search,
		);
	}

	/**
	 * One log_viewer entry.
	 *
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	public static function format_viewer_entry( array $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$module  = isset( $row['module'] ) ? (string) $row['module'] : '';
		$level   = isset( $row['level'] ) ? (string) $row['level'] : 'info';
		$message = isset( $row['message'] ) ? wp_strip_all_tags( (string) $row['message'] ) : '';
		$code    = isset( $row['code'] ) ? (string) $row['code'] : '';
		if ( '' !== $code ) {
			$message = '[' . $code . '] ' . $message;
		}
		$ctx = array();
		if ( isset( $row['detail_json'] ) && '' !== (string) $row['detail_json'] ) {
			$decoded = json_decode( (string) $row['detail_json'], true );
			if ( is_array( $decoded ) ) {
				$ctx = $decoded;
			} else {
				$ctx = array( 'detail' => (string) $row['detail_json'] );
			}
		}
		if ( isset( $row['task_id'] ) && '' !== (string) $row['task_id'] ) {
			$ctx['task_id'] = (string) $row['task_id'];
		}
		if ( isset( $row['ref_type'] ) && '' !== (string) $row['ref_type'] ) {
			$ctx['ref_type'] = (string) $row['ref_type'];
		}
		if ( isset( $row['ref_id'] ) && '' !== (string) $row['ref_id'] ) {
			$ctx['ref_id'] = (string) $row['ref_id'];
		}
		$context = array() !== $ctx ? wp_json_encode( $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
		if ( ! is_string( $context ) ) {
			$context = '';
		}
		$labels_mod = self::module_label( $module );
		return array(
			'id'            => $id,
			'time'          => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'level'         => $level,
			'channel'       => $module,
			'channel_label' => $labels_mod,
			'message'       => $message,
			'context'       => $context,
		);
	}

	/**
	 * @param array                    $field  Field.
	 * @param mixed                    $value  Value.
	 * @param string                   $unique Unique.
	 * @param string                   $where  Where.
	 * @param string                   $parent Parent.
	 * @param array<string,mixed>|null $query  Unused.
	 * @return array<int,array<string,mixed>>
	 */
	public static function viewer_entries( $field = null, $value = null, $unique = '', $where = '', $parent = '', $query = null ) {
		unset( $field, $value, $unique, $where, $parent, $query );
		return array();
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_table_row( array $row ) {
		$when   = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
		$module = isset( $row['module'] ) ? (string) $row['module'] : '';
		$level  = isset( $row['level'] ) ? (string) $row['level'] : '';
		$msg    = isset( $row['message'] ) ? (string) $row['message'] : '';
		$detail = isset( $row['detail_json'] ) ? (string) $row['detail_json'] : '';
		$id     = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$parts  = array();
		$parts[] = '<p><strong>' . esc_html( pili__( '摘要' ) ) . '</strong></p><p>' . esc_html( $msg ) . '</p>';
		if ( '' !== $detail ) {
			$parts[] = '<p><strong>' . esc_html( pili__( '详情' ) ) . '</strong></p><pre style="white-space:pre-wrap;word-break:break-all;">' . esc_html( $detail ) . '</pre>';
		}
		$code = isset( $row['code'] ) ? (string) $row['code'] : '';
		if ( '' !== $code ) {
			$parts[] = '<p><strong>' . esc_html( pili__( '代码' ) ) . '</strong> <code>' . esc_html( $code ) . '</code></p>';
		}
		$tid = isset( $row['task_id'] ) ? (string) $row['task_id'] : '';
		if ( '' !== $tid ) {
			$parts[] = '<p><strong>' . esc_html( pili__( '任务' ) ) . '</strong> ' . esc_html( $tid ) . '</p>';
		}

		return array(
			'id'           => $id,
			'created_at'   => '' !== $when ? self::format_datetime( $when ) : '—',
			'module_label' => self::module_label( $module ),
			'level_label'  => self::level_badge( $level ),
			'message'      => $msg,
			'detail'       => implode( '', $parts ),
		);
	}

	/**
	 * Site-local MySQL datetime. Never pass current_time( 'timestamp' ) into wp_date():
	 * that timestamp is already offset, and wp_date would add the timezone again.
	 *
	 * @param int $offset_seconds Seconds added to now.
	 * @return string
	 */
	private static function mysql_local( $offset_seconds = 0 ) {
		$offset_seconds = (int) $offset_seconds;
		if ( function_exists( 'wp_date' ) ) {
			$s = wp_date( 'Y-m-d H:i:s', time() + $offset_seconds );
			if ( is_string( $s ) && '' !== $s ) {
				return $s;
			}
		}
		if ( 0 === $offset_seconds ) {
			$mysql = current_time( 'mysql' );
			if ( is_string( $mysql ) && '' !== $mysql ) {
				return $mysql;
			}
		}
		return gmdate( 'Y-m-d H:i:s', time() + $offset_seconds );
	}

	/**
	 * @param string $mysql_dt Datetime stored as site-local MySQL.
	 * @return string
	 */
	private static function format_datetime( $mysql_dt ) {
		$mysql_dt = (string) $mysql_dt;
		$format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		try {
			$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$dt = date_create( $mysql_dt, $tz );
			if ( $dt instanceof DateTimeInterface && function_exists( 'wp_date' ) ) {
				return (string) wp_date( $format, $dt->getTimestamp() );
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
		return $mysql_dt;
	}

	/**
	 * @param string $module Module.
	 * @return string
	 */
	public static function module_label( $module ) {
		$map = array(
			'slim'    => pili__( '站点瘦身' ),
			'speed'   => pili__( '速度优化' ),
			'db'      => pili__( '数据库' ),
			'oss'     => pili__( '对象存储' ),
			'system'  => pili__( '系统' ),
			'migrate' => pili__( '迁移' ),
		);
		$module = self::normalize_module( $module );
		return isset( $map[ $module ] ) ? $map[ $module ] : ( '' !== $module ? $module : '—' );
	}

	/**
	 * @param string $level Level.
	 * @return string
	 */
	public static function level_badge( $level ) {
		$level = self::normalize_level( $level );
		$map   = array(
			'info'    => pili__( '信息' ),
			'warning' => pili__( '警告' ),
			'error'   => pili__( '错误' ),
		);
		$label = isset( $map[ $level ] ) ? $map[ $level ] : $level;
		$color = array(
			'info'    => '#1d4ed8',
			'warning' => '#c2410c',
			'error'   => '#b91c1c',
		);
		$hex = isset( $color[ $level ] ) ? $color[ $level ] : '#374151';
		return '<span style="color:' . esc_attr( $hex ) . ';font-weight:600;">' . esc_html( $label ) . '</span>';
	}

	/**
	 * @param string $module Module.
	 * @param bool   $allow_empty Allow empty.
	 * @return string
	 */
	public static function normalize_module( $module, $allow_empty = false ) {
		$module = sanitize_key( (string) $module );
		$ok     = array( 'slim', 'speed', 'db', 'oss', 'system', 'migrate' );
		if ( in_array( $module, $ok, true ) ) {
			return $module;
		}
		return $allow_empty ? '' : 'system';
	}

	/**
	 * @param string $level Level.
	 * @param bool   $allow_empty Allow empty.
	 * @return string
	 */
	public static function normalize_level( $level, $allow_empty = false ) {
		$level = sanitize_key( (string) $level );
		if ( 'warn' === $level || 'warning' === $level ) {
			return 'warning';
		}
		if ( 'err' === $level || 'error' === $level || 'fail' === $level ) {
			return 'error';
		}
		if ( 'success' === $level || 'debug' === $level || 'info' === $level ) {
			return 'info';
		}
		return $allow_empty ? '' : 'info';
	}

	/**
	 * @param mixed $detail Detail.
	 * @return mixed
	 */
	public static function sanitize_detail( $detail ) {
		if ( is_array( $detail ) ) {
			$out = array();
			foreach ( $detail as $k => $v ) {
				$key = is_string( $k ) ? self::sanitize_text( $k ) : $k;
				if ( is_string( $k ) && preg_match( '/secret|password|access.?key|token|signature/i', $k ) ) {
					$out[ $key ] = '***';
					continue;
				}
				$out[ $key ] = self::sanitize_detail( $v );
			}
			return $out;
		}
		if ( is_object( $detail ) ) {
			return self::sanitize_detail( (array) $detail );
		}
		if ( is_bool( $detail ) || is_int( $detail ) || is_float( $detail ) ) {
			return $detail;
		}
		return self::sanitize_text( (string) $detail );
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function sanitize_text( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$patterns = array(
			'/(AWSAccessKeyId|AccessKeyId|AccessKey|access_key|SecretKey|SecretAccessKey|secret_key)\s*[=:]\s*[^&\s]+/i',
			'/(Signature|x-oss-signature|x-amz-signature|authorization)\s*[=:]\s*[^&\s]+/i',
			'/\bAKID[A-Za-z0-9]+\b/',
			'/\bLTAI[A-Za-z0-9]+\b/',
			'/(sk|token)=[^&\s]+/i',
		);
		$repls = array( '$1=***', '$1=***', '***', '***', '$1=***' );
		foreach ( $patterns as $i => $re ) {
			$repl = isset( $repls[ $i ] ) ? $repls[ $i ] : '***';
			$next = preg_replace( $re, $repl, $text );
			$text = is_string( $next ) ? $next : '';
		}
		return $text;
	}

	/**
	 * @param string $code Code.
	 * @return string
	 */
	private static function sanitize_code( $code ) {
		$code = strtolower( (string) $code );
		$code = preg_replace( '/[^a-z0-9_:\-]/', '', $code );
		$code = is_string( $code ) ? substr( $code, 0, 64 ) : '';
		return $code;
	}

	/**
	 * @param string $value Date string.
	 * @param bool   $end_of_day Append 23:59:59 when date-only.
	 * @return string
	 */
	private static function normalize_date( $value, $end_of_day ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $end_of_day ? $value . ' 23:59:59' : $value . ' 00:00:00';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
			return str_replace( 'T', ' ', $value );
		}
		$ts = strtotime( $value );
		if ( ! $ts ) {
			return '';
		}
		return wp_date( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * @param string[] $cols Cells.
	 * @return string
	 */
	private static function csv_line( array $cols ) {
		$out = array();
		foreach ( $cols as $col ) {
			$col   = str_replace( array( "\r", "\n" ), ' ', (string) $col );
			$out[] = '"' . str_replace( '"', '""', $col ) . '"';
		}
		return implode( ',', $out );
	}
}

/**
 * Facade used at existing write points.
 *
 * @param string               $module Module.
 * @param string               $level  Level.
 * @param string               $message Message.
 * @param array<string,mixed>  $args Args.
 * @return bool
 */
function zaprocket_run_log( $module, $level, $message, array $args = array() ) {
	if ( ! class_exists( 'ZapRocket_Run_Log', false ) ) {
		return false;
	}
	return ZapRocket_Run_Log::write( $module, $level, $message, $args );
}

/**
 * Empty first paint; log_viewer loads via refresh_action.
 *
 * @return array<int,array<string,mixed>>
 */
function zaprocket_run_log_viewer_entries( $field = null, $value = null, $unique = '', $where = '', $parent = '' ) {
	unset( $field, $value, $unique, $where, $parent );
	return array();
}

/**
 * @param array                    $field  Field.
 * @param mixed                    $value  Value.
 * @param string                   $unique Unique.
 * @param string                   $where  Where.
 * @param string                   $parent Parent.
 * @param array<string,mixed>|null $query  Page query.
 * @return array{rows:array<int,array<string,mixed>>,total:int}
 */
function zaprocket_run_log_table_rows( $field, $value, $unique, $where, $parent, $query = null ) {
	unset( $field, $value, $unique, $where, $parent );
	return ZapRocket_Run_Log::paged_for_table( $query );
}

/**
 * @return array<int,array<string,mixed>>
 */
function zaprocket_run_log_stat_cards( $field = null, $value = null, $unique = '', $where = '', $parent = '' ) {
	unset( $field, $value, $unique, $where, $parent );
	return ZapRocket_Run_Log::summary_cards();
}
