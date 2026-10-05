<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 文本字段类型
 * 
 * 这个类实现了基础的文本输入字段功能。
 * 支持各种文本输入类型，如text、email、url、password等。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_text' ) ) {
    
    /**
     * PILI_Field_text 文本字段类
     * 
     * 提供文本输入字段的完整功能，包括：
     * - 基础文本输入
     * - 邮箱输入
     * - URL输入
     * - 密码输入
     * - 数字输入
     * 
     * @since 1.0
     */
    class PILI_Field_text extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化文本字段实例。
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
         * 渲染文本字段
         *
         * 输出文本输入字段的HTML代码。
         *
         * @since 1.0
         */
        public function render() {

            $type = ( ! empty( $this->field['attributes']['type'] ) ) ? $this->field['attributes']['type'] : 'text';

            $placeholder = ! empty( $this->field['placeholder'] ) ? $this->field['placeholder'] : '';

            $title = ! empty( $this->field['title'] ) ? $this->field['title'] : '';

            $desc = ! empty( $this->field['desc'] ) ? $this->field['desc'] : '';

            echo $this->field_before();

            echo '<div>';

            echo '<div class="mt-2">';

            $rule      = self::resolve_rule( $this->field );
            $has_error = ! empty( $this->field['_error'] );
            $err_id    = $this->field_id() . '-error';

            $input_classes = 'block w-full !min-h-10 !h-10 rounded-md bg-white !px-3 !py-2.5 appearance-none !text-base/6 !leading-6 box-border text-gray-900 border border-gray-300 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none sm:text-sm/6';

            if ( $type === 'number' ) {
                $input_classes .= ' text-right';
            }

            if ( $has_error ) {
                $input_classes .= ' pili-input--invalid';
            }

            echo '<input type="' . esc_attr( $type ) . '" ';
            echo 'name="' . esc_attr( $this->field_name() ) . '" ';
            echo 'id="' . esc_attr( $this->field_id() ) . '" ';
            echo 'value="' . esc_attr( $this->value ) . '" ';
            echo 'class="pili-input pili-focusable ' . esc_attr( $input_classes ) . '" ';

            if ( ! empty( $placeholder ) ) {
                echo 'placeholder="' . esc_attr( $placeholder ) . '" ';
            }

            if ( '' !== $rule ) {
                echo 'data-pili-rule="' . esc_attr( $rule ) . '" ';
                if ( ! empty( $this->field['rule_message'] ) ) {
                    echo 'data-pili-rule-message="' . esc_attr( (string) $this->field['rule_message'] ) . '" ';
                }
            }

            $described = array();
            if ( ! empty( $desc ) ) {
                $described[] = $this->field_id() . '-description';
            }
            $described[] = $err_id;
            echo 'aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '" ';
            if ( $has_error ) {
                echo 'aria-invalid="true" ';
            }

            echo $this->field_attributes() . ' />';

            echo '</div>';

            if ( ! empty( $desc ) ) {
                echo '<p id="' . esc_attr( $this->field_id() . '-description' ) . '" class="mt-2 text-sm text-gray-500">';
                echo wp_kses_post( $desc );
                echo '</p>';
            }

            $err_text = $has_error ? (string) $this->field['_error'] : '';
            echo '<p id="' . esc_attr( $err_id ) . '" class="pili-input-error' . ( $has_error ? ' is-visible' : '' ) . '" role="alert">';
            echo esc_html( $err_text );
            echo '</p>';

            if ( ! empty( $this->field['after'] ) ) {
                echo '<div class="pili-after-text">' . $this->field['after'] . '</div>';
            }

            if ( ! empty( $this->field['help'] ) ) {
                echo '<div class="pili-help">';
                echo '<span class="pili-help-text">' . $this->field['help'] . '</span>';
                echo '<i class="pili-help-icon">?</i>';
                echo '</div>';
            }

            echo '</div>';

        }
        
        /**
         * 加载字段资源
         * 
         * 加载文本字段所需的CSS和JavaScript文件。
         * 
         * @since 1.0
         */
        public function enqueue() {
            $css = pili_asset_handle( 'field-text' );
            $js  = $css;
            $ver = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : PILI_Setup::$version;
            wp_enqueue_style(
                $css,
                PILI_Setup::$url . '/assets/css/fields/text.css',
                array(),
                $ver
            );
            wp_enqueue_script(
                $js,
                PILI_Setup::$url . '/assets/js/fields/text.js',
                array( 'jquery' ),
                $ver,
                true
            );
            pili_localize_bag(
                $js,
                'text',
                array(
                    'invalidUrl'   => pili__( '请填写完整网址，必须以 http:// 或 https:// 开头。' ),
                    'invalidHost'  => pili__( '网址格式不正确。' ),
                    'invalidEmail' => pili__( '请填写有效的邮箱地址。' ),
                )
            );
        }
        
        /**
         * Resolve builtin rule: field.rule, or validate when it is a non-callable string.
         *
         * @param array<string,mixed> $field Field.
         * @return string
         */
        public static function resolve_rule( $field ) {
            if ( ! is_array( $field ) ) {
                return '';
            }
            if ( ! empty( $field['rule'] ) && is_string( $field['rule'] ) ) {
                return sanitize_key( $field['rule'] );
            }
            if ( isset( $field['validate'] ) && is_string( $field['validate'] ) && ! is_callable( $field['validate'] ) ) {
                return sanitize_key( $field['validate'] );
            }
            $type = ( ! empty( $field['attributes']['type'] ) && is_string( $field['attributes']['type'] ) )
                ? strtolower( $field['attributes']['type'] )
                : '';
            if ( in_array( $type, array( 'url', 'email' ), true ) ) {
                return $type;
            }
            return '';
        }

        /**
         * Empty string = pass. Non-empty = error message.
         *
         * @param string               $rule  url|email.
         * @param string               $value Raw.
         * @param array<string,mixed>  $field Field.
         * @return string
         */
        public static function check_rule( $rule, $value, $field = array() ) {
            $rule  = sanitize_key( (string) $rule );
            $value = is_string( $value ) ? trim( $value ) : '';
            if ( '' === $rule || '' === $value ) {
                return '';
            }
            $custom = ( is_array( $field ) && ! empty( $field['rule_message'] ) )
                ? (string) $field['rule_message']
                : '';
            if ( 'url' === $rule ) {
                if ( ! preg_match( '#^https?://#i', $value ) ) {
                    return '' !== $custom ? $custom : pili__( '请填写完整网址，必须以 http:// 或 https:// 开头。' );
                }
                $host = wp_parse_url( $value, PHP_URL_HOST );
                if ( ! is_string( $host ) || '' === $host ) {
                    return '' !== $custom ? $custom : pili__( '网址格式不正确。' );
                }
                return '';
            }
            if ( 'email' === $rule ) {
                if ( ! is_email( $value ) ) {
                    return '' !== $custom ? $custom : pili__( '请填写有效的邮箱地址。' );
                }
                return '';
            }
            return '';
        }

        public function validate( $value ) {
            
            $type = ( ! empty( $this->field['attributes']['type'] ) ) ? $this->field['attributes']['type'] : 'text';
            
            switch ( $type ) {
                case 'email':
                    $value = sanitize_email( $value );
                    if ( ! empty( $value ) && ! is_email( $value ) ) {
                        $value = '';
                    }
                    break;
                    
                case 'url':
                    $value = esc_url_raw( $value );
                    break;
                    
                case 'number':
                    $value = intval( $value );
                    break;
                    
                case 'password':
                    $value = $value;
                    break;
                    
                case 'tel':
                    $value = preg_replace( '/[^0-9+\-\s\(\)]/', '', $value );
                    break;
                    
                default:
                    $value = sanitize_text_field( $value );
                    break;
            }
            
            if ( ! empty( $this->field['max_length'] ) ) {
                $max_length = intval( $this->field['max_length'] );
                if ( strlen( $value ) > $max_length ) {
                    $value = substr( $value, 0, $max_length );
                }
            }
            
            if ( ! empty( $this->field['min_length'] ) ) {
                $min_length = intval( $this->field['min_length'] );
                if ( strlen( $value ) < $min_length ) {
                    $value = '';
                }
            }
            
            $value = apply_filters( 'pili_validate_text_field', $value, $this->field );
            $value = apply_filters( "pili_validate_text_field_{$this->field['id']}", $value, $this->field );
            
            return $value;
        }
        
        /**
         * 获取字段默认属性
         * 
         * 返回文本字段的默认HTML属性。
         * 
         * @since 1.0
         * 
         * @return array 默认属性数组
         */
        public function get_default_attributes() {
            
            $attributes = array();
            
            $attributes['class'] = 'pilidoc-post-pili-field-text';
            
            if ( ! empty( $this->field['max_length'] ) ) {
                $attributes['maxlength'] = intval( $this->field['max_length'] );
            }
            
            if ( ! empty( $this->field['min_length'] ) ) {
                $attributes['minlength'] = intval( $this->field['min_length'] );
            }
            
            if ( $this->is_required() ) {
                $attributes['required'] = 'required';
            }
            
            if ( ! empty( $this->field['readonly'] ) ) {
                $attributes['readonly'] = 'readonly';
            }
            
            if ( ! empty( $this->field['disabled'] ) ) {
                $attributes['disabled'] = 'disabled';
            }
            
            return $attributes;
        }
    }
}
