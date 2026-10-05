<?php
/**
 * 预载：DNS / 文件 preload / 悬停预取 / 首屏图。不是整站 HTML 预热。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: preload hints.
 */
final class ZapRocket_Module_Preload extends ZapRocket_Module {

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_preload';
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		add_action( 'wp_head', array( $this, 'print_hints' ), 2 );
		if ( ZapRocket_Options::speed_on( 'zr_speed_preload_links', false ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_preload_links' ), 99 );
		}
	}

	/**
	 * @return void
	 */
	public function enqueue_preload_links() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}
		wp_enqueue_script(
			'zaprocket-preload-links',
			ZAPROCKET_URL . 'assets/js/preload-links.js',
			array(),
			ZAPROCKET_VERSION,
			true
		);
	}

	/**
	 * @return void
	 */
	public function print_hints() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}

		$rows = ZapRocket_Options::get( 'speed', 'zr_speed_preload_dns', array() );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$host = isset( $row['host'] ) ? strtolower( trim( (string) $row['host'] ) ) : '';
				$host = preg_replace( '#^https?://#', '', $host );
				$host = trim( $host, '/' );
				if ( '' === $host || false !== strpos( $host, '/' ) ) {
					continue;
				}
				$mode = isset( $row['mode'] ) ? sanitize_key( (string) $row['mode'] ) : 'preconnect';
				$href = 'https://' . $host;
				if ( 'prefetch' === $mode ) {
					echo '<link rel="dns-prefetch" href="//' . esc_attr( $host ) . '" />' . "\n";
				} else {
					echo '<link rel="preconnect" href="' . esc_url( $href ) . '" crossorigin />' . "\n";
					echo '<link rel="dns-prefetch" href="//' . esc_attr( $host ) . '" />' . "\n";
				}
			}
		}

		$assets = ZapRocket_Options::get( 'speed', 'zr_speed_preload_assets', array() );
		if ( is_array( $assets ) ) {
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}
				$url = isset( $asset['url'] ) ? (string) $asset['url'] : '';
				if ( '' === $url ) {
					continue;
				}
				$as = isset( $asset['as'] ) ? sanitize_key( (string) $asset['as'] ) : 'image';
				if ( ! in_array( $as, array( 'image', 'style', 'font', 'script' ), true ) ) {
					$as = 'image';
				}
				$cross = ! empty( $asset['cross'] ) || 'font' === $as;
				$tag   = '<link rel="preload" href="' . esc_url( $url ) . '" as="' . esc_attr( $as ) . '"';
				if ( $cross ) {
					$tag .= ' crossorigin';
				}
				if ( 'font' === $as ) {
					$ext = strtolower( (string) pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
					$mime = array(
						'woff2' => 'font/woff2',
						'woff'  => 'font/woff',
						'ttf'   => 'font/ttf',
						'otf'   => 'font/otf',
					);
					if ( isset( $mime[ $ext ] ) ) {
						$tag .= ' type="' . esc_attr( $mime[ $ext ] ) . '"';
					}
				}
				echo $tag . " />\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			}
		}

		if ( ZapRocket_Options::speed_on( 'zr_speed_font_preload', false ) ) {
			$this->print_enqueued_fonts();
		}

		$n = (int) ZapRocket_Options::get( 'speed', 'zr_speed_preload_lcp_n', 0 );
		if ( $n > 0 ) {
			foreach ( $this->first_content_images( $n ) as $src ) {
				echo '<link rel="preload" href="' . esc_url( $src ) . '" as="image" />' . "\n";
			}
		}
	}

	/**
	 * @return void
	 */
	private function print_enqueued_fonts() {
		global $wp_styles;
		if ( ! is_object( $wp_styles ) || empty( $wp_styles->queue ) || ! is_array( $wp_styles->queue ) ) {
			return;
		}
		$printed = array();
		foreach ( $wp_styles->queue as $handle ) {
			if ( empty( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}
			$src = isset( $wp_styles->registered[ $handle ]->src ) ? (string) $wp_styles->registered[ $handle ]->src : '';
			if ( ! preg_match( '/\.(woff2?|ttf|otf)(\?|$)/i', $src ) ) {
				continue;
			}
			if ( isset( $printed[ $src ] ) ) {
				continue;
			}
			$printed[ $src ] = true;
			echo '<link rel="preload" href="' . esc_url( $src ) . '" as="font" type="font/woff2" crossorigin />' . "\n";
		}
	}

	/**
	 * @param int $n Count.
	 * @return string[]
	 */
	private function first_content_images( $n ) {
		$n = max( 0, min( 5, (int) $n ) );
		if ( $n < 1 || ! is_singular() ) {
			return array();
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return array();
		}
		if ( ! preg_match_all( '#<img[^>]+src=["\']([^"\']+)#i', (string) $post->post_content, $m ) ) {
			$thumb = get_the_post_thumbnail_url( $post, 'full' );
			return is_string( $thumb ) && '' !== $thumb ? array( $thumb ) : array();
		}
		$out = array();
		foreach ( $m[1] as $src ) {
			$src = html_entity_decode( $src, ENT_QUOTES );
			if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
				continue;
			}
			$out[] = $src;
			if ( count( $out ) >= $n ) {
				break;
			}
		}
		return $out;
	}
}
