<?php
/**
 * OSS admin AJAX, secret save, logs, notices.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin helpers.
 */
final class ZapRocket_Oss_Admin {

	/**
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_zaprocket_oss_test', array( $this, 'ajax_test' ) );
		add_action( 'wp_ajax_zaprocket_oss_dismiss_log', array( $this, 'ajax_dismiss_log' ) );
		add_action( 'wp_ajax_zaprocket_oss_retry_log', array( $this, 'ajax_retry_log' ) );
		add_action( 'wp_ajax_zaprocket_oss_replace_urls', array( $this, 'ajax_replace_urls' ) );
		add_action( 'admin_notices', array( $this, 'maybe_php_notice' ) );
		add_filter( 'pili_zaprocket_options_save', array( $this, 'on_save_options' ), 10, 1 );
		add_filter( 'option_zaprocket__oss', array( $this, 'inject_secret_for_form' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_ui' ), 30 );
	}

	/**
	 * Show decrypted SecretKey on the settings form; keep ciphertext out of domain options.
	 *
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	public function inject_secret_for_form( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array();
		}
		if ( ! is_admin() || ! ZapRocket_Context::can_manage() ) {
			return $value;
		}
		$value['zr_oss_secret_key'] = ZapRocket_Oss_S3::get_secret();
		return $value;
	}

	/**
	 * OSS settings CSS/JS + vendor presets (independent of cached plugin enqueue).
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_ui( $hook ) {
		if ( ! ZapRocket_Context::can_manage() ) {
			return;
		}
		if ( false === strpos( (string) $hook, 'zaprocket' ) ) {
			return;
		}
		$oss_css = ZAPROCKET_DIR . 'assets/css/admin-oss.css';
		wp_enqueue_style(
			'zaprocket-admin-oss',
			ZAPROCKET_URL . 'assets/css/admin-oss.css',
			array(),
			(string) ( @filemtime( $oss_css ) ?: ZAPROCKET_VERSION )
		);
		$oss_deps = array( 'jquery' );
		$toast    = self::enqueue_toast();
		if ( is_string( $toast ) && '' !== $toast ) {
			$oss_deps[] = $toast;
		}
		if ( function_exists( 'pili_asset_handle' ) ) {
			$fw = pili_asset_handle( 'framework' );
			if ( is_string( $fw ) && '' !== $fw ) {
				$oss_deps[] = $fw;
			}
			$uid = pili_asset_handle( 'admin-ui-deps' );
			if ( is_string( $uid ) && '' !== $uid ) {
				$oss_deps[] = $uid;
			}
		}
		wp_enqueue_script(
			'zaprocket-admin-oss',
			ZAPROCKET_URL . 'assets/js/admin-oss.js',
			$oss_deps,
			ZAPROCKET_VERSION . '.ossui6',
			true
		);
		// i18n: 与 ZapRocket_Plugin::enqueue_admin_assets 同袋；本钩子更晚，以此为准。
		$payload = array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'zaprocket_oss' ),
			'presets' => class_exists( 'ZapRocket_Oss_Providers', false ) ? ZapRocket_Oss_Providers::frontend_presets() : array(),
			'i18n'    => array(
				'ok'         => pili__( '连接成功。' ),
				'fail'       => pili__( '连接失败。' ),
				'done'       => pili__( '已处理。' ),
				'exampleFmt' => pili__( '填写示例：%s' ),
				'needAppId'  => pili__( '请填写腾讯云 APPID。未填写时无法保存对象存储设置。' ),
				'r2Domain'   => pili__( '未填写自定义公网域名时，媒体地址不会改写成 R2 API 域名。请绑定自定义域后再保存。' ),
				'foldMore'   => pili__( '展开详情' ),
				'foldLess'   => pili__( '收起' ),
				'prefixBad'  => pili__( '桶内基础目录前缀含有非法字符。不要填写 ../，也不要用 \\ : * ? " < > |。' ),
				'replaceOk'  => pili__( '替换完成。' ),
				'replaceNeed'=> pili__( '请填写要替换的旧地址和新地址。' ),
				'replaceSame'=> pili__( '旧地址和新地址不能相同。' ),
				'replaceBad' => pili__( '请填写完整的 http:// 或 https:// 地址。' ),
			),
		);
		wp_localize_script( 'zaprocket-admin-oss', 'zaprocketOss', $payload );
	}

	/**
	 * @return void
	 */
	public function maybe_php_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		return;
	}

	/**
	 * Test uses posted fields; does not persist.
	 *
	 * @return void
	 */
	public function ajax_test() {
		check_ajax_referer( 'zaprocket_oss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足。' ) ) );
		}

		$posted = array(
			'zr_oss_provider'        => sanitize_key( wp_unslash( $_POST['zr_oss_provider'] ?? '' ) ),
			'zr_oss_bucket'          => sanitize_text_field( wp_unslash( $_POST['zr_oss_bucket'] ?? '' ) ),
			'zr_oss_region'          => sanitize_text_field( wp_unslash( $_POST['zr_oss_region'] ?? '' ) ),
			'zr_oss_region_aliyun'   => sanitize_text_field( wp_unslash( $_POST['zr_oss_region_aliyun'] ?? '' ) ),
			'zr_oss_region_tencent'  => sanitize_text_field( wp_unslash( $_POST['zr_oss_region_tencent'] ?? '' ) ),
			'zr_oss_app_id'          => sanitize_text_field( wp_unslash( $_POST['zr_oss_app_id'] ?? '' ) ),
			'zr_oss_endpoint'        => sanitize_text_field( wp_unslash( $_POST['zr_oss_endpoint'] ?? '' ) ),
			'zr_oss_custom_domain'   => sanitize_text_field( wp_unslash( $_POST['zr_oss_custom_domain'] ?? '' ) ),
			'zr_oss_access_key'      => sanitize_text_field( wp_unslash( $_POST['zr_oss_access_key'] ?? '' ) ),
		);
		$sk = trim( (string) wp_unslash( $_POST['zr_oss_secret_key'] ?? '' ) );

		add_filter(
			'zaprocket_oss_settings',
			static function ( $settings ) use ( $posted ) {
				return array_merge( is_array( $settings ) ? $settings : array(), $posted );
			},
			99
		);

		if ( '' !== $sk ) {
			add_filter(
				'pre_option_' . ZapRocket_Oss_S3::SECRET_OPTION,
				static function () use ( $sk ) {
					return ZapRocket_Oss_S3::encrypt_secret( $sk );
				}
			);
		}

		ZapRocket_Oss_S3::reset_client();
		$result = ZapRocket_Oss_S3::test_connection();
		ZapRocket_Oss_S3::reset_client();

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}
		if ( class_exists( 'ZapRocket_Options', false ) && ZapRocket_Options::is_oss_test_log_enabled() && function_exists( 'zaprocket_run_log' ) ) {
			$msg = isset( $result['message'] ) ? (string) $result['message'] : pili__( '连接失败。' );
			zaprocket_run_log(
				'oss',
				'error',
				$msg,
				array(
					'code'   => 'oss_test',
					'detail' => array( 'success' => false ),
				)
			);
		}
		wp_send_json_error( $result );
	}

	/**
	 * Replace old media URL with new URL in post_content and postmeta (Aliyun OSS style).
	 *
	 * @return void
	 */
	public function ajax_replace_urls() {
		check_ajax_referer( 'zaprocket_oss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足。' ) ) );
		}
		$old = esc_url_raw( trim( (string) wp_unslash( $_POST['old_url'] ?? '' ) ) );
		$new = esc_url_raw( trim( (string) wp_unslash( $_POST['new_url'] ?? '' ) ) );
		if ( '' === $old || '' === $new ) {
			wp_send_json_error( array( 'message' => pili__( '请填写要替换的旧地址和新地址。' ) ) );
		}
		if ( $old === $new ) {
			wp_send_json_error( array( 'message' => pili__( '旧地址和新地址不能相同。' ) ) );
		}
		if ( ! preg_match( '#^https?://#i', $old ) || ! preg_match( '#^https?://#i', $new ) ) {
			wp_send_json_error( array( 'message' => pili__( '请填写完整的 http:// 或 https:// 地址。' ) ) );
		}
		if ( strlen( $old ) < 12 ) {
			wp_send_json_error( array( 'message' => pili__( '旧地址过短，已取消替换以免误改数据库。' ) ) );
		}

		global $wpdb;
		$posts = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
				$old,
				$new
			)
		);
		$meta  = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)",
				$old,
				$new
			)
		);
		if ( false === $posts || false === $meta ) {
			wp_send_json_error( array( 'message' => pili__( '替换失败，请稍后重试。' ) ) );
		}
		$posts = max( 0, (int) $posts );
		$meta  = max( 0, (int) $meta );
		$msg   = sprintf(
			/* translators: 1: post rows 2: postmeta rows */
			pili__( '替换成功。文章正文 %1$d 条，自定义字段 %2$d 条。' ),
			$posts,
			$meta
		);
		if ( function_exists( 'zaprocket_run_log' ) ) {
			zaprocket_run_log(
				'oss',
				'info',
				$msg,
				array(
					'code'   => 'db_url_replace',
					'detail' => array(
						'old_url'  => $old,
						'new_url'  => $new,
						'posts'    => $posts,
						'postmeta' => $meta,
					),
				)
			);
		}
		wp_send_json_success( array( 'message' => $msg ) );
	}

	/**
	 * @return void
	 */
	public function ajax_dismiss_log() {
		check_ajax_referer( 'zaprocket_oss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		$index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$logs  = self::get_logs();
		if ( isset( $logs[ $index ] ) ) {
			$id = (int) ( $logs[ $index ]['attachment_id'] ?? 0 );
			if ( $id > 0 ) {
				delete_post_meta( $id, ZapRocket_Oss_Upload::META_PENDING );
				delete_post_meta( $id, ZapRocket_Oss_Upload::META_RETRY );
			}
			unset( $logs[ $index ] );
			update_option( ZapRocket_Oss_S3::LOG_OPTION, array_values( $logs ), false );
		}
		wp_send_json_success();
	}

	/**
	 * @return void
	 */
	public function ajax_retry_log() {
		check_ajax_referer( 'zaprocket_oss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		$index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$logs  = self::get_logs();
		if ( ! isset( $logs[ $index ] ) ) {
			wp_send_json_error();
		}
		$id = (int) ( $logs[ $index ]['attachment_id'] ?? 0 );
		if ( $id < 1 ) {
			wp_send_json_error();
		}
		delete_post_meta( $id, ZapRocket_Oss_Upload::META_RETRY );
		if ( ! wp_next_scheduled( ZapRocket_Oss_Upload::CRON_HOOK, array( $id ) ) ) {
			wp_schedule_single_event( time() + 5, ZapRocket_Oss_Upload::CRON_HOOK, array( $id ) );
		}
		unset( $logs[ $index ] );
		update_option( ZapRocket_Oss_S3::LOG_OPTION, array_values( $logs ), false );
		wp_send_json_success();
	}

	/**
	 * Persist secret outside domain payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return array<string,mixed>
	 */
	public function on_save_options( $payload ) {
		if ( ! is_array( $payload ) ) {
			return $payload;
		}
		unset( $payload['zr_oss_replace_old'], $payload['zr_oss_replace_new'] );
		if ( isset( $payload['zr_oss_secret_key'] ) ) {
			$sk = trim( (string) $payload['zr_oss_secret_key'] );
			unset( $payload['zr_oss_secret_key'] );
			if ( '' !== $sk ) {
				ZapRocket_Oss_S3::maybe_save_secret( $sk );
			} else {
				delete_option( ZapRocket_Oss_S3::SECRET_OPTION );
			}
		}
		foreach ( array( 'zr_oss_enable', 'zr_oss_no_local', 'zr_oss_rename_enable' ) as $bool_key ) {
			if ( isset( $payload[ $bool_key ] ) ) {
				$payload[ $bool_key ] = ZapRocket_Options::is_on( $payload[ $bool_key ] );
			}
		}
		$provider = isset( $payload['zr_oss_provider'] ) ? sanitize_key( (string) $payload['zr_oss_provider'] ) : 'aliyun';
		if ( isset( $payload['zr_oss_bucket'] ) ) {
			$payload['zr_oss_bucket'] = ZapRocket_Oss_S3::normalize_bucket( (string) $payload['zr_oss_bucket'] );
		}
		if ( isset( $payload['zr_oss_endpoint'] ) ) {
			$bucket = isset( $payload['zr_oss_bucket'] ) ? (string) $payload['zr_oss_bucket'] : '';
			$ep     = trim( (string) $payload['zr_oss_endpoint'] );
			if ( preg_match( '#^https?://#i', $ep ) ) {
				$payload['zr_oss_endpoint'] = ZapRocket_Oss_S3::normalize_endpoint( $ep, $bucket, $provider );
			} else {
				$payload['zr_oss_endpoint'] = $ep;
			}
		}
		foreach ( array( 'zr_oss_region', 'zr_oss_region_aliyun', 'zr_oss_region_tencent', 'zr_oss_app_id', 'zr_oss_custom_domain', 'zr_oss_access_key', 'zr_oss_object_prefix', 'zr_oss_rename_prefix' ) as $trim_key ) {
			if ( isset( $payload[ $trim_key ] ) ) {
				$payload[ $trim_key ] = trim( (string) $payload[ $trim_key ] );
			}
		}
		if ( isset( $payload['zr_oss_app_id'] ) ) {
			$digits = preg_replace( '/\D+/', '', (string) $payload['zr_oss_app_id'] );
			$payload['zr_oss_app_id'] = is_string( $digits ) ? $digits : '';
		}
		if ( isset( $payload['zr_oss_region_aliyun'] ) && class_exists( 'ZapRocket_Oss_Regions', false ) ) {
			$ali = ZapRocket_Oss_Regions::aliyun();
			$id  = (string) $payload['zr_oss_region_aliyun'];
			if ( ! isset( $ali[ $id ] ) ) {
				$payload['zr_oss_region_aliyun'] = 'oss-cn-hangzhou';
			}
		}
		if ( isset( $payload['zr_oss_region_tencent'] ) && class_exists( 'ZapRocket_Oss_Regions', false ) ) {
			$tc = ZapRocket_Oss_Regions::tencent();
			$id = (string) $payload['zr_oss_region_tencent'];
			if ( ! isset( $tc[ $id ] ) ) {
				$payload['zr_oss_region_tencent'] = 'ap-guangzhou';
			}
		}
		if ( isset( $payload['zr_oss_object_prefix'] ) && class_exists( 'ZapRocket_Oss_Providers', false ) ) {
			$payload['zr_oss_object_prefix'] = ZapRocket_Oss_Providers::sanitize_object_prefix( (string) $payload['zr_oss_object_prefix'] );
			if ( '' === $payload['zr_oss_object_prefix'] ) {
				$payload['zr_oss_object_prefix'] = ZapRocket_Oss_Providers::default_object_prefix();
			}
		}
		if ( isset( $payload['zr_oss_rename_rule'] ) ) {
			$rule = sanitize_key( (string) $payload['zr_oss_rename_rule'] );
			$allow = array( 'md5', 'timestamp', 'sanitize', 'prefix' );
			$payload['zr_oss_rename_rule'] = in_array( $rule, $allow, true ) ? $rule : 'md5';
		}

		$enabled = ! empty( $payload['zr_oss_enable'] ) && ZapRocket_Options::is_on( $payload['zr_oss_enable'] );
		if ( isset( $payload['zr_oss_provider'] ) && $enabled && 'tencent' === $provider ) {
			$app = isset( $payload['zr_oss_app_id'] ) ? preg_replace( '/\D+/', '', (string) $payload['zr_oss_app_id'] ) : '';
			$app = is_string( $app ) ? $app : '';
			if ( '' === $app ) {
				$msg = pili__( '请填写腾讯云 APPID。未填写时无法保存对象存储设置。' );
				if ( wp_doing_ajax() ) {
					wp_send_json_error( array( 'message' => $msg ) );
				}
				wp_die( esc_html( $msg ) );
			}
		}

		ZapRocket_Oss_S3::reset_client();
		return $payload;
	}

	/**
	 * @param int    $attachment_id ID.
	 * @param string $code          Code.
	 * @param string $message       Message.
	 * @return void
	 */
	public static function log_error( $attachment_id, $code, $message ) {
		$logs   = self::get_logs();
		$logs[] = array(
			'time'          => time(),
			'attachment_id' => (int) $attachment_id,
			'code'          => sanitize_key( (string) $code ),
			'message'       => wp_strip_all_tags( (string) $message ),
		);
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}
		update_option( ZapRocket_Oss_S3::LOG_OPTION, $logs, false );
		$code   = (string) $code;
		$level  = ( 0 === strpos( sanitize_key( $code ), 'thumbs_missing' ) || 0 === strpos( $code, 'thumbs_missing' ) )
			? 'warning'
			: 'error';
		if ( function_exists( 'zaprocket_run_log' ) ) {
			zaprocket_run_log(
				'oss',
				$level,
				wp_strip_all_tags( (string) $message ),
				array(
					'code'     => $code,
					'ref_type' => 'oss_fail',
					'ref_id'   => (string) (int) $attachment_id,
					'detail'   => array(
						'attachment_id' => (int) $attachment_id,
						'code'          => $code,
					),
				)
			);
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_logs() {
		$logs = get_option( ZapRocket_Oss_S3::LOG_OPTION, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Password field badge.
	 *
	 * @return array<string,mixed>
	 */
	public static function secret_status() {
		$stored = get_option( ZapRocket_Oss_S3::SECRET_OPTION, '' );
		return array(
			'configured' => is_string( $stored ) && '' !== $stored,
			'label'      => ( is_string( $stored ) && '' !== $stored ) ? pili__( '已保存密钥（留空则不改）' ) : pili__( '未配置密钥' ),
		);
	}

	/**
	 * Load table field helpers.
	 *
	 * @return bool
	 */
	public static function table_ready() {
		if ( class_exists( '\Pili\Core\PILI_Field_table' ) ) {
			return true;
		}
		$dir = defined( 'PILI_CORE_DIR' ) ? PILI_CORE_DIR : '';
		$file = $dir ? trailingslashit( $dir ) . 'fields/table/table.php' : '';
		if ( $file && is_readable( $file ) ) {
			require_once $file;
		}
		return class_exists( '\Pili\Core\PILI_Field_table' );
	}

	/**
	 * Test-connection control (table action button, not WP core .button).
	 *
	 * @return string
	 */
	public static function test_button_html() {
		if ( ! self::table_ready() ) {
			return '';
		}
		$btn = \Pili\Core\PILI_Field_table::render_action_button(
			array(
				'label'         => pili__( '测试连接' ),
				'action_key'    => 'oss-test',
				'field_id'      => 'zr_oss_test',
				'loading_label' => pili__( '测试中…' ),
				'variant'       => 'solid',
				'attrs'         => array(
					'id' => 'zr-oss-test',
				),
			)
		);
		$wrap = \Pili\Core\PILI_Field_table::render_action_buttons_wrap( $btn );
		return '<div class="zr-oss-test">' . $wrap . '<div class="zr-oss-test__result" hidden></div></div>';
	}

	/**
	 * Prefill old/new URLs for the replace fields (not stored).
	 *
	 * @return array{old:string,new:string}
	 */
	public static function replace_url_defaults() {
		$uploads = wp_get_upload_dir();
		$old     = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		$new     = '';
		if ( class_exists( 'ZapRocket_Oss_Url', false ) && class_exists( 'ZapRocket_Oss_Providers', false ) ) {
			$prefix = ZapRocket_Oss_Providers::sanitize_object_prefix(
				(string) ( ZapRocket_Oss_S3::get_settings()['zr_oss_object_prefix'] ?? '' )
			);
			if ( '' === $prefix ) {
				$prefix = ZapRocket_Oss_Providers::default_object_prefix();
			}
			$new = untrailingslashit( ZapRocket_Oss_Url::get_object_url( $prefix ) );
		}
		return array(
			'old' => $old,
			'new' => $new,
		);
	}

	/**
	 * Replace action button (table action button, not a homemade input).
	 *
	 * @return string
	 */
	public static function replace_form_html() {
		if ( ! self::table_ready() ) {
			return '';
		}
		$btn = \Pili\Core\PILI_Field_table::render_action_button(
			array(
				'label'         => pili__( '开始替换' ),
				'action_key'    => 'oss-replace',
				'field_id'      => 'zr_oss_replace',
				'loading_label' => pili__( '替换中…' ),
				'variant'       => 'solid',
				'attrs'         => array(
					'id' => 'zr-oss-replace',
				),
			)
		);
		$wrap = \Pili\Core\PILI_Field_table::render_action_buttons_wrap( $btn );
		return '<div class="zr-oss-replace">' . $wrap . '<div class="zr-oss-test__result zr-oss-replace__result" hidden></div></div>';
	}

	/**
	 * Enqueue PILI toast (no extra form row).
	 *
	 * @return string Script handle, or empty.
	 */
	public static function enqueue_toast() {
		$file = defined( 'PILI_CORE_DIR' ) ? trailingslashit( PILI_CORE_DIR ) . 'fields/toast/toast.php' : '';
		if ( $file && is_readable( $file ) && ! class_exists( '\Pili\Core\PILI_Field_toast', false ) ) {
			require_once $file;
		}
		if ( ! class_exists( '\Pili\Core\PILI_Field_toast' ) ) {
			return '';
		}
		$field = new \Pili\Core\PILI_Field_toast(
			array(
				'id'       => 'zr_oss_toast',
				'type'     => 'toast',
				'position' => 'bottom-center',
				'bot'      => true,
			),
			'',
			ZAPROCKET_OPTION_ID,
			'options',
			''
		);
		$field->enqueue();
		return function_exists( 'pili_asset_handle' ) ? pili_asset_handle( 'field-toast' ) : '';
	}

	/**
	 * @param int $index Log index.
	 * @return string
	 */
	public static function log_row_actions_html( $index ) {
		if ( ! self::table_ready() ) {
			return '';
		}
		$index = absint( $index );
		$retry = \Pili\Core\PILI_Field_table::render_action_button(
			array(
				'label'         => pili__( '重试' ),
				'action_key'    => 'oss-retry',
				'field_id'      => 'zr_oss_logs',
				'loading_label' => pili__( '提交中…' ),
				'variant'       => 'primary',
				'attrs'         => array(
					'data-id' => (string) $index,
				),
			)
		);
		$dismiss = \Pili\Core\PILI_Field_table::render_action_button(
			array(
				'label'         => pili__( '忽略' ),
				'action_key'    => 'oss-dismiss',
				'field_id'      => 'zr_oss_logs',
				'loading_label' => pili__( '处理中…' ),
				'variant'       => 'default',
				'attrs'         => array(
					'data-id' => (string) $index,
				),
			)
		);
		return \Pili\Core\PILI_Field_table::render_action_buttons_wrap( $retry . $dismiss );
	}

	/**
	 * Table data_callback payload.
	 *
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public static function log_table_rows() {
		$logs = self::get_logs();
		$rows = array();
		foreach ( $logs as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$aid = (int) ( $row['attachment_id'] ?? 0 );
			$ts  = ! empty( $row['time'] ) ? wp_date( 'Y-m-d H:i', (int) $row['time'] ) : '';
			$rows[] = array(
				'id'            => (int) $i,
				'time'          => (string) $ts,
				'attachment'    => '#' . $aid,
				'message'       => (string) ( $row['message'] ?? '' ),
				'actions'       => self::log_row_actions_html( (int) $i ),
			);
		}
		return array(
			'rows'  => $rows,
			'total' => count( $rows ),
		);
	}
}

/**
 * Content field callback: test button.
 *
 * @return string
 */
function zaprocket_oss_test_button_html() {
	return class_exists( 'ZapRocket_Oss_Admin' ) ? ZapRocket_Oss_Admin::test_button_html() : '';
}

/**
 * Content field callback: database URL replace.
 *
 * @return string
 */
function zaprocket_oss_replace_form_html() {
	return class_exists( 'ZapRocket_Oss_Admin' ) ? ZapRocket_Oss_Admin::replace_form_html() : '';
}

/**
 * Table field data_callback.
 *
 * @return array{rows:array<int,array<string,mixed>>,total:int}
 */
function zaprocket_oss_log_table_rows() {
	if ( ! class_exists( 'ZapRocket_Oss_Admin' ) ) {
		return array(
			'rows'  => array(),
			'total' => 0,
		);
	}
	return ZapRocket_Oss_Admin::log_table_rows();
}

/**
 * Provider tutorial (first paint; JS updates on change).
 *
 * @return string
 */
function zaprocket_oss_guide_html() {
	$provider = 'aliyun';
	if ( class_exists( 'ZapRocket_Options' ) ) {
		$provider = (string) ZapRocket_Options::get( 'oss', 'zr_oss_provider', 'aliyun' );
	}
	if ( class_exists( 'ZapRocket_Oss_Providers' ) ) {
		return ZapRocket_Oss_Providers::guide_html( $provider );
	}
	return '';
}

/**
 * Block empty Tencent APPID when OSS is enabled.
 *
 * @param mixed $value Field value.
 * @return string Empty string when valid.
 */
function zaprocket_oss_validate_app_id( $value ) {
	$posted = array();
	if ( isset( $_POST['zaprocket_options'] ) && is_array( $_POST['zaprocket_options'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- save nonce already checked by PILI.
		$posted = wp_unslash( $_POST['zaprocket_options'] );
	}
	$provider = isset( $posted['zr_oss_provider'] ) ? sanitize_key( (string) $posted['zr_oss_provider'] ) : '';
	$enabled  = isset( $posted['zr_oss_enable'] ) && class_exists( 'ZapRocket_Options' )
		? ZapRocket_Options::is_on( $posted['zr_oss_enable'] )
		: false;
	if ( ! $enabled || 'tencent' !== $provider ) {
		return '';
	}
	$digits = preg_replace( '/\D+/', '', (string) $value );
	$digits = is_string( $digits ) ? $digits : '';
	if ( '' === $digits ) {
		return pili__( '请填写腾讯云 APPID。未填写时无法保存对象存储设置。' );
	}
	return '';
}

/**
 * Reject illegal object-key prefix characters.
 *
 * @param mixed $value Field value.
 * @return string Empty when valid.
 */
function zaprocket_oss_validate_prefix( $value ) {
	$value = (string) $value;
	if ( '' === trim( $value ) ) {
		return '';
	}
	if ( false !== strpos( $value, '..' ) || preg_match( '/[<>:"|?*\\\\]/', $value ) ) {
		return pili__( '桶内基础目录前缀含有非法字符。不要填写 ../，也不要用 \\ : * ? " < > |。' );
	}
	return '';
}
