<?php
/**
 * Guest HTML file cache.
 *
 * Hit/save run on template_redirect after the main query. Logged-in users,
 * Woo cart/checkout/account, 404, search, and disallowed query strings are
 * never stored. First view still boots WordPress fully; files live only under
 * wp-content/cache/zaprocket.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: page cache.
 */
final class ZapRocket_Module_Page_Cache extends ZapRocket_Module {

	const NONCE = 'zaprocket_cache_purge';

	/** @var bool */
	private $buffering = false;

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_page_cache';
	}

	/**
	 * {@inheritdoc}
	 */
	public function enabled() {
		return ZapRocket_Context::plugin_active();
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		self::write_config();
		add_action( 'save_post', array( $this, 'purge_after_save' ), 20, 1 );
		add_action( 'comment_post', array( __CLASS__, 'purge_all' ), 20, 0 );
		add_action( 'switch_theme', array( __CLASS__, 'purge_all' ), 20, 0 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'purge_generated' ), 20, 0 );
		// Saving speed/slim/general changes HTML (delay JS, minify, lazyload, master switch).
		add_action( 'update_option_zaprocket__speed', array( __CLASS__, 'purge_generated' ), 20, 0 );
		add_action( 'update_option_zaprocket__general', array( __CLASS__, 'purge_generated' ), 20, 0 );
		add_action( 'update_option_zaprocket__slim', array( __CLASS__, 'purge_generated' ), 20, 0 );
		add_action( 'woocommerce_settings_saved', array( __CLASS__, 'write_config' ), 20, 0 );
		add_action( 'update_option_woocommerce_cart_page_id', array( __CLASS__, 'write_config' ), 20, 0 );
		add_action( 'update_option_woocommerce_checkout_page_id', array( __CLASS__, 'write_config' ), 20, 0 );
		add_action( 'update_option_woocommerce_myaccount_page_id', array( __CLASS__, 'write_config' ), 20, 0 );
		add_action( 'wp_ajax_zaprocket_cache_purge', array( $this, 'ajax_purge' ) );
		add_action( 'admin_init', array( $this, 'maybe_admin_purge' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 82 );

		if ( ! ZapRocket_Options::speed_on( 'zr_speed_cache_enable', false ) ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );
		add_action( 'template_redirect', array( $this, 'maybe_buffer' ), 1 );
	}

	/**
	 * @return string
	 */
	public static function cache_dir() {
		return wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) . '/cache/zaprocket' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function build_config() {
		$n    = (int) ZapRocket_Options::get( 'speed', 'zr_speed_adv_lifespan', 10 );
		$unit = sanitize_key( (string) ZapRocket_Options::get( 'speed', 'zr_speed_adv_lifespan_unit', 'hours' ) );
		$ttl  = 0;
		if ( $n > 0 ) {
			$ttl = ( 'days' === $unit ) ? $n * DAY_IN_SECONDS : $n * HOUR_IN_SECONDS;
		}
		$reject_uri = array_merge(
			ZapRocket_Options::lines( 'speed', 'zr_speed_adv_reject_uri', '' ),
			self::ecommerce_reject_uris()
		);
		$root = self::cache_dir();
		return array(
			'enabled'        => ZapRocket_Options::speed_on( 'zr_speed_cache_enable', false ),
			'plugin_on'      => ZapRocket_Context::plugin_active(),
			'ttl'            => $ttl,
			'cache_dir'      => $root,
			'reject_uri'     => array_values( array_unique( $reject_uri ) ),
			'reject_cookies' => ZapRocket_Options::lines( 'speed', 'zr_speed_adv_reject_cookies', '' ),
			'reject_ua'      => ZapRocket_Options::lines( 'speed', 'zr_speed_adv_reject_ua', '' ),
			'query_allow'    => ZapRocket_Options::lines( 'speed', 'zr_speed_adv_query_strings', '' ),
		);
	}

	/**
	 * WooCommerce cart / checkout / account paths (baked into config.json).
	 *
	 * @return string[]
	 */
	private static function ecommerce_reject_uris() {
		$out = array();
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $out;
		}
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page_key ) {
			$page_id = (int) wc_get_page_id( $page_key );
			if ( $page_id < 1 ) {
				continue;
			}
			$permalink = get_permalink( $page_id );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				continue;
			}
			$path = wp_parse_url( $permalink, PHP_URL_PATH );
			if ( ! is_string( $path ) || '' === $path || '/' === $path ) {
				continue;
			}
			$path  = untrailingslashit( $path );
			$out[] = $path;
			$out[] = $path . '/(.*)';
		}
		return $out;
	}

	/**
	 * @return void
	 */
	public static function write_config() {
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		$dir = self::cache_dir();
		if ( ! zaprocket_cache_path_ok( $dir ) ) {
			return;
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = $dir . '/index.html';
		if ( zaprocket_cache_path_ok( $index ) && ! is_readable( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$cfg = zaprocket_cache_config_path();
		if ( '' === $cfg || ! zaprocket_cache_path_ok( $cfg ) ) {
			return;
		}
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$cfg,
			wp_json_encode( self::build_config() )
		);
	}

	/**
	 * @return void
	 */
	public function maybe_serve() {
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return;
		}
		if ( ! $this->request_ok() ) {
			return;
		}
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		$config = self::build_config();
		if ( zaprocket_cache_bypass( $config ) ) {
			return;
		}
		$html = zaprocket_cache_get( $config );
		if ( '' === $html ) {
			return;
		}
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		header( 'X-ZapRocket-Cache: hit' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cached page HTML.
		exit;
	}

	/**
	 * @return void
	 */
	public function maybe_buffer() {
		if ( ! $this->request_ok() ) {
			return;
		}
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return;
		}
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		if ( zaprocket_cache_bypass( self::build_config() ) ) {
			return;
		}
		$this->buffering = true;
		ob_start( array( $this, 'store_buffer' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function store_buffer( $html ) {
		if ( ! $this->buffering ) {
			return $html;
		}
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return $html;
		}
		$code = function_exists( 'http_response_code' ) ? (int) http_response_code() : 200;
		if ( 200 !== $code ) {
			return $html;
		}
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		zaprocket_cache_put( self::build_config(), (string) $html );
		return $html;
	}

	/**
	 * @return bool
	 */
	private function request_ok() {
		if ( ZapRocket_Context::should_skip() ) {
			return false;
		}
		if ( is_user_logged_in() ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}
		if ( function_exists( 'is_search' ) && is_search() ) {
			return false;
		}
		if ( is_404() || is_feed() || is_trackback() || is_robots() || is_preview() ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return false;
		}
		if ( isset( $_COOKIE ) && is_array( $_COOKIE ) ) {
			foreach ( array_keys( $_COOKIE ) as $name ) {
				$name = (string) $name;
				if ( 0 === strpos( $name, 'wordpress_logged_in' ) ) {
					return false;
				}
				if ( 0 === strpos( $name, 'wp_woocommerce_session' ) ) {
					return false;
				}
				if ( 0 === strpos( $name, 'woocommerce_items_in_cart' ) || 0 === strpos( $name, 'woocommerce_cart_hash' ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function purge_after_save( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::purge_url( (string) get_permalink( $post_id ) );
		self::purge_url( home_url( '/' ) );
		foreach ( ZapRocket_Options::lines( 'speed', 'zr_speed_adv_purge_urls', '' ) as $line ) {
			if ( false !== strpos( $line, '(.*)' ) || '/' === $line ) {
				self::purge_all();
				return;
			}
			self::purge_url( home_url( $line ) );
		}
	}

	/**
	 * @param string $url URL.
	 * @return void
	 */
	public static function purge_url( $url ) {
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return;
		}
		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( '' === $host ) {
			return;
		}
		$scheme = isset( $parts['scheme'] ) ? (string) $parts['scheme'] : 'https';
		$host_dir = preg_replace( '/[^a-z0-9.\-]/', '', $host );
		if ( ! is_string( $host_dir ) || '' === $host_dir ) {
			return;
		}
		$file = self::cache_dir() . '/pages/' . $host_dir . '/' . md5( $scheme . '://' . $host . $path ) . '.html';
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		if ( zaprocket_cache_path_ok( $file ) && is_readable( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * @return void
	 */
	public static function purge_all() {
		$dir = self::cache_dir() . '/pages';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		self::rm_tree( $dir );
	}

	/**
	 * Page HTML + minify combine files. Font files are kept.
	 *
	 * @return void
	 */
	public static function purge_generated() {
		self::purge_all();
		$upload = wp_upload_dir();
		$base   = isset( $upload['basedir'] ) ? (string) $upload['basedir'] : '';
		if ( '' !== $base ) {
			$min = trailingslashit( $base ) . 'zaprocket/min';
			if ( is_dir( $min ) ) {
				self::rm_tree( $min );
			}
		}
		self::write_config();
	}

	/**
	 * @return void
	 */
	public function ajax_purge() {
		if ( ! ZapRocket_Context::can_manage() ) {
			wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		self::purge_generated();
		if ( function_exists( 'zaprocket_run_log' ) ) {
			zaprocket_run_log(
				'speed',
				'info',
				pili__( '已清空页面缓存和压缩文件。' ),
				array(
					'code' => 'cache_purge',
				)
			);
		}
		wp_send_json_success( array( 'message' => pili__( '已清空页面缓存和压缩文件。字体文件仍保留。' ) ) );
	}

	/**
	 * @return void
	 */
	public function maybe_admin_purge() {
		if ( empty( $_GET['zaprocket_purge'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! ZapRocket_Context::can_manage() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		self::purge_generated();
		if ( function_exists( 'zaprocket_run_log' ) ) {
			zaprocket_run_log(
				'speed',
				'info',
				pili__( '已清空页面缓存和压缩文件。' ),
				array(
					'code' => 'cache_purge',
				)
			);
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/**
	 * @param \WP_Admin_Bar $bar Bar.
	 * @return void
	 */
	public function admin_bar( $bar ) {
		if ( ! ZapRocket_Context::can_manage() || ! is_object( $bar ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'zaprocket-purge',
				'title' => pili__( '清空闪电缓存' ),
				'href'  => wp_nonce_url( admin_url( 'index.php?zaprocket_purge=1' ), self::NONCE ),
			)
		);
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function rm_tree( $dir ) {
		$dir = wp_normalize_path( untrailingslashit( (string) $dir ) );
		if ( ! is_dir( $dir ) || false !== strpos( $dir, '..' ) ) {
			return;
		}
		$ok = false;
		require_once ZAPROCKET_DIR . 'includes/cache/engine.php';
		if ( function_exists( 'zaprocket_cache_path_ok' ) && zaprocket_cache_path_ok( $dir ) ) {
			$ok = true;
		}
		$upload = wp_upload_dir();
		if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) ) {
			$base = wp_normalize_path( untrailingslashit( trailingslashit( $upload['basedir'] ) . 'zaprocket' ) );
			if ( $dir === $base || 0 === strpos( $dir, $base . '/' ) ) {
				$ok = true;
			}
		}
		if ( ! $ok ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				self::rm_tree( $path );
			} else {
				wp_delete_file( $path );
			}
		}
	}
}
