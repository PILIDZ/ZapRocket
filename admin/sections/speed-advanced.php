<?php
/**
 * 速度优化 · 高级规则。对齐 WP Rocket Advanced Rules。
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

$sanitize_paths = static function ( $value ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
	$out  = array();
	foreach ( explode( "\n", $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || preg_match( '/^\s*javascript:/i', $line ) ) {
			continue;
		}
		if ( preg_match( '#^https?://#i', $line ) || 0 === strpos( $line, '//' ) ) {
			$path  = wp_parse_url( $line, PHP_URL_PATH );
			$query = wp_parse_url( $line, PHP_URL_QUERY );
			$line  = is_string( $path ) ? $path : '';
			if ( is_string( $query ) && '' !== $query ) {
				$line .= '?' . $query;
			}
		}
		$line = sanitize_text_field( $line );
		if ( '' === $line ) {
			continue;
		}
		$out[] = $line;
	}
	return implode( "\n", array_values( array_unique( $out ) ) );
};

$sanitize_lifespan = static function ( $value ) {
	$n = (int) $value;
	if ( $n < 0 ) {
		$n = 0;
	}
	if ( $n > 365 ) {
		$n = 365;
	}
	return $n;
};

$sanitize_unit = static function ( $value ) {
	$value = sanitize_key( (string) $value );
	return in_array( $value, array( 'hours', 'days' ), true ) ? $value : 'hours';
};

$blank_adv = static function () {
	return '';
};

return array(
	array(
		'id'       => 'zr_speed_adv_phase_note',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank_adv,
		'content'  => '<p>' . esc_html( pili__( '整页缓存默认关。打开后，未登录访客在主题查询完成时（template_redirect）才读磁盘 HTML，第一次访问仍会完整跑 WordPress，不如把缓存放在更早 PHP 入口快。登录用户、购物车/结账/账户、404、搜索、未列入「可缓存查询参数」的问号地址、以及带着登录或购物车 Cookie 的请求，都不会存、也不会读缓存，避免把私人页面给别人看。请先关掉其它整页缓存插件。' ) ) . '</p>',
		'variant'  => 'caution',
	),
	array(
		'id'      => 'zr_speed_cache_enable',
		'type'    => 'switch',
		'title'   => pili__( '整页缓存（未登录访客读生成好的 HTML）' ),
		'desc'    => pili__( '把整页存到 wp-content/cache/zaprocket，第二次打开更快。默认关。登录页、购物车、结账、账户页、404、搜索页不会缓存。和其它整页缓存插件一起开会乱。' ),
		'default' => false,
	),
	array(
		'id'       => 'zr_speed_cache_purge',
		'type'     => 'content',
		'title'    => '',
		'sanitize' => $blank_adv,
		'content'  => '<p>' . esc_html( pili__( '改完主题、插件或开关后，可立刻清掉已生成的页面和压缩文件。字体本地下载的文件会留着。顶部工具栏也有「清空闪电缓存」。' ) ) . '</p>'
			. '<p><button type="button" class="button" data-zr-act="purge-cache">' . esc_html( pili__( '清空页面缓存' ) ) . '</button> '
			. '<span data-zr-purge-status></span></p>',
		'variant'  => 'info',
	),
	array(
		'id'         => 'zr_speed_adv_lifespan',
		'type'       => 'slider',
		'title'      => pili__( '缓存有效期（隔多久作废）' ),
		'desc'       => pili__( '页面缓存最多留多久。到期后下次访问会重新生成。拉到 0 表示一直留着、不按时间作废。拿不准就保持 10，单位在下面选小时或天。' ),
		'default'    => 10,
		'min'        => 0,
		'max'        => 365,
		'step'       => 1,
		'unit'       => '',
		'suffix'     => '',
		'show_input' => true,
		'show_ticks' => true,
		'tick_step'  => 30,
		'color'      => 'blue',
		'sanitize'   => $sanitize_lifespan,
	),
	array(
		'id'       => 'zr_speed_adv_lifespan_unit',
		'type'     => 'select',
		'title'    => pili__( '有效期单位（按小时还是按天）' ),
		'desc'     => pili__( '和上面的数字一起读：选「小时」则 10 就是 10 小时；选「天」则 10 就是 10 天。' ),
		'options'  => array(
			'hours' => pili__( '小时' ),
			'days'  => pili__( '天' ),
		),
		'default'  => 'hours',
		'sanitize' => $sanitize_unit,
	),
	array(
		'id'          => 'zr_speed_adv_reject_uri',
		'type'        => 'textarea',
		'title'       => pili__( '永不缓存网址（这些页面不存缓存）' ),
		'desc'        => pili__( '写在这里的页面每次都出现场内容，不读缓存。适合登录、退出、购物车、结账、会员中心。填写：每行一条；可粘贴完整网址，保存时会自动去掉 https://域名，只留后面的路径。单页示例：/wp-login.php 。某一栏下面全部页面示例：/docs/(.*) ，其中 (.*) 表示「这一段后面不管是什么都算」。WooCommerce 的购物车、结账、我的账户默认已经不缓存，一般不用再写。不确定就先留空。' ),
		'default'     => '',
		'placeholder' => "/wp-login.php\n/cart/(.*)",
		'sanitize'    => $sanitize_paths,
	),
	array(
		'id'          => 'zr_speed_adv_reject_cookies',
		'type'        => 'textarea',
		'title'       => pili__( '永不缓存 Cookie（带着这些就不缓存）' ),
		'desc'        => pili__( 'Cookie 是网站存在浏览器里的一小段记号，用来记住登录、购物车等。填写：每行一个名称，或名称里的一段文字。只要这次访问带着匹配的 Cookie，就不走缓存，避免「登录后还看到未登录的页面」。名称可在浏览器开发者工具 → 应用/存储 → Cookie 里看到。示例：wordpress_logged_in 。不确定就留空。' ),
		'default'     => '',
		'placeholder' => 'wordpress_logged_in',
		'sanitize'    => $sanitize_lines,
	),
	array(
		'id'          => 'zr_speed_adv_reject_ua',
		'type'        => 'textarea',
		'title'       => pili__( '永不缓存 User Agent（这些浏览器不走缓存）' ),
		'desc'        => pili__( 'User Agent 是浏览器报给网站的身份，例如手机 Safari。填写：每行一段文字。只要访问者的身份里包含这段，就不给他缓存页。适合「某种 App 内置浏览器显示错乱」时单独排除。 (.*) 表示中间可以是任意内容。普通网站一般不用填。示例：(.*)Mobile(.*)Safari(.*)' ),
		'default'     => '',
		'placeholder' => '(.*)Mobile(.*)Safari(.*)',
		'sanitize'    => $sanitize_lines,
	),
	array(
		'id'          => 'zr_speed_adv_purge_urls',
		'type'        => 'textarea',
		'title'       => pili__( '发文清缓存网址（更新文章时一并清）' ),
		'desc'        => pili__( '你更新任意一篇文章或页面时，除了那一篇本身，还会把这里列出的地址缓存一并清掉。适合首页、列表页、栏目页，避免新文章发出去了列表还是旧的。填写：每行一条路径，可粘贴完整网址（域名会自动去掉）。整栏都清可写 /blog/(.*) 。不确定就留空。' ),
		'default'     => '',
		'placeholder' => "/\n/blog/(.*)",
		'sanitize'    => $sanitize_paths,
	),
	array(
		'id'          => 'zr_speed_adv_query_strings',
		'type'        => 'textarea',
		'title'       => pili__( '可缓存查询参数（带这些问号也缓存）' ),
		'desc'        => pili__( '网址问号后面的叫查询参数。例如 https://你的站点/?utm_source=weixin 里的 utm_source。默认：带问号的地址不缓存，以免每个人看到的广告来源页都各存一份。若某种参数可以共用同一份缓存（常见是统计用的 utm_source、utm_medium），把参数名写在这里，每行一个，不要写成整段网址。不确定就留空。' ),
		'default'     => '',
		'placeholder' => "utm_source\nutm_medium\nutm_campaign",
		'sanitize'    => $sanitize_lines,
	),
);
