<?php
/**
 * Section: 概览 / general domain (zr_gen_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

$fields = array(
	array(
		'id'      => 'zr_gen_overview_note',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '闪电WP性能：站点瘦身、速度优化、对象存储、数据库清理。总开关激活后基础瘦身可用。Delay JS、压缩、整页缓存默认关，要管理员自己打开。卸载默认不删配置和缓存。整页缓存在主题查询之后才读文件，第一次访问仍会完整跑 WordPress。' ) ) . '</p>',
		'variant' => 'info',
	),
	array(
		'id'             => 'zr_gen_overview_stats',
		'type'           => 'stat_cards',
		'sanitize'       => $blank,
		'layout'         => 'grid',
		'columns'        => array(
			'sm' => 2,
			'md' => 4,
			'lg' => 4,
		),
		'cards_callback' => 'zaprocket_overview_stat_cards',
	),
	array(
		'id'      => 'zr_gen_enabled',
		'type'    => 'switch',
		'title'   => pili__( '启用优化（关掉后前台暂停）' ),
		'desc'    => pili__( '关闭后瘦身、前台优化全部停掉（含心跳/修订/自动保存），站点跟没装本插件一样。本设置页仍可访问。' ),
		'default' => true,
	),
	array(
		'id'      => 'zr_gen_uninstall_cleanup',
		'type'    => 'switch',
		'title'   => pili__( '卸载时删除数据（卸插件一并清空）' ),
		'desc'    => pili__( '卸载时删除 option、运行日志表、uploads/zaprocket 与 wp-content/cache/zaprocket。默认关闭。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_gen_recommend_note',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '推荐配置只打开低风险瘦身：禁用 Emoji、禁止自己给自己留言（Self-Ping）、去掉 RSD / WLW 和版本号。不会改 XML-RPC、评论、访客 REST。' ) ) . '</p>',
		'variant' => 'caution',
	),
	array(
		'id'       => 'zr_gen_recommend',
		'type'     => 'content',
		'title'    => pili__( '套用低风险推荐' ),
		'sanitize' => $blank,
		'callback' => 'zaprocket_recommend_button_html',
	),
);

if ( class_exists( 'ZapRocket_Module_General', false ) ) {
	$hits = ZapRocket_Module_General::active_overlaps();
	if ( array() !== $hits ) {
		$names = array();
		foreach ( $hits as $row ) {
			$names[] = $row['name'];
		}
		$fields[] = array(
			'id'      => 'zr_gen_conflict_note',
			'type'    => 'content',
			'title'   => '',
			'content' => '<p>' . esc_html(
				sprintf(
					/* translators: %s: plugin names */
					pili__( '检测到其它性能插件同时启用：%s。重复改同一处资源时，页面可能更慢或行为对不上。建议只留一套。' ),
					implode( '、', $names )
				)
			) . '</p>',
			'variant' => 'alert',
		);
	}
}

return $fields;
