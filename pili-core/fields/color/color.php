<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class PILI_Field_color extends PILI_Fields {

        /**
         * 字段类型
         */
        public $type = 'color';

        /**
         * 构造函数
         *
         * 初始化颜色字段实例。
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
         * 支持的颜色格式
         */
        private $supported_formats = array( 'hex', 'rgb', 'rgba', 'hsl', 'hsla' );
        
        /**
         * 默认调色板
         */
        private $default_palette = array(
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
            '#F8C471', '#82E0AA', '#F1948A', '#85C1E9', '#D7BDE2',
            '#A3E4D7', '#F9E79F', '#D5A6BD', '#AED6F1', '#A9DFBF'
        );
        
        /**
         * 渲染字段 - 响应式自适应设计
         */
        public function render() {
            $args = wp_parse_args( $this->field, array(
                'format'           => 'hex',
                'alpha'            => false,
                'palette'          => $this->default_palette,
                'history'          => false,
                'contrast_check'   => false,
                'eyedropper'       => false,
                'keyboard_support' => true,
                'width'            => '100%',
                'height'           => '300px'
            ) );

            $unique_suffix = uniqid();
            $field_path = $this->unique ? $this->unique . '_' . $this->field['id'] : $this->field['id'];
            $field_id = 'pili-color-' . md5($field_path) . '-' . $unique_suffix;
            $input_id = $field_id . '-input';

            $value = $this->value;
            $display_value = ! empty( $value ) ? $value : ( ! empty( $args['default'] ) ? $args['default'] : '#3B82F6' );

            echo $this->field_before();

            echo '<div class="pili-color-field bg-white rounded-lg border border-gray-200 p-3 sm:p-4 md:p-6 w-full" data-field-id="' . esc_attr( $field_id ) . '" data-field-path="' . esc_attr( $field_path ) . '">';

            echo '<div class="pili-color-responsive-container" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">';

            echo '<style>
                @media (max-width: 640px) {
                    .pili-color-responsive-container {
                        flex-direction: column !important;
                        align-items: stretch !important;
                        gap: 1rem !important;
                    }
                    .pili-color-input-group {
                        flex-direction: column !important;
                        gap: 0.75rem !important;
                    }
                    .pili-color-preview-mobile {
                        align-self: center !important;
                    }
                    .pili-color-preview-mobile .w-12 {
                        width: 4rem !important;
                        height: 4rem !important;
                    }
                }
                @media (min-width: 641px) and (max-width: 768px) {
                    .pili-color-responsive-container {
                        flex-direction: row !important;
                        align-items: center !important;
                        gap: 1rem !important;
                    }
                    .pili-color-input-group {
                        flex-direction: column !important;
                        gap: 0.5rem !important;
                    }
                }
            </style>';

            echo '<div class="pili-color-preview-mobile" style="flex-shrink: 0;">';
            echo '<div class="w-12 h-12 rounded-lg border-2 border-gray-300 shadow-sm cursor-pointer transition-transform duration-200 hover:scale-105" ';
            echo 'style="background: ' . esc_attr( $display_value ) . ';" ';
            echo 'id="' . esc_attr( $field_id ) . '-preview" ';
            echo 'title="' . esc_attr( pili__( '点击选择颜色' ) ) . '">';
            echo '</div>';
            echo '</div>';

            echo '<div class="pili-color-input-group" style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0;">';

            echo '<input type="text" ';
            echo 'name="' . esc_attr( $this->field_name() ) . '" ';
            echo 'id="' . esc_attr( $input_id ) . '" ';
            echo 'value="' . esc_attr( $value ) . '" ';
            echo 'class="pili-color-input flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono transition-colors" ';
            echo 'style="min-width: 120px;" ';
            echo 'placeholder="' . esc_attr( pili__( '输入颜色值' ) ) . '" ';
            echo $this->field_attributes() . '/>';

            echo '<select class="pili-color-format-select px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">';
            $formats = array( 'hex' => 'HEX', 'rgb' => 'RGB', 'rgba' => 'RGBA', 'hsl' => 'HSL', 'hsla' => 'HSLA' );
            foreach ( $formats as $format_key => $format_label ) {
                $selected = $format_key === $args['format'] ? ' selected' : '';
                echo '<option value="' . esc_attr( $format_key ) . '"' . $selected . '>' . esc_html( $format_label ) . '</option>';
            }
            echo '</select>';

            echo '</div>';

            echo '</div>';

            if ( ! empty( $args['palette'] ) && is_array( $args['palette'] ) ) {
                echo '<div class="mt-4">';
                echo '<label class="block text-xs font-medium text-gray-700 mb-2">' . esc_html( pili__( '调色板' ) ) . '</label>';

                echo '<div class="pili-palette-grid" style="display: grid; grid-template-columns: repeat(10, 1fr); gap: 6px;">';

                echo '<style>
                    @media (max-width: 640px) {
                        .pili-palette-grid {
                            grid-template-columns: repeat(5, 1fr) !important;
                            gap: 10px !important;
                        }
                        .pili-palette-color {
                            width: 2rem !important;
                            height: 2rem !important;
                            border-radius: 0.5rem !important;
                        }
                    }
                    @media (min-width: 641px) and (max-width: 768px) {
                        .pili-palette-grid {
                            grid-template-columns: repeat(6, 1fr) !important;
                            gap: 8px !important;
                        }
                    }
                    @media (min-width: 769px) and (max-width: 1024px) {
                        .pili-palette-grid {
                            grid-template-columns: repeat(8, 1fr) !important;
                        }
                    }
                    @media (min-width: 1025px) {
                        .pili-palette-grid {
                            grid-template-columns: repeat(12, 1fr) !important;
                        }
                    }
                </style>';

                foreach ( $args['palette'] as $palette_color ) {
                    echo '<button type="button" class="pili-palette-color pili-palette-' . esc_attr( $field_id ) . ' w-6 h-6 rounded border border-gray-300 hover:scale-110 hover:shadow-md transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" ';
                    echo 'style="background: ' . esc_attr( $palette_color ) . ';" ';
                    echo 'data-color="' . esc_attr( $palette_color ) . '" ';
                    echo 'data-field-id="' . esc_attr( $field_id ) . '" ';
                    echo 'title="' . esc_attr( $palette_color ) . '">';
                    echo '</button>';
                }

                echo '</div>';
                echo '</div>';
            }

            $config = array(
                'fieldId'         => $field_id,
                'inputId'         => $input_id,
                'actualFieldId'   => $field_id,
                'fieldPath'       => $field_path,
                'value'           => $display_value,
                'format'          => $args['format'],
                'alpha'           => $args['alpha'],
                'palette'         => $args['palette'],
                'responsive'      => true,
                'messages'        => array(
                    'formatLabel' => pili__( '颜色值' ),
                    'invalidColor' => pili__( '无效的颜色值' ),
                    'colorCopied' => pili__( '颜色已复制' ),
                    'selectColor' => pili__( '选择颜色' ),
                    'closeColorPicker' => pili__( '关闭取色器' )
                )
            );

            echo '<script type="application/json" class="pili-color-config">' . wp_json_encode( $config ) . '</script>';

            echo '</div>';

            echo $this->field_after();
        }
        
        /**
         * 获取容器CSS类
         */
        private function get_container_classes( $args ) {
            $classes = array( 'pili-color-field' );

            $classes[] = 'bg-white rounded-lg border border-gray-200 overflow-hidden';
            $classes[] = 'transition-all duration-200 ease-in-out';
            
            $classes[] = 'w-full';
            
            $classes[] = 'touch-manipulation';
            
            $classes[] = 'p-3 sm:p-4';

            return implode( ' ', $classes );
        }
        
        /**
         * 渲染工具栏
         */
        private function render_toolbar( $args, $field_id ) {
            echo '<div class="flex items-center space-x-2">';
            echo '<button type="button" class="pili-color-eyedropper-btn inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors" title="' . pili_esc_attr__( '取色器' ) . '">';
            echo '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zM7 3V1m0 18v2m8-10h2m-2 0h2m-2 0v2m-2-2h2"></path></svg>';
            echo '<span>' . pili_esc_html__( '取色' ) . '</span>';
            echo '</button>';

            echo '<button type="button" class="pili-color-copy-btn inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors" title="' . pili_esc_attr__( '复制颜色' ) . '">';
            echo '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>';
            echo '<span>' . pili_esc_html__( '复制' ) . '</span>';
            echo '</button>';
            echo '</div>';

            echo '<div class="flex items-center space-x-2">';
            echo '<button type="button" class="pili-color-reset-btn inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors" title="' . pili_esc_attr__( '重置为默认值' ) . '">';
            echo '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
            echo '<span>' . pili_esc_html__( '重置' ) . '</span>';
            echo '</button>';
            echo '</div>';
        }
        
        /**
         * 渲染状态栏
         */
        private function render_status_bar( $args, $field_id ) {
            echo '<div class="pili-color-status-bar flex items-center justify-between p-2 bg-gray-50 border-t border-gray-200 text-xs text-gray-600">';
            
            echo '<div class="flex items-center gap-2">';
            echo '<span class="pili-color-current-value font-mono"></span>';
            echo '</div>';
            
            if ( ! empty( $args['contrast_check'] ) ) {
                echo '<div class="flex items-center gap-2">';
                echo '<span>' . pili_esc_html__( '对比度:' ) . '</span>';
                echo '<span class="pili-color-contrast-ratio font-mono"></span>';
                echo '<span class="pili-color-contrast-status"></span>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        /**
         * 字段脚本和样式
         */
        public function enqueue() {
            $handle       = pili_asset_handle( 'field-color' );
            $dependencies = array( 'jquery' );

            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/color.js',
                $dependencies,
                PILI_Setup::$version,
                true
            );
            
            pili_localize_bag( $handle, 'color', array(
                'messages' => array(
                    'loading' => pili__( '加载中…' ),
                    'error' => pili__( '错误' ),
                    'invalidColor' => pili__( '无效的颜色值' ),
                    'colorCopied' => pili__( '颜色已复制' ),
                    'contrastGood' => pili__( '对比度良好' ),
                    'contrastPoor' => pili__( '对比度不足' ),
                    'eyedropperError' => pili__( '取色失败' ),
                    'selectColor' => pili__( '选择颜色' ),
                    'outputFormat' => pili__( '输出格式' ),
                    'presetColors' => pili__( '预设颜色' ),
                    'hue' => pili__( '色相' ),
                    'historyLabel' => pili__( '历史颜色' ),
                    'paletteLabel' => pili__( '调色板' ),
                    'noHistory' => pili__( '暂无历史记录' ),
                    'cancel' => pili__( '取消' ),
                    'confirm' => pili__( '确认' ),
                    'copyFailed' => pili__( '复制失败' ),
                    'copyUnavailable' => pili__( '复制功能不可用' ),
                ),
                'nonce' => wp_create_nonce( 'pili_color_field' )
            ) );
        }
        
        /**
         * 字段验证
         */
        public function validate( $value ) {
            if ( empty( $value ) ) {
                return '';
            }
            
            if ( $this->is_valid_color( $value ) ) {
                return sanitize_text_field( $value );
            }
            
            return '';
        }
        
        /**
         * 验证颜色值是否有效
         */
        private function is_valid_color( $color ) {
            if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3}|[A-Fa-f0-9]{8})$/', $color ) ) {
                return true;
            }
            
            if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[0-1]?(?:\.\d+)?)?\s*\)$/', $color ) ) {
                return true;
            }
            
            if ( preg_match( '/^hsla?\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%\s*(?:,\s*[0-1]?(?:\.\d+)?)?\s*\)$/', $color ) ) {
                return true;
            }
            
            if ( $color === 'transparent' ) {
                return true;
            }
            
            return false;
        }
    }
