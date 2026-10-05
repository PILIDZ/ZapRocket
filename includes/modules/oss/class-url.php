<?php
/**
 * Rewrite attachment URLs to object storage.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public object URLs.
 */
final class ZapRocket_Oss_Url {

	/**
	 * @return void
	 */
	public function init() {
		add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_attachment_image_src' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 10, 5 );
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_oss_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}
		$s      = ZapRocket_Oss_S3::get_settings();
		$custom = untrailingslashit( trim( (string) ( $s['zr_oss_custom_domain'] ?? '' ) ) );
		if ( '' !== $custom ) {
			$host = wp_parse_url( 0 === strpos( $custom, 'http' ) ? $custom : 'https://' . $custom, PHP_URL_HOST );
			if ( is_string( $host ) && $host && false !== stripos( $url, $host ) ) {
				return true;
			}
		}
		$endpoint = untrailingslashit( trim( (string) ( $s['zr_oss_endpoint'] ?? '' ) ) );
		if ( '' !== $endpoint ) {
			$host = wp_parse_url( 0 === strpos( $endpoint, 'http' ) ? $endpoint : 'https://' . $endpoint, PHP_URL_HOST );
			if ( is_string( $host ) && $host && false !== stripos( $url, $host ) ) {
				return true;
			}
		}
		$bucket = trim( (string) ( $s['zr_oss_bucket'] ?? '' ) );
		return ( '' !== $bucket && false !== stripos( $url, $bucket . '.s3.' ) );
	}

	/**
	 * @param string $url URL.
	 * @param int    $attachment_id ID.
	 * @return string
	 */
	public function rewrite_attachment_url( $url, $attachment_id ) {
		if ( ! ZapRocket_Oss_S3::is_enabled() || ! self::has_public_base() ) {
			return $url;
		}
		$key = get_post_meta( (int) $attachment_id, ZapRocket_Oss_Upload::META_KEY, true );
		if ( $key ) {
			return self::get_object_url( (string) $key );
		}
		return $url;
	}

	/**
	 * @param array|false  $image Image.
	 * @param int          $attachment_id ID.
	 * @param string|int[] $size Size.
	 * @param bool         $icon Icon.
	 * @return array|false
	 */
	public function rewrite_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		unset( $icon );
		if ( ! ZapRocket_Oss_S3::is_enabled() || ! self::has_public_base() || ! is_array( $image ) || empty( $image[0] ) ) {
			return $image;
		}
		$key = get_post_meta( (int) $attachment_id, ZapRocket_Oss_Upload::META_KEY, true );
		if ( ! $key ) {
			return $image;
		}
		$size_key     = is_array( $size ) ? ( $size[0] . 'x' . $size[1] ) : (string) $size;
		$key_for_size = get_post_meta( (int) $attachment_id, '_zr_oss_key_' . sanitize_key( $size_key ), true );
		$image[0]     = self::get_object_url( $key_for_size ? (string) $key_for_size : (string) $key );
		return $image;
	}

	/**
	 * @param array<int,array<string,mixed>> $sources Sources.
	 * @param array<int,int>                 $size_array Size.
	 * @param string                         $image_src Src.
	 * @param array<string,mixed>            $image_meta Meta.
	 * @param int                            $attachment_id ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function rewrite_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		unset( $size_array, $image_src, $image_meta );
		if ( ! ZapRocket_Oss_S3::is_enabled() || ! self::has_public_base() || ! is_array( $sources ) ) {
			return $sources;
		}
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 || ! get_post_meta( $attachment_id, ZapRocket_Oss_Upload::META_KEY, true ) ) {
			return $sources;
		}
		foreach ( $sources as $w => $row ) {
			if ( empty( $row['url'] ) ) {
				continue;
			}
			$path = wp_parse_url( (string) $row['url'], PHP_URL_PATH );
			$file = is_string( $path ) ? basename( $path ) : '';
			$key  = $this->find_key_for_basename( $attachment_id, $file );
			if ( $key ) {
				$sources[ $w ]['url'] = self::get_object_url( $key );
			}
		}
		return $sources;
	}

	/**
	 * @param int    $attachment_id ID.
	 * @param string $basename      File name.
	 * @return string
	 */
	private function find_key_for_basename( $attachment_id, $basename ) {
		if ( '' === $basename ) {
			return '';
		}
		$all = get_post_meta( (int) $attachment_id );
		if ( ! is_array( $all ) ) {
			return '';
		}
		$stem = (string) get_post_meta( $attachment_id, ZapRocket_Oss_Upload::META_RENAME_STEM, true );
		$orig = (string) get_post_meta( $attachment_id, ZapRocket_Oss_Upload::META_RENAME_ORIG, true );
		$want = $basename;
		if ( '' !== $stem && '' !== $orig && 0 === strpos( $basename, $orig ) ) {
			$want = $stem . substr( $basename, strlen( $orig ) );
		}
		$want_enc = class_exists( 'ZapRocket_Oss_Providers', false )
			? ZapRocket_Oss_Providers::encode_segment( $want )
			: $want;
		foreach ( $all as $meta_key => $vals ) {
			if ( 0 !== strpos( (string) $meta_key, '_zr_oss_key' ) ) {
				continue;
			}
			$val = is_array( $vals ) ? (string) reset( $vals ) : (string) $vals;
			$bn  = $val ? basename( str_replace( '\\', '/', $val ) ) : '';
			if ( ! $bn ) {
				continue;
			}
			if ( $bn === $basename || $bn === $want || $bn === $want_enc || rawurldecode( $bn ) === $want ) {
				return $val;
			}
		}
		return '';
	}

	/**
	 * Public media URLs require a bound custom / CDN domain.
	 *
	 * @return bool
	 */
	public static function has_public_base() {
		$s      = ZapRocket_Oss_S3::get_settings();
		$custom = untrailingslashit( trim( (string) ( $s['zr_oss_custom_domain'] ?? '' ) ) );
		return '' !== $custom;
	}

	/**
	 * Custom domain only (never S3 / API hosts).
	 *
	 * @param string $key Object key.
	 * @return string
	 */
	public static function get_object_url( $key ) {
		$s   = ZapRocket_Oss_S3::get_settings();
		$key = ltrim( str_replace( '\\', '/', (string) $key ), '/' );
		if ( class_exists( 'ZapRocket_Oss_Providers', false ) ) {
			$key = ZapRocket_Oss_Providers::encode_key_path( $key );
		}

		$custom = untrailingslashit( trim( (string) ( $s['zr_oss_custom_domain'] ?? '' ) ) );
		if ( '' === $custom ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $custom ) ) {
			$custom = 'https://' . ltrim( $custom, '/' );
		}
		return $custom . '/' . $key;
	}
}
