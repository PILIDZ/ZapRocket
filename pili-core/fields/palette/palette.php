<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Xun Framework Palette 字段类型
 * 
 * 这个字段类型提供了一个现代化的调色板界面，支持预设颜色选择、
 * 颜色分组、搜索过滤、自定义颜色、多选支持等高级功能。
 * 
 * @package Xun Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_palette' ) ) {
    
    /**
     * PILI_Field_palette 调色板字段类
     * 
     * 功能特性：
     * - 预设颜色调色板选择
     * - 颜色分组和分类显示
     * - 颜色搜索和过滤功能
     * - 自定义颜色添加支持
     * - 单选和多选模式
     * - 颜色对比度检查
     * - 响应式设计
     * - 完整的数据验证
     * 
     * @since 1.0
     */
    class PILI_Field_palette extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化调色板字段实例。
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
         * 渲染调色板字段
         * 
         * 生成现代化的调色板选择界面。
         * 
         * @since 1.0
         */
        public function render() {
            
            echo $this->field_before();
            
            $args = wp_parse_args( $this->field, array(
                'options'         => array(),
                'multiple'        => false,
                'show_labels'     => true,
                'show_search'     => true,
                'show_custom'     => false,
                'allow_empty'     => true,
                'group_colors'    => false,
                'colors_per_row'  => 5,
                'color_size'      => 'medium',
                'show_contrast'   => false,
                'show_preview'    => true,
                'layout'          => 'grid',
                'animation'       => true,
            ) );
            
            $value = $this->value;
            if ( $args['multiple'] ) {
                if ( ! is_array( $value ) ) {
                    $value = ! empty( $value ) ? array( $value ) : array();
                }
            } else {
                if ( is_array( $value ) ) {
                    $value = ! empty( $value ) ? $value[0] : '';
                }
            }
            
            $field_id = 'pili-palette-' . uniqid();
            
            $container_classes = array(
                'pili-palette-field',
                'pili-palette-' . $args['layout'],
                'pili-palette-' . $args['color_size'],
            );
            
            if ( $args['multiple'] ) {
                $container_classes[] = 'pili-palette-multiple';
            }
            
            if ( $args['animation'] ) {
                $container_classes[] = 'pili-palette-animate';
            }
            
            echo '<div class="' . implode( ' ', $container_classes ) . '" data-field-id="' . esc_attr( $this->field['id'] ) . '">';
            
            $palette_data = array(
                'multiple'      => $args['multiple'],
                'allowEmpty'    => $args['allow_empty'],
                'showContrast'  => $args['show_contrast'],
                'colorsPerRow'  => $args['colors_per_row'],
                'layout'        => $args['layout'],
                'animation'     => $args['animation'],
            );
            
            if ( $args['show_search'] || $args['show_custom'] ) {
                echo '<div class="pili-palette-toolbar mb-4 flex flex-wrap items-center gap-3">';
                
                if ( $args['show_search'] ) {
                    echo '<div class="flex-1 min-w-0">';
                    echo '<div class="relative">';
                    echo '<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">';
                    echo '<svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />';
                    echo '</svg>';
                    echo '</div>';
                    echo '<input type="text" class="pili-palette-search block w-full pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" style="padding-left:2.5rem" placeholder="' . pili_esc_attr__( '搜索颜色...' ) . '" />';
                    echo '</div>';
                    echo '</div>';
                }
                
                if ( $args['show_custom'] ) {
                    echo '<button type="button" class="pili-palette-add-custom inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">';
                    echo '<svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />';
                    echo '</svg>';
                    echo pili_esc_html__( '添加颜色' );
                    echo '</button>';
                }
                
                echo '</div>';
            }
            
            echo '<div class="pili-palette-container" data-palette-config="' . esc_attr( json_encode( $palette_data ) ) . '">';
            
            if ( ! empty( $args['options'] ) ) {
                
                if ( $args['group_colors'] && is_array( reset( $args['options'] ) ) ) {
                    $this->render_grouped_palettes( $args['options'], $value, $args );
                } else {
                    $this->render_single_palette( $args['options'], $value, $args );
                }
                
            } else {
                echo '<div class="pili-palette-empty text-center py-8 text-gray-500">';
                echo '<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zM21 5a2 2 0 00-2-2h-4a2 2 0 00-2 2v12a4 4 0 004 4h4a2 2 0 002-2V5z" />';
                echo '</svg>';
                echo '<p class="mt-2 text-sm">' . pili_esc_html__( '暂无可用的调色板' ) . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
            
            if ( $args['show_preview'] ) {
                echo '<div class="pili-palette-preview mt-4 p-3 bg-gray-50 rounded-lg">';
                echo '<div class="text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '已选择的颜色' ) . '</div>';
                echo '<div class="pili-palette-selected-colors flex flex-wrap gap-2"></div>';
                echo '</div>';
            }
            
            if ( $args['multiple'] ) {
                if ( is_array( $value ) ) {
                    foreach ( $value as $selected_value ) {
                        echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '[]" value="' . esc_attr( $selected_value ) . '" class="pili-palette-input" />';
                    }
                }
                if ( empty( $value ) ) {
                    echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '[]" value="" class="pili-palette-input" />';
                }
            } else {
                echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $value ) . '" class="pili-palette-input" />';
            }
            
            echo '</div>';
            
            echo $this->field_after();
        }
        
        /**
         * 渲染分组调色板
         * 
         * @since 1.0
         * 
         * @param array $palettes 调色板组
         * @param mixed $value    当前值
         * @param array $args     配置参数
         */
        private function render_grouped_palettes( $palettes, $value, $args ) {
            
            foreach ( $palettes as $group_key => $group_data ) {
                
                if ( $args['show_labels'] && ! empty( $group_data['label'] ) ) {
                    echo '<div class="pili-palette-group-label mb-3 text-sm font-medium text-gray-700">';
                    echo esc_html( $group_data['label'] );
                    echo '</div>';
                }
                
                echo '<div class="pili-palette-group mb-6" data-group="' . esc_attr( $group_key ) . '">';
                
                $colors = ! empty( $group_data['colors'] ) ? $group_data['colors'] : $group_data;
                $this->render_color_grid( $colors, $value, $args );
                
                echo '</div>';
            }
        }
        
        /**
         * 渲染单一调色板
         * 
         * @since 1.0
         * 
         * @param array $colors 颜色数组
         * @param mixed $value  当前值
         * @param array $args   配置参数
         */
        private function render_single_palette( $colors, $value, $args ) {
            echo '<div class="pili-palette-group" data-group="default">';
            $this->render_color_grid( $colors, $value, $args );
            echo '</div>';
        }
        
        /**
         * 渲染颜色网格
         *
         * @since 1.0
         *
         * @param array $colors 颜色数组
         * @param mixed $value  当前值
         * @param array $args   配置参数
         */
        private function render_color_grid( $colors, $value, $args ) {

            $grid_classes = 'grid gap-3';

            switch ( $args['layout'] ) {
                case 'list':
                    $grid_classes .= ' grid-cols-1';
                    break;
                case 'compact':
                    $grid_classes .= ' grid-cols-8 sm:grid-cols-10 md:grid-cols-12';
                    break;
                default:
                    $cols = intval( $args['colors_per_row'] );
                    switch ( $cols ) {
                        case 3:
                            $grid_classes .= ' grid-cols-3';
                            break;
                        case 4:
                            $grid_classes .= ' grid-cols-4';
                            break;
                        case 5:
                            $grid_classes .= ' grid-cols-5';
                            break;
                        case 6:
                            $grid_classes .= ' grid-cols-6';
                            break;
                        case 7:
                            $grid_classes .= ' grid-cols-7';
                            break;
                        case 8:
                            $grid_classes .= ' grid-cols-8';
                            break;
                        case 10:
                            $grid_classes .= ' grid-cols-10';
                            break;
                        case 12:
                            $grid_classes .= ' grid-cols-12';
                            break;
                        default:
                            $grid_classes .= ' grid-cols-5';
                            break;
                    }
                    break;
            }
            
            echo '<div class="' . $grid_classes . '">';
            
            foreach ( $colors as $color_key => $color_data ) {
                
                if ( is_array( $color_data ) ) {
                    $color_value = ! empty( $color_data['value'] ) ? $color_data['value'] : $color_key;
                    $color_label = ! empty( $color_data['label'] ) ? $color_data['label'] : $color_value;
                    $color_desc = ! empty( $color_data['desc'] ) ? $color_data['desc'] : '';
                } else {
                    // Support '#hex' => 'Label' (preferred) and legacy 'Label' => '#hex'.
                    $key_s  = is_string( $color_key ) ? $color_key : '';
                    $data_s = is_string( $color_data ) ? $color_data : (string) $color_data;
                    if ( self::looks_like_css_color( $key_s ) ) {
                        $color_value = $key_s;
                        $color_label = '' !== $data_s ? $data_s : $key_s;
                    } elseif ( self::looks_like_css_color( $data_s ) ) {
                        $color_value = $data_s;
                        $color_label = '' !== $key_s ? $key_s : $data_s;
                    } else {
                        $color_value = '' !== $key_s ? $key_s : $data_s;
                        $color_label = '' !== $data_s ? $data_s : $key_s;
                    }
                    $color_desc = '';
                }
                
                $is_selected = false;
                if ( $args['multiple'] ) {
                    $is_selected = is_array( $value ) && in_array( $color_value, $value );
                } else {
                    $is_selected = $color_value === $value;
                }
                
                $item_classes = array(
                    'pili-palette-item',
                    'relative',
                    'cursor-pointer',
                    'group',
                    'transition-all',
                    'duration-200',
                );
                
                if ( $is_selected ) {
                    $item_classes[] = 'pili-selected';
                }
                
                echo '<div class="' . implode( ' ', $item_classes ) . '" data-color="' . esc_attr( $color_value ) . '" data-label="' . esc_attr( $color_label ) . '">';
                
                $this->render_color_display( $color_value, $color_label, $color_desc, $args, $is_selected );
                
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        /**
         * 渲染颜色显示
         * 
         * @since 1.0
         * 
         * @param string $color_value 颜色值
         * @param string $color_label 颜色标签
         * @param string $color_desc  颜色描述
         * @param array  $args        配置参数
         * @param bool   $is_selected 是否选中
         */
        private function render_color_display( $color_value, $color_label, $color_desc, $args, $is_selected ) {
            
            $size_classes = array(
                'small'  => 'w-8 h-8',
                'medium' => 'w-12 h-12',
                'large'  => 'w-16 h-16',
            );
            
            $color_size = $size_classes[ $args['color_size'] ] ?? $size_classes['medium'];
            
            if ( $args['layout'] === 'list' ) {
                $list_border_classes = $is_selected
                    ? 'border-blue-500 bg-blue-50 border-2'
                    : 'border-gray-200 hover:border-gray-300 border';

                echo '<div class="flex items-center space-x-3 p-3 rounded-lg transition-colors ' . $list_border_classes . '">';

                $color_border_classes = $is_selected
                    ? 'border-2 border-blue-500'
                    : 'border border-gray-200';

                echo '<div class="' . $color_size . ' rounded-lg shadow-sm flex-shrink-0 ' . $color_border_classes . '" style="background-color: ' . esc_attr( $color_value ) . ';"></div>';
                echo '<div class="flex-1 min-w-0">';
                echo '<div class="text-sm font-medium text-gray-900">' . esc_html( $color_label ) . '</div>';
                if ( ! empty( $color_desc ) ) {
                    echo '<div class="text-xs text-gray-500">' . esc_html( $color_desc ) . '</div>';
                }
                echo '<div class="text-xs text-gray-400 font-mono">' . esc_html( $color_value ) . '</div>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="relative">';

                $border_classes = $is_selected
                    ? 'border-2 border-blue-500 shadow-md'
                    : 'border border-gray-200 group-hover:border-gray-400';

                echo '<div class="' . $color_size . ' rounded-lg shadow-sm transition-all duration-200 ' . $border_classes . ' group-hover:shadow-md" style="background-color: ' . esc_attr( $color_value ) . ';"></div>';

                if ( $args['layout'] !== 'compact' ) {
                    echo '<div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-10">';
                    echo esc_html( $color_label );
                    if ( $color_label !== $color_value ) {
                        echo '<br><span class="font-mono">' . esc_html( $color_value ) . '</span>';
                    }
                    echo '</div>';
                }

                echo '</div>';
            }
        }
        
        /**
         * 加载字段资源
         *
         * 加载调色板字段所需的JavaScript资源。
         *
         * @since 1.0
         */
        public function enqueue() {
            $handle       = pili_asset_handle( 'field-palette' );
            $color_handle = pili_asset_handle( 'field-color' );
            $palette_js   = PILI_Setup::$dir . '/assets/js/fields/palette.js';
            $palette_ver  = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : (string) PILI_Setup::$version;
            if ( is_readable( $palette_js ) ) {
                $palette_ver .= '.' . (string) filemtime( $palette_js );
            }

            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/palette.js',
                array( 'jquery', 'wp-color-picker', $color_handle ),
                $palette_ver,
                true
            );

            // 构建产物可能不含 pl-10：无左内边距时放大镜与 placeholder/文字重叠。
            wp_register_style( $handle, false, array(), $palette_ver );
            wp_enqueue_style( $handle );
            wp_add_inline_style(
                $handle,
                '.pili-palette-search{padding-left:2.5rem!important}'
            );
            
            pili_localize_bag( $handle, 'palette', array(
                'strings' => array(
                    'search' => pili__( '搜索' ),
                    'noResults'       => pili__( '未找到匹配的颜色' ),
                    'addCustom'       => pili__( '添加自定义颜色' ),
                    'removeColor'     => pili__( '移除颜色' ),
                    'selectColor'     => pili__( '选择颜色' ),
                    'selectedColor'   => pili__( '已选择的颜色' ),
                    'colorValue'      => pili__( '颜色值' ),
                    'noColorSelected' => pili__( '未选择任何颜色' ),
                    'formatLabel'     => pili__( '格式' ),
                    'invalidColor'    => pili__( '无效的颜色值' ),
                    'colorCopied'     => pili__( '颜色已复制' ),
                ),
                'nonce' => wp_create_nonce( 'pili_palette_nonce' ),
            ) );
        }

        /**
         * Whether a string looks like a CSS color token (hex / rgb / hsl / var).
         *
         * @param string $s Candidate.
         * @return bool
         */
        private static function looks_like_css_color( $s ) {
            $s = trim( (string) $s );
            if ( '' === $s ) {
                return false;
            }
            return (bool) preg_match( '/^(#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|rgba?\(|hsla?\(|var\()/', $s );
        }
        
        /**
         * 验证和清理字段数据
         * 
         * 对调色板字段的数据进行验证和清理。
         * 
         * @since 1.0
         * 
         * @param mixed $value 要验证的值
         * 
         * @return mixed 清理后的数据
         */
        public function validate( $value ) {
            
            $args = wp_parse_args( $this->field, array(
                'multiple'    => false,
                'allow_empty' => true,
                'options'     => array(),
            ) );
            
            if ( $args['multiple'] ) {
                if ( ! is_array( $value ) ) {
                    return array();
                }
                
                $validated = array();
                foreach ( $value as $single_value ) {
                    $clean_value = sanitize_text_field( $single_value );
                    if ( ! empty( $clean_value ) || $args['allow_empty'] ) {
                        $validated[] = $clean_value;
                    }
                }
                
                return array_unique( $validated );
            } else {
                $validated = sanitize_text_field( $value );
                
                if ( empty( $validated ) && ! $args['allow_empty'] ) {
                    return '';
                }
                
                return $validated;
            }
        }
    }
}
