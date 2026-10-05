<?php
/**
 * Safe query helpers (prepare + identifier whitelist + paged select).
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query helpers.
 */
final class PILI_Db_Query {

	/**
	 * Whitelist column list for SELECT (no *).
	 *
	 * @param string[] $columns Columns.
	 * @return string Comma list or empty.
	 */
	public static function columns_sql( array $columns ) {
		$parts = array();
		foreach ( $columns as $c ) {
			$c = PILI_Db_Schema::sanitize_ident( (string) $c );
			if ( '' !== $c ) {
				$parts[] = '`' . $c . '`';
			}
		}
		return implode( ', ', $parts );
	}

	/**
	 * Paged SELECT on a registered table.
	 *
	 * @param string               $slug    Table slug.
	 * @param array<string,mixed>  $args    columns, where_sql (already prepared fragments?), where_values, orderby, order, page, per_page.
	 * @return array{rows:array,total:int,page:int,per_page:int}|array{error:string}
	 */
	public static function paged_select( $slug, array $args = array() ) {
		global $wpdb;
		$table = PILI_Db_Schema::table_name( $slug );
		if ( '' === $table ) {
			return array( 'error' => 'unknown_table' );
		}
		$cols = isset( $args['columns'] ) && is_array( $args['columns'] ) ? $args['columns'] : array( 'id' );
		$col_sql = self::columns_sql( $cols );
		if ( '' === $col_sql ) {
			return array( 'error' => 'no_columns' );
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$orderby = PILI_Db_Schema::sanitize_ident( (string) ( $args['orderby'] ?? 'id' ) );
		$order   = strtoupper( (string) ( $args['order'] ?? 'DESC' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		if ( '' === $orderby ) {
			$orderby = 'id';
		}

		$where_sql = '';
		$values    = array();
		if ( ! empty( $args['where_sql'] ) && is_string( $args['where_sql'] ) ) {
			$where_sql = ' WHERE ' . $args['where_sql'];
			if ( isset( $args['where_values'] ) && is_array( $args['where_values'] ) ) {
				$values = $args['where_values'];
			}
		}

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array(
				'rows'     => array(),
				'total'    => 0,
				'page'     => $page,
				'per_page' => $per_page,
			);
		}

		$count_sql = 'SELECT COUNT(*) FROM `' . str_replace( '`', '', $table ) . '`' . $where_sql;
		if ( $values && method_exists( $wpdb, 'prepare' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, $values );
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = 'SELECT ' . $col_sql . ' FROM `' . str_replace( '`', '', $table ) . '`' . $where_sql
			. ' ORDER BY `' . $orderby . '` ' . $order . ' LIMIT %d OFFSET %d';
		$list_values = array_merge( $values, array( $per_page, $offset ) );
		if ( method_exists( $wpdb, 'prepare' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$list_sql = $wpdb->prepare( $list_sql, $list_values );
		}
		$rows = $wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
}
