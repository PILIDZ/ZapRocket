<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 抽象基类
 * 
 * 这个抽象类为框架中的所有其他类提供基础功能和通用方法。
 * 它定义了框架中各个组件共享的核心功能。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Abstract' ) ) {
    /**
     * PILI_Abstract 抽象基类
     * 
     * 为框架中的所有类提供基础功能，包括：
     * - 字段预处理
     * - 区块管理
     * - 默认值处理
     * - 数据验证
     * 
     * @since 1.0
     */
    abstract class PILI_Abstract {
        /**
         * 唯一标识符
         * 
         * @since 1.0
         * @var string
         */
        public $unique = '';
        /**
         * 抽象类型标识
         * 
         * @since 1.0
         * @var string
         */
        public $abstract = '';
        /**
         * 预处理字段数组
         * 
         * 存储经过预处理的字段配置，用于提高性能。
         * 
         * @since 1.0
         * 
         * @param array $sections 区块配置数组
         * 
         * @return array 预处理后的字段数组
         */
        public function pre_fields( $sections ) {
            $result = array();
            if ( ! empty( $sections ) ) {
                foreach ( $sections as $key => $section ) {
                    if ( ! empty( $section['fields'] ) ) {
                        foreach ( $section['fields'] as $field ) {
                            $result[] = $field;
                        }
                    }
                }
            }
            return $result;
        }
        /**
         * 预处理区块数组
         *
         * 对区块配置进行预处理，优化结构和性能。
         * 只包含有字段的区块，跳过没有字段的父菜单。
         *
         * @since 1.0
         *
         * @param array $sections 原始区块配置数组
         *
         * @return array 预处理后的区块数组
         */
        public function pre_sections( $sections ) {
            $result = array();
            if ( ! empty( $sections ) ) {
                foreach ( $sections as $key => $section ) {
                    if ( ! empty( $section['fields'] ) ) {
                        $result[] = $section;
                    }
                }
            }
            return $result;
        }
        /**
         * 获取字段默认值
         * 
         * 从字段配置中提取默认值，支持多层级配置覆盖。
         * 
         * @since 1.0
         * 
         * @param array $field 字段配置数组
         * 
         * @return mixed 字段的默认值
         */
        public function get_default( $field ) {
            $default = '';
            if ( isset( $field['default'] ) ) {
                $default = $field['default'];
            }
            return $default;
        }
        /**
         * 获取选项值
         *
         * 从数据库中获取保存的选项值。
         * 这个方法在选项管理类中实现，字段类不需要实现。
         *
         * @since 1.0
         *
         * @return array 选项值数组
         */
        public function get_options() {
            return array();
        }
        /**
         * 保存选项值
         *
         * 将选项值保存到数据库中。
         * 这个方法在选项管理类中实现，字段类不需要实现。
         *
         * @since 1.0
         *
         * @param array $data 要保存的数据
         *
         * @return bool 保存是否成功
         */
        public function save_options( $data ) {
            return false;
        }
        /**
         * 验证字段值
         * 
         * 对字段值进行验证和清理，确保数据安全性。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * @param array $field 字段配置
         * 
         * @return mixed 验证后的值
         */
        public function validate_field( $value, $field ) {
            switch ( $field['type'] ) {
                case 'text':
                    $value = sanitize_text_field( $value );
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field( $value );
                    break;
                case 'email':
                    $value = sanitize_email( $value );
                    break;
                case 'url':
                    $value = esc_url_raw( $value );
                    break;
                case 'number':
                    if ( $value === '' || $value === null ) {
                        $value = '';
                        break;
                    }
                    if ( is_string( $value ) ) {
                        $value = sanitize_text_field( str_replace( ',', '', $value ) );
                    }
                    if ( ! is_numeric( $value ) ) {
                        $value = 0;
                        break;
                    }
                    $numeric = (float) $value;
                    if ( isset( $field['precision'] ) && is_numeric( $field['precision'] ) && (int) $field['precision'] >= 0 ) {
                        $numeric = round( $numeric, (int) $field['precision'] );
                    }
                    $value = $numeric;
                    break;
                case 'code':
                    if ( is_string( $value ) ) {
                        if ( function_exists( 'get_magic_quotes_gpc' ) && get_magic_quotes_gpc() ) {
                            $value = stripslashes( $value );
                        }
                        if ( strpos( $value, '\\\\' ) !== false ) {
                            $value = stripslashes( $value );
                        }
                        if ( strlen( $value ) > 32768 ) {
                            $value = substr( $value, 0, 32768 );
                        }
                        $language = isset( $field['language'] ) ? $field['language'] : 'html';
                        if ( $language !== 'php' ) {
                            $value = preg_replace( '/<\?(?:php)?/i', '&lt;?', $value );
                            $value = preg_replace( '/\?>/i', '?&gt;', $value );
                        }
                        if ( $language !== 'javascript' && $language !== 'js' ) {
                            $value = preg_replace( '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $value );
                        }
                    }
                    break;
                default:
                    $value = apply_filters( 'pili_validate_field_' . $field['type'], $value, $field );
                    break;
            }
            return $value;
        }
        /**
         * 清理数据
         * 
         * 对整个数据数组进行清理和验证。
         * 
         * @since 1.0
         * 
         * @param array $data   要清理的数据数组
         * @param array $fields 字段配置数组
         * 
         * @return array 清理后的数据数组
         */
        public function sanitize_data( $data, $fields ) {
            $sanitized = array();
            if ( ! empty( $data ) && ! empty( $fields ) ) {
                foreach ( $fields as $field ) {
                    if ( isset( $field['id'] ) && isset( $data[ $field['id'] ] ) ) {
                        $sanitized[ $field['id'] ] = $this->validate_field( $data[ $field['id'] ], $field );
                    }
                }
            }
            return $sanitized;
        }
        /**
         * 应用过滤器钩子
         * 
         * 为特定的唯一标识符应用过滤器钩子。
         * 
         * @since 1.0
         * 
         * @param string $hook_name 钩子名称
         * @param mixed  $value     要过滤的值
         * @param mixed  ...$args   额外的参数
         * 
         * @return mixed 过滤后的值
         */
        public function apply_filters( $hook_name, $value, ...$args ) {
            $filter_name = "xun_{$this->unique}_{$hook_name}";
            return apply_filters( $filter_name, $value, ...$args );
        }
        /**
         * 触发动作钩子
         * 
         * 为特定的唯一标识符触发动作钩子。
         * 
         * @since 1.0
         * 
         * @param string $hook_name 钩子名称
         * @param mixed  ...$args   传递给钩子的参数
         */
        public function do_action( $hook_name, ...$args ) {
            $action_name = "xun_{$this->unique}_{$hook_name}";
            do_action( $action_name, ...$args );
        }
        /**
         * 获取字段值
         * 
         * 从选项数组中获取特定字段的值，如果不存在则返回默认值。
         * 
         * @since 1.0
         * 
         * @param string $field_id 字段ID
         * @param array  $options  选项数组
         * @param mixed  $default  默认值
         * 
         * @return mixed 字段值
         */
        public function get_field_value( $field_id, $options = array(), $default = '' ) {
            if ( isset( $options[ $field_id ] ) ) {
                return $options[ $field_id ];
            }
            return $default;
        }
    }
}
