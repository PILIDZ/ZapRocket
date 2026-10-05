<?php
/**
 * ZapRocket plugin bootstrap (admin options + frontend loader).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin singleton.
 */
final class ZapRocket_Plugin {

	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Activation: install run log and migrate tables.
	 *
	 * @return void
	 */
	public static function activate() {
		$core = function_exists( 'zaprocket_find_core_dir' ) ? zaprocket_find_core_dir() : '';
		if ( '' === $core || ! is_readable( $core . 'bootstrap.php' ) ) {
			return;
		}
		if ( ! defined( 'PILI_CORE_DIR' ) ) {
			define( 'PILI_CORE_DIR', $core );
		}
		if ( ! function_exists( 'pili_boot' ) ) {
			require_once $core . 'bootstrap.php';
		}

		pili_boot( self::boot_config() );
		if ( class_exists( 'ZapRocket_Run_Log', false ) ) {
			ZapRocket_Run_Log::install();
		}
		require_once ZAPROCKET_DIR . 'includes/modules/oss/class-migrate-db.php';
		if ( class_exists( 'ZapRocket_Oss_Migrate_Db', false ) ) {
			ZapRocket_Oss_Migrate_Db::install();
		}

		if ( ! get_option( 'zaprocket__general' ) ) {
			add_option(
				'zaprocket__general',
				array(
					'zr_gen_enabled'            => true,
					'zr_gen_uninstall_cleanup'  => false,
				),
				'',
				false
			);
		}
	}

	/**
	 * Deactivation: clear scheduled events only (keep data).
	 *
	 * @return void
	 */
	public static function deactivate() {
		$hook = 'zaprocket_db_optimize_event';
		$ts   = wp_next_scheduled( $hook );
		if ( $ts ) {
			wp_unschedule_event( $ts, $hook );
		}
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( 'zaprocket_oss_upload_sizes' );
			wp_unschedule_hook( 'zaprocket_oss_migrate_tick' );
			wp_unschedule_hook( 'zaprocket_run_log_purge' );
		}
	}

	/**
	 * Shared pili_boot config.
	 *
	 * @return array<string,mixed>
	 */
	public static function boot_config() {
		return array(
			'instance_id'   => 'zaprocket',
			'option_prefix' => 'zaprocket__',
			'text_domain'   => 'zaprocket-wp',
			'filter_ns'     => 'zaprocket',
			'ajax_ns'       => 'zaprocket',
			'asset_prefix'  => 'zaprocket',
			'css_scope'     => 'pili-inst-zaprocket',
			'event_ns'      => 'pili:zaprocket',
			'write_ok_key'  => 'zaprocket_options_domain_write_ok',
		);
	}

	/**
	 * Boot framework, domains, admin page, frontend loader.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		if ( ! function_exists( 'pili_boot' ) ) {
			return;
		}

		pili_boot( self::boot_config() );
		// i18n: 文本域 zaprocket；顶栏可覆盖用户偏好，不改 WordPress 站点语言。
		ZapRocket_I18n::register();
		ZapRocket_I18n::load_preferred_textdomain();
		$this->register_domains();
		add_filter(
			'pili_migrate_option_unique',
			static function () {
				return ZAPROCKET_OPTION_ID;
			}
		);
		pili_options_sdk_ready_greenfield( ZAPROCKET_OPTION_ID );
		if ( class_exists( 'ZapRocket_Run_Log', false ) ) {
			ZapRocket_Run_Log::register();
			ZapRocket_Run_Log::hooks();
		}

		add_action( 'admin_init', array( $this, 'guard_admin_page' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 20 );

		if ( is_admin() ) {
			$this->register_admin();
		}

		zaprocket_load_modules();
	}

	/**
	 * Register four storage domains + field prefix map.
	 *
	 * @return void
	 */
	private function register_domains() {
		add_filter(
			'pili_options_domains',
			static function () {
				return array( 'slim', 'speed', 'dbopt', 'general', 'oss' );
			},
			99
		);

		add_filter(
			'pili_options_domain_prefix_map',
			static function ( $map ) {
				$map = is_array( $map ) ? $map : array();
				$map['zr_slim_']  = 'slim';
				$map['zr_speed_'] = 'speed';
				$map['zr_db_']    = 'dbopt';
				$map['zr_gen_']   = 'general';
				$map['zr_oss_']   = 'oss';
				return $map;
			}
		);

		add_filter(
			'pili_section_loader_roots',
			static function ( $roots ) {
				$roots   = is_array( $roots ) ? $roots : array();
				$roots[] = wp_normalize_path( untrailingslashit( ZAPROCKET_DIR ) );
				return $roots;
			}
		);
	}

	/**
	 * Sidebar footer: author WeChat QR for custom plugin work.
	 *
	 * @param mixed $admin Options instance (unused).
	 * @return string
	 */
	public static function render_sidebar_footer( $admin = null ) {
		unset( $admin );
		$src = ZAPROCKET_URL . 'assets/img/kaifa.png';
		$html  = '<div class="zr-sidebar-dev">';
		$html .= '<details id="zr-sidebar-dev-fold" class="zr-sidebar-dev__fold">';
		$html .= '<summary class="zr-sidebar-dev__title">' . esc_html( pili__( '定制插件联系开发者' ) ) . '</summary>';
		$html .= '<img class="zr-sidebar-dev__qr" src="' . esc_url( $src ) . '" alt="' . esc_attr( pili__( '作者微信二维码' ) ) . '" width="168" height="168" />';
		$html .= '</details>';
		$html .= '<a class="zr-sidebar-dev__more" href="https://www.pilipost.net/" target="_blank" rel="noopener noreferrer">' . esc_html( pili__( '更多插件产品' ) ) . '</a>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Create options page + lazy sections.
	 *
	 * @return void
	 */
	private function register_admin() {
		if ( ! class_exists( '\Pili\Core\PILI', false ) ) {
			return;
		}

		require_once ZAPROCKET_DIR . 'admin/welcome.php';

		\Pili\Core\PILI::createOptions(
			ZAPROCKET_OPTION_ID,
			array(
				'menu_title'      => pili__( '闪电WP性能' ),
				'menu_slug'       => 'zaprocket',
				'menu_type'       => 'menu',
				'menu_capability' => 'manage_options',
				'menu_icon'       => 'dashicons-performance',
				'menu_position'   => 58,
				'save_defaults'   => true,
				'ajax_save'       => true,
				'version'         => 'v' . ZAPROCKET_VERSION,
				'framework_title' => sprintf(
					/* translators: %s: version html */
					pili__( '闪电WP性能 %s' ),
					'<small>v' . esc_html( ZAPROCKET_VERSION ) . '</small>'
				),
				'header_bar_callback'     => array( 'ZapRocket_I18n', 'render_header_language_switcher' ),
				'sidebar_footer_callback' => array( __CLASS__, 'render_sidebar_footer' ),
				'copyright'               => '',
				'welcome_brand_mark'        => ZAPROCKET_URL . 'assets/img/danlogo.svg',
				'sidebar_brand_mark'        => ZAPROCKET_URL . 'assets/img/danlogo.svg',
				'welcome_content_callback'  => 'zaprocket_render_welcome_page',
			)
		);

		add_filter(
			'pili_lazy_sections_enabled',
			static function ( $enabled, $unique ) {
				if ( ZAPROCKET_OPTION_ID === (string) $unique ) {
					return true;
				}
				return (bool) $enabled;
			},
			10,
			2
		);

		add_filter(
			'pili_lazy_assets_enabled',
			static function ( $enabled, $unique ) {
				if ( ZAPROCKET_OPTION_ID === (string) $unique ) {
					return true;
				}
				return (bool) $enabled;
			},
			10,
			2
		);

		add_filter( 'pili_skip_options_page_media_enqueue', '__return_true' );

		require_once ZAPROCKET_DIR . 'admin/register-sections.php';
		zaprocket_register_sections();
	}

	/**
	 * Block non-admins from the settings screen (defence in depth).
	 *
	 * @return void
	 */
	public function guard_admin_page() {
		if ( ! is_admin() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page identity only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'zaprocket' !== $page ) {
			return;
		}
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_die(
				esc_html( pili__( '您没有权限访问闪电WP性能设置页面。' ) ),
				esc_html( pili__( '权限不足' ) ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Extra admin CSS on the ZapRocket options screen.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! ZapRocket_Context::can_manage() ) {
			return;
		}
		$hook = (string) $hook;
		if ( false === strpos( $hook, 'zaprocket' ) ) {
			return;
		}
		if ( ! class_exists( 'ZapRocket_Module_Cleaner', false ) || ! class_exists( 'ZapRocket_Module_General', false ) || ! class_exists( 'ZapRocket_Module_Page_Cache', false ) ) {
			return;
		}
		if ( class_exists( 'ZapRocket_I18n', false ) ) {
			ZapRocket_I18n::enqueue_js_runtime();
		}
		if ( class_exists( 'ZapRocket_Oss_Admin', false ) ) {
			$oss_deps = array( 'jquery' );
			$toast    = ZapRocket_Oss_Admin::enqueue_toast();
			if ( is_string( $toast ) && '' !== $toast ) {
				$oss_deps[] = $toast;
			}
			wp_enqueue_style(
				'zaprocket-admin-oss',
				ZAPROCKET_URL . 'assets/css/admin-oss.css',
				array(),
				(string) ( @filemtime( ZAPROCKET_DIR . 'assets/css/admin-oss.css' ) ?: ZAPROCKET_VERSION )
			);
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
			// i18n: PHP 译好后传给 JS（wp_localize_script），禁止在 JS 里硬写多语言。
			wp_localize_script(
				'zaprocket-admin-oss',
				'zaprocketOss',
				array(
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'zaprocket_oss' ),
					'presets' => class_exists( 'ZapRocket_Oss_Providers', false ) ? ZapRocket_Oss_Providers::frontend_presets() : array(),
					'i18n'    => array(
						'ok'         => pili__( '连接成功。' ),
						'fail'       => pili__( '连接失败。' ),
						'done'       => pili__( '已处理。' ),
						'exampleFmt' => pili__( '填写示例：%s' ),
					),
				)
			);
		}
		$extra_deps = array();
		if ( function_exists( 'pili_asset_handle' ) ) {
			$fw = pili_asset_handle( 'framework' );
			if ( is_string( $fw ) && '' !== $fw && function_exists( 'wp_style_is' ) && wp_style_is( $fw, 'registered' ) ) {
				$extra_deps[] = $fw;
			}
		}
		wp_enqueue_script(
			'zaprocket-admin-locale',
			ZAPROCKET_URL . 'assets/js/admin-locale.js',
			array( 'jquery' ),
			ZAPROCKET_VERSION,
			true
		);
		wp_localize_script(
			'zaprocket-admin-locale',
			'zaprocketLocale',
			array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'action' => ZapRocket_I18n::AJAX_ACTION,
				'nonce'  => wp_create_nonce( 'zaprocket_locale' ),
				'i18n'   => array(
					'fail'      => pili__( '语言切换失败。' ),
					'switching' => pili__( '正在切换语言…' ),
				),
			)
		);
		wp_enqueue_style(
			'zaprocket-admin-extra',
			ZAPROCKET_URL . 'assets/css/admin-extra.css',
			$extra_deps,
			(string) ( @filemtime( ZAPROCKET_DIR . 'assets/css/admin-extra.css' ) ?: ZAPROCKET_VERSION )
		);
		ZapRocket_Module_General::enqueue_action_button_assets();
		wp_enqueue_script(
			'zaprocket-admin-clean',
			ZAPROCKET_URL . 'assets/js/admin-clean.js',
			array( 'jquery' ),
			(string) ( @filemtime( ZAPROCKET_DIR . 'assets/js/admin-clean.js' ) ?: ZAPROCKET_VERSION ),
			true
		);
		// i18n: 清理/推荐/清缓存的 JS 提示一律由此袋提供。
		$scan_cache = array();
		if ( class_exists( 'ZapRocket_Db_Size_Estimator', false ) ) {
			$cached = ZapRocket_Db_Size_Estimator::get_cache();
			if ( is_array( $cached ) ) {
				$scan_cache = $cached;
			}
		}
		wp_localize_script(
			'zaprocket-admin-clean',
			'zaprocketClean',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( ZapRocket_Module_Cleaner::NONCE ),
				'preview'   => 'zaprocket_db_preview',
				'run'       => 'zaprocket_db_run',
				'recommend' => 'zaprocket_recommend',
				'recNonce'  => wp_create_nonce( ZapRocket_Module_General::NONCE ),
				'purge'     => 'zaprocket_cache_purge',
				'purgeNonce'=> wp_create_nonce( ZapRocket_Module_Page_Cache::NONCE ),
				'scanCache' => $scan_cache,
				'i18n'      => array(
					'scanning' => pili__( '正在扫描…' ),
					'scanOk'   => pili__( '扫描完成。确认后点「清理」。' ),
					'running'  => pili__( '正在清理…' ),
					'runMore'  => pili__( '本批已删完，还有剩余，请再点清理。' ),
					'runOk'    => pili__( '清理完成。' ),
					'needSel'  => pili__( '请先勾选要清理的项目。' ),
					'fail'     => pili__( '请求失败，请刷新后重试。' ),
					'recBusy'  => pili__( '正在写入推荐配置…' ),
					'recOk'    => pili__( '已套用低风险推荐。请刷新本页核对开关。' ),
					'purgeBusy'=> pili__( '正在清空缓存…' ),
					'purgeOk'  => pili__( '已清空页面缓存和压缩文件。' ),
					'countFmt'    => pili__( '%d 条' ),
					'sizing'      => pili__( '计算中…' ),
					'sizeTimeout' => pili__( '无法估算，站点数据量大，估算超时' ),
					'sizeHint'    => pili__( '预估体积仅作为参考，实际释放空间以清理完成后为准。' ),
					'emptyScan'   => class_exists( 'ZapRocket_Db_Size_Estimator', false )
						? ZapRocket_Db_Size_Estimator::empty_note()
						: pili__( '暂无扫描记录，请点击「重新扫描」计算预估数据' ),
				),
			)
		);
	}
}
