<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 核心设置类
 * 
 * 这个类负责框架的初始化、配置管理和核心功能的设置。
 * 它是整个框架的入口点，管理所有其他组件的加载和初始化。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
*/
    /**
     * PILI_Setup 类
     * 
     * 框架的主要设置和初始化类，负责：
     * - 框架常量定义
     * - 文件包含管理
     * - 钩子注册
     * - 实例管理
     * 
     * @since 1.0
     */
    class PILI_Setup {
        
        /**
         * 框架版本号
         * 
         * @since 1.0
         * @var string
         */
        public static $version = '1.0';
        
        /**
         * 框架主文件路径
         * 
         * @since 1.0
         * @var string
         */
        public static $file = '';
        
        /**
         * 框架目录路径
         * 
         * @since 1.0
         * @var string
         */
        public static $dir = '';
        
        /**
         * 框架URL路径
         *
         * @since 1.0
         * @var string
         */
        public static $url = '';

        /**
         * 已注册的框架页面
         *
         * @since 1.0
         * @var array
         */
        public static $registered_pages = array();

        /**
         * 是否为高级版本
         * 
         * @since 1.0
         * @var bool
         */
        public static $premium = true;
        
        /**
         * 存储框架参数的数组
         * 
         * @since 1.0
         * @var array
         */
        public static $args = array(
            'admin_options'     => array(), // 后台选项配置
            'metabox_options'   => array(), // 元数据框选项配置
            'customize_options' => array(), // 自定义器选项配置
            'sections'          => array(), // 区块配置
        );
        
        /**
         * 已初始化的实例数组
         * 
         * @since 1.0
         * @var array
         */
        public static $inited = array();
        
        /**
         * 字段类型数组
         * 
         * @since 1.0
         * @var array
         */
        public static $fields = array();
        
        /**
         * 单例实例
         * 
         * @since 1.0
         * @var PILI_Setup|null
         */
        private static $instance = null;
        
        /**
         * 初始化框架
         * 
         * 这是框架的主要入口点，负责设置所有必要的常量、
         * 包含文件和初始化核心功能。
         * 
         * @since 1.0
         * 
         * @param string $file    框架主文件路径
         * @param bool   $premium 是否为高级版本
         * 
         * @return PILI_Setup 返回设置类实例
         */
        public static function init( $file = __FILE__, $premium = true ) {
            self::$file = $file;
            self::$premium = $premium;
            self::constants();
            self::includes();
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        /**
         * 构造函数
         * 
         * 初始化框架的核心功能，注册必要的WordPress钩子。
         * 
         * @since 1.0
         */
        public function __construct() {
            do_action( 'pili_init' );
            self::textdomain();
            add_action( 'after_setup_theme', [ PILI::class, 'setup' ]);
            add_action( 'init', [ PILI::class, 'setup' ]);
            add_action( 'switch_theme', [ PILI::class, 'setup' ]);
            add_action( 'admin_enqueue_scripts', [ PILI::class, 'add_admin_enqueue_scripts' ]);
            add_action( 'wp_enqueue_scripts', [ PILI::class, 'add_frontend_enqueue_scripts' ], 80 );
            add_action( 'wp_head', [ PILI::class, 'add_custom_css' ], 80 );
            add_filter( 'admin_body_class', [ PILI::class, 'add_admin_body_class' ]);
            add_action( 'init', array( $this, 'init_field_ajax_handlers' ) );

            // 若在 admin_menu 等晚于 init 的时机才 bootstrap，上述钩子已错过，需立即补跑。
            if ( did_action( 'init' ) ) {
                PILI::setup();
                $this->init_field_ajax_handlers();
            }
        }

        /**
         * 初始化字段AJAX处理器
         *
         * @since 1.0
         */
        public function init_field_ajax_handlers() {
            if ( class_exists( __NAMESPACE__ . '\PILI_Field_gallery' ) ) {
                PILI_Field_gallery::init_ajax();
            }
        }

        /**
         * 设置框架常量
         * 
         * 定义框架运行所需的各种路径和URL常量。
         * 
         * @since 1.0
         */
                public static function constants() {
            if ( defined( 'PILI_CORE_DIR' ) && defined( 'PILI_CORE_URL' ) ) {
                self::$dir = PILI_CORE_DIR;
                self::$url = PILI_CORE_URL;
                return;
            }
            $dirname = str_replace( '//', '/', wp_normalize_path( dirname( self::$file ) ) );
            $theme_dir = str_replace( '//', '/', wp_normalize_path( get_parent_theme_file_path() ) );
            $plugin_dir = str_replace( '//', '/', wp_normalize_path( WP_PLUGIN_DIR ) );
            $located_plugin = ( preg_match( '#'. self::sanitize_dirname( $plugin_dir ) .'#', self::sanitize_dirname( $dirname ) ) ) ? true : false;
            $directory = ( $located_plugin ) ? $plugin_dir : $theme_dir;
            $directory_uri = ( $located_plugin ) ? WP_PLUGIN_URL : get_parent_theme_file_uri();
            $foldername = str_replace( $directory, '', $dirname );
            self::$dir = $dirname;
            self::$url = $directory_uri . $foldername;
        }
        
        /**
         * 包含必要的文件
         *
         * 加载框架运行所需的所有类文件和函数文件。
         *
         * @since 1.0
         */
        public static function includes() {
            self::include_plugin_file( 'functions/helpers.php' );
            self::include_plugin_file( 'functions/chart-theme.php' );
            self::include_plugin_file( 'functions/sanitize.php' );
            self::include_plugin_file( 'functions/validate.php' );
            self::include_plugin_file( 'classes/abstract.class.php' );
            self::include_plugin_file( 'classes/fields.class.php' );
            self::include_plugin_file( 'classes/admin-options.class.php' );
            self::include_plugin_file( 'classes/icons.class.php' );
            self::include_plugin_file( 'classes/remix-icons.class.php' );
        }
        
        /**
         * 设置文本域
         * 
         * 加载框架的多语言文件。
         * 
         * @since 1.0
         */
        public static function textdomain() {
            load_textdomain( 'xun', self::$dir . '/languages/' . get_locale() . '.mo' );
        }
        
        /**
         * 清理目录名称
         * 
         * 移除目录名称中的非字母字符，用于路径比较。
         * 
         * @since 1.0
         * 
         * @param string $dirname 目录名称
         * 
         * @return string 清理后的目录名称
         */
        public static function sanitize_dirname( $dirname ) {
            return preg_replace( '/[^A-Za-z]/', '', $dirname );
        }
        
        /**
         * 包含插件文件助手函数
         *
         * 安全地包含框架文件，支持主题覆盖功能。
         *
         * @since 1.0
         *
         * @param string $file 要包含的文件路径
         * @param bool   $load 是否立即加载文件
         *
         * @return string|void 如果不加载则返回文件路径
         */
        public static function include_plugin_file( $file, $load = true ) {
            $path = '';
            $file = ltrim( $file, '/' );
            $override = apply_filters( 'pili_override', 'pili-override' );
            if ( file_exists( get_parent_theme_file_path( $override . '/' . $file ) ) ) {
                $path = get_parent_theme_file_path( $override . '/' . $file );
            } elseif ( file_exists( get_theme_file_path( $override . '/' . $file ) ) ) {
                $path = get_theme_file_path( $override . '/' . $file );
            } elseif ( file_exists( self::$dir . '/' . $override . '/' . $file ) ) {
                $path = self::$dir . '/' . $override . '/' . $file;
            } elseif ( file_exists( self::$dir . '/' . $file ) ) {
                $path = self::$dir . '/' . $file;
            }
            if ( ! empty( $path ) && ! empty( $file ) && $load ) {
                require_once( $path );
            } else {
                return self::$dir . '/' . $file;
            }
        }
    }

/**
 * XUN 主类
 *
 * 这是框架的主要接口类，提供了创建选项页面的静态方法。
 * 开发者主要通过这个类来使用框架功能。
 *
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */
    /**
     * XUN 主类
     *
     * 提供框架的主要API接口，包括：
     * - 创建选项页面
     * - 创建区块
     * - 资源管理
     * - 工具函数
     *
     * @since 1.0
     */
    class PILI {

        /**
         * 创建选项页面
         *
         * 创建一个新的选项页面实例。
         *
         * @since 1.0
         *
         * @param string $id   唯一标识符
         * @param array  $args 选项页面参数
         *
         * @return PILI_Options 选项页面实例
         */
        public static function createOptions( $id, $args = array() ) {
            if ( ! empty( PILI_Setup::$dir ) ) {
                $framework_dir = PILI_Setup::$dir;
            } elseif ( defined( 'PILI_CORE_DIR' ) ) {
                $framework_dir = PILI_CORE_DIR;
            } else {
                $framework_dir = dirname( dirname( __FILE__ ) );
            }
            if ( ! class_exists( __NAMESPACE__ . '\PILI_Abstract' ) ) {
                $file_path = $framework_dir . '/classes/abstract.class.php';
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                }
            }

            if ( ! class_exists( __NAMESPACE__ . '\PILI_Fields' ) ) {
                $file_path = $framework_dir . '/classes/fields.class.php';
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                }
            }

            if ( ! class_exists( __NAMESPACE__ . '\PILI_Options' ) ) {
                $file_path = $framework_dir . '/classes/admin-options.class.php';
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                }
            }

            $params = array(
                'args'     => $args,
                'sections' => array(),
            );
            if ( class_exists( '\PILI_Config', false ) && \PILI_Config::current_id() ) {
                $params['args']['_pili_instance_id'] = \PILI_Config::current_id();
            }

            PILI_Setup::$args['admin_options'][ $id ] = $params;
            if ( ! isset( PILI_Setup::$inited[ $id ] ) ) {
                PILI_Setup::$inited[ $id ] = PILI_Options::instance( $id, $params );
            }

            return PILI_Setup::$inited[ $id ];
        }

        /**
         * 创建区块
         *
         * 为指定的选项页面创建一个新的区块。
         *
         * @since 1.0
         *
         * @param string $id      选项页面ID
         * @param array  $section 区块配置
         */
        public static function createSection( $id, $section ) {

            if ( isset( PILI_Setup::$args['admin_options'][ $id ] ) ) {
                PILI_Setup::$args['admin_options'][ $id ]['sections'][] = $section;
                if ( isset( PILI_Setup::$inited[ $id ] ) ) {
                    $inst           = PILI_Setup::$inited[ $id ];
                    $inst->sections = PILI_Setup::$args['admin_options'][ $id ]['sections'];
                    // M3：壳分区（无 fields）勿每次全量 rebuild pre_fields，避免 300 次 O(n²)。
                    if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                        $inst->pre_sections = $inst->pre_sections( $inst->sections );
                        $inst->pre_fields   = $inst->pre_fields( $inst->sections );
                    }
                }
            }
        }

        /**
         * Register section meta (deferred fields via loader). Alias of pili_register_section_meta.
         *
         * @param string              $id   Options unique.
         * @param array<string,mixed> $meta Meta.
         * @return void
         */
        public static function registerSectionMeta( $id, array $meta ) {
            if ( function_exists( 'pili_register_section_meta' ) ) {
                pili_register_section_meta( $id, $meta );
            }
        }

        /**
         * 获取选项值
         *
         * 从指定的选项组中获取值。
         *
         * @since 1.0
         *
         * @param string $option_name 选项组名称
         * @param string $field_id    字段ID（可选）
         * @param mixed  $default     默认值
         *
         * @return mixed 选项值
         */
        public static function get_option( $option_name, $field_id = '', $default = '' ) {
            return pili_get_option( $option_name, $field_id, $default );
        }

        /**
         * 设置选项值
         *
         * 设置指定选项组中的值。
         *
         * @since 1.0
         *
         * @param string $option_name 选项组名称
         * @param string $field_id    字段ID
         * @param mixed  $value       要设置的值
         *
         * @return bool 是否设置成功
         */
        public static function set_option( $option_name, $field_id, $value ) {
            return pili_set_option( $option_name, $field_id, $value );
        }

        /**
         * 字段工厂
         *
         * 这是框架的核心字段渲染方法，采用工厂模式统一创建和渲染所有字段类型。
         * 支持动态字段类型加载和第三方字段扩展。
         *
         * @since 1.0
         *
         * @param array  $field  字段配置数组
         * @param mixed  $value  字段值
         * @param string $unique 唯一标识符
         * @param string $where  字段位置标识
         * @param string $parent 父级字段标识
         */
        public static function field( $field = array(), $value = '', $unique = '', $where = '', $parent = '' ) {

            if ( empty( $field ) || ! is_array( $field ) ) {
                echo '<div class="p-4 bg-red-50 border border-red-200 rounded-md">';
                echo '<p class="text-sm text-red-600">' . pili_esc_html__( '字段配置无效' ) . '</p>';
                echo '</div>';
                return;
            }

            $field_type = ( ! empty( $field['type'] ) ) ? $field['type'] : '';

            if ( empty( $field_type ) ) {
                echo '<div class="p-4 bg-red-50 border border-red-200 rounded-md">';
                echo '<p class="text-sm text-red-600">' . pili_esc_html__( '字段类型未指定' ) . '</p>';
                echo '</div>';
                return;
            }

            $boot_types = array(
                'media', 'gallery', 'background', 'repeater', 'icon', 'accordion', 'slider',
                'sortable', 'sorter', 'code', 'border', 'palette', 'progress', 'inputs_grid',
                'table', 'chart', 'date', 'color', 'select', 'switch', 'textarea', 'number',
                'button', 'checkbox', 'radio', 'record_viewer', 'loading',
                'choice', 'modal', 'live_preview', 'serp_preview', 'log_viewer', 'pilibot_guide',
                'clean_toolbar', 'clean_tree',
            );
            $boot_attr  = in_array( $field_type, $boot_types, true )
                ? ' data-pili-boot="' . esc_attr( $field_type ) . '"'
                : '';
            echo '<div class="pili-field pili-field-' . esc_attr( $field_type ) . '"' . $boot_attr . '>';
			$title_excluded_types = array( 'heading', 'content', 'notice', 'callback', 'stat_cards', 'steps', 'badge', 'modal', 'toast', 'pilibot', 'pilibot_guide', 'log_viewer', 'live_preview', 'serp_preview', 'lead_header', 'resource_panel', 'activity_panel', 'metric_cards', 'usage_panel', 'chart_panel', 'module_status', 'clean_toolbar', 'clean_tree' );
			// force_title：组件测试等场景需要给 content/modal/toast 等也显示字段标题。
			$show_field_title = ! empty( $field['title'] )
				&& ( ! in_array( $field_type, $title_excluded_types, true ) || ! empty( $field['force_title'] ) );
            if ( $show_field_title ) {
                echo '<div class="pili-title">';
                echo '<h4 class="text-sm font-medium text-gray-700 mb-2">' . esc_html( $field['title'] ) . '</h4>';
                if ( ! empty( $field['subtitle'] ) ) {
                    echo '<div class="pili-subtitle-text text-xs text-gray-500 mb-2">' . esc_html( $field['subtitle'] ) . '</div>';
                }
                echo '</div>';
            }

            echo $show_field_title ? '<div class="pili-fieldset">' : '';

            $value = ( ! isset( $value ) && isset( $field['default'] ) ) ? $field['default'] : $value;
            $value = ( isset( $field['value'] ) ) ? $field['value'] : $value;

            if ( isset( $field['value_callback'] ) && is_callable( $field['value_callback'] ) ) {
                $value = call_user_func( $field['value_callback'], $value, $field, null );
            }

            $classname = __NAMESPACE__ . '\\PILI_Field_' . $field_type;

            // 兜底：setup 未跑或单字段遗漏时，按需加载 fields/{type}/{type}.php。
            if ( ! class_exists( $classname ) && ! empty( PILI_Setup::$dir ) && is_string( $field_type ) && preg_match( '/^[a-z0-9_]+$/', $field_type ) ) {
                $field_file = trailingslashit( PILI_Setup::$dir ) . 'fields/' . $field_type . '/' . $field_type . '.php';
                if ( is_readable( $field_file ) ) {
                    require_once $field_file;
                }
            }

            if ( class_exists( $classname ) ) {
                try {
                    $instance = new $classname( $field, $value, $unique, $where, $parent );

                    if ( method_exists( $instance, 'enqueue' ) ) {
                        $instance->enqueue();
                    }

                    $instance->render();

                } catch ( Exception $e ) {
                    echo '<div class="p-4 bg-red-50 border border-red-200 rounded-md">';
                    echo '<p class="text-sm text-red-600">' . pili_esc_html__( '字段渲染错误:' ) . ' ' . esc_html( $e->getMessage() ) . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="p-4 bg-yellow-50 border border-yellow-200 rounded-md">';
                echo '<p class="text-sm text-yellow-600">' . esc_html(
                    sprintf(
                        /* translators: %s: field type */
                        pili__( '字段类型 "%s" 未找到' ),
                        $field_type
                    )
                ) . '</p>';
                echo '<p class="text-xs text-yellow-500 mt-1">' . esc_html(
                    sprintf(
                        /* translators: %s: class name */
                        pili__( '请确保字段类 "%s" 已正确加载' ),
                        $classname
                    )
                ) . '</p>';
                echo '</div>';
            }

            echo $show_field_title ? '</div>' : '';

            echo '<div class="clear"></div>';
            echo '</div>';
        }

        /**
         * 框架设置
         *
         * 初始化框架的核心功能。
         *
         * @since 1.0
         */
        public static function setup() {

            self::load_fields();

            self::init_options();
        }

        /**
         * 加载字段类型
         *
         * 自动加载所有可用的字段类型。
         *
         * @since 1.0
         */
        public static function load_fields() {

            $fields_dir = PILI_Setup::$dir . '/fields';

            if ( is_dir( $fields_dir ) ) {
                $fields = glob( $fields_dir . '/*/');

                foreach ( $fields as $field_dir ) {
                    $field_name = basename( $field_dir );
                    $field_file = $field_dir . $field_name . '.php';

                    if ( file_exists( $field_file ) ) {
                        require_once $field_file;
                        PILI_Setup::$fields[ $field_name ] = $field_file;
                    }
                }
            }
        }

        /**
         * 初始化选项页面
         *
         * 初始化所有已注册的选项页面。
         *
         * @since 1.0
         */
        public static function init_options() {

            if ( ! empty( PILI_Setup::$args['admin_options'] ) ) {
                foreach ( PILI_Setup::$args['admin_options'] as $key => $params ) {
                    if ( ! isset( PILI_Setup::$inited[ $key ] ) ) {
                        PILI_Setup::$inited[ $key ] = PILI_Options::instance( $key, $params );
                    }
                }
            }
        }

        /**
         * 添加管理页面脚本
         *
         * 在管理页面加载必要的CSS和JavaScript文件。
         *
         * @since 1.0
         */
        public static function add_admin_enqueue_scripts() {
            $is_framework_page = false;

            if ( isset( $_GET['page'] ) ) {
                $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

                if ( strpos( $page, 'pilipost' ) === 0 || in_array( $page, PILI_Setup::$registered_pages ) ) {
                    $is_framework_page = true;
                }
            }

            if ( $is_framework_page ) {
                $fw = pili_asset_handle( 'framework' );
                // 加载基础样式
                wp_enqueue_style( 'dashicons' );
                wp_enqueue_style( $fw, PILI_Setup::$url . '/assets/css/style.min.css', array( 'dashicons' ), PILI_Setup::$version );

                // 预加载媒体库（P4：PILIDOC 选项页按需加载，见 pili-options-lazy-assets.php）
                $skip_media = function_exists( 'pili_skip_options_page_media_enqueue' ) && pili_skip_options_page_media_enqueue();
                if ( ! $skip_media && ! did_action( 'wp_enqueue_media' ) ) {
                    wp_enqueue_media();
                }

                // 选项页 script 由 PILI_Options::admin_enqueue_scripts 统一入队（含 dialog 依赖）。
                // 辅助函数历史上未落地；用 menu slug 判断，避免先以静态 1.0 注册同 handle。
                $is_pili_options = true; // PILI fork: always treat as options page
                if ( ! $is_pili_options ) {
                    wp_enqueue_script( $fw, PILI_Setup::$url . '/assets/js/pili-framework.min.js', array( 'jquery' ), PILI_Setup::$version, true );

                    wp_localize_script( $fw, 'pili_vars', array(
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                        'nonce'    => wp_create_nonce( 'pili_nonce' ),
                        'i18n'     => array(
                            'confirm' => pili__( '确认' ),
                            'saved' => pili__( '已保存' ),
                            'reset' => pili__( '重置' ),
                            'error' => pili__( '错误' ),
                            'loading' => pili__( '加载中…' ),
                        ),
                    ) );
                }
            }
        }

        /**
         * 添加前端脚本
         *
         * 在前端加载必要的CSS文件。
         *
         * @since 1.0
         */
        public static function add_frontend_enqueue_scripts() {
        }

        /**
         * 添加自定义CSS
         *
         * 在页面头部添加自定义CSS样式。
         *
         * @since 1.0
         */
        public static function add_custom_css() {
        }

        /**
         * 添加管理页面body类
         *
         * 为管理页面添加特定的CSS类。
         *
         * @since 1.0
         *
         * @param string $classes 现有的CSS类
         *
         * @return string 修改后的CSS类
         */
        public static function add_admin_body_class( $classes ) {

            if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'pili-' ) === 0 ) {
                $classes .= ' pili-framework-page';
            }

            return $classes;
        }

        /**
         * 获取图标HTML
         *
         * 获取指定名称的Heroicons图标HTML代码。
         *
         * @since 1.0
         *
         * @param string $name       图标名称（如：academic-cap）
         * @param array  $attributes 图标属性配置
         *
         * @return string 图标HTML代码
         */
        public static function icon( $name, $attributes = array() ) {
            if ( is_string( $name ) && strpos( $name, 'ri-' ) === 0 && class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
                return Remix_Icons::get_icon( $name, $attributes );
            }
            return PILI_Icons::get_icon( $name, $attributes );
        }

        /**
         * 输出图标HTML
         *
         * 直接输出指定名称的图标HTML代码。
         *
         * @since 1.0
         *
         * @param string $name       图标名称
         * @param array  $attributes 图标属性配置
         */
        public static function the_icon( $name, $attributes = array() ) {
            echo self::icon( $name, $attributes );
        }

        /**
         * 检查图标是否存在
         *
         * 检查指定的图标是否存在。
         *
         * @since 1.0
         *
         * @param string $name  图标名称
         * @param string $size  图标尺寸
         * @param string $style 图标样式
         *
         * @return bool 图标是否存在
         */
        public static function icon_exists( $name, $size = '24', $style = 'outline' ) {
            if ( is_string( $name ) && strpos( $name, 'ri-' ) === 0 && class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
                return Remix_Icons::icon_exists( $name );
            }
            return PILI_Icons::icon_exists( $name, $size, $style );
        }

        /**
         * 获取可用图标列表
         *
         * 获取所有可用的图标名称列表。
         *
         * @since 1.0
         *
         * @param string $size  图标尺寸
         * @param string $style 图标样式
         *
         * @return array 图标名称数组
         */
        public static function get_available_icons( $size = '24', $style = 'outline' ) {
            return PILI_Icons::get_available_icons( $size, $style );
        }

        /**
         * 搜索图标
         *
         * 根据关键词搜索匹配的图标。
         *
         * @since 1.0
         *
         * @param string $keyword 搜索关键词
         * @param string $size    图标尺寸
         * @param string $style   图标样式
         *
         * @return array 匹配的图标名称数组
         */
        public static function search_icons( $keyword, $size = '24', $style = 'outline' ) {
            return PILI_Icons::search_icons( $keyword, $size, $style );
        }
    }
