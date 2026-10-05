<?php
/**
 * 懒加载：原生 loading=lazy；排除 skip-lazy；可选补宽高、CSS 背景。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: lazy loading.
 */
final class ZapRocket_Module_Lazyload extends ZapRocket_Module {

	/** @var int */
	private $img_seen = 0;

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_lazyload';
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		$img    = ZapRocket_Options::speed_on( 'zr_speed_lazy_images', false );
		$iframe = ZapRocket_Options::speed_on( 'zr_speed_lazy_iframes', false );
		$bg     = ZapRocket_Options::speed_on( 'zr_speed_lazy_css_bg', false );
		$dims   = ZapRocket_Options::speed_on( 'zr_speed_lazy_dimensions', false );
		$yt     = ZapRocket_Options::speed_on( 'zr_speed_lazy_youtube', false );

		if ( ! $img && ! $iframe && ! $bg && ! $dims && ! $yt ) {
			return;
		}

		if ( $img ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_attachment_attrs' ), 10, 1 );
			add_filter( 'the_content', array( $this, 'filter_content_images' ), 20 );
			add_filter( 'post_thumbnail_html', array( $this, 'filter_thumb' ), 20 );
			add_filter( 'widget_text', array( $this, 'filter_content_images' ), 20 );
		}

		if ( $iframe || $yt ) {
			add_filter( 'the_content', array( $this, 'filter_iframes' ), 21 );
			add_filter( 'widget_text', array( $this, 'filter_iframes' ), 21 );
			add_filter( 'embed_oembed_html', array( $this, 'filter_iframes' ), 21 );
		}

		if ( $bg ) {
			add_filter( 'the_content', array( $this, 'filter_bg' ), 22 );
			add_filter( 'widget_text', array( $this, 'filter_bg' ), 22 );
		}

		if ( $bg || $yt ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_bg_js' ), 99 );
		}

		if ( $dims && function_exists( 'wp_img_tag_add_width_and_height_attr' ) ) {
			add_filter( 'wp_img_tag_add_width_and_height_attr', '__return_true' );
		}
	}

	/**
	 * @return void
	 */
	public function enqueue_bg_js() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}
		wp_enqueue_script(
			'zaprocket-lazyload',
			ZAPROCKET_URL . 'assets/js/lazyload.js',
			array(),
			ZAPROCKET_VERSION,
			true
		);
	}

	/**
	 * @param array<string,string> $attr Attributes.
	 * @return array<string,string>
	 */
	public function filter_attachment_attrs( $attr ) {
		if ( ! is_array( $attr ) ) {
			return $attr;
		}
		$html = isset( $attr['class'] ) ? (string) $attr['class'] : '';
		$src  = isset( $attr['src'] ) ? (string) $attr['src'] : '';
		if ( $this->should_skip_node( $html . ' ' . $src ) ) {
			$attr['loading'] = 'eager';
			return $attr;
		}
		++$this->img_seen;
		if ( $this->img_seen <= $this->skip_first() ) {
			$attr['loading']       = 'eager';
			$attr['fetchpriority'] = 'high';
			return $attr;
		}
		$attr['loading'] = 'lazy';
		return $attr;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function filter_thumb( $html ) {
		return $this->filter_content_images( $html );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function filter_content_images( $html ) {
		if ( ZapRocket_Context::should_skip() || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		return preg_replace_callback(
			'#<img\b[^>]*>#i',
			function ( $m ) {
				$tag = $m[0];
				if ( $this->should_skip_node( $tag ) ) {
					return $tag;
				}
				++$this->img_seen;
				if ( $this->img_seen <= $this->skip_first() ) {
					return $this->set_attr( $tag, 'loading', 'eager' );
				}
				if ( preg_match( '/\bloading\s*=/i', $tag ) ) {
					return $tag;
				}
				return $this->set_attr( $tag, 'loading', 'lazy' );
			},
			$html
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function filter_iframes( $html ) {
		if ( ZapRocket_Context::should_skip() || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$yt   = ZapRocket_Options::speed_on( 'zr_speed_lazy_youtube', false );
		$lazy = ZapRocket_Options::speed_on( 'zr_speed_lazy_iframes', false );
		$out  = preg_replace_callback(
			'#<iframe\b([^>]*)>(.*?)</iframe>#is',
			function ( $m ) use ( $yt, $lazy ) {
				$attrs = $m[1];
				$inner = $m[2];
				$open  = '<iframe' . $attrs . '>';
				if ( $this->should_skip_node( $open ) ) {
					return $m[0];
				}
				if ( $yt ) {
					$id = self::youtube_id( $attrs );
					if ( '' !== $id ) {
						return self::youtube_facade( $id );
					}
				}
				if ( ! $lazy || preg_match( '/\bloading\s*=/i', $attrs ) ) {
					return $m[0];
				}
				return $this->set_attr( $open, 'loading', 'lazy' ) . $inner . '</iframe>';
			},
			$html
		);
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param string $attrs Iframe attributes.
	 * @return string
	 */
	private static function youtube_id( $attrs ) {
		if ( ! preg_match( '/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $m ) ) {
			return '';
		}
		$src = $m[2];
		if ( preg_match( '#(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#', $src, $id ) ) {
			return $id[1];
		}
		if ( preg_match( '#[?&]v=([A-Za-z0-9_-]{11})#', $src, $id ) ) {
			return $id[1];
		}
		return '';
	}

	/**
	 * @param string $id Video id.
	 * @return string
	 */
	private static function youtube_facade( $id ) {
		$id  = preg_replace( '/[^A-Za-z0-9_-]/', '', $id );
		$img = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
		return '<div class="zr-yt" data-zr-yt="' . esc_attr( $id ) . '" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;background:#111">'
			. '<button type="button" class="zr-yt__play" style="position:absolute;inset:0;width:100%;height:100%;padding:0;border:0;cursor:pointer;background:transparent">'
			. '<img src="' . esc_url( $img ) . '" alt="" width="480" height="360" loading="lazy" style="width:100%;height:100%;object-fit:cover" />'
			. '</button></div>';
	}

	/**
	 * Inline background-image only.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function filter_bg( $html ) {
		if ( ZapRocket_Context::should_skip() || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		return preg_replace_callback(
			'#\sstyle=(["\'])([^"\']*background-image\s*:\s*url\((?:["\']?)([^"\')]+)(?:["\']?)\)[^"\']*)\1#i',
			function ( $m ) {
				$quote = $m[1];
				$style = $m[2];
				$url   = $m[3];
				if ( $this->should_skip_node( $style ) ) {
					return $m[0];
				}
				$style = preg_replace( '#background-image\s*:\s*url\((?:["\']?)[^"\')]+(?:["\']?)\)\s*;?#i', '', $style );
				return ' style=' . $quote . trim( $style ) . $quote . ' data-zr-bg="' . esc_attr( $url ) . '"';
			},
			$html
		);
	}

	/**
	 * @return int
	 */
	private function skip_first() {
		$n = (int) ZapRocket_Options::get( 'speed', 'zr_speed_preload_lcp_n', 0 );
		return $n > 0 ? $n : 1;
	}

	/**
	 * @param string $hay Haystack.
	 * @return bool
	 */
	private function should_skip_node( $hay ) {
		$hay = (string) $hay;
		if ( preg_match( '/skip-lazy|data-no-lazy|no-lazy|zr-no-lazy/i', $hay ) ) {
			return true;
		}
		foreach ( ZapRocket_Options::lines( 'speed', 'zr_speed_lazy_exclude', '' ) as $needle ) {
			if ( '' !== $needle && false !== stripos( $hay, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $tag  Tag.
	 * @param string $name Attr.
	 * @param string $val  Value.
	 * @return string
	 */
	private function set_attr( $tag, $name, $val ) {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=/i', $tag ) ) {
			return preg_replace(
				'/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\']).*?\1/i',
				' ' . $name . '="' . esc_attr( $val ) . '"',
				$tag,
				1
			);
		}
		return preg_replace( '/<(img|iframe)\b/i', '<$1 ' . $name . '="' . esc_attr( $val ) . '"', $tag, 1 );
	}
}
