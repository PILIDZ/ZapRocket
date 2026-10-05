<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 重复器字段类型
 * 
 * 这个类实现了现代化的重复器字段功能。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_repeater' ) ) {
    
    /**
     * PILI_Field_repeater 重复器字段类 - 响应式自适应设计
     *
     * 提供现代化重复器字段的完整功能，包括：
     * - 拖拽排序
     * - 折叠展开
     * - 批量操作
     * - 实时预览
     * - 键盘快捷键
     * - 无障碍访问
     * - 自动保存
     * - 数据验证
     * - 完全响应式设计（移动端优先）
     * - 触摸友好的交互体验
     * - 自适应布局和间距
     * - 跨设备兼容性优化
     *
     * @since 1.0
     */
    class PILI_Field_repeater extends PILI_Fields {
        /**
         * 构造函数
         * 
         * 初始化重复器字段实例。
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
         * 与 PILI_Options::encode_field_dependency_attr 一致：子字段不经 options render_field，须在此输出 data-dependency 供 admin-ui-deps 联动。
         *
         * @param array<int|string,mixed> $dependency dependency 或 dependency[]。
         * @return string JSON 或空。
         */
        private function repeater_encode_field_dependency_attr( $dependency ) {
            if ( empty( $dependency ) || ! is_array( $dependency ) ) {
                return '';
            }
            if ( isset( $dependency[0] ) && is_array( $dependency[0] ) ) {
                $list = $dependency;
            } else {
                $list = array( $dependency );
            }
            $rules = array();
            foreach ( $list as $rule ) {
                if ( ! is_array( $rule ) || count( $rule ) < 3 ) {
                    continue;
                }
                $rules[] = array(
                    'field' => (string) $rule[0],
                    'op'    => (string) $rule[1],
                    'value' => $rule[2],
                );
            }
            if ( empty( $rules ) ) {
                return '';
            }
            $out = wp_json_encode( $rules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE );
            return false === $out ? '' : $out;
        }

        /**
         * 打开与 PILI_Options::render_field 一致的外层 .space-y-2（含 data-dependency）。
         *
         * @param array<string,mixed> $field 子字段配置。
         */
        private function repeater_open_subfield_wrapper( $field ) {
            if ( empty( $field['id'] ) ) {
                return;
            }
            $ftype = ! empty( $field['type'] ) ? (string) $field['type'] : '';
            echo '<div class="space-y-2" data-field-id="' . esc_attr( (string) $field['id'] ) . '" data-field-type="' . esc_attr( $ftype ) . '"';
            if ( array_key_exists( 'default', $field ) ) {
                echo ' data-field-default="' . esc_attr( wp_json_encode( $field['default'] ) ) . '"';
            }
            if ( ! empty( $field['required'] ) ) {
                echo ' data-required="true"';
            }
            $dep_json = ! empty( $field['dependency'] ) ? $this->repeater_encode_field_dependency_attr( $field['dependency'] ) : '';
            if ( '' !== $dep_json ) {
                echo ' data-dependency="' . esc_attr( $dep_json ) . '"';
            }
            echo '>';
        }

        /**
         * 渲染重复器字段
         *
         * 输出现代化重复器字段的HTML代码。
         *
         * @since 1.0
         */
        public function render() {
            if ( preg_match( '/'. preg_quote( '['. $this->field['id'] .']' ) .'/', $this->unique ) ) {
                echo '<div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4">';
                echo '<div class="flex">';
                echo '<div class="flex-shrink-0">';
                echo '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
                echo '</svg>';
                echo '</div>';
                echo '<div class="ml-3">';
                echo '<h3 class="text-sm font-medium text-red-800">' . pili_esc_html__( '字段ID冲突错误' ) . '</h3>';
                echo '<div class="mt-2 text-sm text-red-700">';
                echo '<p>' . esc_html(
                    sprintf(
                        /* translators: %s: field id */
                        pili__( '重复器字段ID "%s" 与父级字段冲突，请使用不同的字段ID。' ),
                        (string) $this->field['id']
                    )
                ) . '</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                return;
            }
            $fields = ! empty( $this->field['fields'] ) ? $this->field['fields'] : array();
            $max = ! empty( $this->field['max'] ) ? intval( $this->field['max'] ) : 0;
            $min = ! empty( $this->field['min'] ) ? intval( $this->field['min'] ) : 0;
            $max_enabled = $this->get_max_enabled();
            $max_enabled_skip_json = $this->get_max_enabled_skip_json();
            $max_enabled_field = $this->get_max_enabled_field();
            $button_title = ! empty( $this->field['button_title'] ) ? $this->field['button_title'] : pili__( '添加项目' );
            $sortable = ! empty( $this->field['sortable'] ) ? $this->field['sortable'] : true;
            $collapsible = ! empty( $this->field['collapsible'] ) ? $this->field['collapsible'] : true;
            // Pilipost 约定：可折叠项默认收起（default_collapsed => true）；需默认展开时显式 false。
            // 注意：属性名是 default_collapsed —— true=收起，false=展开（勿与中文「默认关掉收起」搞反）。
            $default_collapsed = true;
            if ( array_key_exists( 'default_collapsed', $this->field ) ) {
                $default_collapsed = (bool) $this->field['default_collapsed'];
            }
            $show_preview = ! empty( $this->field['show_preview'] ) ? $this->field['show_preview'] : true;
            $preview_field = ! empty( $this->field['preview_field'] ) ? $this->field['preview_field'] : '';
            $title_config = $this->build_title_config( $fields );
            $title_config_json = wp_json_encode( $title_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE );
            $layout = ! empty( $this->field['layout'] ) ? $this->field['layout'] : 'default';
            $color = ! empty( $this->field['color'] ) ? $this->field['color'] : 'blue';
            $field_name = $this->field_name();
            $is_empty   = empty( $this->value ) || ! is_array( $this->value );
            echo $this->field_before();
            echo '<div class="pili-repeater-field w-full touch-manipulation" data-field-id="' . esc_attr( $this->field['id'] ) . '" data-field-name="' . esc_attr( $field_name ) . '" data-max="' . esc_attr( $max ) . '" data-min="' . esc_attr( $min ) . '" data-max-enabled="' . esc_attr( $max_enabled ) . '" data-max-enabled-field="' . esc_attr( $max_enabled_field ) . '" data-max-enabled-skip="' . esc_attr( $max_enabled_skip_json ) . '" data-sortable="' . esc_attr( $sortable ? 'true' : 'false' ) . '" data-collapsible="' . esc_attr( $collapsible ? 'true' : 'false' ) . '" data-default-collapsed="' . esc_attr( ( $collapsible && $default_collapsed ) ? 'true' : 'false' ) . '" data-layout="' . esc_attr( $layout ) . '" data-color="' . esc_attr( $color ) . '" data-preview-field="' . esc_attr( $preview_field ) . '" data-title-config="' . esc_attr( false === $title_config_json ? '' : $title_config_json ) . '">';
            /*
             * preserve_existing=true 时：清空所有行后表单不会提交该字段名，
             * 保存会跳过并保留库中旧值。空状态用隐藏哨兵提交空串，sanitize 再写成 []。
             */
            echo '<input type="hidden" class="pili-repeater-empty-sentinel" name="' . esc_attr( $field_name ) . '" value=""' . ( $is_empty ? '' : ' disabled="disabled"' ) . ' />';
            echo '<style>
                @media (max-width: 640px) {
                    .pili-repeater-field .pili-repeater-toolbar {
                        flex-direction: column !important;
                        gap: 0.75rem !important;
                        padding: 1rem !important;
                    }
                    .pili-repeater-field .pili-repeater-item {
                        padding: 1rem !important;
                        margin-bottom: 1rem !important;
                    }
                    .pili-repeater-field .pili-repeater-item-header {
                        flex-direction: column !important;
                        align-items: flex-start !important;
                        gap: 0.75rem !important;
                    }
                    .pili-repeater-field .pili-repeater-item-actions {
                        width: 100% !important;
                        justify-content: space-between !important;
                    }
                    .pili-repeater-field .pili-repeater-add-button {
                        width: 100% !important;
                        padding: 1rem !important;
                        font-size: 1rem !important;
                    }
                    .pili-repeater-field .pili-repeater-items {
                        gap: 1rem !important;
                    }
                }
                @media (min-width: 640px) and (max-width: 767px) {
                    .pili-repeater-field .pili-repeater-toolbar {
                        padding: 0.875rem !important;
                    }
                    .pili-repeater-field .pili-repeater-item {
                        padding: 0.875rem !important;
                    }
                    .pili-repeater-field .pili-repeater-add-button {
                        padding: 0.75rem 1.5rem !important;
                    }
                }
                @media (min-width: 768px) {
                    .pili-repeater-field .pili-repeater-toolbar {
                        padding: 0.75rem !important;
                    }
                    .pili-repeater-field .pili-repeater-full-text {
                        display: inline-block !important;
                    }
                    .pili-repeater-field .pili-repeater-short-text {
                        display: none !important;
                    }
                }
                @media (max-width: 767px) {
                    .pili-repeater-field .pili-repeater-full-text {
                        display: none !important;
                    }
                    .pili-repeater-field .pili-repeater-short-text {
                        display: inline-block !important;
                    }
                }
            </style>';
            $this->render_toolbar( $max, $min, $max_enabled, $sortable, $collapsible );
            echo '<div class="pili-repeater-items space-y-6 sm:space-y-4 md:space-y-4" data-sortable-container style="position: relative;">';
            if ( ! empty( $this->value ) && is_array( $this->value ) ) {
                foreach ( $this->value as $index => $item_value ) {
                    $this->render_item( $fields, $item_value, $index, $collapsible, $default_collapsed, $show_preview, $title_config, $color );
                }
            }
            echo '</div>';
            $this->render_empty_state();
            $this->render_add_button( $button_title, $max, $color );
            $this->render_limit_alerts( $min );
            $this->render_template_item( $fields, $collapsible, $default_collapsed, $show_preview, $title_config, $color );
            echo '</div>';
            echo $this->field_after();
        }
        /**
         * 渲染工具栏
         * 
         * @since 1.0
         */
        private function render_toolbar( $max, $min, $max_enabled, $sortable, $collapsible ) {
            unset( $sortable );
            echo '<div class="pili-repeater-toolbar flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 sm:mb-4 md:mb-4 p-4 sm:p-3 md:p-3 bg-gray-50 rounded-lg border border-gray-200 gap-4 sm:gap-0">';
            echo '<div class="flex items-center space-x-4 w-full sm:w-auto">';
            echo '<div class="text-base sm:text-sm md:text-sm text-gray-600 font-medium sm:font-normal">';
            echo '<span class="pili-repeater-count">0</span> ' . pili_esc_html__( '个项目' );
            if ( $max > 0 ) {
                echo ' / ' . esc_html( sprintf( /* translators: %d: max items */ pili__( '最多 %d 个' ), $max ) );
            }
            if ( $max_enabled > 0 ) {
                echo ' / ' . esc_html( sprintf( /* translators: %d: max enabled items */ pili__( '最多启用 %d 个' ), $max_enabled ) );
            }
            if ( $min > 0 ) {
                echo ' / ' . esc_html( sprintf( /* translators: %d: min items */ pili__( '最少 %d 个' ), $min ) );
            }
            echo '</div>';
            echo '</div>';
            echo '<div class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-start sm:justify-end">';
            if ( $collapsible ) {
                echo '<button type="button" class="pili-repeater-collapse-all inline-flex items-center px-4 py-2 sm:px-3 sm:py-1.5 md:px-3 md:py-1.5 border border-gray-300 shadow-sm text-sm sm:text-xs md:text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors duration-150 touch-manipulation">';
                echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 mr-2 sm:mr-1 md:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>';
                echo '</svg>';
                echo '<span class="pili-repeater-full-text">' . pili_esc_html__( '全部折叠' ) . '</span><span class="pili-repeater-short-text">' . pili_esc_html__( '折叠' ) . '</span>';
                echo '</button>';
                echo '<button type="button" class="pili-repeater-expand-all inline-flex items-center px-4 py-2 sm:px-3 sm:py-1.5 md:px-3 md:py-1.5 border border-gray-300 shadow-sm text-sm sm:text-xs md:text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors duration-150 touch-manipulation">';
                echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 mr-2 sm:mr-1 md:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>';
                echo '</svg>';
                echo '<span class="pili-repeater-full-text">' . pili_esc_html__( '全部展开' ) . '</span><span class="pili-repeater-short-text">' . pili_esc_html__( '展开' ) . '</span>';
                echo '</button>';
            }
            echo '<button type="button" class="pili-repeater-clear-all inline-flex items-center px-4 py-2 sm:px-3 sm:py-1.5 md:px-3 md:py-1.5 border border-red-300 shadow-sm text-sm sm:text-xs md:text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none transition-colors duration-150 touch-manipulation">';
            echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 mr-2 sm:mr-1 md:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
            echo '</svg>';
            echo '<span class="pili-repeater-full-text">' . pili_esc_html__( '清空全部' ) . '</span><span class="pili-repeater-short-text">' . pili_esc_html__( '清空' ) . '</span>';
            echo '</button>';
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染单个项目
         * 
         * @since 1.0
         */
        private function render_item( $fields, $item_value, $index, $collapsible, $default_collapsed, $show_preview, $title_config, $color ) {
            $item_collapsed = ( $collapsible && $default_collapsed );
            echo '<div class="pili-repeater-item bg-white border border-gray-200 rounded-lg shadow-sm transition-none hover:shadow-md touch-manipulation' . ( $item_collapsed ? ' collapsed' : '' ) . '" data-index="' . esc_attr( $index ) . '">';
            $this->render_item_header( $item_value, $index, $fields, $collapsible, $item_collapsed, $show_preview, $title_config, $color );
            echo '<div class="pili-repeater-item-content p-6 sm:p-4 md:p-4 space-y-6 sm:space-y-4 md:space-y-4"' . ( $item_collapsed ? ' style="display: none;" aria-hidden="true"' : ' aria-hidden="false"' ) . '>';
            foreach ( $fields as $field ) {
                $field_unique = ( ! empty( $this->unique ) ) ? $this->unique .'['. $this->field['id'] .']['. $index .']' : $this->field['id'] .'['. $index .']';
                $field_value = ( isset( $field['id'] ) && isset( $item_value[$field['id']] ) ) ? $item_value[$field['id']] : '';
                $has_sub_wrapper = ! empty( $field['id'] );
                if ( $has_sub_wrapper ) {
                    $this->repeater_open_subfield_wrapper( $field );
                }
                PILI::field( $field, $field_value, $field_unique, 'field/repeater' );
                if ( $has_sub_wrapper ) {
                    echo '</div>';
                }
            }
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染项目头部
         * 
         * @since 1.0
         */
        private function render_item_header( $item_value, $index, $fields, $collapsible, $item_collapsed, $show_preview, $title_config, $color ) {
            echo '<div class="pili-repeater-item-header flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 sm:p-3 md:p-3 bg-gray-50 border-b border-gray-200 rounded-t-lg cursor-pointer gap-3 sm:gap-0" role="button" tabindex="0" aria-expanded="' . ( $item_collapsed ? 'false' : 'true' ) . '" aria-label="' . pili_esc_attr__( '展开或折叠此项目' ) . '">';
            echo '<div class="flex items-center space-x-3 w-full sm:w-auto">';
            echo '<div class="pili-repeater-sort-handle cursor-move text-gray-400 hover:text-gray-600 transition-colors touch-manipulation">';
            echo '<svg class="w-6 h-6 sm:w-5 sm:h-5 md:w-5 md:h-5" fill="currentColor" viewBox="0 0 20 20">';
            echo '<path d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zM8 4h4v2H8V4zm0 4h4v2H8V8zm0 4h4v2H8v-2z"></path>';
            echo '</svg>';
            echo '</div>';
            echo '<div class="flex-1 min-w-0">';
            echo '<div class="flex items-center space-x-2">';
            $default_title = esc_html( (string) $title_config['fallback'] . ' #' . ( $index + 1 ) );
            $title_text    = esc_html( $this->build_item_title_text( $item_value, $index, $fields, $title_config ) );
            echo '<span class="pili-repeater-item-title text-base sm:text-sm md:text-sm font-medium text-gray-900 truncate" data-default-title="' . $default_title . '">' . $title_text . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="pili-repeater-item-actions flex items-center space-x-2 sm:space-x-1 md:space-x-1 w-full sm:w-auto justify-end">';
            if ( $collapsible ) {
                echo '<button type="button" class="pili-repeater-toggle inline-flex items-center p-2 sm:p-1.5 md:p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition-colors touch-manipulation" title="' . pili_esc_attr__( '折叠/展开' ) . '" aria-expanded="' . ( $item_collapsed ? 'false' : 'true' ) . '" aria-label="' . pili_esc_attr__( '展开或折叠' ) . '">';
                echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 transform transition-transform' . ( $item_collapsed ? ' rotate-180' : '' ) . '" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>';
                echo '</svg>';
                echo '</button>';
            }
            echo '<button type="button" class="pili-repeater-clone inline-flex items-center p-2 sm:p-1.5 md:p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors touch-manipulation" title="' . pili_esc_attr__( '复制项目' ) . '">';
            echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>';
            echo '</svg>';
            echo '</button>';
            echo '<button type="button" class="pili-repeater-remove inline-flex items-center p-2 sm:p-1.5 md:p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors touch-manipulation" title="' . pili_esc_attr__( '删除项目' ) . '">';
            echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
            echo '</svg>';
            echo '</button>';
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染空状态
         * 
         * @since 1.0
         */
        private function render_empty_state() {
            echo '<div class="pili-repeater-empty-state hidden text-center py-16 sm:py-12 md:py-12 px-6 sm:px-4 md:px-4">';
            echo '<div class="mx-auto w-28 h-28 sm:w-24 sm:h-24 md:w-24 md:h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6 sm:mb-4 md:mb-4">';
            echo '<svg class="w-14 h-14 sm:w-12 sm:h-12 md:w-12 md:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
            echo '</svg>';
            echo '</div>';
            echo '<h3 class="text-xl sm:text-lg md:text-lg font-medium text-gray-900 mb-3 sm:mb-2 md:mb-2">' . pili_esc_html__( '暂无项目' ) . '</h3>';
            echo '<p class="text-base sm:text-sm md:text-sm text-gray-500 mb-8 sm:mb-6 md:mb-6">' . pili_esc_html__( '点击下方按钮添加第一个项目' ) . '</p>';
            echo '</div>';
        }
        /**
         * 渲染添加按钮
         * 
         * @since 1.0
         */
        private function render_add_button( $button_title, $max, $color ) {
            echo '<div class="pili-repeater-add-container mt-6 sm:mt-4 md:mt-4">';
            echo '<button type="button" class="pili-repeater-add w-full inline-flex items-center justify-center px-6 py-4 sm:px-4 sm:py-3 md:px-4 md:py-3 border border-dashed border-gray-300 rounded-lg text-base sm:text-sm md:text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 hover:border-' . esc_attr( $color ) . '-300 hover:text-' . esc_attr( $color ) . '-600 focus:outline-none transition-all duration-200 touch-manipulation">';
            echo '<svg class="w-6 h-6 sm:w-5 sm:h-5 md:w-5 md:h-5 mr-3 sm:mr-2 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>';
            echo '</svg>';
            echo esc_html( $button_title );
            echo '</button>';
            echo '</div>';
        }
        /**
         * 渲染限制提示
         * 
         * @since 1.0
         */
        private function render_limit_alerts( $min ) {
            if ( $min > 0 ) {
                echo '<div class="pili-repeater-alert pili-repeater-min-alert hidden mt-6 sm:mt-4 md:mt-4 p-4 bg-red-50 border border-red-200 rounded-md">';
                echo '<div class="flex">';
                echo '<div class="flex-shrink-0">';
                echo '<svg class="h-6 w-6 sm:h-5 sm:w-5 md:h-5 md:w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
                echo '</svg>';
                echo '</div>';
                echo '<div class="ml-3">';
                echo '<p class="text-base sm:text-sm md:text-sm text-red-700">' . esc_html( sprintf( /* translators: %d: min items */ pili__( '至少需要保留 %d 个项目' ), $min ) ) . '</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }
        /**
         * 渲染模板项目
         * 
         * @since 1.0
         */
        private function render_template_item( $fields, $collapsible, $default_collapsed, $show_preview, $title_config, $color ) {
            $template_collapsed = ( $collapsible && $default_collapsed );
            $item_label           = esc_html( (string) $title_config['fallback'] );
            $item_title_tpl       = $item_label . ' #{{NUMBER}}';
            echo '<div class="pili-repeater-template hidden" data-template>';
            echo '<div class="pili-repeater-item bg-white border border-gray-200 rounded-lg shadow-sm transition-none hover:shadow-md" data-index="{{INDEX}}">';
            echo '<div class="pili-repeater-item-header flex items-center justify-between p-3 bg-gray-50 border-b border-gray-200 rounded-t-lg cursor-pointer" role="button" tabindex="0" aria-expanded="' . ( $template_collapsed ? 'false' : 'true' ) . '" aria-label="' . pili_esc_attr__( '展开或折叠此项目' ) . '">';
            echo '<div class="flex items-center space-x-3">';
            echo '<div class="pili-repeater-sort-handle cursor-move text-gray-400 hover:text-gray-600 transition-colors">';
            echo '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zM8 4h4v2H8V4zm0 4h4v2H8V8zm0 4h4v2H8v-2z"></path></svg>';
            echo '</div>';
            echo '<div class="flex-1"><div class="flex items-center space-x-2"><span class="pili-repeater-item-title text-sm font-medium text-gray-900" data-default-title="' . esc_attr( $item_title_tpl ) . '">' . esc_html( $item_title_tpl ) . '</span></div></div>';
            echo '</div>';
            echo '<div class="flex items-center space-x-1">';
            if ( $collapsible ) {
                echo '<button type="button" class="pili-repeater-toggle inline-flex items-center p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition-colors" title="' . pili_esc_attr__( '折叠/展开' ) . '" aria-expanded="' . ( $template_collapsed ? 'false' : 'true' ) . '" aria-label="' . pili_esc_attr__( '展开或折叠' ) . '"><svg class="w-4 h-4 transform transition-transform' . ( $template_collapsed ? ' rotate-180' : '' ) . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></button>';
            }
            echo '<button type="button" class="pili-repeater-clone inline-flex items-center p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="' . pili_esc_attr__( '复制项目' ) . '"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg></button>';
            echo '<button type="button" class="pili-repeater-remove inline-flex items-center p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="' . pili_esc_attr__( '删除项目' ) . '"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="pili-repeater-item-content p-4 space-y-4"' . ( $template_collapsed ? ' style="display: none;" aria-hidden="true"' : ' aria-hidden="false"' ) . '>';
            foreach ( $fields as $field ) {
                $field_default = ( isset( $field['default'] ) ) ? $field['default'] : '';
                $field_unique = ( ! empty( $this->unique ) ) ? $this->unique .'['. $this->field['id'] .'][{{INDEX}}]' : $this->field['id'] .'[{{INDEX}}]';
                $has_sub_wrapper = ! empty( $field['id'] );
                if ( $has_sub_wrapper ) {
                    $this->repeater_open_subfield_wrapper( $field );
                }
                PILI::field( $field, $field_default, '___'. $field_unique, 'field/repeater' );
                if ( $has_sub_wrapper ) {
                    echo '</div>';
                }
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        /**
         * 加载字段资源
         * 
         * 加载重复器字段所需的CSS和JavaScript文件。
         * 
         * @since 1.0
         */
        public function enqueue() {
            $handle       = pili_asset_handle( 'field-repeater' );
            $toast_handle = pili_asset_handle( 'field-toast' );
            $repeater_deps = array( 'jquery', 'jquery-ui-sortable' );
            if ( wp_script_is( $toast_handle, 'registered' ) ) {
                $repeater_deps[] = $toast_handle;
            }
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/repeater.js',
                $repeater_deps,
                PILI_CORE_VERSION,
                true
            );
            pili_localize_bag( $handle, 'repeater',
                array(
                    'confirmDelete' => pili__( '确定要删除此项吗？' ),
                    'confirmDeleteTitle' => pili__( '确认删除' ),
                    'confirmClear' => pili__( '确定要清空全部吗？' ),
                    'confirmClearTitle' => pili__( '确认清空' ),
                    'clear' => pili__( '清空' ),
                    'cancel' => pili__( '取消' ),
                    'maxItems' => pili__( '已达到最大项目数量限制（%d 个）' ),
                    'maxEnabled' => pili__( '已达到最多启用数量限制（%d 个），多出来的项目可以保留但不能启用。' ),
                    'minItems' => pili__( '未达到最小数量' ),
                    'addItem' => pili__( '添加项目' ),
                    'removeItem' => pili__( '删除' ),
                    'cloneItem' => pili__( '复制此项' ),
                    'moveUp' => pili__( '上移' ),
                    'moveDown' => pili__( '下移' ),
                    'collapse' => pili__( '折叠' ),
                    'expand' => pili__( '展开' ),
                    'item' => pili__( '项目' ),
                    'disabled' => pili__( '已禁用' ),
                    'toggleItem' => pili__( '展开或折叠此项目' ),
                    'toggle' => pili__( '展开或折叠' ),
                    'collapseExpand' => pili__( '展开或折叠' ),
                )
            );
        }
        /**
         * 验证重复器字段值
         * 
         * 验证重复器字段的数据结构和内容。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return mixed 验证后的值
         */
        public function validate( $value ) {
            if ( ! is_array( $value ) ) {
                return array();
            }
            $validated = array();
            $fields = ! empty( $this->field['fields'] ) ? $this->field['fields'] : array();
            foreach ( $value as $index => $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }
                $validated_item = array();
                foreach ( $fields as $field ) {
                    if ( ! isset( $field['id'] ) ) {
                        continue;
                    }
                    $field_value = isset( $item[$field['id']] ) ? $item[$field['id']] : '';
                    if ( method_exists( $this, 'validate_field' ) ) {
                        $field_value = $this->validate_field( $field_value, $field );
                    }
                    $validated_item[$field['id']] = $field_value;
                }
                $validated[] = $validated_item;
            }
            $validated = $this->cap_max_enabled( $validated );
            $validated = apply_filters( 'pili_validate_repeater_field', $validated, $this->field );
            $validated = apply_filters( "pili_validate_repeater_field_{$this->field['id']}", $validated, $this->field );
            return $validated;
        }

        /**
         * admin-options sanitize 走 validate_field default 分支时的 repeater 清洗。
         *
         * @param mixed $value Raw value.
         * @param array $field Field config.
         * @return array<int,array<string,mixed>>
         */
        public static function sanitize_stored_value( $value, $field ) {
            if ( ! is_array( $value ) ) {
                return array();
            }
            if ( ! is_array( $field ) ) {
                return $value;
            }
            // 只收紧 enabled 配额，不改其它子字段清洗（避免影响全部 repeater）。
            $instance = new self( $field, $value );
            return $instance->cap_max_enabled( $value );
        }

        /**
         * @return int
         */
        private function get_max_enabled() {
            if ( ! isset( $this->field['max_enabled'] ) ) {
                return 0;
            }
            return max( 0, (int) $this->field['max_enabled'] );
        }

        /**
         * @return string
         */
        private function get_max_enabled_field() {
            if ( ! empty( $this->field['max_enabled_field'] ) ) {
                return (string) $this->field['max_enabled_field'];
            }
            return 'enabled';
        }

        /**
         * @return array{field:string,values:array<int,string>}
         */
        private function get_max_enabled_skip() {
            $skip = isset( $this->field['max_enabled_skip'] ) && is_array( $this->field['max_enabled_skip'] )
                ? $this->field['max_enabled_skip']
                : array();
            $field  = isset( $skip['field'] ) ? (string) $skip['field'] : '';
            $values = isset( $skip['values'] ) && is_array( $skip['values'] )
                ? array_values( array_map( 'strval', $skip['values'] ) )
                : array();
            return array(
                'field'  => $field,
                'values' => $values,
            );
        }

        /**
         * @return string
         */
        private function get_max_enabled_skip_json() {
            $skip = $this->get_max_enabled_skip();
            if ( '' === $skip['field'] || empty( $skip['values'] ) ) {
                return '';
            }
            $json = wp_json_encode( $skip, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE );
            return false === $json ? '' : $json;
        }

        /**
         * @param array<int,array<string,mixed>> $rows Rows.
         * @return array<int,array<string,mixed>>
         */
        private function cap_max_enabled( $rows ) {
            $max = $this->get_max_enabled();
            if ( $max <= 0 || ! is_array( $rows ) ) {
                return $rows;
            }
            $enabled_id = $this->get_max_enabled_field();
            $skip       = $this->get_max_enabled_skip();
            $kept       = 0;
            foreach ( $rows as $index => $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }
                if ( '' !== $skip['field'] && ! empty( $skip['values'] ) ) {
                    $probe = isset( $item[ $skip['field'] ] ) ? (string) $item[ $skip['field'] ] : '';
                    if ( in_array( $probe, $skip['values'], true ) ) {
                        continue;
                    }
                }
                if ( ! $this->is_switch_on( isset( $item[ $enabled_id ] ) ? $item[ $enabled_id ] : false ) ) {
                    continue;
                }
                ++$kept;
                if ( $kept > $max ) {
                    $rows[ $index ][ $enabled_id ] = false;
                }
            }
            return $rows;
        }

        /**
         * @param mixed $value Switch value.
         * @return bool
         */
        private function is_switch_on( $value ) {
            if ( is_bool( $value ) ) {
                return $value;
            }
            $s = strtolower( trim( (string) $value ) );
            return in_array( $s, array( '1', 'true', 'on', 'yes' ), true );
        }

        /**
         * 行头标题参与字段：title_fields → preview_field → 自动探测子字段。
         *
         * @param array<int|string,mixed> $fields 子字段配置。
         * @return array<int,string>
         */
        private function resolve_title_field_ids( $fields ) {
            if ( ! empty( $this->field['title_fields'] ) && is_array( $this->field['title_fields'] ) ) {
                return array_values( array_filter( array_map( 'strval', $this->field['title_fields'] ) ) );
            }
            if ( ! empty( $this->field['preview_field'] ) ) {
                return array( (string) $this->field['preview_field'] );
            }
            if ( array_key_exists( 'title_auto', $this->field ) && ! $this->field['title_auto'] ) {
                return array();
            }
            return $this->auto_detect_title_field_ids( $fields );
        }

        /**
         * 自动选取前 N 个适合做行头文案的子字段（text/textarea/select/number 等）。
         *
         * @param array<int|string,mixed> $fields 子字段配置。
         * @return array<int,string>
         */
        private function auto_detect_title_field_ids( $fields ) {
            $eligible = array( 'text', 'textarea', 'select', 'number', 'radio', 'button' );
            $skip     = array( 'switch', 'repeater', 'accordion', 'subheading', 'content', 'callback', 'notice', 'fieldset', 'tabbed', 'group', 'media', 'gallery', 'icon', 'color' );
            $ids      = array();
            $limit    = isset( $this->field['title_auto_limit'] ) ? max( 1, (int) $this->field['title_auto_limit'] ) : 2;
            foreach ( $fields as $sub ) {
                if ( empty( $sub['id'] ) ) {
                    continue;
                }
                $type = ! empty( $sub['type'] ) ? (string) $sub['type'] : 'text';
                if ( in_array( $type, $skip, true ) ) {
                    continue;
                }
                if ( in_array( $type, $eligible, true ) && count( $ids ) < $limit ) {
                    $ids[] = (string) $sub['id'];
                }
            }
            return $ids;
        }

        /**
         * 输出到 data-title-config 的行头配置（与 repeater.js 共用）。
         *
         * @param array<int|string,mixed> $fields 子字段配置。
         * @return array<string,mixed>
         */
        private function build_title_config( $fields ) {
            $field_ids    = $this->resolve_title_field_ids( $fields );
            $has_enabled  = false;
            foreach ( $fields as $sub ) {
                if ( ! empty( $sub['id'] ) && 'enabled' === (string) $sub['id'] ) {
                    $type = ! empty( $sub['type'] ) ? (string) $sub['type'] : '';
                    if ( 'switch' === $type ) {
                        $has_enabled = true;
                        break;
                    }
                }
            }
            $only_preview = ! empty( $this->field['preview_field'] ) && empty( $this->field['title_fields'] );
            $show_index   = true;
            if ( array_key_exists( 'title_show_index', $this->field ) ) {
                $show_index = (bool) $this->field['title_show_index'];
            } elseif ( $only_preview ) {
                $show_index = false;
            }
            $show_disabled = true;
            if ( array_key_exists( 'title_show_disabled', $this->field ) ) {
                $show_disabled = (bool) $this->field['title_show_disabled'];
            } else {
                $show_disabled = $has_enabled;
            }
            $prefix = '';
            if ( ! empty( $this->field['title_prefix'] ) ) {
                $prefix = (string) $this->field['title_prefix'];
            } elseif ( $show_index ) {
                $prefix = pili__( '项目' );
            }
            return array(
                'fields'        => $field_ids,
                'prefix'        => $prefix,
                'showIndex'     => $show_index,
                'showDisabled'  => $show_disabled,
                'separator'     => ! empty( $this->field['title_separator'] ) ? (string) $this->field['title_separator'] : ' · ',
                'fallback'      => ! empty( $this->field['title_fallback'] ) ? (string) $this->field['title_fallback'] : pili__( '项目' ),
                'maxLength'     => isset( $this->field['title_max_length'] ) ? max( 12, (int) $this->field['title_max_length'] ) : 100,
            );
        }

        /**
         * @param array<int|string,mixed> $fields 子字段配置。
         * @return array<string,mixed>|null
         */
        private function find_subfield_config( $fields, $field_id ) {
            foreach ( $fields as $sub ) {
                if ( ! empty( $sub['id'] ) && (string) $sub['id'] === (string) $field_id ) {
                    return $sub;
                }
            }
            return null;
        }

        /**
         * @param mixed                   $raw_value 原始值。
         * @param array<string,mixed>|null $subfield_config 子字段配置。
         */
        private function resolve_subfield_display_value( $raw_value, $subfield_config ) {
            if ( null === $raw_value || '' === $raw_value ) {
                return '';
            }
            if ( is_array( $raw_value ) ) {
                $raw_value = implode( ', ', $raw_value );
            }
            $type = ( is_array( $subfield_config ) && ! empty( $subfield_config['type'] ) ) ? (string) $subfield_config['type'] : 'text';
            if ( in_array( $type, array( 'select', 'radio', 'button' ), true ) ) {
                $options = ( is_array( $subfield_config ) && ! empty( $subfield_config['options'] ) && is_array( $subfield_config['options'] ) ) ? $subfield_config['options'] : array();
                $key     = is_scalar( $raw_value ) ? (string) $raw_value : '';
                if ( isset( $options[ $key ] ) ) {
                    $label = $options[ $key ];
                    return is_scalar( $label ) ? trim( (string) $label ) : $key;
                }
            }
            return is_scalar( $raw_value ) ? trim( (string) $raw_value ) : '';
        }

        /**
         * @param array<string,mixed> $item_value 行数据。
         */
        private function is_item_enabled( $item_value ) {
            if ( ! is_array( $item_value ) || ! array_key_exists( 'enabled', $item_value ) ) {
                return true;
            }
            $v = $item_value['enabled'];
            if ( is_bool( $v ) ) {
                return $v;
            }
            $s = strtolower( trim( (string) $v ) );
            return in_array( $s, array( '1', 'true', 'on', 'yes' ), true );
        }

        /**
         * 服务端渲染行头标题文案。
         *
         * @param array<string,mixed>     $item_value 行数据。
         * @param int                     $index      行序号（0-based）。
         * @param array<int|string,mixed> $fields     子字段配置。
         * @param array<string,mixed>     $title_config 行头配置。
         */
        private function build_item_title_text( $item_value, $index, $fields, $title_config ) {
            if ( ! is_array( $item_value ) ) {
                $item_value = array();
            }
            $parts = array();
            if ( ! empty( $title_config['showIndex'] ) ) {
                $parts[] = trim( (string) $title_config['prefix'] . ' ' . ( $index + 1 ) );
            }
            $has_field_value = false;
            foreach ( (array) $title_config['fields'] as $field_id ) {
                $sub     = $this->find_subfield_config( $fields, $field_id );
                $raw     = isset( $item_value[ $field_id ] ) ? $item_value[ $field_id ] : '';
                $display = $this->resolve_subfield_display_value( $raw, $sub );
                if ( '' !== $display ) {
                    $parts[]           = $display;
                    $has_field_value   = true;
                }
            }
            if ( ! empty( $title_config['showDisabled'] ) && ! $this->is_item_enabled( $item_value ) ) {
                $parts[] = pili__( '已禁用' );
            }
            if ( empty( $parts ) || ( ! $has_field_value && empty( $title_config['showIndex'] ) ) ) {
                return (string) $title_config['fallback'] . ' #' . ( $index + 1 );
            }
            $text = implode( (string) $title_config['separator'], $parts );
            $max  = (int) $title_config['maxLength'];
            if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
                return mb_substr( $text, 0, $max ) . '...';
            }
            if ( strlen( $text ) > $max ) {
                return substr( $text, 0, $max ) . '...';
            }
            return $text;
        }
    }
}

if ( ! has_filter( 'pili_validate_field_repeater', array( __NAMESPACE__ . '\PILI_Field_repeater', 'sanitize_stored_value' ) ) ) {
	add_filter( 'pili_validate_field_repeater', array( __NAMESPACE__ . '\PILI_Field_repeater', 'sanitize_stored_value' ), 10, 2 );
}
