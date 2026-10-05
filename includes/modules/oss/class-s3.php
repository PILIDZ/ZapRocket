<?php
/**
 * S3-compatible client, settings, and secret encryption.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Object storage S3 wrapper.
 */
final class ZapRocket_Oss_S3 {

	const SECRET_OPTION = 'zaprocket_oss_secret_key';
	const LOG_OPTION    = 'zaprocket_oss_error_logs';

	/** @var \Aws\S3\S3Client|array<string,mixed>|null */
	private static $client = null;

	/** @var array<string,mixed>|null */
	private static $conn = null;

	/** @var string */
	private static $last_error = '';

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function providers() {
		if ( class_exists( 'ZapRocket_Oss_Providers', false ) ) {
			return ZapRocket_Oss_Providers::s3_profiles();
		}
		return array(
			'aliyun'  => array(
				'label'      => pili__( '阿里云 OSS' ),
				'path_style' => false,
				'region'     => '',
			),
			'tencent' => array(
				'label'      => pili__( '腾讯云 COS' ),
				'path_style' => false,
				'region'     => '',
			),
			'r2'      => array(
				'label'      => pili__( 'Cloudflare R2' ),
				'path_style' => true,
				'region'     => 'us-east-1',
			),
			'qiniu'   => array(
				'label'      => pili__( '七牛 Kodo' ),
				'path_style' => true,
				'region'     => '',
			),
			'custom'  => array(
				'label'      => pili__( '自定义 S3' ),
				'path_style' => false,
				'region'     => '',
			),
		);
	}

	/**
	 * @param string $message Message.
	 * @return void
	 */
	private static function set_last_error( $message ) {
		self::$last_error = is_string( $message ) ? trim( $message ) : '';
	}

	/**
	 * @param string $message Message.
	 * @return void
	 */
	public static function set_last_error_public( $message ) {
		self::set_last_error( $message );
	}

	/**
	 * @return string
	 */
	public static function get_last_error() {
		return self::$last_error;
	}

	/**
	 * AWS SDK (PHP 8.1+). Do not autoload it on older PHP.
	 *
	 * @return bool
	 */
	public static function aws_sdk_available() {
		if ( ! defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 80100 ) {
			return false;
		}
		return class_exists( '\Aws\S3\S3Client' );
	}

	/**
	 * Transport ready: SDK or WordPress HTTP SigV4 fallback.
	 *
	 * @return bool
	 */
	public static function sdk_ready() {
		return self::aws_sdk_available() || function_exists( 'wp_remote_request' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'zr_oss_enable'        => false,
			'zr_oss_no_local'      => false,
			'zr_oss_provider'      => 'aliyun',
			'zr_oss_bucket'        => '',
			'zr_oss_region'        => '',
			'zr_oss_endpoint'      => '',
			'zr_oss_custom_domain' => '',
			'zr_oss_app_id'        => '',
			'zr_oss_region_aliyun' => 'oss-cn-hangzhou',
			'zr_oss_region_tencent'=> 'ap-guangzhou',
			'zr_oss_access_key'     => '',
			'zr_oss_object_prefix'  => 'wp-content/uploads',
			'zr_oss_rename_enable'  => false,
			'zr_oss_rename_rule'    => 'md5',
			'zr_oss_rename_prefix'  => '',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$settings = self::defaults();
		foreach ( array_keys( $settings ) as $key ) {
			$val = ZapRocket_Options::get( 'oss', $key, $settings[ $key ] );
			if ( null !== $val ) {
				$settings[ $key ] = $val;
			}
		}
		$filtered = apply_filters( 'zaprocket_oss_settings', $settings );
		return is_array( $filtered ) ? array_merge( $settings, $filtered ) : $settings;
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! ZapRocket_Context::plugin_active() ) {
			return false;
		}
		$s = self::get_settings();
		return ZapRocket_Options::is_on( $s['zr_oss_enable'] ?? false );
	}

	/**
	 * @return bool
	 */
	public static function no_local() {
		$s = self::get_settings();
		return ZapRocket_Options::is_on( $s['zr_oss_no_local'] ?? false );
	}

	/**
	 * @return string
	 */
	private static function encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|zaprocket-oss-secret', true );
	}

	/**
	 * @param string $secret Raw.
	 * @return string
	 */
	public static function encrypt_secret( $secret ) {
		$secret = trim( (string) $secret );
		if ( '' === $secret ) {
			return '';
		}
		$key = self::encryption_key();
		$iv  = random_bytes( 12 );
		$tag = '';
		$enc = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $enc ) {
			return '';
		}
		return 'v2:' . base64_encode( $iv . $tag . $enc );
	}

	/**
	 * @param string $secret Stored.
	 * @return string
	 */
	public static function decrypt_secret( $secret ) {
		$secret = (string) $secret;
		if ( '' === $secret ) {
			return '';
		}
		if ( 0 === strpos( $secret, 'v2:' ) ) {
			$decoded = base64_decode( substr( $secret, 3 ), true );
			if ( false === $decoded || strlen( $decoded ) < 28 ) {
				return '';
			}
			$iv  = substr( $decoded, 0, 12 );
			$tag = substr( $decoded, 12, 16 );
			$enc = substr( $decoded, 28 );
			$dec = openssl_decrypt( $enc, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
			return false !== $dec ? $dec : '';
		}
		return $secret;
	}

	/**
	 * @param string $raw Raw from form.
	 * @return string
	 */
	public static function maybe_save_secret( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$enc = self::encrypt_secret( $raw );
		if ( '' !== $enc ) {
			update_option( self::SECRET_OPTION, $enc, false );
		}
		self::reset_client();
		return '';
	}

	/**
	 * @return string
	 */
	public static function get_secret() {
		$stored = get_option( self::SECRET_OPTION, '' );
		return self::decrypt_secret( is_string( $stored ) ? $stored : '' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function provider_profile() {
		$s        = self::get_settings();
		$provider = sanitize_key( (string) ( $s['zr_oss_provider'] ?? 'aliyun' ) );
		$list     = self::providers();
		if ( ! isset( $list[ $provider ] ) ) {
			$provider = 'custom';
		}
		return $list[ $provider ];
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function connection_config() {
		$s      = self::get_settings();
		$bucket = trim( (string) $s['zr_oss_bucket'] );
		$ak     = trim( (string) $s['zr_oss_access_key'] );
		$sk     = self::get_secret();
		if ( '' === $bucket || '' === $ak || '' === $sk ) {
			self::set_last_error( pili__( '对象存储配置不完整。请填写 AccessKey、SecretKey、Bucket。' ) );
			return null;
		}
		$provider = sanitize_key( (string) ( $s['zr_oss_provider'] ?? 'aliyun' ) );
		$bucket   = self::normalize_bucket( $bucket );
		$profile  = self::provider_profile();

		if ( 'qiniu' === $provider ) {
			return array(
				'driver'     => 'qiniu',
				'bucket'     => $bucket,
				'ak'         => $ak,
				'sk'         => $sk,
				'region'     => '',
				'endpoint'   => '',
				'path_style' => true,
			);
		}

		$app_id = trim( (string) ( $s['zr_oss_app_id'] ?? '' ) );
		if ( 'tencent' === $provider && class_exists( 'ZapRocket_Oss_Regions', false ) ) {
			if ( '' === $app_id ) {
				self::set_last_error( pili__( '请填写腾讯云 APPID，保存时会自动拼成 桶名-APPID。' ) );
				return null;
			}
			$bucket = ZapRocket_Oss_Regions::tencent_bucket( $bucket, $app_id );
		}

		$region_ali = trim( (string) ( $s['zr_oss_region_aliyun'] ?? '' ) );
		$region_tc  = trim( (string) ( $s['zr_oss_region_tencent'] ?? '' ) );
		$region     = trim( (string) ( $s['zr_oss_region'] ?? '' ) );
		if ( 'aliyun' === $provider && '' !== $region_ali ) {
			$region = $region_ali;
		} elseif ( 'tencent' === $provider && '' !== $region_tc ) {
			$region = $region_tc;
		}

		if ( 'aliyun' === $provider && class_exists( 'ZapRocket_Oss_Regions', false ) ) {
			if ( '' === $region ) {
				$region = 'oss-cn-hangzhou';
			}
			return array(
				'driver'     => 's3',
				'bucket'     => $bucket,
				'ak'         => $ak,
				'sk'         => $sk,
				'region'     => ZapRocket_Oss_Regions::aliyun_sign_region( $region ),
				'endpoint'   => ZapRocket_Oss_Regions::aliyun_endpoint( $region ),
				'path_style' => false,
			);
		}

		if ( 'tencent' === $provider && class_exists( 'ZapRocket_Oss_Regions', false ) ) {
			if ( '' === $region ) {
				$region = 'ap-guangzhou';
			}
			$sign = ( 'accelerate' === $region ) ? 'ap-guangzhou' : $region;
			return array(
				'driver'     => 's3',
				'bucket'     => $bucket,
				'ak'         => $ak,
				'sk'         => $sk,
				'region'     => sanitize_text_field( $sign ),
				'endpoint'   => ZapRocket_Oss_Regions::tencent_endpoint( $region ),
				'path_style' => false,
			);
		}

		if ( ! empty( $profile['region'] ) ) {
			$region = (string) $profile['region'];
		}
		$raw_endpoint = trim( (string) ( $s['zr_oss_endpoint'] ?? '' ) );
		if ( 'r2' === $provider || 'custom' === $provider ) {
			if ( '' === $raw_endpoint || ! preg_match( '#^https?://#i', $raw_endpoint ) ) {
				self::set_last_error( pili__( 'Endpoint 必须以 http:// 或 https:// 开头。' ) );
				return null;
			}
		}
		$endpoint = self::normalize_endpoint( $raw_endpoint, $bucket, $provider );
		if ( 'r2' === $provider ) {
			$region = ( '' === $region || 'auto' === strtolower( $region ) ) ? 'us-east-1' : $region;
		} elseif ( '' === $region || 'auto' === strtolower( $region ) ) {
			$derived = self::region_from_endpoint( $endpoint, $provider );
			$region  = '' !== $derived ? $derived : 'us-east-1';
		}
		return array(
			'driver'     => 's3',
			'bucket'     => $bucket,
			'ak'         => $ak,
			'sk'         => $sk,
			'region'     => sanitize_text_field( $region ),
			'endpoint'   => $endpoint,
			'path_style' => ! empty( $profile['path_style'] ),
		);
	}

	/**
	 * Bucket only; strip pasted hostnames.
	 *
	 * @param string $bucket Raw.
	 * @return string
	 */
	public static function normalize_bucket( $bucket ) {
		$bucket = trim( (string) $bucket );
		$bucket = preg_replace( '#^https?://#i', '', $bucket );
		$bucket = is_string( $bucket ) ? trim( $bucket, '/' ) : '';
		if ( preg_match( '/^([^./]+)\.oss-[a-z0-9-]+\.aliyuncs\.com$/i', $bucket, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/^([^./]+)\.cos\.[a-z0-9-]+\.myqcloud\.com$/i', $bucket, $m ) ) {
			return $m[1];
		}
		if ( false !== strpos( $bucket, '/' ) ) {
			$bucket = (string) strtok( $bucket, '/' );
		}
		return $bucket;
	}

	/**
	 * Regional API host, never bucket.example.com (SDK adds the bucket itself).
	 *
	 * @param string $endpoint Raw.
	 * @param string $bucket   Bucket.
	 * @param string $provider Provider.
	 * @return string
	 */
	public static function normalize_endpoint( $endpoint, $bucket, $provider ) {
		$endpoint = untrailingslashit( trim( (string) $endpoint ) );
		$bucket   = trim( (string) $bucket );
		$provider = sanitize_key( (string) $provider );
		if ( '' === $endpoint ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $endpoint ) ) {
			return '';
		}
		$parsed = wp_parse_url( $endpoint );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) || ! is_string( $parsed['host'] ) ) {
			return untrailingslashit( $endpoint );
		}
		$host   = $parsed['host'];
		$scheme = ( ! empty( $parsed['scheme'] ) && is_string( $parsed['scheme'] ) ) ? $parsed['scheme'] : 'https';
		$path   = isset( $parsed['path'] ) && is_string( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
		$port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : 0;
		if ( '' !== $bucket && 0 === stripos( $host, $bucket . '.' ) ) {
			$host = substr( $host, strlen( $bucket ) + 1 );
		}
		if ( '' !== $bucket && '' !== $path ) {
			$segs = explode( '/', $path );
			if ( isset( $segs[0] ) && 0 === strcasecmp( $segs[0], $bucket ) ) {
				array_shift( $segs );
				$path = implode( '/', $segs );
			}
		}
		$out = $scheme . '://' . $host;
		if ( $port && ! in_array( $port, array( 80, 443 ), true ) ) {
			$out .= ':' . $port;
		}
		if ( '' !== $path ) {
			$out .= '/' . $path;
		}
		return untrailingslashit( $out );
	}

	/**
	 * @param string $endpoint Endpoint.
	 * @param string $provider Provider.
	 * @return string
	 */
	public static function region_from_endpoint( $endpoint, $provider ) {
		$host = wp_parse_url( $endpoint, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}
		if ( 'aliyun' === $provider && preg_match( '/(oss-[a-z0-9-]+)\.aliyuncs\.com$/i', $host, $m ) ) {
			return $m[1];
		}
		if ( 'tencent' === $provider && preg_match( '/cos\.([a-z0-9-]+)\.myqcloud\.com$/i', $host, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * @return \Aws\S3\S3Client|array<string,mixed>|null
	 */
	public static function get_client() {
		self::set_last_error( '' );
		if ( null !== self::$client ) {
			return self::$client;
		}
		$conn = self::connection_config();
		if ( ! $conn ) {
			return null;
		}
		self::$conn   = $conn;
		self::$client = $conn;
		return self::$client;
	}

	/**
	 * @return void
	 */
	public static function reset_client() {
		self::$client = null;
		self::$conn   = null;
	}

	/**
	 * @param string $local_path File.
	 * @param string $key        Object key.
	 * @return bool
	 */
	public static function upload_file( $local_path, $key ) {
		$client = self::get_client();
		if ( ! $client ) {
			return false;
		}
		if ( is_array( $client ) && isset( $client['driver'] ) && 'qiniu' === $client['driver'] ) {
			return ZapRocket_Oss_Qiniu::upload_file( $client, $local_path, $key );
		}
		if ( is_array( $client ) ) {
			$mime = wp_check_filetype( $local_path );
			$headers = array();
			if ( ! empty( $mime['type'] ) ) {
				$headers['Content-Type'] = $mime['type'];
			}
			$code = self::http_request( $client, 'PUT', $key, $local_path, $headers );
			return $code >= 200 && $code < 300;
		}
		$bucket = ( is_array( self::$conn ) && ! empty( self::$conn['bucket'] ) )
			? (string) self::$conn['bucket']
			: trim( (string) ZapRocket_Oss_S3::get_settings()['zr_oss_bucket'] );
		try {
			$params = array(
				'Bucket'     => $bucket,
				'Key'        => $key,
				'SourceFile' => $local_path,
			);
			$mime = wp_check_filetype( $local_path );
			if ( ! empty( $mime['type'] ) ) {
				$params['ContentType'] = $mime['type'];
			}
			$client->putObject( $params );
			return true;
		} catch ( Exception $e ) {
			self::set_last_error( $e->getMessage() );
			return false;
		}
	}

	/**
	 * @param string $key Key.
	 * @return bool
	 */
	public static function delete_object( $key ) {
		$client = self::get_client();
		if ( ! $client ) {
			return false;
		}
		if ( is_array( $client ) && isset( $client['driver'] ) && 'qiniu' === $client['driver'] ) {
			return ZapRocket_Oss_Qiniu::delete_object( $client, $key );
		}
		if ( is_array( $client ) ) {
			$code = self::http_request( $client, 'DELETE', $key, '', array() );
			return $code >= 200 && $code < 300;
		}
		$bucket = ( is_array( self::$conn ) && ! empty( self::$conn['bucket'] ) )
			? (string) self::$conn['bucket']
			: trim( (string) ZapRocket_Oss_S3::get_settings()['zr_oss_bucket'] );
		try {
			$client->deleteObject(
				array(
					'Bucket' => $bucket,
					'Key'    => $key,
				)
			);
			return true;
		} catch ( Exception $e ) {
			self::set_last_error( $e->getMessage() );
			return false;
		}
	}

	/**
	 * @return array{success:bool,message:string,skew?:int}
	 */
	public static function test_connection() {
		$client = self::get_client();
		if ( ! $client ) {
			$msg = self::get_last_error();
			return array(
				'success' => false,
				'message' => $msg ? $msg : pili__( '无法初始化连接，请检查 AccessKey、SecretKey、Bucket、Region、Endpoint。' ),
			);
		}
		$s      = self::get_settings();
		$bucket = ( is_array( self::$conn ) && ! empty( self::$conn['bucket'] ) )
			? (string) self::$conn['bucket']
			: trim( (string) $s['zr_oss_bucket'] );
		if ( is_array( $client ) && isset( $client['driver'] ) && 'qiniu' === $client['driver'] ) {
			return ZapRocket_Oss_Qiniu::test_connection( $client );
		}
		if ( is_array( $client ) ) {
			$code = self::http_request( $client, 'HEAD', '', '', array(), true );
			if ( $code >= 200 && $code < 300 ) {
				return array(
					'success' => true,
					'message' => self::success_message(),
					'skew'    => 0,
				);
			}
			$msg = self::get_last_error();
			return array(
				'success' => false,
				'message' => $msg ? $msg : sprintf(
					/* translators: %d: HTTP status */
					pili__( '连接失败（HTTP %d）。请核对密钥、Bucket、Region、Endpoint。' ),
					(int) $code
				),
			);
		}
		try {
			$result = $client->headBucket( array( 'Bucket' => $bucket ) );
			$skew   = self::clock_skew_seconds( $result );
			$msg    = self::success_message();
			if ( abs( $skew ) > 300 ) {
				$msg .= ' ' . sprintf(
					/* translators: %d: seconds */
					pili__( '服务器时间与对象存储相差约 %d 秒。超过约 15 分钟会导致签名失败，请校准系统时间（UTC）。' ),
					(int) $skew
				);
			}
			return array(
				'success' => true,
				'message' => $msg,
				'skew'    => $skew,
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => class_exists( 'ZapRocket_Oss_Errors', false )
					? ZapRocket_Oss_Errors::for_user( $e->getMessage(), 0, self::error_vendor() )
					: pili__( '对象存储请求失败。请核对配置后重试。' ),
			);
		}
	}

	/**
	 * @return string
	 */
	public static function success_message() {
		$msg = pili__( '连接成功。' );
		$s   = self::get_settings();
		if ( '' === trim( (string) ( $s['zr_oss_custom_domain'] ?? '' ) ) ) {
			$msg .= ' ' . pili__( '请填写已绑定的自定义加速域名，否则前台不会改写为云上地址。' );
		}
		return $msg;
	}

	/**
	 * @param mixed $result AWS result.
	 * @return int
	 */
	private static function clock_skew_seconds( $result ) {
		$header = '';
		if ( is_object( $result ) && isset( $result['@metadata']['headers'] ) && is_array( $result['@metadata']['headers'] ) ) {
			$headers = array_change_key_case( $result['@metadata']['headers'], CASE_LOWER );
			if ( ! empty( $headers['date'] ) ) {
				$header = (string) $headers['date'];
			}
		}
		if ( '' === $header ) {
			return 0;
		}
		$remote = strtotime( $header );
		if ( ! is_int( $remote ) ) {
			return 0;
		}
		return $remote - time();
	}

	/**
	 * @param string $key Object key.
	 * @return string
	 */
	private static function encode_key_path( $key ) {
		$key   = ltrim( str_replace( '\\', '/', (string) $key ), '/' );
		$parts = explode( '/', $key );
		$out   = array();
		foreach ( $parts as $part ) {
			$out[] = rawurlencode( $part );
		}
		return implode( '/', $out );
	}

	/**
	 * @param array<string,mixed> $conn Conn.
	 * @param string              $key  Object key; empty for bucket root.
	 * @return array{url:string,host:string,uri:string}
	 */
	private static function http_target( $conn, $key ) {
		$bucket   = (string) $conn['bucket'];
		$endpoint = (string) $conn['endpoint'];
		if ( '' === $endpoint ) {
			$endpoint = 'https://s3.' . $conn['region'] . '.amazonaws.com';
		}
		$parsed = wp_parse_url( $endpoint );
		$scheme = ( ! empty( $parsed['scheme'] ) && is_string( $parsed['scheme'] ) ) ? $parsed['scheme'] : 'https';
		$host   = ( ! empty( $parsed['host'] ) && is_string( $parsed['host'] ) ) ? $parsed['host'] : '';
		$port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : 0;
		$path   = isset( $parsed['path'] ) && is_string( $parsed['path'] ) ? untrailingslashit( $parsed['path'] ) : '';
		if ( $port && ! in_array( $port, array( 80, 443 ), true ) ) {
			$host .= ':' . $port;
		}
		$enc_key = '' === $key ? '' : self::encode_key_path( $key );
		if ( ! empty( $conn['path_style'] ) ) {
			$uri = $path . '/' . rawurlencode( $bucket ) . ( '' === $enc_key ? '' : '/' . $enc_key );
			$url = $scheme . '://' . $host . $uri;
		} else {
			$vhost = $bucket . '.' . preg_replace( '/:\d+$/', '', $host );
			if ( $port && ! in_array( $port, array( 80, 443 ), true ) ) {
				$vhost .= ':' . $port;
			}
			$uri  = ( '' === $path ? '' : $path ) . ( '' === $enc_key ? '/' : '/' . $enc_key );
			$host = $vhost;
			$url  = $scheme . '://' . $host . $uri;
		}
		if ( '' === $uri ) {
			$uri = '/';
		}
		return array(
			'url'  => $url,
			'host' => $host,
			'uri'  => $uri,
		);
	}

	/**
	 * AWS SigV4 via wp_remote_request (PHP 8.0 / no SDK).
	 *
	 * @param array<string,mixed>  $conn    Conn.
	 * @param string               $method  HTTP method.
	 * @param string               $key     Object key.
	 * @param string               $file    Local file for PUT.
	 * @param array<string,string> $headers Extra headers.
	 * @param bool                 $bucket  HEAD/GET bucket.
	 * @return int HTTP status, 0 on transport error.
	 */
	private static function http_request( $conn, $method, $key, $file, $headers, $bucket = false ) {
		$method = strtoupper( (string) $method );
		$target = self::http_target( $conn, $bucket ? '' : $key );
		$amz    = gmdate( 'Ymd\THis\Z' );
		$date   = gmdate( 'Ymd' );
		$region = (string) $conn['region'];
		$body   = '';
		if ( 'PUT' === $method && '' !== $file && is_readable( $file ) ) {
			$payload = hash_file( 'sha256', $file );
			if ( ! is_string( $payload ) ) {
				self::set_last_error( pili__( '无法读取要上传的本地文件。' ) );
				return 0;
			}
			$body = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} else {
			$payload = hash( 'sha256', '' );
		}

		$hdrs = array(
			'host'                 => $target['host'],
			'x-amz-content-sha256' => $payload,
			'x-amz-date'           => $amz,
		);
		foreach ( $headers as $hk => $hv ) {
			$hdrs[ strtolower( (string) $hk ) ] = trim( (string) $hv );
		}
		ksort( $hdrs );
		$signed = array();
		$canon  = '';
		foreach ( $hdrs as $hk => $hv ) {
			$signed[] = $hk;
			$canon   .= $hk . ':' . $hv . "\n";
		}
		$signed_str = implode( ';', $signed );
		$canonical  = $method . "\n" . $target['uri'] . "\n\n" . $canon . "\n" . $signed_str . "\n" . $payload;
		$scope      = $date . '/' . $region . '/s3/aws4_request';
		$string     = 'AWS4-HMAC-SHA256' . "\n" . $amz . "\n" . $scope . "\n" . hash( 'sha256', $canonical );
		$k_date     = hash_hmac( 'sha256', $date, 'AWS4' . $conn['sk'], true );
		$k_region   = hash_hmac( 'sha256', $region, $k_date, true );
		$k_service  = hash_hmac( 'sha256', 's3', $k_region, true );
		$k_signing  = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
		$signature  = hash_hmac( 'sha256', $string, $k_signing );
		$auth       = 'AWS4-HMAC-SHA256 Credential=' . $conn['ak'] . '/' . $scope . ', SignedHeaders=' . $signed_str . ', Signature=' . $signature;

		$send = array();
		foreach ( $hdrs as $hk => $hv ) {
			$send[ $hk ] = $hv;
		}
		$send['Authorization'] = $auth;

		$args = array(
			'method'    => $method,
			'headers'   => $send,
			'timeout'   => 60,
			'sslverify' => true,
		);
		if ( 'PUT' === $method ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $target['url'], $args );
		if ( is_wp_error( $response ) ) {
			$vendor = self::error_vendor();
			self::set_last_error(
				class_exists( 'ZapRocket_Oss_Errors', false )
					? ZapRocket_Oss_Errors::for_user( $response, 0, $vendor )
					: pili__( '无法连接到对象存储。请检查服务器网络、防火墙和接口地址。' )
			);
			return 0;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$err_body = wp_remote_retrieve_body( $response );
			$err_body = is_string( $err_body ) ? $err_body : '';
			$hdrs     = wp_remote_retrieve_headers( $response );
			foreach ( array( 'x-oss-err', 'x-amz-error-code', 'x-amz-error-message' ) as $hk ) {
				$hv = is_object( $hdrs ) || is_array( $hdrs ) ? $hdrs[ $hk ] : '';
				if ( is_array( $hv ) ) {
					$hv = implode( ' ', $hv );
				}
				if ( is_string( $hv ) && '' !== $hv ) {
					$err_body .= ' ' . $hv;
				}
			}
			$vendor = self::error_vendor();
			self::set_last_error(
				class_exists( 'ZapRocket_Oss_Errors', false )
					? ZapRocket_Oss_Errors::for_user( $err_body, $code, $vendor )
					: self::explain_transport_error( $err_body, $code )
			);
		}
		return $code;
	}

	/**
	 * @return string
	 */
	private static function error_vendor() {
		$s = self::get_settings();
		$id = sanitize_key( (string) ( $s['zr_oss_provider'] ?? 's3' ) );
		return $id ? $id : 's3';
	}

	/**
	 * Map vendor XML / HTTP failures to readable copy.
	 *
	 * @param string $raw  Body.
	 * @param int    $code Status.
	 * @return string
	 */
	public static function explain_transport_error( $raw, $code ) {
		if ( class_exists( 'ZapRocket_Oss_Errors', false ) ) {
			return ZapRocket_Oss_Errors::for_user( $raw, $code, self::error_vendor() );
		}
		return pili__( '对象存储请求失败。请核对配置后重试。' );
	}
}
