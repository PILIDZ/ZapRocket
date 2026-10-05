<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Date 字段类 - 组合式架构增强
 *
 * 保留所有日期选择器特有功能：日历弹窗、日期验证、格式化等
 * 基础文本输入功能由text字段处理
 *
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.1.0
 * @version 1.1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_date' ) ) {
    
    /**
     * PILI_Field_date 日期选择器字段类
     *
     * 采用组合式架构，保留所有日期选择器特有功能：
     * - 日历弹窗和交互
     * - 日期格式化和验证
     * - 日期范围限制
     * - 快速选择功能
     * - 键盘导航支持
     * - 无障碍访问优化
     *
     * 基础文本输入由text字段组件处理
     *
     * @since 1.1.0
     */
    class PILI_Field_date extends PILI_Fields {
        /**
         * 构造函数
         *
         * 初始化date字段实例。
         *
         * @since 1.1.0
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
         * 渲染日期选择器字段 - 组合式架构增强
         *
         * 保留所有日期选择器特有功能，基础文本输入改为组合式。
         *
         * @since 1.1.0
         */
        public function render() {
            $settings = $this->get_field_settings();
            echo $this->field_before();
            if ( ! empty( $this->field['date_range'] ) ) {
                $this->render_date_range( $settings );
            } else {
                $display_value = $this->format_display_value( $this->value, $settings );
                $this->render_single_date( $settings, $display_value );
            }
            echo $this->field_after();
        }
        /**
         * 渲染单个日期选择器
         * 
         * @since 1.0
         * 
         * @param array  $settings      字段设置
         * @param string $display_value 显示值
         */
        private function render_single_date( $settings, $display_value ) {
            $field_id = $this->field_name();
            $unique_id = 'pili-date-' . uniqid();
            $defer = ! empty( $settings['defer_picker'] );
            $time_only = ! empty( $settings['time_only'] );
            $wrap_class = 'pili-date-field-wrapper relative';
            if ( $time_only ) {
                $wrap_class .= ' pili-date-time-only';
            }
            $wrap_attrs = ' class="' . esc_attr( $wrap_class ) . '" data-settings="' . esc_attr( wp_json_encode( $settings ) ) . '"';
            if ( $defer ) {
                $wrap_attrs .= ' data-defer-picker="1"';
            }
            echo '<div' . $wrap_attrs . '>';
            echo '<input type="hidden" name="' . esc_attr( $field_id ) . '" value="' . esc_attr( $this->value ) . '" class="pili-date-value" />';
            echo '<div class="relative">';
            echo '<input type="text" ';
            echo 'id="' . esc_attr( $unique_id ) . '" ';
            echo 'class="pili-date-input grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-10 pl-3 text-left text-gray-900 ';
            echo 'sm:text-sm/6 touch-manipulation ';
            echo 'focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 transition-colors duration-200" ';
            echo 'value="' . esc_attr( $display_value ) . '" ';
            echo 'placeholder="' . esc_attr( $settings['placeholder'] ) . '" ';
            echo 'readonly ';
            echo $this->field_attributes() . ' />';
            echo '<button type="button" class="pili-date-trigger absolute right-2 top-1/2 -translate-y-1/2 flex items-center ';
            echo 'text-gray-400 hover:text-gray-600 transition-colors duration-200 focus:outline-none focus:text-indigo-600" ';
            echo 'aria-label="' . esc_attr( $time_only ? pili__( '打开时间选择器' ) : pili__( '打开日期选择器' ) ) . '">';
            if ( $time_only ) {
                echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
                echo '<circle cx="12" cy="12" r="9" stroke-width="2"></circle>';
                echo '<polyline points="12,7 12,12 15,14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>';
                echo '</svg>';
            } else {
                echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
                echo '<rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke-width="2"></rect>';
                echo '<line x1="16" y1="2" x2="16" y2="6" stroke-width="2"></line>';
                echo '<line x1="8" y1="2" x2="8" y2="6" stroke-width="2"></line>';
                echo '<line x1="3" y1="10" x2="21" y2="10" stroke-width="2"></line>';
                echo '</svg>';
            }
            echo '</button>';
            echo '</div>';
            echo '<div class="pili-date-picker absolute top-full left-0 mt-2 z-50 hidden ';
            echo 'bg-white border border-gray-200 rounded-lg shadow-xl min-w-80 max-w-sm ';
            echo 'transform opacity-0 scale-95 transition-all duration-200 ease-out';
            // portal 挂 body 后仍靠 picker 自身 class 隐藏日历（不只依赖 wrapper）。
            echo $time_only ? ' pili-date-time-only' : '';
            echo '">';
            // 表格等场景可 defer：首开时从原型克隆，避免每行塞满日历 DOM。
            if ( ! $defer ) {
                $this->render_date_picker_content();
            }
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染日期范围选择器
         * 
         * @since 1.0
         * 
         * @param array $settings 字段设置
         */
        private function render_date_range( $settings ) {
            $field_name = $this->field_name();
            $default_range = array(
                'from' => '',
                'to'   => '',
            );
            if ( ! is_array( $this->value ) ) {
                $range_value = $default_range;
            } else {
                $range_value = wp_parse_args( $this->value, $default_range );
            }
            $from_display = $this->format_display_value( $range_value['from'], $settings );
            $to_display = $this->format_display_value( $range_value['to'], $settings );
            echo '<div class="pili-date-range-wrapper" data-settings="' . esc_attr( json_encode( $settings ) ) . '">';
            echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            echo '<div class="pili-date-range-from">';
            echo '<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">';
            echo esc_html( $settings['text_from'] );
            echo '</label>';
            $this->render_range_input( $field_name . '[from]', $range_value['from'], $from_display, $settings, 'from' );
            echo '</div>';
            echo '<div class="pili-date-range-to">';
            echo '<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">';
            echo esc_html( $settings['text_to'] );
            echo '</label>';
            $this->render_range_input( $field_name . '[to]', $range_value['to'], $to_display, $settings, 'to' );
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染范围输入字段
         * 
         * @since 1.0
         * 
         * @param string $name         字段名称
         * @param string $value        字段值
         * @param string $display_value 显示值
         * @param array  $settings     设置
         * @param string $type         类型（from/to）
         */
        private function render_range_input( $name, $value, $display_value, $settings, $type ) {
            $unique_id = 'pili-date-range-' . $type . '-' . uniqid();
            echo '<div class="pili-date-field-wrapper relative" data-range-type="' . esc_attr( $type ) . '">';
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="pili-date-value" />';
            echo '<div class="relative">';
            echo '<input type="text" ';
            echo 'id="' . esc_attr( $unique_id ) . '" ';
            echo 'class="pili-date-input grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-10 pl-3 text-left text-gray-900 ';
            echo 'sm:text-sm/6 touch-manipulation ';
            echo 'focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 transition-colors duration-200" ';
            echo 'value="' . esc_attr( $display_value ) . '" ';
            echo 'placeholder="' . esc_attr( $settings['placeholder'] ) . '" ';
            echo 'readonly />';
            echo '<button type="button" class="pili-date-trigger absolute right-2 top-1/2 -translate-y-1/2 flex items-center ';
            echo 'text-gray-400 hover:text-gray-600 transition-colors duration-200 focus:outline-none focus:text-indigo-600" ';
            echo 'aria-label="' . pili_esc_attr__( '打开日期选择器' ) . '">';
            echo '<svg class="w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
            echo '<rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke-width="2"></rect>';
            echo '<line x1="16" y1="2" x2="16" y2="6" stroke-width="2"></line>';
            echo '<line x1="8" y1="2" x2="8" y2="6" stroke-width="2"></line>';
            echo '<line x1="3" y1="10" x2="21" y2="10" stroke-width="2"></line>';
            echo '</svg>';
            echo '</button>';
            echo '</div>';
            echo '<div class="pili-date-picker absolute top-full left-0 mt-2 z-50 hidden ';
            echo 'bg-white border border-gray-200 rounded-lg shadow-xl min-w-80 max-w-sm ';
            echo 'transform opacity-0 scale-95 transition-all duration-200 ease-out">';
            $this->render_date_picker_content();
            echo '</div>';
            echo '</div>';
        }
        /**
         * 渲染日期选择器内容
         *
         * @since 1.0
         */
        private function render_date_picker_content() {
            $settings = $this->get_field_settings();
            $time_only = ! empty( $settings['time_only'] );
            echo '<div class="pili-date-picker-content p-4">';
            // 仅时间：不输出日历壳，避免 portal/CSS 未命中时仍露出月历。
            if ( ! $time_only ) {
                echo '<div class="pili-date-header flex items-center justify-between mb-4">';
                echo '<button type="button" class="pili-date-prev-month p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="' . pili_esc_attr__( '上个月' ) . '">';
                echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
                echo '<polyline points="15,18 9,12 15,6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>';
                echo '</svg>';
                echo '</button>';
                echo '<div class="pili-date-title flex items-center space-x-2">';
                echo '<button type="button" class="pili-date-month-year px-3 py-1 text-sm font-medium rounded-lg hover:bg-gray-100 transition-colors">';
                echo '<span class="pili-current-month-year"></span>';
                echo '</button>';
                echo '</div>';
                echo '<button type="button" class="pili-date-next-month p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="' . pili_esc_attr__( '下个月' ) . '">';
                echo '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
                echo '<polyline points="9,18 15,12 9,6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>';
                echo '</svg>';
                echo '</button>';
                echo '</div>';
                echo '<div class="pili-date-weekdays grid grid-cols-7 gap-1 mb-2">';
                $weekdays = array(
                    pili__( '日' ),
                    pili__( '一' ),
                    pili__( '二' ),
                    pili__( '三' ),
                    pili__( '四' ),
                    pili__( '五' ),
                    pili__( '六' ),
                );
                foreach ( $weekdays as $day ) {
                    echo '<div class="text-center text-xs font-medium text-gray-500 py-2">' . esc_html( $day ) . '</div>';
                }
                echo '</div>';
                echo '<div class="pili-date-grid grid grid-cols-7 gap-1 mb-4"></div>';
            }
            if ( $settings['enable_time'] ) {
                $time_class = $time_only
                    ? 'pili-time-section'
                    : 'pili-time-section pt-3 border-t border-gray-200';
                echo '<div class="' . esc_attr( $time_class ) . '">';
                $this->render_time_picker( $settings );
                echo '</div>';
            }
            $show_footer = $settings['show_today'] || $settings['show_clear'];
            if ( $show_footer ) {
                echo '<div class="pili-date-footer flex items-center justify-between pt-3 border-t border-gray-200">';
                if ( $settings['show_today'] ) {
                    echo '<button type="button" class="pili-date-today px-3 py-1 text-sm text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">' . pili_esc_html__( '现在' ) . '</button>';
                } else {
                    echo '<div></div>';
                }
                if ( $settings['show_clear'] ) {
                    echo '<button type="button" class="pili-date-clear px-3 py-1 text-sm text-gray-500 hover:bg-gray-100 rounded-lg transition-colors">' . pili_esc_html__( '清除' ) . '</button>';
                } else {
                    echo '<div></div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        /**
         * 渲染时间选择器
         *
         * @since 1.0
         *
         * @param array $settings 字段设置
         */
        private function render_time_picker( $settings ) {
            echo '<div class="pili-time-picker-section">';
            echo '<div class="text-sm font-medium text-gray-700 mb-3">' . pili_esc_html__( '选择时间' ) . '</div>';
            echo '<div class="flex items-center space-x-2">';
            echo '<div class="flex-1">';
            echo '<input type="number" class="pili-time-hour block w-full rounded-md bg-white px-2 py-1 text-sm text-gray-900 ';
            echo 'outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 text-center" ';
            if ( $settings['time_format'] === '12' ) {
                echo 'min="1" max="12" placeholder="12" ';
            } else {
                echo 'min="0" max="23" placeholder="00" ';
            }
            echo 'value="' . ( $settings['time_format'] === '12' ? '12' : '00' ) . '" />';
            echo '<label class="block text-xs text-gray-500 mt-1 text-center">' . pili_esc_html__( '时' ) . '</label>';
            echo '</div>';
            echo '<div class="text-gray-400 text-sm">:</div>';
            echo '<div class="flex-1">';
            echo '<input type="number" class="pili-time-minute block w-full rounded-md bg-white px-2 py-1 text-sm text-gray-900 ';
            echo 'outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 text-center" ';
            echo 'min="0" max="59" placeholder="00" value="00" />';
            echo '<label class="block text-xs text-gray-500 mt-1 text-center">' . pili_esc_html__( '分' ) . '</label>';
            echo '</div>';
            if ( $settings['show_seconds'] ) {
                echo '<div class="text-gray-400 text-sm">:</div>';
                echo '<div class="flex-1">';
                echo '<input type="number" class="pili-time-second block w-full rounded-md bg-white px-2 py-1 text-sm text-gray-900 ';
                echo 'outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 text-center" ';
                echo 'min="0" max="59" placeholder="00" value="00" />';
                echo '<label class="block text-xs text-gray-500 mt-1 text-center">' . pili_esc_html__( '秒' ) . '</label>';
                echo '</div>';
            }
            if ( $settings['time_format'] === '12' ) {
                echo '<div class="flex-1">';
                echo '<select class="pili-time-ampm block w-full rounded-md bg-white px-2 py-1 text-sm text-gray-900 ';
                echo 'outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600">';
                echo '<option value="AM">' . pili_esc_html__( '上午' ) . '</option>';
                echo '<option value="PM">' . pili_esc_html__( '下午' ) . '</option>';
                echo '</select>';
                echo '<label class="block text-xs text-gray-500 mt-1 text-center">' . pili_esc_html__( '上/下午' ) . '</label>';
                echo '</div>';
            }
            echo '</div>';
            echo '<div class="mt-3 flex flex-wrap gap-2">';
            echo '<button type="button" class="pili-time-preset px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded transition-colors" data-time="09:00">09:00</button>';
            echo '<button type="button" class="pili-time-preset px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded transition-colors" data-time="12:00">12:00</button>';
            echo '<button type="button" class="pili-time-preset px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded transition-colors" data-time="18:00">18:00</button>';
            echo '<button type="button" class="pili-time-now px-2 py-1 text-xs bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded transition-colors">' . pili_esc_html__( '现在' ) . '</button>';
            echo '</div>';
            echo '</div>';
        }
        /**
         * 获取字段设置
         *
         * 合并默认设置和用户自定义设置。
         *
         * @since 1.0
         *
         * @return array 完整的字段设置
         */
        private function get_field_settings() {
            $default_settings = array(
                'date_format'    => 'Y-m-d',
                'display_format' => pili__( 'Y年m月d日' ),
                'placeholder' => pili__( '请选择…' ),
                'min_date'       => '',
                'max_date'       => '',
                'disabled_dates' => array(),
                'disabled_days'  => array(),
                'first_day'      => 1,
                'show_today'     => true,
                'show_clear'     => true,
                'auto_close'     => true,
                'text_from' => pili__( '开始日期' ),
                'text_to' => pili__( '结束日期' ),
                'locale'         => ( class_exists( 'Pilipost_I18n' ) ? self::js_date_locale() : 'zh-CN' ),
                'enable_time'    => false,
                'time_only'      => false,
                'time_format'    => '24',
                'show_seconds'   => false,
                'minute_step'    => 1,
                'second_step'    => 1,
                'default_time'   => '',
                'defer_picker'   => false,
            );
            $user_settings = ! empty( $this->field['settings'] ) ? $this->field['settings'] : array();
            $settings = wp_parse_args( $user_settings, $default_settings );
            if ( ! empty( $this->field['date_range'] ) ) {
                $range_settings = wp_parse_args( $this->field, array(
                    'text_from' => pili__( '开始日期' ),
                    'text_to' => pili__( '结束日期' ),
                ) );
                $settings['text_from'] = $range_settings['text_from'];
                $settings['text_to'] = $range_settings['text_to'];
            }
            // 仅时间：强制开时间区，默认存/显 H:i（可被 settings 覆盖）。
            if ( ! empty( $settings['time_only'] ) ) {
                $settings['time_only']   = true;
                $settings['enable_time'] = true;
                if ( ! isset( $user_settings['date_format'] ) ) {
                    $settings['date_format'] = 'H:i';
                }
                if ( ! isset( $user_settings['display_format'] ) ) {
                    $settings['display_format'] = 'H:i';
                }
                if ( ! isset( $user_settings['placeholder'] ) ) {
                    $settings['placeholder'] = pili__( '请选择时间' );
                }
            }
            return $settings;
        }
        /**
         * 格式化显示值
         *
         * 将存储格式的日期转换为显示格式。
         *
         * @since 1.0
         *
         * @param string $value    日期值
         * @param array  $settings 字段设置
         *
         * @return string 格式化后的显示值
         */
        private function format_display_value( $value, $settings ) {
            if ( empty( $value ) || ! is_string( $value ) ) {
                return '';
            }
            $value = trim( $value );

            // 仅时间：14:30 / 14:30:00
            if ( ! empty( $settings['time_only'] ) ) {
                if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
                    $out = sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
                    if ( ! empty( $settings['show_seconds'] ) ) {
                        $out .= sprintf( ':%02d', isset( $m[3] ) ? (int) $m[3] : 0 );
                    }
                    return $out;
                }
            }

            $date = \DateTime::createFromFormat( $settings['date_format'], $value );
            if ( false === $date ) {
                $common_formats = array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'H:i:s', 'H:i' );
                foreach ( $common_formats as $format ) {
                    $date = \DateTime::createFromFormat( $format, $value );
                    if ( false !== $date ) {
                        break;
                    }
                }
            }
            if ( false === $date ) {
                return $value;
            }
            $display = $date->format( $settings['display_format'] );
            // 日期+时间：显示格式默认不含时刻时，补上时分（与 JS updateDisplay 对齐）。
            if ( ! empty( $settings['enable_time'] ) && empty( $settings['time_only'] ) && false === strpos( $settings['display_format'], 'H' ) ) {
                $display .= ' ' . $date->format( ! empty( $settings['show_seconds'] ) ? 'H:i:s' : 'H:i' );
            }
            return $display;
        }
        /**
         * 加载字段资源 - 组合式架构增强
         *
         * 保留date字段特有的功能（日历弹窗、日期验证等），
         * 基础文本输入由text字段自动加载资源。
         *
         * @since 1.1.0
         */
        public function enqueue() {
            $handle        = pili_asset_handle( 'field-date' );
            $inline_handle = pili_asset_handle( 'field-date-inline' );
            $date_js       = PILI_Setup::$dir . '/assets/js/fields/date.js';
            $date_ver      = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : (string) PILI_Setup::$version;
            if ( is_readable( $date_js ) ) {
                $date_ver .= '.' . (string) filemtime( $date_js );
            }
            if ( is_readable( __FILE__ ) ) {
                $date_ver .= '.' . (string) filemtime( __FILE__ );
            }
            wp_enqueue_script(
                $handle,
                PILI_Setup::$url . '/assets/js/fields/date.js',
                array( 'jquery' ),
                $date_ver,
                true
            );
            $date_table_css = '.pili-table-grid td:has(.pili-table-cell-editor),.pili-table-grid td:has(.pili-date-field-wrapper){overflow:visible;position:relative;}'
                . '.pili-table-grid .pili-table-cell-editor{min-width:11rem;max-width:16rem;}'
                . '.pili-table-grid .pili-date-input{font-size:0.75rem;line-height:1.25rem;padding-top:0.375rem;padding-bottom:0.375rem;}'
                . '.pili-date-picker.pili-date-picker--portal{position:fixed;z-index:100050;}'
                . '.pili-date-time-only .pili-date-header,'
                . '.pili-date-time-only .pili-date-weekdays,'
                . '.pili-date-time-only .pili-date-grid,'
                . '.pili-date-picker.pili-date-time-only .pili-date-header,'
                . '.pili-date-picker.pili-date-time-only .pili-date-weekdays,'
                . '.pili-date-picker.pili-date-time-only .pili-date-grid{display:none!important;}'
                . '.pili-date-time-only .pili-time-section,'
                . '.pili-date-picker.pili-date-time-only .pili-time-section{border-top:0;padding-top:0;}'
                . '.pili-date-picker.pili-date-time-only{min-width:16rem;}';
            wp_register_style( $inline_handle, false, array(), $date_ver );
            wp_enqueue_style( $inline_handle );
            wp_add_inline_style( $inline_handle, $date_table_css );
            pili_localize_bag( $handle, 'dateL10n', array(
                'months' => array(
                    pili__( '一月' ), pili__( '二月' ), pili__( '三月' ),
                    pili__( '四月' ), pili__( '五月' ), pili__( '六月' ),
                    pili__( '七月' ), pili__( '八月' ), pili__( '九月' ),
                    pili__( '十月' ), pili__( '十一月' ), pili__( '十二月' ),
                ),
                'monthsShort' => array(
                    pili__( '1月' ), pili__( '2月' ), pili__( '3月' ),
                    pili__( '4月' ), pili__( '5月' ), pili__( '6月' ),
                    pili__( '7月' ), pili__( '8月' ), pili__( '9月' ),
                    pili__( '10月' ), pili__( '11月' ), pili__( '12月' ),
                ),
                'weekdays' => array(
                    pili__( '星期日' ), pili__( '星期一' ), pili__( '星期二' ),
                    pili__( '星期三' ), pili__( '星期四' ), pili__( '星期五' ),
                    pili__( '星期六' ),
                ),
                'weekdaysShort' => array(
                    pili__( '日' ), pili__( '一' ), pili__( '二' ),
                    pili__( '三' ), pili__( '四' ), pili__( '五' ),
                    pili__( '六' ),
                ),
                'today' => pili__( '今天' ),
                'now' => pili__( '现在' ),
                'clear' => pili__( '清除' ),
                'close' => pili__( '关闭弹窗' ),
                'prevMonth' => pili__( '上个月' ),
                'nextMonth' => pili__( '下个月' ),
                'selectMonth' => pili__( '选择月份' ),
                'selectYear' => pili__( '选择年份' ),
                'selectTime' => pili__( '选择时间' ),
                'placeholder' => pili__( '请选择日期' ),
                'displayFormat' => pili__( 'Y年m月d日' ),
                'locale' => self::js_date_locale(),
            ) );
            if ( ! has_action( 'admin_footer', array( __CLASS__, 'print_global_picker_prototype' ) ) ) {
                add_action( 'admin_footer', array( __CLASS__, 'print_global_picker_prototype' ) );
            }
        }

        /**
         * 全局日历原型：表格 defer_picker / editor=date 首开时克隆（含时间区）。
         *
         * @return void
         */
        public static function print_global_picker_prototype() {
            static $done = false;
            if ( $done || ! is_admin() ) {
                return;
            }
            $done = true;
            $inst = new self(
                array(
                    'id'       => '_xun_date_global_proto',
                    'type'     => 'date',
                    'settings' => array(
                        'enable_time'  => true,
                        'time_format'  => '24',
                        'show_seconds' => false,
                        'show_today'   => true,
                        'show_clear'   => false,
                        'auto_close'   => false,
                    ),
                ),
                '',
                '',
                '',
                ''
            );
            echo '<div id="pili-date-picker-prototype" hidden aria-hidden="true">';
            $inst->render_date_picker_content();
            echo '</div>';
        }

        /**
         * Flatpickr / Intl 风格 locale（随后台插件语言，非站点语言）。
         *
         * @return string
         */
        private static function js_date_locale() {
            if ( ! class_exists( '\Pilipost_I18n' ) ) {
                return 'zh-CN';
            }
            $loc = \Pilipost_I18n::resolve_locale();
            if ( 'zh_TW' === $loc || ( method_exists( '\Pilipost_I18n', 'is_traditional_chinese_locale' ) && \Pilipost_I18n::is_traditional_chinese_locale( $loc ) ) ) {
                return 'zh-TW';
            }
            if ( \Pilipost_I18n::is_chinese_locale( $loc ) || 'zh_CN' === $loc ) {
                return 'zh-CN';
            }
            if ( 0 === stripos( str_replace( '-', '_', $loc ), 'de' ) ) {
                return 'de-DE';
            }
            if ( 0 === stripos( str_replace( '-', '_', $loc ), 'fr' ) ) {
                return 'fr-FR';
            }
            return 'en-US';
        }
        /**
         * 验证日期字段值
         *
         * 验证和清理日期值，确保格式正确和在允许范围内。
         *
         * @since 1.0
         *
         * @param mixed $value 要验证的值
         *
         * @return mixed 验证后的值
         */
        public function validate( $value ) {
            if ( ! empty( $this->field['date_range'] ) && is_array( $value ) ) {
                return $this->validate_date_range( $value );
            }
            return $this->validate_single_date( $value );
        }
        /**
         * 验证单个日期
         *
         * @since 1.0
         *
         * @param mixed $value 日期值
         *
         * @return string 验证后的日期值
         */
        private function validate_single_date( $value ) {
            if ( empty( $value ) ) {
                return '';
            }
            $settings = $this->get_field_settings();
            $date = $this->parse_date( $value, $settings );
            if ( $date === false ) {
                return '';
            }
            if ( ! $this->is_date_in_range( $date, $settings ) ) {
                return '';
            }
            if ( $this->is_date_disabled( $date, $settings ) ) {
                return '';
            }
            return $date->format( $settings['date_format'] );
        }
        /**
         * 验证日期范围
         *
         * @since 1.0
         *
         * @param array $value 日期范围值
         *
         * @return array 验证后的日期范围值
         */
        private function validate_date_range( $value ) {
            $result = array(
                'from' => '',
                'to'   => '',
            );
            if ( ! is_array( $value ) ) {
                return $result;
            }
            $value = wp_parse_args( $value, $result );
            if ( ! empty( $value['from'] ) ) {
                $result['from'] = $this->validate_single_date( $value['from'] );
            }
            if ( ! empty( $value['to'] ) ) {
                $result['to'] = $this->validate_single_date( $value['to'] );
            }
            if ( ! empty( $result['from'] ) && ! empty( $result['to'] ) ) {
                $settings = $this->get_field_settings();
                $from_date = $this->parse_date( $result['from'], $settings );
                $to_date = $this->parse_date( $result['to'], $settings );
                if ( $from_date && $to_date && $from_date > $to_date ) {
                    $temp = $result['from'];
                    $result['from'] = $result['to'];
                    $result['to'] = $temp;
                }
            }
            return $result;
        }
        /**
         * 解析日期字符串
         *
         * @since 1.0
         *
         * @param string $value    日期字符串
         * @param array  $settings 字段设置
         *
         * @return DateTime|false 解析后的日期对象或false
         */
        private function parse_date( $value, $settings ) {
            if ( empty( $value ) ) {
                return false;
            }
            $date = \DateTime::createFromFormat( $settings['date_format'], $value );
            if ( $date !== false ) {
                return $date;
            }
            $common_formats = array( 'Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s' );
            foreach ( $common_formats as $format ) {
                $date = \DateTime::createFromFormat( $format, $value );
                if ( $date !== false ) {
                    return $date;
                }
            }
            $timestamp = strtotime( $value );
            if ( $timestamp !== false ) {
                return new \DateTime( '@' . $timestamp );
            }
            return false;
        }
        /**
         * 检查日期是否在允许范围内
         *
         * @since 1.0
         *
         * @param DateTime $date     要检查的日期
         * @param array    $settings 字段设置
         *
         * @return bool 是否在范围内
         */
        private function is_date_in_range( $date, $settings ) {
            if ( ! empty( $settings['min_date'] ) ) {
                $min_date = $this->parse_date( $settings['min_date'], $settings );
                if ( $min_date && $date < $min_date ) {
                    return false;
                }
            }
            if ( ! empty( $settings['max_date'] ) ) {
                $max_date = $this->parse_date( $settings['max_date'], $settings );
                if ( $max_date && $date > $max_date ) {
                    return false;
                }
            }
            return true;
        }
        /**
         * 检查日期是否被禁用
         *
         * @since 1.0
         *
         * @param DateTime $date     要检查的日期
         * @param array    $settings 字段设置
         *
         * @return bool 是否被禁用
         */
        private function is_date_disabled( $date, $settings ) {
            if ( ! empty( $settings['disabled_days'] ) && is_array( $settings['disabled_days'] ) ) {
                $day_of_week = (int) $date->format( 'w' );
                if ( in_array( $day_of_week, $settings['disabled_days'] ) ) {
                    return true;
                }
            }
            if ( ! empty( $settings['disabled_dates'] ) && is_array( $settings['disabled_dates'] ) ) {
                $date_string = $date->format( $settings['date_format'] );
                if ( in_array( $date_string, $settings['disabled_dates'] ) ) {
                    return true;
                }
            }
            return false;
        }
    }
}
