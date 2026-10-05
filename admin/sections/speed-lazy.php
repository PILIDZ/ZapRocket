<?php
/**
 * 速度优化 · 媒体。对齐 WP Rocket Media：懒加载、图片尺寸、字体。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sanitize_lines = static function ( $value ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
	$out  = array();
	foreach ( explode( "\n", $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || preg_match( '/^\s*javascript:/i', $line ) ) {
			continue;
		}
		$out[] = sanitize_text_field( $line );
	}
	return implode( "\n", array_values( array_unique( $out ) ) );
};

return array(
	array(
		'id'      => 'zr_speed_lazy_images',
		'type'    => 'switch',
		'title'   => pili__( '图片懒加载（滚到再下载）' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_speed_lazy_css_bg',
		'type'    => 'switch',
		'title'   => pili__( '背景图懒加载（CSS 背景也推迟）' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_speed_lazy_iframes',
		'type'    => 'switch',
		'title'   => pili__( 'iframe 懒加载（视频框滚到再加载）' ),
		'default' => false,
	),
	array(
		'id'         => 'zr_speed_lazy_youtube',
		'type'       => 'switch',
		'title'      => pili__( 'YouTube 预览图（先出图再播）' ),
		'desc'       => pili__( '页面里 YouTube 视频较多时，能明显加快加载。' ),
		'default'    => false,
		'dependency' => array( 'zr_speed_lazy_iframes', '==', '1' ),
	),
	array(
		'id'          => 'zr_speed_lazy_exclude',
		'type'        => 'textarea',
		'title'       => pili__( '懒加载排除（这些不推迟）' ),
		'desc'        => pili__( '每行一个关键词（文件名、class、域名）。' ),
		'default'     => '',
		'placeholder' => "example-image.jpg\nslider-image",
		'sanitize'    => $sanitize_lines,
	),
	array(
		'id'      => 'zr_speed_lazy_dimensions',
		'type'    => 'switch',
		'title'   => pili__( '补全图片尺寸（减少布局跳动）' ),
		'desc'    => pili__( '给缺少宽高的图片补上 width / height。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_speed_font_preload',
		'type'    => 'switch',
		'title'   => pili__( '预加载字体（字更早点出来）' ),
		'desc'    => pili__( '预载首屏字体，减轻文字跳动。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_speed_font_local',
		'type'    => 'switch',
		'title'   => pili__( '本地托管 Google 字体（字体放本站）' ),
		'desc'    => pili__( '把 fonts.googleapis.com 的样式和 woff2 下到 uploads/zaprocket/fonts/。第一次打开该字体可能稍慢。' ),
		'default' => false,
	),
);
