<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework Sorter 字段类型
 * 
 * 这个字段类型提供了一个现代化的拖拽排序界面，支持启用/禁用项目。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_sorter' ) ) {
    
    /**
     * PILI_Field_sorter 排序器字段类
     * 
     * 功能特性：
     * - 拖拽排序功能
     * - 启用/禁用项目管理
     * - 移动端友好设计
     * - 实时预览功能
     * - 批量操作支持
     * - 完整的数据验证
     * 
     * @since 1.0
     */
    class PILI_Field_sorter extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化排序器字段实例。
         * 
         * @since 1.0
         * 
         * @param array  $field  字段配置
         * @param mixed  $value  字段值
         * @param string $unique 唯一标识符
         * @param string $where  字段位置
         * @param string $parent 父级字段
         */
        public function __construct( $field = array(), $value = '', $unique = '', $where = '', $parent = '' ) {
            parent::__construct( $field, $value, $unique, $where, $parent );
        }
        
        /**
         * 渲染排序器字段
         * 
         * 生成现代化的拖拽排序界面，包括启用和禁用区域。
         * 
         * @since 1.0
         */
        public function render() {
            
            echo $this->field_before();
            
            $options = ! empty( $this->field['options'] ) ? $this->field['options'] : array();
            $enabled_title = ! empty( $this->field['enabled_title'] ) ? $this->field['enabled_title'] : pili__( '已启用项目' );
            $disabled_title = ! empty( $this->field['disabled_title'] ) ? $this->field['disabled_title'] : pili__( '可用项目' );
            $show_disabled = ! isset( $this->field['disabled'] ) || $this->field['disabled'] !== false;
            $allow_all_none = ! empty( $this->field['allow_all_none'] );
            
            $enabled_items = array();
            $disabled_items = array();
            
            if ( ! empty( $this->value ) && is_array( $this->value ) ) {
                $enabled_items = ! empty( $this->value['enabled'] ) ? $this->value['enabled'] : array();
                $disabled_items = ! empty( $this->value['disabled'] ) ? $this->value['disabled'] : array();
            }
            
            foreach ( $options as $key => $label ) {
                if ( ! isset( $enabled_items[ $key ] ) && ! isset( $disabled_items[ $key ] ) ) {
                    $disabled_items[ $key ] = $label;
                }
            }
            
            echo '<div class="pili-sorter-field" data-field-id="' . esc_attr( $this->field['id'] ) . '">';
            
            if ( $allow_all_none ) {
                echo '<div class="mb-4 flex flex-wrap gap-2">';
                echo '<button type="button" class="pili-sorter-enable-all inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors">';
                echo '<svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                echo pili_esc_html__( '全部启用' ) . '</button>';
                echo '<button type="button" class="pili-sorter-disable-all inline-flex items-center rounded-md bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 transition-colors">';
                echo '<svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
                echo pili_esc_html__( '全部禁用' ) . '</button>';
                echo '</div>';
            }
            
            $grid_classes = $show_disabled ? 'grid grid-cols-1 lg:grid-cols-2 gap-6' : 'w-full';
            echo '<div class="' . $grid_classes . '">';
            
            echo '<div class="pili-sorter-enabled-section">';
            echo '<div class="mb-3 flex items-center justify-between">';
            echo '<h4 class="text-sm font-medium text-gray-900">' . esc_html( $enabled_title ) . '</h4>';
            echo '<span class="pili-enabled-count inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">' . count( $enabled_items ) . '</span>';
            echo '</div>';
            
            echo '<div class="pili-sorter-enabled min-h-[120px] rounded-lg border-2 border-dashed border-blue-300 bg-blue-50/50 p-4 transition-colors" data-type="enabled">';
            
            if ( ! empty( $enabled_items ) ) {
                foreach ( $enabled_items as $key => $label ) {
                    $this->render_sortable_item( $key, $label, 'enabled' );
                }
            } else {
                echo '<div class="pili-empty-placeholder text-center py-8 text-gray-500">';
                echo '<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />';
                echo '</svg>';
                echo '<p class="mt-2 text-sm">' . pili_esc_html__( '拖拽项目到此处启用' ) . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
            
            if ( $show_disabled ) {
                echo '<div class="pili-sorter-disabled-section">';
                echo '<div class="mb-3 flex items-center justify-between">';
                echo '<h4 class="text-sm font-medium text-gray-900">' . esc_html( $disabled_title ) . '</h4>';
                echo '<span class="pili-disabled-count inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">' . count( $disabled_items ) . '</span>';
                echo '</div>';
                
                echo '<div class="pili-sorter-disabled min-h-[120px] rounded-lg border-2 border-dashed border-gray-300 bg-gray-50/50 p-4 transition-colors" data-type="disabled">';
                
                if ( ! empty( $disabled_items ) ) {
                    foreach ( $disabled_items as $key => $label ) {
                        $this->render_sortable_item( $key, $label, 'disabled' );
                    }
                } else {
                    echo '<div class="pili-empty-placeholder text-center py-8 text-gray-500">';
                    echo '<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />';
                    echo '</svg>';
                    echo '<p class="mt-2 text-sm">' . pili_esc_html__( '拖拽项目到此处禁用' ) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            
            if ( ! empty( $this->field['show_preview'] ) ) {
                echo '<div class="mt-6 rounded-lg bg-gray-50 p-4">';
                echo '<h5 class="mb-3 text-sm font-medium text-gray-900">' . pili_esc_html__( '实时预览' ) . '</h5>';
                echo '<div class="pili-sorter-preview text-sm text-gray-600"></div>';
                echo '</div>';
            }
            
            echo '</div>';
            
            echo $this->field_after();
        }
        
        /**
         * 渲染可排序项目
         * 
         * 生成单个可拖拽的项目元素。
         * 
         * @since 1.0
         * 
         * @param string $key   项目键值
         * @param string $label 项目标签
         * @param string $type  项目类型（enabled/disabled）
         */
        private function render_sortable_item( $key, $label, $type ) {
            $input_name = $this->field_name( '[' . $type . '][' . $key . ']' );
            $is_enabled = $type === 'enabled';
            
            $item_classes = 'pili-sortable-item group relative mb-2 cursor-move rounded-lg border bg-white p-3 shadow-sm transition-all hover:shadow-md';
            $item_classes .= $is_enabled ? ' border-blue-200 hover:border-blue-300' : ' border-gray-200 hover:border-gray-300';
            
            echo '<div class="' . $item_classes . '" data-key="' . esc_attr( $key ) . '">';
            
            echo '<input type="hidden" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $label ) . '" />';
            
            echo '<div class="flex items-center justify-between">';
            
            echo '<div class="flex items-center space-x-3">';
            echo '<div class="pili-drag-handle text-gray-400 group-hover:text-gray-600 transition-colors">';
            echo '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />';
            echo '</svg>';
            echo '</div>';
            echo '<span class="text-sm font-medium text-gray-900">' . esc_html( $label ) . '</span>';
            echo '</div>';
            
            echo '<div class="flex items-center space-x-2">';
            if ( $is_enabled ) {
                echo '<span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">';
                echo '<svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />';
                echo '</svg>' . pili_esc_html__( '已启用' ) . '</span>';
            } else {
                echo '<span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">';
                echo '<svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />';
                echo '</svg>' . pili_esc_html__( '已禁用' ) . '</span>';
            }
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
        }
        
        /**
         * 加载字段资源
         * 
         * 加载排序器字段所需的JavaScript和CSS资源。
         * 
         * @since 1.0
         */
        public function enqueue() {

            $handle = pili_asset_handle( 'field-sorter' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/sorter.js',
                array( 'jquery' ),
                PILI_Setup::$version,
                true
            );
            
            pili_localize_bag( $handle, 'sorter', array(
                'strings' => array(
                    'enableAll' => pili__( '全部启用' ),
                    'disableAll' => pili__( '全部禁用' ),
                    'enabled' => pili__( '启用' ),
                    'disabled' => pili__( '已禁用' ),
                    'dragHereEnable' => pili__( '拖拽项目到此处启用' ),
                    'dragHereDisable' => pili__( '拖拽项目到此处禁用' ),
                    'empty' => pili__( '暂无数据' ),
                    'enabledItems' => pili__( '已启用项目：' ),
                    'disabledItems' => pili__( '已禁用项目：' ),
                ),
                'nonce' => wp_create_nonce( 'pili_sorter_nonce' ),
            ) );
        }
        
        /**
         * 验证和清理字段数据
         * 
         * 对排序器字段的数据进行验证和清理。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return array 清理后的数据
         */
        public function validate( $value ) {
            
            if ( ! is_array( $value ) ) {
                return array(
                    'enabled'  => array(),
                    'disabled' => array(),
                );
            }
            
            $enabled = ! empty( $value['enabled'] ) && is_array( $value['enabled'] ) ? $value['enabled'] : array();
            $disabled = ! empty( $value['disabled'] ) && is_array( $value['disabled'] ) ? $value['disabled'] : array();
            
            $enabled = array_map( 'sanitize_text_field', $enabled );
            $disabled = array_map( 'sanitize_text_field', $disabled );
            
            $allowed_options = ! empty( $this->field['options'] ) ? $this->field['options'] : array();
            
            if ( ! empty( $allowed_options ) ) {
                $enabled = array_intersect_key( $enabled, $allowed_options );
                $disabled = array_intersect_key( $disabled, $allowed_options );
            }
            
            $value = array(
                'enabled'  => $enabled,
                'disabled' => $disabled,
            );
            
            $value = apply_filters( 'pili_validate_sorter_field', $value, $this->field );
            $value = apply_filters( "pili_validate_sorter_field_{$this->field['id']}", $value, $this->field );
            
            return $value;
        }
    }
}
