<?php
/**
 * WP 瘦身：页头、功能开关、访客 REST、编辑器、头像、拦截谷歌字体外链。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slim module: applies switches from zaprocket__slim.
 */
final class ZapRocket_Module_Slim extends ZapRocket_Module {

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'slim';
	}

	/**
	 * Editor + REST + frontend; master switch unhooks everything.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! ZapRocket_Context::plugin_active() ) {
			return;
		}

		$this->hooks_editor();
		$this->hooks_rest();
		$this->hooks_xmlrpc();
		$this->hooks_comments();
		add_action( 'wp_default_scripts', array( $this, 'filter_jquery_migrate' ) );
		add_action( 'pre_ping', array( $this, 'maybe_filter_self_ping' ) );

		if ( ZapRocket_Context::should_skip() ) {
			return;
		}

		$this->hooks_frontend();
		$this->apply_head_and_features();
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		$this->boot();
	}

	/**
	 * Heartbeat / revisions / autosave — admin + save requests.
	 *
	 * @return void
	 */
	private function hooks_editor() {
		if ( ZapRocket_Context::should_skip_editor() ) {
			return;
		}

		add_filter( 'heartbeat_settings', array( $this, 'filter_heartbeat_settings' ) );
		add_action( 'init', array( $this, 'maybe_disable_frontend_heartbeat' ), 1 );
		add_filter( 'wp_revisions_to_keep', array( $this, 'filter_revisions_to_keep' ), 10, 2 );
		$this->maybe_define_autosave_interval();
	}

	/**
	 * Guest REST — not gated by is_admin(); logged-in users always pass.
	 *
	 * @return void
	 */
	private function hooks_rest() {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_disable_rest_guests', false ) ) {
			return;
		}
		add_filter( 'rest_authentication_errors', array( $this, 'filter_rest_guests' ) );
	}

	/**
	 * XML-RPC must run on xmlrpc.php (not a typical “frontend page”).
	 *
	 * @return void
	 */
	private function hooks_xmlrpc() {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_disable_xmlrpc', false ) ) {
			return;
		}
		add_filter( 'xmlrpc_enabled', '__return_false', 99 );
		add_filter( 'wp_headers', array( $this, 'filter_remove_pingback_header' ) );
	}

	/**
	 * Site-wide comments off (admin + front). Default remains off.
	 *
	 * @return void
	 */
	private function hooks_comments() {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_disable_comments', false ) ) {
			return;
		}
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_action( 'wp_loaded', array( $this, 'remove_comment_post_type_support' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_comment_reply' ), 99 );
	}

	/**
	 * @return void
	 */
	public function remove_comment_post_type_support() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		if ( ! is_array( $types ) ) {
			return;
		}
		foreach ( $types as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	}

	/**
	 * Head cleanup, emoji, admin bar, oEmbed, gravatar, gfonts.
	 *
	 * @return void
	 */
	private function hooks_frontend() {
		add_filter( 'show_admin_bar', array( $this, 'filter_admin_bar' ) );
		add_filter( 'comment_form_default_fields', array( $this, 'filter_comment_url_field' ) );
		add_filter( 'get_comment_author_url', array( $this, 'filter_comment_author_url' ) );

		$gmode = sanitize_key( (string) ZapRocket_Options::get( 'slim', 'zr_slim_gravatar_mode', 'default' ) );
		if ( 'disable' === $gmode ) {
			add_filter( 'pre_option_show_avatars', array( $this, 'filter_disable_avatars_option' ) );
			add_filter( 'get_avatar', array( $this, 'filter_empty_avatar' ), 10, 1 );
		} elseif ( 'local' === $gmode ) {
			add_filter( 'get_avatar_url', array( $this, 'filter_local_avatar_url' ), 10, 1 );
		} elseif ( 'mirror' === $gmode ) {
			add_filter( 'get_avatar_url', array( $this, 'filter_mirror_avatar_url' ), 10, 1 );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_disable_gfonts', false ) ) {
			add_filter( 'style_loader_src', array( $this, 'filter_strip_gfont_src' ), 99, 1 );
			add_filter( 'script_loader_src', array( $this, 'filter_strip_gfont_src' ), 99, 1 );
			add_filter( 'wp_resource_hints', array( $this, 'filter_gfont_resource_hints' ), 10, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_google_fonts' ), 9999 );
			add_action( 'wp_print_styles', array( $this, 'dequeue_google_fonts' ), 9999 );
			add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
		}
	}

	/**
	 * Head tags, emoji, rss, embeds.
	 *
	 * @return void
	 */
	public function apply_head_and_features() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_disable_rss', false ) ) {
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
			add_action( 'do_feed', array( $this, 'block_feeds' ), 1 );
			add_action( 'do_feed_rdf', array( $this, 'block_feeds' ), 1 );
			add_action( 'do_feed_rss', array( $this, 'block_feeds' ), 1 );
			add_action( 'do_feed_rss2', array( $this, 'block_feeds' ), 1 );
			add_action( 'do_feed_atom', array( $this, 'block_feeds' ), 1 );
			add_action( 'do_feed_rss2_comments', array( $this, 'block_feeds' ), 1 );
			add_action( 'do_feed_atom_comments', array( $this, 'block_feeds' ), 1 );
			add_action( 'template_redirect', array( $this, 'maybe_block_feed_request' ), 1 );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_remove_wp_version', true ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_remove_wlw', true ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_remove_rsd', true ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_remove_shortlink', false ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_remove_rest_link', false ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
			remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_disable_emojis', true ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
			add_filter( 'tiny_mce_plugins', array( $this, 'filter_emoji_tinymce' ) );
			add_filter( 'wp_resource_hints', array( $this, 'filter_emoji_dns_prefetch' ), 10, 2 );
		}

		if ( ZapRocket_Options::slim_on( 'zr_slim_disable_embeds', false ) ) {
			global $wp;
			if ( is_object( $wp ) && isset( $wp->public_query_vars ) && is_array( $wp->public_query_vars ) ) {
				$wp->public_query_vars = array_diff( $wp->public_query_vars, array( 'embed' ) );
			}
			remove_action( 'rest_api_init', 'wp_oembed_register_route' );
			remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_filter( 'embed_oembed_discover', '__return_false' );
			add_filter( 'tiny_mce_plugins', array( $this, 'filter_embed_tinymce' ) );
			add_filter( 'rewrite_rules_array', array( $this, 'filter_embed_rewrites' ) );
			remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
			add_action( 'wp_footer', array( $this, 'dequeue_wp_embed' ), 1 );
		}
	}

	/**
	 * @return void
	 */
	public function block_feeds() {
		wp_die( esc_html( pili__( '本站已关闭订阅源。' ) ), '', array( 'response' => 403 ) );
	}

	/**
	 * @return void
	 */
	public function maybe_block_feed_request() {
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$this->block_feeds();
		}
	}

	/**
	 * @param array<int,string> $plugins TinyMCE plugins.
	 * @return array<int,string>
	 */
	public function filter_emoji_tinymce( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}
		return array_values( array_diff( $plugins, array( 'wpemoji' ) ) );
	}

	/**
	 * @param array<int,string> $plugins TinyMCE plugins.
	 * @return array<int,string>
	 */
	public function filter_embed_tinymce( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}
		return array_values( array_diff( $plugins, array( 'wpembed' ) ) );
	}

	/**
	 * @param array<string,string> $rules Rewrite rules.
	 * @return array<string,string>
	 */
	public function filter_embed_rewrites( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}
		foreach ( $rules as $rule => $rewrite ) {
			if ( is_string( $rewrite ) && false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}
		return $rules;
	}

	/**
	 * @param array<int,string> $urls          URLs.
	 * @param string            $relation_type Relation.
	 * @return array<int,string>
	 */
	public function filter_emoji_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' !== $relation_type || ! is_array( $urls ) ) {
			return $urls;
		}
		foreach ( $urls as $i => $url ) {
			if ( is_string( $url ) && false !== strpos( $url, 's.w.org' ) ) {
				unset( $urls[ $i ] );
			}
		}
		return array_values( $urls );
	}

	/**
	 * @return void
	 */
	public function dequeue_wp_embed() {
		wp_dequeue_script( 'wp-embed' );
		wp_deregister_script( 'wp-embed' );
	}

	/**
	 * @return void
	 */
	public function dequeue_comment_reply() {
		if ( function_exists( 'wp_script_is' ) && wp_script_is( 'comment-reply', 'registered' ) ) {
			wp_dequeue_script( 'comment-reply' );
			wp_deregister_script( 'comment-reply' );
		}
	}

	/**
	 * @param mixed $scripts WP_Scripts.
	 * @return void
	 */
	public function filter_jquery_migrate( $scripts ) {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_remove_jquery_migrate', false ) ) {
			return;
		}
		if ( ! is_object( $scripts ) || ! isset( $scripts->registered['jquery'] ) ) {
			return;
		}
		$jquery = $scripts->registered['jquery'];
		if ( ! is_object( $jquery ) || empty( $jquery->deps ) || ! is_array( $jquery->deps ) ) {
			return;
		}
		$jquery->deps = array_values( array_diff( $jquery->deps, array( 'jquery-migrate' ) ) );
	}

	/**
	 * @param bool $show Show bar.
	 * @return bool
	 */
	public function filter_admin_bar( $show ) {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_disable_admin_bar', false ) ) {
			return $show;
		}
		if ( is_admin() ) {
			return $show;
		}
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
		return false;
	}

	/**
	 * @param array<string,string> $fields Comment fields.
	 * @return array<string,string>
	 */
	public function filter_comment_url_field( $fields ) {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_remove_comment_link', false ) ) {
			return $fields;
		}
		if ( is_array( $fields ) ) {
			unset( $fields['url'] );
		}
		return $fields;
	}

	/**
	 * @param string $url Author URL.
	 * @return string
	 */
	public function filter_comment_author_url( $url ) {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_remove_comment_link', false ) ) {
			return $url;
		}
		return '';
	}

	/**
	 * Publishing happens in admin — not gated by should_skip().
	 *
	 * @param array<int,string> $links URLs.
	 * @return void
	 */
	public function maybe_filter_self_ping( &$links ) {
		if ( ! ZapRocket_Options::slim_on( 'zr_slim_disable_self_ping', true ) ) {
			return;
		}
		$this->filter_self_ping( $links );
	}

	/**
	 * @param array<int,string> $links URLs.
	 * @return void
	 */
	public function filter_self_ping( &$links ) {
		if ( ! is_array( $links ) ) {
			return;
		}
		$home = home_url();
		foreach ( $links as $i => $link ) {
			if ( is_string( $link ) && 0 === strpos( $link, $home ) ) {
				unset( $links[ $i ] );
			}
		}
	}

	/**
	 * @param array<string,string> $headers Headers.
	 * @return array<string,string>
	 */
	public function filter_remove_pingback_header( $headers ) {
		if ( is_array( $headers ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * @param mixed $errors Previous result.
	 * @return mixed
	 */
	public function filter_rest_guests( $errors ) {
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return $errors;
		}

		$route = '';
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}
		if ( '' === $route && ! empty( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$route = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		foreach ( $this->rest_exclude_needles() as $needle ) {
			if ( '' !== $needle && false !== stripos( $route, $needle ) ) {
				return $errors;
			}
		}

		return new WP_Error(
			'zaprocket_rest_guest',
			pili__( '未登录访客不能使用本站接口。' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * @return string[]
	 */
	private function rest_exclude_needles() {
		$raw  = (string) ZapRocket_Options::get( 'slim', 'zr_slim_rest_exclude', "oembed\ncontact-form" );
		$out  = array();
		$text = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		foreach ( explode( "\n", $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || preg_match( '/^\s*javascript:/i', $line ) ) {
				continue;
			}
			$out[] = $line;
		}
		return $out;
	}

	/**
	 * Dashboard slower Heartbeat; never disable the post editor.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	public function filter_heartbeat_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$mode = sanitize_key( (string) ZapRocket_Options::get( 'slim', 'zr_slim_heartbeat_mode', 'off' ) );
		if ( 'dashboard' !== $mode ) {
			return $settings;
		}

		if ( ZapRocket_Context::is_post_editor() ) {
			return $settings;
		}

		$settings['interval'] = 120;
		return $settings;
	}

	/**
	 * @return void
	 */
	public function maybe_disable_frontend_heartbeat() {
		if ( is_admin() || ZapRocket_Context::is_login_request() ) {
			return;
		}
		$mode = sanitize_key( (string) ZapRocket_Options::get( 'slim', 'zr_slim_heartbeat_mode', 'off' ) );
		if ( 'front' !== $mode && 'dashboard' !== $mode ) {
			return;
		}
		wp_deregister_script( 'heartbeat' );
	}

	/**
	 * 0 = unlimited. Purge-all checkbox maps to WordPress keep 0.
	 *
	 * @param int          $num  Current.
	 * @param WP_Post|null $post Post.
	 * @return int
	 */
	public function filter_revisions_to_keep( $num, $post ) {
		unset( $post );
		if ( ZapRocket_Options::slim_on( 'zr_slim_revisions_purge_all', false ) ) {
			return 0;
		}
		$keep = (int) ZapRocket_Options::get( 'slim', 'zr_slim_revisions_keep', 5 );
		if ( $keep < 0 ) {
			$keep = 0;
		}
		if ( $keep > 50 ) {
			$keep = 50;
		}
		if ( 0 === $keep ) {
			return -1;
		}
		return $keep;
	}

	/**
	 * @return void
	 */
	private function maybe_define_autosave_interval() {
		$raw = ZapRocket_Options::get( 'slim', 'zr_slim_autosave_interval', '0' );
		$sec = (int) $raw;
		if ( $sec <= 0 ) {
			return;
		}
		if ( ! in_array( $sec, array( 30, 60, 120, 180 ), true ) ) {
			return;
		}
		if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
			define( 'AUTOSAVE_INTERVAL', $sec );
		}
	}

	/**
	 * @param mixed $pre Previous.
	 * @return string
	 */
	public function filter_disable_avatars_option( $pre ) {
		unset( $pre );
		return '0';
	}

	/**
	 * @param string $avatar HTML.
	 * @return string
	 */
	public function filter_empty_avatar( $avatar ) {
		unset( $avatar );
		return '';
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public function filter_local_avatar_url( $url ) {
		unset( $url );
		return ZAPROCKET_URL . 'assets/img/avatar-placeholder.svg';
	}

	/**
	 * Route Gravatar through Cravatar (same hash path).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function filter_mirror_avatar_url( $url ) {
		$url = (string) $url;
		$url = preg_replace( '#https?://(www\.|secure\.|[0-2]\.)?gravatar\.com#i', 'https://cravatar.cn', $url );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param string $src URL.
	 * @return string|false
	 */
	public function filter_strip_gfont_src( $src ) {
		if ( is_string( $src ) && $this->is_google_font_url( $src ) ) {
			return false;
		}
		return $src;
	}

	/**
	 * @param array<int,string> $urls          URLs.
	 * @param string            $relation_type Relation.
	 * @return array<int,string>
	 */
	public function filter_gfont_resource_hints( $urls, $relation_type ) {
		unset( $relation_type );
		if ( ! is_array( $urls ) ) {
			return $urls;
		}
		foreach ( $urls as $i => $url ) {
			if ( is_string( $url ) && $this->is_google_font_url( $url ) ) {
				unset( $urls[ $i ] );
			}
		}
		return array_values( $urls );
	}

	/**
	 * @return void
	 */
	public function dequeue_google_fonts() {
		global $wp_styles;
		if ( ! is_object( $wp_styles ) || empty( $wp_styles->registered ) || ! is_array( $wp_styles->registered ) ) {
			return;
		}
		foreach ( $wp_styles->registered as $handle => $obj ) {
			$src = isset( $obj->src ) ? (string) $obj->src : '';
			if ( $this->is_google_font_url( $src ) ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_google_font_url( $url ) {
		$url = strtolower( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		return ( false !== strpos( $url, 'fonts.googleapis.com' ) || false !== strpos( $url, 'fonts.gstatic.com' ) );
	}
}
