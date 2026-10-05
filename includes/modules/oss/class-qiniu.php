<?php
/**
 * Qiniu Kodo native upload (uploadToken), not S3.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kodo HTTP client via WordPress HTTP API.
 */
final class ZapRocket_Oss_Qiniu {

	/**
	 * @param array<string,mixed> $conn bucket, ak, sk.
	 * @param string              $local_path File.
	 * @param string              $key        Object key (UTF-8, no leading slash).
	 * @return bool
	 */
	public static function upload_file( array $conn, $local_path, $key ) {
		$key   = ltrim( str_replace( '\\', '/', (string) $key ), '/' );
		$token = self::upload_token( (string) $conn['ak'], (string) $conn['sk'], (string) $conn['bucket'], null );
		if ( '' === $token ) {
			ZapRocket_Oss_S3::set_last_error_public( pili__( '无法生成七牛上传凭证，请检查 AccessKey 和 SecretKey。' ) );
			return false;
		}
		if ( ! is_readable( $local_path ) ) {
			ZapRocket_Oss_S3::set_last_error_public( pili__( '无法读取要上传的本地文件。' ) );
			return false;
		}

		$hosts = self::upload_hosts( (string) $conn['ak'], (string) $conn['bucket'] );
		foreach ( $hosts as $host ) {
			$code = self::put_file( $host, $token, $key, $local_path );
			if ( $code >= 200 && $code < 300 ) {
				return true;
			}
		}
		$msg = ZapRocket_Oss_S3::get_last_error();
		if ( '' === $msg ) {
			ZapRocket_Oss_S3::set_last_error_public( pili__( '七牛上传失败。请核对空间名、密钥和网络。' ) );
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $conn Conn.
	 * @param string              $key  Key.
	 * @return bool
	 */
	public static function delete_object( array $conn, $key ) {
		$key = ltrim( str_replace( '\\', '/', (string) $key ), '/' );
		if ( '' === $key ) {
			return false;
		}
		$entry = self::urlsafe_b64( $conn['bucket'] . ':' . $key );
		$path  = '/delete/' . $entry;
		$code  = self::rs_post( $conn, 'https://rs.qiniu.com' . $path, $path );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Probe bucket via UC query (no S3).
	 *
	 * @param array<string,mixed> $conn Conn.
	 * @return array{success:bool,message:string}
	 */
	public static function test_connection( array $conn ) {
		$ak     = rawurlencode( (string) $conn['ak'] );
		$bucket = rawurlencode( (string) $conn['bucket'] );
		$url    = 'https://uc.qbox.me/v2/query?ak=' . $ak . '&bucket=' . $bucket;
		$res    = wp_remote_get(
			$url,
			array(
				'timeout'   => 20,
				'sslverify' => true,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array(
				'success' => false,
				'message' => class_exists( 'ZapRocket_Oss_Errors', false )
					? ZapRocket_Oss_Errors::for_user( $res, 0, 'qiniu' )
					: pili__( '无法连接到对象存储。请检查服务器网络、防火墙和接口地址。' ),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( $code >= 200 && $code < 300 && false !== strpos( $body, 'up' ) ) {
			return array(
				'success' => true,
				'message' => ZapRocket_Oss_S3::success_message(),
			);
		}
		$decoded = json_decode( $body, true );
		$err     = '';
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['error'] ) ) {
				$err = (string) $decoded['error'];
			} elseif ( ! empty( $decoded['error_code'] ) ) {
				$err = (string) $decoded['error_code'];
			}
		}
		if ( false !== stripos( $body, 'no such bucket' ) || 631 === (int) ( $decoded['code'] ?? 0 ) ) {
			return array(
				'success' => false,
				'message' => pili__( '空间不存在，请检查空间名称。' ),
			);
		}
		if ( '' !== $err ) {
			return array(
				'success' => false,
				'message' => self::explain_http_error( $err, $code ),
			);
		}
		return array(
			'success' => false,
			'message' => self::explain_http_error( $body, $code ),
		);
	}

	/**
	 * @param string      $ak     AK.
	 * @param string      $sk     SK.
	 * @param string      $bucket Bucket.
	 * @param string|null $key    Optional key scope.
	 * @return string
	 */
	public static function upload_token( $ak, $sk, $bucket, $key = null ) {
		$ak     = trim( (string) $ak );
		$sk     = (string) $sk;
		$bucket = trim( (string) $bucket );
		if ( '' === $ak || '' === $sk || '' === $bucket ) {
			return '';
		}
		$scope = $bucket;
		if ( is_string( $key ) && '' !== $key ) {
			$scope .= ':' . $key;
		}
		$policy  = wp_json_encode(
			array(
				'scope'    => $scope,
				'deadline' => time() + 3600,
			)
		);
		$encoded = self::urlsafe_b64( (string) $policy );
		$sign    = self::urlsafe_b64( hash_hmac( 'sha1', $encoded, $sk, true ) );
		return $ak . ':' . $sign . ':' . $encoded;
	}

	/**
	 * @param string $ak     AK.
	 * @param string $bucket Bucket.
	 * @return string[]
	 */
	private static function upload_hosts( $ak, $bucket ) {
		$fallback = array(
			'https://up.qiniup.com',
			'https://upload.qiniup.com',
			'https://up-z1.qiniup.com',
			'https://up-z2.qiniup.com',
			'https://up-na0.qiniup.com',
			'https://up-as0.qiniup.com',
		);
		$url      = 'https://uc.qbox.me/v2/query?ak=' . rawurlencode( $ak ) . '&bucket=' . rawurlencode( $bucket );
		$res      = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $fallback;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$out  = array();
		if ( is_array( $data ) ) {
			foreach ( array( 'up', 'src' ) as $k1 ) {
				if ( empty( $data[ $k1 ] ) || ! is_array( $data[ $k1 ] ) ) {
					continue;
				}
				$node = $data[ $k1 ];
				if ( isset( $node['up'] ) && is_array( $node['up'] ) ) {
					$node = $node['up'];
				}
				foreach ( array( 'main', 'backup', 'acc_main', 'acc_backup' ) as $list_key ) {
					if ( empty( $node[ $list_key ] ) || ! is_array( $node[ $list_key ] ) ) {
						continue;
					}
					foreach ( $node[ $list_key ] as $host ) {
						$host = preg_replace( '#^https?://#i', '', (string) $host );
						$host = is_string( $host ) ? trim( $host, '/' ) : '';
						if ( '' !== $host ) {
							$out[] = 'https://' . $host;
						}
					}
				}
			}
		}
		$out = array_values( array_unique( $out ) );
		return $out ? $out : $fallback;
	}

	/**
	 * @param string $host       https://up...
	 * @param string $token      Token.
	 * @param string $key        Key.
	 * @param string $local_path File.
	 * @return int
	 */
	private static function put_file( $host, $token, $key, $local_path ) {
		$boundary = 'zr' . wp_generate_password( 16, false, false );
		$mime     = wp_check_filetype( $local_path );
		$type     = ! empty( $mime['type'] ) ? (string) $mime['type'] : 'application/octet-stream';
		$filename = basename( str_replace( '\\', '/', $local_path ) );
		$bin      = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $bin ) {
			ZapRocket_Oss_S3::set_last_error_public( pili__( '无法读取要上传的本地文件。' ) );
			return 0;
		}
		$body  = '';
		$parts = array(
			'token' => $token,
			'key'   => $key,
		);
		foreach ( $parts as $name => $value ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
			$body .= $value . "\r\n";
		}
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
		$body .= 'Content-Type: ' . $type . "\r\n\r\n";
		$body .= $bin . "\r\n";
		$body .= '--' . $boundary . "--\r\n";

		$res = wp_remote_post(
			untrailingslashit( $host ) . '/',
			array(
				'timeout'   => 120,
				'sslverify' => true,
				'headers'   => array(
					'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'      => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			ZapRocket_Oss_S3::set_last_error_public(
				class_exists( 'ZapRocket_Oss_Errors', false )
					? ZapRocket_Oss_Errors::for_user( $res, 0, 'qiniu' )
					: pili__( '无法连接到对象存储。请检查服务器网络、防火墙和接口地址。' )
			);
			return 0;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			$err = wp_strip_all_tags( (string) wp_remote_retrieve_body( $res ) );
			ZapRocket_Oss_S3::set_last_error_public( self::explain_http_error( $err, $code ) );
		}
		return $code;
	}

	/**
	 * @param array<string,mixed> $conn Conn.
	 * @param string              $url  Full URL.
	 * @param string              $path Path starting with /.
	 * @return int
	 */
	private static function rs_post( $conn, $url, $path ) {
		$data = $path . "\n";
		$sign = self::urlsafe_b64( hash_hmac( 'sha1', $data, (string) $conn['sk'], true ) );
		$auth = 'QBox ' . $conn['ak'] . ':' . $sign;
		$res  = wp_remote_post(
			$url,
			array(
				'timeout'   => 30,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => $auth,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'      => '',
			)
		);
		if ( is_wp_error( $res ) ) {
			ZapRocket_Oss_S3::set_last_error_public(
				class_exists( 'ZapRocket_Oss_Errors', false )
					? ZapRocket_Oss_Errors::for_user( $res, 0, 'qiniu' )
					: pili__( '无法连接到对象存储。请检查服务器网络、防火墙和接口地址。' )
			);
			return 0;
		}
		return (int) wp_remote_retrieve_response_code( $res );
	}

	/**
	 * @param string $raw  Body.
	 * @param int    $code Status.
	 * @return string
	 */
	private static function explain_http_error( $raw, $code ) {
		if ( class_exists( 'ZapRocket_Oss_Errors', false ) ) {
			return ZapRocket_Oss_Errors::for_user( $raw, $code, 'qiniu' );
		}
		return pili__( '七牛请求失败。请核对空间名与密钥。' );
	}

	/**
	 * @param string $raw Raw.
	 * @return string
	 */
	private static function urlsafe_b64( $raw ) {
		return strtr( base64_encode( (string) $raw ), '+/', '-_' );
	}
}
