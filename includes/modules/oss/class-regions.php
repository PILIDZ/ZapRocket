<?php
/**
 * Object-storage region catalogs (Aliyun / Tencent).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Region ID => translated label.
 */
final class ZapRocket_Oss_Regions {

	/**
	 * Dropdown labels include the official Region ID.
	 *
	 * @param array<string,string> $map Id => name.
	 * @return array<string,string>
	 */
	public static function labeled( array $map ) {
		$out = array();
		foreach ( $map as $id => $name ) {
			$out[ $id ] = $name . ' · ' . $id;
		}
		return $out;
	}

	/**
	 * Aliyun OSS dedicated Region IDs (dropdown values).
	 *
	 * @return array<string,string>
	 */
	public static function aliyun() {
		return array(
			'oss-cn-hangzhou'                 => pili__( '华东 1（杭州）' ),
			'oss-cn-shanghai'                 => pili__( '华东 2（上海）' ),
			'oss-cn-wuhan-lr'                 => pili__( '华中 1（武汉-本地地域）' ),
			'oss-cn-qingdao'                  => pili__( '华北 1（青岛）' ),
			'oss-cn-beijing'                  => pili__( '华北 2（北京）' ),
			'oss-cn-zhangjiakou'              => pili__( '华北 3（张家口）' ),
			'oss-cn-huhehaote'                => pili__( '华北 5（呼和浩特）' ),
			'oss-cn-wulanchabu'               => pili__( '华北 6（乌兰察布）' ),
			'oss-cn-shenzhen'                 => pili__( '华南 1（深圳）' ),
			'oss-cn-heyuan'                   => pili__( '华南 2（河源）' ),
			'oss-cn-guangzhou'                => pili__( '华南 3（广州）' ),
			'oss-cn-chengdu'                  => pili__( '西南 1（成都）' ),
			'oss-cn-zhongwei'                 => pili__( '西北 2（中卫）' ),
			'oss-cn-hongkong'                 => pili__( '中国香港' ),
			'oss-rg-china-mainland'           => pili__( '无地域属性（中国内地）' ),
			'oss-ap-northeast-1'              => pili__( '日本（东京）' ),
			'oss-ap-northeast-2'              => pili__( '韩国（首尔）' ),
			'oss-ap-southeast-1'              => pili__( '新加坡' ),
			'oss-ap-southeast-3'              => pili__( '马来西亚（吉隆坡）' ),
			'oss-ap-southeast-5'              => pili__( '印度尼西亚（雅加达）' ),
			'oss-ap-southeast-6'              => pili__( '菲律宾（马尼拉）' ),
			'oss-ap-southeast-7'              => pili__( '泰国（曼谷）' ),
			'oss-ap-southeast-8'              => pili__( '马来西亚（柔佛州）' ),
			'oss-sa-east-1'                   => pili__( '巴西（圣保罗）' ),
			'oss-eu-central-1'                => pili__( '德国（法兰克福）' ),
			'oss-eu-west-1'                   => pili__( '英国（伦敦）' ),
			'oss-eu-west-2'                   => pili__( '法国（巴黎）' ),
			'oss-us-west-1'                   => pili__( '美国（硅谷）' ),
			'oss-us-east-1'                   => pili__( '美国（弗吉尼亚）' ),
			'oss-na-south-1'                  => pili__( '墨西哥' ),
			'oss-me-east-1'                   => pili__( '阿联酋（迪拜）' ),
			'oss-accelerate'                  => pili__( '全球传输加速' ),
			'oss-accelerate-overseas'         => pili__( '非中国内地加速' ),
			'oss-cn-hzfinance'                => pili__( '杭州金融云外网' ),
			'oss-cn-shanghai-finance-1-pub'   => pili__( '上海金融云外网' ),
			'oss-cn-szfinance'                => pili__( '深圳金融云外网' ),
			'oss-cn-beijing-finance-1-pub'    => pili__( '北京金融云外网' ),
			'oss-cn-north-2-gov-1'            => pili__( '华北 2 阿里政务云 1' ),
		);
	}

	/**
	 * Tencent COS region codes.
	 *
	 * @return array<string,string>
	 */
	public static function tencent() {
		return array(
			'ap-beijing'         => pili__( '北京' ),
			'ap-nanjing'         => pili__( '南京' ),
			'ap-shanghai'        => pili__( '上海' ),
			'ap-guangzhou'       => pili__( '广州' ),
			'ap-chengdu'         => pili__( '成都' ),
			'ap-chongqing'       => pili__( '重庆' ),
			'ap-shenzhen-fsi'    => pili__( '深圳金融' ),
			'ap-shanghai-fsi'    => pili__( '上海金融' ),
			'ap-beijing-fsi'     => pili__( '北京金融' ),
			'ap-hongkong'        => pili__( '中国香港' ),
			'ap-singapore'       => pili__( '新加坡' ),
			'ap-jakarta'         => pili__( '雅加达' ),
			'ap-seoul'           => pili__( '首尔' ),
			'ap-bangkok'         => pili__( '曼谷' ),
			'ap-tokyo'           => pili__( '东京' ),
			'ap-osaka'           => pili__( '大阪' ),
			'me-saudi-arabia'    => pili__( '利雅得' ),
			'na-siliconvalley'   => pili__( '硅谷（美西）' ),
			'na-ashburn'         => pili__( '弗吉尼亚（美东）' ),
			'sa-saopaulo'        => pili__( '圣保罗' ),
			'eu-frankfurt'       => pili__( '法兰克福' ),
			'accelerate'         => pili__( '全球加速' ),
		);
	}

	/**
	 * Grouped Aliyun options (official dedicated Region IDs + Chinese labels).
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function aliyun_grouped() {
		$flat = self::aliyun();
		$tree = array(
			pili__( '亚太 - 中国' )   => array(
				'oss-cn-hangzhou',
				'oss-cn-shanghai',
				'oss-cn-wuhan-lr',
				'oss-cn-qingdao',
				'oss-cn-beijing',
				'oss-cn-zhangjiakou',
				'oss-cn-huhehaote',
				'oss-cn-wulanchabu',
				'oss-cn-shenzhen',
				'oss-cn-heyuan',
				'oss-cn-guangzhou',
				'oss-cn-chengdu',
				'oss-cn-zhongwei',
				'oss-cn-hongkong',
				'oss-rg-china-mainland',
			),
			pili__( '亚太 - 其他' )   => array(
				'oss-ap-northeast-1',
				'oss-ap-northeast-2',
				'oss-ap-southeast-1',
				'oss-ap-southeast-3',
				'oss-ap-southeast-5',
				'oss-ap-southeast-6',
				'oss-ap-southeast-7',
				'oss-ap-southeast-8',
			),
			pili__( '欧洲与美洲' )    => array(
				'oss-sa-east-1',
				'oss-eu-central-1',
				'oss-eu-west-1',
				'oss-eu-west-2',
				'oss-us-west-1',
				'oss-us-east-1',
				'oss-na-south-1',
			),
			pili__( '中东' )          => array( 'oss-me-east-1' ),
			pili__( '传输加速' )      => array( 'oss-accelerate', 'oss-accelerate-overseas' ),
			pili__( '金融云外网' )    => array(
				'oss-cn-hzfinance',
				'oss-cn-shanghai-finance-1-pub',
				'oss-cn-szfinance',
				'oss-cn-beijing-finance-1-pub',
			),
			pili__( '政务云' )        => array( 'oss-cn-north-2-gov-1' ),
		);
		return self::label_groups( $flat, $tree );
	}

	/**
	 * Grouped Tencent options (official region codes + Chinese labels).
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function tencent_grouped() {
		$flat = self::tencent();
		$tree = array(
			pili__( '中国大陆' ) => array(
				'ap-beijing',
				'ap-nanjing',
				'ap-shanghai',
				'ap-guangzhou',
				'ap-chengdu',
				'ap-chongqing',
			),
			pili__( '金融云' )   => array(
				'ap-shenzhen-fsi',
				'ap-shanghai-fsi',
				'ap-beijing-fsi',
			),
			pili__( '亚太' )     => array(
				'ap-hongkong',
				'ap-singapore',
				'ap-jakarta',
				'ap-seoul',
				'ap-bangkok',
				'ap-tokyo',
				'ap-osaka',
			),
			pili__( '中东' )     => array( 'me-saudi-arabia' ),
			pili__( '北美' )     => array( 'na-siliconvalley', 'na-ashburn' ),
			pili__( '南美' )     => array( 'sa-saopaulo' ),
			pili__( '欧洲' )     => array( 'eu-frankfurt' ),
			pili__( '全球加速' ) => array( 'accelerate' ),
		);
		return self::label_groups( $flat, $tree );
	}

	/**
	 * @param array<string,string>       $flat Id => name.
	 * @param array<string,array<int,string>> $tree Group => ids.
	 * @return array<string,array<string,string>>
	 */
	private static function label_groups( array $flat, array $tree ) {
		$out = array();
		foreach ( $tree as $group => $ids ) {
			$row = array();
			foreach ( $ids as $id ) {
				if ( ! isset( $flat[ $id ] ) ) {
					continue;
				}
				$row[ $id ] = $flat[ $id ] . ' · ' . $id;
			}
			if ( $row ) {
				$out[ $group ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Public HTTPS API endpoint for Aliyun (no bucket in host).
	 *
	 * @param string $regional Dropdown value (oss-cn-hangzhou).
	 * @return string
	 */
	public static function aliyun_endpoint( $regional ) {
		$regional = sanitize_key( (string) $regional );
		if ( '' === $regional ) {
			$regional = 'oss-cn-hangzhou';
		}
		return 'https://s3.' . $regional . '.aliyuncs.com';
	}

	/**
	 * SigV4 / OSS SDK region (cn-hangzhou).
	 *
	 * @param string $regional Dropdown value.
	 * @return string
	 */
	public static function aliyun_sign_region( $regional ) {
		$regional = strtolower( trim( (string) $regional ) );
		if ( 'oss-accelerate' === $regional ) {
			return 'cn-hangzhou';
		}
		if ( 'oss-accelerate-overseas' === $regional ) {
			return 'cn-hongkong';
		}
		if ( 0 === strpos( $regional, 'oss-' ) ) {
			return substr( $regional, 4 );
		}
		return $regional;
	}

	/**
	 * Tencent COS endpoint (no bucket in host).
	 *
	 * @param string $region ap-guangzhou or accelerate.
	 * @return string
	 */
	public static function tencent_endpoint( $region ) {
		$region = sanitize_key( (string) $region );
		if ( '' === $region ) {
			$region = 'ap-guangzhou';
		}
		if ( 'accelerate' === $region ) {
			return 'https://cos.accelerate.myqcloud.com';
		}
		return 'https://cos.' . $region . '.myqcloud.com';
	}

	/**
	 * Append -APPID when missing.
	 *
	 * @param string $bucket Bucket.
	 * @param string $app_id APPID.
	 * @return string
	 */
	public static function tencent_bucket( $bucket, $app_id ) {
		$bucket = trim( (string) $bucket );
		$app_id = preg_replace( '/\D+/', '', (string) $app_id );
		$app_id = is_string( $app_id ) ? $app_id : '';
		if ( '' === $bucket || '' === $app_id ) {
			return $bucket;
		}
		$suffix = '-' . $app_id;
		if ( strlen( $bucket ) >= strlen( $suffix ) && substr( $bucket, -strlen( $suffix ) ) === $suffix ) {
			return $bucket;
		}
		return $bucket . $suffix;
	}
}
