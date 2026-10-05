<?php
/**
 * 数据库 · 定时清理策略 (zr_db_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'id'      => 'zr_db_task_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '这里管「留多少、隔多久自动清」。真正动手删数据请去「垃圾清理」。未勾选整理数据表时，定时任务不会去整理表。' ) ) . '</p>',
		'variant' => 'caution',
	),
	array(
		'id'         => 'zr_db_keep_revisions',
		'type'       => 'slider',
		'title'      => pili__( '修订版本保留（每篇最多留几份）' ),
		'desc'       => pili__( '自动清理和手动清理时，每篇最多留几份。拉到 0 表示不按份数删修订（不限制）。若要清光历史稿，请勾选下面的「清理时删光修订」。' ),
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
		'sanitize'   => static function ( $value ) {
			$n = (int) $value;
			if ( $n < 0 ) {
				$n = 0;
			}
			if ( $n > 50 ) {
				$n = 50;
			}
			return $n;
		},
		// Keep N; 0 means skip count-based trim.
	),
	array(
		'id'      => 'zr_db_purge_all_revisions',
		'type'    => 'switch',
		'title'   => pili__( '清理时删光修订（历史稿一份不留）' ),
		'desc'    => pili__( '仅在「垃圾清理」或定时清理勾了修订项时生效。打开后会按「全删」处理，不再看上面的份数。默认关闭。' ),
		'default' => false,
	),
	array(
		'id'         => 'zr_db_autodraft_days',
		'type'       => 'slider',
		'title'      => pili__( '自动草稿保留（超过几天再删）' ),
		'desc'       => pili__( '编辑时 WordPress 会自动存临时稿。超过这里填的天数，清理时才会删。' ),
		'default'    => 7,
		'min'        => 1,
		'max'        => 90,
		'step'       => 1,
		'unit'       => pili__( '天' ),
		'suffix'     => pili__( '天' ),
		'show_input' => true,
		'show_ticks' => true,
		'tick_step'  => 15,
		'color'      => 'blue',
		'sanitize'   => static function ( $value ) {
			$n = (int) $value;
			if ( $n < 1 ) {
				$n = 1;
			}
			if ( $n > 90 ) {
				$n = 90;
			}
			return $n;
		},
	),
	array(
		'id'      => 'zr_db_schedule',
		'type'    => 'select',
		'title'   => pili__( '自动清理周期（多久跑一次）' ),
		'desc'    => pili__( '按「垃圾清理」里勾过的项目执行。' ),
		'options' => array(
			'off'    => pili__( '不自动跑' ),
			'daily'  => pili__( '每天' ),
			'weekly' => pili__( '每周' ),
		),
		'default' => 'off',
	),
	array(
		'id'      => 'zr_db_optimize_tables',
		'type'    => 'switch',
		'title'   => pili__( '整理数据表（顺带回收表空间）' ),
		'desc'    => pili__( '自动清理时是否顺便回收表空间。对常见 InnoDB 效果有限，大网站请在半夜再开。' ),
		'default' => false,
	),
);
