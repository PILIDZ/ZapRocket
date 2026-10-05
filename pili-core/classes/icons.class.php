<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 图标管理类
 * 
 * 这个类负责管理WordPress Dashicons图标的渲染功能。
 * 提供简单的API接口来在框架的各个部分使用图标。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Icons' ) ) {
    
    /**
     * PILI_Icons 图标管理类
     * 
     * 提供WordPress Dashicons图标的完整管理功能，包括：
     * - 图标列表管理
     * - 图标HTML生成
     * - 样式和属性自定义
     * - 错误处理和回退
     * 
     * @since 1.0
     */
    class PILI_Icons {
        
        /**
         * 图标缓存键前缀
         * 
         * @since 1.0
         * @var string
         */
        const CACHE_PREFIX = 'pili_dashicons_';
        
        /**
         * 缓存过期时间（秒）
         * 
         * @since 1.0
         * @var int
         */
        const CACHE_EXPIRY = 3600; // 1小时
        
        /**
         * Dashicons图标列表
         * 
         * @since 1.0
         * @var array
         */
        private static $dashicons = array(
            'admin-appearance',
            'admin-collapse',
            'admin-comments',
            'admin-customizer',
            'admin-generic',
            'admin-home',
            'admin-links',
            'admin-media',
            'admin-multisite',
            'admin-network',
            'admin-page',
            'admin-plugins',
            'admin-post',
            'admin-settings',
            'admin-site-alt',
            'admin-site-alt2',
            'admin-site-alt3',
            'admin-site',
            'admin-tools',
            'admin-users',
            'album',
            'align-center',
            'align-full-width',
            'align-left',
            'align-none',
            'align-pull-left',
            'align-pull-right',
            'align-right',
            'align-wide',
            'amazon',
            'analytics',
            'archive',
            'arrow-down-alt',
            'arrow-down-alt2',
            'arrow-down',
            'arrow-left-alt',
            'arrow-left-alt2',
            'arrow-left',
            'arrow-right-alt',
            'arrow-right-alt2',
            'arrow-right',
            'arrow-up-alt',
            'arrow-up-alt2',
            'arrow-up',
            'art',
            'awards',
            'backup',
            'beer',
            'bell',
            'block-default',
            'book-alt',
            'book',
            'buddypress',
            'building',
            'businessman',
            'businessperson',
            'businesswoman',
            'button',
            'calculator',
            'calendar-alt',
            'calendar',
            'camera-alt',
            'camera',
            'car',
            'carrot',
            'cart',
            'category',
            'chart-area',
            'chart-bar',
            'chart-line',
            'chart-pie',
            'clipboard',
            'clock',
            'cloud-saved',
            'cloud-upload',
            'cloud',
            'columns',
            'code-standards',
            'coffee',
            'color-picker',
            'columns',
            'dashboard',
            'database-add',
            'database-export',
            'database-import',
            'database-remove',
            'database-view',
            'database',
            'desktop',
            'dismiss',
            'download',
            'drumstick',
            'edit-large',
            'edit',
            'editor-aligncenter',
            'editor-alignleft',
            'editor-alignright',
            'editor-bold',
            'editor-break',
            'editor-code',
            'editor-contract',
            'editor-customchar',
            'editor-expand',
            'editor-help',
            'editor-indent',
            'editor-insertmore',
            'editor-italic',
            'editor-justify',
            'editor-kitchensink',
            'editor-ltr',
            'editor-ol-rtl',
            'editor-ol',
            'editor-outdent',
            'editor-paragraph',
            'editor-paste-text',
            'editor-paste-word',
            'editor-quote',
            'editor-removeformatting',
            'editor-rtl',
            'editor-spellcheck',
            'editor-strikethrough',
            'editor-table',
            'editor-textcolor',
            'editor-ul',
            'editor-underline',
            'editor-unlink',
            'editor-video',
            'email-alt',
            'email-alt2',
            'email',
            'embed-audio',
            'embed-generic',
            'embed-photo',
            'embed-post',
            'embed-video',
            'excerpt-view',
            'exit',
            'external',
            'facebook-alt',
            'facebook',
            'feedback',
            'filter',
            'flag',
            'food',
            'format-aside',
            'format-audio',
            'format-chat',
            'format-gallery',
            'format-image',
            'format-quote',
            'format-status',
            'format-video',
            'forms',
            'fullscreen-alt',
            'fullscreen-exit-alt',
            'games',
            'google',
            'grid-view',
            'groups',
            'hammer',
            'heading',
            'heart',
            'hidden',
            'hourglass',
            'html',
            'id-alt',
            'id',
            'image-crop',
            'image-filter',
            'image-flip-horizontal',
            'image-flip-vertical',
            'image-rotate-left',
            'image-rotate-right',
            'image-rotate',
            'images-alt',
            'images-alt2',
            'index-card',
            'info-outline',
            'info',
            'insert-after',
            'insert-before',
            'insert',
            'instagram',
            'keyboard-hide',
            'laptop',
            'layout',
            'leftright',
            'lightbulb',
            'list-view',
            'location-alt',
            'location',
            'lock-duplicate',
            'lock',
            'marker',
            'media-archive',
            'media-audio',
            'media-code',
            'media-default',
            'media-document',
            'media-interactive',
            'media-spreadsheet',
            'media-text',
            'media-video',
            'megaphone',
            'menu-alt',
            'menu-alt2',
            'menu-alt3',
            'menu',
            'microphone',
            'migrate',
            'minus',
            'money-alt',
            'move',
            'nametag',
            'networking',
            'no-alt',
            'no',
            'open-folder',
            'palmtree',
            'paperclip',
            'pdf',
            'performance',
            'pets',
            'phone',
            'pinterest',
            'playlist-audio',
            'playlist-video',
            'plus-alt',
            'plus-light',
            'plus',
            'portfolio',
            'post-status',
            'pressthis',
            'printer',
            'privacy',
            'products',
            'randomize',
            'redo',
            'remove',
            'rest-api',
            'rss',
            'saved',
            'schedule',
            'screenoptions',
            'search',
            'share-alt',
            'share-alt2',
            'share',
            'shield-alt',
            'shield',
            'shortcode',
            'slides',
            'smartphone',
            'smiley',
            'sort',
            'sos',
            'spotify',
            'star-empty',
            'star-filled',
            'star-half',
            'sticky',
            'store',
            'superhero-alt',
            'superhero',
            'table-col-after',
            'table-col-before',
            'table-col-delete',
            'table-row-after',
            'table-row-before',
            'table-row-delete',
            'tablet',
            'tag',
            'tagcloud',
            'testimonial',
            'text-page',
            'text',
            'thumbs-down',
            'thumbs-up',
            'tickets-alt',
            'tickets',
            'tide',
            'translation',
            'trash',
            'twitch',
            'twitter',
            'undo',
            'universal-access-alt',
            'universal-access',
            'unlock',
            'update-alt',
            'update',
            'upload',
            'vault',
            'video-alt',
            'video-alt2',
            'video-alt3',
            'visibility',
            'warning',
            'welcome-add-page',
            'welcome-comments',
            'welcome-learn-more',
            'welcome-view-site',
            'welcome-widgets-menus',
            'welcome-write-blog',
            'whatsapp',
            'wordpress-alt',
            'wordpress',
            'xing',
            'yes-alt',
            'yes',
            'youtube'
        );
        
        /**
         * 获取图标HTML
         * 
         * 根据图标名称获取对应的Dashicons HTML代码。
         * 
         * @since 1.0
         * 
         * @param string $name       图标名称（如：admin-users）
         * @param array  $attributes 图标属性配置
         * 
         * @return string 图标HTML代码
         */
        public static function get_icon( $name, $attributes = array() ) {
            if ( empty( $name ) || ! is_string( $name ) ) {
                return self::get_fallback_icon( pili__( '图标' ) );
            }
            $defaults = array(
                'class'  => '',
                'size'   => '24',
                'color'  => '',
                'title'  => '',
            );
            $attributes = wp_parse_args( $attributes, $defaults );
            if ( ! self::icon_exists( $name ) ) {
                return self::get_fallback_icon(
                    sprintf(
                        /* translators: %s: icon name */
                        pili__( "图标 '%s' 未找到" ),
                        $name
                    )
                );
            }
            return self::generate_dashicon_html( $name, $attributes );
        }
        
        /**
         * 生成Dashicon HTML
         * 
         * 生成Dashicon图标的HTML代码。
         * 
         * @since 1.0
         * 
         * @param string $name       图标名称
         * @param array  $attributes 图标属性配置
         * 
         * @return string 图标HTML代码
         */
        private static function generate_dashicon_html( $name, $attributes ) {
            $classes = 'dashicons dashicons-' . $name;
            if ( ! empty( $attributes['class'] ) ) {
                $classes .= ' ' . $attributes['class'];
            }
            $style = '';
            if ( ! empty( $attributes['color'] ) ) {
                $style .= 'color: ' . esc_attr( $attributes['color'] ) . ';';
            }
            if ( ! empty( $attributes['size'] ) ) {
                $style .= 'font-size: ' . esc_attr( $attributes['size'] ) . 'px;';
            }
            $style_attr = ! empty( $style ) ? ' style="' . $style . '"' : '';
            $title_attr = ! empty( $attributes['title'] ) ? ' title="' . esc_attr( $attributes['title'] ) . '"' : '';
            return '<span class="' . esc_attr( $classes ) . '"' . $style_attr . $title_attr . '></span>';
        }
        
        /**
         * 获取回退图标
         * 
         * 当请求的图标不存在时，返回一个默认的回退图标。
         * 
         * @since 1.0
         * 
         * @param string $error_message 错误信息
         * 
         * @return string 回退图标HTML
         */
        private static function get_fallback_icon( $error_message = '' ) {
            return '<span class="dashicons dashicons-warning" title="' . esc_attr( $error_message ) . '"></span>';
        }
        
        /**
         * 获取可用图标列表
         *
         * 返回所有可用的Dashicons图标名称列表。
         *
         * @since 1.0
         *
         * @return array 图标名称数组
         */
        public static function get_available_icons() {
            $cache_key = self::CACHE_PREFIX . 'list';
            $cached_list = get_transient( $cache_key );
            if ( $cached_list !== false ) {
                return $cached_list;
            }
            set_transient( $cache_key, self::$dashicons, self::CACHE_EXPIRY );
            return self::$dashicons;
        }
        
        /**
         * 检查图标是否存在
         *
         * 检查指定的图标是否存在于Dashicons中。
         *
         * @since 1.0
         *
         * @param string $name 图标名称
         *
         * @return bool 图标是否存在
         */
        public static function icon_exists( $name ) {
            if ( empty( $name ) ) {
                return false;
            }
            return in_array( $name, self::$dashicons );
        }
        
        /**
         * 清除图标缓存
         *
         * 清除所有图标相关的缓存数据。
         *
         * @since 1.0
         *
         * @return bool 是否清除成功
         */
        public static function clear_cache() {
            global $wpdb;
            $prefix = self::CACHE_PREFIX;
            $sql = $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $prefix . '%',
                '_transient_timeout_' . $prefix . '%'
            );
            $result = $wpdb->query( $sql );
            wp_cache_flush();
            return $result !== false;
        }
        
        /**
         * 搜索图标
         *
         * 根据关键词搜索匹配的图标名称。
         *
         * @since 1.0
         *
         * @param string $keyword 搜索关键词
         *
         * @return array 匹配的图标名称数组
         */
        public static function search_icons( $keyword ) {
            if ( empty( $keyword ) ) {
                return array();
            }
            $all_icons = self::get_available_icons();
            $matched_icons = array();
            $keyword = strtolower( $keyword );
            foreach ( $all_icons as $icon_name ) {
                if ( strpos( strtolower( $icon_name ), $keyword ) !== false ) {
                    $matched_icons[] = $icon_name;
                }
            }
            return $matched_icons;
        }
    }
}
