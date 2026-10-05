<?php
/**
 * 速度优化 · 文件优化。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$css_minify_on = array( 'zr_speed_css_minify', '==', '1' );
$js_minify_on  = array( 'zr_speed_js_minify', '==', '1' );

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

$blank = static function () {
	return '';
};

return array(
	array(
		'id'       => 'zr_speed_file_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( pili__( '压缩、合并、defer、延迟加载都会改前台，默认全关，需管理员手动打开。开了「JS 延迟加载」时，「合并 JS」不生效。菜单、支付、轮播出问题就关掉对应项，或写进排除。' ) ) . '</p>',
		'variant'  => 'caution',
	),
	array(
		'id'       => 'zr_speed_css_note',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p><strong>' . esc_html( pili__( 'CSS 文件' ) ) . '</strong></p>',
		'variant'  => 'info',
	),
	array(
		'id'      => 'zr_speed_css_minify',
		'type'    => 'switch',
		'title'   => pili__( 'CSS 压缩（减小 CSS 体积）' ),
		'desc'    => pili__( '去掉空格和注释，文件更小、下载更快。' ),
		'default' => false,
	),
	array(
		'id'          => 'zr_speed_css_exclude',
		'type'        => 'textarea',
		'title'       => pili__( 'CSS 排除（这些文件不压缩）' ),
		'desc'        => pili__( '每行一个地址或路径。写成 /wp-content/plugins/某插件/(.*).css 可整目录跳过。' ),
		'default'     => '',
		'placeholder' => '/wp-content/plugins/some-plugin/(.*).css',
		'sanitize'    => $sanitize_lines,
		'dependency'  => $css_minify_on,
	),
	array(
		'id'       => 'zr_speed_js_note',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p><strong>' . esc_html( pili__( 'JavaScript 文件' ) ) . '</strong></p>',
		'variant'  => 'info',
	),
	array(
		'id'      => 'zr_speed_js_minify',
		'type'    => 'switch',
		'title'   => pili__( 'JS 压缩（减小 JS 体积）' ),
		'desc'    => pili__( '去掉空格和注释，脚本更小。' ),
		'default' => false,
	),
	array(
		'id'          => 'zr_speed_js_exclude',
		'type'        => 'textarea',
		'title'       => pili__( 'JS 排除（这些文件不压缩、不合并）' ),
		'desc'       => pili__( '每行一个地址或路径。支付、验证码、客服脚本建议写在这里。压缩、defer、延迟加载都会跳过这些文件。' ),
		'default'     => '',
		'placeholder' => '/wp-content/themes/some-theme/(.*).js',
		'sanitize'    => $sanitize_lines,
	),
	array(
		'id'         => 'zr_speed_js_combine',
		'type'       => 'switch',
		'title'      => pili__( '合并 JS（减少请求次数）' ),
		'desc'       => pili__( '把多个脚本合成一个。要先开「JS 压缩」。网站已是 HTTP/2 时一般不必开。开了「JS 延迟加载」后本项无效。' ),
		'default'    => false,
		'dependency' => $js_minify_on,
	),
	array(
		'id'      => 'zr_speed_js_defer',
		'type'    => 'switch',
		'title'   => pili__( 'JS 异步加载（页面先出来再跑脚本）' ),
		'desc'    => pili__( '给脚本加上 defer，不挡页面排版。菜单、支付异常时关掉，或写进排除。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_speed_js_delay',
		'type'    => 'switch',
		'title'   => pili__( 'JS 延迟加载（点一下或滚动后再跑）' ),
		'desc'    => pili__( '访客动手之前先不执行脚本，提速更明显，也更容易弄坏菜单、轮播。开启后「合并 JS」不生效。' ),
		'default' => false,
	),
);
