<?php
/**
 * Plugin UI locale (zaprocket text domain only).
 *
 * Does not write WPLANG / user locale, and does not switch_to_locale().
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header language switcher + preferred pack load.
 */
final class ZapRocket_I18n {

	const USER_META_LOCALE = 'zaprocket_admin_locale';
	const AJAX_ACTION      = 'zaprocket_set_admin_locale';
	const LOCALE_AUTO      = 'auto';

	/** @var bool */
	private static $filtering = false;

	/** @var string|null */
	private static $resolved = null;

	/**
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_set_locale' ) );
		add_filter( 'load_translation_file', array( __CLASS__, 'filter_load_translation_file' ), 10, 3 );
	}

	/**
	 * Locale codes only (no gettext — safe inside translation load).
	 *
	 * @return array<int,string>
	 */
	public static function allowed_locale_codes() {
		return array( self::LOCALE_AUTO, 'zh_CN', 'en_US' );
	}

	/**
	 * Dropdown labels (UI only). PILI select 富选项：label + icon。
	 *
	 * @return array<string,array{label:string,icon:string}|string>
	 */
	public static function supported_locales() {
		return array(
			self::LOCALE_AUTO => array(
				'label' => pili__( '跟随 WordPress（仅本插件）' ),
				'icon'  => '🌐',
			),
			'zh_CN'           => array(
				'label' => '简体中文',
				'icon'  => '🇨🇳',
			),
			'en_US'           => array(
				'label' => 'English',
				'icon'  => '🇺🇸',
			),
		);
	}

	/**
	 * @return string
	 */
	public static function get_user_preference() {
		if ( ! function_exists( 'get_current_user_id' ) ) {
			return '';
		}
		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 && function_exists( 'wp_validate_auth_cookie' ) ) {
			$auth = wp_validate_auth_cookie( '', 'logged_in' );
			if ( $auth ) {
				$user_id = (int) $auth;
			}
		}
		if ( $user_id <= 0 ) {
			return '';
		}
		$pref = get_user_meta( $user_id, self::USER_META_LOCALE, true );
		if ( ! is_string( $pref ) || '' === $pref || self::LOCALE_AUTO === $pref ) {
			return '';
		}
		if ( ! in_array( $pref, array( 'zh_CN', 'en_US' ), true ) ) {
			delete_user_meta( $user_id, self::USER_META_LOCALE );
			return '';
		}
		return $pref;
	}

	/**
	 * WordPress locale without determine_locale() (avoids pre_determine_locale recursion).
	 *
	 * @return string
	 */
	private static function wp_locale_raw() {
		if ( function_exists( 'is_admin' ) && is_admin() && function_exists( 'get_user_locale' ) && function_exists( 'get_current_user_id' ) && get_current_user_id() ) {
			return (string) get_user_locale();
		}
		if ( function_exists( 'get_locale' ) ) {
			return (string) get_locale();
		}
		return 'en_US';
	}

	/**
	 * @return string
	 */
	public static function resolve_locale() {
		if ( is_string( self::$resolved ) && '' !== self::$resolved ) {
			return self::$resolved;
		}
		$pref = self::get_user_preference();
		if ( in_array( $pref, array( 'zh_CN', 'en_US' ), true ) ) {
			self::$resolved = $pref;
			return self::$resolved;
		}
		$wp = str_replace( '-', '_', self::wp_locale_raw() );
		if ( 0 === strpos( $wp, 'en' ) ) {
			self::$resolved = 'en_US';
		} elseif ( 0 === strpos( $wp, 'zh' ) ) {
			self::$resolved = 'zh_CN';
		} else {
			self::$resolved = 'zh_CN';
		}
		return self::$resolved;
	}

	/**
	 * @param string|null $locale Locale.
	 * @return bool
	 */
	public static function uses_source_msgid( $locale = null ) {
		if ( null === $locale ) {
			$locale = self::resolve_locale();
		}
		return 'zh_CN' === (string) $locale;
	}

	/**
	 * @return void
	 */
	public static function load_preferred_textdomain() {
		$want = self::resolve_locale();
		$rel  = dirname( plugin_basename( ZAPROCKET_FILE ) ) . '/languages';
		load_plugin_textdomain( 'zaprocket-wp', false, $rel );

		if ( self::uses_source_msgid( $want ) ) {
			if ( function_exists( 'is_textdomain_loaded' ) && is_textdomain_loaded( 'zaprocket-wp' ) ) {
				unload_textdomain( 'zaprocket-wp', true );
			}
			return;
		}

		$mo = ZAPROCKET_DIR . 'languages/zaprocket-wp-' . $want . '.mo';
		if ( ! is_readable( $mo ) ) {
			$mo = ZAPROCKET_DIR . 'languages/zaprocket-wp-en_US.mo';
		}
		if ( ! is_readable( $mo ) ) {
			return;
		}
		if ( function_exists( 'is_textdomain_loaded' ) && is_textdomain_loaded( 'zaprocket-wp' ) ) {
			unload_textdomain( 'zaprocket-wp', true );
		}
		self::$filtering = true;
		load_textdomain( 'zaprocket-wp', $mo );
		self::$filtering = false;
	}

	/**
	 * @param string $file   Path.
	 * @param string $domain Domain.
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function filter_load_translation_file( $file, $domain, $locale ) {
		unset( $locale );
		if ( 'zaprocket-wp' !== $domain || self::$filtering ) {
			return $file;
		}
		self::$filtering = true;
		try {
			$want = self::resolve_locale();
			if ( self::uses_source_msgid( $want ) ) {
				return is_string( $file ) && '' !== $file ? $file . '.zaprocket-skip-zh' : $file;
			}
			$is_mo = is_string( $file ) && substr( $file, -3 ) === '.mo';
			if ( ! $is_mo ) {
				return $file;
			}
			$target = ZAPROCKET_DIR . 'languages/zaprocket-wp-' . $want . '.mo';
			if ( is_readable( $target ) ) {
				return $target;
			}
			$en = ZAPROCKET_DIR . 'languages/zaprocket-wp-en_US.mo';
			return is_readable( $en ) ? $en : $file;
		} finally {
			self::$filtering = false;
		}
	}

	/**
	 * @return void
	 */
	public static function ajax_set_locale() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足。' ) ), 403 );
		}
		check_ajax_referer( 'zaprocket_locale', 'nonce' );
		$locale  = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['locale'] ) ) : '';
		$allowed = self::allowed_locale_codes();
		if ( '' === $locale ) {
			$locale = self::LOCALE_AUTO;
		}
		if ( ! in_array( $locale, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => pili__( '不支持的语言。' ) ), 400 );
		}
		$user_id = get_current_user_id();
		if ( self::LOCALE_AUTO === $locale ) {
			delete_user_meta( $user_id, self::USER_META_LOCALE );
		} else {
			update_user_meta( $user_id, self::USER_META_LOCALE, $locale );
		}
		self::$resolved = null;
		$redirect       = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect'] ) ) : '';
		if ( '' === $redirect ) {
			$redirect = admin_url( 'admin.php?page=zaprocket' );
		}
		$redirect = remove_query_arg( '_zrlang', $redirect );
		wp_send_json_success(
			array(
				'locale'   => $locale,
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * i18n: 顶栏语言切换（pili_header_bar / header_bar_callback）。
	 *
	 * @param mixed $admin Options instance.
	 * @return string
	 */
	public static function render_header_language_switcher( $admin = null ) {
		unset( $admin );
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		if ( ! class_exists( '\Pili\Core\PILI', false ) ) {
			return '';
		}
		$current = self::get_user_preference();
		if ( '' === $current ) {
			$current = self::LOCALE_AUTO;
		}
		$resolved = self::resolve_locale();
		ob_start();
		echo '<div class="zaprocket-lang-switcher flex items-center gap-2 mr-1 sm:mr-2 shrink-0 self-center" data-zr-lang-switcher="1" data-current="' . esc_attr( $current ) . '" data-resolved="' . esc_attr( $resolved ) . '" title="' . esc_attr( pili__( '仅切换本插件后台界面语言，不会修改 WordPress 站点语言' ) ) . '" style="min-width:9.5rem;max-width:12rem;">';
		echo '<span class="hidden sm:inline text-xs text-gray-500 shrink-0">' . esc_html( pili__( '插件语言' ) ) . '</span>';
		echo '<div class="zaprocket-lang-switcher-field flex-1 min-w-0">';
		\Pili\Core\PILI::field(
			array(
				'id'              => 'zaprocket_admin_locale',
				'type'            => 'select',
				'name'            => 'zaprocket_admin_locale',
				'options'         => self::supported_locales(),
				'placeholder'     => pili__( '选择插件界面语言' ),
				'multiple'        => false,
				'searchable'      => false,
				'clearable'       => false,
				'close_on_select' => true,
				'max_height'      => 200,
				'attributes'      => array(
					'id'            => 'zaprocket-admin-locale',
					'data-current'  => $current,
					'data-resolved' => $resolved,
				),
			),
			$current,
			'',
			'header'
		);
		echo '</div>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * JS gettext runtime for pili-framework (window.pilipost__).
	 *
	 * @return string Script handle.
	 */
	public static function enqueue_js_runtime() {
		$handle = 'zaprocket-i18n';
		static $localized = false;
		if ( ! function_exists( 'wp_register_script' ) ) {
			return '';
		}
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script(
				$handle,
				ZAPROCKET_URL . 'assets/js/i18n-runtime.js',
				array(),
				ZAPROCKET_VERSION,
				true
			);
		}
		wp_enqueue_script( $handle );
		if ( ! $localized ) {
			$localized = true;
			wp_localize_script(
				$handle,
				'zaprocketL10n',
				array(
					'locale'   => self::resolve_locale(),
					'messages' => self::js_messages(),
				)
			);
		}
		return $handle;
	}

	/**
	 * msgid => msgstr for framework JS (lazy overlay, save, import).
	 *
	 * @return array<string,string>
	 */
	private static function js_messages() {
		$locale = self::resolve_locale();
		if ( self::uses_source_msgid( $locale ) ) {
			return array();
		}
		$messages = self::js_messages_from_loaded_domain();
		foreach ( self::js_chrome_msgids() as $msgid ) {
			$tr = pili__( $msgid );
			if ( is_string( $tr ) && '' !== $tr ) {
				$messages[ $msgid ] = $tr;
			}
		}
		return $messages;
	}

	/**
	 * Framework chrome that JS interpolates via pilipostT().
	 *
	 * @return array<int,string>
	 */
	private static function js_chrome_msgids() {
		return array(
			'正在加载设置…',
			'设置页加载失败',
			'分区内容加载失败，请刷新后重试。',
			'分区加载失败',
			'加载失败',
			'从这里开始',
			'保存中...',
			'已保存',
			'保存失败',
			'保存失败:',
			'取消',
			'确定',
			'确认',
			'提示',
			'操作完成',
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function js_messages_from_loaded_domain() {
		if ( ! function_exists( 'get_translations_for_domain' ) || ! function_exists( 'pili_text_domain' ) ) {
			return array();
		}
		$translations = get_translations_for_domain( pili_text_domain() );
		if ( ! $translations || ! is_object( $translations ) ) {
			return array();
		}
		$entries = $translations->entries;
		if ( ! is_array( $entries ) || array() === $entries ) {
			return array();
		}
		$out = array();
		foreach ( $entries as $entry ) {
			if ( ! is_object( $entry ) || empty( $entry->singular ) ) {
				continue;
			}
			$k = (string) $entry->singular;
			if ( false !== strpos( $k, '<' ) || false !== strpos( $k, '>' ) || strlen( $k ) > 180 ) {
				continue;
			}
			$v = '';
			if ( ! empty( $entry->translations ) && is_array( $entry->translations ) ) {
				$v = (string) reset( $entry->translations );
			}
			if ( '' === $v ) {
				continue;
			}
			$out[ $k ] = $v;
		}
		return $out;
	}
}
