<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 单选按钮字段类型
 *
 * 支持 `multiple => true`：保持 radio 卡片 UI，底层用 checkbox 实现可多选。
 *
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.1
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_radio' ) ) {

	/**
	 * PILI_Field_radio 单选按钮字段类
	 *
	 * @since 1.0
	 */
	class PILI_Field_radio extends PILI_Fields {
		/**
		 * @since 1.0
		 *
		 * @param array  $field  字段配置数组
		 * @param mixed  $value  字段值
		 * @param string $unique 唯一标识符
		 * @param string $where  字段位置
		 * @param string $parent 父级字段
		 */
		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * @return bool
		 */
		protected function is_multiple() {
			return ! empty( $this->field['multiple'] );
		}

		/**
		 * @param mixed $value Value.
		 * @return array<int,string>
		 */
		protected function selected_values( $value = null ) {
			if ( null === $value ) {
				$value = $this->value;
			}
			if ( $this->is_multiple() ) {
				if ( ! is_array( $value ) ) {
					$value = ( '' === $value || null === $value ) ? array() : array( $value );
				}
				$out = array();
				foreach ( $value as $v ) {
					$v = (string) $v;
					if ( '' !== $v ) {
						$out[] = $v;
					}
				}
				return array_values( array_unique( $out ) );
			}
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			$value = (string) $value;
			return ( '' === $value ) ? array() : array( $value );
		}

		/**
		 * @param string $option_value Option key.
		 * @return bool
		 */
		protected function is_option_checked( $option_value ) {
			return in_array( (string) $option_value, $this->selected_values(), true );
		}

		/**
		 * 扁平化 options（含分组）。
		 *
		 * @param array $options Options.
		 * @return array<string,string>
		 */
		protected function flatten_options( $options ) {
			$flat = array();
			if ( ! is_array( $options ) ) {
				return $flat;
			}
			foreach ( $options as $key => $option ) {
				if ( is_array( $option ) ) {
					foreach ( $option as $k => $label ) {
						if ( ! is_array( $label ) ) {
							$flat[ (string) $k ] = (string) $label;
						}
					}
				} else {
					$flat[ (string) $key ] = (string) $option;
				}
			}
			return $flat;
		}

		/**
		 * 渲染单选按钮字段
		 *
		 * @since 1.0
		 */
		public function render() {
			$options      = ! empty( $this->field['options'] ) ? $this->field['options'] : array();
			if ( function_exists( 'pili_resolve_field_options' ) ) {
				$options = pili_resolve_field_options( $options );
			} elseif ( ! is_array( $options ) ) {
				$options = array();
			}
			$inline       = ! empty( $this->field['inline'] ) ? $this->field['inline'] : false;
			$searchable   = ! empty( $this->field['searchable'] ) ? $this->field['searchable'] : false;
			$icons        = ! empty( $this->field['icons'] ) ? $this->field['icons'] : array();
			$descriptions = ! empty( $this->field['descriptions'] ) ? $this->field['descriptions'] : array();
			$color        = ! empty( $this->field['color'] ) ? $this->field['color'] : 'blue';
			$size         = ! empty( $this->field['size'] ) ? $this->field['size'] : 'medium';
			$style        = ! empty( $this->field['style'] ) ? $this->field['style'] : 'default';
			$show_count   = ! empty( $this->field['show_count'] ) ? $this->field['show_count'] : false;
			$desc         = ! empty( $this->field['desc'] ) ? $this->field['desc'] : '';
			$multiple     = $this->is_multiple();
			$selected     = $this->selected_values();

			echo $this->field_before();
			echo '<div class="pili-radio-field"'
				. ' data-field-id="' . esc_attr( $this->field_id() ) . '"'
				. ' data-color="' . esc_attr( $color ) . '"'
				. ' data-size="' . esc_attr( $size ) . '"'
				. ' data-style="' . esc_attr( $style ) . '"'
				. ' data-multiple="' . ( $multiple ? '1' : '0' ) . '"'
				. ' data-i18n-selected="' . esc_attr( pili__( '已选 %1$d / 共 %2$d 个选项' ) ) . '"'
				. ' data-i18n-total="' . esc_attr( pili__( '共 %d 个选项' ) ) . '"'
				. ' data-i18n-showing="' . esc_attr( pili__( '显示 %1$d / %2$d 个选项' ) ) . '"'
				. ' data-i18n-showing-items="' . esc_attr( pili__( '显示 %1$d / %2$d 项' ) ) . '"'
				. ' data-i18n-no-results="' . esc_attr( pili__( '未找到匹配的选项' ) ) . '"'
				. ' data-i18n-try-other="' . esc_attr( pili__( '尝试使用其他关键词搜索' ) ) . '"'
				. '>';
			if ( $searchable && count( $options ) > 5 ) {
				echo '<div class="mb-4">';
				echo '<div class="relative">';
				echo '<div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none z-10">';
				echo '<svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
				echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>';
				echo '</svg>';
				echo '</div>';
				echo '<input type="text" class="pili-radio-search block w-full pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-' . esc_attr( $color ) . '-500 focus:border-' . esc_attr( $color ) . '-500 sm:text-sm" style="padding-left: 2rem;" placeholder="' . pili_esc_attr__( '搜索选项...' ) . '" />';
				echo '</div>';
				echo '</div>';
			}
			if ( $show_count ) {
				echo '<div class="mb-3 text-sm text-gray-500">';
				if ( $multiple ) {
					echo '<span class="pili-radio-count">' . esc_html( sprintf( pili__( '已选 %1$d / 共 %2$d 个选项' ), count( $selected ), count( $this->flatten_options( $options ) ) ) ) . '</span>';
				} else {
					echo '<span class="pili-radio-count">' . esc_html( sprintf( pili__( '共 %d 个选项' ), count( $options ) ) ) . '</span>';
				}
				echo '</div>';
			}
			$container_classes = array(
				'pili-radio-group',
				'space-y-3',
			);
			if ( $inline ) {
				$container_classes = array(
					'pili-radio-group',
					'flex',
					'flex-wrap',
					'gap-4',
				);
			}
			echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '" role="' . ( $multiple ? 'group' : 'radiogroup' ) . '">';
			$this->render_options( $options, $icons, $descriptions, $inline, $color, $size, $style );
			echo '</div>';

			// 多选：始终带空值兜底；有选中时 disabled，避免覆盖数组提交。
			if ( $multiple ) {
				echo '<input type="hidden" class="pili-radio-empty" name="' . esc_attr( $this->field_name() ) . '" value=""' . ( empty( $selected ) ? '' : ' disabled' ) . ' />';
			}

			if ( ! empty( $desc ) ) {
				echo '<p class="mt-3 text-sm text-gray-500">';
				echo wp_kses_post( $desc );
				echo '</p>';
			}
			if ( ! empty( $this->field['_error'] ) ) {
				echo '<p class="mt-2 text-sm text-red-600">';
				echo esc_html( $this->field['_error'] );
				echo '</p>';
			}
			if ( ! empty( $this->field['after'] ) ) {
				echo '<div class="mt-3 pili-after-text">' . $this->field['after'] . '</div>';
			}
			if ( ! empty( $this->field['help'] ) ) {
				echo '<div class="mt-2 pili-help">';
				echo '<span class="pili-help-text">' . $this->field['help'] . '</span>';
				echo '<i class="pili-help-icon">?</i>';
				echo '</div>';
			}
			echo '</div>';
		}

		/**
		 * @since 1.0
		 *
		 * @param array  $options      选项数组
		 * @param array  $icons        图标数组
		 * @param array  $descriptions 描述数组
		 * @param bool   $inline       是否内联显示
		 * @param string $color        颜色主题
		 * @param string $size         尺寸
		 * @param string $style        样式
		 */
		private function render_options( $options, $icons, $descriptions, $inline, $color, $size, $style ) {
			foreach ( $options as $option_value => $option_label ) {
				if ( is_array( $option_label ) ) {
					$this->render_option_group( $option_value, $option_label, $icons, $descriptions, $inline, $color, $size, $style );
				} else {
					$this->render_single_option( $option_value, $option_label, $icons, $descriptions, $inline, $color, $size, $style );
				}
			}
		}

		/**
		 * @since 1.0
		 */
		private function render_option_group( $group_name, $group_options, $icons, $descriptions, $inline, $color, $size, $style ) {
			echo '<div class="pili-radio-group-section">';
			echo '<h4 class="text-sm font-medium text-gray-900 mb-3">' . esc_html( $group_name ) . '</h4>';
			echo '<div class="ml-4 space-y-2">';
			foreach ( $group_options as $option_value => $option_label ) {
				$this->render_single_option( $option_value, $option_label, $icons, $descriptions, $inline, $color, $size, $style );
			}
			echo '</div>';
			echo '</div>';
		}

		/**
		 * @since 1.0
		 */
		private function render_single_option( $option_value, $option_label, $icons, $descriptions, $inline, $color, $size, $style ) {
			$multiple       = $this->is_multiple();
			$is_checked     = $this->is_option_checked( $option_value );
			$has_icon       = isset( $icons[ $option_value ] );
			$has_description = isset( $descriptions[ $option_value ] );
			$input_type     = $multiple ? 'checkbox' : 'radio';
			$input_name     = $multiple ? $this->field_name( '[]' ) : $this->field_name();
			$option_classes = array(
				'pili-radio-option',
				'relative',
				'flex',
				'items-start',
				'cursor-pointer',
				'rounded-lg',
				'border',
				'p-4',
				'transition-all',
				'duration-200',
				'hover:bg-gray-50',
				'focus-within:ring-2',
				'focus-within:ring-offset-2',
				'focus-within:ring-' . $color . '-500',
			);
			if ( $is_checked ) {
				$option_classes[] = 'bg-' . $color . '-50';
				$option_classes[] = 'border-' . $color . '-200';
				$option_classes[] = 'ring-1';
				$option_classes[] = 'ring-' . $color . '-500';
			} else {
				$option_classes[] = 'border-gray-200';
			}
			if ( $inline && ! $has_description ) {
				$option_classes[] = 'inline-flex';
				$option_classes[] = 'mr-4';
				$option_classes[] = 'mb-2';
			}
			echo '<div class="' . esc_attr( implode( ' ', $option_classes ) ) . '" data-value="' . esc_attr( $option_value ) . '">';
			echo '<div class="flex items-center h-5">';
			echo '<input ';
			echo 'id="' . esc_attr( $this->field_id() . '_' . $option_value ) . '" ';
			echo 'name="' . esc_attr( $input_name ) . '" ';
			echo 'type="' . esc_attr( $input_type ) . '" ';
			echo 'value="' . esc_attr( $option_value ) . '" ';
			// 多选仍用圆形外观，保持 radio UI。
			echo 'class="pili-radio-input h-4 w-4 rounded-full text-' . esc_attr( $color ) . '-600 border-gray-300 focus:ring-' . esc_attr( $color ) . '-500" ';
			echo $is_checked ? 'checked ' : '';
			echo $this->field_attributes() . ' />';
			echo '</div>';
			echo '<div class="ml-3 flex-1">';
			echo '<label for="' . esc_attr( $this->field_id() . '_' . $option_value ) . '" class="block cursor-pointer">';
			echo '<div class="flex items-center">';
			if ( $has_icon ) {
				echo '<div class="mr-3 flex-shrink-0">';
				echo $icons[ $option_value ];
				echo '</div>';
			}
			echo '<div class="text-sm font-medium text-gray-900">';
			echo esc_html( $option_label );
			echo '</div>';
			echo '</div>';
			if ( $has_description ) {
				echo '<div class="mt-1 text-sm text-gray-500">';
				echo esc_html( $descriptions[ $option_value ] );
				echo '</div>';
			}
			echo '</label>';
			echo '</div>';
			echo '</div>';
		}

		/**
		 * @since 1.0
		 */
		public function enqueue() {
			$deps = array( 'jquery' );
			if ( function_exists( 'pili_enqueue_js_i18n_runtime' ) ) {
				$i18n_rt = pili_enqueue_js_i18n_runtime();
				if ( is_string( $i18n_rt ) && '' !== $i18n_rt ) {
					$deps[] = $i18n_rt;
				}
			}
			$handle = pili_asset_handle( 'field-radio' );
			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/radio.js',
				$deps,
				PILI_CORE_VERSION,
				true
			);
			pili_localize_bag( $handle, 'radio',
				array(
					'selectedCount' => pili__( '已选择 %1$d / %2$d 项' ),
					'totalCount' => pili__( '共 %d 个选项' ),
					'showingCount' => pili__( '显示 %1$d / %2$d 个选项' ),
					'showingItems' => pili__( '显示 %1$d / %2$d 项' ),
					'noResults' => pili__( '无结果' ),
					'tryOtherKeyword' => pili__( '尝试使用其他关键词搜索' ),
				)
			);
		}

		/**
		 * @since 1.0
		 *
		 * @param mixed $value 要验证的值
		 *
		 * @return mixed 验证后的值
		 */
		public function validate( $value ) {
			$raw_options  = ! empty( $this->field['options'] ) ? $this->field['options'] : array();
			if ( function_exists( 'pili_resolve_field_options' ) ) {
				$raw_options = pili_resolve_field_options( $raw_options );
			} elseif ( ! is_array( $raw_options ) ) {
				$raw_options = array();
			}
			$flat_options = $this->flatten_options( $raw_options );

			if ( $this->is_multiple() ) {
				$selected = $this->selected_values( $value );
				$out      = array();
				foreach ( $selected as $v ) {
					if ( array_key_exists( $v, $flat_options ) ) {
						$out[] = $v;
					}
				}
				$value = $out;
			} else {
				if ( is_array( $value ) ) {
					$value = reset( $value );
				}
				$value = (string) $value;
				if ( '' !== $value && ! array_key_exists( $value, $flat_options ) ) {
					$value = '';
				}
			}

			$value = apply_filters( 'pili_validate_radio_field', $value, $this->field );
			$value = apply_filters( "pili_validate_radio_field_{$this->field['id']}", $value, $this->field );
			return $value;
		}
	}
}
