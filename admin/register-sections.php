<?php
/**
 * Register lazy option sections (sidebar parent + child pages).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load fields array from a section PHP file.
 *
 * @param string $file Absolute path under admin/sections/.
 * @return array<int,array<string,mixed>>
 */
function zaprocket_load_section_fields( $file ) {
	$file = (string) $file;
	if ( '' === $file || ! is_readable( $file ) ) {
		return array();
	}
	$fields = include $file;
	return is_array( $fields ) ? array_values( $fields ) : array();
}

/**
 * Register a deferred child (or top-level) section with a file loader.
 *
 * @param string               $unique Options unique.
 * @param array<string,mixed>  $meta   Must include id, title, file; optional parent/icon.
 * @return void
 */
function zaprocket_register_lazy_section( $unique, array $meta ) {
	$file = isset( $meta['file'] ) ? (string) $meta['file'] : '';
	unset( $meta['file'] );
	$meta['loader'] = static function ( $opt_unique, $section_meta ) use ( $file ) {
		unset( $opt_unique, $section_meta );
		return zaprocket_load_section_fields( $file );
	};
	pili_register_section_meta( $unique, $meta );
}

/**
 * Register overview + four parent menus with child pages.
 *
 * Parents have no fields on purpose: they only expand the native sidebar tree.
 * i18n: 侧栏 title 一律 pili__()，msgid 为简体中文。
 *
 * @return void
 */
function zaprocket_register_sections() {
	if ( ! function_exists( 'pili_register_section_meta' ) || ! class_exists( '\Pili\Core\PILI', false ) ) {
		return;
	}

	$unique = ZAPROCKET_OPTION_ID;
	$base   = ZAPROCKET_DIR . 'admin/sections/';

	zaprocket_register_lazy_section(
		$unique,
		array(
			'id'    => 'zr_overview',
			'title' => pili__( '概览' ),
			'icon'  => 'dashicons-dashboard',
			'file'  => $base . 'overview.php',
		)
	);

	$parents = array(
		array(
			'id'    => 'zr_slim',
			'title' => pili__( '站点瘦身' ),
			'icon'  => 'dashicons-hammer',
		),
		array(
			'id'    => 'zr_speed',
			'title' => pili__( '速度优化' ),
			'icon'  => 'dashicons-performance',
		),
		array(
			'id'    => 'zr_oss',
			'title' => pili__( '对象存储' ),
			'icon'  => 'dashicons-cloud',
		),
		array(
			'id'    => 'zr_database',
			'title' => pili__( '数据库' ),
			'icon'  => 'dashicons-database',
		),
	);

	foreach ( $parents as $parent ) {
		\Pili\Core\PILI::createSection( $unique, $parent );
	}

	$children = array(
		array(
			'id'     => 'zr_slim_head',
			'parent' => 'zr_slim',
			'title'  => pili__( '页头清理' ),
			'icon'   => 'dashicons-editor-removeformatting',
			'file'   => $base . 'slim-head.php',
		),
		array(
			'id'     => 'zr_slim_features',
			'parent' => 'zr_slim',
			'title'  => pili__( '功能开关' ),
			'icon'   => 'dashicons-dismiss',
			'file'   => $base . 'slim-features.php',
		),
		array(
			'id'     => 'zr_slim_fonts',
			'parent' => 'zr_slim',
			'title'  => pili__( '评论头像' ),
			'icon'   => 'dashicons-admin-users',
			'file'   => $base . 'slim-fonts.php',
		),
		array(
			'id'     => 'zr_slim_rest',
			'parent' => 'zr_slim',
			'title'  => pili__( '访客接口' ),
			'icon'   => 'dashicons-rest-api',
			'file'   => $base . 'slim-rest.php',
		),
		array(
			'id'     => 'zr_slim_heartbeat',
			'parent' => 'zr_slim',
			'title'  => pili__( '自动保存' ),
			'icon'   => 'dashicons-heart',
			'file'   => $base . 'slim-heartbeat.php',
		),
		array(
			'id'     => 'zr_speed_cdn',
			'parent' => 'zr_speed',
			'title'  => pili__( 'CDN加速' ),
			'icon'   => 'dashicons-admin-site-alt3',
			'file'   => $base . 'speed-cdn.php',
		),
		array(
			'id'     => 'zr_speed_preload',
			'parent' => 'zr_speed',
			'title'  => pili__( '资源预载' ),
			'icon'   => 'dashicons-download',
			'file'   => $base . 'speed-preload.php',
		),
		array(
			'id'     => 'zr_speed_lazy',
			'parent' => 'zr_speed',
			'title'  => pili__( '媒体优化' ),
			'icon'   => 'dashicons-format-image',
			'file'   => $base . 'speed-lazy.php',
		),
		array(
			'id'     => 'zr_speed_js',
			'parent' => 'zr_speed',
			'title'  => pili__( '文件优化' ),
			'icon'   => 'dashicons-media-code',
			'file'   => $base . 'speed-js.php',
		),
		array(
			'id'     => 'zr_speed_advanced',
			'parent' => 'zr_speed',
			'title'  => pili__( '高级规则' ),
			'icon'   => 'dashicons-filter',
			'file'   => $base . 'speed-advanced.php',
		),
		array(
			'id'     => 'zr_oss_setup',
			'parent' => 'zr_oss',
			'title'  => pili__( '存储设置' ),
			'icon'   => 'dashicons-cloud',
			'file'   => $base . 'oss.php',
		),
		array(
			'id'     => 'zr_oss_tools',
			'parent' => 'zr_oss',
			'title'  => pili__( '快捷操作' ),
			'icon'   => 'dashicons-admin-tools',
			'file'   => $base . 'oss-tools.php',
		),
		array(
			'id'     => 'zr_oss_migrate',
			'parent' => 'zr_oss',
			'title'  => pili__( '一键迁移' ),
			'icon'   => 'dashicons-migrate',
			'file'   => $base . 'oss-migrate.php',
		),
		array(
			'id'     => 'zr_db_clean',
			'parent' => 'zr_database',
			'title'  => pili__( '垃圾清理' ),
			'icon'   => 'dashicons-trash',
			'file'   => $base . 'database-clean.php',
		),
		array(
			'id'     => 'zr_db_task',
			'parent' => 'zr_database',
			'title'  => pili__( '定时清理' ),
			'icon'   => 'dashicons-clock',
			'file'   => $base . 'database-schedule.php',
		),
	);

	foreach ( $children as $child ) {
		zaprocket_register_lazy_section( $unique, $child );
	}

	zaprocket_register_lazy_section(
		$unique,
		array(
			'id'    => 'zr_logs',
			'title' => pili__( '插件运行日志' ),
			'icon'  => 'dashicons-media-text',
			'file'  => $base . 'run-log.php',
		)
	);
}
