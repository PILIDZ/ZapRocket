<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Field: content
 *
 * 用于在配置页中渲染静态/动态 HTML 内容，不参与数据存储。
 * 可选 variant 包裹为带图标与边框的说明块（见 assets/css/fields/content.css）。
 * 可选 dismissible：右上角关闭，记忆在 localStorage，可一键恢复。
 */
	class PILI_Field_content extends PILI_Fields {

		/**
		 * 说明块语义 variant：说明 / 警示 / 警告 / 危险。
		 *
		 * @return array<string, array{modifier:string,icon:string,label:string,color:string}>
		 */
		public static function variant_registry() {
			return array(
				'info'    => array(
					'modifier' => 'pili-content-callout--info',
					'icon'     => 'ri-information-line',
					'label' => pili__( '说明' ),
					'color'    => '#1d4ed8',
				),
				'caution' => array(
					'modifier' => 'pili-content-callout--caution',
					'icon'     => 'ri-error-warning-line',
					'label' => pili__( '警示' ),
					'color'    => '#b45309',
				),
				'alert'   => array(
					'modifier' => 'pili-content-callout--alert',
					'icon'     => 'ri-alarm-warning-line',
					'label' => pili__( '警告' ),
					'color'    => '#c2410c',
				),
				'danger'  => array(
					'modifier' => 'pili-content-callout--danger',
					'icon'     => 'ri-shield-cross-line',
					'label' => pili__( '危险' ),
					'color'    => '#b91c1c',
				),
			);
		}

		/**
		 * @param string $variant 字段 variant 或历史别名。
		 * @return string info|caution|alert|danger
		 */
		public static function normalize_variant( $variant ) {
			$key = sanitize_key( (string) $variant );
			$aliases = array(
				'warning' => 'caution',
				'neutral' => 'info',
				'success' => 'info',
			);
			if ( isset( $aliases[ $key ] ) ) {
				$key = $aliases[ $key ];
			}
			$registry = self::variant_registry();
			return isset( $registry[ $key ] ) ? $key : 'info';
		}

		/**
		 * @param string $variant info|caution|alert|danger
		 * @return string BEM 修饰类名。
		 */
		public static function variant_modifier_class( $variant ) {
			$key  = self::normalize_variant( $variant );
			$meta = self::variant_registry()[ $key ];
			return $meta['modifier'];
		}

		/**
		 * Remix 字体不可用时的内联 SVG（不依赖主题 / CDN）。
		 *
		 * @param string $variant info|caution|alert|danger
		 * @param string $color   Hex color.
		 * @return string
		 */
		public static function callout_svg_icon( $variant, $color ) {
			$variant = self::normalize_variant( $variant );
			$color   = preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $color ) ? (string) $color : '#1d4ed8';

			// 简洁线框图标 path。
			$paths = array(
				'info'    => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M12 10.5v5.25" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="7.5" r="1" fill="currentColor"/>',
				'caution' => '<path d="M12 3.5L21 19.5H3L12 3.5z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M12 10v4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/>',
				'alert'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M12 7.5v5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="16.25" r="1" fill="currentColor"/>',
				'danger'  => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M9 9l6 6M15 9l-6 6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>',
			);
			$path = isset( $paths[ $variant ] ) ? $paths[ $variant ] : $paths['info'];

			return '<svg class="pili-content-callout__icon-glyph pili-content-callout__icon-svg" width="22" height="22" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="color:' . esc_attr( $color ) . ';display:block">' . $path . '</svg>';
		}

		/**
		 * @param string $variant      已 normalize 或可 normalize 的键。
		 * @param string $custom_icon  可选 ri-*（保留参数兼容；说明块统一用内联 SVG，不依赖字体）。
		 * @return string
		 */
		public static function render_callout_icon_html( $variant, $custom_icon = '' ) {
			unset( $custom_icon );
			$key  = self::normalize_variant( $variant );
			$meta = self::variant_registry()[ $key ];

			// 说明块图标固定内联 SVG：不依赖主题是否带 Remix、也不依赖 CDN。
			$icon_html = self::callout_svg_icon( $key, $meta['color'] );

			return '<div class="pili-content-callout__icon" role="img" aria-label="' . esc_attr( $meta['label'] ) . '">' . $icon_html . '</div>';
		}

		/**
		 * 将 HTML 片段包进说明块外壳（供 callback 或业务 PHP 复用）。
		 *
		 * @param string              $html        内层 HTML（勿再套外层色块 div）。
		 * @param string              $variant     info|caution|alert|danger（warning 等别名见 normalize_variant）。
		 * @param string              $custom_icon 可选 ri-* 覆盖默认图标。
		 * @param array<string,mixed> $options     可选：dismissible、dismiss_key、dismiss_label、restore_label。
		 * @return string
		 */
		public static function wrap_callout( $html, $variant = 'info', $custom_icon = '', $options = array() ) {
			$key     = self::normalize_variant( $variant );
			$meta    = self::variant_registry()[ $key ];
			$classes = 'pili-content-callout ' . $meta['modifier'];
			$options = is_array( $options ) ? $options : array();

			$dismissible = ! empty( $options['dismissible'] );
			$dismiss_key = isset( $options['dismiss_key'] ) ? sanitize_key( (string) $options['dismiss_key'] ) : '';
			if ( $dismissible && '' === $dismiss_key ) {
				$dismiss_key = 'callout';
			}
			if ( $dismissible ) {
				$classes .= ' pili-content-callout--dismissible';
			}

			$dismiss_label = isset( $options['dismiss_label'] ) && is_string( $options['dismiss_label'] ) && '' !== $options['dismiss_label']
				? $options['dismiss_label']
				: pili__( '关闭说明' );
			$restore_label = isset( $options['restore_label'] ) && is_string( $options['restore_label'] ) && '' !== $options['restore_label']
				? $options['restore_label']
				: pili__( '重新显示合规说明' );

			$inner  = '<div class="pili-content-callout__inner">';
			$inner .= self::render_callout_icon_html( $key, $custom_icon );
			$inner .= '<div class="pili-content-callout__body">' . (string) $html . '</div>';
			if ( $dismissible ) {
				$inner .= '<button type="button" class="pili-content-callout__dismiss" data-pili-content-dismiss-btn="1" aria-label="' . esc_attr( $dismiss_label ) . '" title="' . esc_attr( $dismiss_label ) . '">';
				$inner .= '<span aria-hidden="true">&times;</span>';
				$inner .= '</button>';
			}
			$inner .= '</div>';

			$callout = '<div class="' . esc_attr( $classes ) . '" data-callout-variant="' . esc_attr( $key ) . '">' . $inner . '</div>';

			if ( ! $dismissible ) {
				return $callout;
			}

			$host  = '<div class="pili-content-dismiss-host" data-pili-content-dismiss-key="' . esc_attr( $dismiss_key ) . '" data-pili-content-dismissible="1">';
			$host .= $callout;
			$host .= '<div class="pili-content-dismiss-restore" hidden>';
			$host .= '<button type="button" class="pili-content-dismiss-restore__btn" data-pili-content-restore-btn="1">';
			$host .= esc_html( $restore_label );
			$host .= '</button>';
			$host .= '</div>';
			$host .= '</div>';

			return $host;
		}

		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		public function render() {
			echo $this->field_before();

			// Prefer callback（支持 string / array callable，与导入插件隔离版一致）。
			$cb = isset( $this->field['callback'] ) ? $this->field['callback'] : null;
			if ( is_callable( $cb ) ) {
				$content = call_user_func( $cb, $this->field, $this->value, $this->unique, $this->where, $this->parent );
			} else {
				$content = isset( $this->field['content'] ) ? $this->field['content'] : '';
				if ( is_callable( $content ) ) {
					$content = call_user_func( $content, $this->field, $this->value, $this->unique, $this->where, $this->parent );
				}
			}
			if ( ! is_string( $content ) ) {
				$content = '';
			}

			$variant = isset( $this->field['variant'] ) ? sanitize_key( (string) $this->field['variant'] ) : '';
			if ( '' !== $variant ) {
				$custom_icon = isset( $this->field['callout_icon'] ) ? (string) $this->field['callout_icon'] : '';
				$opts        = array();
				if ( ! empty( $this->field['dismissible'] ) ) {
					$opts['dismissible'] = true;
					$field_id            = isset( $this->field['id'] ) ? sanitize_key( (string) $this->field['id'] ) : '';
					$opts['dismiss_key'] = ! empty( $this->field['dismiss_key'] )
						? sanitize_key( (string) $this->field['dismiss_key'] )
						: ( '' !== $field_id ? $field_id : 'callout' );
					if ( ! empty( $this->field['dismiss_label'] ) && is_string( $this->field['dismiss_label'] ) ) {
						$opts['dismiss_label'] = $this->field['dismiss_label'];
					}
					if ( ! empty( $this->field['restore_label'] ) && is_string( $this->field['restore_label'] ) ) {
						$opts['restore_label'] = $this->field['restore_label'];
					}
				}
				echo self::wrap_callout( $content, $variant, $custom_icon, $opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 受控后台 HTML + 已转义控件
			} else {
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 受控后台 HTML
			}

			echo $this->field_after();
		}

		public function enqueue() {
			if ( class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
				Remix_Icons::enqueue_style( 'pilidoc-remixicon-content-callout' );
			}
			$handle = pili_asset_handle( 'field-content' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/content.css',
				array(),
				PILI_CORE_VERSION
			);
			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/content.js',
				array(),
				PILI_CORE_VERSION,
				true
			);
		}
	}
