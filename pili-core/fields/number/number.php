<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 数字输入字段类型
 * 
 * 这个类实现了功能强大的数字输入字段。
 * 支持整数和小数输入、数值范围限制、步长控制、格式化显示、增减按钮等高级功能。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_number' ) ) {
    
    /**
     * PILI_Field_number 数字输入字段类
     * 
     * 提供现代化数字输入字段的完整功能，包括：
     * - 整数和小数输入支持
     * - 最小值/最大值限制
     * - 步长控制（step）
     * - 数字格式化显示
     * - 增减按钮（spinner controls）
     * - 实时验证和错误提示
     * - 键盘快捷键支持（上下箭头调整数值）
     * - 无障碍访问优化
     * - 多种输入模式（基础、货币、百分比等）
     * - 单位显示支持（px、%、em等）
     * - 数值范围指示器
     * - 自动格式化和千分位分隔符
     * 
     * @since 1.0
     */
    class PILI_Field_number extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化数字字段实例。
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
         * 渲染数字输入字段
         *
         * 输出功能完整的数字输入字段HTML代码，包含所有高级功能。
         *
         * @since 1.0
         */
        public function render() {
            
            $args = wp_parse_args( $this->field, array(
                'min'              => '',           // 最小值
                'max'              => '',           // 最大值
                'step'             => '1',          // 步长
                'unit'             => '',           // 单位
                'unit_position'    => 'right',      // 单位位置：left/right
                'mode'             => 'basic',      // 输入模式：basic/currency/percentage
                'precision'        => 0,           // 小数位数
                'thousand_sep'     => false,       // 是否显示千分位分隔符
                'spinner'          => false,       // 是否显示增减按钮（已移除）
                'range_indicator'  => false,       // 是否显示范围指示器
                'format_display'   => true,        // 是否格式化显示
                'width'            => 'auto',       // 字段宽度：auto/small/medium/large/full
                'placeholder'      => '',          // 占位符文本
                'readonly'         => false,       // 是否只读
                'disabled'         => false,       // 是否禁用
                'required'         => false,       // 是否必填
                'validation'       => true,        // 是否启用实时验证
                'keyboard_nav'     => true,        // 是否启用键盘导航
                'auto_select'      => false,       // 聚焦时是否自动选中
                'currency_symbol'  => '$',         // 货币符号
                'currency_position' => 'left',     // 货币符号位置
            ) );
            
            $value = $this->value;
            if ( $value === '' || $value === null ) {
                $value = isset( $this->field['default'] ) ? $this->field['default'] : '';
            }
            
            $field_id = 'pili-number-' . uniqid();
            
            echo $this->field_before();
            echo '<div class="pili-number-field-container relative" data-field-id="' . esc_attr( $field_id ) . '">';
            $container_classes = array(
                'pili-number-input-container',
                'relative',
                'inline-flex',
                'items-center',
                'bg-white',
                'rounded-md',
                'outline-1',
                '-outline-offset-1',
                'outline-gray-300',
                'focus-within:outline-2',
                'focus-within:-outline-offset-2',
                'focus-within:outline-indigo-600'
            );
            
            switch ( $args['width'] ) {
                case 'small':
                    $container_classes[] = 'w-28'; 
                    break;
                case 'medium':
                    $container_classes[] = 'w-32';
                    break;
                case 'large':
                    $container_classes[] = 'w-48';
                    break;
                case 'full':
                    $container_classes[] = 'w-full';
                    break;
                default:
                    $container_classes[] = 'w-auto min-w-28';
                    break;
            }
            
            if ( $args['disabled'] ) {
                $container_classes[] = 'bg-gray-50 border-gray-200 cursor-not-allowed opacity-60';
            }
            
            echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '">';
            
            if ( ( $args['unit'] && $args['unit_position'] === 'left' ) || 
                 ( $args['mode'] === 'currency' && $args['currency_position'] === 'left' ) ) {
                $symbol = $args['mode'] === 'currency' ? $args['currency_symbol'] : $args['unit'];
                echo '<span class="pili-number-prefix flex items-center px-3 h-full text-sm text-gray-500 rounded-l-md select-none">';
                echo esc_html( $symbol );
                echo '</span>';
            }
            
            $input_classes = array(
                'pili-number-input',
                'flex-1',
                'min-w-0',
                'px-3',
                'py-2',
                'text-sm',
                'border-0',
                'bg-transparent',
                'focus:outline-none',
                'focus:ring-0',
                'appearance-none'
            );
            
            if ( $args['unit'] && $args['unit_position'] === 'left' ) {
                $input_classes[] = 'rounded-r-md';
            } elseif ( $args['unit'] && $args['unit_position'] === 'right' ) {
                $input_classes[] = 'rounded-l-md';
            } else {
                $input_classes[] = 'rounded-md';
            }
            
            if ( $args['disabled'] ) {
                $input_classes[] = 'cursor-not-allowed text-gray-400';
            }

            
            $text_field = array(
                'id'          => $this->field['id'] . '_proxy',
                'type'        => 'text',
                'title'       => '',
                'placeholder' => $args['placeholder'],
                'name'        => $this->field_name(),
                'attributes'  => array(
                    'type'        => 'number',
                    'inputmode'   => ( $args['precision'] > 0 ? 'decimal' : 'numeric' ),
                    'class'       => implode( ' ', $input_classes ) . ' pili-number-input',
                    'data-mode'   => $args['mode'],
                    'data-precision' => $args['precision'],
                    'data-thousand-sep' => $args['thousand_sep'] ? 'true' : 'false',
                    'data-format-display' => $args['format_display'] ? 'true' : 'false',
                    'data-validation' => $args['validation'] ? 'true' : 'false',
                    'data-keyboard-nav' => $args['keyboard_nav'] ? 'true' : 'false',
                    'data-auto-select' => $args['auto_select'] ? 'true' : 'false',
                ),
            );
            if ( $args['min'] !== '' ) { $text_field['attributes']['min'] = $args['min']; $text_field['attributes']['data-min'] = $args['min']; }
            if ( $args['max'] !== '' ) { $text_field['attributes']['max'] = $args['max']; $text_field['attributes']['data-max'] = $args['max']; }
            if ( $args['step'] !== '' ) { $text_field['attributes']['step'] = $args['step']; $text_field['attributes']['data-step'] = $args['step']; }
            if ( $args['readonly'] ) { $text_field['attributes']['readonly'] = 'readonly'; }
            if ( $args['disabled'] ) { $text_field['attributes']['disabled'] = 'disabled'; }
            if ( $args['required'] ) { $text_field['attributes']['required'] = 'required'; $text_field['attributes']['aria-required'] = 'true'; }
            $text_field['attributes']['aria-label'] = isset( $this->field['title'] ) ? $this->field['title'] : pili__( '数字输入' );
            if ( $args['min'] !== '' || $args['max'] !== '' ) { $text_field['attributes']['aria-describedby'] = $field_id . '-range-desc'; }

            PILI::field( $text_field, $value, $this->unique, 'field/number', $this->field['id'] );
            
            if ( ( $args['unit'] && $args['unit_position'] === 'right' ) ||
                 ( $args['mode'] === 'currency' && $args['currency_position'] === 'right' ) ) {
                $symbol = $args['mode'] === 'currency' ? $args['currency_symbol'] : $args['unit'];

                $suffix_classes = array(
                    'pili-number-suffix',
                    'flex',
                    'items-center',
                    'px-3',
                    'h-full', 
                    'text-sm',
                    'text-gray-500',
                    'select-none'
                );

                $suffix_classes[] = 'rounded-r-md';

                echo '<span class="' . esc_attr( implode( ' ', $suffix_classes ) ) . '">';
                echo esc_html( $symbol );
                echo '</span>';
            }
            
            
            echo '</div>';

            if ( $args['range_indicator'] && $args['min'] !== '' && $args['max'] !== '' ) {
                $slider_field = array(
                    'id'        => $field_id . '_slider',
                    'type'      => 'slider',
                    'title'     => '',
                    'min'       => $args['min'],
                    'max'       => $args['max'],
                    'step'      => $args['step'] !== '' ? $args['step'] : 1,
                    'range'     => false,
                    'show_input'=> false,
                    'tooltip'   => true,
                    'color'     => 'blue',
                );
                echo '<div class="pili-number-slider-bridge mt-3" data-min="' . esc_attr( $args['min'] ) . '" data-max="' . esc_attr( $args['max'] ) . '">';
                PILI::field( $slider_field, $value === '' ? $args['min'] : $value, $this->unique, 'field/number' );
                echo '</div>';
            }

            echo '<div class="pili-number-error-message mt-1 text-sm text-red-600 hidden" role="alert" aria-live="polite"></div>';

            if ( $args['min'] !== '' || $args['max'] !== '' ) {
                echo '<div id="' . esc_attr( $field_id ) . '-range-desc" class="sr-only">';
                if ( $args['min'] !== '' && $args['max'] !== '' ) {
                    echo esc_html( sprintf( pili__( '数值范围：%1$s 到 %2$s' ), $args['min'], $args['max'] ) );
                } elseif ( $args['min'] !== '' ) {
                    echo esc_html( sprintf( pili__( '最小值：%s' ), $args['min'] ) );
                } elseif ( $args['max'] !== '' ) {
                    echo esc_html( sprintf( pili__( '最大值：%s' ), $args['max'] ) );
                }
                echo '</div>';
            }

            echo '</div>'; 

            echo $this->field_after();
        }

        /**
         * 加载字段所需的JavaScript和CSS资源
         *
         * 在需要时加载number字段的前端资源。
         *
         * @since 1.0
         */
        public function enqueue() {

            $handle = pili_asset_handle( 'field-number' );
            if ( ! wp_script_is( $handle, 'enqueued' ) ) {
                wp_enqueue_script(
                    $handle,
                    PILI_Setup::$url . '/assets/js/fields/number.js',
                    array( 'jquery' ),
                    PILI_Setup::$version,
                    true
                );

                pili_localize_bag( $handle, 'number', array(
                    'i18n' => array(
                        'invalid_number' => pili__( '数字无效' ),
                        'out_of_range' => pili__( '超出范围' ),
                        'below_minimum' => pili__( '不能小于 %s' ),
                        'above_maximum' => pili__( '不能大于 %s' ),
                        'required_field' => pili__( '此字段为必填项' ),
                        'invalid_step' => pili__( '步进必须为 %s' ),
                        'decimal_places' => pili__( '最多 %d 位小数' ),
                    ),
                    'settings' => array(
                        'thousand_separator' => ',',
                        'decimal_separator'  => '.',
                        'currency_symbol'    => '$',
                        'percentage_symbol'  => '%',
                    ),
                ) );
            }
        }

        /**
         * 验证和清理字段值
         *
         * 对用户输入的数字进行验证和清理，确保数据安全性。
         *
         * @since 1.0
         *
         * @param mixed $value 要验证的值
         *
         * @return mixed 验证后的值
         */
        public function validate( $value ) {

            if ( $value === '' || $value === null ) {
                return '';
            }

            $value = sanitize_text_field( $value );

            $value = str_replace( ',', '', $value );

            if ( ! is_numeric( $value ) ) {
                return new WP_Error( 'invalid_number', pili__( '请输入有效的数字' ) );
            }

            $numeric_value = floatval( $value );

            if ( isset( $this->field['min'] ) && $this->field['min'] !== '' ) {
                $min = floatval( $this->field['min'] );
                if ( $numeric_value < $min ) {
                    return new WP_Error( 'below_minimum', sprintf( pili__( '数值不能小于 %s' ), $min ) );
                }
            }

            if ( isset( $this->field['max'] ) && $this->field['max'] !== '' ) {
                $max = floatval( $this->field['max'] );
                if ( $numeric_value > $max ) {
                    return new WP_Error( 'above_maximum', sprintf( pili__( '数值不能大于 %s' ), $max ) );
                }
            }

            if ( isset( $this->field['step'] ) && $this->field['step'] !== '' && $this->field['step'] !== 'any' ) {
                $step = floatval( $this->field['step'] );
                $min = isset( $this->field['min'] ) && $this->field['min'] !== '' ? floatval( $this->field['min'] ) : 0;

                if ( $step > 0 ) {
                    $remainder = fmod( $numeric_value - $min, $step );
                    if ( abs( $remainder ) > 0.0001 ) {
                        return new WP_Error( 'invalid_step', sprintf( pili__( '数值必须是 %s 的倍数' ), $step ) );
                    }
                }
            }

            if ( isset( $this->field['precision'] ) && $this->field['precision'] >= 0 ) {
                $precision = intval( $this->field['precision'] );
                $decimal_places = strlen( substr( strrchr( $value, '.' ), 1 ) );

                if ( $decimal_places > $precision ) {
                    return new WP_Error( 'decimal_places', sprintf( pili__( '小数位数不能超过 %d 位' ), $precision ) );
                }

                $numeric_value = round( $numeric_value, $precision );
            }

            return $numeric_value;
        }

        /**
         * 格式化数字显示
         *
         * 根据字段配置格式化数字的显示方式。
         *
         * @since 1.0
         *
         * @param mixed $value 要格式化的值
         *
         * @return string 格式化后的字符串
         */
        public function format_value( $value ) {

            if ( $value === '' || $value === null || ! is_numeric( $value ) ) {
                return '';
            }

            $numeric_value = floatval( $value );
            $args = wp_parse_args( $this->field, array(
                'precision'     => 0,
                'thousand_sep'  => false,
                'mode'          => 'basic',
                'unit'          => '',
                'currency_symbol' => '$',
            ) );

            $formatted = number_format( $numeric_value, $args['precision'], '.', $args['thousand_sep'] ? ',' : '' );

            switch ( $args['mode'] ) {
                case 'currency':
                    $formatted = $args['currency_symbol'] . $formatted;
                    break;
                case 'percentage':
                    $formatted = $formatted . '%';
                    break;
                default:
                    if ( $args['unit'] ) {
                        $formatted = $formatted . $args['unit'];
                    }
                    break;
            }

            return $formatted;
        }
    }
}
