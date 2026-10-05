<?php
/**
 * CDN：把本站静态资源主机名换成加速域名。不改 PHP 地址。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: CDN URL replacement.
 */
final class ZapRocket_Module_Cdn extends ZapRocket_Module {

	/** @var string */
	private $cdn = '';

	/** @var string[] */
	private $home_hosts = array();

	/** @var string[] */
	private $dirs = array();

	/** @var string[] */
	private $excludes = array();

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_cdn';
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		if ( ! ZapRocket_Options::speed_on( 'zr_speed_cdn_enable', false ) ) {
			return;
		}
		$this->cdn = untrailingslashit( (string) ZapRocket_Options::get( 'speed', 'zr_speed_cdn_host', '' ) );
		if ( '' === $this->cdn ) {
			return;
		}

		$this->home_hosts = $this->collect_home_hosts();
		$this->dirs       = $this->collect_dirs();
		$this->excludes   = ZapRocket_Options::lines( 'speed', 'zr_speed_cdn_exclude', '.php' );
		if ( ! in_array( '.php', $this->excludes, true ) ) {
			$this->excludes[] = '.php';
		}

		add_filter( 'script_loader_src', array( $this, 'rewrite' ), 999, 1 );
		add_filter( 'style_loader_src', array( $this, 'rewrite' ), 999, 1 );
		add_filter( 'wp_get_attachment_url', array( $this, 'rewrite' ), 999, 1 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 999, 1 );
		add_filter( 'the_content', array( $this, 'rewrite_html' ), 999, 1 );
		add_filter( 'widget_text', array( $this, 'rewrite_html' ), 999, 1 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_image_src' ), 999, 1 );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public function rewrite( $url ) {
		if ( ZapRocket_Context::should_skip() ) {
			return $url;
		}
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		if ( 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'blob:' ) ) {
			return $url;
		}
		if ( false !== stripos( $url, $this->cdn ) ) {
			return $url;
		}
		if ( class_exists( 'ZapRocket_Oss_Url', false ) && ZapRocket_Oss_Url::is_oss_url( $url ) ) {
			return $url;
		}

		foreach ( $this->excludes as $needle ) {
			if ( '' !== $needle && false !== stripos( $url, $needle ) ) {
				return $url;
			}
		}

		if ( preg_match( '/\.php(?:[?#]|$)/i', $url ) ) {
			return $url;
		}

		$path = $this->path_of( $url );
		if ( '' === $path || ! $this->path_allowed( $path ) ) {
			return $url;
		}

		$parsed = wp_parse_url( $url );
		$host   = isset( $parsed['host'] ) ? (string) $parsed['host'] : '';
		if ( '' !== $host && ! in_array( $host, $this->home_hosts, true ) ) {
			return $url;
		}

		$query = isset( $parsed['query'] ) && is_string( $parsed['query'] ) && '' !== $parsed['query']
			? '?' . $parsed['query']
			: '';
		$frag  = isset( $parsed['fragment'] ) && is_string( $parsed['fragment'] ) && '' !== $parsed['fragment']
			? '#' . $parsed['fragment']
			: '';

		return $this->cdn . $path . $query . $frag;
	}

	/**
	 * @param array<int,array<string,mixed>>|mixed $sources Sources.
	 * @return mixed
	 */
	public function rewrite_srcset( $sources ) {
		if ( ! is_array( $sources ) ) {
			return $sources;
		}
		foreach ( $sources as $w => $row ) {
			if ( isset( $row['url'] ) ) {
				$sources[ $w ]['url'] = $this->rewrite( (string) $row['url'] );
			}
		}
		return $sources;
	}

	/**
	 * @param array<int,mixed>|false $image Image src tuple.
	 * @return array<int,mixed>|false
	 */
	public function rewrite_image_src( $image ) {
		if ( is_array( $image ) && isset( $image[0] ) && is_string( $image[0] ) ) {
			$image[0] = $this->rewrite( $image[0] );
		}
		return $image;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function rewrite_html( $html ) {
		if ( ZapRocket_Context::should_skip() || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		return preg_replace_callback(
			'#(?:https?:)?//[^\s"\'<>]+#i',
			function ( $m ) {
				return $this->rewrite( $m[0] );
			},
			$html
		);
	}

	/**
	 * @return string[]
	 */
	private function collect_home_hosts() {
		$out = array();
		foreach ( array( home_url(), site_url() ) as $u ) {
			$host = wp_parse_url( (string) $u, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$out[] = $host;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @return string[]
	 */
	private function collect_dirs() {
		$map = array(
			'uploads'  => 'wp-content/uploads',
			'themes'   => 'wp-content/themes',
			'plugins'  => 'wp-content/plugins',
			'includes' => 'wp-includes',
		);
		$sel = ZapRocket_Options::get( 'speed', 'zr_speed_cdn_dirs', array( 'uploads', 'themes', 'plugins' ) );
		if ( ! is_array( $sel ) ) {
			$sel = array();
		}
		$paths = array();
		foreach ( $sel as $key ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $map[ $key ] ) ) {
				$paths[] = $map[ $key ];
			}
		}
		foreach ( ZapRocket_Options::lines( 'speed', 'zr_speed_cdn_include_extra', '' ) as $extra ) {
			$extra = trim( str_replace( '\\', '/', $extra ), '/' );
			if ( '' !== $extra ) {
				$paths[] = $extra;
			}
		}
		return $paths;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private function path_of( $url ) {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return $url;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) ? $path : '';
	}

	/**
	 * @param string $path Path.
	 * @return bool
	 */
	private function path_allowed( $path ) {
		$path = ltrim( str_replace( '\\', '/', $path ), '/' );
		foreach ( $this->dirs as $dir ) {
			$dir = trim( str_replace( '\\', '/', $dir ), '/' );
			if ( '' !== $dir && 0 === strpos( $path, $dir ) ) {
				return true;
			}
		}
		return false;
	}
}
