<?php
/**
 * Upload original immediately; extra sizes via WP-Cron.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media library offload.
 */
final class ZapRocket_Oss_Upload {

	const META_KEY          = '_zr_oss_key';
	const META_PENDING      = '_zr_oss_pending';
	const META_RETRY        = '_zr_oss_sizes_retry_count';
	const META_RENAME_STEM  = '_zr_oss_rename_stem';
	const META_RENAME_ORIG  = '_zr_oss_rename_orig';
	const CRON_HOOK         = 'zaprocket_oss_upload_sizes';

	/**
	 * @return void
	 */
	public function init() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_generate_metadata' ), 20, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'on_update_metadata' ), 20, 2 );
		add_action( 'delete_attachment', array( $this, 'on_delete_attachment' ), 10, 1 );
		add_action( self::CRON_HOOK, array( $this, 'cron_upload_sizes' ), 10, 1 );
	}

	/**
	 * @param array<string,mixed> $metadata Metadata.
	 * @param int                 $attachment_id ID.
	 * @return array<string,mixed>
	 */
	public function on_generate_metadata( $metadata, $attachment_id ) {
		return $this->process_attachment( $metadata, (int) $attachment_id );
	}

	/**
	 * Media Replace / REST / sideload updates.
	 *
	 * @param array<string,mixed> $metadata Metadata.
	 * @param int                 $attachment_id ID.
	 * @return array<string,mixed>
	 */
	public function on_update_metadata( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata ) ) {
			return $metadata;
		}
		if ( doing_filter( 'wp_generate_attachment_metadata' ) ) {
			return $metadata;
		}
		return $this->process_attachment( $metadata, (int) $attachment_id );
	}

	/**
	 * @param array<string,mixed> $metadata Metadata.
	 * @param int                 $attachment_id ID.
	 * @return array<string,mixed>
	 */
	private function process_attachment( $metadata, $attachment_id ) {
		if ( ! ZapRocket_Oss_S3::is_enabled() || ! is_array( $metadata ) || $attachment_id < 1 ) {
			return $metadata;
		}
		$file = get_attached_file( $attachment_id, true );
		if ( ! $file || ! is_readable( $file ) ) {
			ZapRocket_Oss_Admin::log_error(
				$attachment_id,
				'original_missing',
				pili__( '本地原图不存在或不可读。' )
			);
			return $metadata;
		}

		$key = self::object_key_for_file( $file, $attachment_id, true );
		$ok  = $this->put_with_retry( $file, $key );
		if ( ! $ok ) {
			ZapRocket_Oss_Admin::log_error(
				$attachment_id,
				'original_put',
				ZapRocket_Oss_S3::get_last_error()
			);
			return $metadata;
		}

		update_post_meta( $attachment_id, self::META_KEY, $key );
		$extras = $this->collect_extra_files( $metadata, $file );

		if ( empty( $extras ) ) {
			if ( wp_attachment_is_image( $attachment_id ) ) {
				ZapRocket_Oss_Admin::log_error(
					$attachment_id,
					'thumbs_missing',
					pili__( '图片没有额外尺寸文件，未调度缩略图上传。' )
				);
			}
			$this->maybe_delete_local( $attachment_id, $extras );
			return $metadata;
		}

		update_post_meta( $attachment_id, self::META_PENDING, wp_json_encode( array_keys( $extras ) ) );
		update_post_meta( $attachment_id, self::META_RETRY, 0 );
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $attachment_id ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $attachment_id ) );
		}
		return $metadata;
	}

	/**
	 * @param int $attachment_id ID.
	 * @return void
	 */
	public function cron_upload_sizes( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 || ! ZapRocket_Oss_S3::is_enabled() ) {
			return;
		}
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			return;
		}
		$file = get_attached_file( $attachment_id, true );
		if ( ! $file ) {
			return;
		}
		$extras = $this->collect_extra_files( $metadata, $file );
		if ( empty( $extras ) ) {
			delete_post_meta( $attachment_id, self::META_PENDING );
			return;
		}

		$failed = array();
		foreach ( $extras as $size_name => $abs ) {
			$size_key = self::object_key_for_file( $abs, $attachment_id, false );
			$ok       = $this->put_with_retry( $abs, $size_key );
			if ( $ok ) {
				update_post_meta( $attachment_id, '_zr_oss_key_' . sanitize_key( (string) $size_name ), $size_key );
			} else {
				$failed[ $size_name ] = $abs;
				ZapRocket_Oss_Admin::log_error(
					$attachment_id,
					'size_put:' . $size_name,
					ZapRocket_Oss_S3::get_last_error()
				);
			}
		}

		$retry = (int) get_post_meta( $attachment_id, self::META_RETRY, true );
		if ( ! empty( $failed ) ) {
			$retry++;
			update_post_meta( $attachment_id, self::META_RETRY, $retry );
			update_post_meta( $attachment_id, self::META_PENDING, wp_json_encode( array_keys( $failed ) ) );
			if ( $retry < 3 ) {
				wp_schedule_single_event( time() + ( 60 * $retry ), self::CRON_HOOK, array( $attachment_id ) );
			}
			return;
		}

		delete_post_meta( $attachment_id, self::META_PENDING );
		delete_post_meta( $attachment_id, self::META_RETRY );
		$this->maybe_delete_local( $attachment_id, $extras );
	}

	/**
	 * @param int                  $attachment_id ID.
	 * @param array<string,string> $extras        Extra files.
	 * @return void
	 */
	private function maybe_delete_local( $attachment_id, $extras ) {
		if ( ! ZapRocket_Oss_S3::no_local() ) {
			return;
		}
		if ( get_post_meta( $attachment_id, self::META_PENDING, true ) ) {
			return;
		}
		$original = get_attached_file( $attachment_id, true );
		$files    = array();
		if ( $original && is_file( $original ) ) {
			$files[] = $original;
		}
		foreach ( $extras as $abs ) {
			if ( $abs && is_file( $abs ) ) {
				$files[] = $abs;
			}
		}
		$basedir = wp_get_upload_dir();
		$base    = isset( $basedir['basedir'] ) ? (string) $basedir['basedir'] : '';
		$real_base = $base ? realpath( $base ) : false;
		foreach ( array_unique( $files ) as $path ) {
			$real = realpath( $path );
			if ( ! $real ) {
				continue;
			}
			if ( $real_base && 0 !== strpos( $real, $real_base ) ) {
				continue;
			}
			wp_delete_file( $real );
		}
	}

	/**
	 * @param int $attachment_id ID.
	 * @return void
	 */
	public function on_delete_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$all           = get_post_meta( $attachment_id );
		if ( ! is_array( $all ) ) {
			return;
		}
		foreach ( $all as $meta_key => $vals ) {
			if ( 0 !== strpos( (string) $meta_key, '_zr_oss_key' ) ) {
				continue;
			}
			$val = is_array( $vals ) ? (string) reset( $vals ) : (string) $vals;
			if ( $val ) {
				$ok = ZapRocket_Oss_S3::delete_object( $val );
				if ( ! $ok && function_exists( 'zaprocket_run_log' ) ) {
					zaprocket_run_log(
						'oss',
						'error',
						pili__( '删除云上对象失败。' ),
						array(
							'code'     => 'delete_object',
							'ref_type' => 'attachment',
							'ref_id'   => (string) $attachment_id,
							'detail'   => array(
								'attachment_id' => $attachment_id,
								'message'       => ZapRocket_Oss_S3::get_last_error(),
							),
						)
					);
				}
			}
		}
	}

	/**
	 * Build object key. Same rules for media upload and one-click migrate.
	 *
	 * @param string $abs_path       Path.
	 * @param int    $attachment_id  Attachment ID.
	 * @param bool   $is_original    Original file.
	 * @return string
	 */
	public function object_key_for_file( $abs_path, $attachment_id = 0, $is_original = false ) {
		$abs_path = wp_normalize_path( (string) $abs_path );
		$rel      = $this->relative_upload_path( $abs_path, (int) $attachment_id, (bool) $is_original );
		$s        = ZapRocket_Oss_S3::get_settings();
		$cap      = class_exists( 'ZapRocket_Oss_Providers', false )
			? ZapRocket_Oss_Providers::capabilities( (string) ( $s['zr_oss_provider'] ?? 'aliyun' ) )
			: array(
				'supports_rename' => true,
			);

		$dir  = dirname( $rel );
		$file = basename( $rel );
		if ( $attachment_id > 0 && ! empty( $cap['supports_rename'] ) && ZapRocket_Options::is_on( $s['zr_oss_rename_enable'] ?? false ) ) {
			$file = $this->renamed_basename( $abs_path, (int) $attachment_id, (bool) $is_original );
		}
		$rel = ( '.' === $dir || '' === $dir ) ? $file : trailingslashit( $dir ) . $file;
		$rel = ltrim( str_replace( '\\', '/', $rel ), '/' );

		$prefix = class_exists( 'ZapRocket_Oss_Providers', false )
			? ZapRocket_Oss_Providers::resolved_object_prefix()
			: 'wp-content/uploads';
		if ( '' !== $prefix && ( $rel === $prefix || 0 === strpos( $rel, $prefix . '/' ) ) ) {
			$rel = ltrim( substr( $rel, strlen( $prefix ) ), '/' );
		}

		$blog  = is_multisite() ? (string) get_current_blog_id() : '';
		$parts = array();
		if ( '' !== $prefix ) {
			$parts[] = $prefix;
		}
		if ( '' !== $blog && '1' !== $blog ) {
			$parts[] = 'sites/' . $blog;
		}
		if ( '' !== $rel ) {
			$parts[] = $rel;
		}
		$key = implode( '/', $parts );
		if ( class_exists( 'ZapRocket_Oss_Providers', false ) ) {
			return ZapRocket_Oss_Providers::sanitize_object_key( $key );
		}
		return $key;
	}

	/**
	 * Path under uploads (year/month/file), never including the bucket prefix.
	 *
	 * @param string $abs_path      Normalized absolute path.
	 * @param int    $attachment_id Attachment ID.
	 * @param bool   $is_original   Original file.
	 * @return string
	 */
	private function relative_upload_path( $abs_path, $attachment_id, $is_original ) {
		if ( $attachment_id > 0 ) {
			$attached = ltrim( str_replace( '\\', '/', (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) ), '/' );
			if ( '' !== $attached && false === strpos( $attached, '..' ) && ! preg_match( '#^(https?:)?//#i', $attached ) ) {
				if ( $is_original ) {
					return $attached;
				}
				$dir  = dirname( $attached );
				$file = basename( $abs_path );
				return ( '.' === $dir || '' === $dir ) ? $file : $dir . '/' . $file;
			}
		}
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		if ( $base && 0 === strpos( $abs_path, $base ) ) {
			return ltrim( substr( $abs_path, strlen( $base ) ), '/' );
		}
		$rel = ltrim( $abs_path, '/' );
		$pos = strpos( $rel, 'wp-content/uploads/' );
		if ( false !== $pos ) {
			return substr( $rel, $pos + strlen( 'wp-content/uploads/' ) );
		}
		return ltrim( str_replace( '\\', '/', $rel ), '/' );
	}

	/**
	 * @param string $abs_path Path.
	 * @param int    $attachment_id ID.
	 * @param bool   $is_original Original.
	 * @return string Basename including extension.
	 */
	private function renamed_basename( $abs_path, $attachment_id, $is_original ) {
		$orig_file = get_attached_file( $attachment_id, true );
		if ( ! $orig_file ) {
			$orig_file = $abs_path;
		}
		$orig_name = pathinfo( $orig_file, PATHINFO_FILENAME );
		$stem      = (string) get_post_meta( $attachment_id, self::META_RENAME_STEM, true );
		$stored    = (string) get_post_meta( $attachment_id, self::META_RENAME_ORIG, true );
		if ( '' === $stem ) {
			$stem = $this->make_rename_stem( $orig_file, $orig_name );
			update_post_meta( $attachment_id, self::META_RENAME_STEM, $stem );
			update_post_meta( $attachment_id, self::META_RENAME_ORIG, $orig_name );
			$stored = $orig_name;
		}
		$base = basename( str_replace( '\\', '/', $abs_path ) );
		$from = '' !== $stored ? $stored : $orig_name;
		if ( $is_original || $base === $from || 0 === strpos( $base, $from ) ) {
			$rest = ( $is_original || $base === pathinfo( $orig_file, PATHINFO_BASENAME ) )
				? '.' . ltrim( (string) pathinfo( $abs_path, PATHINFO_EXTENSION ), '.' )
				: substr( $base, strlen( $from ) );
			if ( '' === $rest || false === $rest ) {
				$ext = pathinfo( $abs_path, PATHINFO_EXTENSION );
				$rest = $ext ? '.' . $ext : '';
			}
			return $stem . $rest;
		}
		$ext = pathinfo( $abs_path, PATHINFO_EXTENSION );
		return $stem . '-' . $this->sanitize_name_part( pathinfo( $abs_path, PATHINFO_FILENAME ) ) . ( $ext ? '.' . $ext : '' );
	}

	/**
	 * @param string $orig_file Original path.
	 * @param string $orig_name Filename without ext.
	 * @return string
	 */
	private function make_rename_stem( $orig_file, $orig_name ) {
		$s    = ZapRocket_Oss_S3::get_settings();
		$rule = sanitize_key( (string) ( $s['zr_oss_rename_rule'] ?? 'md5' ) );
		if ( 'timestamp' === $rule ) {
			return gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
		}
		if ( 'sanitize' === $rule ) {
			$clean = $this->sanitize_name_part( $orig_name );
			return '' !== $clean ? $clean : substr( md5( $orig_name ), 0, 12 );
		}
		if ( 'prefix' === $rule ) {
			$pre   = $this->sanitize_name_part( (string) ( $s['zr_oss_rename_prefix'] ?? '' ) );
			$clean = $this->sanitize_name_part( $orig_name );
			if ( '' === $clean ) {
				$clean = substr( md5( $orig_name ), 0, 8 );
			}
			return '' !== $pre ? $pre . '-' . $clean : $clean;
		}
		if ( is_readable( $orig_file ) ) {
			$hash = md5_file( $orig_file );
			if ( is_string( $hash ) && '' !== $hash ) {
				return $hash;
			}
		}
		return md5( $orig_name . '|' . (string) filesize( $orig_file ) );
	}

	/**
	 * @param string $name Name.
	 * @return string
	 */
	private function sanitize_name_part( $name ) {
		$name = strtolower( (string) $name );
		$name = preg_replace( '/[^\x20-\x7E]/', '', $name );
		$name = preg_replace( '/[^a-z0-9]+/', '-', (string) $name );
		$name = trim( (string) $name, '-' );
		return is_string( $name ) ? $name : '';
	}

	/**
	 * @param array<string,mixed> $metadata Meta.
	 * @param string              $original Original path.
	 * @return array<string,string>
	 */
	private function collect_extra_files( $metadata, $original ) {
		$out  = array();
		$dir  = trailingslashit( dirname( $original ) );
		$seen = array( wp_normalize_path( $original ) );

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $name => $info ) {
				if ( empty( $info['file'] ) ) {
					continue;
				}
				$abs = $dir . $info['file'];
				$n   = wp_normalize_path( $abs );
				if ( in_array( $n, $seen, true ) || ! is_readable( $abs ) ) {
					continue;
				}
				$seen[]       = $n;
				$out[ $name ] = $abs;
			}
		}

		foreach ( array( 'original_image' ) as $extra ) {
			if ( empty( $metadata[ $extra ] ) || ! is_string( $metadata[ $extra ] ) ) {
				continue;
			}
			$abs = $dir . basename( $metadata[ $extra ] );
			$n   = wp_normalize_path( $abs );
			if ( in_array( $n, $seen, true ) || ! is_readable( $abs ) ) {
				continue;
			}
			$seen[]        = $n;
			$out[ $extra ] = $abs;
		}

		$siblings = glob( preg_replace( '/\.[^.]+$/', '', $original ) . '-*.{webp,avif,WEBP,AVIF}', GLOB_BRACE );
		if ( is_array( $siblings ) ) {
			foreach ( $siblings as $abs ) {
				$n = wp_normalize_path( $abs );
				if ( in_array( $n, $seen, true ) || ! is_readable( $abs ) ) {
					continue;
				}
				$seen[] = $n;
				$out[ 'extra_' . md5( $n ) ] = $abs;
			}
		}

		return $out;
	}

	/**
	 * @param string $local Local file.
	 * @param string $key   Object key.
	 * @return bool
	 */
	private function put_with_retry( $local, $key ) {
		$attempt = 0;
		while ( $attempt < 3 ) {
			if ( ZapRocket_Oss_S3::upload_file( $local, $key ) ) {
				return true;
			}
			$attempt++;
			if ( $attempt < 3 ) {
				usleep( 200000 * $attempt );
			}
		}
		return false;
	}
}
