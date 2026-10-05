<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 字段基类
 * 
 * 这个抽象类为所有字段类型提供基础功能和通用方法。
 * 它定义了字段渲染、属性处理和数据管理的标准接口。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Fields' ) ) {
    
    /**
     * PILI_Fields 字段基类
     * 
     * 为所有字段类型提供基础功能，包括：
     * - 字段名称生成
     * - 属性处理
     * - 前后内容渲染
     * - 值处理
     * 
     * @since 1.0
     */
    abstract class PILI_Fields extends PILI_Abstract {
        
        /**
         * 字段配置数组
         * 
         * @since 1.0
         * @var array
         */
        public $field = array();
        
        /**
         * 字段值
         * 
         * @since 1.0
         * @var mixed
         */
        public $value = '';
        
        /**
         * 唯一标识符
         * 
         * @since 1.0
         * @var string
         */
        public $unique = '';
        
        /**
         * 字段位置标识
         * 
         * @since 1.0
         * @var string
         */
        public $where = '';
        
        /**
         * 父级字段标识
         * 
         * @since 1.0
         * @var string
         */
        public $parent = '';
        
        /**
         * 构造函数
         * 
         * 初始化字段实例，设置基本属性。
         * 
         * @since 1.0
         * 
         * @param array  $field  字段配置数组
         * @param mixed  $value  字段值
         * @param string $unique 唯一标识符
         * @param string $where  字段位置
         * @param string $parent 父级字段
         */
        public function __construct( $field = array(), $value = '', $unique = '', $where = '', $parent = '' ) {
            $this->field  = $field;
            $this->value  = $value;
            $this->unique = $unique;
            $this->where  = $where;
            $this->parent = $parent;
        }
        
        /**
         * 生成字段名称
         * 
         * 根据字段配置生成正确的HTML name属性值。
         * 
         * @since 1.0
         * 
         * @param string $nested_name 嵌套名称
         * 
         * @return string 字段名称
         */
        public function field_name( $nested_name = '' ) {
            
            $field_id   = ( ! empty( $this->field['id'] ) ) ? $this->field['id'] : '';
            $unique_id  = ( ! empty( $this->unique ) ) ? $this->unique . '[' . $field_id . ']' : $field_id;
            $field_name = ( ! empty( $this->field['name'] ) ) ? $this->field['name'] : $unique_id;
            $tag_prefix = ( ! empty( $this->field['tag_prefix'] ) ) ? $this->field['tag_prefix'] : '';
            
            if ( ! empty( $tag_prefix ) ) {
                $nested_name = str_replace( '[', '[' . $tag_prefix, $nested_name );
            }
            
            return $field_name . $nested_name;
        }
        
        /**
         * 生成字段属性
         * 
         * 根据字段配置生成HTML属性字符串。
         * 
         * @since 1.0
         * 
         * @param array $custom_atts 自定义属性数组
         * 
         * @return string 属性字符串
         */
        public function field_attributes( $custom_atts = array() ) {
            
            $field_id   = ( ! empty( $this->field['id'] ) ) ? $this->field['id'] : '';
            $attributes = ( ! empty( $this->field['attributes'] ) ) ? $this->field['attributes'] : array();
            if ( ! empty( $field_id ) && empty( $attributes['data-depend-id'] ) ) {
                $attributes['data-depend-id'] = $field_id;
            }
            if ( ! empty( $this->field['placeholder'] ) ) {
                $attributes['placeholder'] = $this->field['placeholder'];
            }
            if ( ! empty( $this->field['class'] ) ) {
                $attributes['class'] = $this->field['class'];
            }
            $attributes = wp_parse_args( $attributes, $custom_atts );
            
            $atts = '';
            
            if ( ! empty( $attributes ) ) {
                foreach ( $attributes as $key => $value ) {
                    if ( $value === 'only-key' ) {
                        $atts .= ' ' . esc_attr( $key );
                    } else {
                        $atts .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
                    }
                }
            }
            
            return $atts;
        }
        
        /**
         * 渲染字段前置内容
         * 
         * 渲染字段前面的说明文字或其他内容。
         * 
         * @since 1.0
         * 
         * @return string 前置内容HTML
         */
        public function field_before() {
            return ( ! empty( $this->field['before'] ) ) ? '<div class="pili-before-text">' . $this->field['before'] . '</div>' : '';
        }
        
        /**
         * 渲染字段后置内容
         * 
         * 渲染字段后面的描述、帮助信息和错误信息。
         * 
         * @since 1.0
         * 
         * @return string 后置内容HTML
         */
        public function field_after() {
            
            $output = '';
            
            if ( ! empty( $this->field['after'] ) ) {
                $output .= '<div class="pili-after-text">' . $this->field['after'] . '</div>';
            }
            
            if ( ! empty( $this->field['help'] ) ) {
                $output .= '<div class="pili-help">';
                $output .= '<span class="pili-help-text">' . $this->field['help'] . '</span>';
                $output .= '<i class="pili-help-icon">?</i>';
                $output .= '</div>';
            }
            
            if ( ! empty( $this->field['_error'] ) ) {
                $output .= '<div class="pili-error-text">' . $this->field['_error'] . '</div>';
            }
            
            return $output;
        }
        
        /**
         * 获取字段ID
         * 
         * 生成字段的HTML ID属性值。
         * 
         * @since 1.0
         * 
         * @return string 字段ID
         */
        public function field_id() {
            
            $field_id = ( ! empty( $this->field['id'] ) ) ? $this->field['id'] : '';
            
            if ( ! empty( $this->unique ) ) {
                $field_id = $this->unique . '_' . $field_id;
            }
            
            return $field_id;
        }
        
        /**
         * 检查字段是否必填
         * 
         * @since 1.0
         * 
         * @return bool 是否必填
         */
        public function is_required() {
            return ! empty( $this->field['required'] );
        }
        
        /**
         * 获取字段标题
         * 
         * @since 1.0
         * 
         * @return string 字段标题
         */
        public function get_title() {
            return ! empty( $this->field['title'] ) ? $this->field['title'] : '';
        }
        
        /**
         * 渲染字段
         * 
         * 这是一个抽象方法，每个字段类型都必须实现此方法。
         * 
         * @since 1.0
         */
        abstract public function render();
        
        /**
         * 加载字段资源
         * 
         * 加载字段所需的CSS和JavaScript文件。
         * 子类可以重写此方法来加载特定的资源。
         * 
         * @since 1.0
         */
        public function enqueue() {
            // 默认不加载任何资源
            // 子类可以重写此方法
        }
        
        /**
         * 验证字段值
         * 
         * 验证和清理字段值。
         * 子类可以重写此方法来实现特定的验证逻辑。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return mixed 验证后的值
         */
        public function validate( $value ) {
            return $this->validate_field( $value, $this->field );
        }
    }
}
