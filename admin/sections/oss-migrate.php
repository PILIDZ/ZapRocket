<?php
/**
 * 对象存储 · 一键迁移（存量媒体，zr_oss_mig）。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

$types = array();
$cron  = pili__( '注意：当前只用 WordPress 定时任务。站点没有访客访问时，迁移会暂停，有人打开网站后才会继续。' );
if ( class_exists( 'ZapRocket_Oss_Migrate', false ) ) {
	$types = ZapRocket_Oss_Migrate::type_options();
	$cron  = ZapRocket_Oss_Migrate::cron_notice_text();
}

return array(
	array(
		'id'       => 'zr_oss_mig_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( pili__( '这是高风险功能：会把历史媒体上传到对象存储，并改写所选内容类型正文里的图片地址。关闭本页不会中止后台队列。回滚只还原已备份的正文链接，不会删除云上文件，也不会清除附件的云标记。默认不删除本地文件。' ) ) . '</p>',
		'variant'  => 'caution',
	),
	array(
		'id'       => 'zr_oss_mig_cron',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( $cron ) . '</p>',
		'variant'  => 'info',
	),
	array(
		'id'             => 'zr_oss_mig_checks',
		'type'           => 'table',
		'title'          => pili__( '启动前预检' ),
		'desc'           => pili__( '预检不通过时不能启动。连通性会在点启动时再测一次。' ),
		'sanitize'       => $blank,
		'data_callback'  => 'zaprocket_oss_mig_check_rows',
		'lazy_load'      => true,
		'page_size'      => 10,
		'selectable'     => false,
		'toolbar_export' => false,
		'toolbar_search' => false,
		'row_key'        => 'id',
		'empty_text'     => pili__( '暂无预检结果。' ),
		'columns'        => array(
			array(
				'id'       => 'item',
				'title'    => pili__( '检查项' ),
				'truncate' => false,
			),
			array(
				'id'         => 'result',
				'title'      => pili__( '结果' ),
				'allow_html' => true,
				'truncate'   => false,
			),
		),
	),
	array(
		'id'              => 'zr_oss_mig_types',
		'type'            => 'select',
		'title'           => pili__( '要改写正文的内容类型' ),
		'desc'            => pili__( '默认文章和页面。可多选；自定义类型请在下拉里勾上。至少选一项才能启动。' ),
		'options'         => $types,
		'default'         => array( 'post', 'page' ),
		'multiple'        => true,
		'searchable'      => true,
		'clearable'       => true,
		'select_all'      => true,
		'show_count'      => true,
		'close_on_select' => false,
		'placeholder'     => pili__( '选择要改写的内容类型' ),
	),
	array(
		'id'      => 'zr_oss_mig_ack',
		'type'    => 'switch',
		'title'   => pili__( '我已备份数据库，并理解可能造成短时图文不一致' ),
		'desc'    => pili__( '不勾选不能启动。此开关不会当作长期配置保存。' ),
		'default' => false,
		'sanitize' => static function () {
			return false;
		},
	),
	array(
		'id'       => 'zr_oss_mig_actions',
		'type'     => 'content',
		'title'    => pili__( '任务控制' ),
		'desc'     => pili__( '启动后关闭本页不会中止队列。终止不会自动回滚，也不会删除云上文件。' ),
		'sanitize' => $blank,
		'callback' => 'zaprocket_oss_mig_actions_html',
	),
	array(
		'id'             => 'zr_oss_mig_stats',
		'type'           => 'stat_cards',
		'sanitize'       => $blank,
		'layout'         => 'grid',
		'columns'        => array(
			'sm' => 2,
			'md' => 4,
			'lg' => 4,
		),
		'cards_callback' => 'zaprocket_oss_mig_stat_cards',
	),
	array(
		'id'              => 'zr_oss_mig_progress',
		'type'            => 'progress',
		'title'           => pili__( '迁移进度' ),
		'desc'            => pili__( '扫描按媒体库总量估算；上传与替换按已完成条目计算，替换未完成时不会到 100%。' ),
		'sanitize'        => $blank,
		'default'         => 0,
		'min'             => 0,
		'max'             => 100,
		'step'            => 1,
		'unit'            => '%',
		'show_percentage' => true,
		'show_input'      => false,
		'show_thumb'      => false,
		'click_track'     => false,
		'show_min_max'    => false,
		'keyboard'        => false,
		'color'           => 'blue',
	),
	array(
		'id'             => 'zr_oss_mig_fails',
		'type'           => 'table',
		'title'          => pili__( '失败条目（最多列出最近 50 条）' ),
		'before'         => '<p class="mt-0 mb-2 text-sm text-gray-600" data-zr-oss-fail-cap="1">' . esc_html( pili__( '失败表最多列出最近 50 条。超过后更早的失败不会出现在本表翻页里，请到下方迁移日志查看。重试只作用于本表列出的条目。' ) ) . '</p>',
		'sanitize'       => $blank,
		'data_callback'  => 'zaprocket_oss_mig_fail_rows',
		'lazy_load'      => true,
		'page_size'      => 10,
		'selectable'     => false,
		'toolbar_export' => false,
		'row_key'        => 'id',
		'empty_text'     => pili__( '暂无失败条目。超过 50 条时更早的失败不会出现在本表翻页里，请到下方迁移日志查看。重试只作用于本表列出的条目。' ),
		'columns'        => array(
			array(
				'id'    => 'file',
				'title' => pili__( '文件' ),
			),
			array(
				'id'       => 'reason',
				'title'    => pili__( '原因' ),
				'truncate' => 48,
			),
			array(
				'id'         => 'actions',
				'title'      => pili__( '操作' ),
				'allow_html' => true,
				'truncate'   => false,
			),
		),
	),
	array(
		'id'              => 'zr_oss_mig_logs',
		'type'            => 'log_viewer',
		'title'           => pili__( '迁移日志（轮询约 120 条，首屏最多约 200 条）' ),
		'before'          => '<p class="mt-0 mb-2 text-sm text-gray-600" data-zr-oss-log-cap="1">' . esc_html( pili__( '本页轮询大约展示最近 120 条迁移日志，首屏最多约 200 条；更早的记录不会出现在翻页里。' ) ) . '</p>',
		'sanitize'        => $blank,
		'data_callback'   => 'zaprocket_oss_mig_log_entries',
		'height'          => 320,
		'page_size'       => 80,
		'theme'           => 'console',
		'toolbar_clear'   => false,
		'toolbar_export'  => false,
		'toolbar_channel' => false,
		'empty_text'      => pili__( '暂无迁移日志。' ),
		'channels'        => array(
			'oss_migrate' => pili__( '一键迁移' ),
		),
	),
	array(
		'id'          => 'zr_oss_mig_rollback_modal',
		'type'        => 'modal',
		'title'       => '',
		'sanitize'    => $blank,
		'modal_title' => pili__( '回滚正文链接' ),
		'size'        => 'md',
			'content'     => '<p data-zr-oss-mig-rollback-modal="1">' . esc_html( pili__( '当前没有可恢复的正文备份（图片仅前台动态替换，文章原始内容未修改）。回滚不会删除云上文件，也不会清除附件云标记。' ) ) . '</p>',
		'buttons'     => array(
			array(
				'id'      => 'cancel',
				'label'   => pili__( '取消' ),
				'variant' => 'secondary',
				'close'   => true,
			),
			array(
				'id'      => 'confirm',
				'label'   => pili__( '确认回滚' ),
				'variant' => 'danger',
			),
		),
	),
	array(
		'id'       => 'zr_oss_mig_toast',
		'type'     => 'toast',
		'title'    => '',
		'sanitize' => $blank,
		'position' => 'bottom-center',
		'mount'    => true,
	),
);
