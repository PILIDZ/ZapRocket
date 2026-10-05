<?php
/**
 * Estimate reclaimable bytes for db-clean preview (no DELETE).
 *
 * Prefers information_schema table stats. Never full-table SUM.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Size estimator for junk-clean UI.
 */
final class ZapRocket_Db_Size_Estimator {

	const TIMEOUT_SEC = 4.0;

	/**
	 * Fallback average row bytes when information_schema is empty.
	 *
	 * @var array<string,int>
	 */
	const FALLBACK_AVG = array(
		'posts'       => 2048,
		'comments'    => 512,
		'options'     => 256,
		'postmeta'    => 160,
		'commentmeta' => 128,
		'termmeta'    => 128,
	);

	/**
	 * @var float
	 */
	private $started = 0.0;

	/**
	 * @var bool
	 */
	private $timed_out = false;

	/**
	 * @var array<string,array{rows:int,data:int,index:int,free:int}>
	 */
	private $tables = array();

	/**
	 * Estimate bytes for each key using already counted rows.
	 *
	 * @param array<string,int> $counts Count per task key.
	 * @return array{items:array<string,array<string,mixed>>,total_bytes:int,timed_out:bool,label:string,note:string}
	 */
	public function estimate_all( array $counts ) {
		$this->started   = microtime( true );
		$this->timed_out = false;
		$this->load_table_stats();

		$items = array();
		$total = 0;
		foreach ( $counts as $key => $count ) {
			$key   = sanitize_key( (string) $key );
			$count = (int) $count;
			if ( $this->expired() ) {
				$this->timed_out = true;
				$items[ $key ]   = $this->timeout_row( $count );
				continue;
			}
			$row             = $this->estimate_one( $key, $count );
			$items[ $key ]   = $row;
			if ( ! empty( $row['ok'] ) ) {
				$total += (int) $row['bytes'];
			}
		}

		$note = pili__( '体积为估算值，仅供参考；部分数据只能估算，无法拿到精确值。实际释放空间以清理完成后为准。' );
		if ( $this->timed_out ) {
			$note = pili__( '无法估算，站点数据量大，估算超时' );
		}

		return array(
			'items'       => $items,
			'total_bytes' => $total,
			'timed_out'   => $this->timed_out,
			'label'       => $this->timed_out ? $note : self::format_bytes( $total ),
			'note'        => $note,
		);
	}

	/**
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function format_bytes( $bytes ) {
		$bytes = (int) $bytes;
		if ( $bytes < 0 ) {
			$bytes = 0;
		}
		if ( function_exists( 'size_format' ) ) {
			$decimals = $bytes >= 1073741824 ? 2 : 1;
			$out      = size_format( $bytes, $decimals );
			if ( is_string( $out ) && '' !== $out ) {
				return $out;
			}
		}
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1048576 ) {
			return number_format( $bytes / 1024, 1 ) . ' KB';
		}
		if ( $bytes < 1073741824 ) {
			return number_format( $bytes / 1048576, 1 ) . ' MB';
		}
		return number_format( $bytes / 1073741824, 2 ) . ' GB';
	}

	/**
	 * @param string $key   Task key.
	 * @param int    $count Row count.
	 * @return array<string,mixed>
	 */
	private function estimate_one( $key, $count ) {
		$bytes = 0;
		switch ( $key ) {
			case 'revisions':
			case 'autodrafts':
			case 'trash_posts':
				$bytes = $count * $this->avg_row( 'posts' );
				break;
			case 'spam_comments':
			case 'trash_comments':
				$bytes = $count * $this->avg_row( 'comments' );
				break;
			case 'expired_transients':
				// timeout row + value row.
				$bytes = $count * 2 * $this->avg_row( 'options' );
				break;
			case 'orphaned_meta':
				$avg    = (int) round(
					(
						$this->avg_row( 'postmeta' )
						+ $this->avg_row( 'commentmeta' )
						+ $this->avg_row( 'termmeta' )
					) / 3
				);
				$bytes = $count * max( 1, $avg );
				break;
			case 'optimize_tables':
				$bytes = $this->optimize_free_bytes();
				break;
			default:
				$bytes = 0;
		}

		return array(
			'bytes' => (int) $bytes,
			'ok'    => true,
			'label' => self::format_bytes( (int) $bytes ),
		);
	}

	/**
	 * @param int $count Count.
	 * @return array<string,mixed>
	 */
	private function timeout_row( $count ) {
		unset( $count );
		$msg = pili__( '无法估算，站点数据量大，估算超时' );
		return array(
			'bytes' => 0,
			'ok'    => false,
			'label' => $msg,
		);
	}

	/**
	 * @return int
	 */
	private function optimize_free_bytes() {
		$sum = 0;
		foreach ( array( 'posts', 'postmeta', 'comments', 'commentmeta', 'options', 'terms', 'termmeta' ) as $slot ) {
			if ( isset( $this->tables[ $slot ] ) ) {
				$sum += (int) $this->tables[ $slot ]['free'];
			}
		}
		return $sum;
	}

	/**
	 * @param string $slot Logical table slot.
	 * @return int
	 */
	private function avg_row( $slot ) {
		if ( isset( $this->tables[ $slot ] ) ) {
			$rows = (int) $this->tables[ $slot ]['rows'];
			$data = (int) $this->tables[ $slot ]['data'] + (int) $this->tables[ $slot ]['index'];
			if ( $rows > 0 && $data > 0 ) {
				return max( 1, (int) round( $data / $rows ) );
			}
			if ( $data > 0 ) {
				return max( 1, $data );
			}
		}
		return isset( self::FALLBACK_AVG[ $slot ] ) ? (int) self::FALLBACK_AVG[ $slot ] : 256;
	}

	/**
	 * @return void
	 */
	private function load_table_stats() {
		global $wpdb;
		$this->tables = array();
		$map          = array(
			$wpdb->posts       => 'posts',
			$wpdb->postmeta    => 'postmeta',
			$wpdb->comments    => 'comments',
			$wpdb->commentmeta => 'commentmeta',
			$wpdb->options     => 'options',
			$wpdb->terms       => 'terms',
			$wpdb->termmeta    => 'termmeta',
		);
		$names = array();
		foreach ( array_keys( $map ) as $name ) {
			if ( is_string( $name ) && preg_match( '/^[A-Za-z0-9_]+$/', $name ) ) {
				$names[] = $name;
			}
		}
		if ( array() === $names ) {
			return;
		}

		$db = defined( 'DB_NAME' ) ? (string) DB_NAME : '';
		if ( '' === $db ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		$sql          = "SELECT TABLE_NAME AS tname, TABLE_ROWS AS trows, DATA_LENGTH AS dlen, INDEX_LENGTH AS ilen, DATA_FREE AS dfree
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({$placeholders})";
		$args         = array_merge( array( $db ), $names );
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from count.
		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$tname = isset( $row['tname'] ) ? (string) $row['tname'] : '';
			if ( ! isset( $map[ $tname ] ) ) {
				continue;
			}
			$this->tables[ $map[ $tname ] ] = array(
				'rows'  => (int) ( $row['trows'] ?? 0 ),
				'data'  => (int) ( $row['dlen'] ?? 0 ),
				'index' => (int) ( $row['ilen'] ?? 0 ),
				'free'  => (int) ( $row['dfree'] ?? 0 ),
			);
		}
	}

	/**
	 * @return bool
	 */
	private function expired() {
		return ( microtime( true ) - $this->started ) >= self::TIMEOUT_SEC;
	}

	const CACHE_OPTION = 'zaprocket_db_scan_cache';
	const CACHE_TTL    = 604800; // 7 days.

	/**
	 * Cached preview payload, or null when missing / expired.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_cache() {
		$raw = get_option( self::CACHE_OPTION, null );
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$ts = isset( $raw['ts'] ) ? (int) $raw['ts'] : 0;
		if ( $ts < 1 ) {
			return null;
		}
		if ( ( time() - $ts ) > self::CACHE_TTL ) {
			self::clear_cache();
			return null;
		}
		$raw['from_cache'] = true;
		$when              = isset( $raw['scanned_at'] ) ? (string) $raw['scanned_at'] : '';
		$raw['size_note']  = self::cached_note( $when );
		return $raw;
	}

	/**
	 * @param array<string,mixed> $payload Preview payload.
	 * @return bool
	 */
	public static function save_cache( array $payload ) {
		$ts   = time();
		$when = function_exists( 'wp_date' )
			? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts )
			: (string) date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
		$store = array(
			'ts'          => (int) $ts,
			'scanned_at'  => $when,
			'items'       => isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array(),
			'total_count' => isset( $payload['total_count'] ) ? (int) $payload['total_count'] : 0,
			'total_bytes' => isset( $payload['total_bytes'] ) ? (int) $payload['total_bytes'] : 0,
			'size_label'  => isset( $payload['size_label'] ) ? (string) $payload['size_label'] : self::format_bytes( 0 ),
			'timed_out'   => false,
			'from_cache'  => true,
		);
		$store['size_note'] = self::cached_note( $when );
		return (bool) update_option( self::CACHE_OPTION, $store, false );
	}

	/**
	 * @return bool
	 */
	public static function clear_cache() {
		return (bool) delete_option( self::CACHE_OPTION );
	}

	/**
	 * @param string $when Formatted datetime.
	 * @return string
	 */
	public static function cached_note( $when ) {
		$when = (string) $when;
		if ( '' === $when ) {
			return pili__( '数据为上次扫描结果，不是实时数据。缓存超过 7 天会失效。数据不会自动刷新，如需最新预估，请手动点击重新扫描。预估体积仅作为参考，实际释放空间以清理完成后为准。' );
		}
		return sprintf(
			/* translators: %s: last scan datetime */
			pili__( '数据为上次扫描结果（%s），不是实时数据。缓存超过 7 天会失效。数据不会自动刷新，如需最新预估，请手动点击重新扫描。预估体积仅作为参考，实际释放空间以清理完成后为准。' ),
			$when
		);
	}

	/**
	 * Empty-state copy for the toolbar.
	 *
	 * @return string
	 */
	public static function empty_note() {
		return pili__( '暂无扫描记录，请点击「重新扫描」计算预估数据。预估体积仅作为参考，实际释放空间以清理完成后为准。' );
	}
}
