<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Remix_Icons {

		const TRANSIENT_PREFIX = 'pili_remixicon_names_';

		/** 插件内本地 Remix Icon 相对路径（相对 XUN 根目录）。 */
		const PLUGIN_CSS_REL = 'assets/vendor/remixicon/remixicon.css';

		/**
		 * 非图标的尺寸/工具类（.ri-lg:before 等），解析时需排除。
		 *
		 * @return string[]
		 */
		private static function utility_icon_classes() {
			return array(
				'ri-lg',
				'ri-xl',
				'ri-xxs',
				'ri-xs',
				'ri-sm',
				'ri-1x',
				'ri-2x',
				'ri-3x',
				'ri-4x',
				'ri-5x',
				'ri-6x',
				'ri-7x',
				'ri-8x',
				'ri-9x',
				'ri-10x',
				'ri-fw',
			);
		}

		/**
		 * 插件内本地 remixicon.css 绝对路径。
		 *
		 * @return string
		 */
		public static function get_plugin_css_path() {
			$dir = '';
			if ( class_exists( __NAMESPACE__ . '\\PILI_Setup' ) && ! empty( PILI_Setup::$dir ) ) {
				$dir = (string) PILI_Setup::$dir;
			} elseif ( defined( 'PILI_CORE_DIR' ) ) {
				$dir = (string) PILI_CORE_DIR;
			}
			if ( '' === $dir ) {
				return '';
			}
			return trailingslashit( $dir ) . self::PLUGIN_CSS_REL;
		}

		/**
		 * 插件内本地 remixicon.css URL。
		 *
		 * @return string
		 */
		public static function get_plugin_css_uri() {
			$url = '';
			if ( class_exists( __NAMESPACE__ . '\\PILI_Setup' ) && ! empty( PILI_Setup::$url ) ) {
				$url = (string) PILI_Setup::$url;
			} elseif ( defined( 'PILI_CORE_URL' ) ) {
				$url = (string) PILI_CORE_URL;
			}
			if ( '' === $url ) {
				return '';
			}
			return trailingslashit( $url ) . self::PLUGIN_CSS_REL;
		}

		/**
		 * remixicon.css 在主题中的相对路径（与 functions.php 中 CX_fONTICON 一致）。
		 *
		 * @return string
		 */
		public static function get_css_relative_path() {
			if ( file_exists( get_theme_file_path( 'assets/vendor/remixicon/remixicon.css' ) ) ) {
				return 'assets/vendor/remixicon/remixicon.css';
			}
			return 'assets/libs/remixicon/remixicon.css';
		}

		/**
		 * 优先插件本地，其次主题本地。
		 *
		 * @return string 绝对文件路径。
		 */
		public static function get_css_path() {
			$plugin = self::get_plugin_css_path();
			if ( $plugin && is_readable( $plugin ) ) {
				return $plugin;
			}
			return get_theme_file_path( self::get_css_relative_path() );
		}

		/**
		 * 优先插件本地，其次主题常量 / 主题目录。
		 *
		 * @return string 可访问 URL。
		 */
		public static function get_css_uri() {
			$plugin_path = self::get_plugin_css_path();
			$plugin_uri  = self::get_plugin_css_uri();
			if ( $plugin_uri && $plugin_path && is_readable( $plugin_path ) ) {
				return $plugin_uri;
			}
			if ( defined( 'CX_fONTICON' ) && CX_fONTICON ) {
				return CX_fONTICON;
			}
			return get_template_directory_uri() . '/' . self::get_css_relative_path();
		}

		/**
		 * 是否已有可用的 Remix 样式来源（仅本地：插件或主题）。
		 *
		 * @return bool
		 */
		public static function is_stylesheet_ready() {
			if ( is_readable( self::get_css_path() ) ) {
				return true;
			}
			if ( defined( 'CX_fONTICON' ) && CX_fONTICON ) {
				return true;
			}
			return (bool) wp_style_is( 'pilidoc-remixicon', 'enqueued' )
				|| (bool) wp_style_is( 'pilidoc-remixicon', 'registered' );
		}

		/**
		 * 注册并加载 Remix Icon 样式（供后台字段等使用）。仅本地，不使用 CDN。
		 *
		 * @param string $handle 样式 handle。
		 */
		public static function enqueue_style( $handle = 'pilidoc-remixicon' ) {
			$path = self::get_css_path();
			$uri  = self::get_css_uri();
			if ( ! $uri || ! is_readable( $path ) ) {
				return;
			}
			$ver = (string) filemtime( $path );
			wp_register_style( $handle, $uri, array(), $ver );
			wp_enqueue_style( $handle );
		}

		/**
		 * 从 CSS 解析全部图标类名（.ri-xxx:before）。
		 *
		 * @return string[]
		 */
		private static function parse_icon_names_from_css( $css ) {
			if ( ! is_string( $css ) || $css === '' ) {
				return array();
			}
			if ( ! preg_match_all( '/\.(ri-[a-z0-9-]+):before\b/', $css, $m ) ) {
				return array();
			}
			$deny = array_flip( self::utility_icon_classes() );
			$out  = array();
			foreach ( $m[1] as $cls ) {
				if ( isset( $deny[ $cls ] ) ) {
					continue;
				}
				$out[] = $cls;
			}
			$out = array_values( array_unique( $out ) );
			sort( $out, SORT_STRING );
			return $out;
		}

		/**
		 * 全部可用 Remix 图标类名（含 ri- 前缀）。
		 *
		 * @return string[]
		 */
		public static function get_available_icons() {
			$path = self::get_css_path();
			if ( ! is_readable( $path ) ) {
				return array();
			}
			$mtime = (string) filemtime( $path );
			$key   = self::TRANSIENT_PREFIX . md5( $mtime );
			$cached = get_transient( $key );
			if ( is_array( $cached ) && $cached !== array() ) {
				return $cached;
			}
			$css   = file_get_contents( $path );
			$names = self::parse_icon_names_from_css( $css );
			set_transient( $key, $names, WEEK_IN_SECONDS );
			return $names;
		}

		/**
		 * @param string $name 完整类名，如 ri-home-line。
		 */
		public static function icon_exists( $name ) {
			if ( ! is_string( $name ) || $name === '' ) {
				return false;
			}
			if ( ! preg_match( '/^ri-[a-z0-9-]+$/', $name ) ) {
				return false;
			}
			if ( ! is_readable( self::get_css_path() ) ) {
				return false;
			}
			$all = self::get_available_icons();
			return in_array( $name, $all, true );
		}

		/**
		 * 输出 &lt;i class="ri-..."&gt;（Remix Icon 字体）。
		 *
		 * @param string $name       如 ri-home-line。
		 * @param array  $attributes class、size（像素数字字符串）、color、title。
		 * @return string
		 */
		public static function get_icon( $name, $attributes = array() ) {
			if ( empty( $name ) || ! is_string( $name ) ) {
				return self::get_fallback_icon();
			}
			$defaults = array(
				'class' => '',
				'size'  => '24',
				'color' => '',
				'title' => '',
			);
			$attributes = wp_parse_args( $attributes, $defaults );
			if ( ! self::icon_exists( $name ) ) {
				return self::get_fallback_icon();
			}
			$classes = trim( $name . ' ' . $attributes['class'] . ' pili-ri-icon' );
			$style   = '';
			if ( ! empty( $attributes['color'] ) ) {
				$style .= 'color:' . esc_attr( $attributes['color'] ) . ';';
			}
			if ( ! empty( $attributes['size'] ) ) {
				$style .= 'font-size:' . esc_attr( (string) $attributes['size'] ) . 'px;';
			}
			$style_attr = $style !== '' ? ' style="' . esc_attr( $style ) . '"' : '';
			$title_attr = ! empty( $attributes['title'] ) ? ' title="' . esc_attr( $attributes['title'] ) . '"' : '';
			return '<i class="' . esc_attr( $classes ) . '"' . $style_attr . $title_attr . ' aria-hidden="true"></i>';
		}

		/**
		 * @return string
		 */
		private static function get_fallback_icon() {
			return '<i class="ri-error-warning-line pili-ri-icon" style="color:#ca8a04;font-size:24px;" aria-hidden="true"></i>';
		}
	}
