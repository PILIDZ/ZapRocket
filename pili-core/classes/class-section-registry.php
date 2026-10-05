<?php
/**
 * Section meta registry — deferred field loading (M3).
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds section shells + loaders; tracks which sections have hydrated fields.
 */
final class PILI_Section_Registry {

	/** @var array<string,array<string,array<string,mixed>>> unique => section_id => meta */
	private static $meta = array();

	/** @var array<string,array<string,true>> unique => section_id => true */
	private static $loaded = array();

	/** @var array<string,array<int,string>> unique => list of section ids in register order */
	private static $order = array();

	/**
	 * Register section meta (may omit fields; provide loader for deferred hydrate).
	 *
	 * @param string              $unique Options unique id.
	 * @param array<string,mixed> $meta   Must include id; optional title/icon/parent/loader/fields.
	 * @return void
	 */
	public static function register( $unique, array $meta ) {
		$unique = (string) $unique;
		$id     = isset( $meta['id'] ) ? sanitize_key( (string) $meta['id'] ) : '';
		if ( '' === $unique || '' === $id ) {
			return;
		}
		if ( ! isset( self::$meta[ $unique ] ) ) {
			self::$meta[ $unique ]  = array();
			self::$order[ $unique ] = array();
		}
		self::$meta[ $unique ][ $id ] = $meta;
		if ( ! in_array( $id, self::$order[ $unique ], true ) ) {
			self::$order[ $unique ][] = $id;
		}
	}

	/**
	 * @param string $unique Unique.
	 * @return int
	 */
	public static function count( $unique ) {
		$unique = (string) $unique;
		return isset( self::$order[ $unique ] ) ? count( self::$order[ $unique ] ) : 0;
	}

	/**
	 * @param string $unique Unique.
	 * @return int
	 */
	public static function loaded_count( $unique ) {
		$unique = (string) $unique;
		return isset( self::$loaded[ $unique ] ) ? count( self::$loaded[ $unique ] ) : 0;
	}

	/**
	 * @param string $unique Unique.
	 * @param string $section_id Section id.
	 * @return bool
	 */
	public static function is_loaded( $unique, $section_id ) {
		$unique     = (string) $unique;
		$section_id = sanitize_key( (string) $section_id );
		return isset( self::$loaded[ $unique ][ $section_id ] );
	}

	/**
	 * @param string $unique Unique.
	 * @param string $section_id Section id.
	 * @return void
	 */
	public static function mark_loaded( $unique, $section_id ) {
		$unique     = (string) $unique;
		$section_id = sanitize_key( (string) $section_id );
		if ( '' === $unique || '' === $section_id ) {
			return;
		}
		if ( ! isset( self::$loaded[ $unique ] ) ) {
			self::$loaded[ $unique ] = array();
		}
		self::$loaded[ $unique ][ $section_id ] = true;
	}

	/**
	 * @param string $unique Unique.
	 * @param string $section_id Section id.
	 * @return array<string,mixed>|null
	 */
	public static function get_meta( $unique, $section_id ) {
		$unique     = (string) $unique;
		$section_id = sanitize_key( (string) $section_id );
		return isset( self::$meta[ $unique ][ $section_id ] ) ? self::$meta[ $unique ][ $section_id ] : null;
	}

	/**
	 * Whether meta has a deferred loader.
	 *
	 * @param string $unique Unique.
	 * @param string $section_id Section id.
	 * @return bool
	 */
	public static function has_loader( $unique, $section_id ) {
		$meta = self::get_meta( $unique, $section_id );
		return is_array( $meta ) && isset( $meta['loader'] ) && ( is_callable( $meta['loader'] ) || ( is_string( $meta['loader'] ) && '' !== $meta['loader'] ) );
	}

	/**
	 * Allowed filesystem roots for path loaders.
	 *
	 * @return array<int,string>
	 */
	public static function loader_roots() {
		$roots = array();
		if ( defined( 'PILI_CORE_DIR' ) && PILI_CORE_DIR ) {
			$roots[] = wp_normalize_path( untrailingslashit( PILI_CORE_DIR ) );
		}
		/**
		 * Extra roots for section path loaders (realpath prefixes).
		 *
		 * @param array<int,string> $roots Roots.
		 */
		$extra = apply_filters( 'pili_section_loader_roots', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $r ) {
				$r = wp_normalize_path( untrailingslashit( (string) $r ) );
				if ( '' !== $r ) {
					$roots[] = $r;
				}
			}
		}
		return array_values( array_unique( $roots ) );
	}

	/**
	 * Run loader; returns fields array or empty array on failure.
	 *
	 * @param string $unique Unique.
	 * @param string $section_id Section id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function run_loader( $unique, $section_id ) {
		$unique     = (string) $unique;
		$section_id = sanitize_key( (string) $section_id );
		$meta       = self::get_meta( $unique, $section_id );
		if ( ! is_array( $meta ) || empty( $meta['loader'] ) ) {
			return array();
		}

		$loader = $meta['loader'];
		$fields = null;

		if ( is_callable( $loader ) ) {
			$fields = call_user_func( $loader, $unique, $meta );
		} elseif ( is_string( $loader ) ) {
			$fields = self::include_loader_path( $loader, $unique, $meta );
		}

		if ( ! is_array( $fields ) ) {
			return array();
		}

		// Allow loader to return full section with fields key.
		if ( isset( $fields['fields'] ) && is_array( $fields['fields'] ) ) {
			$fields = $fields['fields'];
		}

		return array_values( $fields );
	}

	/**
	 * Safe include: path must resolve under allowlisted roots.
	 *
	 * @param string              $path   Relative or absolute path.
	 * @param string              $unique Unique.
	 * @param array<string,mixed> $meta   Meta.
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function include_loader_path( $path, $unique, array $meta ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return null;
		}

		// Relative → under PILI_CORE_DIR.
		if ( defined( 'PILI_CORE_DIR' ) && PILI_CORE_DIR && ! preg_match( '#^(?:[a-zA-Z]:\\\\|/|\\\\)#', $path ) ) {
			$path = PILI_CORE_DIR . ltrim( str_replace( '\\', '/', $path ), '/' );
		}

		$real = realpath( $path );
		if ( false === $real || ! is_readable( $real ) || ! is_file( $real ) ) {
			return null;
		}
		$real_n = wp_normalize_path( $real );
		$ok     = false;
		foreach ( self::loader_roots() as $root ) {
			$root = rtrim( $root, '/' );
			if ( '' !== $root && ( $real_n === $root || 0 === strpos( $real_n, $root . '/' ) ) ) {
				$ok = true;
				break;
			}
		}
		if ( ! $ok ) {
			return null;
		}

		$result = include $real;
		if ( is_array( $result ) ) {
			return $result;
		}
		return null;
	}

	/**
	 * Stats for probes.
	 *
	 * @param string $unique Unique.
	 * @return array{registry:int,loaded:int,ids:array<int,string>}
	 */
	public static function stats( $unique ) {
		$unique = (string) $unique;
		return array(
			'registry' => self::count( $unique ),
			'loaded'   => self::loaded_count( $unique ),
			'ids'      => isset( self::$order[ $unique ] ) ? self::$order[ $unique ] : array(),
		);
	}

	/**
	 * Test helper: reset all state.
	 *
	 * @return void
	 */
	public static function reset_all() {
		self::$meta   = array();
		self::$loaded = array();
		self::$order  = array();
	}
}
