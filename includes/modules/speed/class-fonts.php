<?php
/**
 * Download Google Fonts CSS + woff2 into uploads/zaprocket/fonts.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: host Google Fonts locally.
 */
final class ZapRocket_Module_Fonts extends ZapRocket_Module {

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_fonts';
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		if ( ! ZapRocket_Options::speed_on( 'zr_speed_font_local', false ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'rewrite_queue' ), 9990 );
		add_filter( 'style_loader_src', array( $this, 'localize_src' ), 15, 1 );
	}

	/**
	 * Point registered google-font handles at local CSS before slim dequeue.
	 *
	 * @return void
	 */
	public function rewrite_queue() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}
		global $wp_styles;
		if ( ! is_object( $wp_styles ) || empty( $wp_styles->registered ) ) {
			return;
		}
		foreach ( $wp_styles->registered as $obj ) {
			$src = isset( $obj->src ) ? (string) $obj->src : '';
			if ( ! self::is_google_css( $src ) ) {
				continue;
			}
			$local = $this->localize_css_url( $src );
			if ( '' !== $local ) {
				$obj->src = $local;
			}
		}
	}

	/**
	 * @param string $src URL.
	 * @return string
	 */
	public function localize_src( $src ) {
		if ( ZapRocket_Context::should_skip() || ! is_string( $src ) ) {
			return $src;
		}
		if ( ! self::is_google_css( $src ) ) {
			return $src;
		}
		$local = $this->localize_css_url( $src );
		return '' !== $local ? $local : $src;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_google_css( $url ) {
		$url = strtolower( (string) $url );
		return ( false !== strpos( $url, 'fonts.googleapis.com' ) );
	}

	/**
	 * @param string $url Google CSS URL.
	 * @return string Local CSS URL or empty.
	 */
	private function localize_css_url( $url ) {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		$hash = substr( md5( $url ), 0, 16 );
		$dir  = self::font_dir();
		$file = $dir . $hash . '.css';
		$base = self::font_url();
		if ( is_readable( $file ) ) {
			return $base . $hash . '.css';
		}

		$css = self::http_get(
			$url,
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
		);
		if ( '' === $css ) {
			return '';
		}

		$n = 0;
		$css = preg_replace_callback(
			'#url\((["\']?)([^"\')]+)\1\)#i',
			function ( $m ) use ( $dir, $base, &$n ) {
				if ( $n >= 24 ) {
					return $m[0];
				}
				$font_url = html_entity_decode( $m[2], ENT_QUOTES );
				if ( 0 === strpos( $font_url, '//' ) ) {
					$font_url = 'https:' . $font_url;
				}
				if ( ! preg_match( '#^https://fonts\.gstatic\.com/#i', $font_url ) ) {
					return $m[0];
				}
				if ( ! preg_match( '/\.(woff2|woff|ttf)(\?|$)/i', $font_url, $ext ) ) {
					return $m[0];
				}
				$ext  = strtolower( $ext[1] );
				$name = substr( md5( $font_url ), 0, 16 ) . '.' . $ext;
				$path = $dir . $name;
				if ( ! is_readable( $path ) ) {
					$bin = self::http_get( $font_url, 'Mozilla/5.0' );
					if ( '' === $bin || strlen( $bin ) > 1048576 ) {
						return $m[0];
					}
					if ( ! self::ensure_dir( $dir ) || false === file_put_contents( $path, $bin, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
						return $m[0];
					}
				}
				++$n;
				return 'url(' . $base . $name . ')';
			},
			$css
		);

		if ( ! is_string( $css ) || '' === $css ) {
			return '';
		}
		if ( ! self::ensure_dir( $dir ) || false === file_put_contents( $file, $css, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return '';
		}
		return $base . $hash . '.css';
	}

	/**
	 * @param string $url URL.
	 * @param string $ua  User-Agent.
	 * @return string
	 */
	private static function http_get( $url, $ua ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => $ua,
				'redirection'=> 3,
			)
		);
		if ( is_wp_error( $res ) ) {
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( 200 !== $code || ! is_string( $body ) || '' === $body ) {
			return '';
		}
		return $body;
	}

	/**
	 * @return string
	 */
	private static function font_dir() {
		$upload = wp_upload_dir();
		return trailingslashit( (string) ( $upload['basedir'] ?? '' ) ) . 'zaprocket/fonts/';
	}

	/**
	 * @return string
	 */
	private static function font_url() {
		$upload = wp_upload_dir();
		return trailingslashit( (string) ( $upload['baseurl'] ?? '' ) ) . 'zaprocket/fonts/';
	}

	/**
	 * @param string $dir Dir.
	 * @return bool
	 */
	private static function ensure_dir( $dir ) {
		if ( is_dir( $dir ) ) {
			return is_writable( $dir );
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$index = $dir . 'index.html';
		if ( ! is_readable( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return true;
	}
}
