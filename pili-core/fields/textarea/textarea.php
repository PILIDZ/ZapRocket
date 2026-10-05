<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Textarea 字段类 - 基础字段
 *
 * 提供多行文本输入功能，作为基础字段供其他复杂字段组合使用。
 * 支持字符计数、自动调整高度、语法高亮等高级功能。
 *
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_textarea' ) ) {

    /**
     * PILI_Field_textarea 多行文本字段类
     *
     * 基础字段类，提供完整的多行文本输入功能：
     * - 自动调整高度
     * - 字符计数和限制
     * - 语法高亮支持
     * - 拖拽调整大小
     * - 键盘快捷键
     * - 无障碍访问优化
     *
     * @since 1.1.0
     */
    class PILI_Field_textarea extends PILI_Fields {

        /**
         * 构造函数
         *
         * 初始化textarea字段实例。
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
         * 渲染多行文本字段 - 增强版
         *
         * 输出功能完整的多行文本输入字段，支持字符计数、自动调整等功能。
         *
         * @since 1.1.0
         */
        public function render() {

            $settings = $this->get_field_settings();

            echo $this->field_before();

            echo '<div class="pili-textarea-field" data-field-id="' . esc_attr( $this->field['id'] ) . '">';

            echo '<div class="relative">';

            $textarea_classes = $this->build_textarea_classes( $settings );

            echo '<textarea ';
            echo 'name="' . esc_attr( $this->field_name() ) . '" ';
            echo 'id="' . esc_attr( $this->field_id() ) . '" ';
            echo 'rows="' . esc_attr( $settings['rows'] ) . '" ';
            echo 'class="pili-textarea-input ' . esc_attr( $textarea_classes ) . '" ';

            if ( ! empty( $settings['placeholder'] ) ) {
                echo 'placeholder="' . esc_attr( $settings['placeholder'] ) . '" ';
            }

            if ( $settings['maxlength'] > 0 ) {
                echo 'maxlength="' . esc_attr( $settings['maxlength'] ) . '" ';
            }

            if ( $settings['minlength'] > 0 ) {
                echo 'minlength="' . esc_attr( $settings['minlength'] ) . '" ';
            }

            if ( $settings['auto_resize'] ) {
                echo 'data-auto-resize="true" ';
            }

            if ( $settings['show_count'] ) {
                echo 'data-show-count="true" ';
            }

            if ( ! empty( $settings['desc'] ) ) {
                echo 'aria-describedby="' . esc_attr( $this->field_id() . '-description' ) . '" ';
            }

            echo $this->field_attributes() . '>';
            echo esc_textarea( $this->value );
            echo '</textarea>';

            if ( $settings['show_count'] ) {
                $current_length = mb_strlen( $this->value );
                $max_length = $settings['maxlength'];

                $counter_classes = array(
                    'pili-textarea-counter',
                    'absolute',
                    'bottom-2',
                    'right-2',
                    'px-2',
                    'py-1',
                    'bg-white',
                    'bg-opacity-90',
                    'rounded',
                    'text-xs',
                    'text-gray-500',
                    'border',
                    'border-gray-200',
                    'shadow-sm',
                    'pointer-events-none',
                    'font-mono',
                    'select-none',
                    'z-10',
                );

                echo '<div class="' . implode( ' ', $counter_classes ) . '">';
                echo '<span class="current-count">' . $current_length . '</span>';
                if ( $max_length > 0 ) {
                    echo ' / <span class="max-count">' . $max_length . '</span>';
                }
                echo '</div>';
            }

            echo '</div>';

            if ( ! empty( $settings['desc'] ) ) {
                echo '<p id="' . esc_attr( $this->field_id() . '-description' ) . '" class="mt-2 text-sm text-gray-500">';
                echo wp_kses_post( $settings['desc'] );
                echo '</p>';
            }

            if ( ! empty( $this->field['_error'] ) ) {
                echo '<p class="mt-2 text-sm text-red-600">';
                echo esc_html( $this->field['_error'] );
                echo '</p>';
            }

            echo '</div>';
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
                'desc'        => ! empty( $this->field['desc'] ) ? $this->field['desc'] : '',
                'placeholder' => ! empty( $this->field['placeholder'] ) ? $this->field['placeholder'] : '',
                'rows'        => ! empty( $this->field['rows'] ) ? intval( $this->field['rows'] ) : 4,
                'maxlength'   => ! empty( $this->field['maxlength'] ) ? intval( $this->field['maxlength'] ) : 0,
                'minlength'   => ! empty( $this->field['minlength'] ) ? intval( $this->field['minlength'] ) : 0,
                'show_count'  => ! empty( $this->field['show_count'] ) ? $this->field['show_count'] : false,
                'auto_resize' => ! empty( $this->field['auto_resize'] ) ? $this->field['auto_resize'] : false,
                'resizable'   => ! empty( $this->field['resizable'] ) ? $this->field['resizable'] : true,
                'syntax'      => ! empty( $this->field['syntax'] ) ? $this->field['syntax'] : '',
            );

            return wp_parse_args( $this->field, $default_settings );
        }

        /**
         * 构建textarea的CSS类
         *
         * @since 1.1.0
         *
         * @param array $settings 字段设置
         *
         * @return string CSS类字符串
         */
        private function build_textarea_classes( $settings ) {

            $classes = array(
                'pili-textarea-input',
                'block',
                'w-full',
                'rounded-md',
                'bg-white',
                'px-3',
                'py-2',
                'text-base',
                'text-gray-900',
                'border',
                'border-gray-300',
                'placeholder:text-gray-400',
                'focus:border-blue-600',
                'focus:outline-none',
                'sm:text-sm',
                'transition-colors',
                'duration-200',
            );

            if ( $settings['show_count'] ) {
                $classes[] = 'pr-16';
                $classes[] = 'pb-8';
            }

            if ( ! empty( $this->field['_error'] ) ) {
                $classes = array_merge( $classes, array(
                    'border-red-300',
                    'focus:border-red-600',
                ) );
                $classes = array_diff( $classes, array( 'border-gray-300', 'focus:border-blue-600' ) );
            }

            if ( $settings['auto_resize'] ) {
                $classes[] = 'resize-none';
            } elseif ( ! $settings['resizable'] ) {
                $classes[] = 'resize-none';
            } else {
                $classes[] = 'resize-y';
                $classes[] = 'min-h-20';
            }

            if ( ! empty( $settings['syntax'] ) ) {
                $classes[] = 'font-mono';
                $classes[] = 'pili-syntax-' . esc_attr( $settings['syntax'] );
            }

            return implode( ' ', $classes );
        }

        /**
         * 加载字段资源
         *
         * 加载textarea字段需要的CSS和JavaScript文件。
         *
         * @since 1.1.0
         */
        public function enqueue() {
            $handle = pili_asset_handle( 'field-textarea' );

            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/textarea.js',
                array( 'jquery' ),
                PILI_Setup::$version,
                true
            );

            pili_localize_bag( $handle, 'textarea', array(
                'maxLengthExceeded' => pili__( '超出最大长度' ),
                'minLengthRequired' => pili__( '未达到最小长度' ),
                'charactersRemaining' => pili__( '还可输入 %d 字' ),
            ) );
        }

        /**
         * 验证字段值
         *
         * 验证textarea字段的输入值，支持字符长度限制。
         *
         * @since 1.1.0
         *
         * @param mixed $value 要验证的值
         * @return mixed 验证后的值
         */
        public function validate( $value ) {
            $value = parent::validate( $value );

            $settings = $this->get_field_settings();

            if ( $settings['maxlength'] > 0 ) {
                $current_length = mb_strlen( $value );
                if ( $current_length > $settings['maxlength'] ) {
                    $this->field['_error'] = sprintf( pili__( '内容长度不能超过 %1$d 个字符，当前 %2$d 个字符' ), $settings['maxlength'], $current_length );
                }
            }

            if ( $settings['minlength'] > 0 ) {
                $current_length = mb_strlen( $value );
                if ( $current_length < $settings['minlength'] ) {
                    $this->field['_error'] = sprintf( pili__( '内容长度不能少于 %1$d 个字符，当前 %2$d 个字符' ), $settings['minlength'], $current_length );
                }
            }

            return $value;
        }

        /**
         * 清理字段值
         *
         * 清理和格式化textarea字段的值。
         *
         * @since 1.0
         *
         * @param mixed $value 要清理的值
         * @return mixed 清理后的值
         */
        public function sanitize( $value ) {
            $value = parent::sanitize( $value );

            $value = sanitize_textarea_field( $value );

            return $value;
        }

        /**
         * 检查字段是否为必填
         *
         * @since 1.0
         * @return bool 是否为必填字段
         */
        public function is_required() {
            return ! empty( $this->field['required'] ) && $this->field['required'] === true;
        }
    }
}
