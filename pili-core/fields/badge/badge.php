<?php
/**
 * Field: badge — 状态标签 / 徽章（软底或实心）。
 *
 * @package PILI Framework
 */

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_badge' ) ) {

	/**
	 * 标签组件；可作独立字段展示，也可 `render_html()` 嵌入业务 HTML。
	 */
	class PILI_Field_badge extends PILI_Fields {

		/**
		 * @return array<string, array{bg:string,border:string,color:string,solid_bg:string,solid_color:string}>
		 */
		public static function tone_map() {
			$defaults = array(
				'gray'    => array(
					'bg'          => '#f3f4f6',
					'border'      => '#e5e7eb',
					'color'       => '#374151',
					'solid_bg'    => '#6b7280',
					'solid_color' => '#ffffff',
				),
				'blue'    => array(
					'bg'          => '#eff6ff',
					'border'      => '#bfdbfe',
					'color'       => '#1d4ed8',
					'solid_bg'    => '#3b82f6',
					'solid_color' => '#ffffff',
				),
				'green'   => array(
					'bg'          => '#f0fdf4',
					'border'      => '#bbf7d0',
					'color'       => '#15803d',
					'solid_bg'    => '#22c55e',
					'solid_color' => '#ffffff',
				),
				'amber'   => array(
					'bg'          => '#fffbeb',
					'border'      => '#fde68a',
					'color'       => '#b45309',
					'solid_bg'    => '#f59e0b',
					'solid_color' => '#ffffff',
				),
				'orange'  => array(
					'bg'          => '#fff7ed',
					'border'      => '#fed7aa',
					'color'       => '#c2410c',
					'solid_bg'    => '#f97316',
					'solid_color' => '#ffffff',
				),
				'red'     => array(
					'bg'          => '#fef2f2',
					'border'      => '#fecaca',
					'color'       => '#b91c1c',
					'solid_bg'    => '#ef4444',
					'solid_color' => '#ffffff',
				),
				'purple'  => array(
					'bg'          => '#faf5ff',
					'border'      => '#e9d5ff',
					'color'       => '#7e22ce',
					'solid_bg'    => '#a855f7',
					'solid_color' => '#ffffff',
				),
				'teal'    => array(
					'bg'          => '#f0fdfa',
					'border'      => '#99f6e4',
					'color'       => '#0f766e',
					'solid_bg'    => '#14b8a6',
					'solid_color' => '#ffffff',
				),
			);

			$custom = apply_filters( 'pili_badge_tone_map', array() );
			return array_merge( $defaults, is_array( $custom ) ? $custom : array() );
		}

		/**
		 * 语义别名 → tone。
		 *
		 * @param string $tone Tone or alias.
		 * @return string
		 */
		public static function normalize_tone( $tone ) {
			$key = sanitize_key( (string) $tone );
			$aliases = array(
				'default'  => 'gray',
				'neutral'  => 'gray',
				'info'     => 'blue',
				'success'  => 'green',
				'caution'  => 'amber',
				'warning'  => 'amber',
				'alert'    => 'orange',
				'danger'   => 'red',
				'error'    => 'red',
				'primary'  => 'blue',
			);
			if ( isset( $aliases[ $key ] ) ) {
				$key = $aliases[ $key ];
			}
			$map = self::tone_map();
			return isset( $map[ $key ] ) ? $key : 'blue';
		}

		/**
		 * @param array|string $args 文案字符串，或含 text/label/tone/variant/size/icon 的数组。
		 * @return string
		 */
		public static function render_html( $args = array() ) {
			if ( is_string( $args ) ) {
				$args = array( 'text' => $args );
			}
			if ( ! is_array( $args ) ) {
				$args = array();
			}

			$text = '';
			if ( isset( $args['text'] ) ) {
				$text = (string) $args['text'];
			} elseif ( isset( $args['label'] ) ) {
				$text = (string) $args['label'];
			} elseif ( isset( $args['content'] ) ) {
				$text = (string) $args['content'];
			}
			$text = trim( $text );
			if ( '' === $text ) {
				return '';
			}

			$tone    = self::normalize_tone( isset( $args['tone'] ) ? $args['tone'] : ( isset( $args['color'] ) ? $args['color'] : 'blue' ) );
			$variant = isset( $args['variant'] ) ? sanitize_key( (string) $args['variant'] ) : 'soft';
			if ( ! in_array( $variant, array( 'soft', 'solid', 'outline' ), true ) ) {
				$variant = 'soft';
			}
			$size = isset( $args['size'] ) ? sanitize_key( (string) $args['size'] ) : 'sm';
			if ( ! in_array( $size, array( 'xs', 'sm', 'md' ), true ) ) {
				$size = 'sm';
			}

			$map   = self::tone_map();
			$colors = $map[ $tone ];

			$classes = array(
				'pili-badge',
				'pili-badge--' . $variant,
				'pili-badge--' . $size,
				'pili-badge--' . $tone,
			);
			if ( ! empty( $args['class'] ) ) {
				$classes[] = (string) $args['class'];
			}

			$style = '';
			if ( 'solid' === $variant ) {
				$style = 'background-color:' . $colors['solid_bg'] . ';border-color:' . $colors['solid_bg'] . ';color:' . $colors['solid_color'] . ';';
			} elseif ( 'outline' === $variant ) {
				$style = 'background-color:transparent;border-color:' . $colors['border'] . ';color:' . $colors['color'] . ';';
			} else {
				$style = 'background-color:' . $colors['bg'] . ';border-color:' . $colors['border'] . ';color:' . $colors['color'] . ';';
			}

			$icon_html = '';
			$icon      = isset( $args['icon'] ) ? trim( (string) $args['icon'] ) : '';
			if ( '' !== $icon && preg_match( '/^ri-[a-z0-9-]+$/', $icon ) ) {
				if ( class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
					$icon_html = Remix_Icons::get_icon(
						$icon,
						array(
							'class' => 'pili-badge__icon',
							'size'  => 'xs' === $size ? '12' : ( 'md' === $size ? '14' : '13' ),
							'color' => 'solid' === $variant ? $colors['solid_color'] : $colors['color'],
						)
					);
				} else {
					$icon_html = '<i class="' . esc_attr( $icon ) . ' pili-badge__icon" aria-hidden="true"></i>';
				}
			}

			$html  = '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '" data-pili-badge="1">';
			if ( $icon_html ) {
				$html .= $icon_html;
			}
			$html .= '<span class="pili-badge__text">' . esc_html( $text ) . '</span>';
			$html .= '</span>';

			return $html;
		}

		/**
		 * 入队样式。
		 */
		public static function enqueue_assets() {
			$handle = pili_asset_handle( 'field-badge' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/badge.css',
				array(),
				defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.0'
			);
		}

		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		public function render() {
			echo $this->field_before();

			$args          = $this->field;
			$args['text']  = isset( $args['text'] ) ? (string) $args['text'] : (string) $this->value;
			if ( '' === trim( (string) $args['text'] ) && isset( $args['label'] ) ) {
				$args['text'] = (string) $args['label'];
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 受控后台 HTML
			echo self::render_html( $args );

			echo $this->field_after();
		}

		public function enqueue() {
			self::enqueue_assets();
			if ( class_exists( __NAMESPACE__ . '\Remix_Icons' ) && ! empty( $this->field['icon'] ) ) {
				Remix_Icons::enqueue_style( 'pilidoc-remixicon-badge' );
			}
		}
	}
}
