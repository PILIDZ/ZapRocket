<?php
/**
 * 插件运行日志 (zr_gen_run_* + log_viewer).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

$nonce = class_exists( 'ZapRocket_Run_Log', false ) ? wp_create_nonce( ZapRocket_Run_Log::NONCE ) : '';

return array(
	array(
		'id'       => 'zr_gen_run_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( pili__( '这里记插件运行与错误，包括数据库扫描、手动清理和定时清理。对象存储「快捷操作」里的失败记录仍保留。前台缓存命中不会写入。' ) ) . '</p>',
		'variant'  => 'info',
	),
	array(
		'id'      => 'zr_gen_run_log_enabled',
		'type'    => 'switch',
		'title'   => pili__( '启用运行日志' ),
		'desc'    => pili__( '关掉后本页不再写入，已有记录仍可查看。' ),
		'default' => true,
	),
	array(
		'id'       => 'zr_gen_run_log_days',
		'type'     => 'number',
		'title'    => pili__( '保留天数' ),
		'desc'     => pili__( '过期记录由定时任务删除。默认 30 天。' ),
		'default'  => 30,
		'min'      => 1,
		'max'      => 365,
		'step'     => 1,
		'unit'     => pili__( '天' ),
		'sanitize' => static function ( $value ) {
			$n = (int) $value;
			if ( $n < 1 ) {
				$n = 30;
			}
			if ( $n > 365 ) {
				$n = 365;
			}
			return $n;
		},
	),
	array(
		'id'      => 'zr_gen_oss_test_log',
		'type'    => 'switch',
		'title'   => pili__( '记录对象存储连通测试失败' ),
		'desc'    => pili__( '默认关闭，避免反复点「测试连接」刷屏。打开后，测试失败会写入本页（成功仍不记）。' ),
		'default' => false,
	),
	array(
		'id'              => 'zr_logs_viewer',
		'type'            => 'log_viewer',
		'title'           => pili__( '运行日志' ),
		'sanitize'        => $blank,
		'data_callback'   => 'zaprocket_run_log_viewer_entries',
		'page_size'       => 50,
		'height'          => 460,
		'theme'           => 'console',
		'row_key'         => 'id',
		'empty_text'      => pili__( '暂无运行日志。打开开关后，对象存储失败、数据库清理、清空缓存会出现在这里。' ),
		'refresh_action'  => class_exists( 'ZapRocket_Run_Log', false ) ? ZapRocket_Run_Log::AJAX_REFRESH : '',
		'clear_action'    => class_exists( 'ZapRocket_Run_Log', false ) ? ZapRocket_Run_Log::AJAX_CLEAR : '',
		'export_action'   => class_exists( 'ZapRocket_Run_Log', false ) ? ZapRocket_Run_Log::AJAX_EXPORT : '',
		'ajax_nonce'      => $nonce,
		'levels'          => array(
			'info'    => pili__( '信息' ),
			'warning' => pili__( '警告' ),
			'error'   => pili__( '错误' ),
		),
		'channels'        => array(
			'slim'    => pili__( '站点瘦身' ),
			'speed'   => pili__( '速度优化' ),
			'db'      => pili__( '数据库' ),
			'oss'     => pili__( '对象存储' ),
			'system'  => pili__( '系统' ),
			'migrate' => pili__( '迁移' ),
		),
	),
);
