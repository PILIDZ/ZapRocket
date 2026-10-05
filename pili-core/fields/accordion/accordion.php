<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 手风琴字段类型
 * 
 * 这个类实现了现代化的手风琴字段功能。
 * 提供比CSF更好的用户体验，包括流畅动画、无障碍访问、键盘导航等高级功能。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_accordion' ) ) {
    /**
     * PILI_Field_accordion 手风琴字段类 - 响应式自适应设计
     *
     * 提供现代化手风琴字段的完整功能，包括：
     * - 流畅的展开折叠动画
     * - 无障碍访问支持
     * - 键盘导航
     * - 自定义图标
     * - 多种展开模式
     * - 完全响应式设计（移动端优先）
     * - 触摸友好的交互体验
     * - 修复默认展开状态问题
     * - 跨设备兼容性优化
     * - 深色模式支持
     *
     * @since 1.0
     */
    class PILI_Field_accordion extends PILI_Fields {
        /**
         * 构造函数
         * 
         * 初始化手风琴字段实例。
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
         * 渲染手风琴字段
         *
         * 输出现代化手风琴字段的HTML代码。
         *
         * @since 1.0
         */
        public function render() {
            if ( empty( $this->field['accordions'] ) || ! is_array( $this->field['accordions'] ) ) {
                echo '<div class="bg-red-50 border border-red-200 rounded-md p-4">';
                echo '<div class="flex">';
                echo '<div class="flex-shrink-0">';
                echo '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
                echo '</svg>';
                echo '</div>';
                echo '<div class="ml-3">';
                echo '<h3 class="text-sm font-medium text-red-800">' . pili_esc_html__( '配置错误' ) . '</h3>';
                echo '<div class="mt-2 text-sm text-red-700">';
                echo '<p>' . pili_esc_html__( '手风琴字段缺少必需的 "accordions" 配置数组。' ) . '</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                return;
            }
            $accordions = $this->field['accordions'];
            $multiple = ! empty( $this->field['multiple'] ) ? $this->field['multiple'] : false;
            $collapsible = ! empty( $this->field['collapsible'] ) ? $this->field['collapsible'] : true;
            $color = ! empty( $this->field['color'] ) ? $this->field['color'] : 'blue';
            $size = ! empty( $this->field['size'] ) ? $this->field['size'] : 'default';
            $rounded = ! empty( $this->field['rounded'] ) ? $this->field['rounded'] : true;
            $shadow = ! empty( $this->field['shadow'] ) ? $this->field['shadow'] : true;
            echo $this->field_before();
            echo '<div class="pili-accordion-field w-full touch-manipulation" ';
            echo 'data-field-id="' . esc_attr( $this->field['id'] ) . '" ';
            echo 'data-multiple="' . esc_attr( $multiple ? 'true' : 'false' ) . '" ';
            echo 'data-collapsible="' . esc_attr( $collapsible ? 'true' : 'false' ) . '" ';
            echo 'data-color="' . esc_attr( $color ) . '" ';
            echo 'data-size="' . esc_attr( $size ) . '">';
            foreach ( $accordions as $index => $accordion ) {
                $this->render_accordion_item( $accordion, $index, $multiple, $collapsible, $color, $size, $rounded, $shadow );
            }
            echo '</div>';
            echo $this->field_after();
        }
        /**
         * 渲染单个手风琴项目
         * 
         * @since 1.0
         * 
         * @param array $accordion 手风琴项目配置
         * @param int   $index     项目索引
         * @param bool  $multiple  是否允许多个展开
         * @param bool  $collapsible 是否可折叠
         * @param string $color    颜色主题
         * @param string $size     尺寸
         * @param bool  $rounded   是否圆角
         * @param bool  $shadow    是否阴影
         */
        private function render_accordion_item( $accordion, $index, $multiple, $collapsible, $color, $size, $rounded, $shadow ) {
            if ( empty( $accordion['title'] ) || empty( $accordion['fields'] ) ) {
                return;
            }
            $item_id = 'pili-accordion-item-' . $this->field['id'] . '-' . $index;
            $content_id = 'pili-accordion-content-' . $this->field['id'] . '-' . $index;
            $is_open = ! empty( $accordion['open'] ) ? $accordion['open'] : false;
            $icon = ! empty( $accordion['icon'] ) ? $accordion['icon'] : '';
            $container_classes = array(
                'pili-accordion-item',
                'border border-gray-200',
                'transition-all duration-200 ease-in-out'
            );
            if ( $rounded ) {
                $container_classes[] = $index === 0 ? 'rounded-t-lg' : '';
                $container_classes[] = 'last:rounded-b-lg';
            }
            if ( $shadow ) {
                $container_classes[] = 'shadow-sm hover:shadow-md';
            }
            if ( $index > 0 ) {
                $container_classes[] = '-mt-px';
            }
            echo '<div class="' . esc_attr( implode( ' ', array_filter( $container_classes ) ) ) . '" data-index="' . esc_attr( $index ) . '">';
            $this->render_accordion_header( $accordion, $index, $item_id, $content_id, $is_open, $icon, $color, $size );
            $this->render_accordion_content( $accordion, $index, $content_id, $item_id, $is_open );
            echo '</div>';
        }
        /**
         * 渲染手风琴头部
         * 
         * @since 1.0
         */
        private function render_accordion_header( $accordion, $index, $item_id, $content_id, $is_open, $icon, $color, $size ) {
            $size_classes = array(
                'small'   => 'px-4 py-3 sm:px-4 sm:py-3 md:px-4 md:py-3 text-base sm:text-sm md:text-sm',
                'default' => 'px-6 py-4 sm:px-6 sm:py-4 md:px-6 md:py-4 text-lg sm:text-base md:text-base',
                'large'   => 'px-8 py-5 sm:px-8 sm:py-5 md:px-8 md:py-5 text-xl sm:text-lg md:text-lg'
            );
            $header_size = isset( $size_classes[$size] ) ? $size_classes[$size] : $size_classes['default'];
            echo '<button type="button" ';
            echo 'class="pili-accordion-trigger w-full flex items-center justify-between ' . esc_attr( $header_size ) . ' bg-white hover:bg-gray-50 focus:outline-none transition-all duration-200 touch-manipulation" ';
            echo 'id="' . esc_attr( $item_id ) . '" ';
            echo 'aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" ';
            echo 'aria-controls="' . esc_attr( $content_id ) . '" ';
            echo 'data-target="' . esc_attr( $content_id ) . '" ';
            echo 'data-default-open="' . ( $is_open ? 'true' : 'false' ) . '">';
            echo '<div class="flex items-center space-x-4 sm:space-x-3 md:space-x-3 min-w-0 flex-1">';
            if ( ! empty( $icon ) ) {
                if ( strpos( $icon, '<svg' ) !== false ) {
                    echo '<div class="flex-shrink-0 text-' . esc_attr( $color ) . '-600 w-6 h-6 sm:w-5 sm:h-5 md:w-5 md:h-5">' . $icon . '</div>';
                } else {
                    echo '<div class="flex-shrink-0 text-' . esc_attr( $color ) . '-600 text-xl sm:text-lg md:text-lg"><i class="' . esc_attr( $icon ) . '"></i></div>';
                }
            }
            echo '<span class="font-medium text-gray-900 text-left truncate">' . esc_html( $accordion['title'] ) . '</span>';
            echo '</div>';
            echo '<div class="flex-shrink-0 ml-2">';
            echo '<svg class="w-6 h-6 sm:w-5 sm:h-5 md:w-5 md:h-5 text-gray-500 transform transition-transform duration-200 pili-accordion-icon' . ( $is_open ? ' rotate-180' : '' ) . '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>';
            echo '</svg>';
            echo '</div>';
            echo '</button>';
        }
        /**
         * 渲染手风琴内容
         * 
         * @since 1.0
         */
        private function render_accordion_content( $accordion, $index, $content_id, $item_id, $is_open ) {
            $content_classes = 'pili-accordion-content overflow-hidden transition-all duration-300 ease-in-out';
            $content_style = '';
            if ( $is_open ) {
                $content_classes .= ' pili-accordion-open';
                $content_style = 'max-height: none; opacity: 1;';
            } else {
                $content_classes .= ' max-h-0';
                $content_style = 'max-height: 0; opacity: 0;';
            }
            echo '<div class="' . esc_attr( $content_classes ) . '" ';
            echo 'style="' . esc_attr( $content_style ) . '" ';
            echo 'id="' . esc_attr( $content_id ) . '" ';
            echo 'aria-labelledby="' . esc_attr( $item_id ) . '" ';
            echo 'aria-hidden="' . ( $is_open ? 'false' : 'true' ) . '" ';
            echo 'role="region" ';
            echo 'data-default-open="' . ( $is_open ? 'true' : 'false' ) . '">';
            echo '<div class="px-6 py-4 sm:px-6 sm:py-4 md:px-6 md:py-4 bg-gray-50 border-t border-gray-200">';
            if ( ! empty( $accordion['fields'] ) && is_array( $accordion['fields'] ) ) {
                $unallowed_types = array( 'accordion' );
                foreach ( $accordion['fields'] as $field ) {
                    if ( in_array( $field['type'], $unallowed_types ) ) {
                        $field['_notice'] = true;
                    }
                    $field_id = isset( $field['id'] ) ? $field['id'] : '';
                    $field_default = isset( $field['default'] ) ? $field['default'] : '';
                    $field_value = isset( $this->value[$field_id] ) ? $this->value[$field_id] : $field_default;
                    $unique_id = ! empty( $this->unique ) ? $this->unique . '[' . $this->field['id'] . ']' : $this->field['id'];
                    PILI::field( $field, $field_value, $unique_id, 'field/accordion' );
                }
            }
            echo '</div>';
            echo '</div>';
        }
        /**
         * 加载字段资源
         * 
         * 加载手风琴字段所需的JavaScript文件。
         * 
         * @since 1.0
         */
        public function enqueue() {
            $handle = pili_asset_handle( 'field-accordion' );
            wp_enqueue_script( 
                $handle, 
                PILI_Setup::$url . '/assets/js/fields/accordion.js', 
                array( 'jquery' ), 
                PILI_CORE_VERSION, 
                true 
            );
            pili_localize_bag( $handle, 'accordion', array(
                'expandText' => pili__( '展开' ),
                'collapseText' => pili__( '折叠' ),
                'expandAll' => pili__( '展开全部' ),
                'collapseAll' => pili__( '折叠全部' ),
            ) );
        }
        /**
         * 验证手风琴字段值
         * 
         * 验证手风琴字段的数据结构和内容。
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
            $accordions = ! empty( $this->field['accordions'] ) ? $this->field['accordions'] : array();
            foreach ( $accordions as $accordion ) {
                if ( empty( $accordion['fields'] ) ) {
                    continue;
                }
                foreach ( $accordion['fields'] as $field ) {
                    if ( ! isset( $field['id'] ) ) {
                        continue;
                    }
                    $field_value = isset( $value[$field['id']] ) ? $value[$field['id']] : '';
                    if ( method_exists( $this, 'validate_field' ) ) {
                        $field_value = $this->validate_field( $field, $field_value );
                    }
                    $validated[$field['id']] = $field_value;
                }
            }
            $validated = apply_filters( 'pili_validate_accordion_field', $validated, $this->field );
            $validated = apply_filters( "pili_validate_accordion_field_{$this->field['id']}", $validated, $this->field );
            return $validated;
        }
    }
}
