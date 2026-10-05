<?php
/**
 * 配置域注册表：字段 id → domain（折中 A）。
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain registry.
 */
final class PILI_Options_Domain_Registry {

	const OPTION_PREFIX = 'pili__';

	/**
	 * 已知域列表。
	 *
	 * @return string[]
	 */
	public static function domains() {
		$domains = array( 'demo', 'content', 'settings', 'advanced' );
		/**
		 * @param string[] $domains Domains.
		 */
		return array_values( array_unique( array_map( 'sanitize_key', (array) apply_filters( 'pili_options_domains', $domains ) ) ) );
	}

	/**
	 * option_name = {option_prefix}{domain}
	 *
	 * @param string $domain Domain.
	 * @return string
	 */
	public static function option_name( $domain ) {
		$domain = sanitize_key( (string) $domain );
		if ( '' === $domain ) {
			$domain = 'advanced';
		}
		$prefix = self::OPTION_PREFIX;
		if ( class_exists( 'PILI_Config', false ) ) {
			$cfg = PILI_Config::get( 'option_prefix', self::OPTION_PREFIX );
			if ( is_string( $cfg ) && '' !== $cfg ) {
				$prefix = $cfg;
			}
		}
		return $prefix . $domain;
	}

	/**
	 * 字段 id → domain（前缀启发式；可用过滤器覆盖）。
	 *
	 * @param string $field_id Field id.
	 * @return string
	 */
	public static function domain_for_field( $field_id ) {
		$field_id = (string) $field_id;
		$map      = self::prefix_map();
		foreach ( $map as $prefix => $domain ) {
			if ( 0 === strpos( $field_id, $prefix ) ) {
				/**
				 * @param string $domain   Domain.
				 * @param string $field_id Field.
				 */
				return (string) apply_filters( 'PILI_Options_domain_for_field', $domain, $field_id );
			}
		}
		$fallback = 'advanced';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$msg = 'pili options: field missing domain mapping: ' . $field_id;
			if ( defined( 'PILI_Options_STRICT_DOMAIN' ) && PILI_Options_STRICT_DOMAIN ) {
				throw new RuntimeException( $msg );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $msg );
		}
		return (string) apply_filters( 'PILI_Options_domain_for_field', $fallback, $field_id );
	}

	/**
	 * 前缀 → 域（长前缀优先）。
	 *
	 * @return array<string,string>
	 */
	private static function prefix_map() {
		$map = array(
			'demo_'     => 'demo',
			'content_'  => 'content',
			'settings_' => 'settings',
		);
		/**
		 * @param array<string,string> $map Prefix => domain.
		 */
		$map = (array) apply_filters( 'pili_options_domain_prefix_map', $map );
		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		return $map;
	}

	/**
	 * 把扁平字段数组拆成域桶。
	 *
	 * @param array<string,mixed> $flat Flat options.
	 * @return array<string,array<string,mixed>>
	 */
	public static function split_flat( array $flat ) {
		$buckets = array();
		foreach ( self::domains() as $domain ) {
			$buckets[ $domain ] = array();
		}
		foreach ( $flat as $field_id => $value ) {
			$domain = self::domain_for_field( (string) $field_id );
			if ( ! isset( $buckets[ $domain ] ) ) {
				$buckets[ $domain ] = array();
			}
			$buckets[ $domain ][ (string) $field_id ] = $value;
		}
		return $buckets;
	}
}
