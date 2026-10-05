<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Background 字段类
 *
 * 提供完整的背景设置功能，包括颜色、图片、位置等所有背景相关属性。
 *
 * @package Xun Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_background' ) ) {

    /**
     * PILI_Field_background 背景字段类
     *
     * 继承自PILI_Fields基类，提供完整的背景设置功能。
     * 支持背景颜色、图片、位置、重复、尺寸等所有CSS背景属性。
     *
     * @since 1.0
     */
    class PILI_Field_background extends PILI_Fields {

        /**
         * 构造函数
         *
         * 初始化background字段实例。
         *
         * @since 1.0
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
         * 渲染背景字段
         * 
         * 输出背景设置字段的HTML代码。
         * 
         * @since 1.0
         */
        public function render() {

            $args = wp_parse_args( $this->field, array(
                'background_color'    => true,
                'background_image'    => true,
                'background_position' => true,
                'background_repeat'   => true,
                'background_size'     => true,
                'background_attachment' => false,
            ) );

            $default_value = array(
                'background-color'    => '',
                'background-image'    => '',
                'background-position' => '',
                'background-repeat'   => '',
                'background-size'     => '',
                'background-attachment' => '',
            );

            $default_value = ( ! empty( $this->field['default'] ) ) ? wp_parse_args( $this->field['default'], $default_value ) : $default_value;
            $this->value = wp_parse_args( $this->value, $default_value );

            echo $this->field_before();

            echo '<div>';

            if ( ! empty( $title ) ) {
                echo '<label class="block text-sm/6 font-medium text-gray-900 mb-4">';
                echo esc_html( $title );
                if ( $this->is_required() ) {
                    echo ' <span class="text-red-500">*</span>';
                }
                echo '</label>';
            }

            echo '<div class="space-y-6 p-6 bg-gray-50 rounded-lg border border-gray-200">';

            echo '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">';

            if ( $args['background_color'] ) {
                echo '<div>';
                $this->render_color_field();
                echo '</div>';
            }

            if ( $args['background_image'] ) {
                echo '<div>';
                $this->render_image_field();
                echo '</div>';
            }

            echo '</div>';

            echo '<div class="pili-bg-attributes space-y-4">';
            
            if ( $args['background_position'] ) {
                $this->render_position_field();
            }

            if ( $args['background_repeat'] ) {
                $this->render_repeat_field();
            }

            if ( $args['background_size'] ) {
                $this->render_size_field();
            }

            if ( $args['background_attachment'] ) {
                $this->render_attachment_field();
            }

            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo $this->field_after();
        }

        /**
         * 渲染背景颜色字段 
         */
        private function render_color_field() {
            $current_color = ! empty( $this->value['background-color'] ) ? $this->value['background-color'] : '#ffffff';

            echo '<div>';
            echo '<label class="block text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '背景颜色' ) . '</label>';

            $color_field = array(
                'id'      => 'background-color',
                'type'    => 'color',
                'title'   => '',
                'default' => '#ffffff',
                'alpha'   => false,
                'palette' => array(
                    '#ffffff', '#000000', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
                    '#f8fafc', '#e2e8f0'
                ),
                'class'   => 'w-full',
                'name'    => $this->field_name( '[background-color]' ),
            );

            $color_value = (string) $current_color;

            $color_unique = $this->unique . '[' . $this->field['id'] . ']';

            PILI::field( $color_field, $color_value, $color_unique, 'background', $this->field['id'] );

            echo '</div>';
        }

        /**
         * 渲染背景图片字段
         */
        private function render_image_field() {
            echo '<div>';
            echo '<label class="block text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '背景图片' ) . '</label>';

            $media_value = array();
            if ( ! empty( $this->value['background-image'] ) ) {
                $media_value = array(
                    'url' => $this->value['background-image']
                );
            }

            $field_id = $this->field_id() . '_background_image';
            $has_media = ! empty( $this->value['background-image'] );

            echo '<div class="pili-media-field" data-field-id="' . esc_attr( $field_id ) . '" data-library="image" data-preview-size="medium" data-multiple="false">';

            if ( $has_media ) {
                echo '<div class="pili-media-preview relative group bg-gray-50 border-2 border-dashed border-gray-200 rounded-lg overflow-hidden transition-all duration-200 hover:border-gray-300 mb-4" style="max-width: 64px; max-height: 64px;">';
                echo '<img src="' . esc_url( $this->value['background-image'] ) . '" alt="" class="w-full h-full object-cover" />';
                echo '<button type="button" class="pili-media-remove absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" title="' . pili_esc_attr__( '移除背景图片' ) . '">';
                echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                echo '</button>';
                echo '</div>';
            }

            echo '<div class="pili-media-controls">';
            echo '<div class="mb-3">';
            echo '<input type="text" name="' . esc_attr( $this->field_name( '[background-image]' ) ) . '" value="' . esc_attr( $this->value['background-image'] ) . '" class="pili-media-url w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="' . pili_esc_attr__( '未选择背景图片' ) . '" readonly />';
            echo '</div>';
            echo '<div class="flex flex-wrap gap-2">';
            echo '<button type="button" class="pili-media-button inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
            echo pili_esc_html__( '选择背景图片' );
            echo '</button>';
            echo '</div>';
            echo '</div>';

            $hidden_fields = array( 'id', 'filename', 'filesize', 'width', 'height', 'thumbnail', 'alt', 'title', 'description', 'mime_type' );
            foreach ( $hidden_fields as $field ) {
                $field_value = isset( $media_value[ $field ] ) ? $media_value[ $field ] : '';
                echo '<input type="hidden" name="' . esc_attr( $this->field_name( '[background-image-' . $field . ']' ) ) . '" value="' . esc_attr( $field_value ) . '" class="pili-media-' . esc_attr( $field ) . '" />';
            }

            echo '</div>';
            echo '</div>';
        }

        /**
         * 渲染背景位置字段
         */
        private function render_position_field() {
            $positions = array(
                ''              => pili__( '选择位置' ),
                'left top'      => pili__( '左上' ),
                'center top'    => pili__( '中上' ),
                'right top'     => pili__( '右上' ),
                'left center'   => pili__( '左中' ),
                'center center' => pili__( '居中' ),
                'right center'  => pili__( '右中' ),
                'left bottom'   => pili__( '左下' ),
                'center bottom' => pili__( '中下' ),
                'right bottom'  => pili__( '右下' ),
            );

            echo '<div>';
            echo '<label class="block text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '背景位置' ) . '</label>';

            $select_unique = $this->unique . '[' . $this->field['id'] . ']';
            $field = array(
                'id'          => 'background-position',
                'type'        => 'select',
                'title'       => '',
                'placeholder' => pili__( '请选择…' ),
                'options'     => $positions,
                'clearable'   => true,
                'searchable'  => false,
                'name'        => $this->field_name( '[background-position]' ),
            );
            $current = isset( $this->value['background-position'] ) ? $this->value['background-position'] : '';
            PILI::field( $field, $current, $select_unique, 'background', $this->field['id'] );

            echo '</div>';
        }

        /**
         * 渲染背景重复字段
         */
        private function render_repeat_field() {
            $repeats = array(
                ''          => pili__( '选择重复方式' ),
                'no-repeat' => pili__( '不重复' ),
                'repeat'    => pili__( '重复' ),
                'repeat-x'  => pili__( '水平重复' ),
                'repeat-y'  => pili__( '垂直重复' ),
            );

            echo '<div>';
            echo '<label class="block text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '背景重复' ) . '</label>';

            $select_unique = $this->unique . '[' . $this->field['id'] . ']';
            $field = array(
                'id'          => 'background-repeat',
                'type'        => 'select',
                'title'       => '',
                'placeholder' => pili__( '请选择…' ),
                'options'     => $repeats,
                'clearable'   => true,
                'searchable'  => false,
                'name'        => $this->field_name( '[background-repeat]' ),
            );
            $current = isset( $this->value['background-repeat'] ) ? $this->value['background-repeat'] : '';
            PILI::field( $field, $current, $select_unique, 'background', $this->field['id'] );

            echo '</div>';
        }

        /**
         * 渲染背景尺寸字段
         */
        private function render_size_field() {
            $sizes = array(
                ''        => pili__( '选择尺寸' ),
                'auto'    => pili__( '自动' ),
                'cover'   => pili__( '覆盖' ),
                'contain' => pili__( '包含' ),
            );

            echo '<div>';
            echo '<label class="block text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '背景尺寸' ) . '</label>';

            $select_unique = $this->unique . '[' . $this->field['id'] . ']';
            $field = array(
                'id'          => 'background-size',
                'type'        => 'select',
                'title'       => '',
                'placeholder' => pili__( '请选择…' ),
                'options'     => $sizes,
                'clearable'   => true,
                'searchable'  => false,
                'name'        => $this->field_name( '[background-size]' ),
            );
            $current = isset( $this->value['background-size'] ) ? $this->value['background-size'] : '';
            PILI::field( $field, $current, $select_unique, 'background', $this->field['id'] );

            echo '</div>';
        }



        /**
         * 渲染背景附着字段
         */
        private function render_attachment_field() {
            $attachments = array(
                ''       => pili__( '选择附着方式' ),
                'scroll' => pili__( '滚动' ),
                'fixed'  => pili__( '固定' ),
            );

            echo '<div>';
            echo '<label class="block text-sm font-medium text-gray-700 mb-2">' . pili_esc_html__( '背景附着' ) . '</label>';

            $select_unique = $this->unique . '[' . $this->field['id'] . ']';
            $field = array(
                'id'          => 'background-attachment',
                'type'        => 'select',
                'title'       => '',
                'placeholder' => pili__( '请选择…' ),
                'options'     => $attachments,
                'clearable'   => true,
                'searchable'  => false,
                'name'        => $this->field_name( '[background-attachment]' ),
            );
            $current = isset( $this->value['background-attachment'] ) ? $this->value['background-attachment'] : '';
            PILI::field( $field, $current, $select_unique, 'background', $this->field['id'] );

            echo '</div>';
        }

        /**
         * 加载字段资源
         */
        public function enqueue() {
            if ( ! did_action( 'wp_enqueue_media' ) ) {
                wp_enqueue_media();
            }

            if ( class_exists( __NAMESPACE__ . '\PILI_Field_color' ) ) {
                $color_stub = new PILI_Field_color(
                    array(
                        'id'   => $this->field['id'] . '_bg_color',
                        'type' => 'color',
                    ),
                    '',
                    $this->unique,
                    '',
                    $this->field['id']
                );
                $color_stub->enqueue();
            }

            $media_handle = pili_asset_handle( 'field-media' );
            $color_handle = pili_asset_handle( 'field-color' );
            $handle       = pili_asset_handle( 'field-background' );
            wp_enqueue_script(
                $media_handle,
                PILI_Setup::$url . '/assets/js/fields/media.js',
                array( 'jquery', 'media-upload', 'media-views' ),
                PILI_CORE_VERSION,
                true
            );

            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/background.js',
                array( 'jquery', $color_handle, $media_handle ),
                PILI_CORE_VERSION,
                true
            );

            pili_localize_bag( $handle, 'background',
                array(
                    'strings' => array(
                        'mediaUnavailable' => pili__( '媒体库不可用，请刷新页面重试' ),
                        'selectImageTitle' => pili__( '选择背景图片' ),
                        'selectImageBtn'   => pili__( '选择图片' ),
                        'replaceImage'     => pili__( '更换图片' ),
                        'remove' => pili__( '移除' ),
                    ),
                )
            );
        }

        /**
         * 检查字段是否为必填
         */
        public function is_required() {
            return ! empty( $this->field['required'] ) && $this->field['required'] === true;
        }
    }
}
