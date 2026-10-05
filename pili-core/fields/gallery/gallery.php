<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
  * PILI Framework 图片库字段类型
  * 
  * 这个类实现了现代化的图片库管理功能，提供比Codestar Framework更优秀的用户体验。
  * 支持拖拽排序、批量操作、响应式设计等高级功能。
  * 
  * @package PILI Framework
  * @author  June
  * @link    https://www.xuntheme.com
  * @since   1.0
  * @version 1.0
  */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_gallery' ) ) {
    
    /**
     * PILI_Field_gallery 图片库字段类
     * 
     * 提供完整的图片库管理功能，包括：
     * - 多图片上传和管理
     * - 拖拽排序功能
     * - 图片预览和编辑
     * - 批量操作（删除、选择等）
     * - 响应式网格布局
     * - 现代化用户界面
     * - 键盘导航支持
     * - 无障碍访问优化
     * - 图片懒加载
     * - 文件大小和格式验证
     * 
     * @since 1.0
     */
    class PILI_Field_gallery extends PILI_Fields {
        
        /**
         * 构造函数
         * 
         * 初始化图片库字段实例。
         * 
         * @since 1.0
         * 
         * @param array  $field  字段配置数组
         * @param mixed  $value  字段值（图片ID数组，逗号分隔的字符串）
         * @param string $unique 唯一标识符
         * @param string $where  字段位置
         * @param string $parent 父级字段
         */
        public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
            parent::__construct( $field, $value, $unique, $where, $parent );
        }
        
        /**
         * 渲染图片库字段
         *
         * 输出现代化图片库字段的HTML代码，包括上传区域、图片网格、操作按钮等。
         *
         * @since 1.0
         */
        public function render() {
            
            $args = wp_parse_args( $this->field, array(
                'add_title' => pili__( '添加图片' ),
                'edit_title' => pili__( '编辑图片库' ),
                'clear_title' => pili__( '清空选择' ),
                'remove_title' => pili__( '移除' ),
                'select_all' => pili__( '全选' ),
                'deselect_all' => pili__( '取消全选' ),
                'delete_selected' => pili__( '删除选中' ),
                'batch_toggle' => pili__( '批量操作' ),
                'batch_exit' => pili__( '退出批量' ),
                'empty_gallery' => pili__( '暂无图片' ),
                'max_files'       => 0,
                'min_files'       => 0,
                'allowed_types'   => array( 'image' ),
                'max_file_size'   => 0,
                'preview_size'    => 'thumbnail',
                'grid_columns'    => array( 'sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 5 ),
                'enable_sorting'  => true,
                'enable_batch'    => true,
                'enable_preview'  => true,
                'upload_text' => pili__( '点击或拖拽文件到此处' ),
                'upload_hint' => pili__( '或点击浏览' ),
                'empty_text' => pili__( '暂无图片' ),
                'loading_text' => pili__( '加载中…' ),
                'error_max_files' => pili__( '最多只能上传 {max} 张图片' ),
                'error_min_files' => pili__( '至少需要上传 {min} 张图片' ),
                'error_file_type' => pili__( '不支持的文件类型' ),
                'error_file_size' => pili__( '文件大小超出限制' ),
                'confirm_clear' => pili__( '确认清空' ),
                'confirm_delete' => pili__( '确认删除' ),
                'confirm_remove' => pili__( '确认移除' ),
            ) );
            
            $image_ids = array();
            if ( ! empty( $this->value ) ) {
                if ( is_string( $this->value ) ) {
                    $image_ids = array_filter( explode( ',', $this->value ) );
                } elseif ( is_array( $this->value ) ) {
                    $image_ids = array_filter( $this->value );
                }
                $image_ids = array_map( 'intval', $image_ids );
            }
            
            $field_id = $this->field_id();
            $field_name = $this->field_name();
            
            echo $this->field_before();
            
            echo '<div class="pili-gallery-field" data-field-id="' . esc_attr( $field_id ) . '" data-max-files="' . esc_attr( $args['max_files'] ) . '" data-min-files="' . esc_attr( $args['min_files'] ) . '">';
            
            echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( implode( ',', $image_ids ) ) . '" class="pili-gallery-input" />';
            
            echo '<div class="pili-gallery-container' . ( empty( $image_ids ) ? ' pili-gallery-empty' : '' ) . '">';
            
            $this->render_toolbar( $args );
            
            $this->render_upload_area( $args );
            
            $this->render_image_grid( $image_ids, $args );
            
            $this->render_empty_state( $args );
            
            echo '</div>';
            
            echo '<div class="pili-gallery-loading hidden">';
            echo '<div class="flex items-center justify-center py-8">';
            echo '<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>';
            echo '<span class="ml-3 text-sm text-gray-600">' . esc_html( $args['loading_text'] ) . '</span>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>';
            
            echo $this->field_after();
            
            $this->render_script_config( $args );
        }
        
        /**
         * 渲染工具栏
         * 
         * @since 1.0
         * 
         * @param array $args 字段配置参数
         */
        private function render_toolbar( $args ) {
            echo '<div class="pili-gallery-toolbar flex flex-wrap items-center justify-between gap-3 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">';
            
            echo '<div class="flex flex-wrap items-center gap-2">';
            
            echo '<button type="button" class="pili-gallery-add-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>';
            echo '</svg>';
            echo esc_html( $args['add_title'] );
            echo '</button>';
            
            echo '<button type="button" class="pili-gallery-edit-btn hidden inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>';
            echo '</svg>';
            echo esc_html( $args['edit_title'] );
            echo '</button>';
            
            echo '</div>';
            
            echo '<div class="flex flex-wrap items-center gap-2">';
            
            if ( $args['enable_batch'] ) {
                echo '<div class="pili-gallery-batch-controls hidden flex-wrap items-center gap-2">';
                
                echo '<button type="button" class="pili-gallery-select-all-btn inline-flex items-center px-3 py-2 border border-blue-300 text-sm leading-4 font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
                echo esc_html( $args['select_all'] );
                echo '</button>';
                
                echo '<button type="button" class="pili-gallery-deselect-all-btn inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
                echo esc_html( $args['deselect_all'] );
                echo '</button>';
                
                echo '<button type="button" class="pili-gallery-delete-selected-btn inline-flex items-center px-3 py-2 border border-red-300 text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">';
                echo esc_html( $args['delete_selected'] );
                echo '</button>';
                
                echo '</div>';
                
                echo '<button type="button" class="pili-gallery-batch-toggle-btn hidden inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
                echo esc_html( $args['batch_toggle'] );
                echo '</button>';
            }
            
            echo '<button type="button" class="pili-gallery-clear-btn hidden inline-flex items-center px-3 py-2 border border-red-300 text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">';
            echo esc_html( $args['clear_title'] );
            echo '</button>';
            
            echo '</div>';
            
            echo '</div>';
        }
        
        /**
         * 渲染上传区域
         * 
         * @since 1.0
         * 
         * @param array $args 字段配置参数
         */
        private function render_upload_area( $args ) {
            echo '<div class="pili-gallery-upload-area hidden border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-gray-400 transition-colors duration-200 mb-4">';
            
            echo '<div class="space-y-4">';
            
            echo '<div class="mx-auto w-12 h-12 text-gray-400">';
            echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>';
            echo '</svg>';
            echo '</div>';
            
            echo '<div>';
            echo '<p class="text-lg font-medium text-gray-900">' . esc_html( $args['upload_text'] ) . '</p>';
            echo '<p class="text-sm text-gray-500 mt-1">' . esc_html( $args['upload_hint'] ) . '</p>';
            echo '</div>';
            
            echo '</div>';
            
            echo '</div>';
        }

        /**
         * 渲染图片网格
         *
         * @since 1.0
         *
         * @param array $image_ids 图片ID数组
         * @param array $args 字段配置参数
         */
        private function render_image_grid( $image_ids, $args ) {
            $grid_classes = array(
                'pili-gallery-grid',
                'grid',
                'gap-4',
                'grid-cols-' . $args['grid_columns']['sm'],
                'md:grid-cols-' . $args['grid_columns']['md'],
                'lg:grid-cols-' . $args['grid_columns']['lg'],
                'xl:grid-cols-' . $args['grid_columns']['xl']
            );

            if ( $args['enable_sorting'] ) {
                $grid_classes[] = 'pili-gallery-sortable';
            }

            echo '<div class="' . esc_attr( implode( ' ', $grid_classes ) ) . '">';

            self::prime_gallery_attachment_caches( $image_ids );

            foreach ( $image_ids as $image_id ) {
                $this->render_image_item( $image_id, $args );
            }

            echo '</div>';
        }

        /**
         * Batch-prime attachment posts + meta before per-id reads (avoid N+1).
         *
         * @param int[] $image_ids Attachment IDs.
         * @return void
         */
        public static function prime_gallery_attachment_caches( $image_ids ) {
            $ids = array();
            foreach ( (array) $image_ids as $id ) {
                $id = (int) $id;
                if ( $id > 0 ) {
                    $ids[] = $id;
                }
            }
            $ids = array_values( array_unique( $ids ) );
            if ( array() === $ids ) {
                return;
            }
            if ( function_exists( '_prime_post_caches' ) ) {
                _prime_post_caches( $ids, false, true );
            }
            if ( function_exists( 'update_meta_cache' ) ) {
                update_meta_cache( 'post', $ids );
            }
        }

        /**
         * 渲染单个图片项
         *
         * @since 1.0
         *
         * @param int   $image_id 图片ID
         * @param array $args 字段配置参数
         */
        private function render_image_item( $image_id, $args ) {
            $image = wp_get_attachment_image_src( $image_id, $args['preview_size'] );
            $image_full = wp_get_attachment_image_src( $image_id, 'full' );
            $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            $image_title = get_the_title( $image_id );

            if ( ! $image ) {
                return;
            }

            echo '<div class="pili-gallery-item group relative bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200" data-image-id="' . esc_attr( $image_id ) . '">';

            if ( $args['enable_batch'] ) {
                echo '<div class="pili-gallery-checkbox absolute top-2 left-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">';
                echo '<input type="checkbox" class="pili-gallery-item-checkbox w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-blue-500 focus:ring-2" value="' . esc_attr( $image_id ) . '">';
                echo '</div>';
            }

            echo '<div class="aspect-square relative overflow-hidden">';

            echo '<img src="' . esc_url( $image[0] ) . '" alt="' . esc_attr( $image_alt ) . '" class="absolute inset-0 w-full h-full object-cover" loading="lazy">';

            // 勿用 bg-black + bg-opacity-*：当前 XUN Tailwind 构建不含 bg-opacity 工具类，会整块实心黑盖住预览。
            echo '<div class="pili-gallery-item-veil absolute inset-0 bg-transparent transition-all duration-200 flex items-center justify-center pointer-events-none">';

            echo '<div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-2 pointer-events-auto">';

            if ( $args['enable_preview'] ) {
                echo '<button type="button" class="pili-gallery-preview-btn p-2 bg-white bg-opacity-90 rounded-full text-gray-700 hover:bg-opacity-100 transition-all duration-200" data-image-url="' . esc_url( $image_full[0] ) . '" data-image-title="' . esc_attr( $image_title ) . '">';
                echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
                echo '</svg>';
                echo '</button>';
            }

            echo '<button type="button" class="pili-gallery-remove-btn p-2 bg-red-500 bg-opacity-90 rounded-full text-white hover:bg-opacity-100 transition-all duration-200" data-image-id="' . esc_attr( $image_id ) . '">';
            echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
            echo '</svg>';
            echo '</button>';

            echo '</div>';

            echo '</div>';

            echo '</div>';

            echo '<div class="p-3">';
            echo '<p class="text-sm font-medium text-gray-900 truncate" title="' . esc_attr( $image_title ) . '">' . esc_html( $image_title ) . '</p>';
            echo '<p class="text-xs text-gray-500 mt-1">ID: ' . esc_html( $image_id ) . '</p>';
            echo '</div>';

            if ( $args['enable_sorting'] ) {
                echo '<div class="pili-gallery-drag-handle absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-move p-1 bg-white bg-opacity-90 rounded">';
                echo '<svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>';
                echo '</svg>';
                echo '</div>';
            }

            echo '</div>';
        }

        /**
         * 渲染空状态
         *
         * @since 1.0
         *
         * @param array $args 字段配置参数
         */
        private function render_empty_state( $args ) {
            echo '<div class="pili-gallery-empty-state text-center py-12">';

            echo '<div class="mx-auto w-16 h-16 text-gray-300 mb-4">';
            echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>';
            echo '</svg>';
            echo '</div>';

            echo '<p class="text-lg font-medium text-gray-900 mb-2">' . esc_html( $args['empty_gallery'] ) . '</p>';
            echo '<p class="text-sm text-gray-500 mb-6">' . esc_html( $args['empty_text'] ) . '</p>';

            echo '<button type="button" class="pili-gallery-add-btn inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>';
            echo '</svg>';
            echo esc_html( $args['add_title'] );
            echo '</button>';

            echo '</div>';
        }

        /**
         * 渲染JavaScript配置
         *
         * @since 1.0
         *
         * @param array $args 字段配置参数
         */
        private function render_script_config( $args ) {
            $config = array(
                'maxFiles'      => $args['max_files'],
                'minFiles'      => $args['min_files'],
                'allowedTypes'  => $args['allowed_types'],
                'maxFileSize'   => $args['max_file_size'],
                'enableSorting' => $args['enable_sorting'],
                'enableBatch'   => $args['enable_batch'],
                'enablePreview' => $args['enable_preview'],
                'previewSize'   => $args['preview_size'],
                'gridColumns'   => $args['grid_columns'],
                'messages'      => array(
                    'errorMaxFiles' => $args['error_max_files'],
                    'errorMinFiles' => $args['error_min_files'],
                    'errorFileType' => $args['error_file_type'],
                    'errorFileSize' => $args['error_file_size'],
                    'confirmClear'  => $args['confirm_clear'],
                    'confirmDelete' => $args['confirm_delete'],
                    'confirmRemove' => $args['confirm_remove'],
                    'batchToggle'   => $args['batch_toggle'],
                    'batchExit'     => $args['batch_exit'],
                )
            );

            echo '<script type="application/json" class="pili-gallery-config">' . wp_json_encode( $config ) . '</script>';
        }

        /**
         * 注册 gallery AJAX（按 ajax_ns；旧 action 短时双注册）。
         *
         * @param string $ajax_ns Ajax namespace.
         * @return void
         */
        public static function ensure_ajax( $ajax_ns = '' ) {
            static $registered = array();

            if ( ! is_string( $ajax_ns ) || '' === $ajax_ns ) {
                $ajax_ns = 'pili';
                if ( class_exists( '\PILI_Config', false ) ) {
                    $ajax_ns = (string) \PILI_Config::get( 'ajax_ns', 'pili' );
                }
            }
            $ajax_ns = sanitize_key( $ajax_ns );
            if ( '' === $ajax_ns ) {
                $ajax_ns = 'pili';
            }

            if ( empty( $registered[ $ajax_ns ] ) ) {
                $registered[ $ajax_ns ] = true;
                add_action( 'wp_ajax_' . $ajax_ns . '_get_gallery_images', array( __CLASS__, 'ajax_get_gallery_images' ) );
            }

            /**
             * Keep legacy action `pili_get_gallery_images` during migration (Batch C; remove later).
             *
             * @param bool $allow Allow legacy.
             */
            if ( (bool) apply_filters( 'pili_gallery_legacy_ajax_action', true ) && empty( $registered['__legacy_pili__'] ) ) {
                $registered['__legacy_pili__'] = true;
                add_action( 'wp_ajax_pili_get_gallery_images', array( __CLASS__, 'ajax_get_gallery_images' ) );
            }
        }

        /**
         * 初始化AJAX处理器
         *
         * @since 1.0
         */
        public static function init_ajax() {
            self::ensure_ajax();
        }

        /**
         * AJAX处理器：获取图片库图片数据
         *
         * @since 1.0
         */
        public static function ajax_get_gallery_images() {
            /**
             * Capability required for gallery image metadata AJAX.
             *
             * @param string $cap Capability.
             */
            $cap = (string) apply_filters( 'pili_gallery_ajax_capability', 'upload_files' );
            if ( '' === $cap || ! current_user_can( $cap ) ) {
                wp_send_json_error( array( 'message' => pili__( '权限不足' ) ) );
            }

            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
            $unique = '';
            if ( isset( $_POST['option_id'] ) ) {
                $unique = sanitize_key( wp_unslash( (string) $_POST['option_id'] ) );
            } elseif ( isset( $_POST['unique'] ) ) {
                $unique = sanitize_key( wp_unslash( (string) $_POST['unique'] ) );
            }

            $nonce_ok = false;
            if ( '' !== $unique && '' !== $nonce && wp_verify_nonce( $nonce, 'PILI_Options_' . $unique ) ) {
                $nonce_ok = true;
            } elseif (
                '' !== $nonce
                && (bool) apply_filters( 'pili_gallery_allow_legacy_nonce', false )
                && wp_verify_nonce( $nonce, 'pili_gallery_nonce' )
            ) {
                $nonce_ok = true;
            }

            if ( ! $nonce_ok ) {
                wp_send_json_error( array( 'message' => pili__( '安全校验失败，请刷新页面后重试' ) ) );
            }

            $image_ids = isset( $_POST['image_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['image_ids'] ) ) : array();
            $preview_size = isset( $_POST['preview_size'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['preview_size'] ) ) : 'thumbnail';

            if ( empty( $image_ids ) ) {
                wp_send_json_error( array( 'message' => pili__( '未提供图片 ID' ) ) );
            }

            self::prime_gallery_attachment_caches( $image_ids );

            $images_data = array();

            foreach ( $image_ids as $image_id ) {
                $image_data = self::get_image_data( $image_id, $preview_size );
                if ( $image_data ) {
                    $images_data[] = $image_data;
                }
            }

            wp_send_json_success( $images_data );
        }

        /**
         * 获取单张图片的数据
         *
         * @since 1.0
         *
         * @param int    $image_id 图片ID
         * @param string $preview_size 预览尺寸
         *
         * @return array|false 图片数据数组或false
         */
        public static function get_image_data( $image_id, $preview_size = 'thumbnail' ) {
            $image_id = intval( $image_id );

            if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) {
                return false;
            }

            $thumbnail = wp_get_attachment_image_src( $image_id, $preview_size );
            $full = wp_get_attachment_image_src( $image_id, 'full' );
            $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            $title = get_the_title( $image_id );

            if ( ! $thumbnail || ! $full ) {
                return false;
            }

            return array(
                'id'        => $image_id,
                'thumbnail' => esc_url( $thumbnail[0] ),
                'full'      => esc_url( $full[0] ),
                'alt'       => esc_attr( $alt ),
                'title'     => esc_html( $title ),
                'width'     => $thumbnail[1],
                'height'    => $thumbnail[2],
            );
        }

        /**
         * 加载字段资源
         *
         * @since 1.0
         */
        public function enqueue() {

            if ( ! did_action( 'wp_enqueue_media' ) ) {
                wp_enqueue_media();
            }

            $handle      = pili_asset_handle( 'field-gallery' );
            $gallery_js  = PILI_Setup::$dir . '/assets/js/fields/gallery.js';
            $gallery_ver = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '0.1.0-dev';
            if ( is_readable( $gallery_js ) ) {
                $gallery_ver .= '.' . (string) filemtime( $gallery_js );
            }
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/gallery.js',
                array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
                $gallery_ver,
                true
            );

            // 悬停遮罩不用 bg-opacity-*（构建产物里没有该类，会变成实心黑盖住缩略图）。
            wp_register_style( $handle, false, array(), PILI_CORE_VERSION );
            wp_enqueue_style( $handle );
            wp_add_inline_style(
                $handle,
                '.pili-gallery-item-veil{background-color:transparent!important}'
                . '.pili-gallery-item:hover .pili-gallery-item-veil{background-color:rgba(0,0,0,.3)!important}'
            );

            $ajax_ns = 'pili';
            if ( class_exists( '\PILI_Config', false ) ) {
                $ajax_ns = sanitize_key( (string) \PILI_Config::get( 'ajax_ns', 'pili' ) );
            }
            if ( '' === $ajax_ns ) {
                $ajax_ns = 'pili';
            }
            self::ensure_ajax( $ajax_ns );

            $unique = is_string( $this->unique ) ? sanitize_key( $this->unique ) : '';
            if ( '' === $unique && class_exists( '\PILI_Config', false ) ) {
                // Fallback: option id often equals createOptions key; Config may not store it — leave empty only if unknown.
                $unique = '';
            }

            pili_localize_bag( $handle, 'gallery', array(
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => '' !== $unique ? wp_create_nonce( 'PILI_Options_' . $unique ) : '',
                'optionId'        => $unique,
                'actionGetImages' => $ajax_ns . '_get_gallery_images',
                'wpVersion'       => get_bloginfo( 'version' ),
                'i18n'       => array(
                    'mediaTitle' => pili__( '选择图片' ),
                    'mediaButtonText' => pili__( '使用这些图片' ),
                    'mediaLibrary' => pili__( '媒体库' ),
                    'uploadFiles' => pili__( '上传文件' ),
                    'selectFiles' => pili__( '选择文件' ),
                    'editGallery' => pili__( '编辑图片库' ),
                    'insertGallery' => pili__( '插入图片库' ),
                    'updateGallery' => pili__( '更新图片库' ),
                    'loading' => pili__( '加载中…' ),
                    'error' => pili__( '错误' ),
                    'success' => pili__( '成功' ),
                    'confirmRemoveTitle' => pili__( '确认移除' ),
                    'remove' => pili__( '移除' ),
                    'cancel' => pili__( '取消' ),
                    'confirmClearTitle' => pili__( '确认清空' ),
                    'clear' => pili__( '清空' ),
                    'confirmDeleteTitle' => pili__( '确认删除' ),
                    'delete' => pili__( '删除' ),
                    'noticeTitle' => pili__( '提示' ),
                    'successTitle' => pili__( '成功' ),
                    'selectImagesFirst' => pili__( '请先选择要删除的图片。' ),
                    'galleryCleared' => pili__( '图片库已清空。' ),
                    'imageRemoved' => pili__( '图片已移除。' ),
                    'imagesDeleted' => pili__( '成功删除 %d 张图片。' ),
                    'galleryUpdated' => pili__( '图片库已更新，共 %d 张图片。' ),
                    'onlyAddMore' => pili__( '只能再添加 %1$d 张图片，已自动截取前 %2$d 张。' ),
                    'imagesAlreadyExist' => pili__( '所选图片已存在于图片库中。' ),
                    'maxFilesTrimmed' => pili__( '最多只能选择 %1$d 张图片，已自动截取前 %2$d 张。' ),
                    'batchToggle' => pili__( '批量操作' ),
                    'batchExit' => pili__( '退出批量' ),
                    'imageIdLabel' => pili__( '图片 ID: %d' ),
                    // 与字段 config.messages / galleryI18n 回落键对齐（缺键会显示中文 msgid）
                    'errorMaxFiles' => pili__( '最多只能上传 {max} 张图片' ),
                    'errorMinFiles' => pili__( '至少需要上传 {min} 张图片' ),
                    'errorFileType' => pili__( '不支持的文件类型' ),
                    'errorFileSize' => pili__( '文件大小超出限制' ),
                    'confirmClear' => pili__( '确定要清空全部吗？' ),
                    'confirmDelete' => pili__( '确定要删除此项吗？' ),
                    'confirmRemove' => pili__( '确定要移除此项吗？' ),
                ),
            ) );
        }
    }
}
