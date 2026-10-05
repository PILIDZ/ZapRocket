<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework Sortable 字段类型
 * 
 * 这个字段类型提供了一个现代化的可排序界面，支持拖拽排序、
 * 嵌套字段、多种布局模式、键盘快捷键等高级功能。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_sortable' ) ) {
    
    /**
     * PILI_Field_sortable 可排序字段类
     * 
     * 功能特性：
     * - 拖拽排序支持
     * - 嵌套字段渲染
     * - 多种布局模式（垂直、水平、网格）
     * - 键盘快捷键支持
     * - 添加/删除项目操作
     * - 实时预览和状态保存
     * - 响应式设计
     * - 无障碍访问优化
     * - 完整的数据验证
     * 
     * @since 1.0
     */
    class PILI_Field_sortable extends PILI_Fields {
        /**
         * 构造函数
         * 
         * 初始化可排序字段实例。
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
         * 渲染可排序字段
         * 
         * 生成现代化的可排序界面。
         * 
         * @since 1.0
         */
        public function render() {
            echo $this->field_before();
            $args = wp_parse_args( $this->field, array(
                'fields'          => array(),
                'layout'          => 'vertical',
                'sortable'        => true,
                'addable'         => false,
                'removable'       => false,
                'collapsible'     => false,
                'show_handles'    => true,
                'show_numbers'    => true,
                'show_preview'    => false,
                'min_items'       => 0,
                'max_items'       => 0,
                'animation'       => true,
                'keyboard_nav'    => true,
                'grid_columns'    => 3,
                'item_template'   => '',
                'empty_message' => pili__( '暂无内容' ),
                'add_button_text' => pili__( '添加项目' ),
            ) );
            if ( empty( $args['fields'] ) ) {
                echo '<div class="text-red-500 text-sm">' . pili_esc_html__( '错误：未定义字段结构' ) . '</div>';
                echo $this->field_after();
                return;
            }
            $pre_fields = array();
            foreach ( $args['fields'] as $key => $field ) {
                $pre_fields[$field['id']] = $field;
            }
            $sorted_fields = $this->sort_fields_by_value( $pre_fields, $this->value );
            $field_id = 'pili-sortable-' . uniqid();
            $container_classes = array(
                'pili-sortable-field',
                'pili-sortable-' . $args['layout'],
            );
            if ( $args['animation'] ) {
                $container_classes[] = 'pili-sortable-animate';
            }
            if ( $args['keyboard_nav'] ) {
                $container_classes[] = 'pili-sortable-keyboard';
            }
            echo '<div class="' . implode( ' ', $container_classes ) . '" data-field-id="' . esc_attr( $this->field['id'] ) . '">';
            $sortable_data = array(
                'layout'        => $args['layout'],
                'sortable'      => $args['sortable'],
                'addable'       => $args['addable'],
                'removable'     => $args['removable'],
                'collapsible'   => $args['collapsible'],
                'showHandles'   => $args['show_handles'],
                'showNumbers'   => $args['show_numbers'],
                'minItems'      => $args['min_items'],
                'maxItems'      => $args['max_items'],
                'animation'     => $args['animation'],
                'keyboardNav'   => $args['keyboard_nav'],
                'gridColumns'   => $args['grid_columns'],
            );
            if ( $args['addable'] || $args['show_preview'] ) {
                echo '<div class="pili-sortable-toolbar mb-4 flex flex-wrap items-center justify-between gap-3">';
                if ( $args['addable'] ) {
                    echo '<button type="button" class="pili-sortable-add-item inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">';
                    echo '<svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />';
                    echo '</svg>';
                    echo esc_html( $args['add_button_text'] );
                    echo '</button>';
                }
                if ( $args['show_preview'] ) {
                    echo '<button type="button" class="pili-sortable-toggle-preview inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">';
                    echo '<svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                    echo '</svg>';
                    echo pili_esc_html__( '预览模式' );
                    echo '</button>';
                }
                echo '</div>';
            }
            echo '<div class="pili-sortable-container" data-sortable-config="' . esc_attr( json_encode( $sortable_data ) ) . '" style="position: relative;">';
            if ( ! empty( $sorted_fields ) ) {
                $this->render_sortable_items( $sorted_fields, $args );
            } else {
                echo '<div class="pili-sortable-empty text-center py-8 text-gray-500">';
                echo '<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />';
                echo '</svg>';
                echo '<p class="mt-2 text-sm">' . esc_html( $args['empty_message'] ) . '</p>';
                if ( $args['addable'] ) {
                    echo '<button type="button" class="pili-sortable-add-item mt-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">';
                    echo esc_html( $args['add_button_text'] );
                    echo '</button>';
                }
                echo '</div>';
            }
            echo '</div>';
            if ( $args['show_preview'] ) {
                echo '<div class="pili-sortable-preview mt-4 p-4 bg-gray-50 rounded-lg hidden">';
                echo '<div class="text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '排序预览' ) . '</div>';
                echo '<div class="pili-sortable-preview-content text-sm text-gray-600"></div>';
                echo '</div>';
            }
            if ( $args['addable'] ) {
                echo '<script type="text/template" class="pili-sortable-item-template">';
                $this->render_item_template( $args['fields'], $args );
                echo '</script>';
            }
            echo '</div>';
            echo $this->field_after();
        }
        /**
         * 根据保存的值排序字段
         * 
         * @since 1.0
         * 
         * @param array $fields 字段定义
         * @param mixed $value  保存的值
         * 
         * @return array 排序后的字段
         */
        private function sort_fields_by_value( $fields, $value ) {
            $sorted_fields = array();
            if ( ! empty( $value ) && is_array( $value ) ) {
                foreach ( $value as $key => $field_value ) {
                    if ( isset( $fields[$key] ) ) {
                        $sorted_fields[$key] = $fields[$key];
                    }
                }
                $diff = array_diff_key( $fields, $value );
                if ( ! empty( $diff ) ) {
                    $sorted_fields = array_merge( $sorted_fields, $diff );
                }
            } else {
                $sorted_fields = $fields;
            }
            return $sorted_fields;
        }
        /**
         * 渲染可排序项目
         * 
         * @since 1.0
         * 
         * @param array $fields 字段数组
         * @param array $args   配置参数
         */
        private function render_sortable_items( $fields, $args ) {
            $items_classes = 'pili-sortable-items';
            switch ( $args['layout'] ) {
                case 'horizontal':
                    $items_classes .= ' flex flex-wrap gap-4';
                    break;
                case 'grid':
                    $cols = intval( $args['grid_columns'] );
                    switch ( $cols ) {
                        case 2:
                            $items_classes .= ' grid grid-cols-1 md:grid-cols-2 gap-4';
                            break;
                        case 3:
                            $items_classes .= ' grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
                            break;
                        case 4:
                            $items_classes .= ' grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4';
                            break;
                        default:
                            $items_classes .= ' grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
                            break;
                    }
                    break;
                default:
                    $items_classes .= ' space-y-4';
                    break;
            }
            echo '<div class="' . $items_classes . '">';
            $index = 0;
            foreach ( $fields as $key => $field ) {
                $this->render_sortable_item( $key, $field, $args, $index );
                $index++;
            }
            echo '</div>';
        }
        /**
         * 渲染单个可排序项目
         * 
         * @since 1.0
         * 
         * @param string $key   字段键
         * @param array  $field 字段配置
         * @param array  $args  配置参数
         * @param int    $index 索引
         */
        private function render_sortable_item( $key, $field, $args, $index ) {
            $field_default = isset( $this->field['default'][$key] ) ? $this->field['default'][$key] : '';
            $field_value = isset( $this->value[$key] ) ? $this->value[$key] : $field_default;
            $unique_id = ! empty( $this->unique ) ? $this->unique . '[' . $this->field['id'] . ']' : $this->field['id'];
            $item_classes = array(
                'pili-sortable-item',
                'relative',
                'bg-white',
                'border',
                'border-gray-200',
                'rounded-lg',
                'shadow-sm',
                'transition-none',
            );
            if ( $args['collapsible'] ) {
                $item_classes[] = 'pili-collapsible';
            }
            echo '<div class="' . implode( ' ', $item_classes ) . '" data-item-key="' . esc_attr( $key ) . '" data-item-index="' . esc_attr( $index ) . '">';
            if ( $args['show_handles'] || $args['show_numbers'] || $args['removable'] || $args['collapsible'] ) {
                echo '<div class="pili-sortable-item-header flex items-center justify-between p-3 border-b border-gray-200">';
                echo '<div class="flex items-center space-x-3">';
                if ( $args['show_handles'] && $args['sortable'] ) {
                    echo '<div class="pili-sortable-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600">';
                    echo '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />';
                    echo '</svg>';
                    echo '</div>';
                }
                if ( $args['show_numbers'] ) {
                    echo '<span class="pili-sortable-number inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-gray-500 bg-gray-100 rounded-full">' . ( $index + 1 ) . '</span>';
                }
                if ( ! empty( $field['title'] ) ) {
                    echo '<h4 class="text-sm font-medium text-gray-900">' . esc_html( $field['title'] ) . '</h4>';
                }
                echo '</div>';
                echo '<div class="flex items-center space-x-2">';
                if ( $args['collapsible'] ) {
                    echo '<button type="button" class="pili-sortable-toggle text-gray-400 hover:text-gray-600 focus:outline-none">';
                    echo '<svg class="w-4 h-4 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />';
                    echo '</svg>';
                    echo '</button>';
                }
                if ( $args['removable'] ) {
                    echo '<button type="button" class="pili-sortable-remove text-red-400 hover:text-red-600 focus:outline-none">';
                    echo '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />';
                    echo '</svg>';
                    echo '</button>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '<div class="pili-sortable-item-content p-4">';
            PILI::field( $field, $field_value, $unique_id, 'field/sortable' );
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染项目模板
         * 
         * @since 1.0
         * 
         * @param array $fields 字段数组
         * @param array $args   配置参数
         */
        private function render_item_template( $fields, $args ) {
            echo '<!-- Item template will be handled by JavaScript -->';
        }
        /**
         * 加载字段资源
         * 
         * 加载可排序字段所需的JavaScript资源。
         * 
         * @since 1.0
         */
        public function enqueue() {
            $handle = pili_asset_handle( 'field-sortable' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/sortable.js',
                array( 'jquery', 'jquery-ui-sortable' ),
                PILI_Setup::$version,
                true
            );
            pili_localize_bag( $handle, 'sortable', array(
                'strings' => array(
                    'confirmRemove' => pili__( '确定要移除此项吗？' ),
                    'addItem' => pili__( '添加项目' ),
                    'removeItem' => pili__( '删除' ),
                    'moveUp' => pili__( '上移' ),
                    'moveDown' => pili__( '下移' ),
                    'collapse' => pili__( '折叠' ),
                    'expand' => pili__( '展开' ),
                    'maxItemsReached' => pili__( '已达到最大数量' ),
                    'minItemsRequired' => pili__( '未达到最小数量' ),
                    'newItem' => pili__( '新项目' ),
                    'inputPlaceholder' => pili__( '请输入内容…' ),
                    'itemNumber' => pili__( '项目 %d' ),
                    'noItems' => pili__( '暂无项目' ),
                ),
                'nonce' => wp_create_nonce( 'pili_sortable_nonce' ),
            ) );
        }
        /**
         * 验证和清理字段数据
         * 
         * 对可排序字段的数据进行验证和清理。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return mixed 清理后的数据
         */
        public function validate( $value ) {
            $args = wp_parse_args( $this->field, array(
                'fields'    => array(),
                'min_items' => 0,
                'max_items' => 0,
            ) );
            if ( ! is_array( $value ) ) {
                return array();
            }
            $validated = array();
            $field_definitions = array();
            foreach ( $args['fields'] as $field ) {
                $field_definitions[$field['id']] = $field;
            }
            foreach ( $value as $key => $field_value ) {
                if ( isset( $field_definitions[$key] ) ) {
                    $field_def = $field_definitions[$key];
                    $validated_value = $this->validate_field_value( $field_value, $field_def );
                    $validated[$key] = $validated_value;
                }
            }
            if ( $args['min_items'] > 0 && count( $validated ) < $args['min_items'] ) {
            }
            if ( $args['max_items'] > 0 && count( $validated ) > $args['max_items'] ) {
                $validated = array_slice( $validated, 0, $args['max_items'], true );
            }
            return $validated;
        }
        /**
         * 验证单个字段值
         * 
         * @since 1.0
         * 
         * @param mixed $value 字段值
         * @param array $field 字段定义
         * 
         * @return mixed 验证后的值
         */
        private function validate_field_value( $value, $field ) {
            switch ( $field['type'] ) {
                case 'text':
                case 'textarea':
                    return sanitize_text_field( $value );
                case 'email':
                    return sanitize_email( $value );
                case 'url':
                    return esc_url_raw( $value );
                case 'number':
                    return intval( $value );
                case 'select':
                case 'radio':
                    if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
                        return in_array( $value, array_keys( $field['options'] ) ) ? $value : '';
                    }
                    return sanitize_text_field( $value );
                case 'checkbox':
                    return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
                default:
                    return sanitize_text_field( $value );
            }
        }
    }
}
