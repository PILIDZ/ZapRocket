<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 选择字段类型
 *
 * 这个类实现了现代化的选择字段功能。
 * 支持单选、多选、搜索、分组、选项图标等功能。
 *
 * 选项格式：
 * - 简单：'key' => 'Label'
 * - 富选项：'key' => array( 'label' => 'Label', 'icon' => '🇨🇳' | 'dashicons dashicons-admin-site', 'icon_url' => '...', 'icon_html' => '<svg...>' )
 * - 分组：'Group' => array( 'key' => 'Label'|富选项, ... )
 *
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.1
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_select' ) ) {

	/**
	 * PILI_Field_select 选择字段类
	 *
	 * @since 1.0
	 */
	class PILI_Field_select extends PILI_Fields {
		/**
		 * 构造函数
		 *
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
		 * 渲染选择字段
		 *
		 * @since 1.0
		 */
		public function render() {
			$args = wp_parse_args(
				$this->field,
				array(
					'placeholder' => pili__( '请选择…' ),
					'multiple'            => false,
					'searchable'          => true,
					'clearable'           => true,
					'max_height'          => 240,
					'show_count'          => false,
					'select_all'          => false,
					'close_on_select'     => true,
					'options'             => array(),
					'ajax'                => false,
					'ajax_url'            => '',
					'min_search_length'   => 0,
					'no_results_text' => pili__( '未找到匹配项' ),
					'loading_text' => pili__( '加载中…' ),
					'search_placeholder' => pili__( '搜索选项…' ),
				)
			);

			if ( $args['multiple'] ) {
				$this->value = is_array( $this->value ) ? $this->value : array_filter( (array) $this->value );
			} else {
				$this->value = is_array( $this->value ) ? reset( $this->value ) : $this->value;
			}
			$options    = $this->get_field_options( $args );
			$field_id   = $this->field_id();
			$listbox_id = $field_id . '_listbox';
			echo $this->field_before();
			// 文案挂 data-*：JS 初始化时优先读这里，避免 localize / pilipost__ 回落成中文 msgid。
			echo '<div class="pili-select-field w-full touch-manipulation"'
				. ' data-field-id="' . esc_attr( $field_id ) . '"'
				. ' data-multiple="' . ( $args['multiple'] ? 'true' : 'false' ) . '"'
				. ' data-searchable="' . ( $args['searchable'] ? 'true' : 'false' ) . '"'
				. ' data-clearable="' . ( $args['clearable'] ? 'true' : 'false' ) . '"'
				. ' data-close-on-select="' . ( $args['close_on_select'] ? 'true' : 'false' ) . '"'
				. ' data-placeholder="' . esc_attr( (string) $args['placeholder'] ) . '"'
				. ' data-i18n-selected="' . esc_attr( pili__( '已选择 %d 项' ) ) . '"'
				. ' data-i18n-clear="' . esc_attr( pili__( '清空选择' ) ) . '"'
				. ' data-i18n-select-all="' . esc_attr( pili__( '全选' ) ) . '"'
				. ' data-i18n-no-results="' . esc_attr( (string) $args['no_results_text'] ) . '"'
				. ' data-i18n-loading="' . esc_attr( (string) $args['loading_text'] ) . '"'
				. ' data-i18n-search="' . esc_attr( (string) $args['search_placeholder'] ) . '"'
				. '>';
			$this->render_select_button( $args, $field_id, $listbox_id, $options );
			$this->render_dropdown( $args, $listbox_id, $options );
			$this->render_native_select( $args, $options );
			echo '</div>';
			$desc = ! empty( $this->field['desc'] ) ? $this->field['desc'] : '';
			if ( ! empty( $desc ) ) {
				echo '<p class="mt-3 text-sm text-gray-500 mb-0">';
				echo wp_kses_post( $desc );
				echo '</p>';
			}
			echo $this->field_after();
		}

		/**
		 * 渲染选择按钮
		 *
		 * @since 1.0
		 *
		 * @param array  $args       字段配置参数
		 * @param string $field_id   字段ID
		 * @param string $listbox_id 列表框ID
		 * @param array  $options    选项数组
		 */
		private function render_select_button( $args, $field_id, $listbox_id, $options ) {
			$display_meta = $this->get_display_meta( $args, $options );
			echo '<div class="relative">';
			echo '<button type="button" aria-expanded="false" aria-haspopup="listbox" aria-labelledby="' . esc_attr( $field_id . '_label' ) . '" class="pili-select-button relative grid w-full cursor-default grid-cols-1 rounded-md bg-white min-h-10 py-2.5 pr-2 pl-3 text-left text-gray-900 sm:text-sm/6 touch-manipulation" data-listbox="' . esc_attr( $listbox_id ) . '">';
			$right_padding = $args['clearable'] && ! empty( $this->value ) ? 'pr-16 sm:pr-14 md:pr-12' : 'pr-8 sm:pr-7 md:pr-6';
			echo '<span class="col-start-1 row-start-1 truncate pili-select-display ' . esc_attr( $right_padding ) . '">';
			if ( empty( $display_meta['label'] ) && empty( $display_meta['count'] ) ) {
				echo '<span class="text-gray-500">' . esc_html( $args['placeholder'] ) . '</span>';
			} elseif ( ! empty( $display_meta['count'] ) && (int) $display_meta['count'] > 1 ) {
				echo esc_html( sprintf( pili__( '已选择 %d 项' ), (int) $display_meta['count'] ) );
			} else {
				echo '<span class="pili-select-option-content inline-flex items-center gap-2 min-w-0 max-w-full">';
				$this->echo_option_icon( $display_meta );
				echo '<span class="pili-select-option-label truncate">' . esc_html( $display_meta['label'] ) . '</span>';
				echo '</span>';
			}
			echo '</span>';
			echo '<svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="absolute right-2 sm:right-1 md:right-1 top-1/2 -translate-y-1/2 w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 text-gray-500 pili-select-arrow">';
			echo '<path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd" />';
			echo '</svg>';
			echo '</button>';
			// 清空按钮必须在触发按钮外侧，避免非法嵌套 <button> 导致浏览器拆 DOM、点击失效。
			if ( $args['clearable'] && ! empty( $this->value ) ) {
				echo '<button type="button" class="pili-select-clear absolute right-8 sm:right-7 md:right-6 top-1/2 -translate-y-1/2 w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 touch-manipulation" title="' . pili_esc_attr__( '清空选择' ) . '">';
				echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3 h-3 sm:w-2.5 sm:h-2.5 md:w-2.5 md:h-2.5">';
				echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
				echo '</svg>';
				echo '</button>';
			}
		}

		/**
		 * 渲染下拉列表
		 *
		 * @since 1.0
		 *
		 * @param array  $args       字段配置参数
		 * @param string $listbox_id 列表框ID
		 * @param array  $options    选项数组
		 */
		private function render_dropdown( $args, $listbox_id, $options ) {
			echo '<div class="pili-select-dropdown absolute z-10 mt-1 w-full bg-white shadow-lg ring-1 ring-black/5 rounded-md hidden transition-all duration-200" style="max-height: ' . esc_attr( $args['max_height'] ) . 'px;">';
			if ( $args['searchable'] ) {
				echo '<div class="p-3 sm:p-2 md:p-2 border-b border-gray-100">';
				echo '<input type="text" class="pili-select-search w-full px-4 py-3 sm:px-3 sm:py-2 md:px-3 md:py-2 text-base sm:text-sm md:text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150 touch-manipulation" placeholder="' . esc_attr( $args['search_placeholder'] ) . '">';
				echo '</div>';
			}
			if ( $args['multiple'] && $args['select_all'] ) {
				echo '<div class="p-3 sm:p-2 md:p-2 border-b border-gray-100">';
				echo '<button type="button" class="pili-select-all w-full text-left px-4 py-3 sm:px-3 sm:py-2 md:px-3 md:py-2 text-base sm:text-sm md:text-sm text-blue-600 hover:bg-blue-50 rounded-md focus:outline-none focus:bg-blue-50 transition-colors duration-150 touch-manipulation">' . pili_esc_html__( '全选' ) . '</button>';
				echo '</div>';
			}
			echo '<ul role="listbox" tabindex="-1" aria-labelledby="' . esc_attr( $listbox_id . '_label' ) . '" class="pili-select-options py-2 sm:py-1 md:py-1 overflow-auto" style="max-height: ' . esc_attr( $args['max_height'] - 100 ) . 'px;">';
			$this->render_options( $options, $args );
			echo '</ul>';
			echo '<div class="pili-select-no-results hidden p-6 sm:p-4 md:p-4 text-center text-gray-500 text-base sm:text-sm md:text-sm">';
			echo esc_html( $args['no_results_text'] );
			echo '</div>';
			echo '<div class="pili-select-loading hidden p-6 sm:p-4 md:p-4 text-center text-gray-500 text-base sm:text-sm md:text-sm">';
			echo esc_html( $args['loading_text'] );
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

		/**
		 * 渲染选项列表
		 *
		 * @since 1.0
		 *
		 * @param array $options 选项数组
		 * @param array $args    字段配置参数
		 */
		private function render_options( $options, $args ) {
			$option_index = 0;
			foreach ( $options as $option_key => $option_value ) {
				if ( $this->is_option_group( $option_value ) ) {
					echo '<li class="px-4 py-3 sm:px-3 sm:py-2 md:px-3 md:py-2 text-sm sm:text-xs md:text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">';
					echo esc_html( $option_key );
					echo '</li>';
					foreach ( $option_value as $sub_key => $sub_value ) {
						$this->render_single_option( $sub_key, $sub_value, $option_index, $args );
						$option_index++;
					}
				} else {
					$this->render_single_option( $option_key, $option_value, $option_index, $args );
					$option_index++;
				}
			}
		}

		/**
		 * 渲染单个选项
		 *
		 * @since 1.0
		 *
		 * @param string       $key    选项键
		 * @param string|array $value  选项值或富选项
		 * @param int          $index  选项索引
		 * @param array        $args   字段配置参数
		 */
		private function render_single_option( $key, $value, $index, $args ) {
			$meta        = $this->normalize_option_meta( $value );
			$is_selected = $args['multiple']
				? in_array( (string) $key, array_map( 'strval', (array) $this->value ), true )
				: (string) $key === (string) $this->value;
			$selected_class = $is_selected ? 'bg-blue-600 text-white' : 'text-gray-900 hover:bg-gray-50';
			$font_weight    = $is_selected ? 'font-semibold' : 'font-normal';

			$attrs  = ' data-value="' . esc_attr( $key ) . '"';
			$attrs .= ' data-text="' . esc_attr( $meta['label'] ) . '"';
			if ( '' !== $meta['icon'] ) {
				$attrs .= ' data-icon="' . esc_attr( $meta['icon'] ) . '"';
			}
			if ( '' !== $meta['icon_url'] ) {
				$attrs .= ' data-icon-url="' . esc_url( $meta['icon_url'] ) . '"';
			}

			echo '<li id="listbox-option-' . esc_attr( $index ) . '" role="option" aria-selected="' . ( $is_selected ? 'true' : 'false' ) . '" class="pili-select-option relative cursor-pointer py-3 px-4 sm:py-2 sm:px-3 md:py-2 md:px-3 pr-12 sm:pr-10 md:pr-9 select-none transition-colors duration-150 touch-manipulation ' . esc_attr( $selected_class ) . '"' . $attrs . '>';
			echo '<span class="pili-select-option-content flex items-center gap-2 min-w-0 pr-1">';
			$this->echo_option_icon( $meta );
			echo '<span class="pili-select-option-label block truncate text-base sm:text-sm md:text-sm ' . esc_attr( $font_weight ) . '">' . esc_html( $meta['label'] ) . '</span>';
			echo '</span>';
			$checkmark_visibility = $is_selected ? '' : 'hidden';
			echo '<span class="pili-select-option-check absolute inset-y-0 right-0 flex items-center pr-4 sm:pr-3 md:pr-3 ' . esc_attr( $checkmark_visibility ) . '">';
			echo '<svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="w-6 h-6 sm:w-5 sm:h-5 md:w-4 md:h-4">';
			echo '<path d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" fill-rule="evenodd" />';
			echo '</svg>';
			echo '</span>';
			echo '</li>';
		}

		/**
		 * 输出选项图标 HTML。
		 *
		 * @param array $meta 规范化后的选项 meta。
		 */
		private function echo_option_icon( array $meta ) {
			if ( '' !== $meta['icon_html'] ) {
				echo '<span class="pili-select-option-icon shrink-0 inline-flex items-center justify-center leading-none" aria-hidden="true">' . wp_kses_post( $meta['icon_html'] ) . '</span>';
				return;
			}
			if ( '' !== $meta['icon_url'] ) {
				echo '<span class="pili-select-option-icon shrink-0 inline-flex items-center justify-center leading-none" aria-hidden="true">';
				echo '<img src="' . esc_url( $meta['icon_url'] ) . '" alt="" class="pili-select-option-icon-img w-4 h-4 object-contain" width="16" height="16" />';
				echo '</span>';
				return;
			}
			if ( '' === $meta['icon'] ) {
				return;
			}
			$icon = $meta['icon'];
			echo '<span class="pili-select-option-icon shrink-0 inline-flex items-center justify-center leading-none" aria-hidden="true">';
			if ( $this->is_icon_class( $icon ) ) {
				echo '<i class="' . esc_attr( $icon ) . '"></i>';
			} else {
				echo '<span class="pili-select-option-icon-emoji text-base leading-none">' . esc_html( $icon ) . '</span>';
			}
			echo '</span>';
		}

		/**
		 * 判断 icon 是否为 CSS class（dashicons / Font Awesome 等）。
		 *
		 * @param string $icon Icon string.
		 * @return bool
		 */
		private function is_icon_class( $icon ) {
			$icon = (string) $icon;
			if ( '' === $icon ) {
				return false;
			}
			return (bool) preg_match( '/(?:^|\s)(dashicons|fa[srb]?|ri-|el-|icon-|material-icons)/i', $icon );
		}

		/**
		 * 是否为分组选项（数组且不是富选项）。
		 *
		 * @param mixed $value Option value.
		 * @return bool
		 */
		private function is_option_group( $value ) {
			return is_array( $value ) && ! $this->is_rich_option( $value );
		}

		/**
		 * 是否为富选项（含 label/icon 等）。
		 *
		 * @param mixed $value Option value.
		 * @return bool
		 */
		private function is_rich_option( $value ) {
			if ( ! is_array( $value ) ) {
				return false;
			}
			return array_key_exists( 'label', $value )
				|| array_key_exists( 'text', $value )
				|| array_key_exists( 'icon', $value )
				|| array_key_exists( 'icon_html', $value )
				|| array_key_exists( 'icon_url', $value );
		}

		/**
		 * 规范化选项 meta。
		 *
		 * @param mixed $value Raw option value.
		 * @return array{label:string,icon:string,icon_html:string,icon_url:string}
		 */
		private function normalize_option_meta( $value ) {
			if ( $this->is_rich_option( $value ) ) {
				$label = '';
				if ( isset( $value['label'] ) ) {
					$label = (string) $value['label'];
				} elseif ( isset( $value['text'] ) ) {
					$label = (string) $value['text'];
				}
				return array(
					'label'     => $label,
					'icon'      => isset( $value['icon'] ) ? (string) $value['icon'] : '',
					'icon_html' => isset( $value['icon_html'] ) ? (string) $value['icon_html'] : '',
					'icon_url'  => isset( $value['icon_url'] ) ? (string) $value['icon_url'] : '',
				);
			}
			return array(
				'label'     => is_scalar( $value ) ? (string) $value : '',
				'icon'      => '',
				'icon_html' => '',
				'icon_url'  => '',
			);
		}

		/**
		 * 渲染隐藏的原生 select
		 *
		 * @since 1.0
		 *
		 * @param array $args    字段配置参数
		 * @param array $options 选项数组
		 */
		private function render_native_select( $args, $options ) {
			$multiple_attr = $args['multiple'] ? ' multiple' : '';
			$multiple_name = $args['multiple'] ? '[]' : '';
			echo '<select name="' . esc_attr( $this->field_name( $multiple_name ) ) . '" class="pili-select-native hidden"' . $multiple_attr . $this->field_attributes() . '>';
			if ( ! $args['multiple'] && empty( $this->value ) ) {
				echo '<option value=""></option>';
			}
			foreach ( $options as $option_key => $option_value ) {
				if ( $this->is_option_group( $option_value ) ) {
					echo '<optgroup label="' . esc_attr( $option_key ) . '">';
					foreach ( $option_value as $sub_key => $sub_value ) {
						$meta     = $this->normalize_option_meta( $sub_value );
						$selected = $args['multiple']
							? ( in_array( (string) $sub_key, array_map( 'strval', (array) $this->value ), true ) ? ' selected' : '' )
							: ( (string) $sub_key === (string) $this->value ? ' selected' : '' );
						echo '<option value="' . esc_attr( $sub_key ) . '"' . $selected . '>' . esc_html( $meta['label'] ) . '</option>';
					}
					echo '</optgroup>';
				} else {
					$meta     = $this->normalize_option_meta( $option_value );
					$selected = $args['multiple']
						? ( in_array( (string) $option_key, array_map( 'strval', (array) $this->value ), true ) ? ' selected' : '' )
						: ( (string) $option_key === (string) $this->value ? ' selected' : '' );
					echo '<option value="' . esc_attr( $option_key ) . '"' . $selected . '>' . esc_html( $meta['label'] ) . '</option>';
				}
			}
			echo '</select>';
		}

		/**
		 * 获取按钮区展示 meta。
		 *
		 * @param array $args    字段配置参数
		 * @param array $options 选项数组
		 * @return array{label:string,icon:string,icon_html:string,icon_url:string,count:int}
		 */
		private function get_display_meta( $args, $options ) {
			$empty = array(
				'label'     => '',
				'icon'      => '',
				'icon_html' => '',
				'icon_url'  => '',
				'count'     => 0,
			);
			// 多选 value 为 array；不可 (string) 强转，否则 empty([]) 时会 Array to string conversion。
			if ( is_array( $this->value ) ) {
				if ( empty( $this->value ) ) {
					return $empty;
				}
			} elseif ( empty( $this->value ) && '0' !== (string) $this->value ) {
				return $empty;
			}
			if ( $args['multiple'] ) {
				$selected = (array) $this->value;
				$count    = count( $selected );
				if ( 0 === $count ) {
					return $empty;
				}
				if ( 1 === $count ) {
					$meta          = $this->get_option_meta( reset( $selected ), $options );
					$meta['count'] = 1;
					return $meta;
				}
				$empty['count'] = $count;
				return $empty;
			}
			$meta          = $this->get_option_meta( $this->value, $options );
			$meta['count'] = 1;
			return $meta;
		}

		/**
		 * 按值查找选项 meta。
		 *
		 * @param mixed $value   选项值
		 * @param array $options 选项数组
		 * @return array{label:string,icon:string,icon_html:string,icon_url:string}
		 */
		private function get_option_meta( $value, $options ) {
			foreach ( $options as $option_key => $option_value ) {
				if ( $this->is_option_group( $option_value ) ) {
					foreach ( $option_value as $sub_key => $sub_value ) {
						if ( (string) $sub_key === (string) $value ) {
							return $this->normalize_option_meta( $sub_value );
						}
					}
				} elseif ( (string) $option_key === (string) $value ) {
					return $this->normalize_option_meta( $option_value );
				}
			}
			return array(
				'label'     => is_scalar( $value ) ? (string) $value : '',
				'icon'      => '',
				'icon_html' => '',
				'icon_url'  => '',
			);
		}

		/**
		 * 获取字段选项数组
		 *
		 * @since 1.0
		 *
		 * @param array $args 字段配置参数
		 * @return array 选项数组
		 */
		public function get_field_options( $args ) {
			if ( ! empty( $args['ajax'] ) ) {
				return array();
			}
			$options = isset( $args['options'] ) ? $args['options'] : array();
			if ( function_exists( 'pili_resolve_field_options' ) ) {
				return pili_resolve_field_options( $options );
			}
			return is_array( $options ) ? $options : array();
		}

		/**
		 * 加载字段资源
		 *
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
			$handle = pili_asset_handle( 'field-select' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/select.css',
				array(),
				PILI_CORE_VERSION
			);
			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/select.js',
				$deps,
				PILI_CORE_VERSION,
				true
			);
			pili_localize_bag( $handle, 'select',
				array(
					'no_results_text' => pili__( '未找到匹配项' ),
					'loading_text' => pili__( '加载中…' ),
					'select_all_text' => pili__( '全选' ),
					'clear_all_text' => pili__( '清空选择' ),
					'selected_text' => pili__( '已选择 %d 项' ),
					'placeholder' => pili__( '请选择…' ),
					'search_placeholder' => pili__( '搜索选项…' ),
					'clear_title' => pili__( '清空选择' ),
				)
			);
		}

		/**
		 * 验证字段值
		 *
		 * @since 1.0
		 *
		 * @param mixed $value 要验证的值
		 * @return mixed 验证后的值
		 */
		public function validate( $value ) {
			if ( empty( $value ) && '0' !== (string) $value ) {
				return '';
			}
			$args       = wp_parse_args(
				$this->field,
				array(
					'multiple' => false,
					'options'  => array(),
				)
			);
			$options    = $this->get_field_options( $args );
			$valid_keys = $this->get_valid_option_keys( $options );
			if ( $args['multiple'] ) {
				$value     = is_array( $value ) ? $value : array( $value );
				$validated = array();
				foreach ( $value as $single_value ) {
					if ( in_array( (string) $single_value, array_map( 'strval', $valid_keys ), true ) ) {
						$validated[] = sanitize_text_field( $single_value );
					}
				}
				return $validated;
			}
			if ( in_array( (string) $value, array_map( 'strval', $valid_keys ), true ) ) {
				return sanitize_text_field( $value );
			}
			return '';
		}

		/**
		 * 获取有效的选项键
		 *
		 * @since 1.0
		 *
		 * @param array $options 选项数组
		 * @return array 有效的选项键数组
		 */
		private function get_valid_option_keys( $options ) {
			$keys = array();
			foreach ( $options as $option_key => $option_value ) {
				if ( $this->is_option_group( $option_value ) ) {
					$keys = array_merge( $keys, array_keys( $option_value ) );
				} else {
					$keys[] = $option_key;
				}
			}
			return $keys;
		}
	}
}
