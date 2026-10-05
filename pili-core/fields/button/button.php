<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
  * Field: button
  *
  * 一个现代化、响应式的按钮选择字段
  * 支持单选和多选模式，具有优雅的动画效果和完整的无障碍支持
  *
  * @package PILI Framework
  * @author  June
  * @link    https://www.xuntheme.com
  * @since   1.0
  * @version 1.0
  */
	class PILI_Field_button extends PILI_Fields {

        public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
            parent::__construct( $field, $value, $unique, $where, $parent );
        }

        public function render() {
            $args = wp_parse_args( $this->field, array(
                'multiple'      => false,           // 是否支持多选
                'options'       => array(),         // 选项数组
                'size'          => 'medium',        // 按钮大小: small, medium, large
                'style'         => 'default',       // 按钮样式: default, outline, ghost, gradient, glass, neon, retro, flat, raised, pill, neumorphism, brutalist, minimal, floating, card
                'color'         => 'blue',          // 主题颜色: blue, green, purple, red, gray
                'columns'       => 'auto',          // 列数: auto, 1, 2, 3, 4, 5, 6
                'full_width'    => false,           // 是否全宽显示
                'icons'         => array(),         // 图标配置
                'descriptions'  => array(),         // 描述文字
                'disabled'      => array(),         // 禁用的选项
                'animation'     => true,            // 是否启用动画
                'search'        => false,           // 是否显示搜索框
                'empty_message' => pili__( '暂无内容' ),
                'search_placeholder' => pili__( '搜索选项…' ),
            ) );

            $value = is_array( $this->value ) ? $this->value : array_filter( (array) $this->value );

            echo $this->field_before();

            if ( empty( $args['options'] ) ) {
                echo '<div class="flex items-center justify-center p-8 text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">';
                echo '<div class="text-center">';
                echo '<svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-2M4 13h2m0 0V9a2 2 0 012-2h2m0 0V6a2 2 0 012-2h2a2 2 0 012 2v1M9 7h6"></path>';
                echo '</svg>';
                echo '<p class="text-sm font-medium">' . esc_html( $args['empty_message'] ) . '</p>';
                echo '</div>';
                echo '</div>';
                echo $this->field_after();
                return;
            }

            echo '<div class="pili-button-field" data-field-id="' . esc_attr( $this->field['id'] ) . '">';

            if ( $args['search'] && count( $args['options'] ) > 5 ) {
                echo '<div class="mb-6">';
                echo '<div class="relative max-w-lg mx-auto">';
                echo '<div class="relative">';
                echo '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
                echo '<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>';
                echo '</svg>';
                echo '</div>';
                echo '<input type="text" class="pili-button-search block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm transition duration-150 ease-in-out" placeholder="' . esc_attr( $args['search_placeholder'] ) . '" />';
                echo '<div class="absolute inset-y-0 right-0 pr-3 flex items-center">';
                echo '<kbd class="inline-flex items-center border border-gray-200 rounded px-2 py-1 text-xs font-sans font-medium text-gray-400 bg-gray-50">⌘K</kbd>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }

            $container_classes = $this->get_container_classes( $args );
            echo '<div class="' . esc_attr( $container_classes ) . '" data-multiple="' . esc_attr( $args['multiple'] ? 'true' : 'false' ) . '" data-color="' . esc_attr( $args['color'] ) . '">';

            foreach ( $args['options'] as $key => $label ) {
                $this->render_button_option( $key, $label, $value, $args );
            }

            echo '</div>';

            echo '</div>';

            echo $this->field_after();
        }

        /**
         * 获取容器CSS类
         */
        private function get_container_classes( $args ) {
            $classes = array( 'pili-button-group' );

            if ( $args['columns'] === 'auto' ) {
                if ( $args['size'] === 'small' ) {
                    $classes[] = 'flex flex-wrap gap-1.5 sm:gap-2';
                } else {
                    $classes[] = 'flex flex-wrap gap-2 sm:gap-3';
                }
            } else {
                $cols = intval( $args['columns'] );
                $gap = $args['size'] === 'small' ? 'gap-1.5 sm:gap-2' : 'gap-2 sm:gap-3';
                if ( $cols <= 2 ) {
                    $classes[] = "grid grid-cols-{$cols} {$gap}";
                } else {
                    $classes[] = "grid grid-cols-2 sm:grid-cols-{$cols} {$gap}";
                }
            }

            return implode( ' ', $classes );
        }

        /**
         * 渲染单个按钮选项
         */
        private function render_button_option( $key, $label, $value, $args ) {
            $input_type = $args['multiple'] ? 'checkbox' : 'radio';
            $input_name = $args['multiple'] ? $this->field_name( '[]' ) : $this->field_name();
            $is_checked = in_array( $key, $value ) || ( empty( $value ) && empty( $key ) );
            $is_disabled = in_array( $key, $args['disabled'] );

            $button_style = $args['style'];

            if ( isset( $args['id'] ) && $args['id'] === 'style_button' ) {
                $button_style = $key;
            }

            $button_classes = $this->get_button_classes( $args, $is_checked, $is_disabled, $key, $button_style );

            $icon = isset( $args['icons'][$key] ) ? $args['icons'][$key] : '';

            $description = isset( $args['descriptions'][$key] ) ? $args['descriptions'][$key] : '';

            echo '<label class="pili-button-option cursor-pointer" data-value="' . esc_attr( $key ) . '">';

            echo '<input type="' . esc_attr( $input_type ) . '" ';
            echo 'name="' . esc_attr( $input_name ) . '" ';
            echo 'value="' . esc_attr( $key ) . '" ';
            echo 'class="sr-only pili-button-input" ';
            echo $is_checked ? 'checked ' : '';
            echo $is_disabled ? 'disabled ' : '';
            echo $this->field_attributes();
            echo '/>';

            if ( $description ) {
                echo '<button type="button" class="' . esc_attr( $button_classes ) . ' text-left">';
                echo '<div class="flex items-center gap-3">';
                if ( $icon ) {
                    echo '<div class="flex-shrink-0">';
                    echo $icon;
                    echo '</div>';
                }
                echo '<div class="flex-1 min-w-0">';
                echo '<div class="font-medium text-sm leading-tight">' . esc_html( $label ) . '</div>';
                echo '<div class="text-xs opacity-75 mt-1 leading-relaxed">' . esc_html( $description ) . '</div>';
                echo '</div>';
                echo '</div>';
                echo '</button>';
            } else {
                echo '<button type="button" class="' . esc_attr( $button_classes ) . '">';
                echo '<span class="flex items-center gap-2">';
                if ( $icon ) {
                    echo '<span class="flex-shrink-0">';
                    echo $icon;
                    echo '</span>';
                }
                echo '<span>' . esc_html( $label ) . '</span>';
                echo '</span>';
                echo '</button>';
            }

            echo '</label>';
        }

        /**
         * 获取按钮CSS类
         */
        private function get_button_classes( $args, $is_checked, $is_disabled, $key = '', $override_style = null ) {
            $classes = array(
                'inline-flex items-center justify-center font-medium transition-all duration-200'
            );

            $current_style = $override_style ?? $args['style'];
            $base_styles = $this->get_base_style_classes( $current_style );
            $classes = array_merge( $classes, $base_styles );

            $has_description = isset( $args['descriptions'][$key] ) && ! empty( $args['descriptions'][$key] );

            switch ( $args['size'] ) {
                case 'small':
                    if ( $has_description ) {
                        $classes[] = 'px-3 py-2 text-sm';
                    } else {
                        $classes[] = $args['style'] === 'pill' ? 'px-4 py-1.5 text-sm' : 'px-3 py-1.5 text-sm';
                    }
                    break;
                case 'large':
                    if ( $has_description ) {
                        $classes[] = 'px-6 py-4 text-base';
                    } else {
                        $classes[] = $args['style'] === 'pill' ? 'px-8 py-3 text-base' : 'px-6 py-3 text-base';
                    }
                    break;
                default:
                    if ( $has_description ) {
                        $classes[] = 'px-4 py-3 text-sm';
                    } else {
                        $classes[] = $args['style'] === 'pill' ? 'px-6 py-2 text-sm' : 'px-4 py-2 text-sm';
                    }
            }

            $color_classes = $this->get_color_classes( $args['color'], $current_style, $is_checked, $is_disabled );
            $classes = array_merge( $classes, $color_classes );

            if ( $is_disabled ) {
                $classes[] = 'opacity-50 cursor-not-allowed';
            } else {
                $classes[] = 'cursor-pointer';
            }

            return implode( ' ', $classes );
        }

        /**
         * 获取基础样式类
         */
        private function get_base_style_classes( $style ) {
            $base_classes = array();

            switch ( $style ) {
                case 'pill':
                    $base_classes[] = 'rounded-full';
                    break;
                case 'glass':
                    $base_classes[] = 'rounded-lg backdrop-blur-sm bg-opacity-20 border border-white border-opacity-20';
                    break;
                case 'neon':
                    $base_classes[] = 'rounded-md border-2 shadow-lg shadow-current/25';
                    break;
                case 'retro':
                    $base_classes[] = 'rounded-none border-4 shadow-[4px_4px_0px_0px_rgba(0,0,0,0.3)] transform hover:translate-x-1 hover:translate-y-1 active:translate-x-2 active:translate-y-2';
                    break;
                case 'flat':
                    $base_classes[] = 'rounded-sm border-0 shadow-none';
                    break;
                case 'raised':
                    $base_classes[] = 'rounded-md shadow-lg transform transition-transform duration-150 hover:scale-105 hover:-translate-y-1 active:scale-95 active:translate-y-0';
                    break;
                case 'gradient':
                    $base_classes[] = 'rounded-md bg-gradient-to-r shadow-md transform transition-transform duration-150 hover:scale-105 active:scale-95';
                    break;
                case 'neumorphism':
                    $base_classes[] = 'rounded-xl shadow-inner transform transition-all duration-200 hover:shadow-lg active:shadow-inner';
                    break;
                case 'brutalist':
                    $base_classes[] = 'rounded-none border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transform transition-all duration-100 hover:translate-x-1 hover:translate-y-1 hover:shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] active:translate-x-2 active:translate-y-2 active:shadow-none';
                    break;
                case 'minimal':
                    $base_classes[] = 'rounded border-0 bg-transparent transition-colors duration-200';
                    break;
                case 'floating':
                    $base_classes[] = 'rounded-lg shadow-xl transform transition-all duration-200 hover:scale-110 hover:shadow-2xl active:scale-95';
                    break;
                case 'card':
                    $base_classes[] = 'rounded-lg border shadow-sm bg-white transform transition-all duration-200 hover:shadow-lg hover:-translate-y-1 active:translate-y-0';
                    break;
                case 'ghost':
                    $base_classes[] = 'rounded-md border-0 bg-transparent';
                    break;
                case 'outline':
                    $base_classes[] = 'rounded-md border-2';
                    break;
                default:
                    $base_classes[] = 'rounded-md border shadow-sm';
            }

            return $base_classes;
        }

        /**
         * 获取颜色相关CSS类
         */
        private function get_color_classes( $color, $style, $is_checked, $is_disabled ) {
            $classes = array();

            if ( $is_disabled ) {
                $classes[] = 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-50';
                return $classes;
            }

            switch ( $style ) {
                case 'gradient':
                    $classes = array_merge( $classes, $this->get_gradient_classes( $color, $is_checked ) );
                    break;
                case 'glass':
                    $classes = array_merge( $classes, $this->get_glass_classes( $color, $is_checked ) );
                    break;
                case 'neon':
                    $classes = array_merge( $classes, $this->get_neon_classes( $color, $is_checked ) );
                    break;
                case 'retro':
                    $classes = array_merge( $classes, $this->get_retro_classes( $color, $is_checked ) );
                    break;
                case 'flat':
                    $classes = array_merge( $classes, $this->get_flat_classes( $color, $is_checked ) );
                    break;
                case 'raised':
                    $classes = array_merge( $classes, $this->get_raised_classes( $color, $is_checked ) );
                    break;
                case 'outline':
                    $classes = array_merge( $classes, $this->get_outline_classes( $color, $is_checked ) );
                    break;
                case 'ghost':
                    $classes = array_merge( $classes, $this->get_ghost_classes( $color, $is_checked ) );
                    break;
                case 'pill':
                    $classes = array_merge( $classes, $this->get_pill_classes( $color, $is_checked ) );
                    break;
                case 'neumorphism':
                    $classes = array_merge( $classes, $this->get_neumorphism_classes( $color, $is_checked ) );
                    break;
                case 'brutalist':
                    $classes = array_merge( $classes, $this->get_brutalist_classes( $color, $is_checked ) );
                    break;
                case 'minimal':
                    $classes = array_merge( $classes, $this->get_minimal_classes( $color, $is_checked ) );
                    break;
                case 'floating':
                    $classes = array_merge( $classes, $this->get_floating_classes( $color, $is_checked ) );
                    break;
                case 'card':
                    $classes = array_merge( $classes, $this->get_card_classes( $color, $is_checked ) );
                    break;
                default:
                    $classes = array_merge( $classes, $this->get_default_classes( $color, $is_checked ) );
                    break;
            }

            return $classes;
        }

        /**
         * 默认样式颜色类
         */
        private function get_default_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-600 text-white border-blue-600 shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                'green'  => $is_checked ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                'purple' => $is_checked ? 'bg-purple-600 text-white border-purple-600 shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                'red'    => $is_checked ? 'bg-red-600 text-white border-red-600 shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                'gray'   => $is_checked ? 'bg-gray-600 text-white border-gray-600 shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 轮廓样式颜色类
         */
        private function get_outline_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-600 text-white border-blue-600 shadow-lg' : 'bg-transparent text-blue-600 border-blue-600 hover:bg-blue-50',
                'green'  => $is_checked ? 'bg-green-600 text-white border-green-600 shadow-lg' : 'bg-transparent text-green-600 border-green-600 hover:bg-green-50',
                'purple' => $is_checked ? 'bg-purple-600 text-white border-purple-600 shadow-lg' : 'bg-transparent text-purple-600 border-purple-600 hover:bg-purple-50',
                'red'    => $is_checked ? 'bg-red-600 text-white border-red-600 shadow-lg' : 'bg-transparent text-red-600 border-red-600 hover:bg-red-50',
                'gray'   => $is_checked ? 'bg-gray-600 text-white border-gray-600 shadow-lg' : 'bg-transparent text-gray-600 border-gray-600 hover:bg-gray-50',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 幽灵样式颜色类
         */
        private function get_ghost_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-200 text-blue-900 shadow-inner' : 'bg-transparent text-blue-500 hover:bg-blue-100 hover:text-blue-700',
                'green'  => $is_checked ? 'bg-green-200 text-green-900 shadow-inner' : 'bg-transparent text-green-500 hover:bg-green-100 hover:text-green-700',
                'purple' => $is_checked ? 'bg-purple-200 text-purple-900 shadow-inner' : 'bg-transparent text-purple-500 hover:bg-purple-100 hover:text-purple-700',
                'red'    => $is_checked ? 'bg-red-200 text-red-900 shadow-inner' : 'bg-transparent text-red-500 hover:bg-red-100 hover:text-red-700',
                'gray'   => $is_checked ? 'bg-gray-200 text-gray-900 shadow-inner' : 'bg-transparent text-gray-500 hover:bg-gray-100 hover:text-gray-700',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 药丸样式颜色类
         */
        private function get_pill_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-600 text-white shadow-lg' : 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                'green'  => $is_checked ? 'bg-green-600 text-white shadow-lg' : 'bg-green-100 text-green-700 hover:bg-green-200',
                'purple' => $is_checked ? 'bg-purple-600 text-white shadow-lg' : 'bg-purple-100 text-purple-700 hover:bg-purple-200',
                'red'    => $is_checked ? 'bg-red-600 text-white shadow-lg' : 'bg-red-100 text-red-700 hover:bg-red-200',
                'gray'   => $is_checked ? 'bg-gray-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 渐变样式颜色类
         */
        private function get_gradient_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'from-blue-500 via-blue-600 to-blue-700 text-white shadow-xl' : 'from-blue-300 via-blue-400 to-blue-500 text-white hover:from-blue-400 hover:via-blue-500 hover:to-blue-600',
                'green'  => $is_checked ? 'from-green-500 via-green-600 to-green-700 text-white shadow-xl' : 'from-green-300 via-green-400 to-green-500 text-white hover:from-green-400 hover:via-green-500 hover:to-green-600',
                'purple' => $is_checked ? 'from-purple-500 via-purple-600 to-purple-700 text-white shadow-xl' : 'from-purple-300 via-purple-400 to-purple-500 text-white hover:from-purple-400 hover:via-purple-500 hover:to-purple-600',
                'red'    => $is_checked ? 'from-red-500 via-red-600 to-red-700 text-white shadow-xl' : 'from-red-300 via-red-400 to-red-500 text-white hover:from-red-400 hover:via-red-500 hover:to-red-600',
                'gray'   => $is_checked ? 'from-gray-500 via-gray-600 to-gray-700 text-white shadow-xl' : 'from-gray-300 via-gray-400 to-gray-500 text-white hover:from-gray-400 hover:via-gray-500 hover:to-gray-600',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 玻璃样式颜色类
         */
        private function get_glass_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-400/80 text-white shadow-2xl backdrop-blur-md border-white/20' : 'bg-blue-100/60 text-blue-900 hover:bg-blue-200/70 backdrop-blur-sm border-blue-200/30',
                'green'  => $is_checked ? 'bg-green-400/80 text-white shadow-2xl backdrop-blur-md border-white/20' : 'bg-green-100/60 text-green-900 hover:bg-green-200/70 backdrop-blur-sm border-green-200/30',
                'purple' => $is_checked ? 'bg-purple-400/80 text-white shadow-2xl backdrop-blur-md border-white/20' : 'bg-purple-100/60 text-purple-900 hover:bg-purple-200/70 backdrop-blur-sm border-purple-200/30',
                'red'    => $is_checked ? 'bg-red-400/80 text-white shadow-2xl backdrop-blur-md border-white/20' : 'bg-red-100/60 text-red-900 hover:bg-red-200/70 backdrop-blur-sm border-red-200/30',
                'gray'   => $is_checked ? 'bg-gray-400/80 text-white shadow-2xl backdrop-blur-md border-white/20' : 'bg-gray-100/60 text-gray-900 hover:bg-gray-200/70 backdrop-blur-sm border-gray-200/30',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 霓虹样式颜色类
         */
        private function get_neon_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-900 text-blue-300 border-blue-400 shadow-lg shadow-blue-500/75 glow-blue' : 'bg-black text-blue-400 border-blue-400 hover:shadow-md hover:shadow-blue-500/50 hover:text-blue-300',
                'green'  => $is_checked ? 'bg-green-900 text-green-300 border-green-400 shadow-lg shadow-green-500/75 glow-green' : 'bg-black text-green-400 border-green-400 hover:shadow-md hover:shadow-green-500/50 hover:text-green-300',
                'purple' => $is_checked ? 'bg-purple-900 text-purple-300 border-purple-400 shadow-lg shadow-purple-500/75 glow-purple' : 'bg-black text-purple-400 border-purple-400 hover:shadow-md hover:shadow-purple-500/50 hover:text-purple-300',
                'red'    => $is_checked ? 'bg-red-900 text-red-300 border-red-400 shadow-lg shadow-red-500/75 glow-red' : 'bg-black text-red-400 border-red-400 hover:shadow-md hover:shadow-red-500/50 hover:text-red-300',
                'gray'   => $is_checked ? 'bg-gray-900 text-gray-300 border-gray-400 shadow-lg shadow-gray-500/75 glow-gray' : 'bg-black text-gray-400 border-gray-400 hover:shadow-md hover:shadow-gray-500/50 hover:text-gray-300',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 复古样式颜色类
         */
        private function get_retro_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-800 text-yellow-200 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-blue-300 text-black border-black hover:bg-blue-400 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'green'  => $is_checked ? 'bg-green-800 text-yellow-200 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-green-300 text-black border-black hover:bg-green-400 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'purple' => $is_checked ? 'bg-purple-800 text-yellow-200 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-purple-300 text-black border-black hover:bg-purple-400 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'red'    => $is_checked ? 'bg-red-800 text-yellow-200 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-red-300 text-black border-black hover:bg-red-400 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'gray'   => $is_checked ? 'bg-gray-800 text-yellow-200 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-gray-300 text-black border-black hover:bg-gray-400 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 扁平样式颜色类
         */
        private function get_flat_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-600 text-white' : 'bg-blue-200 text-blue-800 hover:bg-blue-300',
                'green'  => $is_checked ? 'bg-green-600 text-white' : 'bg-green-200 text-green-800 hover:bg-green-300',
                'purple' => $is_checked ? 'bg-purple-600 text-white' : 'bg-purple-200 text-purple-800 hover:bg-purple-300',
                'red'    => $is_checked ? 'bg-red-600 text-white' : 'bg-red-200 text-red-800 hover:bg-red-300',
                'gray'   => $is_checked ? 'bg-gray-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 凸起样式颜色类
         */
        private function get_raised_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-600 text-white shadow-blue-500/25' : 'bg-blue-500 text-white hover:bg-blue-600 shadow-blue-500/25',
                'green'  => $is_checked ? 'bg-green-600 text-white shadow-green-500/25' : 'bg-green-500 text-white hover:bg-green-600 shadow-green-500/25',
                'purple' => $is_checked ? 'bg-purple-600 text-white shadow-purple-500/25' : 'bg-purple-500 text-white hover:bg-purple-600 shadow-purple-500/25',
                'red'    => $is_checked ? 'bg-red-600 text-white shadow-red-500/25' : 'bg-red-500 text-white hover:bg-red-600 shadow-red-500/25',
                'gray'   => $is_checked ? 'bg-gray-600 text-white shadow-gray-500/25' : 'bg-gray-500 text-white hover:bg-gray-600 shadow-gray-500/25',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 新拟物样式颜色类
         */
        private function get_neumorphism_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-200 text-blue-800 shadow-[inset_8px_8px_16px_#bfdbfe,inset_-8px_-8px_16px_#ffffff]' : 'bg-blue-100 text-blue-700 shadow-[8px_8px_16px_#bfdbfe,-8px_-8px_16px_#ffffff]',
                'green'  => $is_checked ? 'bg-green-200 text-green-800 shadow-[inset_8px_8px_16px_#bbf7d0,inset_-8px_-8px_16px_#ffffff]' : 'bg-green-100 text-green-700 shadow-[8px_8px_16px_#bbf7d0,-8px_-8px_16px_#ffffff]',
                'purple' => $is_checked ? 'bg-purple-200 text-purple-800 shadow-[inset_8px_8px_16px_#ddd6fe,inset_-8px_-8px_16px_#ffffff]' : 'bg-purple-100 text-purple-700 shadow-[8px_8px_16px_#ddd6fe,-8px_-8px_16px_#ffffff]',
                'red'    => $is_checked ? 'bg-red-200 text-red-800 shadow-[inset_8px_8px_16px_#fecaca,inset_-8px_-8px_16px_#ffffff]' : 'bg-red-100 text-red-700 shadow-[8px_8px_16px_#fecaca,-8px_-8px_16px_#ffffff]',
                'gray'   => $is_checked ? 'bg-gray-200 text-gray-800 shadow-[inset_8px_8px_16px_#e5e7eb,inset_-8px_-8px_16px_#ffffff]' : 'bg-gray-100 text-gray-700 shadow-[8px_8px_16px_#e5e7eb,-8px_-8px_16px_#ffffff]',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 野兽派样式颜色类
         */
        private function get_brutalist_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-500 text-white border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-blue-200 text-black border-black hover:bg-blue-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'green'  => $is_checked ? 'bg-green-500 text-white border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-green-200 text-black border-black hover:bg-green-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'purple' => $is_checked ? 'bg-purple-500 text-white border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-purple-200 text-black border-black hover:bg-purple-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'red'    => $is_checked ? 'bg-red-500 text-white border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-red-200 text-black border-black hover:bg-red-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                'gray'   => $is_checked ? 'bg-gray-500 text-white border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]' : 'bg-gray-200 text-black border-black hover:bg-gray-300 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 极简样式颜色类
         */
        private function get_minimal_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'text-blue-700 bg-blue-100 underline underline-offset-2' : 'text-blue-600 hover:text-blue-700 hover:underline hover:underline-offset-2',
                'green'  => $is_checked ? 'text-green-700 bg-green-100 underline underline-offset-2' : 'text-green-600 hover:text-green-700 hover:underline hover:underline-offset-2',
                'purple' => $is_checked ? 'text-purple-700 bg-purple-100 underline underline-offset-2' : 'text-purple-600 hover:text-purple-700 hover:underline hover:underline-offset-2',
                'red'    => $is_checked ? 'text-red-700 bg-red-100 underline underline-offset-2' : 'text-red-600 hover:text-red-700 hover:underline hover:underline-offset-2',
                'gray'   => $is_checked ? 'text-gray-700 bg-gray-100 underline underline-offset-2' : 'text-gray-600 hover:text-gray-700 hover:underline hover:underline-offset-2',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 浮动样式颜色类
         */
        private function get_floating_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-600 text-white shadow-blue-500/50' : 'bg-blue-500 text-white hover:bg-blue-600 shadow-blue-500/25',
                'green'  => $is_checked ? 'bg-green-600 text-white shadow-green-500/50' : 'bg-green-500 text-white hover:bg-green-600 shadow-green-500/25',
                'purple' => $is_checked ? 'bg-purple-600 text-white shadow-purple-500/50' : 'bg-purple-500 text-white hover:bg-purple-600 shadow-purple-500/25',
                'red'    => $is_checked ? 'bg-red-600 text-white shadow-red-500/50' : 'bg-red-500 text-white hover:bg-red-600 shadow-red-500/25',
                'gray'   => $is_checked ? 'bg-gray-600 text-white shadow-gray-500/50' : 'bg-gray-500 text-white hover:bg-gray-600 shadow-gray-500/25',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 卡片样式颜色类
         */
        private function get_card_classes( $color, $is_checked ) {
            $color_map = array(
                'blue'   => $is_checked ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-white text-blue-600 border-blue-100 hover:bg-blue-50 hover:border-blue-200',
                'green'  => $is_checked ? 'bg-green-50 text-green-700 border-green-200' : 'bg-white text-green-600 border-green-100 hover:bg-green-50 hover:border-green-200',
                'purple' => $is_checked ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-white text-purple-600 border-purple-100 hover:bg-purple-50 hover:border-purple-200',
                'red'    => $is_checked ? 'bg-red-50 text-red-700 border-red-200' : 'bg-white text-red-600 border-red-100 hover:bg-red-50 hover:border-red-200',
                'gray'   => $is_checked ? 'bg-gray-50 text-gray-700 border-gray-200' : 'bg-white text-gray-600 border-gray-100 hover:bg-gray-50 hover:border-gray-200',
            );
            return explode( ' ', $color_map[$color] ?? $color_map['blue'] );
        }

        /**
         * 加载字段资源
         *
         * 加载button字段所需的JavaScript文件。
         *
         * @since 1.0
         */
        public function enqueue() {
            $handle = pili_asset_handle( 'field-button' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/button.js',
                array( 'jquery' ),
                PILI_Setup::$version,
                true
            );

            pili_localize_bag( $handle, 'button', array(
                'i18n' => array(
                    'noResults' => pili__( '无结果' ),
                    'tryOtherKeyword' => pili__( '尝试使用其他关键词搜索' ),
                    'showingCount' => pili__( '显示 %1$d / %2$d 个选项' ),
                ),
            ) );
        }
    }
