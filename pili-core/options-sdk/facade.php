<?php
/**
 * 业务配置读写门面（阶段一 · v1.5）。
 *
 * 契约锁定：签名与对外行为见《插件端架构设计优化.md》§3.1。
 * 业务优先本文件；禁止业务直调 Domain_Store::split_and_save / merge_all。
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 域是否在注册表中合法。
 *
 * @param string $domain Domain.
 * @return bool
 */
function pili_domain_is_known( $domain ) {
	$domain = sanitize_key( (string) $domain );
	if ( '' === $domain ) {
		return false;
	}
	return in_array( $domain, PILI_Options_Domain_Registry::domains(), true );
}

/**
 * 读取指定域配置。
 *
 * @param string      $domain 域标识。
 * @param string|null $field  字段 id；null 返回该域全部字段（数组）。
 * @return mixed 非法 domain：null（$field 非 null）或空数组（$field 为 null）；合法时按契约返回。
 */
function pili_domain_get( string $domain, ?string $field = null ) {
	$domain = sanitize_key( $domain );
	if ( ! pili_domain_is_known( $domain ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'pili_domain_get: invalid domain: ' . $domain );
		}
		return null === $field ? array() : null;
	}

	$chunk = PILI_Options_Domain_Store::get( $domain );
	if ( null === $field ) {
		return is_array( $chunk ) ? $chunk : array();
	}

	$field = (string) $field;
	if ( '' === $field ) {
		return null;
	}
	if ( array_key_exists( $field, $chunk ) ) {
		return $chunk[ $field ];
	}
	return null;
}

/**
 * 保存指定域数据集（整域替换写入；调用方需自行合并局部字段）。
 *
 * @param string              $domain 域标识。
 * @param array<string,mixed> $data   本域要写入的字段集合。
 * @return array{success:bool,error_message:string}
 */
function pili_domain_save( string $domain, array $data ): array {
	$domain = sanitize_key( $domain );
	if ( ! pili_domain_is_known( $domain ) ) {
		return array(
			'success'       => false,
			'error_message' => 'invalid_domain',
		);
	}

	$encoded = wp_json_encode( $data );
	if ( false === $encoded ) {
		return array(
			'success'       => false,
			'error_message' => 'json_encode_failed',
		);
	}

	if ( class_exists( 'PILI_Options_Save_Budget', false ) && PILI_Options_Save_Budget::is_active() ) {
		if ( ! PILI_Options_Save_Budget::check( 'facade_domain_' . $domain ) ) {
			$msg = PILI_Options_Save_Budget::friendly_message();
			return array(
				'success'       => false,
				'error_message' => '' !== $msg ? $msg : 'budget_tripped',
			);
		}
	}

	$snapshot = PILI_Options_Domain_Store::get( $domain );
	$ok       = PILI_Options_Domain_Store::update( $domain, $data );

	if ( ! $ok ) {
		// 再读确认：未真正落库则恢复快照缓存语义（update 失败路径已尽量自检）。
		$again = PILI_Options_Domain_Store::get( $domain );
		if ( PILI_Options_Domain_Store::normalize( $again ) !== PILI_Options_Domain_Store::normalize( $data ) ) {
			PILI_Options_Domain_Store::update( $domain, is_array( $snapshot ) ? $snapshot : array() );
			return array(
				'success'       => false,
				'error_message' => 'write_failed',
			);
		}
	}

	return array(
		'success'       => true,
		'error_message' => '',
	);
}
