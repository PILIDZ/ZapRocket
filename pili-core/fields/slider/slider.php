<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework Slider 字段类型
 * 
 * 这个字段类型提供了一个现代化的滑块界面，支持单值和范围选择、
 * 实时预览、键盘快捷键、自定义单位显示等高级功能。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_slider' ) ) {
    
    /**
     * PILI_Field_slider 滑块字段类
     * 
     * 功能特性：
     * - 单值和范围滑块支持
     * - 实时数值显示和预览
     * - 键盘快捷键支持
     * - 移动端友好设计
     * - 自定义单位和格式化
     * - 平滑动画过渡效果
     * - 完整的数据验证
     * 
     * @since 1.0
     */
    class PILI_Field_slider extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化滑块字段实例。
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
         * 渲染滑块字段
         * 
         * 生成现代化的滑块界面，支持单值和范围选择。
         * 
         * @since 1.0
         */
        public function render() {
            
            echo $this->field_before();
            
            $args = wp_parse_args( $this->field, array(
                'min'           => 0,
                'max'           => 100,
                'step'          => 1,
                'unit'          => '',
                'range'         => false,
                'show_input'    => true,
                'show_labels'   => true,
                'show_ticks'    => false,
                'tick_step'     => 10,
                'precision'     => 0,
                'prefix'        => '',
                'suffix'        => '',
                'min_label' => pili__( '最小' ),
                'max_label' => pili__( '最大' ),
                'value_label' => pili__( '当前值' ),
                'color'         => 'blue',
                'size'          => 'medium',
                'animate'       => true,
                'keyboard'      => true,
                'tooltip'       => true,
                'format_value'  => null,
            ) );
            
            $value = $this->value;
            if ( $args['range'] ) {
                if ( ! is_array( $value ) ) {
                    $value = array(
                        'min' => $args['min'],
                        'max' => $args['max'],
                    );
                }
                $value = wp_parse_args( $value, array(
                    'min' => $args['min'],
                    'max' => $args['max'],
                ) );
            } else {
                if ( $value === '' || $value === null ) {
                    $value = $args['min'];
                }
                $value = (float) $value;
            }
            
            $field_id = 'pili-slider-' . uniqid();
            
            $container_classes = array(
                'pili-slider-field',
                'pili-slider-' . $args['color'],
                'pili-slider-' . $args['size'],
            );
            
            if ( $args['range'] ) {
                $container_classes[] = 'pili-slider-range';
            }
            
            if ( $args['animate'] ) {
                $container_classes[] = 'pili-slider-animate';
            }
            
            echo '<div class="' . implode( ' ', $container_classes ) . '" data-field-id="' . esc_attr( $this->field['id'] ) . '">';
            
            $slider_data = array(
                'min'          => $args['min'],
                'max'          => $args['max'],
                'step'         => $args['step'],
                'range'        => $args['range'],
                'precision'    => $args['precision'],
                'keyboard'     => $args['keyboard'],
                'tooltip'      => $args['tooltip'],
                'animate'      => $args['animate'],
                'color'        => $args['color'],
                'formatValue'  => $args['format_value'],
            );
            
            echo '<div class="relative mb-4">';
            
            $track_classes = 'pili-slider-track relative w-full h-2 bg-gray-200 rounded-full cursor-pointer transition-colors duration-200 hover:bg-gray-300';
            echo '<div class="' . $track_classes . '" data-slider-config="' . esc_attr( json_encode( $slider_data ) ) . '">';
            
            echo '<div class="pili-slider-progress absolute top-0 left-0 h-full bg-' . esc_attr( $args['color'] ) . '-500 rounded-full transition-all duration-200"></div>';
            
            if ( $args['range'] ) {
                echo '<div class="pili-slider-handle pili-slider-handle-min absolute top-1/2 -translate-y-1/2 w-5 h-5 bg-white border-2 border-' . esc_attr( $args['color'] ) . '-500 rounded-full cursor-grab shadow-md transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-' . esc_attr( $args['color'] ) . '-500 focus:ring-offset-2" tabindex="0" role="slider" aria-valuemin="' . esc_attr( $args['min'] ) . '" aria-valuemax="' . esc_attr( $args['max'] ) . '" aria-valuenow="' . esc_attr( $value['min'] ) . '"></div>';
                echo '<div class="pili-slider-handle pili-slider-handle-max absolute top-1/2 -translate-y-1/2 w-5 h-5 bg-white border-2 border-' . esc_attr( $args['color'] ) . '-500 rounded-full cursor-grab shadow-md transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-' . esc_attr( $args['color'] ) . '-500 focus:ring-offset-2" tabindex="0" role="slider" aria-valuemin="' . esc_attr( $args['min'] ) . '" aria-valuemax="' . esc_attr( $args['max'] ) . '" aria-valuenow="' . esc_attr( $value['max'] ) . '"></div>';
            } else {
                echo '<div class="pili-slider-handle absolute top-1/2 -translate-y-1/2 w-5 h-5 bg-white border-2 border-' . esc_attr( $args['color'] ) . '-500 rounded-full cursor-grab shadow-md transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-' . esc_attr( $args['color'] ) . '-500 focus:ring-offset-2" tabindex="0" role="slider" aria-valuemin="' . esc_attr( $args['min'] ) . '" aria-valuemax="' . esc_attr( $args['max'] ) . '" aria-valuenow="' . esc_attr( $value ) . '"></div>';
            }
            
            if ( $args['tooltip'] ) {
                if ( $args['range'] ) {
                    echo '<div class="pili-slider-tooltip pili-slider-tooltip-min absolute -top-10 left-0 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 transition-opacity duration-200 pointer-events-none"></div>';
                    echo '<div class="pili-slider-tooltip pili-slider-tooltip-max absolute -top-10 right-0 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 transition-opacity duration-200 pointer-events-none"></div>';
                } else {
                    echo '<div class="pili-slider-tooltip absolute -top-10 left-0 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 transition-opacity duration-200 pointer-events-none"></div>';
                }
            }
            
            echo '</div>';
            
            if ( $args['show_ticks'] ) {
                echo '<div class="pili-slider-ticks flex justify-between mt-2">';
                $tick_count = ( $args['max'] - $args['min'] ) / $args['tick_step'];
                for ( $i = 0; $i <= $tick_count; $i++ ) {
                    $tick_value = $args['min'] + ( $i * $args['tick_step'] );
                    echo '<div class="text-xs text-gray-500">' . esc_html( $args['prefix'] ) . esc_html( $tick_value ) . esc_html( $args['suffix'] ) . '</div>';
                }
                echo '</div>';
            }
            
            echo '</div>';
            
            if ( $args['show_input'] ) {
                echo '<div class="flex items-center space-x-3">';
                
                if ( $args['range'] ) {
                    echo '<div class="flex items-center space-x-2">';
                    echo '<label class="text-sm font-medium text-gray-700">' . esc_html( (string) $args['min_label'] ) . ':</label>';
                    echo '<input type="number" name="' . esc_attr( $this->field_name( '[min]' ) ) . '" value="' . esc_attr( $value['min'] ) . '" min="' . esc_attr( $args['min'] ) . '" max="' . esc_attr( $args['max'] ) . '" step="' . esc_attr( $args['step'] ) . '" class="pili-slider-input pili-slider-input-min w-20 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-' . esc_attr( $args['color'] ) . '-500 focus:border-transparent" />';
                    if ( ! empty( $args['unit'] ) ) {
                        echo '<span class="text-sm text-gray-500">' . esc_html( $args['unit'] ) . '</span>';
                    }
                    echo '</div>';
                    
                    echo '<div class="flex items-center space-x-2">';
                    echo '<label class="text-sm font-medium text-gray-700">' . esc_html( (string) $args['max_label'] ) . ':</label>';
                    echo '<input type="number" name="' . esc_attr( $this->field_name( '[max]' ) ) . '" value="' . esc_attr( $value['max'] ) . '" min="' . esc_attr( $args['min'] ) . '" max="' . esc_attr( $args['max'] ) . '" step="' . esc_attr( $args['step'] ) . '" class="pili-slider-input pili-slider-input-max w-20 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-' . esc_attr( $args['color'] ) . '-500 focus:border-transparent" />';
                    if ( ! empty( $args['unit'] ) ) {
                        echo '<span class="text-sm text-gray-500">' . esc_html( $args['unit'] ) . '</span>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="flex items-center space-x-2">';
                    echo '<label class="text-sm font-medium text-gray-700">' . esc_html( (string) $args['value_label'] ) . ':</label>';
                    echo '<input type="number" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $args['min'] ) . '" max="' . esc_attr( $args['max'] ) . '" step="' . esc_attr( $args['step'] ) . '" class="pili-slider-input w-20 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-' . esc_attr( $args['color'] ) . '-500 focus:border-transparent" />';
                    if ( ! empty( $args['unit'] ) ) {
                        echo '<span class="text-sm text-gray-500">' . esc_html( $args['unit'] ) . '</span>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            } else {
                if ( $args['range'] ) {
                    echo '<input type="hidden" name="' . esc_attr( $this->field_name( '[min]' ) ) . '" value="' . esc_attr( $value['min'] ) . '" class="pili-slider-input pili-slider-input-min" />';
                    echo '<input type="hidden" name="' . esc_attr( $this->field_name( '[max]' ) ) . '" value="' . esc_attr( $value['max'] ) . '" class="pili-slider-input pili-slider-input-max" />';
                } else {
                    echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $value ) . '" class="pili-slider-input" />';
                }
            }
            
            if ( ! empty( $this->field['show_preview'] ) ) {
                echo '<div class="mt-4 p-3 bg-gray-50 rounded-lg">';
                echo '<div class="text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '实时预览' ) . '</div>';
                echo '<div class="pili-slider-preview text-lg font-semibold text-' . esc_attr( $args['color'] ) . '-600"></div>';
                echo '</div>';
            }
            
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
         * 加载字段资源
         * 
         * 加载滑块字段所需的JavaScript资源。
         * 
         * @since 1.0
         */
        public function enqueue() {
            
            $handle = pili_asset_handle( 'field-slider' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/slider.js',
                array( 'jquery' ),
                PILI_Setup::$version,
                true
            );
            
            pili_localize_bag( $handle, 'slider',
                array(
                    'strings' => array(
                        'min' => pili__( '最小' ),
                        'max' => pili__( '最大' ),
                        'value' => pili__( '值' ),
                        'range' => pili__( '范围' ),
                        'invalid' => pili__( '无效' ),
                    ),
                    'nonce'   => wp_create_nonce( 'pili_slider_nonce' ),
                )
            );
        }
        
        /**
         * 验证和清理字段数据
         * 
         * 对滑块字段的数据进行验证和清理。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return mixed 清理后的数据
         */
        public function validate( $value ) {
            
            $args = wp_parse_args( $this->field, array(
                'min'   => 0,
                'max'   => 100,
                'step'  => 1,
                'range' => false,
            ) );
            
            if ( $args['range'] ) {
                if ( ! is_array( $value ) ) {
                    return array(
                        'min' => $args['min'],
                        'max' => $args['max'],
                    );
                }
                
                $min = isset( $value['min'] ) ? (float) $value['min'] : $args['min'];
                $max = isset( $value['max'] ) ? (float) $value['max'] : $args['max'];
                
                $min = max( $args['min'], min( $args['max'], $min ) );
                $max = max( $args['min'], min( $args['max'], $max ) );
                
                if ( $min > $max ) {
                    $temp = $min;
                    $min = $max;
                    $max = $temp;
                }
                
                $validated = array(
                    'min' => $min,
                    'max' => $max,
                );
            } else {
                $validated = (float) $value;
                $validated = max( $args['min'], min( $args['max'], $validated ) );
            }
            
            $validated = apply_filters( 'pili_validate_slider_field', $validated, $this->field );
            $validated = apply_filters( "pili_validate_slider_field_{$this->field['id']}", $validated, $this->field );
            
            return $validated;
        }
    }
}
