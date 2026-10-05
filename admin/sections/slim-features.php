<?php
/**
 * WP 瘦身 · 关掉用不到的功能 (zr_slim_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'id'      => 'zr_slim_features_intro',
		'type'    => 'content',
		'title'   => '',
		'content' => '<p>' . esc_html( pili__( '按需禁用 WordPress 内置功能，减少脚本与入口。不确定的项请保持关闭。' ) ) . '</p>',
		'variant' => 'info',
	),
	array(
		'id'      => 'zr_slim_disable_emojis',
		'type'    => 'switch',
		'title'   => pili__( '禁用 Emoji（少加载表情脚本）' ),
		'desc'    => pili__( '表情是小小的心情图标，好玩，但很多网站并不需要。开了会少加载一套转换脚本，页面轻一点。手机输入法自带的表情一般不受影响。' ),
		'default' => true,
	),
	array(
		'id'      => 'zr_slim_remove_jquery_migrate',
		'type'    => 'switch',
		'title'   => pili__( '移除 jQuery Migrate（去掉老兼容脚本）' ),
		'desc'    => pili__( '这是给很老的 jQuery 写法做兼容的，会多占一点加载。多数新主题可以去掉。只改前台，后台编辑器不动。去掉后前台脚本报错，再把本项关掉。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_disable_admin_bar',
		'type'    => 'switch',
		'title'   => pili__( '禁用管理黑条（前台不显示顶栏）' ),
		'desc'    => pili__( '登录后看网站，页面最顶上那条 WordPress 黑条会藏起来。部分主题本来就会显示它。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_disable_embeds',
		'type'    => 'switch',
		'title'   => pili__( '禁用 oEmbed（文章里不自动变卡片）' ),
		'desc'    => pili__( '文章里贴外站链接时，WordPress 会自动变成卡片，同时多请求一次 wp-embed.min.js。很多站点觉得方便，想留着就不要开本项。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_disable_xmlrpc',
		'type'    => 'switch',
		'title'   => pili__( '禁用 XML-RPC（关掉远程发文接口）' ),
		'desc'    => pili__( '给手机客户端、Jetpack、远程发文章用的老接口，也常被拿来撞密码。不用这些就关掉。不确定就别开。' ),
		'default' => false,
	),
	array(
		'id'      => 'zr_slim_disable_self_ping',
		'type'    => 'switch',
		'title'   => pili__( '禁用自己 Pingback（不给自己留引用）' ),
		'desc'    => pili__( '正文里贴了自己网站的链接，WordPress 会给自己留一条 Pingback，基本是噪音，还浪费资源。建议打开本项。' ),
		'default' => true,
	),
	array(
		'id'      => 'zr_slim_disable_comments',
		'type'    => 'switch',
		'title'   => pili__( '禁用评论（全站不能再评）' ),
		'desc'    => pili__( '默认关。打开后文章、页面都不能再评，主题评论区会显示关闭，不会白屏。还要互动就别开。' ),
		'default' => false,
	),
);
