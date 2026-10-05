<?php
/**
 * Table SQL true-paging helpers (host templates).
 *
 * Contract for `data_callback` when `server_paged => true`:
 *   return array( 'rows' => array<...>, 'total' => int )
 * Query args typically include page / per_page / search / orderby / order.
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize server_paged callback result.
 *
 * @param mixed                    $result Callback return.
 * @param array<string,mixed>|null $query  Query.
 * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
 */
function pili_table_normalize_paged_result( $result, $query = null ) {
	$page     = 1;
	$per_page = 20;
	if ( is_array( $query ) ) {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $query['per_page'] ?? 20 ) ) );
	}

	if ( is_array( $result ) && isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
		$total = isset( $result['total'] ) ? (int) $result['total'] : count( $result['rows'] );
		return array(
			'rows'     => array_values( $result['rows'] ),
			'total'    => max( 0, $total ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	if ( is_array( $result ) ) {
		// Flat list without total → not true paging; expose count as total for debug.
		$rows = array_values( $result );
		return array(
			'rows'     => $rows,
			'total'    => count( $rows ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	return array(
		'rows'     => array(),
		'total'    => 0,
		'page'     => $page,
		'per_page' => $per_page,
	);
}

/**
 * Example in-memory true page (for Demo / probes). Prefer real SQL in production.
 *
 * @param array<int,array<string,mixed>> $all_rows Full dataset (Demo only; prod use SQL).
 * @param array<string,mixed>|null       $query    page/per_page/search.
 * @return array{rows:array,total:int,page:int,per_page:int}
 */
function pili_table_slice_true_page( array $all_rows, $query = null ) {
	$page     = 1;
	$per_page = 20;
	$search   = '';
	if ( is_array( $query ) ) {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $query['per_page'] ?? 20 ) ) );
		$search   = isset( $query['search'] ) ? (string) $query['search'] : '';
	}

	$filtered = $all_rows;
	if ( '' !== $search ) {
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );
		$filtered = array();
		foreach ( $all_rows as $row ) {
			$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( wp_json_encode( $row ) ) : strtolower( wp_json_encode( $row ) );
			if ( false !== strpos( (string) $hay, $needle ) ) {
				$filtered[] = $row;
			}
		}
	}

	$total  = count( $filtered );
	$offset = ( $page - 1 ) * $per_page;
	$rows   = array_slice( $filtered, $offset, $per_page );

	return array(
		'rows'     => $rows,
		'total'    => $total,
		'page'     => $page,
		'per_page' => $per_page,
	);
}

/**
 * SQL true-paging template using $wpdb (host must pass safe identifiers).
 *
 * Forbidden patterns: SELECT * of LONGTEXT without need; LIMIT N then PHP slice as "paging".
 *
 * @param string               $table          Table name (already prefixed or escaped by caller).
 * @param array<string,mixed>  $args {
 *   @type string $select   Column list (default '*" — prefer explicit cols).
 *   @type string $where_sql WHERE clause without leading WHERE (use placeholders).
 *   @type array  $where_values Values for $wpdb->prepare on WHERE.
 *   @type string $order_sql ORDER BY clause without ORDER BY.
 *   @type int    $page
 *   @type int    $per_page
 * }
 * @return array{rows:array,total:int,page:int,per_page:int}|WP_Error
 */
function pili_table_sql_paged( $table, array $args = array() ) {
	global $wpdb;
	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
		return new WP_Error( 'pili_table_no_wpdb', 'wpdb unavailable' );
	}

	$table = (string) $table;
	if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
		return new WP_Error( 'pili_table_bad_name', 'invalid table' );
	}

	$select = isset( $args['select'] ) ? (string) $args['select'] : '*';
	// Soft guard: discourage SELECT * in comments for hosts; still allow for small tables.
	$where_sql    = isset( $args['where_sql'] ) ? trim( (string) $args['where_sql'] ) : '';
	$where_values = isset( $args['where_values'] ) && is_array( $args['where_values'] ) ? $args['where_values'] : array();
	$order_sql    = isset( $args['order_sql'] ) ? trim( (string) $args['order_sql'] ) : 'id DESC';
	$page         = max( 1, (int) ( $args['page'] ?? 1 ) );
	$per_page     = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
	$offset       = ( $page - 1 ) * $per_page;

	$where_clause = ( '' !== $where_sql ) ? ( ' WHERE ' . $where_sql ) : '';

	$count_sql = "SELECT COUNT(*) FROM {$table}{$where_clause}";
	if ( ! empty( $where_values ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- host-supplied WHERE with prepare values.
		$count_sql = $wpdb->prepare( $count_sql, $where_values );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$total = (int) $wpdb->get_var( $count_sql );

	$list_sql = "SELECT {$select} FROM {$table}{$where_clause} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
	$prepare_values = $where_values;
	$prepare_values[] = $per_page;
	$prepare_values[] = $offset;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$list_sql = $wpdb->prepare( $list_sql, $prepare_values );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $list_sql, ARRAY_A );
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	return array(
		'rows'     => $rows,
		'total'    => max( 0, $total ),
		'page'     => $page,
		'per_page' => $per_page,
	);
}
