<?php
/**
 * WP 瘦身 · 访客接口 (zr_slim_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'id'      => 'zr_slim_rest_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( 'REST API 给区块编辑器和第三方用。本项默认关。打开后只拦未登录访客访问 /wp-json/，已登录后台写文章不受影响。不会关掉整个 REST，站点不会因此白屏。前台表单若要用接口，把路径写进排除。' ) ) . '</p>',
		'variant' => 'caution',
	),
	array(
		'id'      => 'zr_slim_disable_rest_guests',
		'type'    => 'switch',
		'title'   => pili__( '禁用访客 REST（没登录不能调接口）' ),
		'desc'    => pili__( '启用后匿名请求 /wp-json/ 将被拒绝。联系表单等请加入排除路由。不会全局关闭 REST。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_rest_exclude',
		'type'    => 'textarea',
		'title'   => pili__( 'REST 排除（这些接口仍然放行）' ),
		'desc'    => pili__( '每行一个命名空间或路径片段，例如 oembed、contact-form。' ),
		'default' => "oembed\ncontact-form",
	),
);
