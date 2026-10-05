<?php
/**
 * Locate pili-core directory for host plugins/themes (C1).
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve filesystem path to pili-core (directory containing bootstrap.php).
 *
 * Order:
 * 1. Constant PILI_CORE_DIR (host override)
 * 2. $hint_dir if it already contains bootstrap.php
 * 3. dirname($hint_dir, 2) — examples/wp-*-skeleton layout
 * 4. Common sibling paths under WP_CONTENT_DIR / plugin dir
 *
 * @param string $hint_dir Usually __DIR__ of the skeleton/bootstrap caller.
 * @return string Absolute path with trailing slash, or '' if not found.
 */
function pili_locate_core_dir( $hint_dir = '' ) {
	if ( defined( 'PILI_CORE_DIR' ) && is_string( PILI_CORE_DIR ) && '' !== PILI_CORE_DIR ) {
		$dir = trailingslashit( wp_normalize_path( PILI_CORE_DIR ) );
		if ( is_readable( $dir . 'bootstrap.php' ) ) {
			return $dir;
		}
	}

	$hint_dir = is_string( $hint_dir ) ? wp_normalize_path( $hint_dir ) : '';
	$candidates = array();

	if ( '' !== $hint_dir ) {
		$candidates[] = trailingslashit( $hint_dir );
		$candidates[] = trailingslashit( dirname( $hint_dir ) );
		$candidates[] = trailingslashit( dirname( $hint_dir, 2 ) );
		$candidates[] = trailingslashit( dirname( $hint_dir, 3 ) );
	}

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		$candidates[] = trailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/packages/pili-core' ) );
		$candidates[] = trailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/plugins/pili-core' ) );
		$candidates[] = trailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/plugins/pilipost/packages/pili-core' ) );
	}

	/**
	 * Extra candidate roots for pili-core (absolute paths).
	 *
	 * @param array<int,string> $candidates Candidates.
	 * @param string              $hint_dir   Hint.
	 */
	$extra = apply_filters( 'pili_locate_core_candidates', array(), $hint_dir );
	if ( is_array( $extra ) ) {
		foreach ( $extra as $c ) {
			$candidates[] = trailingslashit( wp_normalize_path( (string) $c ) );
		}
	}

	$seen = array();
	foreach ( $candidates as $dir ) {
		$dir = trailingslashit( (string) $dir );
		if ( '' === $dir || isset( $seen[ $dir ] ) ) {
			continue;
		}
		$seen[ $dir ] = true;
		if ( is_readable( $dir . 'bootstrap.php' ) ) {
			return $dir;
		}
	}

	return '';
}

/**
 * Resolve public URL for pili-core assets.
 *
 * @param string $core_dir From pili_locate_core_dir().
 * @return string Trailing-slash URL or ''.
 */
function pili_locate_core_url( $core_dir ) {
	if ( defined( 'PILI_CORE_URL' ) && is_string( PILI_CORE_URL ) && '' !== PILI_CORE_URL ) {
		return trailingslashit( PILI_CORE_URL );
	}

	$core_dir = trailingslashit( wp_normalize_path( (string) $core_dir ) );
	if ( '' === $core_dir || ! is_readable( $core_dir . 'bootstrap.php' ) ) {
		return '';
	}

	$pilipost_main = dirname( $core_dir, 2 ) . '/pilipost.php';
	if ( is_readable( $pilipost_main ) && function_exists( 'plugins_url' ) ) {
		return trailingslashit( plugins_url( 'packages/pili-core', $pilipost_main ) );
	}

	if ( defined( 'WP_CONTENT_DIR' ) && function_exists( 'content_url' ) ) {
		$content = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		if ( 0 === strpos( $core_dir, $content ) ) {
			$rel = ltrim( substr( $core_dir, strlen( $content ) ), '/' );
			return trailingslashit( content_url( $rel ) );
		}
	}

	if ( function_exists( 'plugins_url' ) ) {
		return trailingslashit( plugins_url( '', $core_dir . 'bootstrap.php' ) );
	}

	return '';
}
