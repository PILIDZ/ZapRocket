<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework Switch 开关字段类型
 * 
 * 这个类实现了现代化的开关切换字段功能。
 * 支持多种尺寸、颜色主题、动画效果和无障碍访问特性。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_switch' ) ) {
    
    /**
     * PILI_Field_switch 开关字段类
     * 
     * 提供现代化开关切换字段的完整功能，包括：
     * - 流畅的动画效果
     * - 多种尺寸选择（small、medium、large）
     * - 自定义颜色主题
     * - 禁用和加载状态
     * - 完整的键盘导航支持
     * - 无障碍访问优化
     * - 自定义开关文本
     * 
     * @since 1.0
     */
    class PILI_Field_switch extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化开关字段实例。
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
         * 渲染开关字段
         *
         * 输出现代化的开关切换字段，主要使用TailwindCSS内置类。
         *
         * @since 1.0
         */
        public function render() {

            $is_checked = ! empty( $this->value );
            $size = ! empty( $this->field['size'] ) ? $this->field['size'] : 'medium';
            $color = ! empty( $this->field['color'] ) ? $this->field['color'] : 'blue';
            $disabled = ! empty( $this->field['disabled'] );
            $loading = ! empty( $this->field['loading'] );
            $show_icons = ! empty( $this->field['show_icons'] );
            $style = ! empty( $this->field['style'] ) ? $this->field['style'] : 'default';

            $label = ! empty( $this->field['label'] ) ? $this->field['label'] : '';

            $field_id = $this->field_name() . '_switch';

            $switch_classes = $this->build_switch_classes( $size, $color, $is_checked, $disabled, $loading );
            $container_classes = $this->build_container_classes( $disabled );

            echo $this->field_before();

            echo '<div class="' . esc_attr( $container_classes ) . '" data-pili-switch data-style="' . esc_attr( $style ) . '">';

            if ( $style === 'short' ) {
                $this->render_short_toggle( $size, $color, $is_checked, $disabled, $loading, $field_id );
            } else {
                $this->render_default_switch( $size, $color, $is_checked, $disabled, $loading, $show_icons, $field_id );
            }

            echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $this->value ) . '"' . $this->field_attributes() . ' />';

            if ( ! empty( $label ) ) {
                echo '<span id="' . esc_attr( $field_id . '_label' ) . '" class="text-sm font-medium text-gray-700 cursor-pointer select-none">' . esc_html( $label ) . '</span>';
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
         * 渲染默认样式开关
         *
         * @since 1.0
         *
         * @param string $size       尺寸
         * @param string $color      颜色主题
         * @param bool   $is_checked 是否选中
         * @param bool   $disabled   是否禁用
         * @param bool   $loading    是否加载中
         * @param bool   $show_icons 是否显示图标
         * @param string $field_id   字段ID
         */
        private function render_default_switch( $size, $color, $is_checked, $disabled, $loading, $show_icons, $field_id ) {

            $switch_classes = $this->build_switch_classes( $size, $color, $is_checked, $disabled, $loading );

            echo '<div class="' . esc_attr( $switch_classes ) . '" role="switch" aria-checked="' . ( $is_checked ? 'true' : 'false' ) . '"';
            echo ' aria-labelledby="' . esc_attr( $field_id . '_label' ) . '"';
            echo ' tabindex="' . ( $disabled ? '-1' : '0' ) . '"';
            echo ' data-size="' . esc_attr( $size ) . '"';
            echo ' data-color="' . esc_attr( $color ) . '"';
            echo ' data-checked="' . ( $is_checked ? 'true' : 'false' ) . '"';
            echo '>';

            $thumb_classes = $this->build_thumb_classes( $size, $is_checked, $loading );
            echo '<span class="' . esc_attr( $thumb_classes ) . '">';

            if ( $show_icons ) {
                $off_opacity = $is_checked ? 'opacity-0' : 'opacity-100';
                echo '<span aria-hidden="true" class="absolute inset-0 flex size-full items-center justify-center ' . $off_opacity . ' transition-opacity duration-200 ease-in">';
                echo '<svg viewBox="0 0 12 12" fill="none" class="size-3 text-gray-400">';
                echo '<path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />';
                echo '</svg>';
                echo '</span>';

                $on_opacity = $is_checked ? 'opacity-100' : 'opacity-0';
                $on_color = $this->get_icon_color( $color );
                echo '<span aria-hidden="true" class="absolute inset-0 flex size-full items-center justify-center ' . $on_opacity . ' transition-opacity duration-200 ease-in">';
                echo '<svg viewBox="0 0 12 12" fill="currentColor" class="size-3 ' . $on_color . '">';
                echo '<path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />';
                echo '</svg>';
                echo '</span>';
            } else if ( $loading ) {
                echo '<span class="absolute inset-0 flex size-full items-center justify-center">';
                echo '<svg class="size-3 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">';
                echo '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle>';
                echo '<path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" class="opacity-75"></path>';
                echo '</svg>';
                echo '</span>';
            }

            echo '</span>';
            echo '</div>';

            echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $this->value ) . '"' . $this->field_attributes() . ' />';
        }

        /**
         * 渲染Short Toggle样式开关
         *
         * @since 1.0
         *
         * @param string $size       尺寸
         * @param string $color      颜色主题
         * @param bool   $is_checked 是否选中
         * @param bool   $disabled   是否禁用
         * @param bool   $loading    是否加载中
         * @param string $field_id   字段ID
         */
        private function render_short_toggle( $size, $color, $is_checked, $disabled, $loading, $field_id ) {

            $container_classes = $this->build_short_container_classes( $size, $disabled );
            $track_classes = $this->build_short_track_classes( $color, $is_checked );
            $thumb_classes = $this->build_short_thumb_classes( $is_checked );

            echo '<div class="' . esc_attr( $container_classes ) . '" role="switch" aria-checked="' . ( $is_checked ? 'true' : 'false' ) . '"';
            echo ' aria-labelledby="' . esc_attr( $field_id . '_label' ) . '"';
            echo ' tabindex="' . ( $disabled ? '-1' : '0' ) . '"';
            echo ' data-size="' . esc_attr( $size ) . '"';
            echo ' data-color="' . esc_attr( $color ) . '"';
            echo ' data-checked="' . ( $is_checked ? 'true' : 'false' ) . '"';
            echo '>';

            echo '<span class="' . esc_attr( $track_classes ) . '"></span>';

            echo '<span class="' . esc_attr( $thumb_classes ) . '"></span>';

            echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $this->value ) . '"' . $this->field_attributes() . ' />';

            echo '</div>';
        }

        /**
         * 获取图标颜色类
         *
         * @since 1.0
         *
         * @param string $color 颜色主题
         *
         * @return string 图标颜色类
         */
        private function get_icon_color( $color ) {
            $color_classes = array(
                'blue'   => 'text-blue-600',
                'green'  => 'text-green-600',
                'red'    => 'text-red-600',
                'yellow' => 'text-yellow-600',
                'purple' => 'text-purple-600',
                'pink'   => 'text-pink-600',
                'gray'   => 'text-gray-600'
            );

            return isset( $color_classes[ $color ] ) ? $color_classes[ $color ] : 'text-blue-600';
        }

        /**
         * 构建开关CSS类 - 主要使用TailwindCSS内置类
         *
         * @since 1.0
         *
         * @param string $size     尺寸
         * @param string $color    颜色主题
         * @param bool   $checked  是否选中
         * @param bool   $disabled 是否禁用
         * @param bool   $loading  是否加载中
         *
         * @return string CSS类名
         */
        private function build_switch_classes( $size, $color, $checked, $disabled, $loading ) {

            $classes = array(
                'group',
                'relative',
                'inline-flex',
                'shrink-0',
                'rounded-full',
                'p-0.5',
                'inset-ring',
                'inset-ring-gray-900/5',
                'outline-offset-2',
                'transition-colors',
                'duration-200',
                'ease-in-out',
                'cursor-pointer'
            );

            switch ( $size ) {
                case 'small':
                    $classes[] = 'w-9';
                    break;
                case 'large':
                    $classes[] = 'w-14';
                    break;
                default:
                    $classes[] = 'w-11';
                    break;
            }

            if ( $checked ) {
                switch ( $color ) {
                    case 'green':
                        $classes[] = 'bg-green-600 outline-green-600';
                        break;
                    case 'red':
                        $classes[] = 'bg-red-600 outline-red-600';
                        break;
                    case 'yellow':
                        $classes[] = 'bg-yellow-600 outline-yellow-600';
                        break;
                    case 'purple':
                        $classes[] = 'bg-purple-600 outline-purple-600';
                        break;
                    case 'pink':
                        $classes[] = 'bg-pink-600 outline-pink-600';
                        break;
                    case 'gray':
                        $classes[] = 'bg-gray-600 outline-gray-600';
                        break;
                    default:
                        $classes[] = 'bg-blue-600 outline-blue-600';
                        break;
                }
            } else {
                $classes[] = 'bg-gray-200 outline-gray-600';
            }

            if ( $disabled ) {
                $classes[] = 'opacity-50 cursor-not-allowed';
            }

            if ( $loading ) {
                $classes[] = 'opacity-75';
            }

            return implode( ' ', $classes );
        }

        /**
         * 构建滑块CSS类
         *
         * @since 1.0
         *
         * @param string $size     尺寸
         * @param bool   $checked  是否选中
         * @param bool   $loading  是否加载中
         *
         * @return string CSS类名
         */
        private function build_thumb_classes( $size, $checked, $loading ) {

            $classes = array(
                'relative',
                'bg-white',
                'rounded-full',
                'shadow-xs',
                'ring-1',
                'ring-gray-900/5',
                'transition-transform',
                'duration-200',
                'ease-in-out'
            );

            switch ( $size ) {
                case 'small':
                    $classes[] = 'size-4';
                    break;
                case 'large':
                    $classes[] = 'size-6';
                    break;
                default:
                    $classes[] = 'size-5';
                    break;
            }

            if ( $checked ) {
                switch ( $size ) {
                    case 'small':
                        $classes[] = 'translate-x-4';
                        break;
                    case 'large':
                        $classes[] = 'translate-x-7';
                        break;
                    default:
                        $classes[] = 'translate-x-5';
                        break;
                }
            } else {
                $classes[] = 'translate-x-0';
            }

            return implode( ' ', $classes );
        }


        
        /**
         * 构建容器CSS类 - 使用TailwindCSS内置类
         *
         * @since 1.0
         *
         * @param bool $disabled 是否禁用
         *
         * @return string CSS类名
         */
        private function build_container_classes( $disabled ) {

            $classes = array(
                'flex',
                'items-center',
                'gap-3',
                'group'
            );

            if ( $disabled ) {
                $classes[] = 'cursor-not-allowed';
            } else {
                $classes[] = 'cursor-pointer';
            }

            return implode( ' ', $classes );
        }

        /**
         * 构建Short Toggle容器CSS类
         *
         * @since 1.0
         *
         * @param string $size     尺寸
         * @param bool   $disabled 是否禁用
         *
         * @return string CSS类名
         */
        private function build_short_container_classes( $size, $disabled ) {

            $classes = array(
                'group',
                'relative',
                'inline-flex',
                'h-5',
                'w-10',
                'shrink-0',
                'items-center',
                'justify-center',
                'rounded-full',
                'outline-offset-2',
                'outline-blue-600'
            );

            if ( ! $disabled ) {
                $classes[] = 'cursor-pointer';
            } else {
                $classes[] = 'opacity-50';
                $classes[] = 'cursor-not-allowed';
            }

            return implode( ' ', $classes );
        }

        /**
         * 构建Short Toggle轨道CSS类
         *
         * @since 1.0
         *
         * @param string $color   颜色主题
         * @param bool   $checked 是否选中
         *
         * @return string CSS类名
         */
        private function build_short_track_classes( $color, $checked ) {

            $classes = array(
                'absolute',
                'mx-auto',
                'h-4',
                'w-9',
                'rounded-full',
                'inset-ring',
                'inset-ring-gray-900/5',
                'transition-colors',
                'duration-200',
                'ease-in-out'
            );

            if ( $checked ) {
                switch ( $color ) {
                    case 'green':
                        $classes[] = 'bg-green-600';
                        break;
                    case 'red':
                        $classes[] = 'bg-red-600';
                        break;
                    case 'yellow':
                        $classes[] = 'bg-yellow-600';
                        break;
                    case 'purple':
                        $classes[] = 'bg-purple-600';
                        break;
                    case 'pink':
                        $classes[] = 'bg-pink-600';
                        break;
                    case 'gray':
                        $classes[] = 'bg-gray-600';
                        break;
                    default:
                        $classes[] = 'bg-blue-600';
                        break;
                }
            } else {
                $classes[] = 'bg-gray-200';
            }

            return implode( ' ', $classes );
        }

        /**
         * 构建Short Toggle滑块CSS类
         *
         * @since 1.0
         *
         * @param bool $checked 是否选中
         *
         * @return string CSS类名
         */
        private function build_short_thumb_classes( $checked ) {

            $classes = array(
                'absolute',
                'left-0',
                'size-5',
                'rounded-full',
                'border',
                'border-gray-300',
                'bg-white',
                'shadow-xs',
                'transition-transform',
                'duration-200',
                'ease-in-out'
            );

            if ( $checked ) {
                $classes[] = 'translate-x-5';
            }

            return implode( ' ', $classes );
        }

        /**
         * 加载字段资源
         * 
         * 加载开关字段所需的CSS和JavaScript文件。
         * 
         * @since 1.0
         */
        public function enqueue() {
            $handle = pili_asset_handle( 'field-switch' );
            
            wp_enqueue_script( 
                $handle, 
                PILI_Setup::$url . '/assets/js/fields/switch.js', 
                array( 'jquery' ), 
                PILI_Setup::$version, 
                true 
            );
            
            pili_localize_bag( $handle, 'switch', array(
                'i18n' => array(
                    'on' => pili__( '开' ),
                    'off' => pili__( '关' ),
                ),
            ) );
        }
        
        /**
         * 验证字段值
         * 
         * 验证和清理开关字段的值。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return mixed 验证后的值
         */
        public function validate( $value ) {
            
            if ( is_bool( $value ) ) {
                return $value;
            }
            
            if ( is_string( $value ) ) {
                $value = trim( $value );
                
                if ( in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ) ) ) {
                    return true;
                }
                
                if ( in_array( strtolower( $value ), array( '0', 'false', 'off', 'no', '' ) ) ) {
                    return false;
                }
            }
            
            if ( is_numeric( $value ) ) {
                return ! empty( $value );
            }
            
            $value = apply_filters( 'pili_validate_switch_field', $value, $this->field );
            $value = apply_filters( "pili_validate_switch_field_{$this->field['id']}", $value, $this->field );
            
            return ! empty( $value );
        }
    }
}

if ( ! function_exists( __NAMESPACE__ . '\pili_validate_field_switch_value' ) ) {
	/**
	 * admin-options sanitize 走 validate_field default 分支时的 switch 清洗。
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field config.
	 * @return bool
	 */
	function pili_validate_field_switch_value( $value, $field ) {
		unset( $field );
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true ) ) {
				return true;
			}
			if ( in_array( strtolower( $value ), array( '0', 'false', 'off', 'no', '' ), true ) ) {
				return false;
			}
		}
		if ( is_numeric( $value ) ) {
			return ( (int) $value ) !== 0;
		}
		return ! empty( $value );
	}
}

add_filter( 'pili_validate_field_switch', __NAMESPACE__ . '\pili_validate_field_switch_value', 10, 2 );
