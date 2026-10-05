<?php
/**
 * WP 瘦身 · 网页源代码瘦身 (zr_slim_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'id'      => 'zr_slim_head_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '移除 wp_head 中的多余输出：Feed、generator、RSD、wlwmanifest、shortlink、REST 发现链接等。' ) ) . '</p>',
		'variant' => 'info',
	),
	array(
		'id'      => 'zr_slim_disable_rss',
		'type'    => 'switch',
		'title'   => pili__( '禁用 RSS（订阅器拿不到更新）' ),
		'desc'    => pili__( '有人用 RSS（比如 Feedly）跟着你的新文章走。现在用的人少了。开了之后订阅器就拿不到更新，页头里的订阅链接也会去掉。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_remove_wp_version',
		'type'    => 'switch',
		'title'   => pili__( '移除 WP 版本号（少暴露版本）' ),
		'desc'    => pili__( '源码里会写你用的是哪一版 WordPress。有人觉得藏起来能少挨一些针对旧版本的扫描。' ),
		'default' => true,
	),
	array(
		'id'      => 'zr_slim_remove_wlw',
		'type'    => 'switch',
		'title'   => pili__( '移除 wlwmanifest（老写博客软件用）' ),
		'desc'    => pili__( '这是给 Windows Live Writer 用的。现在几乎没人用那个软件写博客，可以去掉。' ),
		'default' => true,
	),
	array(
		'id'      => 'zr_slim_remove_rsd',
		'type'    => 'switch',
		'title'   => pili__( '移除 RSD（远程发文链接）' ),
		'desc'    => pili__( 'WordPress 会在页头加 EditURI。用第三方软件远程发文章时才需要。大部分站点用不上。' ),
		'default' => true,
	),
	array(
		'id'      => 'zr_slim_remove_shortlink',
		'type'    => 'switch',
		'title'   => pili__( '移除短链接（不要 ?p=123）' ),
		'desc'    => pili__( '如果你已经用了好看的固定链接（比如 /文章名/），源码里那种 ?p=123 的短链就没意义了，可以去掉。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_remove_rest_link',
		'type'    => 'switch',
		'title'   => pili__( '移除 REST 链接（页头不打印接口地址）' ),
		'desc'    => pili__( '只是不在网页头部打印接口链接。接口本身还在，后台写文章不受影响。很多站点用不上这段代码。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_remove_comment_link',
		'type'    => 'switch',
		'title'   => pili__( '移除评论者网站（表单里不要网址栏）' ),
		'desc'    => pili__( '评论表单里默认有一个「网站」栏，会给评论者加外链。去掉后，垃圾评论少一点，页面也干净一点。' ),
		'default' => false,
	),
);
