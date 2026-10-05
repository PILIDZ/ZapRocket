<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Field: loading — 四环 SVG 加载动画（只读展示，不参与存储）。
 *
 * 视觉来源：导入插件《加载案例.md》（Uiverse.io by Nawsome）。
 */
	class PILI_Field_loading extends PILI_Fields {

		/**
		 * @param array<string, mixed> $args size, class, aria_label.
		 * @return string SVG markup.
		 */
		public static function render_spinner( array $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'size'       => 'sm',
					'class'      => '',
					'aria_label' => pili__( '加载中' ),
				)
			);

			$size_class = self::size_class( (string) $args['size'] );
			$extra      = trim( (string) $args['class'] );
			$classes    = trim( 'pili-loading-pl ' . $size_class . ' ' . $extra );

			$html  = '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 240 240" role="img" aria-label="' . esc_attr( (string) $args['aria_label'] ) . '">';
			$html .= '<circle class="pili-loading-pl__ring pili-loading-pl__ring--a" cx="120" cy="120" r="105" fill="none" stroke-width="20" stroke-dasharray="0 660" stroke-dashoffset="-330" stroke-linecap="round"></circle>';
			$html .= '<circle class="pili-loading-pl__ring pili-loading-pl__ring--b" cx="120" cy="120" r="35" fill="none" stroke-width="20" stroke-dasharray="0 220" stroke-dashoffset="-110" stroke-linecap="round"></circle>';
			$html .= '<circle class="pili-loading-pl__ring pili-loading-pl__ring--c" cx="85" cy="120" r="70" fill="none" stroke-width="20" stroke-dasharray="0 440" stroke-linecap="round"></circle>';
			$html .= '<circle class="pili-loading-pl__ring pili-loading-pl__ring--d" cx="155" cy="120" r="70" fill="none" stroke-width="20" stroke-dasharray="0 440" stroke-linecap="round"></circle>';
			$html .= '</svg>';

			return $html;
		}

		/**
		 * 向导 sync 预览条外壳（含 hidden spinner 槽 + 文案区）。
		 *
		 * @param array<string, mixed> $args preview_id, variant, text_id, content, extra_class.
		 * @return string
		 */
		public static function render_preview_card( array $args ) {
			$args = wp_parse_args(
				$args,
				array(
					'preview_id'  => '',
					'variant'     => 'info',
					'text_id'     => '',
					'content'     => '',
					'extra_class' => 'mt-4',
				)
			);

			$preview_id = sanitize_key( (string) $args['preview_id'] );
			if ( '' === $preview_id ) {
				return '';
			}

			$variant_classes = self::variant_classes( (string) $args['variant'] );
			$text_id         = sanitize_html_class( (string) $args['text_id'] );
			$content         = (string) $args['content'];
			$extra_class     = trim( (string) $args['extra_class'] );

			$root_classes = trim(
				'pili-wizard-step-preview pili-loading-preview ' . $variant_classes . ' ' . $extra_class
			);

			$html  = '<div id="' . esc_attr( $preview_id ) . '" class="' . esc_attr( $root_classes ) . '" data-state="idle" role="status" aria-live="polite" aria-busy="false">';
			$html .= '<div class="pili-loading-preview__inner flex items-center gap-3">';
			$html .= '<div class="pili-loading-field pili-loading-field--slot is-hidden" aria-hidden="true">';
			$html .= self::render_spinner(
				array(
					'size'       => 'xs',
					'aria_label' => pili__( '加载中' ),
				)
			);
			$html .= '</div>';
			$html .= '<span';
			if ( '' !== $text_id ) {
				$html .= ' id="' . esc_attr( $text_id ) . '"';
			}
		$html .= ' class="pili-loading-preview__text flex-1 min-w-0">';
		if ( ! empty( $args['content_html'] ) ) {
			$html .= wp_kses_post( $content );
		} else {
			$html .= esc_html( $content );
		}
			$html .= '</span>';
			$html .= '</div></div>';

			return $html;
		}

		/**
		 * @param string $size xs|sm|md|lg.
		 * @return string
		 */
		private static function size_class( $size ) {
			$map = array(
				'xs' => 'pili-loading-pl--xs',
				'sm' => 'pili-loading-pl--sm',
				'md' => 'pili-loading-pl--md',
				'lg' => 'pili-loading-pl--lg',
			);
			$key = sanitize_key( $size );
			return isset( $map[ $key ] ) ? $map[ $key ] : $map['sm'];
		}

		/**
		 * @param string $variant info|neutral|warning.
		 * @return string Tailwind utility classes.
		 */
		private static function variant_classes( $variant ) {
			switch ( sanitize_key( $variant ) ) {
				case 'neutral':
					return 'rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700';
				case 'warning':
					return 'rounded-lg border border-amber-100 bg-amber-50 p-3 text-sm text-amber-900';
				case 'info':
				default:
					return 'rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900';
			}
		}

		public function render() {
			echo $this->field_before();

			$args = wp_parse_args(
				$this->field,
				array(
					'size'        => 'md',
					'label'       => '',
					'inline'      => false,
					'visible'     => true,
					'preview_id'  => '',
					'variant'     => 'neutral',
					'text_id'     => '',
					'content'     => '',
					'mode'        => 'spinner',
				)
			);

			if ( 'preview_card' === (string) $args['mode'] && '' !== (string) $args['preview_id'] ) {
				echo self::render_preview_card(
					array(
						'preview_id'  => $args['preview_id'],
						'variant'     => $args['variant'],
						'text_id'     => $args['text_id'],
						'content'     => is_string( $args['content'] ) ? $args['content'] : '',
						'extra_class' => isset( $args['class'] ) ? (string) $args['class'] : '',
					)
				);
				echo $this->field_after();
				return;
			}

			$wrap_classes = array( 'pili-loading-field' );
			if ( ! $args['visible'] ) {
				$wrap_classes[] = 'is-hidden';
			}
			if ( $args['inline'] ) {
				$wrap_classes[] = 'pili-loading-field--inline';
			}

			echo '<div class="' . esc_attr( implode( ' ', $wrap_classes ) ) . '" data-field-id="' . esc_attr( $this->field['id'] ) . '">';
			echo self::render_spinner(
				array(
					'size'       => (string) $args['size'],
					'aria_label' => (string) $args['label'] !== '' ? (string) $args['label'] : pili__( '加载中' ),
				)
			);
			if ( '' !== (string) $args['label'] ) {
				echo '<p class="pili-loading-field__label text-sm text-gray-600 mt-2 mb-0">' . esc_html( (string) $args['label'] ) . '</p>';
			}
			echo '</div>';

			echo $this->field_after();
		}

		public function enqueue() {
			$handle = pili_asset_handle( 'field-loading' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/loading.css',
				array(),
				PILI_CORE_VERSION
			);

			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/loading.js',
				array( 'jquery' ),
				PILI_CORE_VERSION,
				true
			);
		}

		public function validate( $value ) {
			return '';
		}
	}
