<?php
/**
 * Path B options facade for ZapRocket domains.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write helpers over pili_domain_get / pili_domain_save.
 */
final class ZapRocket_Options {

	/**
	 * Known domain slugs.
	 *
	 * @return string[]
	 */
	public static function domains() {
		return array( 'slim', 'speed', 'dbopt', 'general', 'oss' );
	}

	/**
	 * Get a single field value from a domain.
	 *
	 * @param string $domain Domain slug.
	 * @param string $key    Field id.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $domain, $key, $default = null ) {
		$domain = sanitize_key( (string) $domain );
		$key    = (string) $key;
		if ( '' === $domain || '' === $key ) {
			return $default;
		}
		if ( function_exists( 'pili_domain_get' ) ) {
			$value = pili_domain_get( $domain, $key );
			return ( null === $value ) ? $default : $value;
		}
		$bucket = get_option( 'zaprocket__' . $domain, array() );
		if ( ! is_array( $bucket ) || ! array_key_exists( $key, $bucket ) ) {
			return $default;
		}
		return $bucket[ $key ];
	}

	/**
	 * Merge-save fields into a domain (Path B).
	 *
	 * @param string               $domain Domain.
	 * @param array<string,mixed>  $fields Field map.
	 * @return bool
	 */
	public static function save( $domain, array $fields ) {
		$domain = sanitize_key( (string) $domain );
		if ( '' === $domain || array() === $fields ) {
			return false;
		}

		$existing = array();
		if ( function_exists( 'pili_domain_get' ) ) {
			$chunk = pili_domain_get( $domain, null );
			$existing = is_array( $chunk ) ? $chunk : array();
		} else {
			$raw = get_option( 'zaprocket__' . $domain, array() );
			$existing = is_array( $raw ) ? $raw : array();
		}

		$merged = array_merge( $existing, $fields );

		if ( function_exists( 'pili_domain_save' ) ) {
			$result = pili_domain_save( $domain, $merged );
			return ! empty( $result['success'] );
		}

		return (bool) update_option( 'zaprocket__' . $domain, $merged, false );
	}

	/**
	 * Switch-like values: non-empty is on (true / '1' / 1). Empty / 0 / '0' is off.
	 *
	 * @param mixed $value Stored option.
	 * @return bool
	 */
	public static function is_on( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		return ! empty( $value );
	}

	/**
	 * Slim domain switch.
	 *
	 * @param string $key     Field id.
	 * @param mixed  $default Default when missing.
	 * @return bool
	 */
	public static function slim_on( $key, $default = false ) {
		return self::is_on( self::get( 'slim', (string) $key, $default ) );
	}

	/**
	 * Speed domain switch.
	 *
	 * @param string $key     Field id.
	 * @param mixed  $default Default when missing.
	 * @return bool
	 */
	public static function speed_on( $key, $default = false ) {
		return self::is_on( self::get( 'speed', (string) $key, $default ) );
	}

	/**
	 * Newline list from a textarea field.
	 *
	 * @param string $domain Domain.
	 * @param string $key    Field id.
	 * @param string $default Default text.
	 * @return string[]
	 */
	public static function lines( $domain, $key, $default = '' ) {
		$raw  = (string) self::get( $domain, $key, $default );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$out  = array();
		foreach ( explode( "\n", $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || preg_match( '/^\s*javascript:/i', $line ) ) {
				continue;
			}
			$out[] = $line;
		}
		return $out;
	}

	/**
	 * Whether haystack matches any exclude line (substring or (.*) wildcard).
	 *
	 * @param string   $haystack URL / handle / tag.
	 * @param string[] $needles  Lines.
	 * @return bool
	 */
	public static function matches_any( $haystack, array $needles ) {
		foreach ( $needles as $needle ) {
			if ( self::matches_needle( $haystack, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	public static function matches_needle( $haystack, $needle ) {
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

	public static function is_plugin_enabled() {
		return self::is_on( self::get( 'general', 'zr_gen_enabled', true ) );
	}

	/**
	 * Whether optimize log writing is enabled (legacy option; cleanup now uses run log).
	 *
	 * @return bool
	 */
	public static function is_logging_enabled() {
		return self::is_on( self::get( 'general', 'zr_gen_log_enabled', true ) );
	}

	/**
	 * Global run log (independent from optimize log).
	 *
	 * @return bool
	 */
	public static function is_run_log_enabled() {
		return self::is_on( self::get( 'general', 'zr_gen_run_log_enabled', true ) );
	}

	/**
	 * Whether OSS connection-test failures write to the run log.
	 *
	 * @return bool
	 */
	public static function is_oss_test_log_enabled() {
		return self::is_on( self::get( 'general', 'zr_gen_oss_test_log', false ) );
	}

	/**
	 * Run log retain days (1–365, default 30).
	 *
	 * @return int
	 */
	public static function run_log_retain_days() {
		$n = (int) self::get( 'general', 'zr_gen_run_log_days', 30 );
		if ( $n < 1 ) {
			$n = 30;
		}
		if ( $n > 365 ) {
			$n = 365;
		}
		return $n;
	}
}
