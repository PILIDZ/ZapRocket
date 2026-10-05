<?php
/**
 * Runtime context / skip guards for frontend vs editor modules.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context helpers shared by slim / speed modules.
 */
final class ZapRocket_Context {

	/**
	 * Plugin master switch + kill switch (no request-type checks).
	 *
	 * @return bool
	 */
	public static function plugin_active() {
		if ( defined( 'ZAPROCKET_DISABLE' ) && ZAPROCKET_DISABLE ) {
			return false;
		}

		return ZapRocket_Options::is_plugin_enabled();
	}

	/**
	 * Skip frontend optimizations (head, emoji, CDN, lazyload, JS…).
	 *
	 * Does not skip editor-only hooks (Heartbeat / revisions / autosave).
	 *
	 * @return bool
	 */
	public static function should_skip() {
		if ( ! self::plugin_active() ) {
			return true;
		}

		/**
		 * Force-skip frontend optimizations (not editor Heartbeat/revisions).
		 *
		 * @param bool $skip Skip.
		 */
		if ( (bool) apply_filters( 'zaprocket_skip', false ) ) {
			return true;
		}

		if ( is_admin() ) {
			return true;
		}

		if ( self::is_login_request() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag.
		if ( isset( $_GET['preview'] ) && 'true' === $_GET['preview'] ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}

		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		return false;
	}

	/**
	 * Skip editor-only slim hooks (Heartbeat interval, revisions cap, autosave).
	 *
	 * Not gated by is_admin().
	 *
	 * @return bool
	 */
	public static function should_skip_editor() {
		return ! self::plugin_active();
	}

	/**
	 * Login / register screens: never rewrite assets or strip head tags.
	 *
	 * @return bool
	 */
	public static function is_login_request() {
		if ( isset( $GLOBALS['pagenow'] ) && in_array( (string) $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return true;
		}

		$candidates = array();
		foreach ( array( 'SCRIPT_NAME', 'PHP_SELF', 'REQUEST_URI' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				continue;
			}
			$candidates[] = str_replace( '\\', '/', (string) wp_unslash( $_SERVER[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		foreach ( $candidates as $path ) {
			if ( false !== strpos( $path, '/wp-login.php' ) || false !== strpos( $path, '/wp-register.php' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Classic / block editor (or Heartbeat from that screen).
	 *
	 * @return bool
	 */
	public static function is_post_editor() {
		global $pagenow;

		$pages = array( 'post.php', 'post-new.php', 'site-editor.php' );
		if ( isset( $pagenow ) && in_array( (string) $pagenow, $pages, true ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && isset( $screen->base ) ) {
				$base = (string) $screen->base;
				if ( in_array( $base, array( 'post', 'site-editor' ), true ) ) {
					return true;
				}
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Heartbeat payload identity.
		$screen_id = isset( $_POST['screen_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['screen_id'] ) ) : '';
		if ( '' !== $screen_id ) {
			if ( 0 === strpos( $screen_id, 'post' ) || 0 === strpos( $screen_id, 'page' ) || false !== strpos( $screen_id, 'site-editor' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}
}
