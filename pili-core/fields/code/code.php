<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
  * Xun Framework 代码字段类型
  *
  * 这个类实现了简洁的代码编辑字段功能。
  *
  * @package Xun Framework
  * @author  June
  * @link    https://www.xuntheme.com
  * @since   1.0
  * @version 1.0
  */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_code' ) ) {

    /**
     * PILI_Field_code 代码字段类
     *
     * 提供简洁的代码编辑功能，包括：
     * - 等宽字体显示
     * - 语法提示
     * - 行号显示
     * - 代码格式化
     * - 全屏编辑
     * - 主题切换
     *
     * @since 1.0
     */
    class PILI_Field_code extends PILI_Fields {

        /**
         * 构造函数
         *
         * 初始化代码字段实例。
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
         * 渲染代码字段
         *
         * 输出现代化的代码编辑字段HTML。
         *
         * @since 1.0
         */
        public function render() {

            $args = wp_parse_args( $this->field, array(
                'language'        => 'html',              // 编程语言类型
                'theme'           => 'light',             // 主题: light, dark
                'height'          => 300,                 // 编辑器高度（像素）
                'show_line_numbers' => true,              // 是否显示行号
                'tab_size'        => 2,                   // Tab缩进大小
                'placeholder' => pili__( '请选择…' ),
                'readonly'        => false,               // 是否只读
                'fullscreen'      => true,                // 是否支持全屏
                'format_button'   => true,                // 是否显示格式化按钮
                'copy_button'     => true,                // 是否显示复制按钮
                'word_wrap'       => true,                // 是否自动换行
                'font_size'       => 14,                  // 字体大小
            ) );

            $field_id = $this->field_id();
            $textarea_id = $field_id . '_textarea';

            $theme_class = $args['theme'] === 'dark' ? 'bg-gray-900 text-green-400' : 'bg-gray-50 text-gray-900';
            $border_class = $args['theme'] === 'dark' ? 'border-gray-700' : 'border-gray-300';

            echo $this->field_before();

            echo '<div class="pili-code-field" data-field-id="' . esc_attr( $field_id ) . '" data-language="' . esc_attr( $args['language'] ) . '" data-theme="' . esc_attr( $args['theme'] ) . '">';

            echo '<div class="pili-code-inner h-full min-h-0 flex flex-col">';

            if ( $args['format_button'] || $args['copy_button'] || $args['fullscreen'] ) {
                echo '<div class="pili-code-header-wrapper flex-shrink-0">';
                $this->render_toolbar( $args, $field_id );
                echo '</div>';
            }

            echo '<div class="pili-code-content flex-1 overflow-hidden">';
            $this->render_editor( $args, $textarea_id );
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo $this->field_after();
        }

        /**
         * 渲染工具栏
         *
         * @since 1.0
         *
         * @param array  $args     字段配置参数
         * @param string $field_id 字段ID
         */
        private function render_toolbar( $args, $field_id ) {

            echo '<div class="pili-code-toolbar flex items-center justify-between p-3 bg-gray-100 border border-b-0 border-gray-300 rounded-t-md">';

            echo '<div class="flex items-center space-x-2">';
            echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">';
            echo esc_html( strtoupper( $args['language'] ) );
            echo '</span>';

            if ( $args['show_line_numbers'] ) {
                echo '<span class="text-xs text-gray-500">' . pili_esc_html__( '行号' ) . '</span>';
            }
            echo '</div>';

            echo '<div class="flex items-center space-x-2">';

            if ( $args['copy_button'] ) {
                echo '<button type="button" class="pili-code-copy inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" title="' . pili_esc_attr__( '复制代码' ) . '">';
                echo '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>';
                echo '</svg>';
                echo pili_esc_html__( '复制' );
                echo '</button>';
            }

            if ( $args['format_button'] ) {
                echo '<button type="button" class="pili-code-format inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" title="' . pili_esc_attr__( '格式化代码' ) . '">';
                echo '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>';
                echo '</svg>';
                echo pili_esc_html__( '格式化' );
                echo '</button>';
            }

            if ( $args['fullscreen'] ) {
                echo '<button type="button" class="pili-code-fullscreen inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" title="' . pili_esc_attr__( '全屏编辑' ) . '">';
                echo '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>';
                echo '</svg>';
                echo pili_esc_html__( '全屏' );
                echo '</button>';
            }

            echo '</div>';
            echo '</div>';
        }

        /**
         * 渲染编辑器区域
         *
         * @since 1.0
         *
         * @param array  $args        字段配置参数
         * @param string $textarea_id textarea元素ID
         */
        private function render_editor( $args, $textarea_id ) {

            $theme_class = $args['theme'] === 'dark'
                ? 'bg-gray-900 text-green-400 border-gray-700'
                : 'bg-gray-50 text-gray-900 border-gray-300';

            $height_style = 'height: ' . intval( $args['height'] ) . 'px;';

            $font_size_style = 'font-size: ' . intval( $args['font_size'] ) . 'px;';

            $tab_size_style = 'tab-size: ' . intval( $args['tab_size'] ) . ';';

            echo '<div class="pili-code-editor-container flex h-full" style="' . esc_attr( $height_style ) . '" data-normal-height="' . esc_attr( $height_style ) . '">';

            if ( $args['show_line_numbers'] ) {
                echo '<div class="pili-code-line-numbers flex-shrink-0 w-12 ' . esc_attr( $theme_class ) . ' border-r text-xs leading-5 text-center py-3 select-none overflow-hidden" style="' . esc_attr( $font_size_style ) . '">';
                echo '<div class="pili-line-numbers-content"></div>';
                echo '</div>';
            }

            echo '<div class="flex-1 relative">';
            echo '<textarea ';
            echo 'id="' . esc_attr( $textarea_id ) . '" ';
            echo 'name="' . esc_attr( $this->field_name() ) . '" ';
            echo 'class="pili-code-textarea block w-full h-full px-4 py-3 font-mono text-sm leading-5 resize-none border-0 focus:outline-none focus:ring-0 ' . esc_attr( $theme_class ) . '" ';
            echo 'style="' . esc_attr( $font_size_style . $tab_size_style ) . '" ';
            echo 'placeholder="' . esc_attr( $args['placeholder'] ) . '" ';
            echo 'spellcheck="false" ';
            echo 'autocomplete="off" ';
            echo 'autocorrect="off" ';
            echo 'autocapitalize="off" ';
            echo 'data-language="' . esc_attr( $args['language'] ) . '" ';
            echo 'data-tab-size="' . esc_attr( $args['tab_size'] ) . '" ';
            echo 'data-word-wrap="' . ( $args['word_wrap'] ? 'true' : 'false' ) . '" ';
            if ( $args['readonly'] ) {
                echo 'readonly ';
            }
            echo $this->field_attributes();
            echo '>';
            echo esc_textarea( $this->value );
            echo '</textarea>';
            echo '</div>';

            echo '</div>';
        }

        /**
         * 加载字段资源
         *
         * 加载代码字段所需的CSS和JavaScript文件。
         *
         * @since 1.0
         */
        public function enqueue() {
            $handle = pili_asset_handle( 'field-code' );

            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/code.js',
                array( 'jquery' ),
                PILI_CORE_VERSION,
                true
            );

            pili_localize_bag( $handle, 'code', array(
                'copy_success'    => pili__( '代码已复制到剪贴板' ),
                'copy_error'      => pili__( '复制失败，请手动复制' ),
                'format_success'  => pili__( '代码格式化完成' ),
                'format_error'    => pili__( '格式化失败' ),
                'fullscreen_enter' => pili__( '进入全屏' ),
                'fullscreen_exit'  => pili__( '退出全屏' ),
            ) );
        }
    }
}