<?php
/**
 * 对象存储 · 快捷操作（链接替换 + 失败记录，zr_oss_tools）。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

$replace_urls = array(
	'old' => '',
	'new' => '',
);
if ( class_exists( 'ZapRocket_Oss_Admin', false ) ) {
	$replace_urls = ZapRocket_Oss_Admin::replace_url_defaults();
}

return array(
	array(
		'id'       => 'zr_oss_tools_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( pili__( '这里做不改存储配置的快捷操作。数据库原链接替换只改文章正文和自定义字段里已经写死的地址，不会上传文件。失败记录是上传没成功的附件，可重试或忽略。首次替换请先备份数据库。密钥、桶和域名请到「存储设置」里改。' ) ) . '</p>',
		'variant'  => 'caution',
	),
	array(
		'id'          => 'zr_oss_replace_old',
		'type'        => 'text',
		'title'       => pili__( '要替换的旧地址' ),
		'desc'        => pili__( '一般是本站媒体库前缀。只替换文章正文和自定义字段里完全匹配的字符串。例如 https://www.example.com/wp-content/uploads。' ),
		'default'     => $replace_urls['old'],
		'placeholder' => 'https://www.example.com/wp-content/uploads',
		'sanitize'    => $blank,
		'attributes'  => array(
			'type'         => 'url',
			'autocomplete' => 'off',
		),
	),
	array(
		'id'          => 'zr_oss_replace_new',
		'type'        => 'text',
		'title'       => pili__( '替换成的新地址' ),
		'desc'        => pili__( '一般是对象存储自定义域名加桶内目录。例如 https://img.example.com/wp-content/uploads。不会上传文件。' ),
		'default'     => $replace_urls['new'],
		'placeholder' => 'https://img.example.com/wp-content/uploads',
		'sanitize'    => $blank,
		'attributes'  => array(
			'type'         => 'url',
			'autocomplete' => 'off',
		),
	),
	array(
		'id'       => 'zr_oss_replace',
		'type'     => 'content',
		'title'    => pili__( '开始替换（不保存当前填写）' ),
		'desc'     => pili__( '用上面两个地址做一次替换，不会写进存储设置。首次请先备份数据库。' ),
		'sanitize' => $blank,
		'callback' => 'zaprocket_oss_replace_form_html',
	),
	array(
		'id'             => 'zr_oss_logs',
		'type'           => 'table',
		'title'          => pili__( '失败记录（上传没成功的附件）' ),
		'desc'           => pili__( '按时间列出。「重试」再排一次上传，「忽略」只清这条记录和排队标记。' ),
		'sanitize'       => $blank,
		'data_callback'  => 'zaprocket_oss_log_table_rows',
		'lazy_load'      => false,
		'page_size'      => 10,
		'selectable'     => false,
		'toolbar_export' => false,
		'row_key'        => 'id',
		'empty_text'     => pili__( '暂无失败记录。' ),
		'columns'        => array(
			array(
				'id'       => 'time',
				'title'    => pili__( '时间' ),
				'truncate' => false,
			),
			array(
				'id'    => 'attachment',
				'title' => pili__( '附件' ),
			),
			array(
				'id'       => 'message',
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
		'id'       => 'zr_oss_tools_toast',
		'type'     => 'toast',
		'title'    => '',
		'sanitize' => $blank,
		'position' => 'bottom-center',
		'mount'    => true,
	),
);
