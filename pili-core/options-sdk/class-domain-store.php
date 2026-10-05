<?php
/**
 * 按域读写 wp_options（pilipost__{domain}）。
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain store.
 */
final class PILI_Options_Domain_Store {

	/** @var array<string,array<string,mixed>> */
	private static $cache = array();

	/**
	 * @param string $domain Domain.
	 * @return array<string,mixed>
	 */
	public static function get( $domain ) {
		$domain = sanitize_key( (string) $domain );
		if ( isset( self::$cache[ $domain ] ) ) {
			return self::$cache[ $domain ];
		}
		$name = PILI_Options_Domain_Registry::option_name( $domain );
		$raw  = get_option( $name, array() );
		self::$cache[ $domain ] = is_array( $raw ) ? $raw : array();
		return self::$cache[ $domain ];
	}

	/**
	 * @param string               $domain Domain.
	 * @param array<string,mixed>  $data   Data.
	 * @return bool
	 */
	public static function update( $domain, array $data ) {
		$domain = sanitize_key( (string) $domain );
		$name   = PILI_Options_Domain_Registry::option_name( $domain );
		$prev   = self::get( $domain );
		// 未变更则不写库（G15：只动变化域）。
		if ( self::normalize( $prev ) === self::normalize( $data ) ) {
			self::$cache[ $domain ] = $data;
			return true;
		}
		$ok = update_option( $name, $data, false );
		self::$cache[ $domain ] = $data;
		if ( $ok ) {
			// 阶段 C：显式踢 options 对象缓存（含 Redis 等外部缓存）；update_option 成功时 WP 也会删，此处作双保险。
			wp_cache_delete( $name, 'options' );
			/**
			 * 域 option 行写入成功后。
			 *
			 * @param string               $domain Domain.
			 * @param string               $name   option_name（如 pilidoc__site）。
			 * @param array<string,mixed>  $data   Written chunk.
			 */
			do_action( 'pili_options_domain_updated', $domain, $name, $data );
			return true;
		}
		// update_option 在值「看似相同」时可能返回 false；再读确认。
		self::$cache[ $domain ] = null;
		unset( self::$cache[ $domain ] );
		$again = self::get( $domain );
		$matched = self::normalize( $again ) === self::normalize( $data );
		if ( $matched ) {
			wp_cache_delete( $name, 'options' );
			do_action( 'pili_options_domain_updated', $domain, $name, $data );
		}
		return $matched;
	}

	/**
	 * 合并全部域（导出 / 兼容读 / 校验用）。
	 *
	 * 业务禁止直调；仅 options 包、迁移工具、XUN 保存内核允许。
	 * WP_DEBUG 下对越界调用打警告。
	 *
	 * @return array<string,mixed>
	 */
	public static function merge_all() {
		self::warn_if_external_caller( 'merge_all' );
		return self::merge_domains( PILI_Options_Domain_Registry::domains() );
	}

	/**
	 * 仅合并指定域。
	 *
	 * @param string[] $domains Domains.
	 * @return array<string,mixed>
	 */
	public static function merge_domains( array $domains ) {
		$merged = array();
		foreach ( $domains as $domain ) {
			$domain = sanitize_key( (string) $domain );
			if ( '' === $domain ) {
				continue;
			}
			$chunk = self::get( $domain );
			if ( is_array( $chunk ) && $chunk ) {
				$merged = array_merge( $merged, $chunk );
			}
		}
		return $merged;
	}

	/**
	 * 按域拆分并写入。
	 *
	 * 先规划全部待写域并快照；任一步 Budget 熔断或写失败时回滚本批已提交域，避免半写。
	 *
	 * @param array<string,mixed> $flat        Flat options.
	 * @param bool                $replace_all true=迁移/整包真源（空域也写入）；false=防护模式（空桶不覆盖已有域）。
	 * @return bool 全部写入成功。
	 */
	public static function split_and_save( array $flat, $replace_all = false ) {
		self::warn_if_external_caller( 'split_and_save' );
		$buckets = PILI_Options_Domain_Registry::split_flat( $flat );
		$planned = array();
		foreach ( $buckets as $domain => $data ) {
			$data   = is_array( $data ) ? $data : array();
			$domain = (string) $domain;
			// T-SAVE-1：半包不得把未触及域清空。
			if ( ! $replace_all && array() === $data ) {
				continue;
			}
			if ( ! $replace_all && array() !== $data ) {
				$prev = self::get( $domain );
				if ( ! empty( $prev ) ) {
					$data = array_merge( $prev, $data );
				}
			}
			$planned[ $domain ] = $data;
		}

		if ( empty( $planned ) ) {
			return true;
		}

		$snapshots = array();
		foreach ( $planned as $domain => $_data ) {
			if ( class_exists( 'PILI_Options_Save_Budget' ) && PILI_Options_Save_Budget::is_active() ) {
				if ( ! PILI_Options_Save_Budget::check( 'domain_pre_' . sanitize_key( $domain ) ) ) {
					return false;
				}
			}
			$snapshots[ $domain ] = self::get( $domain );
		}

		$committed = array();
		foreach ( $planned as $domain => $data ) {
			if ( class_exists( 'PILI_Options_Save_Budget' ) && PILI_Options_Save_Budget::is_active() ) {
				if ( ! PILI_Options_Save_Budget::check( 'domain_' . sanitize_key( $domain ) ) ) {
					self::restore_domain_snapshots( $snapshots, $committed );
					return false;
				}
			}
			$write_ok = self::update( $domain, $data );
			if ( ! $write_ok ) {
				$again = self::get( $domain );
				if ( self::normalize( $again ) !== self::normalize( $data ) ) {
					$committed[] = $domain;
					self::restore_domain_snapshots( $snapshots, $committed );
					return false;
				}
			}
			$committed[] = $domain;
		}
		return true;
	}

	/**
	 * 将本批已写入域恢复为写入前快照。
	 *
	 * @param array<string,array<string,mixed>> $snapshots Snapshots keyed by domain.
	 * @param string[]                          $domains   Domains to restore.
	 * @return void
	 */
	private static function restore_domain_snapshots( array $snapshots, array $domains ) {
		foreach ( $domains as $domain ) {
			$domain = (string) $domain;
			if ( ! array_key_exists( $domain, $snapshots ) ) {
				continue;
			}
			self::update( $domain, is_array( $snapshots[ $domain ] ) ? $snapshots[ $domain ] : array() );
		}
	}

	/**
	 * 按字段 id 读单值（不 merge_all）。
	 *
	 * @param string $field_id Field id.
	 * @param mixed  $default  Default.
	 * @return mixed
	 */
	public static function get_field( $field_id, $default = null ) {
		$field_id = (string) $field_id;
		if ( '' === $field_id ) {
			return $default;
		}
		$domain = PILI_Options_Domain_Registry::domain_for_field( $field_id );
		$chunk  = self::get( $domain );
		if ( array_key_exists( $field_id, $chunk ) ) {
			return $chunk[ $field_id ];
		}
		return $default;
	}

	/**
	 * 按字段 id 写单值（只碰一域）。
	 *
	 * @param string $field_id Field id.
	 * @param mixed  $value    Value.
	 * @return bool
	 */
	public static function update_field( $field_id, $value ) {
		$field_id = (string) $field_id;
		if ( '' === $field_id ) {
			return false;
		}
		$domain = PILI_Options_Domain_Registry::domain_for_field( $field_id );
		$chunk  = self::get( $domain );
		$chunk[ $field_id ] = $value;
		return self::update( $domain, $chunk );
	}

	/**
	 * 规范化后比对（迁移校验）。
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	public static function normalize( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		self::ksort_recursive( $data );
		return wp_json_encode( $data );
	}

	/**
	 * @param array<string,mixed> $arr Array.
	 * @return void
	 */
	private static function ksort_recursive( array &$arr ) {
		ksort( $arr );
		foreach ( $arr as &$v ) {
			if ( is_array( $v ) ) {
				self::ksort_recursive( $v );
			}
		}
	}

	/**
	 * 清请求内缓存。
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = array();
	}

	/**
	 * WP_DEBUG：业务路径直调 merge_all / split_and_save 时告警。
	 *
	 * @param string $op Operation name.
	 * @return void
	 */
	private static function warn_if_external_caller( $op ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
		foreach ( $trace as $frame ) {
			$file = isset( $frame['file'] ) ? str_replace( '\\', '/', (string) $frame['file'] ) : '';
			if ( '' === $file ) {
				continue;
			}
			if ( false !== strpos( $file, '/includes/options/' ) ) {
				return;
			}
			if ( false !== strpos( $file, '/vendor-xun/' ) ) {
				return;
			}
		}
		$from = isset( $trace[2]['file'] ) ? (string) $trace[2]['file'] : 'unknown';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'pilipost Domain_Store::' . $op . ' called outside options/XUN allowlist from ' . $from );
	}
}
