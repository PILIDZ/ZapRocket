<?php
/**
 * WP 瘦身 · 后台自动保存 (zr_slim_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'id'      => 'zr_slim_hb_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '写文章时 WordPress 会定时跟服务器打招呼：自动存草稿、刷新仪表盘。太勤了会占 CPU。写文章时不要把它彻底关掉，不然可能丢稿。' ) ) . '</p>',
		'variant' => 'info',
	),
	array(
		'id'      => 'zr_slim_heartbeat_mode',
		'type'    => 'select',
		'title'   => pili__( 'Heartbeat（后台定时刷新）' ),
		'desc'    => pili__( '写文章、仪表盘会定时跟服务器打招呼。太勤了占 CPU。写文章时不会一刀切关掉，避免丢稿。' ),
		'options' => array(
			'off'       => pili__( '不改，跟 WordPress 默认一样' ),
			'front'     => pili__( '网站前台不要定时刷新' ),
			'dashboard' => pili__( '仪表盘少刷新一点（写文章不受影响）' ),
		),
		'default' => 'off',
	),
	array(
		'id'         => 'zr_slim_revisions_keep',
		'type'       => 'slider',
		'title'      => pili__( '修订版本保留（每篇最多留几份）' ),
		'desc'       => pili__( '每次保存都会多一份历史。时间一长占空间。拉到 0 表示不限制份数、也不自动清光。默认 5。若要一篇都不留，请勾选下面的「修订全部不留」。' ),
		'default'    => 5,
		'min'        => 0,
		'max'        => 50,
		'step'       => 1,
		'unit'       => pili__( '份' ),
		'suffix'     => pili__( '份' ),
		'show_input' => true,
		'show_ticks' => true,
		'tick_step'  => 10,
		'color'      => 'blue',
	),
	array(
		'id'      => 'zr_slim_revisions_purge_all',
		'type'    => 'switch',
		'title'   => pili__( '修订全部不留（新保存不再留历史稿）' ),
		'desc'    => pili__( '打开后，新保存不再生成修订（相当于保留 0 份）。上面的份数滑块会被忽略。不会立刻批量删掉已经存在的历史稿，那是「垃圾清理」的事。默认关闭。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_autosave_interval',
		'type'    => 'select',
		'title'   => pili__( '自动保存间隔（写文章多久存一次）' ),
		'desc'    => pili__( '默认大约每 60 秒存一次草稿。存稀一点能少写数据库。' ),
		'options' => array(
			'0'   => pili__( '不改（大约 1 分钟）' ),
			'30'  => pili__( '30 秒' ),
			'60'  => pili__( '1 分钟' ),
			'120' => pili__( '2 分钟' ),
			'180' => pili__( '3 分钟' ),
		),
		'default' => '0',
	),
);
