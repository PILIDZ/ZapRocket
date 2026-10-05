<?php
/**
 * Object-storage vendor presets (tutorials, field hints, capabilities).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-provider copy and capability flags.
 */
final class ZapRocket_Oss_Providers {

	/**
	 * S3 client flags used by ZapRocket_Oss_S3.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function s3_profiles() {
		$out = array();
		foreach ( self::catalog() as $id => $row ) {
			$out[ $id ] = array(
				'label'      => $row['label'],
				'path_style' => ! empty( $row['path_style'] ),
				'region'     => isset( $row['region'] ) ? (string) $row['region'] : '',
			);
		}
		return $out;
	}

	/**
	 * JSON for admin JS (already translated).
	 *
	 * @return array<string,mixed>
	 */
	public static function frontend_presets() {
		$providers = array();
		foreach ( self::catalog() as $id => $row ) {
			$providers[ $id ] = array(
				'label'           => $row['label'],
				'docs'            => $row['docs'],
				'summary'         => $row['summary'],
				'prepare'         => $row['prepare'],
				'keys'            => $row['keys'],
				'pits'            => $row['pits'],
				'fields'          => $row['fields'],
				'supports_rename' => ! empty( $row['supports_rename'] ),
				'supports_prefix' => ! empty( $row['supports_prefix'] ),
				'cap_note'        => isset( $row['cap_note'] ) ? (string) $row['cap_note'] : '',
				'ui'              => isset( $row['ui'] ) && is_array( $row['ui'] ) ? $row['ui'] : array(),
				'domainNote'      => isset( $row['domain_note'] ) ? (string) $row['domain_note'] : '',
			);
		}
		return array(
			'defaultProvider' => 'aliyun',
			'providers'       => $providers,
			'i18n'            => array(
				'guideTitle' => pili__( '当前服务商专属接入教程' ),
				'prepare'    => pili__( '准备工作' ),
				'keys'       => pili__( '获取密钥步骤' ),
				'pits'       => pili__( '排坑提示（厂商专属坑点）' ),
				'docs'       => pili__( '官方文档' ),
				'example'    => pili__( '填写示例' ),
				'exampleFmt' => pili__( '填写示例：%s' ),
				'capHide'     => pili__( '当前服务商不支持该特性，已隐藏对应设置。' ),
				'needAppId'   => pili__( '请填写腾讯云 APPID。未填写时无法保存对象存储设置。' ),
				'r2Domain'    => pili__( '未填写自定义公网域名时，媒体地址不会改写成 R2 API 域名。请绑定自定义域后再保存。' ),
				'foldMore'    => pili__( '展开详情' ),
				'foldLess'    => pili__( '收起' ),
				'prefixBad'   => pili__( '桶内基础目录前缀含有非法字符。不要填写 ../，也不要用 \\ : * ? " < > |。' ),
			),
		);
	}

	/**
	 * HTML guide for first paint.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function guide_html( $provider ) {
		$catalog = self::catalog();
		$id      = sanitize_key( (string) $provider );
		if ( ! isset( $catalog[ $id ] ) ) {
			$id = 'aliyun';
		}
		$row = $catalog[ $id ];
		ob_start();
		echo '<div class="zr-oss-guide" data-oss-guide="1">';
		echo '<pre id="zr-oss-presets" hidden>' . esc_html( (string) wp_json_encode( self::frontend_presets() ) ) . '</pre>';
		echo '<div class="zr-oss-guide__head">';
		echo '<h3 class="zr-oss-guide__title">' . esc_html( pili__( '当前服务商专属接入教程' ) ) . '</h3>';
		echo '<p class="zr-oss-guide__brand">' . esc_html( (string) $row['label'] ) . '</p>';
		echo '</div>';
		echo '<p class="zr-oss-guide__summary">' . esc_html( (string) $row['summary'] ) . '</p>';
		if ( ! empty( $row['domain_note'] ) ) {
			echo '<p class="zr-oss-guide__note">' . esc_html( (string) $row['domain_note'] ) . '</p>';
		}
		if ( ! empty( $row['docs'] ) ) {
			echo '<p class="zr-oss-guide__docs"><a href="' . esc_url( (string) $row['docs'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( pili__( '官方文档' ) ) . '</a></p>';
		}
		self::echo_list( pili__( '准备工作' ), $row['prepare'], 'prepare' );
		self::echo_list( pili__( '获取密钥步骤' ), $row['keys'], 'keys' );
		self::echo_list( pili__( '排坑提示（厂商专属坑点）' ), $row['pits'], 'pits' );
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * @param string   $title Title.
	 * @param string[] $items Items.
	 * @param string   $kind  Kind.
	 * @return void
	 */
	private static function echo_list( $title, $items, $kind ) {
		if ( ! is_array( $items ) || array() === $items ) {
			return;
		}
		echo '<section class="zr-oss-guide__block zr-oss-guide__block--' . esc_attr( $kind ) . '">';
		echo '<h4>' . esc_html( $title ) . '</h4>';
		$tag = ( 'pits' === $kind ) ? 'ul' : 'ol';
		echo '<' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ol/ul only.
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( (string) $item ) . '</li>';
		}
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</section>';
	}

	/**
	 * Capability flags for a provider.
	 *
	 * @param string $provider Provider.
	 * @return array{supports_rename:bool,supports_prefix:bool,cap_note:string}
	 */
	public static function capabilities( $provider ) {
		$catalog = self::catalog();
		$id      = sanitize_key( (string) $provider );
		if ( ! isset( $catalog[ $id ] ) ) {
			$id = 'aliyun';
		}
		$row = $catalog[ $id ];
		return array(
			'supports_rename' => ! empty( $row['supports_rename'] ),
			'supports_prefix' => ! empty( $row['supports_prefix'] ),
			'cap_note'        => isset( $row['cap_note'] ) ? (string) $row['cap_note'] : '',
		);
	}

	/**
	 * Full catalog. Add a vendor here to extend the UI.
	 * i18n: 教程、坑点、字段备注一律 pili__()；切换服务商时 JS 使用已翻译的 frontend_presets()。
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalog() {
		return array(
			'aliyun'  => array(
				'label'           => pili__( '阿里云 OSS' ),
				'path_style'      => false,
				'region'          => '',
				'supports_rename' => true,
				'supports_prefix' => true,
				'ui'              => array(
					'show_aliyun_region'  => true,
					'show_tencent_region' => false,
					'show_custom_region'  => false,
					'show_endpoint'       => false,
					'show_app_id'         => false,
				),
				'cap_note'        => '',
				'docs'            => 'https://help.aliyun.com/zh/oss/',
				'summary'         => pili__( '区域下拉选择，接口地址自动拼接。图片公网访问请用已绑定的自定义域名，不要用 API 域名。' ),
				'prepare'         => array(
					pili__( '创建 Bucket，记下 Region ID（如 oss-cn-hangzhou）。' ),
					pili__( '绑定自定义域名并完成 CNAME。国内站点域名需备案。' ),
					pili__( '用 RAM 用户创建 AccessKey，不要用主账号密钥。' ),
				),
				'keys'            => array(
					pili__( 'RAM 授权 OSS 读写后，复制 AccessKey ID 与 Secret。' ),
				),
				'pits'            => array(
					pili__( '桶名不要带英文句点，也不要写成带域名的完整地址。' ),
					pili__( 'API 域名只用于上传管理；浏览器看图必须走自定义域名。' ),
				),
				'domain_note'     => pili__( '自定义域名需完成 CNAME；中国内地站点请使用已备案域名。' ),
				'fields'          => self::fields_pack(
					array(
						'bucket'    => array( 'my-bucket-oss', pili__( '只填桶名，不要带域名。' ) ),
						'region'    => array( 'oss-cn-hangzhou', pili__( 'Bucket 所在区域，可搜索中文或 Region ID。' ) ),
						'domain'    => array( 'https://img.example.com', pili__( '自定义加速域名（公网看图）。不要填 oss-*.aliyuncs.com。' ) ),
						'prefix'    => self::prefix_field_pair(),
						'ak'        => array( '', pili__( 'RAM AccessKey ID，默认掩码显示。' ) ),
						'sk'        => array( '', pili__( 'RAM AccessKey Secret，加密保存。' ) ),
					)
				),
			),
			'tencent' => array(
				'label'           => pili__( '腾讯云 COS' ),
				'path_style'      => false,
				'region'          => '',
				'supports_rename' => true,
				'supports_prefix' => true,
				'ui'              => array(
					'show_aliyun_region'  => false,
					'show_tencent_region' => true,
					'show_custom_region'  => false,
					'show_endpoint'       => false,
					'show_app_id'         => true,
				),
				'cap_note'        => '',
				'docs'            => 'https://cloud.tencent.com/document/product/436',
				'summary'         => pili__( '桶名填自定义段，APPID 单独填。图片请用已绑定的自定义加速域名，不要用 COS API 域名。' ),
				'prepare'         => array(
					pili__( '记下账号 APPID，创建存储桶并选择地域。' ),
					pili__( '绑定自定义域名并完成 CNAME。国内站点域名需备案。' ),
				),
				'keys'            => array(
					pili__( '用子账号 SecretId / SecretKey，不要用登录密码。' ),
				),
				'pits'            => array(
					pili__( 'APPID 必填。自定义域名未做 CNAME 时，前台无法用加速域名访问。' ),
				),
				'domain_note'     => pili__( '自定义域名必须完成 CNAME；中国内地请使用已备案域名。' ),
				'fields'          => self::fields_pack(
					array(
						'bucket'    => array( 'my-bucket', pili__( '只填桶名自定义段，不要带 -APPID。' ) ),
						'appid'     => array( '1250000000', pili__( '腾讯云账号 APPID，纯数字。' ) ),
						'region'    => array( 'ap-guangzhou', pili__( '存储桶所在地域，可搜索中文或简称。' ) ),
						'domain'    => array( 'https://cdn.example.com', pili__( '自定义加速域名（公网看图）。不要填 *.myqcloud.com。' ) ),
						'prefix'    => self::prefix_field_pair(),
						'ak'        => array( '', pili__( 'SecretId，默认掩码显示。' ) ),
						'sk'        => array( '', pili__( 'SecretKey，加密保存。' ) ),
					)
				),
			),
			'r2'      => array(
				'label'           => pili__( 'Cloudflare R2' ),
				'path_style'      => true,
				'region'          => 'us-east-1',
				'supports_rename' => true,
				'supports_prefix' => true,
				'ui'              => array(
					'show_aliyun_region'  => false,
					'show_tencent_region' => false,
					'show_custom_region'  => true,
					'show_endpoint'       => true,
					'show_app_id'         => false,
				),
				'cap_note'        => '',
				'docs'            => 'https://developers.cloudflare.com/r2/',
				'summary'         => pili__( 'Endpoint 是 API 地址，只用于上传。图片公网必须用已绑定的自定义域，不能用 r2.cloudflarestorage.com。' ),
				'prepare'         => array(
					pili__( '创建 R2 桶，记下 Account ID 以填写 Endpoint。' ),
					pili__( '为桶绑定自定义域名并完成 DNS / CNAME，前台才会改写媒体地址。' ),
				),
				'keys'            => array(
					pili__( '创建具备该桶读写权限的 R2 API 令牌。' ),
				),
				'pits'            => array(
					pili__( 'Endpoint 不要带桶名。未绑定自定义域时不会改写前台地址。' ),
				),
				'domain_note'     => pili__( '自定义域名必须完成 CNAME / DNS 解析后才能公网访问。' ),
				'fields'          => self::fields_pack(
					array(
						'bucket'    => array( 'my-r2-bucket', pili__( '只填桶名。' ) ),
						'region'    => array( 'us-east-1', pili__( '签名区域填 us-east-1 或 auto。' ) ),
						'endpoint'  => array( 'https://xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.r2.cloudflarestorage.com', pili__( 'API 域名（上传用）。不要当图片地址。' ) ),
						'domain'    => array( 'https://media.example.com', pili__( '自定义加速域名（公网看图）。' ) ),
						'prefix'    => self::prefix_field_pair(),
						'ak'        => array( '', pili__( 'R2 Access Key ID，默认掩码显示。' ) ),
						'sk'        => array( '', pili__( 'R2 Secret Access Key，加密保存。' ) ),
					)
				),
			),
			'qiniu'   => array(
				'label'           => pili__( '七牛 Kodo' ),
				'path_style'      => true,
				'region'          => '',
				'supports_rename' => true,
				'supports_prefix' => true,
				'ui'              => array(
					'show_aliyun_region'  => false,
					'show_tencent_region' => false,
					'show_custom_region'  => false,
					'show_endpoint'       => false,
					'show_app_id'         => false,
				),
				'cap_note'        => '',
				'docs'            => 'https://developer.qiniu.com/kodo',
				'summary'         => pili__( '用空间密钥上传，无需 Region / Endpoint。图片公网请填已绑定的加速域名。' ),
				'prepare'         => array(
					pili__( '创建空间，绑定加速域名并完成 CNAME。国内站点域名需备案。' ),
					pili__( '在密钥管理复制 AccessKey 和 SecretKey。' ),
				),
				'keys'            => array(
					pili__( '使用空间密钥，不要用登录密码。' ),
				),
				'pits'            => array(
					pili__( '加速域名不是接口地址。未完成 CNAME 时前台不会改写图片地址。' ),
				),
				'domain_note'     => pili__( '加速域名必须完成 CNAME；中国内地请使用已备案域名。' ),
				'fields'          => self::fields_pack(
					array(
						'bucket'    => array( 'my-kodo-bucket', pili__( '填空间名，不要带域名。' ) ),
						'domain'    => array( 'https://img.example.com', pili__( '加速域名（公网看图），需已做 CNAME。' ) ),
						'prefix'    => self::prefix_field_pair(),
						'ak'        => array( '', pili__( '七牛 AccessKey，默认掩码显示。' ) ),
						'sk'        => array( '', pili__( '七牛 SecretKey，加密保存。' ) ),
					)
				),
			),
			'custom'  => array(
				'label'           => pili__( '自定义 S3' ),
				'path_style'      => false,
				'region'          => '',
				'supports_rename' => true,
				'supports_prefix' => true,
				'ui'              => array(
					'show_aliyun_region'  => false,
					'show_tencent_region' => false,
					'show_custom_region'  => true,
					'show_endpoint'       => true,
					'show_app_id'         => false,
				),
				'cap_note'        => '',
				'docs'            => 'https://docs.aws.amazon.com/AmazonS3/latest/userguide/Welcome.html',
				'summary'         => pili__( '填写 API Endpoint 用于上传；公网看图请另填自定义域名。' ),
				'prepare'         => array(
					pili__( '确认网关已开 S3 兼容 API，并准备公网域名。' ),
				),
				'keys'            => array(
					pili__( '创建具备该桶读写权限的密钥。' ),
				),
				'pits'            => array(
					pili__( 'Endpoint 是 API 地址，不要当作图片域名。' ),
				),
				'domain_note'     => pili__( '公网展示请填写已可访问的自定义域名。' ),
				'fields'          => self::fields_pack(
					array(
						'bucket'    => array( 'my-bucket', pili__( 'S3 桶名，不要带域名。' ) ),
						'region'    => array( 'us-east-1', pili__( '签名区域。不确定可填 us-east-1。' ) ),
						'endpoint'  => array( 'https://s3.example.com', pili__( 'API 域名（上传用），不要带桶名。' ) ),
						'domain'    => array( 'https://cdn.example.com', pili__( '自定义加速域名（公网看图）。' ) ),
						'prefix'    => self::prefix_field_pair(),
						'ak'        => array( '', pili__( 'Access Key ID，默认掩码显示。' ) ),
						'sk'        => array( '', pili__( 'Secret Access Key，加密保存。' ) ),
					)
				),
			),
		);
	}

	/**
	 * Keep UTF-8 object keys; drop control chars and empty path segments.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function sanitize_object_key( $key ) {
		$key = str_replace( '\\', '/', (string) $key );
		$key = preg_replace( '/[\x00-\x1F\x7F]/u', '', $key );
		$key = is_string( $key ) ? $key : '';
		$parts = explode( '/', $key );
		$out   = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part || '.' === $part || '..' === $part ) {
				continue;
			}
			$out[] = $part;
		}
		return implode( '/', $out );
	}

	/**
	 * Default bucket directory, same as WordPress media path.
	 *
	 * @return string
	 */
	public static function default_object_prefix() {
		return 'wp-content/uploads';
	}

	/**
	 * Prefix for newly uploaded object keys.
	 *
	 * @param string $prefix Raw.
	 * @return string
	 */
	public static function sanitize_object_prefix( $prefix ) {
		$prefix = str_replace( '\\', '/', (string) $prefix );
		$prefix = preg_replace( '/[<>:"|?*\x00-\x1F\x7F]+/u', '', $prefix );
		$prefix = is_string( $prefix ) ? $prefix : '';
		$prefix = trim( $prefix, "/ \t" );
		$prefix = preg_replace( '#/+#', '/', $prefix );
		$prefix = is_string( $prefix ) ? $prefix : '';
		return self::sanitize_object_key( $prefix );
	}

	/**
	 * Bucket directory actually used for keys (empty setting → wp-content/uploads).
	 *
	 * @return string
	 */
	public static function resolved_object_prefix() {
		$raw = '';
		if ( class_exists( 'ZapRocket_Oss_S3', false ) ) {
			$s   = ZapRocket_Oss_S3::get_settings();
			$raw = (string) ( $s['zr_oss_object_prefix'] ?? '' );
		}
		$prefix = self::sanitize_object_prefix( $raw );
		return '' === $prefix ? self::default_object_prefix() : $prefix;
	}

	/**
	 * Encode object-key path segments (keep slashes). For URLs only.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function encode_key_path( $key ) {
		$key   = trim( str_replace( '\\', '/', (string) $key ), '/' );
		$parts = explode( '/', $key );
		$out   = array();
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$out[] = self::encode_segment( $part );
		}
		return implode( '/', $out );
	}

	/**
	 * @param string $part Segment.
	 * @return string
	 */
	public static function encode_segment( $part ) {
		$part = (string) $part;
		if ( '' === $part ) {
			return '';
		}
		if ( false !== strpos( $part, '%' ) && $part === rawurldecode( $part ) ) {
			return rawurlencode( $part );
		}
		if ( preg_match( '/%[0-9A-Fa-f]{2}/', $part ) ) {
			return $part;
		}
		return rawurlencode( $part );
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private static function prefix_field_pair() {
		return array(
			'wp-content/uploads',
			pili__( '这个前缀会加在桶内文件路径最前面，用来区分「这张图属于哪个网站」。多个网站共用同一个存储桶时，如果都用默认的 wp-content/uploads，路径会撞在一起（都是 wp-content/uploads/2026/10/xxx.jpg），后传的可能盖掉先传的。每个站点请填不同前缀，例如本站填 site2_uploads，桶内就是 site2_uploads/2026/10/xxx.jpg，另一个站填 site1_uploads。只填目录名，不要带域名，不要以斜杠开头或结尾。不填时默认 wp-content/uploads，和 WordPress 媒体库目录一致。原图和缩略图都会带上；从媒体库删除附件时，也会按带前缀的路径删除云上文件。不要填写 ../。' ),
		);
	}

	/**
	 * @param array<string,array{0:string,1:string}> $map Map.
	 * @return array<string,array{example:string,hint:string}>
	 */
	private static function fields_pack( array $map ) {
		$out = array();
		foreach ( $map as $key => $pair ) {
			$out[ $key ] = array(
				'example' => (string) $pair[0],
				'hint'    => (string) $pair[1],
			);
		}
		return $out;
	}
}
