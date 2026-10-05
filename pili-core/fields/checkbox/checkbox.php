<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Checkbox 字段类 - 基础字段
 *
 * 提供复选框功能，作为基础字段供其他复杂字段组合使用。
 * 支持单选、多选、搜索、分组等高级功能。
 *
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_checkbox' ) ) {

    /**
     * PILI_Field_checkbox 复选框字段类
     *
     * 基础字段类，提供完整的复选框功能：
     * - 单选和多选模式
     * - 选项搜索和过滤
     * - 全选/反选功能
     * - 选项分组
     * - 数量限制验证
     * - 无障碍访问优化
     *
     * @since 1.1.0
     */
    class PILI_Field_checkbox extends PILI_Fields {

        /**
         * 构造函数
         *
         * 初始化checkbox字段实例。
         *
         * @since 1.1.0
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
         * 渲染checkbox字段 - 基础字段增强版
         *
         * 输出功能完整的复选框字段，支持搜索、分组、验证等功能。
         *
         * @since 1.1.0
         */
        public function render() {

            $settings = $this->get_field_settings();

            $options = $this->process_options( $settings['options'] );
            $value = is_array( $this->value ) ? $this->value : array();

            echo $this->field_before();

            $container_id = 'pili-checkbox-' . uniqid();
            echo '<div class="pili-checkbox-field bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden"'
				. ' id="' . esc_attr( $container_id ) . '"'
				. ' data-field-id="' . esc_attr( $this->field['id'] ) . '"'
				. ' data-min-required="' . esc_attr( (string) (int) $settings['required_min'] ) . '"'
				. ' data-max-allowed="' . esc_attr( (string) (int) $settings['required_max'] ) . '"'
				. ' data-i18n-selected-count="' . esc_attr( pili__( '已选择 %1$d / %2$d 项' ) ) . '"'
				. ' data-i18n-min-required="' . esc_attr( pili__( '至少需要选择 %d 项' ) ) . '"'
				. ' data-i18n-max-exceeded="' . esc_attr( pili__( '最多只能选择 %d 项' ) ) . '"'
				. ' data-i18n-no-results="' . esc_attr( pili__( '没有找到匹配的选项' ) ) . '"'
				. '>';

            if ( $settings['show_count'] ) {
                echo '<div class="flex items-center justify-end p-4 border-b border-gray-200">';
                echo '<div class="pili-checkbox-counter text-sm text-blue-600">';
                echo esc_html(
					sprintf(
						/* translators: 1: selected count, 2: total options */
						pili__( '已选择 %1$d / %2$d 项' ),
						count( $value ),
						count( $options )
					)
				);
                echo '</div>';
                echo '</div>';
            }

            if ( $settings['searchable'] || $settings['select_all'] ) {
                $this->render_toolbar( $settings, $options, $value );
            }

            echo '<div class="pili-checkbox-options p-4">';
            $this->render_options( $options, $value, $settings );
            echo '</div>';

            $this->render_hidden_inputs( $value );

            echo '<div class="pili-checkbox-validation hidden mt-3 mx-4 mb-4">';
            echo '<div class="p-3 bg-red-50 border border-red-200 rounded-md">';
            echo '<div class="flex">';
            echo '<div class="flex-shrink-0">';
            echo '<svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">';
            echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />';
            echo '</svg>';
            echo '</div>';
            echo '<div class="ml-3">';
            echo '<p class="text-sm text-red-800 pili-checkbox-validation-message"></p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '</div>'; 

            echo $this->field_after();
        }

        /**
         * 处理选项数据
         */
        private function process_options( $options ) {
            if ( empty( $options ) ) {
                return array();
            }

            $processed = array();

            foreach ( $options as $key => $option ) {
                if ( is_string( $option ) ) {
                    $processed[ $key ] = array(
                        'label' => $option,
                        'description' => '',
                    );
                } else {
                    $processed[ $key ] = wp_parse_args( $option, array(
                        'label' => '',
                        'description' => '',
                        'disabled' => false,
                    ) );
                }
            }

            return $processed;
        }

        /**
         * 渲染工具栏
         */
        private function render_toolbar( $args, $options, $value ) {
            echo '<div class="pili-checkbox-toolbar flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0 p-4 bg-gray-50 border-b border-gray-200">';

            echo '<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">';

            if ( $args['searchable'] && count( $options ) > 5 ) {
                echo '<div class="relative">';
                echo '<input type="text" class="pili-checkbox-search w-full sm:w-48 md:w-64 pl-8 sm:pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-md sm:rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="' . pili_esc_attr__( '搜索选项...' ) . '">';
                echo '<div class="absolute inset-y-0 left-0 pl-2 sm:pl-3 flex items-center pointer-events-none">';
                echo '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>';
                echo '</svg>';
                echo '</div>';
                echo '</div>';
            }

            if ( $args['select_all'] && count( $options ) > 1 ) {
                echo '<div class="flex gap-2">';
                echo '<button type="button" class="pili-checkbox-select-all inline-flex items-center justify-center gap-1 sm:gap-2 px-3 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md sm:rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-colors duration-200">';
                echo '<svg class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                echo '</svg>';
                echo '<span class="ml-1">' . pili_esc_html__( '全选' ) . '</span>';
                echo '</button>';

                echo '<button type="button" class="pili-checkbox-clear-all inline-flex items-center justify-center gap-1 sm:gap-2 px-3 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md sm:rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-colors duration-200" style="display: none;">';
                echo '<svg class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                echo '</svg>';
                echo '<span class="ml-1">' . pili_esc_html__( '清空' ) . '</span>';
                echo '</button>';
                echo '</div>';
            }

            echo '</div>';

            if ( $args['show_count'] ) {
                echo '<div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-xs sm:text-sm text-gray-600 text-center sm:text-left">';
                echo '<span class="pili-checkbox-count">' . esc_html(
					sprintf(
						/* translators: 1: selected count, 2: total options */
						pili__( '已选择 %1$d / %2$d 项' ),
						count( $value ),
						count( $options )
					)
				) . '</span>';

                if ( $args['required_min'] > 0 || $args['required_max'] > 0 ) {
                    $min_n = (int) $args['required_min'];
                    $max_n = (int) $args['required_max'];
                    if ( $min_n > 0 && $max_n > 0 ) {
                        $requirement = sprintf(
                            /* translators: 1: min selections, 2: max selections */
                            pili__( '至少 %1$d 项，最多 %2$d 项' ),
                            $min_n,
                            $max_n
                        );
                    } elseif ( $min_n > 0 ) {
                        $requirement = sprintf(
                            /* translators: %d: minimum selections */
                            pili__( '至少 %d 项' ),
                            $min_n
                        );
                    } else {
                        $requirement = sprintf(
                            /* translators: %d: maximum selections */
                            pili__( '最多 %d 项' ),
                            $max_n
                        );
                    }

                    if ( $requirement ) {
                        echo '<span class="text-xs text-gray-500">(' . esc_html( $requirement ) . ')</span>';
                    }
                }
                echo '</div>';
            }

            echo '</div>';
        }

        /**
         * 渲染选项
         */
        private function render_options( $options, $value, $args ) {
            $has_descriptions = false;
            foreach ( $options as $option ) {
                if ( ! empty( $option['description'] ) ) {
                    $has_descriptions = true;
                    break;
                }
            }

            echo '<fieldset class="mt-4">';
            echo '<legend class="sr-only">' . esc_html( $this->field['title'] ?? 'Options' ) . '</legend>';

            if ( $has_descriptions ) {
                echo '<div class="space-y-4">';
            } else {
                echo '<div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-200">';
            }

            foreach ( $options as $key => $option ) {
                $this->render_single_option( $key, $option, $value, $args, $has_descriptions );
            }

            echo '</div>';
            echo '</fieldset>';
        }

        /**
         * 渲染单个选项 - 无障碍增强版
         */
        private function render_single_option( $key, $option, $value, $args, $has_descriptions = false ) {
            $option_id = $this->field['id'] . '_' . $key;
            $input_name = $this->field_name( '[]' );
            $is_checked = is_array( $value ) && in_array( $key, $value );
            $is_disabled = ! empty( $option['disabled'] );

            if ( $has_descriptions ) {
                echo '<div class="pili-checkbox-option group relative bg-white rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-all duration-200 cursor-pointer" ';
                echo 'role="button" tabindex="0" ';
                echo 'aria-labelledby="' . esc_attr( $option_id . '_label' ) . '" ';
                echo 'aria-describedby="' . esc_attr( $option_id . '_desc' ) . '" ';
                echo 'aria-checked="' . ( $is_checked ? 'true' : 'false' ) . '" ';
                echo 'data-option-key="' . esc_attr( $key ) . '" ';
                echo ( $is_disabled ? 'aria-disabled="true"' : '' ) . '>';

                echo '<label for="' . esc_attr( $option_id ) . '" class="absolute inset-0 cursor-pointer" aria-hidden="true"></label>';

                echo '<div class="p-4">';
                echo '<div class="flex items-start gap-3">';

                echo '<div class="flex h-6 shrink-0 items-center">';
                echo '<input id="' . esc_attr( $option_id ) . '" type="checkbox" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $key ) . '" ';
                echo 'class="h-4 w-4 rounded border-gray-300 text-blue-600 pili-checkbox-input transition-colors duration-200 relative z-10"';
                echo checked( $is_checked, true, false ) . ( $is_disabled ? ' disabled' : '' ) . '>';
                echo '</div>';

                echo '<div class="flex-1 min-w-0">';
                echo '<div id="' . esc_attr( $option_id . '_label' ) . '" class="text-sm font-medium text-gray-900 group-hover:text-blue-900 transition-colors duration-200">';
                echo esc_html( $option['label'] );
                echo '</div>';
                if ( ! empty( $option['description'] ) ) {
                    echo '<p id="' . esc_attr( $option_id . '_desc' ) . '" class="mt-1 text-sm text-gray-500 group-hover:text-blue-700 transition-colors duration-200">' . esc_html( $option['description'] ) . '</p>';
                }
                echo '</div>';

                echo '</div>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="pili-checkbox-option group relative flex items-center justify-between hover:bg-blue-50 transition-all duration-200 cursor-pointer focus-within:bg-blue-50" ';
                echo 'role="button" tabindex="0" ';
                echo 'aria-labelledby="' . esc_attr( $option_id . '_label' ) . '" ';
                echo 'aria-checked="' . ( $is_checked ? 'true' : 'false' ) . '" ';
                echo 'data-option-key="' . esc_attr( $key ) . '" ';
                echo ( $is_disabled ? 'aria-disabled="true"' : '' ) . '>';

                echo '<label for="' . esc_attr( $option_id ) . '" class="absolute inset-0 cursor-pointer" aria-hidden="true"></label>';

                echo '<div class="flex items-center justify-between px-4 py-3 w-full">';

                echo '<div class="flex-1 min-w-0">';
                echo '<div id="' . esc_attr( $option_id . '_label' ) . '" class="text-sm font-medium text-gray-900 select-none group-hover:text-blue-900 transition-colors duration-200">';
                echo esc_html( $option['label'] );
                echo '</div>';
                echo '</div>';

                echo '<div class="flex h-6 shrink-0 items-center ml-3">';
                echo '<input id="' . esc_attr( $option_id ) . '" type="checkbox" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $key ) . '" ';
                echo 'class="h-4 w-4 rounded border-gray-300 text-blue-600 pili-checkbox-input transition-colors duration-200 relative z-10"';
                echo checked( $is_checked, true, false ) . ( $is_disabled ? ' disabled' : '' ) . '>';
                echo '</div>';

                echo '</div>';
                echo '</div>';
            }
        }

        /**
         * 渲染隐藏输入字段
         */
        private function render_hidden_inputs( $value ) {
            if ( is_array( $value ) && ! empty( $value ) ) {
                foreach ( $value as $val ) {
                    echo '<input type="hidden" name="' . esc_attr( $this->field_name( '[]' ) ) . '" value="' . esc_attr( $val ) . '" class="pili-checkbox-hidden-input">';
                }
            } else {
                echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="" class="pili-checkbox-empty-input">';
            }
        }

        /**
         * 获取字段设置
         *
         * 合并默认设置和用户自定义设置。
         *
         * @since 1.1.0
         *
         * @return array 完整的字段设置
         */
        private function get_field_settings() {

            $default_settings = array(
                'desc'            => ! empty( $this->field['desc'] ) ? $this->field['desc'] : '',
                'options'         => ! empty( $this->field['options'] )
					? ( function_exists( 'pili_resolve_field_options' )
						? pili_resolve_field_options( $this->field['options'] )
						: ( is_array( $this->field['options'] ) ? $this->field['options'] : array() ) )
					: array(),
                'searchable'      => ! empty( $this->field['searchable'] ) ? $this->field['searchable'] : false,
                'select_all'      => ! empty( $this->field['select_all'] ) ? $this->field['select_all'] : false,
                'show_count'      => ! empty( $this->field['show_count'] ) ? $this->field['show_count'] : false,
                'required_min'    => ! empty( $this->field['required_min'] ) ? intval( $this->field['required_min'] ) : 0,
                'required_max'    => ! empty( $this->field['required_max'] ) ? intval( $this->field['required_max'] ) : 0,
                'inline'          => ! empty( $this->field['inline'] ) ? $this->field['inline'] : false,
                'columns'         => ! empty( $this->field['columns'] ) ? intval( $this->field['columns'] ) : 1,
                'group_by'        => ! empty( $this->field['group_by'] ) ? $this->field['group_by'] : '',
            );

            return wp_parse_args( $this->field, $default_settings );
        }

        /**
         * 加载字段资源
         *
         * 加载checkbox字段需要的JavaScript文件。
         *
         * @since 1.1.0
         */
        public function enqueue() {
			$deps = array( 'jquery' );
			if ( function_exists( 'pili_enqueue_js_i18n_runtime' ) ) {
				$i18n_rt = pili_enqueue_js_i18n_runtime();
				if ( is_string( $i18n_rt ) && '' !== $i18n_rt ) {
					$deps[] = $i18n_rt;
				}
			}

            $handle = pili_asset_handle( 'field-checkbox' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/checkbox.js',
                $deps,
                defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : PILI_Setup::$version,
                true
            );

			$l10n = array(
				'minRequired' => pili__( '至少需要选择 %d 项' ),
				'maxExceeded' => pili__( '最多只能选择 %d 项' ),
				'searchPlaceholder' => pili__( '搜索…' ),
				'selectAll' => pili__( '全选' ),
				'deselectAll' => pili__( '取消全选' ),
				'noResults' => pili__( '无结果' ),
				'selectedCount' => pili__( '已选择 %1$d / %2$d 项' ),
			);

            pili_localize_bag(
				$handle,
				'checkbox',
				array_merge(
					$l10n,
					array(
						'messages' => $l10n,
					)
				)
			);
        }

        /**
         * 验证字段值
         */
        public function validate( $value ) {
            $value = is_array( $value ) ? $value : array();
            $value = array_filter( $value );

            if ( ! empty( $this->field['required_min'] ) ) {
                $min = intval( $this->field['required_min'] );
                if ( count( $value ) < $min ) {
					return new WP_Error( 'min_required', sprintf( pili__( '至少需要选择 %d 项' ), $min ) );
                }
            }

            if ( ! empty( $this->field['required_max'] ) ) {
                $max = intval( $this->field['required_max'] );
                if ( count( $value ) > $max ) {
                    return new WP_Error( 'max_exceeded', sprintf( pili__( '最多只能选择 %d 项' ), $max ) );
                }
            }

            return $value;
        }
    }
}