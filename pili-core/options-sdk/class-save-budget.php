<?php
/**
 * 配置保存预算熔断（阶段 5 / P12）。
 *
 * 内存 ≥80% 或 已用时间 ≥70% → 中止后续写入，返回可读诊断。
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save budget fuse.
 */
final class PILI_Options_Save_Budget {

	const MEM_RATIO  = 0.80;
	const TIME_RATIO = 0.70;

	/** @var bool */
	private static $active = false;

	/** @var bool */
	private static $tripped = false;

	/** @var float */
	private static $started_at = 0.0;

	/** @var int */
	private static $mem_limit = 0;

	/** @var float */
	private static $time_limit = 0.0;

	/** @var string */
	private static $reason = '';

	/** @var string */
	private static $checkpoint = '';

	/**
	 * 开始一次保存预算会话。
	 *
	 * @return void
	 */
	public static function begin() {
		self::$active     = true;
		self::$tripped    = false;
		self::$reason     = '';
		self::$checkpoint = '';
		self::$started_at = microtime( true );
		self::$mem_limit  = self::parse_memory_limit( (string) ini_get( 'memory_limit' ) );
		$raw_time         = (float) ini_get( 'max_execution_time' );
		// 0 / 无限制时按 30s 弱机基线计，避免永不熔断。
		self::$time_limit = ( $raw_time > 0 ) ? $raw_time : 30.0;
	}

	/**
	 * 结束会话。
	 *
	 * @return void
	 */
	public static function end() {
		self::$active = false;
	}

	/**
	 * @return bool
	 */
	public static function is_active() {
		return self::$active;
	}

	/**
	 * @return bool
	 */
	public static function tripped() {
		return self::$tripped;
	}

	/**
	 * 检查点；返回 true=可继续，false=已熔断。
	 *
	 * @param string $checkpoint Label.
	 * @return bool
	 */
	public static function check( $checkpoint = '' ) {
		if ( ! self::$active ) {
			return true;
		}
		if ( self::$tripped ) {
			return false;
		}

		$checkpoint = sanitize_key( (string) $checkpoint );
		$mem_usage  = memory_get_usage( true );
		$elapsed    = microtime( true ) - self::$started_at;

		$mem_hit  = ( self::$mem_limit > 0 ) && ( $mem_usage >= ( self::$mem_limit * self::MEM_RATIO ) );
		$time_hit = ( self::$time_limit > 0 ) && ( $elapsed >= ( self::$time_limit * self::TIME_RATIO ) );

		if ( ! $mem_hit && ! $time_hit ) {
			return true;
		}

		self::$tripped    = true;
		self::$checkpoint = $checkpoint;
		if ( $mem_hit && $time_hit ) {
			self::$reason = 'memory_and_time';
		} elseif ( $mem_hit ) {
			self::$reason = 'memory';
		} else {
			self::$reason = 'time';
		}
		return false;
	}

	/**
	 * 诊断快照（可进 JSON）。
	 *
	 * @return array<string,mixed>
	 */
	public static function snapshot() {
		$mem_usage = memory_get_usage( true );
		$elapsed   = self::$started_at > 0 ? ( microtime( true ) - self::$started_at ) : 0.0;
		return array(
			'tripped'       => self::$tripped,
			'reason'        => self::$reason,
			'checkpoint'    => self::$checkpoint,
			'memoryUsage'   => $mem_usage,
			'memoryLimit'   => self::$mem_limit,
			'memoryRatio'   => self::MEM_RATIO,
			'timeElapsed'   => round( $elapsed, 3 ),
			'timeLimit'     => self::$time_limit,
			'timeRatio'     => self::TIME_RATIO,
			'friendly'      => self::friendly_message(),
		);
	}

	/**
	 * @return string
	 */
	public static function friendly_message() {
		if ( ! self::$tripped ) {
			return '';
		}
		if ( 'memory' === self::$reason ) {
			return '服务器内存接近上限，已中止保存且未继续写入。请改用「保存当前页」，或稍后再试。';
		}
		if ( 'time' === self::$reason ) {
			return '保存耗时接近执行时间上限，已中止写入。请改用「保存当前页」，或稍后再试。';
		}
		return '保存资源不足（内存/时间），已中止写入。请改用「保存当前页」，或稍后再试。';
	}

	/**
	 * @param string $raw ini memory_limit.
	 * @return int Bytes；无限制返回 0。
	 */
	private static function parse_memory_limit( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || '-1' === $raw ) {
			return 0;
		}
		$unit = strtolower( substr( $raw, -1 ) );
		$num  = (float) $raw;
		switch ( $unit ) {
			case 'g':
				$num *= 1024;
				// no break.
			case 'm':
				$num *= 1024;
				// no break.
			case 'k':
				$num *= 1024;
				break;
			default:
				$num = (float) $raw;
		}
		return (int) $num;
	}
}
