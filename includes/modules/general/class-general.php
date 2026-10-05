<?php
/**
 * Overview helpers: recommend preset + overlapping-plugin notice.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General-domain AJAX and admin notices.
 */
final class ZapRocket_Module_General {

	const NONCE = 'zaprocket_recommend';

	/**
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_ajax_zaprocket_recommend', array( $this, 'ajax_recommend' ) );
		add_action( 'admin_notices', array( $this, 'conflict_notice' ) );
	}

	/**
	 * Low-risk slim keys only. Does not touch XML-RPC, comments, guest REST.
	 *
	 * @return array<string,bool>
	 */
	public static function recommend_slim_fields() {
		return array(
			'zr_slim_disable_emojis'    => true,
			'zr_slim_disable_self_ping' => true,
			'zr_slim_remove_wlw'        => true,
			'zr_slim_remove_rsd'        => true,
			'zr_slim_remove_wp_version' => true,
		);
	}

	/**
	 * Load table field helpers (action button markup / CSS).
	 *
	 * @return bool
	 */
	public static function table_ready() {
		if ( class_exists( '\Pili\Core\PILI_Field_table' ) ) {
			return true;
		}
		$dir  = defined( 'PILI_CORE_DIR' ) ? PILI_CORE_DIR : '';
		$file = $dir ? trailingslashit( $dir ) . 'fields/table/table.php' : '';
		if ( $file && is_readable( $file ) ) {
			require_once $file;
		}
		return class_exists( '\Pili\Core\PILI_Field_table' );
	}

	/**
	 * Enqueue table action-button styles on pages without a table field.
	 *
	 * @return void
	 */
	public static function enqueue_action_button_assets() {
		if ( ! self::table_ready() || ! class_exists( '\Pili\Core\PILI_Setup' ) ) {
			return;
		}
		$handle = function_exists( 'pili_asset_handle' ) ? pili_asset_handle( 'field-table-css' ) : 'pili-field-table-css';
		$path   = trailingslashit( (string) \Pili\Core\PILI_Setup::$dir ) . 'assets/css/fields/table.css';
		$ver    = is_readable( $path ) ? (string) filemtime( $path ) : '1';
		wp_enqueue_style(
			$handle,
			untrailingslashit( (string) \Pili\Core\PILI_Setup::$url ) . '/assets/css/fields/table.css',
			array(),
			$ver
		);
	}

	/**
	 * Recommend control (table action button, not WP core .button, not nested in a callout).
	 *
	 * @return string
	 */
	public static function recommend_button_html() {
		if ( ! self::table_ready() ) {
			return '';
		}
		self::enqueue_action_button_assets();
		$btn = \Pili\Core\PILI_Field_table::render_action_button(
			array(
				'label'         => pili__( '套用低风险推荐' ),
				'action_key'    => 'recommend',
				'field_id'      => 'zr_gen_recommend',
				'loading_label' => pili__( '正在写入推荐配置…' ),
				'variant'       => 'solid',
				'attrs'         => array(
					'data-zr-act' => 'recommend',
				),
			)
		);
		$wrap = \Pili\Core\PILI_Field_table::render_action_buttons_wrap( $btn );
		return $wrap . ' <span data-zr-recommend-status></span>';
	}

	/**
	 * @return void
	 */
	public function ajax_recommend() {
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );

		$ok = ZapRocket_Options::save( 'slim', self::recommend_slim_fields() );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => pili__( '写入推荐配置失败。' ) ), 500 );
		}

		if ( function_exists( 'zaprocket_run_log' ) ) {
			zaprocket_run_log(
				'slim',
				'info',
				pili__( '已套用低风险推荐配置（Emoji、Self-Ping、RSD、WLW、版本号）。' ),
				array(
					'code'   => 'recommend',
					'detail' => array( 'keys' => array_keys( self::recommend_slim_fields() ) ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => pili__( '已打开低风险瘦身项。刷新本页后，可在「关掉用不到的功能」「网页源代码瘦身」里核对。高风险项（XML-RPC、评论、访客 REST）未改。' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function conflict_notice() {
		if ( ! ZapRocket_Context::can_manage() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page identity only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' === $page || 0 !== strpos( $page, 'zaprocket' ) ) {
			return;
		}

		$hits = self::active_overlaps();
		if ( array() === $hits ) {
			return;
		}

		$names = array();
		foreach ( $hits as $row ) {
			$names[] = $row['name'];
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: plugin names */
				pili__( '同时开着其它性能插件时，可能重复改同一处：%s。建议只留一套负责前台加速。' ),
				implode( '、', $names )
			)
		);
		echo '</p></div>';
	}

	/**
	 * @return array<int,array{file:string,name:string}>
	 */
	public static function active_overlaps() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$known = array(
			'wp-rocket/wp-rocket.php'                 => 'WP Rocket',
			'wpturbo/index.php'                       => 'WPTurbo',
			'wpturbo/wpturbo.php'                     => 'WPTurbo',
			'autoptimize/autoptimize.php'             => 'Autoptimize',
			'litespeed-cache/litespeed-cache.php'     => 'LiteSpeed Cache',
			'w3-total-cache/w3-total-cache.php'       => 'W3 Total Cache',
			'wp-super-cache/wp-cache.php'             => 'WP Super Cache',
			'perfmatters/perfmatters.php'             => 'Perfmatters',
			'flying-press/flying-press.php'           => 'FlyingPress',
			'sg-cachepress/sg-cachepress.php'         => 'SG Optimizer',
			'nitropack/main.php'                      => 'NitroPack',
		);

		$out = array();
		foreach ( $known as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$out[] = array(
					'file' => $file,
					'name' => $name,
				);
			}
		}
		return $out;
	}

	/**
	 * Overview KPI cards.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function overview_cards() {
		$on    = ZapRocket_Options::is_plugin_enabled();
		$cache = ZapRocket_Options::speed_on( 'zr_speed_cache_enable', false );
		$speed = array(
			'zr_speed_cdn_enable',
			'zr_speed_lazy_images',
			'zr_speed_js_defer',
			'zr_speed_js_delay',
			'zr_speed_js_minify',
			'zr_speed_css_minify',
			'zr_speed_js_combine',
			'zr_speed_font_local',
			'zr_speed_preload_links',
		);
		$n = 0;
		foreach ( $speed as $key ) {
			if ( ZapRocket_Options::speed_on( $key, false ) ) {
				++$n;
			}
		}
		if ( $cache ) {
			++$n;
		}

		$logs = 0;
		if ( class_exists( 'ZapRocket_Run_Log', false ) && ZapRocket_Run_Log::table_exists() ) {
			global $wpdb;
			$table = ZapRocket_Run_Log::table_name();
			if ( '' !== $table && preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
				$logs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$card = static function ( $label, $value, $icon, $tone, $hint ) {
			return array(
				'label' => $label,
				'value' => $value,
				'icon'  => $icon,
				'tone'  => $tone,
				'trend' => array(
					'direction' => 'flat',
					'text'      => $hint,
					'suffix'    => '',
				),
			);
		};

		return array(
			$card(
				pili__( '前台优化' ),
				$on ? pili__( '开' ) : pili__( '关' ),
				'ri-flashlight-line',
				$on ? 'green' : 'orange',
				$on ? pili__( '总开关已打开' ) : pili__( '总开关已关闭' )
			),
			$card(
				pili__( '整页缓存' ),
				$cache ? pili__( '开' ) : pili__( '关' ),
				'ri-database-2-line',
				$cache ? 'blue' : 'yellow',
				$cache ? pili__( '未登录访客可读磁盘页' ) : pili__( '默认关闭' )
			),
			$card(
				pili__( '已开速度项' ),
				(string) $n,
				'ri-speed-up-line',
				'purple',
				pili__( 'CDN / 懒加载 / 压缩 / 缓存等' )
			),
			$card(
				pili__( '运行日志' ),
				(string) $logs,
				'ri-file-list-3-line',
				'teal',
				pili__( '插件运行日志条数' )
			),
		);
	}
}

/**
 * Overview stat_cards callback.
 *
 * @return array<int,array<string,mixed>>
 */
function zaprocket_overview_stat_cards( $field = null, $value = null, $unique = '', $where = '', $parent = '' ) {
	unset( $field, $value, $unique, $where, $parent );
	return ZapRocket_Module_General::overview_cards();
}

/**
 * Overview recommend button (PILI table action button).
 *
 * @return string
 */
function zaprocket_recommend_button_html( $field = null, $value = null, $unique = '', $where = '', $parent = '' ) {
	unset( $field, $value, $unique, $where, $parent );
	return class_exists( 'ZapRocket_Module_General' ) ? ZapRocket_Module_General::recommend_button_html() : '';
}
