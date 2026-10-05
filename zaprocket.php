<?php
/**
 * Plugin Name: ZapRocket-闪电WP性能
 * Plugin URI:  https://www.pilipost.net/products/zaprocket/
 * Description: 针对 WordPress 的性能优化：站点瘦身、CDN / 预载 / 懒加载 / 脚本、可选整页缓存、对象存储（阿里云、腾讯云、R2、七牛与兼容 S3）、数据库清理。
 * Version:     1.2.1
 * Author:      霹雳设计
 * Author URI:  https://gitcode.com/pilidz
 * Text Domain: zaprocket-wp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZAPROCKET_VERSION', '1.2.1' );
define( 'ZAPROCKET_FILE', __FILE__ );
define( 'ZAPROCKET_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZAPROCKET_URL', plugin_dir_url( __FILE__ ) );
define( 'ZAPROCKET_OPTION_ID', 'zaprocket_options' );
define( 'ZAPROCKET_DB_LOG_SLUG', 'zaprocket_opt_log' );

/**
 * Locate pili-core bootstrap directory.
 *
 * @return string Trailing-slash path, or empty string.
 */
function zaprocket_find_core_dir() {
	if ( defined( 'PILI_CORE_DIR' ) && is_string( PILI_CORE_DIR ) && '' !== PILI_CORE_DIR ) {
		$dir = rtrim( str_replace( '\\', '/', PILI_CORE_DIR ), '/' ) . '/';
		if ( is_readable( $dir . 'bootstrap.php' ) ) {
			return $dir;
		}
	}

	$bundled = rtrim( str_replace( '\\', '/', ZAPROCKET_DIR ), '/' ) . '/pili-core/';
	if ( is_readable( $bundled . 'bootstrap.php' ) ) {
		return $bundled;
	}

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		$c = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );
		$candidates = array(
			$c . '/packages/pili-core/',
			$c . '/plugins/pili-core/',
		);
		foreach ( $candidates as $dir ) {
			$dir = rtrim( $dir, '/' ) . '/';
			if ( is_readable( $dir . 'bootstrap.php' ) ) {
				return $dir;
			}
		}
	}

	return '';
}

$zaprocket_core = zaprocket_find_core_dir();
if ( '' === $zaprocket_core ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			// i18n: 框架缺失时 pili__() 不可用，直接走 zaprocket 文本域。
			load_plugin_textdomain( 'zaprocket-wp', false, dirname( plugin_basename( ZAPROCKET_FILE ) ) . '/languages' );
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( '闪电WP性能:', 'zaprocket-wp' ) . '</strong> ';
			echo wp_kses(
				sprintf(
					/* translators: 1: bootstrap file, 2: framework directory */
					__( '找不到 %1$s。请确认插件内已包含 %2$s 目录。', 'zaprocket-wp' ),
					'<code>pili-core/bootstrap.php</code>',
					'<code>pili-core</code>'
				),
				array( 'code' => array() )
			);
			echo '</p></div>';
		}
	);
	return;
}

if ( ! defined( 'PILI_CORE_DIR' ) ) {
	define( 'PILI_CORE_DIR', $zaprocket_core );
}

if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 80100 ) {
	$zaprocket_aws = ZAPROCKET_DIR . 'vendor/autoload.php';
	if ( is_readable( $zaprocket_aws ) ) {
		require_once $zaprocket_aws;
	}
}

require_once $zaprocket_core . 'bootstrap.php';

if ( ! defined( 'PILI_CORE_URL' ) ) {
	$url = function_exists( 'pili_locate_core_url' ) ? pili_locate_core_url( $zaprocket_core ) : '';
	if ( '' !== $url ) {
		define( 'PILI_CORE_URL', $url );
	}
}

require_once ZAPROCKET_DIR . 'includes/class-context.php';
require_once ZAPROCKET_DIR . 'includes/class-i18n.php';
require_once ZAPROCKET_DIR . 'includes/class-options.php';
require_once ZAPROCKET_DIR . 'includes/modules/class-module.php';
require_once ZAPROCKET_DIR . 'includes/db/class-optimize-log.php';
require_once ZAPROCKET_DIR . 'includes/db/class-run-log.php';
require_once ZAPROCKET_DIR . 'includes/class-plugin.php';
require_once ZAPROCKET_DIR . 'includes/loader.php';

register_activation_hook( __FILE__, array( 'ZapRocket_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZapRocket_Plugin', 'deactivate' ) );

/**
 * Boot plugin after WordPress plugins are loaded.
 *
 * @return void
 */
function zaprocket_boot() {
	ZapRocket_Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'zaprocket_boot', 5 );
