<?php
/**
 * WP 瘦身 · 评论头像 (zr_slim_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'id'      => 'zr_slim_fonts_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '评论头像来源。' ) ) . '</p>',
		'variant' => 'info',
	),
	array(
		'id'      => 'zr_slim_gravatar_mode',
		'type'    => 'select',
		'title'   => pili__( 'Gravatar（评论头像来源）' ),
		'desc'    => pili__( '默认走国外头像。本地默认图用站点自己的图；禁用就不显示头像；镜像走 Cravatar。' ),
		'options' => array(
			'default' => pili__( 'WordPress 默认' ),
			'local'   => pili__( '用本地默认图' ),
			'disable' => pili__( '不显示头像' ),
			'mirror'  => pili__( '走 Cravatar 镜像' ),
		),
		'default' => 'default',
	),
	array(
		'id'      => 'zr_slim_disable_gfonts',
		'type'    => 'switch',
		'title'   => pili__( '拦截谷歌字体（前台不加载外链字体）' ),
		'desc'    => pili__( '挡住 fonts.googleapis.com / fonts.gstatic.com。若同时打开「本地托管 Google 字体」，会先下载再替换，拦不住已换成本站的文件。' ),
		'default' => false,
	),
);
