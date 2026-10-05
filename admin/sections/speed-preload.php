<?php
/**
 * 速度优化 · 资源预载 (zr_speed_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

$sanitize_host = static function ( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw || preg_match( '/^\s*javascript:/i', $raw ) ) {
		return '';
	}
	if ( 0 === strpos( $raw, '//' ) ) {
		$raw = 'https:' . $raw;
	}
	if ( ! preg_match( '#^https?://#i', $raw ) ) {
		$raw = 'https://' . ltrim( $raw, '/' );
	}
	$host = wp_parse_url( $raw, PHP_URL_HOST );
	return is_string( $host ) ? $host : '';
};

$sanitize_dns = static function ( $value ) use ( $sanitize_host ) {
	if ( is_string( $value ) ) {
		$lines  = preg_split( '/\r\n|\r|\n/', $value );
		$value  = array();
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$host = $sanitize_host( $line );
				if ( '' !== $host ) {
					$value[] = array(
						'host' => $host,
						'mode' => 'preconnect',
					);
				}
			}
		}
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$host = $sanitize_host( isset( $row['host'] ) ? $row['host'] : '' );
		if ( '' === $host ) {
			continue;
		}
		$mode = isset( $row['mode'] ) ? sanitize_key( (string) $row['mode'] ) : 'preconnect';
		if ( ! in_array( $mode, array( 'prefetch', 'preconnect' ), true ) ) {
			$mode = 'preconnect';
		}
		$out[] = array(
			'host' => $host,
			'mode' => $mode,
		);
	}
	return $out;
};

$sanitize_assets = static function ( $value ) {
	$allow_as = array( 'image', 'style', 'font', 'script' );
	if ( is_string( $value ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$rows  = array();
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = explode( '|', $line, 2 );
				$url   = trim( $parts[0] );
				$as    = isset( $parts[1] ) ? sanitize_key( trim( $parts[1] ) ) : 'image';
				$rows[] = array(
					'url'   => $url,
					'as'    => $as,
					'cross' => false,
				);
			}
		}
		$value = $rows;
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$url = isset( $row['url'] ) ? untrailingslashit( trim( (string) $row['url'] ) ) : '';
		if ( '' === $url || preg_match( '/^\s*javascript:/i', $url ) ) {
			continue;
		}
		$clean = esc_url_raw( $url );
		if ( ! is_string( $clean ) || '' === $clean ) {
			continue;
		}
		$as = isset( $row['as'] ) ? sanitize_key( (string) $row['as'] ) : 'image';
		if ( ! in_array( $as, $allow_as, true ) ) {
			$as = 'image';
		}
		$out[] = array(
			'url'   => $clean,
			'as'    => $as,
			'cross' => ! empty( $row['cross'] ),
		);
	}
	return $out;
};

$sanitize_lcp = static function ( $value ) {
	$n = (int) $value;
	if ( $n < 0 ) {
		$n = 0;
	}
	if ( $n > 5 ) {
		$n = 5;
	}
	return $n;
};

return array(
	array(
		'id'       => 'zr_speed_preload_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p>' . esc_html( pili__( '提前打招呼，不是整站缓存预热。不懂可以只开下面两个开关、填一个数字，域名和文件列表留空也行。' ) ) . '</p>',
		'variant'  => 'info',
	),
	array(
		'id'      => 'zr_speed_preload_links',
		'type'    => 'switch',
		'title'   => pili__( '预载链接（鼠标悬停先下载那一页）' ),
		'desc'    => pili__( '访客还没点，只是鼠标在站内文章/栏目链接上停一下，浏览器会先偷偷下一点。点进去会感觉快。不用填任何地址。后台编辑时不会开。' ),
		'default' => false,
	),
	array(
		'id'       => 'zr_speed_preload_lcp_n',
		'type'     => 'number',
		'title'    => pili__( '预载首屏图（最上头几张图提前下）' ),
		'desc'     => pili__( '不用自己贴图片地址。填 1 或 2 即可：把当前页从上往下数的头几张图提前下载，减轻「字出来了图还在转」。填 0 表示不自动预载。最多 5。' ),
		'default'  => 0,
		'min'      => 0,
		'max'      => 5,
		'step'     => 1,
		'sanitize' => $sanitize_lcp,
	),
	array(
		'id'       => 'zr_speed_preload_dns_note',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p><strong>' . esc_html( pili__( '外站预连接' ) ) . '</strong> ' . esc_html( pili__( '用了 CDN、统计或外链字体才需要。没有外链就不用加。只填主机名，不要带 http。' ) ) . '</p>',
		'variant'  => 'info',
	),
	array(
		'id'            => 'zr_speed_preload_dns',
		'type'          => 'repeater',
		'title'         => pili__( '外站名单（提前打招呼）' ),
		'button_title'  => pili__( '添加一个外站' ),
		'preview_field' => 'host',
		'max'           => 20,
		'sanitize'      => $sanitize_dns,
		'default'       => array(),
		'fields'        => array(
			array(
				'id'          => 'host',
				'type'        => 'text',
				'title'       => pili__( '主机名（只填域名）' ),
				'desc'        => pili__( '只填域名，例如 cdn.example.com 或 fonts.gstatic.com，不要写成整段网址。' ),
				'placeholder' => 'cdn.example.com',
			),
			array(
				'id'      => 'mode',
				'type'    => 'select',
				'title'   => pili__( '方式（提前做什么）' ),
				'desc'    => pili__( '不确定就选「连连接也建好」。' ),
				'options' => array(
					'prefetch'    => pili__( '只查域名（轻）' ),
					'preconnect'  => pili__( '连连接也建好（适合 CDN / 字体）' ),
				),
				'default' => 'preconnect',
			),
		),
	),
	array(
		'id'       => 'zr_speed_preload_assets_note',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<p><strong>' . esc_html( pili__( '点名预载某个文件' ) ) . '</strong> ' . esc_html( pili__( '适合全站都会用到的 Logo、主样式、字体文件。可从媒体库复制链接。不知道地址就用上面的「预载首屏图」。' ) ) . '</p>',
		'variant'  => 'info',
	),
	array(
		'id'            => 'zr_speed_preload_assets',
		'type'          => 'repeater',
		'title'         => pili__( '预载文件（点名提前下载）' ),
		'button_title'  => pili__( '添加一个文件' ),
		'preview_field' => 'url',
		'max'           => 15,
		'sanitize'      => $sanitize_assets,
		'default'       => array(),
		'fields'        => array(
			array(
				'id'          => 'url',
				'type'        => 'text',
				'title'       => pili__( '文件地址（完整网址）' ),
				'desc'        => pili__( '完整网址，以 https:// 开头。可从媒体库复制。' ),
				'placeholder' => pili__( 'https://你的域名/wp-content/uploads/logo.png' ),
			),
			array(
				'id'      => 'as',
				'type'    => 'select',
				'title'   => pili__( '文件类型（选错浏览器会忽略）' ),
				'options' => array(
					'image'  => pili__( '图片' ),
					'style'  => pili__( '样式表 CSS' ),
					'font'   => pili__( '字体文件' ),
					'script' => pili__( '脚本 JS（慎用）' ),
				),
				'default' => 'image',
			),
			array(
				'id'      => 'cross',
				'type'    => 'switch',
				'title'   => pili__( '允许跨域（字体一般要开）' ),
				'desc'    => pili__( '预载字体文件时请打开。普通本站图片、CSS 不用开。' ),
				'default' => false,
			),
		),
	),
);
