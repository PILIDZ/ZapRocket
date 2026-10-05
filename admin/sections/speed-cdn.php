<?php
/**
 * 速度优化 · CDN 加速 (zr_speed_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cdn_on = array( 'zr_speed_cdn_enable', '==', '1' );

$sanitize_host = static function ( $value ) {
	$value = untrailingslashit( trim( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( preg_match( '/^\s*javascript:/i', $value ) ) {
		return '';
	}
	if ( ! preg_match( '#^(https?:)?//#i', $value ) ) {
		$value = 'https://' . ltrim( $value, '/' );
	}
	$clean = esc_url_raw( $value );
	return is_string( $clean ) ? untrailingslashit( $clean ) : '';
};

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

$sanitize_dirs = static function ( $value ) {
	$allow = array( 'uploads', 'themes', 'plugins', 'includes' );
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $item ) {
		$key = sanitize_key( (string) $item );
		if ( in_array( $key, $allow, true ) ) {
			$out[] = $key;
		}
	}
	return array_values( array_unique( $out ) );
};

$cdn_intro   = '<p>' . esc_html( pili__( '将本站静态资源 URL 改写到 CDN 主机。须填写独立 CDN URL；Cloudflare 整站代理且无单独加速域名时不要启用。PHP 页面地址不会改写。' ) ) . '</p>';
$cdn_variant = 'info';

return array(
	array(
		'id'      => 'zr_speed_cdn_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => $cdn_intro,
		'variant' => $cdn_variant,
	),
	array(
		'id'      => 'zr_speed_cdn_enable',
		'type'    => 'switch',
		'title'   => pili__( '启用 CDN（静态文件换加速域名）' ),
		'desc'    => pili__( '将匹配目录下的静态资源 URL 替换为 CDN 主机。CDN URL 为空时不替换。' ),
		'default' => false,
	),
	array(
		'id'          => 'zr_speed_cdn_host',
		'type'        => 'text',
		'title'       => pili__( 'CDN 地址（加速域名）' ),
		'desc'        => pili__( '必填。写成完整地址，末尾不要斜杠，例如 https://cdn.example.com。也可以只填域名，保存时会补上 https://。' ),
		'default'     => '',
		'placeholder' => 'https://cdn.example.com',
		'sanitize'    => $sanitize_host,
		'dependency'  => $cdn_on,
	),
	array(
		'id'         => 'zr_speed_cdn_dirs',
		'type'       => 'checkbox',
		'title'      => pili__( '包含目录（哪些文件夹走加速）' ),
		'desc'       => pili__( '仅替换勾选目录中的静态资源。wp-includes 含核心脚本，谨慎启用。' ),
		'options'    => array(
			'uploads'  => 'wp-content/uploads',
			'themes'   => 'wp-content/themes',
			'plugins'  => 'wp-content/plugins',
			'includes' => 'wp-includes',
		),
		'default'    => array( 'uploads', 'themes', 'plugins' ),
		'sanitize'   => $sanitize_dirs,
		'dependency' => $cdn_on,
	),
	array(
		'id'          => 'zr_speed_cdn_include_extra',
		'type'        => 'textarea',
		'title'       => pili__( '额外路径（还要包含这些）' ),
		'desc'        => pili__( '每行一个相对站点根目录的路径，例如 wp-content/mu-plugins。' ),
		'default'     => '',
		'sanitize'    => $sanitize_lines,
		'dependency'  => $cdn_on,
	),
	array(
		'id'          => 'zr_speed_cdn_exclude',
		'type'        => 'textarea',
		'title'       => pili__( 'CDN 排除（这些不换地址）' ),
		'desc'        => pili__( 'URL 包含所列片段则不替换。每行一项，例如 .php、preview=。' ),
		'default'     => ".php",
		'sanitize'    => $sanitize_lines,
		'dependency'  => $cdn_on,
	),
);
