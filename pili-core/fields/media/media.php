<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 媒体字段类型
 * 
 * 这个类实现了现代化的媒体选择字段功能。
 * 支持图片、视频、音频等多种媒体类型的选择和管理。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_media' ) ) {
    
    /**
     * PILI_Field_media 媒体字段类
     * 
     * 提供媒体选择字段的完整功能，包括：
     * - 现代化的媒体选择界面
     * - 实时预览功能
     * - 拖拽上传支持
     * - 多种媒体类型支持
     * - 响应式设计
     * - 无障碍访问优化
     * 
     * @since 1.0
     */
    class PILI_Field_media extends PILI_Fields {
        /**
         * 构造函数
         * 
         * 初始化媒体字段实例。
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
         * 渲染媒体字段
         * 
         * 输出现代化的媒体选择字段HTML。
         * 
         * @since 1.0
         */
        public function render() {
            $args = wp_parse_args( $this->field, array(
                'url'              => true,           // 是否显示URL输入框
                'preview'          => true,           // 是否显示预览
                'preview_size'     => 'medium',       // 预览尺寸
                'preview_width'    => '',             // 自定义预览宽度
                'preview_height'   => '',             // 自定义预览高度
                'library'          => array(),        // 媒体库类型限制
                'multiple'         => false,          // 是否支持多选
                'button_title' => pili__( '选择媒体' ),
                'remove_title' => pili__( '移除' ),
                'placeholder' => pili__( '请选择…' ),
                'drag_drop'        => true,           // 是否支持拖拽上传
                'show_filename'    => true,           // 是否显示文件名
                'show_filesize'    => true,           // 是否显示文件大小
                'show_dimensions'  => true,           // 是否显示图片尺寸
                'compact_mode'     => false,          // 紧凑模式
            ) );
            
            $default_values = array(
                'id'          => '',
                'url'         => '',
                'filename'    => '',
                'filesize'    => '',
                'width'       => '',
                'height'      => '',
                'thumbnail'   => '',
                'alt'         => '',
                'title'       => '',
                'description' => '',
                'mime_type'   => '',
            );
            if ( is_numeric( $this->value ) ) {
                $attachment_id = intval( $this->value );
                $attachment = get_post( $attachment_id );
                if ( $attachment ) {
                    $this->value = array(
                        'id'          => $attachment_id,
                        'url'         => wp_get_attachment_url( $attachment_id ),
                        'filename'    => basename( get_attached_file( $attachment_id ) ),
                        'thumbnail'   => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
                        'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                        'title'       => $attachment->post_title,
                        'description' => $attachment->post_content,
                        'mime_type'   => $attachment->post_mime_type,
                    );
                    $file_path = get_attached_file( $attachment_id );
                    if ( $file_path && file_exists( $file_path ) ) {
                        $this->value['filesize'] = size_format( filesize( $file_path ) );
                        if ( wp_attachment_is_image( $attachment_id ) ) {
                            $image_meta = wp_get_attachment_metadata( $attachment_id );
                            if ( $image_meta ) {
                                $this->value['width'] = $image_meta['width'];
                                $this->value['height'] = $image_meta['height'];
                            }
                        }
                    }
                }
            }
            if ( ! is_array( $this->value ) ) {
                $this->value = array();
            }
            $this->value = wp_parse_args( $this->value, $default_values );
            $library = '';
            if ( ! empty( $args['library'] ) ) {
                $library_types = is_array( $args['library'] ) ? $args['library'] : array( $args['library'] );
                $library = implode( ',', $library_types );
            }
            $field_id = $this->field_id();
            $has_media = ! empty( $this->value['id'] );
            echo $this->field_before();
            echo '<div class="pili-media-field" data-field-id="' . esc_attr( $field_id ) . '" data-library="' . esc_attr( $library ) . '" data-preview-size="' . esc_attr( $args['preview_size'] ) . '" data-multiple="' . ( $args['multiple'] ? 'true' : 'false' ) . '"';
            
            // 添加自定义预览尺寸属性
            if ( ! empty( $args['preview_width'] ) ) {
                echo ' data-preview-width="' . esc_attr( intval( $args['preview_width'] ) ) . '"';
            }
            if ( ! empty( $args['preview_height'] ) ) {
                echo ' data-preview-height="' . esc_attr( intval( $args['preview_height'] ) ) . '"';
            }
            
            echo '>';
            if ( $args['preview'] && $has_media ) {
                $this->render_preview( $args );
            }
            $this->render_controls( $args, $field_id, $has_media );
            $this->render_hidden_fields();
            echo '</div>';
            echo $this->field_after();
        }
        /**
         * 渲染预览区域
         * 
         * @since 1.0
         * 
         * @param array $args 字段配置参数
         */
        private function render_preview( $args ) {
            $preview_style = '';
            if ( ! empty( $args['preview_width'] ) ) {
                $preview_style .= 'max-width: ' . esc_attr( $args['preview_width'] ) . 'px; ';
            }
            if ( ! empty( $args['preview_height'] ) ) {
                $preview_style .= 'max-height: ' . esc_attr( $args['preview_height'] ) . 'px; ';
            }
            $preview_url = ! empty( $this->value['thumbnail'] ) ? $this->value['thumbnail'] : $this->value['url'];
            $is_image = strpos( $this->value['mime_type'], 'image/' ) === 0;
            echo '<div class="pili-media-preview relative group bg-gray-50 border-2 border-dashed border-gray-200 rounded-lg overflow-hidden transition-all duration-200 hover:border-gray-300"' . ( $preview_style ? ' style="' . $preview_style . '"' : '' ) . '>';
            if ( $is_image && $preview_url ) {
                echo '<img src="' . esc_url( $preview_url ) . '" alt="' . esc_attr( $this->value['alt'] ) . '" class="w-full h-full object-cover" />';
            } else {
                echo '<div class="flex items-center justify-center p-6 min-h-[120px]">';
                echo '<div class="text-center">';
                echo '<div class="text-4xl text-gray-400 mb-2">' . $this->get_file_icon( $this->value['mime_type'] ) . '</div>';
                if ( ! empty( $this->value['filename'] ) ) {
                    echo '<div class="text-sm font-medium text-gray-700 truncate max-w-[200px]">' . esc_html( $this->value['filename'] ) . '</div>';
                }
                if ( ! empty( $this->value['filesize'] ) ) {
                    echo '<div class="text-xs text-gray-500 mt-1">' . esc_html( $this->value['filesize'] ) . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '<button type="button" class="pili-media-remove absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center hover:bg-red-600 focus:outline-none" title="' . esc_attr( $args['remove_title'] ) . '">';
            echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
            echo '</button>';
            echo '</div>';
        }
        /**
         * 渲染控制区域
         * 
         * @since 1.0
         * 
         * @param array  $args     字段配置参数
         * @param string $field_id 字段ID
         * @param bool   $has_media 是否有媒体文件
         */
        private function render_controls( $args, $field_id, $has_media ) {
            echo '<div class="pili-media-controls mt-4">';
            echo '<div class="pili-media-input-group" style="display: flex; align-items: stretch; gap: 0.5rem; flex-wrap: wrap;">';
            echo '<style>
                @media (max-width: 640px) {
                    .pili-media-input-group {
                        flex-direction: column !important;
                        gap: 0.75rem !important;
                    }
                    .pili-media-input-group .pili-media-url {
                        border-radius: 0.375rem !important;
                    }
                    .pili-media-input-group .pili-media-button {
                        justify-content: center !important;
                    }
                }
            </style>';
            if ( $args['url'] ) {
                echo '<input type="text" name="' . esc_attr( $this->field_name( '[url]' ) ) . '" value="' . esc_attr( $this->value['url'] ) . '" class="pili-media-url flex-1 px-3 py-2 border border-gray-300 rounded-l-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" style="min-width: 200px;" placeholder="' . esc_attr( $args['placeholder'] ) . '" readonly />';
            }
            $button_class = $args['url'] ? 'rounded-r-md border-l-0' : 'rounded-md';
            echo '<button type="button" class="pili-media-button pili-focusable inline-flex items-center px-4 py-2 border border-blue-600/0 text-sm font-medium ' . $button_class . ' text-white bg-blue-600 hover:bg-blue-700 focus:outline-none transition-colors duration-200" style="flex-shrink: 0;">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
            echo esc_html( $args['button_title'] );
            echo '</button>';
            echo '</div>';
            if ( $has_media && ! $args['preview'] ) {
                echo '<div class="mt-3">';
                echo '<button type="button" class="pili-media-remove pili-focusable inline-flex items-center px-4 py-2 border border-red-500/30 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none transition-colors duration-200">';
                echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
                echo esc_html( $args['remove_title'] );
                echo '</button>';
                echo '</div>';
            }
            if ( $has_media && ( $args['show_filename'] || $args['show_filesize'] || $args['show_dimensions'] ) ) {
                echo '<div class="mt-3 p-3 bg-gray-50 rounded-md">';
                echo '<div class="text-sm text-gray-600 space-y-1">';
                if ( $args['show_filename'] && ! empty( $this->value['filename'] ) ) {
                    echo '<div><span class="font-medium">' . pili_esc_html__( '文件名:' ) . '</span> ' . esc_html( $this->value['filename'] ) . '</div>';
                }
                if ( $args['show_filesize'] && ! empty( $this->value['filesize'] ) ) {
                    echo '<div><span class="font-medium">' . pili_esc_html__( '文件大小:' ) . '</span> ' . esc_html( $this->value['filesize'] ) . '</div>';
                }
                if ( $args['show_dimensions'] && ! empty( $this->value['width'] ) && ! empty( $this->value['height'] ) ) {
                    echo '<div><span class="font-medium">' . pili_esc_html__( '尺寸:' ) . '</span> ' . esc_html( $this->value['width'] ) . ' × ' . esc_html( $this->value['height'] ) . ' ' . pili_esc_html__( '像素' ) . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        /**
         * 渲染隐藏字段
         * 
         * @since 1.0
         */
        private function render_hidden_fields() {
            $hidden_fields = array(
                'id', 'filename', 'filesize', 'width', 'height',
                'thumbnail', 'alt', 'title', 'description', 'mime_type'
            );
            foreach ( $hidden_fields as $field ) {
                echo '<input type="hidden" name="' . esc_attr( $this->field_name( '[' . $field . ']' ) ) . '" value="' . esc_attr( $this->value[ $field ] ) . '" class="pili-media-' . esc_attr( $field ) . '" />';
            }
        }
        /**
         * 获取文件类型图标
         *
         * @since 1.0
         *
         * @param string $mime_type MIME类型
         *
         * @return string 图标HTML
         */
        private function get_file_icon( $mime_type ) {
            if ( strpos( $mime_type, 'image/' ) === 0 ) {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm16 12V8l-4 4-6-6-6 6v4h16zM9 11a1 1 0 100-2 1 1 0 000 2z"/></svg>';
            } elseif ( strpos( $mime_type, 'video/' ) === 0 ) {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm6 4v8l6-4-6-4z"/></svg>';
            } elseif ( strpos( $mime_type, 'audio/' ) === 0 ) {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>';
            } else {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>';
            }
        }
        /**
         * 加载字段资源
         *
         * 加载媒体字段所需的CSS和JavaScript文件。
         *
         * @since 1.0
         */
        public function enqueue() {
            // 确保WordPress媒体库已加载
            if ( ! did_action( 'wp_enqueue_media' ) ) {
                wp_enqueue_media();
            }

            // 确保必要的依赖已加载
            wp_enqueue_script( 'media-upload' );
            wp_enqueue_script( 'media-views' );
            wp_enqueue_script( 'media-models' );
            wp_enqueue_script( 'media-grid' );

            // 加载媒体字段脚本
            $handle = pili_asset_handle( 'field-media' );
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/media.js',
                array( 'jquery', 'media-upload', 'media-views', 'media-models', 'media-grid', 'wp-backbone' ),
                PILI_CORE_VERSION,
                true
            );

            // 本地化脚本
            pili_localize_bag( $handle, 'media', array(
                'title' => pili__( '标题' ),
                'button' => pili__( '选择' ),
                'uploading' => pili__( '上传中…' ),
                'error' => pili__( '错误' ),
                'remove_confirm' => pili__( '确定要移除这个文件吗？' ),
                'errorTitle' => pili__( '错误' ),
                'confirmRemoveTitle' => pili__( '确认移除' ),
                'confirmRemoveMedia' => pili__( '确定要移除这个媒体文件吗？' ),
                'remove' => pili__( '移除' ),
                'cancel' => pili__( '取消' ),
                'libraryLoadError' => pili__( '媒体库未正确加载，请刷新页面重试' ),
                'libraryOpenError' => pili__( '无法打开媒体库' ),
                'selectionError' => pili__( '无法获取选择的媒体文件' ),
                'processError' => pili__( '处理媒体选择时出现错误' ),
                'setMediaError' => pili__( '设置媒体文件时出现错误' ),
                'removeMediaError' => pili__( '移除媒体文件时出现错误' ),
                'unknownFile' => pili__( '未知文件' ),
                'imageFile' => pili__( '图片文件' ),
                'max_file_size'       => wp_max_upload_size(),
                'allowed_types'       => get_allowed_mime_types(),
                'nonce'               => wp_create_nonce( 'pili_media_nonce' ),
                'ajax_url'            => admin_url( 'admin-ajax.php' ),
                'debug'               => defined( 'WP_DEBUG' ) && WP_DEBUG,
            ) );
        }
        /**
         * 验证字段值
         *
         * 验证和清理媒体字段值。
         *
         * @since 1.0
         *
         * @param mixed $value 要验证的值
         *
         * @return mixed 验证后的值
         */
        public function validate( $value ) {
            if ( empty( $value ) ) {
                return array();
            }
            if ( is_numeric( $value ) ) {
                $attachment_id = intval( $value );
                if ( get_post( $attachment_id ) ) {
                    return array( 'id' => $attachment_id );
                }
                return array();
            }
            if ( is_array( $value ) ) {
                $validated = array();
                if ( ! empty( $value['id'] ) ) {
                    $attachment_id = intval( $value['id'] );
                    if ( get_post( $attachment_id ) ) {
                        $validated['id'] = $attachment_id;
                    }
                }
                if ( ! empty( $value['url'] ) ) {
                    $validated['url'] = esc_url_raw( $value['url'] );
                }
                $text_fields = array( 'filename', 'filesize', 'alt', 'title', 'description', 'mime_type' );
                foreach ( $text_fields as $field ) {
                    if ( ! empty( $value[ $field ] ) ) {
                        $validated[ $field ] = sanitize_text_field( $value[ $field ] );
                    }
                }
                $number_fields = array( 'width', 'height' );
                foreach ( $number_fields as $field ) {
                    if ( ! empty( $value[ $field ] ) ) {
                        $validated[ $field ] = intval( $value[ $field ] );
                    }
                }
                if ( ! empty( $value['thumbnail'] ) ) {
                    $validated['thumbnail'] = esc_url_raw( $value['thumbnail'] );
                }
                return $validated;
            }
            return array();
        }
    }
}
