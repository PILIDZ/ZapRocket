<?php
/**
 * 数据库 · 清理记录 (zr_db_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

return array(
	array(
		'id'       => 'zr_db_log_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( pili__( '扫描、手动清理、定时清理、套用推荐配置都会记在这里。关掉「概览」里的「优化日志」则不再写入。' ) ) . '</p>',
		'variant'  => 'info',
	),
	array(
		'id'             => 'zr_db_log_stats',
		'type'           => 'stat_cards',
		'sanitize'       => $blank,
		'layout'         => 'grid',
		'columns'        => array(
			'sm' => 2,
			'md' => 4,
			'lg' => 4,
		),
		'cards_callback' => 'zaprocket_opt_log_stat_cards',
	),
	array(
		'id'            => 'zr_db_log_table',
		'type'          => 'table',
		'title'         => pili__( '清理记录（扫描和清理留下的条目）' ),
		'desc'          => pili__( '按时间倒序。可搜索类型、状态或说明。导出只包含当前页。' ),
		'sanitize'      => $blank,
		'server_paged'  => true,
		'data_callback' => 'zaprocket_opt_log_table_rows',
		'page_size'     => 10,
		'selectable'    => false,
		'row_key'       => 'id',
		'empty_text'    => pili__( '暂无记录。请先在「垃圾清理」扫描或清理。' ),
		'columns'       => array(
			array(
				'id'       => 'created_at',
				'title'    => pili__( '时间' ),
				'truncate' => false,
			),
			array(
				'id'    => 'action_label',
				'title' => pili__( '类型' ),
			),
			array(
				'id'    => 'status_label',
				'title' => pili__( '状态' ),
			),
			array(
				'id'    => 'rows_affected',
				'title' => pili__( '处理条数' ),
			),
			array(
				'id'       => 'message',
				'title'    => pili__( '说明' ),
				'truncate' => 48,
			),
		),
	),
);
