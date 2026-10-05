<?php
/**
 * Optimize activity log table.
 *
 * Table: {$wpdb->prefix}zaprocket_opt_log
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register + install optimize log table via pili db-sdk.
 */
final class ZapRocket_Optimize_Log {

	const SLUG = 'zaprocket_opt_log';

	/**
	 * Register schema (no DDL until install()).
	 *
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
					'id'           => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'action'       => "varchar(64) NOT NULL DEFAULT ''",
					'status'       => "varchar(16) NOT NULL DEFAULT 'ok'",
					'message'      => 'text NOT NULL',
					'rows_affected'=> 'int(11) NOT NULL DEFAULT 0',
					'context_json' => 'longtext NULL',
					'created_at'   => 'datetime NOT NULL',
					'retain_until' => 'datetime NOT NULL',
				),
				'indexes' => array(
					'idx_created' => array( 'created_at' ),
					'idx_retain'  => array( 'retain_until' ),
					'idx_action'  => array( 'action' ),
					'idx_status'  => array( 'status' ),
				),
			)
		);
	}

	/**
	 * Install / upgrade table. Call from activation hook.
	 *
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
		return $result;
	}

	/**
	 * Prefixed table name.
	 *
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
	 * Write a log row.
	 *
	 * Write a log row and drop expired rows.
	 *
	 * @param string               $action Action key.
	 * @param string               $message Message.
	 * @param array<string,mixed>  $args Optional status / rows_affected / context.
	 * @return bool
	 */
	public static function write( $action, $message, array $args = array() ) {
		if ( ! ZapRocket_Options::is_logging_enabled() ) {
			return false;
		}
		if ( ! self::table_exists() ) {
			self::install();
			if ( ! self::table_exists() ) {
				return false;
			}
		}

		global $wpdb;
		$table  = self::table_name();
		if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
			return false;
		}
		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'ok';
		if ( ! in_array( $status, array( 'ok', 'fail', 'error', 'pending', 'success' ), true ) ) {
			$status = 'ok';
		}
		$rows = isset( $args['rows_affected'] ) ? (int) $args['rows_affected'] : 0;
		$ctx  = isset( $args['context'] ) && is_array( $args['context'] ) ? wp_json_encode( $args['context'] ) : null;
		$now = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s' ) : current_time( 'mysql' );
		if ( ! is_string( $now ) || '' === $now ) {
			$now = current_time( 'mysql' );
		}
		$keep = function_exists( 'wp_date' )
			? wp_date( 'Y-m-d H:i:s', time() + ( DAY_IN_SECONDS * 90 ) )
			: gmdate( 'Y-m-d H:i:s', time() + ( DAY_IN_SECONDS * 90 ) );
		if ( ! is_string( $keep ) || '' === $keep ) {
			$keep = gmdate( 'Y-m-d H:i:s', time() + ( DAY_IN_SECONDS * 90 ) );
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'action'        => sanitize_key( (string) $action ),
				'status'        => $status,
				'message'       => wp_kses_post( (string) $message ),
				'rows_affected' => $rows,
				'context_json'  => $ctx,
				'created_at'    => $now,
				'retain_until'  => $keep,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE retain_until < %s LIMIT 200", $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from helper.

		return false !== $ok;
	}

	/**
	 * Whether the log table exists.
	 *
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
	 * KPI cards for the log page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function summary_cards() {
		$dash  = '—';
		$empty = array(
			array(
				'label' => pili__( '记录条数' ),
				'value' => $dash,
				'icon'  => 'ri-file-list-3-line',
				'tone'  => 'blue',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '还没有写入' ),
					'suffix'    => '',
				),
			),
			array(
				'label' => pili__( '上次清理' ),
				'value' => $dash,
				'icon'  => 'ri-calendar-check-line',
				'tone'  => 'green',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '清理后会出现时间' ),
					'suffix'    => '',
				),
			),
			array(
				'label' => pili__( '成功次数' ),
				'value' => $dash,
				'icon'  => 'ri-checkbox-circle-line',
				'tone'  => 'teal',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '按状态统计' ),
					'suffix'    => '',
				),
			),
			array(
				'label' => pili__( '失败次数' ),
				'value' => $dash,
				'icon'  => 'ri-error-warning-line',
				'tone'  => 'orange',
				'trend' => array(
					'direction' => 'flat',
					'text'      => pili__( '按状态统计' ),
					'suffix'    => '',
				),
			),
		);

		if ( ! self::table_exists() ) {
			return $empty;
		}

		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			"SELECT COUNT(*) AS total_n,
				SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) AS ok_n,
				SUM(CASE WHEN status <> 'ok' THEN 1 ELSE 0 END) AS fail_n,
				MAX(created_at) AS last_at
			FROM {$table}",
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return $empty;
		}

		$total = (int) ( $row['total_n'] ?? 0 );
		$ok    = (int) ( $row['ok_n'] ?? 0 );
		$fail  = (int) ( $row['fail_n'] ?? 0 );
		$last  = isset( $row['last_at'] ) ? (string) $row['last_at'] : '';

		$empty[0]['value'] = $total;
		$empty[0]['trend']['text'] = $total > 0
			? pili__( '表里已有记录' )
			: pili__( '还没有写入' );
		$empty[1]['value'] = '' !== $last ? self::format_datetime( $last ) : $dash;
		$empty[1]['trend']['text'] = '' !== $last
			? pili__( '最近一次写入' )
			: pili__( '清理后会出现时间' );
		$empty[2]['value'] = $ok;
		$empty[3]['value'] = $fail;

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

		$args = array(
			'select'    => 'id, created_at, action, status, rows_affected, message',
			'order_sql' => 'created_at DESC, id DESC',
			'page'      => $page,
			'per_page'  => $per_page,
		);

		if ( '' !== $search ) {
			global $wpdb;
			$like                 = '%' . $wpdb->esc_like( $search ) . '%';
			$args['where_sql']    = '(action LIKE %s OR status LIKE %s OR message LIKE %s)';
			$args['where_values'] = array( $like, $like, $like );
		}

		$pack = pili_table_sql_paged( self::table_name(), $args );
		if ( is_wp_error( $pack ) || ! is_array( $pack ) ) {
			return $empty;
		}

		$rows = isset( $pack['rows'] ) && is_array( $pack['rows'] ) ? $pack['rows'] : array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = self::format_table_row( $row );
		}

		return array(
			'rows'  => $out,
			'total' => isset( $pack['total'] ) ? max( 0, (int) $pack['total'] ) : 0,
		);
	}

	/**
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_table_row( array $row ) {
		$action = isset( $row['action'] ) ? (string) $row['action'] : '';
		$status = isset( $row['status'] ) ? (string) $row['status'] : '';
		$when   = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
		$msg    = isset( $row['message'] ) ? (string) $row['message'] : '';

		return array(
			'id'            => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'created_at'    => '' !== $when ? self::format_datetime( $when ) : '—',
			'action_label'  => self::action_label( $action ),
			'status_label'  => self::status_label( $status ),
			'rows_affected' => isset( $row['rows_affected'] ) ? (string) (int) $row['rows_affected'] : '0',
			'message'       => $msg,
		);
	}

	/**
	 * @param string $mysql_dt Datetime.
	 * @return string
	 */
	private static function format_datetime( $mysql_dt ) {
		$ts = strtotime( (string) $mysql_dt );
		if ( ! $ts ) {
			return (string) $mysql_dt;
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		if ( function_exists( 'wp_date' ) ) {
			return (string) wp_date( $format, $ts );
		}
		return (string) date_i18n( $format, $ts );
	}

	/**
	 * @param string $action Action key.
	 * @return string
	 */
	public static function action_label( $action ) {
		$action = sanitize_key( (string) $action );
		$map    = array(
			'revisions'           => pili__( '修订版本' ),
			'autodrafts'          => pili__( '自动草稿' ),
			'trash_posts'         => pili__( '回收站文章' ),
			'spam_comments'       => pili__( '垃圾评论' ),
			'trash_comments'      => pili__( '回收站评论' ),
			'expired_transients'  => pili__( '过期临时数据' ),
			'orphaned_meta'       => pili__( '孤立元数据' ),
			'optimize_tables'     => pili__( '整理数据表' ),
			'schedule'            => pili__( '定时清理' ),
			'scan'                => pili__( '扫描 / 体积估算' ),
			'run'                 => pili__( '手动清理' ),
			'recommend'           => pili__( '推荐配置' ),
		);
		return isset( $map[ $action ] ) ? $map[ $action ] : ( '' !== $action ? $action : '—' );
	}

	/**
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$status = sanitize_key( (string) $status );
		$map    = array(
			'ok'      => pili__( '成功' ),
			'success' => pili__( '成功' ),
			'fail'    => pili__( '失败' ),
			'error'   => pili__( '失败' ),
			'pending' => pili__( '进行中' ),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : ( '' !== $status ? $status : '—' );
	}
}

/**
 * Table field data_callback (string callable).
 *
 * @param array                    $field  Field.
 * @param mixed                    $value  Value.
 * @param string                   $unique Unique.
 * @param string                   $where  Where.
 * @param string                   $parent Parent.
 * @param array<string,mixed>|null $query  Page query.
 * @return array{rows:array<int,array<string,mixed>>,total:int}
 */
function zaprocket_opt_log_table_rows( $field, $value, $unique, $where, $parent, $query = null ) {
	unset( $field, $value, $unique, $where, $parent );
	return ZapRocket_Optimize_Log::paged_for_table( $query );
}

/**
 * Stat cards callback.
 *
 * @return array<int,array<string,mixed>>
 */
function zaprocket_opt_log_stat_cards( $field = null, $value = null, $unique = '', $where = '', $parent = '' ) {
	unset( $field, $value, $unique, $where, $parent );
	return ZapRocket_Optimize_Log::summary_cards();
}
