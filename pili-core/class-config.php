<?php
/**
 * PILI 实例配置（多实例：单份 PHP + Config 隔离）。
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-instance configuration store.
 */
final class PILI_Config {

	/** @var array<string,array<string,mixed>> */
	private static $instances = array();

	/** @var string|null */
	private static $current = null;

	/**
	 * Default config values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'instance_id'   => 'default',
			'option_prefix' => 'pili__',
			'text_domain'   => 'pili',
			'filter_ns'     => 'pili',
			'ajax_ns'       => 'pili',
			'asset_prefix'  => 'pili',
			'css_scope'     => 'pili-inst-default',
			'event_ns'      => 'pili:default',
			'write_ok_key'  => 'pili_options_domain_write_ok',
		);
	}

	/**
	 * Register / overwrite an instance config.
	 *
	 * @param string               $instance_id Instance slug.
	 * @param array<string,mixed>  $config      Overrides.
	 * @return array<string,mixed>
	 */
	public static function register( $instance_id, array $config = array() ) {
		$instance_id = sanitize_key( (string) $instance_id );
		if ( '' === $instance_id ) {
			$instance_id = 'default';
		}
		$merged = array_merge( self::defaults(), $config );
		$merged['instance_id'] = $instance_id;
		if ( empty( $merged['css_scope'] ) || 'pili-inst-default' === $merged['css_scope'] ) {
			$merged['css_scope'] = 'pili-inst-' . $instance_id;
		}
		if ( empty( $merged['event_ns'] ) || 'pili:default' === $merged['event_ns'] ) {
			$merged['event_ns'] = 'pili:' . $instance_id;
		}
		self::$instances[ $instance_id ] = $merged;
		if ( null === self::$current ) {
			self::$current = $instance_id;
		}
		return $merged;
	}

	/**
	 * Set current instance for subsequent framework calls.
	 *
	 * @param string $instance_id Instance.
	 * @return void
	 */
	public static function use_instance( $instance_id ) {
		$instance_id = sanitize_key( (string) $instance_id );
		if ( isset( self::$instances[ $instance_id ] ) ) {
			self::$current = $instance_id;
		}
	}

	/**
	 * Get config value for current (or named) instance.
	 *
	 * @param string      $key          Key.
	 * @param mixed       $default      Default.
	 * @param string|null $instance_id  Optional instance.
	 * @return mixed
	 */
	public static function get( $key, $default = null, $instance_id = null ) {
		$id = $instance_id ? sanitize_key( (string) $instance_id ) : self::$current;
		if ( ! $id || ! isset( self::$instances[ $id ] ) ) {
			$defs = self::defaults();
			return array_key_exists( $key, $defs ) ? $defs[ $key ] : $default;
		}
		$cfg = self::$instances[ $id ];
		return array_key_exists( $key, $cfg ) ? $cfg[ $key ] : $default;
	}

	/**
	 * @return string|null
	 */
	public static function current_id() {
		return self::$current;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		return self::$instances;
	}
}
