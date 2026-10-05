<?php
/**
 * Local CSS/JS minify and optional footer JS combine.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed: minify stylesheet and script URLs.
 */
final class ZapRocket_Module_Minify extends ZapRocket_Module {

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'speed_minify';
	}

	/**
	 * {@inheritdoc}
	 */
	public function hooks() {
		$css = ZapRocket_Options::speed_on( 'zr_speed_css_minify', false );
		$js  = ZapRocket_Options::speed_on( 'zr_speed_js_minify', false );
		if ( ! $css && ! $js ) {
			return;
		}
		if ( $css ) {
			add_filter( 'style_loader_src', array( $this, 'minify_css_src' ), 20, 2 );
		}
		if ( $js ) {
			add_filter( 'script_loader_src', array( $this, 'minify_js_src' ), 20, 2 );
			if ( ZapRocket_Options::speed_on( 'zr_speed_js_combine', false ) && ! ZapRocket_Options::speed_on( 'zr_speed_js_delay', false ) ) {
				add_action( 'wp_footer', array( $this, 'combine_footer_scripts' ), 1 );
			}
		}
	}

	/**
	 * @param string $src    URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function minify_css_src( $src, $handle = '' ) {
		return $this->maybe_minify( $src, (string) $handle, 'css' );
	}

	/**
	 * @param string $src    URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function minify_js_src( $src, $handle = '' ) {
		return $this->maybe_minify( $src, (string) $handle, 'js' );
	}

	/**
	 * @param string $src    URL.
	 * @param string $handle Handle.
	 * @param string $kind   css|js.
	 * @return string
	 */
	private function maybe_minify( $src, $handle, $kind ) {
		if ( ZapRocket_Context::should_skip() ) {
			return $src;
		}
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}
		if ( 0 === strpos( $src, 'data:' ) || 0 === strpos( $src, 'blob:' ) ) {
			return $src;
		}

		$hay = $handle . ' ' . $src;
		$key = ( 'css' === $kind ) ? 'zr_speed_css_exclude' : 'zr_speed_js_exclude';
		if ( ZapRocket_Options::matches_any( $hay, ZapRocket_Options::lines( 'speed', $key, '' ) ) ) {
			return $src;
		}

		$path = self::url_to_local_path( $src );
		if ( '' === $path || ! is_readable( $path ) ) {
			return $src;
		}
		if ( ! preg_match( '/\.' . $kind . '$/i', $path ) ) {
			return $src;
		}
		if ( preg_match( '/\.min\.' . $kind . '$/i', $path ) ) {
			return $src;
		}

		$size = filesize( $path );
		if ( ! is_int( $size ) || $size < 8 || $size > 1048576 ) {
			return $src;
		}

		$mtime = (int) filemtime( $path );
		$hash  = substr( md5( $path . '|' . $mtime . '|' . $size . '|' . $kind ), 0, 12 );
		$cache = self::cache_dir() . $hash . '.' . $kind;
		if ( ! is_readable( $cache ) ) {
			$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_string( $raw ) || '' === $raw ) {
				return $src;
			}
			$min = ( 'css' === $kind ) ? self::minify_css( $raw ) : self::minify_js( $raw );
			if ( '' === $min || strlen( $min ) >= strlen( $raw ) ) {
				return $src;
			}
			if ( ! self::write_cache( $cache, $min ) ) {
				return $src;
			}
		}

		$base = self::cache_url() . $hash . '.' . $kind;
		$q    = wp_parse_url( $src, PHP_URL_QUERY );
		if ( is_string( $q ) && '' !== $q ) {
			$base .= '?' . $q;
		}
		return $base;
	}

	/**
	 * @return string Trailing-slash dir.
	 */
	private static function cache_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( (string) ( $upload['basedir'] ?? '' ) ) . 'zaprocket/min/';
		return $dir;
	}

	/**
	 * @return string Trailing-slash URL.
	 */
	private static function cache_url() {
		$upload = wp_upload_dir();
		return trailingslashit( (string) ( $upload['baseurl'] ?? '' ) ) . 'zaprocket/min/';
	}

	/**
	 * @param string $file File.
	 * @param string $body Body.
	 * @return bool
	 */
	private static function write_cache( $file, $body ) {
		$dir = self::cache_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$index = $dir . 'index.html';
			if ( ! is_readable( $index ) ) {
				file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}
		return false !== file_put_contents( $file, $body, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * @param string $url URL.
	 * @return string Absolute path or empty.
	 */
	private static function url_to_local_path( $url ) {
		$clean = (string) strtok( $url, '?#' );
		if ( 0 === strpos( $clean, '//' ) ) {
			$clean = ( is_ssl() ? 'https:' : 'http:' ) . $clean;
		}
		$candidates = array(
			array( content_url(), WP_CONTENT_DIR ),
			array( includes_url(), ABSPATH . WPINC ),
			array( site_url(), untrailingslashit( ABSPATH ) ),
			array( home_url(), untrailingslashit( ABSPATH ) ),
		);
		foreach ( $candidates as $pair ) {
			$base_url = untrailingslashit( $pair[0] );
			$base_dir = $pair[1];
			if ( '' === $base_url ) {
				continue;
			}
			if ( 0 !== strpos( $clean, $base_url ) ) {
				continue;
			}
			$rel  = substr( $clean, strlen( $base_url ) );
			$path = wp_normalize_path( $base_dir . $rel );
			$root = wp_normalize_path( $base_dir );
			if ( 0 !== strpos( $path, $root ) ) {
				return '';
			}
			if ( false !== strpos( $path, '..' ) ) {
				return '';
			}
			return $path;
		}
		return '';
	}

	/**
	 * @param string $css CSS.
	 * @return string
	 */
	private static function minify_css( $css ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		if ( ! is_string( $css ) ) {
			return '';
		}
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/\s*([{};:,>~+])\s*/', '$1', (string) $css );
		return is_string( $css ) ? trim( $css ) : '';
	}

	/**
	 * Conservative JS minify: drop comments and extra whitespace outside strings.
	 *
	 * @param string $js JS.
	 * @return string
	 */
	private static function minify_js( $js ) {
		$len = strlen( $js );
		$out = '';
		$i   = 0;
		$ctx = 'code';
		$q   = '';
		while ( $i < $len ) {
			$c  = $js[ $i ];
			$n  = ( $i + 1 < $len ) ? $js[ $i + 1 ] : '';
			$p  = ( $i > 0 ) ? $js[ $i - 1 ] : '';

			if ( 'code' === $ctx ) {
				if ( '/' === $c && '*' === $n ) {
					$i += 2;
					while ( $i < $len && ! ( '*' === $js[ $i ] && ( $i + 1 < $len ) && '/' === $js[ $i + 1 ] ) ) {
						++$i;
					}
					$i += 2;
					continue;
				}
				if ( '/' === $c && '/' === $n ) {
					$i += 2;
					while ( $i < $len && "\n" !== $js[ $i ] ) {
						++$i;
					}
					continue;
				}
				if ( ( "'" === $c || '"' === $c || '`' === $c ) && '\\' !== $p ) {
					$ctx  = 'str';
					$q    = $c;
					$out .= $c;
					++$i;
					continue;
				}
				if ( ctype_space( $c ) ) {
					$next = '';
					$j    = $i + 1;
					while ( $j < $len && ctype_space( $js[ $j ] ) ) {
						++$j;
					}
					if ( $j < $len ) {
						$next = $js[ $j ];
					}
					$prev = substr( $out, -1 );
					if ( '' !== $prev && '' !== $next && preg_match( '/[A-Za-z0-9_\$]/', $prev ) && preg_match( '/[A-Za-z0-9_\$]/', $next ) ) {
						$out .= ' ';
					}
					$i = $j;
					continue;
				}
				$out .= $c;
				++$i;
				continue;
			}

			$out .= $c;
			if ( $c === $q && '\\' !== $p ) {
				$ctx = 'code';
				$q   = '';
			}
			++$i;
		}
		return trim( $out );
	}

	/**
	 * Merge local footer scripts into one file. Skips jquery, extras, and exclude list.
	 *
	 * @return void
	 */
	public function combine_footer_scripts() {
		if ( ZapRocket_Context::should_skip() ) {
			return;
		}
		if ( ZapRocket_Options::speed_on( 'zr_speed_js_delay', false ) ) {
			return;
		}
		global $wp_scripts;
		if ( ! ( $wp_scripts instanceof WP_Scripts ) || empty( $wp_scripts->queue ) ) {
			return;
		}

		$skip_handles = array( 'jquery', 'jquery-core', 'jquery-migrate', 'zaprocket-delay-js', 'zaprocket-combined' );
		$parts          = array();
		$taken          = array();
		foreach ( (array) $wp_scripts->queue as $handle ) {
			$handle = (string) $handle;
			if ( in_array( $handle, $skip_handles, true ) || in_array( $handle, $wp_scripts->done, true ) ) {
				continue;
			}
			if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
				continue;
			}
			$obj = $wp_scripts->registered[ $handle ];
			$src = isset( $obj->src ) ? (string) $obj->src : '';
			if ( '' === $src ) {
				continue;
			}
			$group = isset( $obj->extra['group'] ) ? (int) $obj->extra['group'] : 0;
			if ( 1 !== $group ) {
				continue;
			}
			if ( $wp_scripts->get_data( $handle, 'data' ) ) {
				continue;
			}
			if ( ! empty( $obj->extra['before'] ) || ! empty( $obj->extra['after'] ) ) {
				continue;
			}
			$hay = $handle . ' ' . $src;
			if ( ZapRocket_Options::matches_any( $hay, ZapRocket_Options::lines( 'speed', 'zr_speed_js_exclude', '' ) ) ) {
				continue;
			}
			if ( preg_match( '/jquery(-core|-migrate)?$/i', $handle ) ) {
				continue;
			}
			$path = self::url_to_local_path( $src );
			if ( '' === $path || ! is_readable( $path ) || ! preg_match( '/\.js$/i', $path ) ) {
				continue;
			}
			$size = filesize( $path );
			if ( ! is_int( $size ) || $size < 1 || $size > 512000 ) {
				continue;
			}
			$parts[] = array(
				'handle' => $handle,
				'path'   => $path,
				'mtime'  => (int) filemtime( $path ),
				'size'   => $size,
			);
			$taken[] = $handle;
			if ( count( $parts ) >= 12 ) {
				break;
			}
		}

		if ( count( $parts ) < 2 ) {
			return;
		}

		$sig = '';
		foreach ( $parts as $row ) {
			$sig .= $row['path'] . '|' . $row['mtime'] . '|' . $row['size'] . ';';
		}
		$hash = substr( md5( $sig ), 0, 12 );
		$file = self::cache_dir() . 'c-' . $hash . '.js';
		if ( ! is_readable( $file ) ) {
			$buf = '';
			foreach ( $parts as $row ) {
				$raw = file_get_contents( $row['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( ! is_string( $raw ) ) {
					return;
				}
				$min = self::minify_js( $raw );
				$buf .= ( '' !== $min ? $min : $raw ) . ";\n";
			}
			if ( strlen( $buf ) < 32 || ! self::write_cache( $file, $buf ) ) {
				return;
			}
		}

		foreach ( $taken as $handle ) {
			wp_dequeue_script( $handle );
		}
		wp_enqueue_script(
			'zaprocket-combined',
			self::cache_url() . 'c-' . $hash . '.js',
			array(),
			null,
			true
		);
	}
}
