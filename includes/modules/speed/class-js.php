<?php
/**
 * JS defer + Delay JS (interaction). Combine stays later.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: JS defer and delay.
 */
final class ZapRocket_Module_Js extends ZapRocket_Module {

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_js';
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		$defer = ZapRocket_Options::speed_on( 'zr_speed_js_defer', false );
		$delay = ZapRocket_Options::speed_on( 'zr_speed_js_delay', false );
		if ( ! $defer && ! $delay ) {
			return;
		}
		add_filter( 'script_loader_tag', array( $this, 'filter_tag' ), 10, 3 );
		if ( $delay ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_delay_helper' ), 9999 );
		}
	}

	/**
	 * @return void
	 */
	public function enqueue_delay_helper() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}
		wp_enqueue_script(
			'zaprocket-delay-js',
			ZAPROCKET_URL . 'assets/js/delay-js.js',
			array(),
			ZAPROCKET_VERSION,
			true
		);
	}

	/**
	 * @param string $tag    Tag.
	 * @param string $handle Handle.
	 * @param string $src    Src.
	 * @return string
	 */
	public function filter_tag( $tag, $handle, $src ) {
		if ( ZapRocket_Context::should_skip() ) {
			return $tag;
		}
		if ( ! is_string( $tag ) || false === stripos( $tag, '<script' ) ) {
			return $tag;
		}
		$handle = (string) $handle;
		if ( 'zaprocket-delay-js' === $handle ) {
			return $tag;
		}

		$src = (string) $src;
		$hay = $handle . ' ' . $src . ' ' . $tag;
		if ( ZapRocket_Options::matches_any( $hay, ZapRocket_Options::lines( 'speed', 'zr_speed_js_exclude', '' ) ) ) {
			return $tag;
		}
		if ( preg_match( '/jquery(-core|-migrate)?$/i', $handle ) || false !== stripos( $src, '/jquery.' ) || false !== stripos( $src, '/jquery.min.' ) ) {
			return $tag;
		}
		if ( false !== stripos( $tag, 'type="module"' ) || false !== stripos( $tag, "type='module'" ) ) {
			return $tag;
		}

		$delay = ZapRocket_Options::speed_on( 'zr_speed_js_delay', false );
		if ( $delay && '' !== $src ) {
			$tag = preg_replace( '/\s+type=(["\']).*?\1/i', '', $tag );
			if ( ! is_string( $tag ) ) {
				return $tag;
			}
			if ( false === stripos( $tag, 'data-zr-delay' ) ) {
				$tag = str_replace( '<script ', '<script type="text/plain" data-zr-delay ', $tag );
			}
			return $tag;
		}

		if ( ! ZapRocket_Options::speed_on( 'zr_speed_js_defer', false ) ) {
			return $tag;
		}
		if ( preg_match( '/\s(?:defer|async)\b/i', $tag ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script defer ', $tag );
	}
}
