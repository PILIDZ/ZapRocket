<?php
/**
 * Map vendor API failures to user-facing i18n copy.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User-readable OSS errors; raw payloads go to PHP error log only.
 */
final class ZapRocket_Oss_Errors {

	/**
	 * @param mixed  $raw    Body or WP_Error.
	 * @param int    $code   HTTP status.
	 * @param string $vendor aliyun|tencent|r2|qiniu|s3.
	 * @return string
	 */
	public static function for_user( $raw, $code, $vendor = 's3' ) {
		$vendor = sanitize_key( (string) $vendor );
		$text   = self::stringify( $raw );
		self::log_raw( $text, (int) $code, $vendor );
		$hay    = strtolower( $text );
		$code   = (int) $code;

		if ( is_wp_error( $raw ) ) {
			return pili__( '无法连接到对象存储。请检查服务器网络、防火墙和接口地址。' );
		}

		$mapped = self::match_common( $hay, $code, $vendor );
		if ( '' !== $mapped ) {
			return $mapped;
		}

		if ( $code >= 500 && $code < 600 ) {
			return pili__( '对象存储服务暂时不可用，请稍后重试。' );
		}
		if ( $code > 0 ) {
			return sprintf(
				/* translators: %d: HTTP status */
				pili__( '对象存储请求失败（HTTP %d）。请核对密钥、桶名和区域。' ),
				$code
			);
		}
		return pili__( '对象存储请求失败。请核对配置后重试。' );
	}

	/**
	 * @param mixed $raw Raw.
	 * @return string
	 */
	private static function stringify( $raw ) {
		if ( is_wp_error( $raw ) ) {
			return $raw->get_error_message();
		}
		$text = is_string( $raw ) ? $raw : '';
		if ( '' === $text ) {
			return '';
		}
		if ( false === strpos( $text, '<' ) && preg_match( '/^[A-Za-z0-9+\/]+=*$/', trim( $text ) ) && strlen( trim( $text ) ) >= 40 ) {
			$decoded = base64_decode( trim( $text ), true );
			if ( is_string( $decoded ) && false !== strpos( $decoded, '<' ) ) {
				$text = $decoded;
			}
		}
		if ( preg_match_all( '/PD94bWwg[A-Za-z0-9+\/=]+/', $text, $chunks ) ) {
			foreach ( $chunks[0] as $chunk ) {
				$decoded = base64_decode( $chunk, true );
				if ( is_string( $decoded ) && '' !== $decoded ) {
					$text .= ' ' . $decoded;
				}
			}
		}
		return $text;
	}

	/**
	 * @param string $text   Body.
	 * @param int    $code   Status.
	 * @param string $vendor Vendor.
	 * @return void
	 */
	private static function log_raw( $text, $code, $vendor ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $text && $code < 1 ) {
			return;
		}
		if ( strlen( $text ) > 500 ) {
			$text = substr( $text, 0, 500 ) . '…';
		}
		if ( function_exists( 'error_log' ) ) {
			error_log( sprintf( 'zaprocket-oss [%s] HTTP %d %s', $vendor, $code, $text ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * @param string $hay    Lowercased body.
	 * @param int    $code   Status.
	 * @param string $vendor Vendor.
	 * @return string
	 */
	private static function match_common( $hay, $code, $vendor ) {
		if ( false !== strpos( $hay, 'curl error' ) || false !== strpos( $hay, 'cURL error' ) || false !== strpos( $hay, 'timed out' ) || false !== strpos( $hay, 'timeout' ) ) {
			return pili__( '连接对象存储超时。请检查服务器网络和防火墙。' );
		}
		if ( false !== strpos( $hay, 'ssl' ) && ( false !== strpos( $hay, 'certificate' ) || false !== strpos( $hay, 'peer' ) ) ) {
			return pili__( 'HTTPS 证书校验失败。请检查服务器 CA 证书与接口地址。' );
		}
		if ( 'qiniu' === $vendor && ( 612 === $code || false !== strpos( $hay, '"code":612' ) || false !== strpos( $hay, '"error":"no such bucket"' ) ) ) {
			return pili__( '存储空间不存在。' );
		}
		if ( false !== strpos( $hay, 'nosuchbucket' ) || false !== strpos( $hay, 'no such bucket' ) || false !== strpos( $hay, '"code":631' ) || false !== strpos( $hay, 'incorrectbucket' ) ) {
			return pili__( '存储桶或空间不存在。请检查名称、APPID 和区域。' );
		}
		if ( false !== strpos( $hay, 'nosuchkey' ) || false !== strpos( $hay, 'no such key' ) ) {
			return pili__( '对象不存在。请检查对象路径。' );
		}

		$xml_code = self::xml_error_code( $hay );
		$auth     = self::map_auth_error( $hay, $xml_code, $vendor );
		if ( '' !== $auth ) {
			return $auth;
		}

		if ( false !== strpos( $hay, 'requesttimetooskewed' ) || false !== strpos( $hay, 'requesttimetooskewed' ) || false !== strpos( $hay, 'expiredtoken' ) ) {
			return pili__( '服务器时间与对象存储相差过大，请把系统时间校准到 UTC。' );
		}
		if ( false !== strpos( $hay, 'invalidbucketname' ) || false !== strpos( $hay, 'invalidargument' ) || false !== strpos( $hay, 'malformed' ) ) {
			return pili__( '参数不正确。请检查桶名、区域和对象路径格式。' );
		}
		if ( false !== strpos( $hay, 'entitytoolarge' ) || false !== strpos( $hay, 'maxmessagesize' ) ) {
			return pili__( '文件过大，对象存储拒绝接收。' );
		}
		if ( false !== strpos( $hay, 'slowdown' ) || 503 === $code ) {
			return pili__( '请求过于频繁，对象存储已限流，请稍后重试。' );
		}
		if ( false !== strpos( $hay, 'accessdenied' ) || false !== strpos( $hay, 'forbidden' ) || 'accessdenied' === $xml_code ) {
			return pili__( '权限不足。请检查密钥权限、桶 ACL，以及区域是否与桶一致。' );
		}
		if ( 401 === $code ) {
			return pili__( '鉴权失败。请核对 AccessKey 和 SecretKey。' );
		}
		if ( 404 === $code ) {
			return pili__( '目标不存在。请检查桶名、区域和对象路径。' );
		}
		if ( 'qiniu' === $vendor ) {
			if ( false !== strpos( $hay, 'unmatched token' ) || false !== strpos( $hay, 'token not specified' ) ) {
				return pili__( '七牛上传凭证无效。请核对 AccessKey 和 SecretKey。' );
			}
		}
		return '';
	}

	/**
	 * @param string $hay Lowercased body.
	 * @return string
	 */
	private static function xml_error_code( $hay ) {
		if ( preg_match( '/<code>\s*([^<]+)\s*<\/code>/', $hay, $m ) ) {
			return strtolower( trim( (string) $m[1] ) );
		}
		return '';
	}

	/**
	 * Split AccessKey / signature / permission. Do not treat HTTP 403 alone as ACL deny.
	 *
	 * @param string $hay      Lowercased body.
	 * @param string $xml_code XML <Code>.
	 * @param string $vendor   Vendor.
	 * @return string
	 */
	private static function map_auth_error( $hay, $xml_code, $vendor ) {
		unset( $vendor );
		$ak = array(
			'invalidaccesskeyid',
			'invalidaccesskeyid.notfound',
			'invalidaccesskey',
			'unknowndevice',
			'invalidsecuritytoken',
		);
		if ( in_array( $xml_code, $ak, true ) || false !== strpos( $hay, 'invalidaccesskeyid' ) || false !== strpos( $hay, 'access key id you provided does not exist' ) ) {
			return pili__( 'AccessKey 不正确，请核对访问密钥 ID。' );
		}

		$sig = array(
			'signaturedoesnotmatch',
			'signaturenotmatch',
			'invalidsignature',
			'authorizationqueryparameterserror',
		);
		if ( in_array( $xml_code, $sig, true ) || false !== strpos( $hay, 'signaturedoesnotmatch' ) || false !== strpos( $hay, 'invalidsignature' ) || false !== strpos( $hay, 'bad token' ) || false !== strpos( $hay, 'the request signature we calculated' ) ) {
			return pili__( '签名不匹配。请核对 SecretKey、区域，并校准服务器 UTC 时间。' );
		}

		return '';
	}
}
