<?php
/**
 * 对象存储 · 存储设置（媒体上云，zr_oss_*）。
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blank = static function () {
	return '';
};

$providers = array();
if ( class_exists( 'ZapRocket_Oss_S3' ) ) {
	foreach ( ZapRocket_Oss_S3::providers() as $key => $row ) {
		$providers[ $key ] = isset( $row['label'] ) ? (string) $row['label'] : $key;
	}
}

$aliyun_regions  = array();
$tencent_regions = array();
if ( class_exists( 'ZapRocket_Oss_Regions' ) ) {
	$aliyun_regions  = ZapRocket_Oss_Regions::aliyun_grouped();
	$tencent_regions = ZapRocket_Oss_Regions::tencent_grouped();
}

$cdn_on = class_exists( 'ZapRocket_Options' ) && ZapRocket_Options::speed_on( 'zr_speed_cdn_enable', false );
$intro  = '<p>' . esc_html( pili__( '只处理媒体库里新上传的附件（含 REST / 远程导入 / 媒体替换）。文章正文里已经写死的旧链接、上传失败的附件，请到「快捷操作」。要把历史文件也传到云上，请用「一键迁移」。不上传主题或插件静态文件。密钥单独加密保存，不会写进普通配置。' ) ) . '</p>';
if ( $cdn_on ) {
	$intro .= '<p><strong>' . esc_html( pili__( '已开启 CDN 加速。已上云的媒体地址不会再被 CDN 改写，避免两个域名抢同一张图。' ) ) . '</strong></p>';
}

$on           = array( 'zr_oss_enable', '==', '1' );
$dep_aliyun   = array( $on, array( 'zr_oss_provider', '==', 'aliyun' ) );
$dep_tencent  = array( $on, array( 'zr_oss_provider', '==', 'tencent' ) );
$dep_s3compat = array( $on, array( 'zr_oss_provider', 'any', 'r2,custom' ) );

$preset_json = '{}';
if ( class_exists( 'ZapRocket_Oss_Providers' ) ) {
	$encoded = wp_json_encode( ZapRocket_Oss_Providers::frontend_presets() );
	if ( is_string( $encoded ) && '' !== $encoded ) {
		$preset_json = $encoded;
	}
}

return array(
	array(
		'id'       => 'zr_oss_intro',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => $intro,
		'variant'  => $cdn_on ? 'caution' : 'info',
	),
	array(
		'id'       => 'zr_oss_boot',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank,
		'content'  => '<pre id="zr-oss-presets" hidden>' . esc_html( $preset_json ) . '</pre>'
			. '<style id="zr-oss-guide-css" data-zr-oss-schema="dep-v2">.pili-inst-zaprocket .zr-oss-guide{margin:0 0 8px;padding:16px 18px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;transition:opacity .18s ease}.pili-inst-zaprocket .zr-oss-guide--updating{opacity:.55}.pili-inst-zaprocket .zr-oss-guide__title{margin:0;font-size:15px;font-weight:600;color:#111827}.pili-inst-zaprocket .zr-oss-guide__brand{margin:4px 0 0;font-size:13px;color:#2563eb;font-weight:600}.pili-inst-zaprocket .zr-oss-guide__summary,.pili-inst-zaprocket .zr-oss-guide__docs{margin:10px 0 0;font-size:13px;color:#374151;line-height:1.6}.pili-inst-zaprocket .zr-oss-guide__block{margin-top:14px}.pili-inst-zaprocket .zr-oss-guide__block h4{margin:0 0 6px;font-size:13px;font-weight:600}.pili-inst-zaprocket .zr-oss-guide__block ol,.pili-inst-zaprocket .zr-oss-guide__block ul{margin:0;padding-left:1.25rem;font-size:13px;color:#4b5563;line-height:1.65}.pili-inst-zaprocket .zr-oss-guide__block--pits{padding:10px 12px;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa}.pili-inst-zaprocket .zr-oss-guide__block--pits h4{color:#9a3412}.pili-inst-zaprocket .zr-oss-example{margin:4px 0 0}.pili-inst-zaprocket .zr-oss-cap-notice{margin:0;padding:8px 12px;border-radius:8px;background:#eff6ff;color:#1e3a8a;font-size:13px}</style>',
	),
	array(
		'id'      => 'zr_oss_enable',
		'type'    => 'switch',
		'title'   => pili__( '启用对象存储（新媒体上传到云）' ),
		'desc'    => pili__( '打开后，媒体库新上传会先传到对象存储。密钥、桶名未填完整时不会真正启用。' ),
		'default' => false,
	),
	array(
		'id'         => 'zr_oss_no_local',
		'type'       => 'switch',
		'title'      => pili__( '原图尺寸都传完后删除本地文件' ),
		'desc'       => pili__( '仅在原图和全部额外尺寸都上传成功后删除本机文件。卸载插件不会删除云上文件。' ),
		'default'    => false,
		'dependency' => $on,
	),
	array(
		'id'         => 'zr_oss_provider',
		'type'       => 'select',
		'title'      => pili__( '服务商（决定访问方式和默认区域）' ),
		'options'    => $providers,
		'default'    => 'aliyun',
		'searchable' => true,
		'clearable'  => false,
		'dependency' => $on,
	),
	array(
		'id'         => 'zr_oss_guide',
		'type'       => 'content',
		'title'      => '',
		'sanitize'   => $blank,
		'callback'   => 'zaprocket_oss_guide_html',
		'dependency' => $on,
	),
	array(
		'id'          => 'zr_oss_bucket',
		'type'        => 'text',
		'title'       => pili__( 'Bucket（存储桶 / 空间名称）' ),
		'default'     => '',
		'placeholder' => 'my-bucket-oss',
		'desc'        => pili__( '仅填写桶名或空间名，不要带域名。' ),
		'dependency'  => $on,
	),
	array(
		'id'          => 'zr_oss_app_id',
		'type'        => 'text',
		'title'       => pili__( '腾讯云 APPID' ),
		'default'     => '',
		'placeholder' => '1250000000',
		'desc'        => pili__( '账号 APPID，纯数字。保存时会与桶名拼成 桶名-APPID。未填写时无法保存。' ),
		'validate'    => 'zaprocket_oss_validate_app_id',
		'dependency'  => $dep_tencent,
	),
	array(
		'id'                  => 'zr_oss_region_aliyun',
		'type'                => 'select',
		'title'               => pili__( '阿里云区域（Region ID）' ),
		'options'             => $aliyun_regions,
		'default'             => 'oss-cn-hangzhou',
		'searchable'          => true,
		'clearable'           => false,
		'max_height'          => 360,
		'placeholder'         => pili__( '搜索地区或 Region ID…' ),
		'search_placeholder'  => pili__( '搜索杭州、北京或 oss-cn-hangzhou' ),
		'desc'                => pili__( '选项为官方专用 Region ID，可输入中文或 ID 筛选。接口地址由插件自动拼接。' ),
		'dependency'          => $dep_aliyun,
	),
	array(
		'id'                 => 'zr_oss_region_tencent',
		'type'               => 'select',
		'title'              => pili__( '腾讯云区域' ),
		'options'            => $tencent_regions,
		'default'            => 'ap-guangzhou',
		'searchable'         => true,
		'clearable'          => false,
		'max_height'         => 360,
		'placeholder'        => pili__( '搜索地区或地域简称…' ),
		'search_placeholder' => pili__( '搜索广州、上海或 ap-guangzhou' ),
		'desc'               => pili__( '选项为官方地域简称，可输入中文或 ID 筛选。接口地址由插件自动生成。' ),
		'dependency'         => $dep_tencent,
	),
	array(
		'id'          => 'zr_oss_region',
		'type'        => 'text',
		'title'       => pili__( 'Region（区域）' ),
		'desc'        => pili__( 'R2 填 us-east-1 或 auto。自定义 S3 按厂商文档填写。' ),
		'default'     => '',
		'placeholder' => 'us-east-1',
		'dependency'  => $dep_s3compat,
	),
	array(
		'id'           => 'zr_oss_endpoint',
		'type'         => 'text',
		'title'       => pili__( 'Endpoint（API 接口地址，仅上传）' ),
		'desc'        => pili__( 'API 域名只用于上传管理，不能当图片地址。必须带 http:// 或 https://，不要带桶名。' ),
		'default'      => '',
		'placeholder'  => 'https://xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.r2.cloudflarestorage.com',
		'dependency'   => $dep_s3compat,
	),
	array(
		'id'           => 'zr_oss_custom_domain',
		'type'         => 'text',
		'title'        => pili__( '自定义 / 加速域名（公网看图）' ),
		'desc'         => pili__( '图片对外地址。须已绑定并完成 CNAME。国内厂商请用已备案域名。不要填 API 域名。' ),
		'default'      => '',
		'placeholder'  => 'https://img.example.com',
		'dependency'   => $on,
	),
	array(
		'id'          => 'zr_oss_object_prefix',
		'type'        => 'text',
		'title'       => pili__( '桶内基础目录前缀' ),
		'desc'        => pili__( '这个前缀会加在桶内文件路径最前面，用来区分「这张图属于哪个网站」。多个网站共用同一个存储桶时，如果都用默认的 wp-content/uploads，路径会撞在一起（都是 wp-content/uploads/2026/10/xxx.jpg），后传的可能盖掉先传的。每个站点请填不同前缀，例如本站填 site2_uploads，桶内就是 site2_uploads/2026/10/xxx.jpg，另一个站填 site1_uploads。只填目录名，不要带域名，不要以斜杠开头或结尾。不填时默认 wp-content/uploads，和 WordPress 媒体库目录一致。原图和缩略图都会带上；从媒体库删除附件时，也会按带前缀的路径删除云上文件。不要填写 ../。' ),
		'default'     => 'wp-content/uploads',
		'placeholder' => 'wp-content/uploads',
		'validate'    => 'zaprocket_oss_validate_prefix',
		'dependency'  => $on,
	),
	array(
		'id'          => 'zr_oss_access_key',
		'type'        => 'password',
		'title'       => pili__( 'AccessKey（访问密钥 ID）' ),
		'default'     => '',
		'desc'        => pili__( '默认掩码显示，可点右侧眼睛查看。保存前去掉首尾空格。' ),
		'dependency'  => $on,
	),
	array(
		'id'         => 'zr_oss_secret_key',
		'type'       => 'password',
		'title'      => pili__( 'SecretKey（访问密钥）' ),
		'desc'       => pili__( '默认掩码显示，可点右侧眼睛查看。单独加密保存，不会写入普通配置。' ),
		'default'    => '',
		'dependency' => $on,
	),
	array(
		'id'         => 'zr_oss_cap_notice',
		'type'       => 'content',
		'title'      => '',
		'sanitize'   => $blank,
		'content'    => '<p class="zr-oss-cap-notice" hidden></p>',
		'dependency' => $on,
	),
	array(
		'id'         => 'zr_oss_rename_enable',
		'type'       => 'switch',
		'title'      => pili__( '启用上传文件自动重命名' ),
		'desc'       => pili__( '只改对象存储上的对象 Key，不改 WordPress 媒体库里的原始文件名和数据库引用。WebP / AVIF 等副本跟主文件同一套规则。默认关。' ),
		'default'    => false,
		'dependency' => $on,
	),
	array(
		'id'         => 'zr_oss_rename_rule',
		'type'       => 'select',
		'title'      => pili__( '重命名规则' ),
		'options'    => array(
			'md5'       => pili__( 'MD5 哈希文件名' ),
			'timestamp' => pili__( '时间戳 + 随机串' ),
			'sanitize'  => pili__( '保留原文件名，只清理中文 / 空格 / 特殊符号' ),
			'prefix'    => pili__( '自定义前缀 + 清理后的原名' ),
		),
		'default'    => 'md5',
		'dependency' => array(
			array( 'zr_oss_enable', '==', '1' ),
			array( 'zr_oss_rename_enable', '==', '1' ),
		),
	),
	array(
		'id'          => 'zr_oss_rename_prefix',
		'type'        => 'text',
		'title'       => pili__( '自定义文件名前缀' ),
		'desc'        => pili__( '仅在规则选「自定义前缀」时生效。会加在清理后的原名前面，例如 sitea-photo.jpg。' ),
		'default'     => '',
		'placeholder' => 'sitea',
		'dependency'  => array(
			array( 'zr_oss_enable', '==', '1' ),
			array( 'zr_oss_rename_enable', '==', '1' ),
			array( 'zr_oss_rename_rule', '==', 'prefix' ),
		),
	),
	array(
		'id'       => 'zr_oss_test',
		'type'     => 'content',
		'title'    => pili__( '测试连接（不保存当前填写）' ),
		'desc'     => pili__( '用当前填写试连，不会保存。结果在按钮旁显示。' ),
		'sanitize' => $blank,
		'callback' => 'zaprocket_oss_test_button_html',
	),
	array(
		'id'       => 'zr_oss_toast',
		'type'     => 'toast',
		'title'    => '',
		'sanitize' => $blank,
		'position' => 'bottom-center',
		'mount'    => true,
	),
);
