<?php
/**
 * Guest HTML file cache helpers.
 *
 * Requires WordPress (ABSPATH). Files are only read/written under
 * WP_CONTENT_DIR/cache/zaprocket. Serve/save happens at template_redirect
 * (after the main query), so logged-in, Woo cart, 404 and search can be
 * skipped using WordPress conditionals. First visit still runs a full
 * WordPress bootstrap.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical cache root. Not taken from request or config.json.
 *
 * @return string Normalized path without trailing slash, or empty.
 */
function zaprocket_cache_allowed_root() {
	if ( ! defined( 'WP_CONTENT_DIR' ) || ! is_string( WP_CONTENT_DIR ) || '' === WP_CONTENT_DIR ) {
		return '';
	}
	return wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) . '/cache/zaprocket' );
}

/**
 * @param string $dir Unused; kept so callers stay compatible.
 * @return string
 */
function zaprocket_cache_config_path( $dir = '' ) {
	unset( $dir );
	$root = zaprocket_cache_allowed_root();
	return '' === $root ? '' : $root . '/config.json';
}

/**
 * True if $path is the cache root or a file/dir inside it (no "..").
 *
 * @param string $path Absolute path.
 * @return bool
 */
function zaprocket_cache_path_ok( $path ) {
	$root = zaprocket_cache_allowed_root();
	$path = wp_normalize_path( (string) $path );
	if ( '' === $root || '' === $path ) {
		return false;
	}
	if ( false !== strpos( $path, '..' ) ) {
		return false;
	}
	$root = rtrim( $root, '/' );
	if ( $path === $root ) {
		return true;
	}
	return 0 === strpos( $path, $root . '/' );
}

/**
 * @param string $dir Unused.
 * @return array<string,mixed>
 */
function zaprocket_cache_read_config( $dir = '' ) {
	unset( $dir );
	$file = zaprocket_cache_config_path();
	if ( '' === $file || ! zaprocket_cache_path_ok( $file ) || ! is_readable( $file ) ) {
		return array();
	}
	$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return array();
	}
	$data['cache_dir'] = zaprocket_cache_allowed_root();
	return $data;
}

/**
 * @param string $haystack Haystack.
 * @param string $needle   Needle.
 * @return bool
 */
function zaprocket_cache_match( $haystack, $needle ) {
	$needle   = trim( (string) $needle );
	$haystack = (string) $haystack;
	if ( '' === $needle || '' === $haystack ) {
		return false;
	}
	if ( false !== strpos( $needle, '(.*)' ) ) {
		$quoted = preg_quote( $needle, '#' );
		$quoted = str_replace( '\(\.\*\)', '.*', $quoted );
		return (bool) preg_match( '#' . $quoted . '#i', $haystack );
	}
	return false !== stripos( $haystack, $needle );
}

/**
 * @param array<string,mixed> $config Config.
 * @return bool True = do not use cache.
 */
function zaprocket_cache_bypass( array $config ) {
	if ( empty( $config['enabled'] ) || empty( $config['plugin_on'] ) ) {
		return true;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return true;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	if ( false !== strpos( $uri, '..' ) ) {
		return true;
	}
	if ( false !== strpos( $uri, '/wp-admin' ) || false !== strpos( $uri, '/wp-login.php' ) || false !== strpos( $uri, '/wp-json' ) || false !== strpos( $uri, '/xmlrpc.php' ) ) {
		return true;
	}
	if ( isset( $_GET['preview'] ) || isset( $_GET['customize_changeset_uuid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	foreach ( (array) ( $config['reject_ua'] ?? array() ) as $needle ) {
		if ( zaprocket_cache_match( $ua, (string) $needle ) ) {
			return true;
		}
	}
	foreach ( (array) ( $config['reject_uri'] ?? array() ) as $needle ) {
		if ( zaprocket_cache_match( $uri, (string) $needle ) ) {
			return true;
		}
	}
	$cookies = isset( $_COOKIE ) && is_array( $_COOKIE ) ? $_COOKIE : array();
	$names   = array_keys( $cookies );
	$joined  = implode( ' ', $names );
	$always  = array(
		'wordpress_logged_in',
		'wp-postpass',
		'comment_author',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session',
		'wordpress_sec',
	);
	foreach ( array_merge( $always, (array) ( $config['reject_cookies'] ?? array() ) ) as $needle ) {
		if ( zaprocket_cache_match( $joined, (string) $needle ) ) {
			return true;
		}
	}
	$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
	if ( '' !== $query ) {
		parse_str( $query, $params );
		if ( ! is_array( $params ) || array() === $params ) {
			return true;
		}
		$allow = array();
		foreach ( (array) ( $config['query_allow'] ?? array() ) as $key ) {
			$k = strtolower( sanitize_key( (string) $key ) );
			if ( '' !== $k ) {
				$allow[ $k ] = true;
			}
		}
		foreach ( array_keys( $params ) as $key ) {
			if ( ! isset( $allow[ strtolower( sanitize_key( (string) $key ) ) ] ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Host folder name (no separators). Port is kept in the file hash, not the folder.
 *
 * @return string
 */
function zaprocket_cache_host_folder() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : 'host';
	$host = preg_replace( '/[^a-z0-9.\-]/', '', $host );
	return is_string( $host ) && '' !== $host ? $host : 'host';
}

/**
 * @param array<string,mixed> $config Config.
 * @return string Absolute html path or empty.
 */
function zaprocket_cache_file( array $config ) {
	unset( $config );
	$root = zaprocket_cache_allowed_root();
	if ( '' === $root ) {
		return '';
	}
	$host_raw = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : 'host';
	$path     = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$qpos     = strpos( $path, '?' );
	if ( false !== $qpos ) {
		$path = substr( $path, 0, $qpos );
	}
	$path = rawurldecode( $path );
	if ( false !== strpos( $path, '..' ) || false !== strpos( $path, "\0" ) ) {
		return '';
	}
	$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
	$key    = $scheme . '://' . $host_raw . $path;
	$folder = zaprocket_cache_host_folder();
	$file   = $root . '/pages/' . $folder . '/' . md5( $key ) . '.html';
	return zaprocket_cache_path_ok( $file ) ? $file : '';
}

/**
 * @param array<string,mixed> $config Config.
 * @return string Empty if miss.
 */
function zaprocket_cache_get( array $config ) {
	$file = zaprocket_cache_file( $config );
	if ( '' === $file || ! is_readable( $file ) ) {
		return '';
	}
	$ttl = isset( $config['ttl'] ) ? (int) $config['ttl'] : 0;
	if ( $ttl > 0 ) {
		$mtime = filemtime( $file );
		if ( ! is_int( $mtime ) || ( time() - $mtime ) > $ttl ) {
			return '';
		}
	}
	$html = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return is_string( $html ) ? $html : '';
}

/**
 * @param array<string,mixed> $config Config.
 * @param string              $html   HTML.
 * @return bool
 */
function zaprocket_cache_put( array $config, $html ) {
	$html = (string) $html;
	if ( strlen( $html ) < 200 || strlen( $html ) > 2097152 ) {
		return false;
	}
	if ( false === stripos( $html, '<html' ) ) {
		return false;
	}
	$file = zaprocket_cache_file( $config );
	if ( '' === $file ) {
		return false;
	}
	$dir = dirname( $file );
	if ( ! zaprocket_cache_path_ok( $dir ) ) {
		return false;
	}
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		return false;
	}
	$ok = file_put_contents( $file, $html, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === $ok && function_exists( 'zaprocket_run_log' ) && ! get_transient( 'zaprocket_run_log_cache_write' ) ) {
		set_transient( 'zaprocket_run_log_cache_write', 1, MINUTE_IN_SECONDS );
		zaprocket_run_log(
			'speed',
			'error',
			pili__( '页面缓存写入失败。' ),
			array(
				'code' => 'cache_write',
			)
		);
	}
	return false !== $ok;
}
