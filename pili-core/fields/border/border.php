<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
  * Border 字段类 - 组合式架构
  *
  * 使用组合式设计，通过PILI::field()方法组合基础字段来构建完整的边框设置功能。
  * 支持边框宽度、样式、颜色等所有CSS边框属性。
  *
 * @package Xun Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.1.0
 * @version 1.1.0
  */

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_border' ) ) {

    /**
     * PILI_Field_border 边框字段类
     *
     * 采用组合式架构，通过组合基础字段实现复杂的边框设置功能。
     * 支持边框宽度、样式、颜色等所有CSS边框属性。
     *
     * @since 1.1.0
     */
    class PILI_Field_border extends PILI_Fields {

        /**
         * 构造函数
         *
         * 初始化border字段实例。
         *
         * @since 1.1.0
         *
         * @param array  $field   字段配置数组
         * @param string $value   字段值
         * @param string $unique  唯一标识符
         * @param string $where   字段位置标识
         * @param string $parent  父级标识符
         */
        public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
            parent::__construct( $field, $value, $unique, $where, $parent );
        }

        /**
         * 安全获取颜色值
         *
         * 处理可能的数组格式，确保返回字符串
         *
         * @since 1.1.0
         *
         * @param mixed $color_value 颜色值（可能是字符串或数组）
         *
         * @return string 处理后的颜色字符串
         */
        private function get_safe_color_value( $color_value ) {
            if ( is_array( $color_value ) ) {
                if ( isset( $color_value['color'] ) && is_string( $color_value['color'] ) ) {
                    return $color_value['color'];
                } elseif ( isset( $color_value[0] ) && is_string( $color_value[0] ) ) {
                    return $color_value[0];
                } else {
                    return '';
                }
            } elseif ( is_string( $color_value ) ) {
                return $color_value;
            } else {
                return '';
            }
        }

        /**
         * 渲染边框字段 - 组合式架构
         *
         * 使用PILI::field()方法组合基础字段来构建边框设置界面。
         *
         * @since 1.1.0
         */
        public function render() {

            $args = wp_parse_args( $this->field, array(
                'top'    => true,
                'right'  => true,
                'bottom' => true,
                'left'   => true,
                'all'    => false,
                'color'  => true,
                'style'  => true,
                'unit'   => 'px',
            ) );

            $border_styles = array(
                'solid'  => pili__( '实线' ),
                'dashed' => pili__( '虚线' ),
                'dotted' => pili__( '点线' ),
                'double' => pili__( '双线' ),
                'groove' => pili__( '凹槽' ),
                'ridge'  => pili__( '凸起' ),
                'inset'  => pili__( '内嵌' ),
                'outset' => pili__( '外凸' ),
                'none'   => pili__( '无边框' ),
            );

            $default_value = array(
                'top'    => '',
                'right'  => '',
                'bottom' => '',
                'left'   => '',
                'color'  => '',
                'style'  => 'solid',
                'all'    => '',
            );

            $default_value = ( ! empty( $this->field['default'] ) ) ? wp_parse_args( $this->field['default'], $default_value ) : $default_value;
            $this->value = wp_parse_args( $this->value, $default_value );

            echo $this->field_before();

            echo '<div class="pili-border-field">';
            echo '<div class="space-y-6 p-6 bg-gray-50 rounded-lg border border-gray-200">';

            echo '<div>';
            echo '<h4 class="text-sm font-medium text-gray-700 mb-4">' . pili_esc_html__( '边框宽度' ) . '</h4>';

            if ( ! empty( $args['all'] ) ) {
                echo '<div class="flex justify-center">';

                $width_field = array(
                    'id'          => 'all',
                    'type'        => 'text',
                    'placeholder' => pili__( '请选择…' ),
                    'attributes'  => array(
                        'inputmode' => 'numeric',
                        'pattern'   => '[0-9]*\.?[0-9]*',
                        'class'     => 'text-right',
                    ),
                );

                $width_field['id'] = $this->field_id() . '_all';
                $width_field['name'] = $this->field_name( '[all]' );
                PILI::field( $width_field, $this->value['all'], '', '' );
                echo '</div>';
            } else {
                echo '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">';

                $directions = array(
                    'top'    => pili__( '上边框' ),
                    'right'  => pili__( '右边框' ),
                    'bottom' => pili__( '下边框' ),
                    'left'   => pili__( '左边框' ),
                );

                foreach ( $directions as $direction => $label ) {
                    if ( ! empty( $args[$direction] ) ) {
                        echo '<div>';
                        echo '<label class="block text-xs font-medium text-gray-600 mb-1">' . esc_html( $label ) . '</label>';

                        $width_field = array(
                            'id'          => $direction,
                            'type'        => 'text',
                            'placeholder' => $label,
                            'attributes'  => array(
                                'inputmode' => 'numeric',
                                'pattern'   => '[0-9]*\.?[0-9]*',
                                'class'     => 'text-right',
                            ),
                        );

                        $width_field['id'] = $this->field_id() . '_' . $direction;
                        $width_field['name'] = $this->field_name( '[' . $direction . ']' );
                        PILI::field( $width_field, $this->value[$direction], '', '' );
                        echo '</div>';
                    }
                }

                echo '</div>';
            }

            echo '</div>';

            echo '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">';

            if ( ! empty( $args['style'] ) ) {
                echo '<div>';
                echo '<h4 class="text-sm font-medium text-gray-700 mb-3">' . pili_esc_html__( '边框样式' ) . '</h4>';

                $select_unique = $this->unique . '[' . $this->field['id'] . ']';
                $style_field = array(
                    'id'          => 'style',
                    'type'        => 'select',
                    'title'       => '',
                    'placeholder' => pili__( '请选择…' ),
                    'options'     => $border_styles,
                    'clearable'   => true,
                    'searchable'  => false,
                    'name'        => $this->field_name( '[style]' ),
                );
                $current_style = isset( $this->value['style'] ) ? $this->value['style'] : '';
                PILI::field( $style_field, $current_style, $select_unique, 'border', $this->field['id'] );
                echo '</div>';
            }

            if ( ! empty( $args['color'] ) ) {
                echo '<div>';
                echo '<h4 class="text-sm font-medium text-gray-700 mb-3">' . pili_esc_html__( '边框颜色' ) . '</h4>';

                $color_field = array(
                    'id'      => 'color',
                    'type'    => 'color',
                    'default' => '#000000',
                );

                $color_value = $this->get_safe_color_value( isset( $this->value['color'] ) ? $this->value['color'] : '' );

                $color_field['id'] = $this->field_id() . '_color';
                $color_field['name'] = $this->field_name( '[color]' );
                PILI::field( $color_field, $color_value, '', '' );
                echo '</div>';
            }

            echo '</div>';

            echo '<div class="mt-6">';
            echo '<h4 class="text-sm font-medium text-gray-700 mb-3">' . pili_esc_html__( '边框预览' ) . '</h4>';
            echo '<div class="border-preview p-4 bg-white rounded-lg border border-gray-200 min-h-24 flex items-center justify-center text-sm text-gray-500">';
            echo pili_esc_html__( '边框预览效果' );
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo $this->field_after();
        }



        /**
         * 加载字段资源 - 组合式架构
         *
         * 在组合式架构中，资源加载主要由基础字段负责。
         * 这里只加载border字段特有的资源。
         *
         * @since 1.1.0
         */
        public function enqueue() {
            if ( class_exists( __NAMESPACE__ . '\PILI_Field_color' ) ) {
                $color_stub = new PILI_Field_color(
                    array(
                        'id'   => $this->field['id'] . '_border_color',
                        'type' => 'color',
                    ),
                    '',
                    $this->unique,
                    '',
                    $this->field['id']
                );
                $color_stub->enqueue();
            }

            parent::enqueue();

            $handle       = pili_asset_handle( 'field-border' );
            $color_handle = pili_asset_handle( 'field-color' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/border.js',
                array( 'jquery', $color_handle ),
                PILI_Setup::$version,
                true
            );

            pili_localize_bag( $handle, 'border',
                array(
                    'strings' => array(
                        'previewDefault' => pili__( '边框预览效果' ),
                        'widthTop'       => pili__( '上' ),
                        'widthRight'     => pili__( '右' ),
                        'widthBottom'    => pili__( '下' ),
                        'widthLeft'      => pili__( '左' ),
                        'styles'         => array(
                            'solid'  => pili__( '实线' ),
                            'dashed' => pili__( '虚线' ),
                            'dotted' => pili__( '点线' ),
                            'double' => pili__( '双线' ),
                            'groove' => pili__( '凹槽' ),
                            'ridge'  => pili__( '凸起' ),
                            'inset'  => pili__( '内嵌' ),
                            'outset' => pili__( '外凸' ),
                            'none'   => pili__( '无边框' ),
                        ),
                    ),
                )
            );
        }

        /**
         * 输出CSS样式
         *
         * 根据字段值生成对应的CSS代码。
         *
         * @since 1.0
         *
         * @return string CSS代码
         */
        public function output() {
            $output = '';
            $unit = ! empty( $this->field['unit'] ) ? $this->field['unit'] : 'px';
            $important = ! empty( $this->field['output_important'] ) ? '!important' : '';
            $element = ( is_array( $this->field['output'] ) ) ? join( ',', $this->field['output'] ) : $this->field['output'];

            if ( empty( $element ) ) {
                return $output;
            }

            $top    = ( isset( $this->value['top'] )    && $this->value['top']    !== '' ) ? $this->value['top']    : '';
            $right  = ( isset( $this->value['right'] )  && $this->value['right']  !== '' ) ? $this->value['right']  : '';
            $bottom = ( isset( $this->value['bottom'] ) && $this->value['bottom'] !== '' ) ? $this->value['bottom'] : '';
            $left   = ( isset( $this->value['left'] )   && $this->value['left']   !== '' ) ? $this->value['left']   : '';
            $style  = ( isset( $this->value['style'] )  && $this->value['style']  !== '' ) ? $this->value['style']  : '';
            $color = $this->get_safe_color_value( isset( $this->value['color'] ) ? $this->value['color'] : '' );
            $all    = ( isset( $this->value['all'] )    && $this->value['all']    !== '' ) ? $this->value['all']    : '';

            if ( ! empty( $this->field['all'] ) && ( $all !== '' || $color !== '' || $style !== '' ) ) {
                $output  = $element . '{';
                $output .= ( $all   !== '' ) ? 'border-width:' . $all . $unit . $important . ';' : '';
                $output .= ( $color !== '' ) ? 'border-color:' . $color . $important . ';' : '';
                $output .= ( $style !== '' ) ? 'border-style:' . $style . $important . ';' : '';
                $output .= '}';
            } else if ( $top !== '' || $right !== '' || $bottom !== '' || $left !== '' || $color !== '' || $style !== '' ) {
                $output  = $element . '{';
                $output .= ( $top    !== '' ) ? 'border-top-width:' . $top . $unit . $important . ';' : '';
                $output .= ( $right  !== '' ) ? 'border-right-width:' . $right . $unit . $important . ';' : '';
                $output .= ( $bottom !== '' ) ? 'border-bottom-width:' . $bottom . $unit . $important . ';' : '';
                $output .= ( $left   !== '' ) ? 'border-left-width:' . $left . $unit . $important . ';' : '';
                $output .= ( $color  !== '' ) ? 'border-color:' . $color . $important . ';' : '';
                $output .= ( $style  !== '' ) ? 'border-style:' . $style . $important . ';' : '';
                $output .= '}';
            }

            return $output;
        }
    }
}
