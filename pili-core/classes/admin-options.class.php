<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 后台选项类
 * 
 * 这个类负责创建和管理WordPress后台的选项页面。
 * 它提供了完整的选项面板功能，包括表单渲染、数据保存和验证。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Options' ) ) {
    /**
     * PILI_Options 后台选项类
     * 
     * 管理WordPress后台选项页面，包括：
     * - 菜单创建
     * - 表单渲染
     * - 数据保存
     * - 选项验证
     * 
     * @since 1.0
     */
    class PILI_Options extends PILI_Abstract {
        /**
         * 唯一标识符
         * 
         * @since 1.0
         * @var string
         */
        public $unique = '';
        /**
         * 抽象类型标识
         * 
         * @since 1.0
         * @var string
         */
        public $abstract = 'options';
        /**
         * 区块配置数组
         * 
         * @since 1.0
         * @var array
         */
        public $sections = array();
        /**
         * 选项数据数组
         * 
         * @since 1.0
         * @var array
         */
        public $options = array();
        /**
         * 错误信息数组
         * 
         * @since 1.0
         * @var array
         */
        public $errors = array();
        public $notice_message = '';
        public $notice_type = 'success';
        /**
         * 预处理的字段数组
         * 
         * @since 1.0
         * @var array
         */
        public $pre_fields = array();
        /**
         * 预处理的区块数组
         * 
         * @since 1.0
         * @var array
         */
        public $pre_sections = array();
        /**
         * AJAX action namespace (from PILI_Config ajax_ns).
         *
         * @var string
         */
        public $ajax_ns = 'pili';
        /**
         * 参数配置数组
         * 
         * @since 1.0
         * @var array
         */
        public $args = array(
            'framework_title'         => 'PILI Framework <small>by June</small>',
            'framework_class'         => '',
            'menu_title'              => '',
            'menu_slug'               => '',
            'menu_type'               => 'menu',
            'menu_capability'         => 'manage_options',
            'menu_icon'               => null,
            'menu_position'           => null,
            'menu_hidden'             => false,
            'menu_parent'             => '',
            'sub_menu_title'          => '',
            'sub_menu_capability'     => 'manage_options',
            'sub_menu_hidden'         => false,
            'show_bar_menu'           => true,
            'show_sub_menu'           => true,
            'show_in_network'         => true,
            'show_in_customizer'      => false,
            'show_search'             => true,
            'show_reset_all'          => true,
            'show_reset_section'      => true,
            'show_footer'             => true,
            'show_all_options'        => true,
            'show_form_warning'       => true,
            'sticky_header'           => true,
            'save_defaults'           => true,
            'ajax_save'               => true,
            'form_action'             => '',
            'database'                => 'option',
            'transient_time'          => 0,
            'theme'                   => 'dark',
            'class'                   => '',
            'defaults'                => array(),
            'preserve_existing'       => true,
            'sidebar_footer_callback' => null,
            'sidebar_brand_mark'      => '',
            'welcome_brand_mark'      => '',
            // 侧栏角标版本：优先 version 字符串，其次 version_callback，再次宿主插件接口。
            'version'                 => '',
            'version_callback'        => null,
        );
        /**
         * 构造函数
         * 
         * 初始化选项页面实例。
         * 
         * @since 1.0
         * 
         * @param string $key    唯一标识符
         * @param array  $params 参数数组
         */
        public function __construct( $key, $params = array() ) {
            $this->unique   = $key;
            $this->args     = apply_filters( "pili_{$this->unique}_args", wp_parse_args( $params['args'], $this->args ), $this );
            if ( ! empty( $this->args['_pili_instance_id'] ) && class_exists( '\PILI_Config', false ) ) {
                \PILI_Config::use_instance( (string) $this->args['_pili_instance_id'] );
            }
            $this->sections = apply_filters( "pili_{$this->unique}_sections", $params['sections'], $this );
            $this->pre_fields   = $this->pre_fields( $this->sections );
            $this->pre_sections = $this->pre_sections( $this->sections );
            $this->get_options();
            $this->save_defaults();
            $this->add_actions();
        }

        /**
         * Activate Config instance bound to this options page (multi-instance).
         *
         * @return void
         */
        public function use_bound_instance() {
            if ( ! empty( $this->args['_pili_instance_id'] ) && class_exists( '\PILI_Config', false ) ) {
                \PILI_Config::use_instance( (string) $this->args['_pili_instance_id'] );
            }
        }
        /**
         * 创建实例
         * 
         * 静态方法用于创建选项页面实例。
         * 
         * @since 1.0
         * 
         * @param string $key    唯一标识符
         * @param array  $params 参数数组
         * 
         * @return PILI_Options 选项页面实例
         */
        public static function instance( $key, $params = array() ) {
            return new self( $key, $params );
        }
        /**
         * 添加WordPress动作钩子
         * 
         * 注册必要的WordPress钩子来处理选项页面功能。
         * 
         * @since 1.0
         */
        public function add_actions() {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_init', array( $this, 'save_options_handler' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

            $ajax_ns = 'pili';
            if ( class_exists( '\PILI_Config', false ) ) {
                $cfg = \PILI_Config::get( 'ajax_ns', 'pili' );
                if ( is_string( $cfg ) && '' !== sanitize_key( $cfg ) ) {
                    $ajax_ns = sanitize_key( $cfg );
                }
            }
            $this->ajax_ns = $ajax_ns;

            add_action( 'wp_ajax_' . $ajax_ns . '_export_options', array( $this, 'ajax_export' ) );
            add_action( 'wp_ajax_' . $ajax_ns . '_import_options', array( $this, 'ajax_import' ) );
            add_action( 'wp_ajax_' . $ajax_ns . '_reset_current', array( $this, 'ajax_reset_current' ) );
            add_action( 'wp_ajax_' . $ajax_ns . '_reset_empty_current', array( $this, 'ajax_reset_empty_current' ) );
            add_action( 'wp_ajax_' . $ajax_ns . '_reset_all', array( $this, 'ajax_reset_all' ) );
            add_action( 'wp_ajax_' . $ajax_ns . '_load_section', array( $this, 'ajax_load_section' ) );
            add_action( 'wp_ajax_' . $ajax_ns . '_load_field_data', array( $this, 'ajax_load_field_data' ) );

            // 宿主若在 admin_menu 回调内才 createOptions：须补挂当次菜单，否则「骨架启用了但侧栏无项」。
            if ( did_action( 'admin_menu' ) ) {
                $this->add_admin_menu();
            }
        }

        /**
         * 是否启用分区懒加载（默认 false；大页阈值强制 Mode B）。
         *
         * @return bool
         */
        public function lazy_sections_enabled() {
            $enabled = (bool) apply_filters( 'pili_lazy_sections_enabled', false, $this->unique, $this );
            if ( $this->should_force_lazy_mode_b() ) {
                return true;
            }
            return $enabled;
        }

        /**
         * 是否启用资源按需入队（默认 false；大页阈值强制 Mode B）。
         *
         * @return bool
         */
        public function lazy_assets_enabled() {
            $enabled = (bool) apply_filters( 'pili_lazy_assets_enabled', false, $this->unique, $this );
            if ( $this->should_force_lazy_mode_b() ) {
                return true;
            }
            return $enabled;
        }

        /**
         * Large options pages force Mode B (lazy sections + lazy assets).
         * Threshold via filter `pili_lazy_force_min_sections` (default 20; &lt;1 disables).
         *
         * @return bool
         */
        public function should_force_lazy_mode_b() {
            $min = (int) apply_filters( 'pili_lazy_force_min_sections', 20, $this->unique, $this );
            if ( $min < 1 ) {
                return false;
            }
            $n = 0;
            foreach ( (array) $this->sections as $section ) {
                if ( is_array( $section ) && $this->section_is_panel( $section ) ) {
                    $n++;
                }
            }
            return $n >= $min;
        }

        /**
         * Section participates in nav / content panel (has fields or deferred loader).
         *
         * @param array<string,mixed> $section Section.
         * @return bool
         */
        public function section_is_panel( array $section ) {
            if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                return true;
            }
            if ( ! empty( $section['_deferred'] ) ) {
                return true;
            }
            $sid = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
            if ( '' !== $sid && class_exists( '\PILI_Section_Registry', false ) && \PILI_Section_Registry::has_loader( $this->unique, $sid ) ) {
                return true;
            }
            return false;
        }

        /**
         * Hydrate deferred section fields via registry loader (idempotent).
         *
         * @param int|string $section_index Index in $this->sections.
         * @return bool True if section has fields after call.
         */
        public function ensure_section_fields( $section_index ) {
            if ( ! isset( $this->sections[ $section_index ] ) || ! is_array( $this->sections[ $section_index ] ) ) {
                return false;
            }
            $section = $this->sections[ $section_index ];
            if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                $sid = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
                if ( '' !== $sid && class_exists( '\PILI_Section_Registry', false ) ) {
                    \PILI_Section_Registry::mark_loaded( $this->unique, $sid );
                }
                return true;
            }

            $sid = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
            if ( '' === $sid || ! class_exists( '\PILI_Section_Registry', false ) ) {
                return false;
            }

            $fields = \PILI_Section_Registry::run_loader( $this->unique, $sid );
            if ( empty( $fields ) || ! is_array( $fields ) ) {
                return false;
            }

            $this->sections[ $section_index ]['fields'] = $fields;
            unset( $this->sections[ $section_index ]['_deferred'] );

            if ( isset( PILI_Setup::$args['admin_options'][ $this->unique ]['sections'][ $section_index ] ) ) {
                PILI_Setup::$args['admin_options'][ $this->unique ]['sections'][ $section_index ] = $this->sections[ $section_index ];
            }

            $this->pre_fields   = $this->pre_fields( $this->sections );
            $this->pre_sections = $this->pre_sections( $this->sections );
            \PILI_Section_Registry::mark_loaded( $this->unique, $sid );
            return true;
        }

        /**
         * 导入/重置全站前：把所有延迟分区 hydrate 进 pre_fields（否则 AJAX 请求里壳分区导致字段表为空）。
         *
         * @return int 成功装载字段的分区数。
         */
        public function ensure_all_section_fields() {
            $loaded = 0;
            if ( ! is_array( $this->sections ) ) {
                return 0;
            }
            foreach ( array_keys( $this->sections ) as $section_index ) {
                if ( $this->ensure_section_fields( $section_index ) ) {
                    $loaded++;
                }
            }
            $this->pre_fields   = $this->pre_fields( $this->sections );
            $this->pre_sections = $this->pre_sections( $this->sections );
            return $loaded;
        }

        /**
         * 添加管理菜单
         * 
         * 在WordPress后台添加选项页面菜单。
         * 
         * @since 1.0
         */
        public function add_admin_menu() {
            if ( ! empty( $this->args['menu_hidden'] ) ) {
                return;
            }
            $menu_slug = $this->args['menu_slug'];
            $menu_title = $this->args['menu_title'];
            $menu_capability = $this->args['menu_capability'];
            if ( $this->args['menu_type'] === 'submenu' ) {
                $menu_parent = $this->args['menu_parent'];
                add_submenu_page(
                    $menu_parent,
                    $menu_title,
                    $menu_title,
                    $menu_capability,
                    $menu_slug,
                    array( $this, 'add_options_html' )
                );
            } else {
                add_menu_page(
                    $menu_title,
                    $menu_title,
                    $menu_capability,
                    $menu_slug,
                    array( $this, 'add_options_html' ),
                    $this->args['menu_icon'],
                    $this->args['menu_position']
                );
            }
            if ( class_exists( __NAMESPACE__ . '\PILI_Setup' ) ) {
                PILI_Setup::$registered_pages[] = $menu_slug;
            }
        }
        /**
         * 获取选项数据
         * 
         * 从数据库中获取保存的选项值。
         * 
         * @since 1.0
         * 
         * @return array 选项数据数组
         */
        public function get_options() {
            // 与前端 framework.min.js 的 xun_refresh 对齐；兼容旧 key。
            if ( isset( $_GET['xun_refresh'] ) || isset( $_GET['pili_refresh'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_cache_delete( $this->unique, 'options' );
                if ( class_exists( '\PILI_Options_Domain_Store' ) ) {
                    \PILI_Options_Domain_Store::flush_cache();
                }
            }
            // 阶段 4：懒加载 + 域拆分已完成后，首屏/切页不 merge_all；保存/导出等仍全量。
            if ( $this->should_defer_full_options_load() ) {
                if ( ! is_array( $this->options ) ) {
                    $this->options = array();
                }
                return $this->options;
            }
            if ( $this->args['database'] === 'transient' ) {
                $this->options = get_transient( $this->unique );
            } elseif ( $this->args['database'] === 'theme_mod' ) {
                $this->options = get_theme_mod( $this->unique, array() );
            } elseif ( $this->args['database'] === 'network' ) {
                $this->options = get_site_option( $this->unique, array() );
            } else {
                $this->options = get_option( $this->unique, array() );
            }
            if ( empty( $this->options ) ) {
                $this->options = array();
            }
            return $this->options;
        }

        /**
         * 是否推迟全量配置加载（阶段 4）。
         *
         * @return bool
         */
        public function should_defer_full_options_load() {
            if ( ! $this->lazy_sections_enabled() ) {
                return false;
            }
            if ( ! class_exists( '\PILI_Options_Migrate' ) || ! \PILI_Options_Migrate::is_done() ) {
                return false;
            }
            if ( ! empty( $GLOBALS['pili_lazy_section_render'] ) ) {
                // 分区 AJAX 渲染已由 hydrate 按域灌入，禁止再 merge_all 冲掉。
                return true;
            }
            if ( isset( $_POST['xun_ajax'] ) || isset( $_POST['PILI_Options_json'] ) || isset( $_POST[ $this->unique ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return false;
            }
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $ajax_ns = ! empty( $this->ajax_ns ) ? (string) $this->ajax_ns : 'pili';
            if ( in_array(
                $action,
                array(
                    $ajax_ns . '_export_options',
                    $ajax_ns . '_import_options',
                    $ajax_ns . '_reset_current',
                    $ajax_ns . '_reset_empty_current',
                    $ajax_ns . '_reset_all',
                    $ajax_ns . '_load_field_data',
                    // 兼容默认命名空间（字段 JS 未读到 ajaxNs 时的回落）
                    'pili_export_options',
                    'pili_import_options',
                    'pili_reset_current',
                    'pili_reset_empty_current',
                    'pili_reset_all',
                    'pili_load_field_data',
                ),
                true
            ) ) {
                return false;
            }
            // load_section 由 hydrate_options_for_section 按域灌入。
            return true;
        }
        /**
         * 保存选项数据
         * 
         * 将选项数据保存到数据库中。
         * 
         * @since 1.0
         * 
         * @param array $data 要保存的数据
         */
        public function save_options( $data ) {
            $this->use_bound_instance();
            // 域拆分已接管：关双写时 update_option 故意返回 false，成功只认域写入标记。
            $domain_mode = (
                'option' === $this->args['database']
                && class_exists( '\PILI_Options_Migrate' )
                && \PILI_Options_Migrate::is_done()
            );
            $write_ok_key = function_exists( 'pili_options_write_ok_key' ) ? pili_options_write_ok_key() : 'pili_options_domain_write_ok';
            if ( $domain_mode ) {
                $GLOBALS[ $write_ok_key ] = false;
            }

            if ( $this->args['database'] === 'transient' ) {
                $saved = set_transient( $this->unique, $data, $this->args['transient_time'] );
            } elseif ( $this->args['database'] === 'theme_mod' ) {
                set_theme_mod( $this->unique, $data );
                $saved = ( get_theme_mod( $this->unique, array() ) === $data );
            } elseif ( $this->args['database'] === 'network' ) {
                $saved = update_site_option( $this->unique, $data );
            } else {
                $saved = update_option( $this->unique, $data );
            }

            if ( $domain_mode ) {
                $saved = ! empty( $GLOBALS[ $write_ok_key ] );
                unset( $GLOBALS[ $write_ok_key ] );
                do_action( "pili_{$this->unique}_saved", $data, $this );
                return (bool) $saved;
            }

            // 非域模式（未迁移 / 其它 database）：沿用 WP 返回值与读回校验。
            if ( $saved === false ) {
                if ( $this->args['database'] === 'transient' ) {
                    $saved = ( get_transient( $this->unique ) === $data );
                } elseif ( $this->args['database'] === 'network' ) {
                    $saved = ( get_site_option( $this->unique, array() ) === $data );
                } elseif ( $this->args['database'] === 'option' ) {
                    $saved = ( get_option( $this->unique, array() ) === $data );
                }
            }

            do_action( "xun_{$this->unique}_saved", $data, $this );
            return (bool) $saved;
        }
        /**
         * 保存默认值
         *
         * 如果启用了保存默认值选项，则将字段的默认值保存到数据库。
         *
         * @since 1.0
         */
        public function save_defaults() {
            if ( ! $this->args['save_defaults'] ) {
                return;
            }
            // 域拆分已完成：禁止构造期用字段 default 写回冲库。
            // 旧条件还要求 lazy_sections；影子页未开懒加载时仍会 array_merge 覆盖同域已有键（BUG-003）。
            if ( class_exists( '\PILI_Options_Migrate' ) && \PILI_Options_Migrate::is_done() ) {
                return;
            }
            $defaults = array();
            foreach ( $this->pre_fields as $field ) {
                // 使用 array_key_exists：default 为 false / 0 / '' 时也必须写入。
                if ( ! empty( $field['id'] ) && array_key_exists( 'default', $field ) ) {
                    $defaults[ $field['id'] ] = $field['default'];
                }
            }
            if ( ! empty( $defaults ) ) {
                $this->options = wp_parse_args( $this->options, $defaults );
                $this->save_options( $this->options );
            }
        }
        /**
         * 处理表单提交
         *
         * 处理选项页面的表单提交和数据保存。
         *
         * @since 1.0
         */
                public function save_options_handler() {
            $is_ajax          = isset( $_POST['xun_ajax'] );
            $posted_option_id = isset( $_POST['xun_option_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['xun_option_id'] ) ) : '';
            $self_id          = sanitize_key( (string) $this->unique );

            // 阶段 1 / P2：AJAX 保存必须声明目标 Options ID，禁止主题/插件互相截胡。
            if ( $is_ajax ) {
                if ( '' === $posted_option_id ) {
                    wp_send_json_error(
                        array(
                            'message' => pili__( '缺少选项 ID，请强制刷新后台后重试。' ),
                        )
                    );
                }
                if ( $posted_option_id !== $self_id ) {
                    return;
                }
            } elseif ( '' !== $posted_option_id && $posted_option_id !== $self_id ) {
                return;
            }

            // 阶段 2 / P3：前端可把整表打成单字段 JSON，规避 max_input_vars 截断。
            if ( ! empty( $_POST['PILI_Options_json'] ) && is_string( $_POST['PILI_Options_json'] ) ) {
                $raw_json = wp_unslash( $_POST['PILI_Options_json'] );
                if ( '' === trim( $raw_json ) ) {
                    if ( $is_ajax && $posted_option_id === $self_id ) {
                        wp_send_json_error(
                            array(
                                'message' => pili__( '表单 JSON 为空，数据库未改动。请刷新页面后重试。' ),
                            )
                        );
                    }
                    return;
                }
                $decoded = json_decode( $raw_json, true );
                if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
                    if ( $is_ajax && $posted_option_id === $self_id ) {
                        wp_send_json_error(
                            array(
                                'message' => pili__( '表单 JSON 解析失败（可能被截断或损坏），数据库未改动。请刷新页面后重试。' ),
                            )
                        );
                    } elseif ( $posted_option_id === $self_id ) {
                        $this->set_admin_notice( pili__( '表单 JSON 解析失败，数据库未改动。' ), 'error' );
                        add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                        return;
                    }
                } else {
                    foreach ( $decoded as $key => $value ) {
                        $_POST[ $key ] = $value;
                    }
                    if ( $posted_option_id === $self_id && ! isset( $_POST[ $this->unique ] ) ) {
                        if ( $is_ajax ) {
                            wp_send_json_error(
                                array(
                                    'message' => pili__( '表单 JSON 缺少配置根节点，数据库未改动。请强制刷新后重试。' ),
                                )
                            );
                        }
                        return;
                    }
                }
            }

            if ( ! isset( $_POST[ $this->unique ] ) ) {
                if ( $is_ajax && $posted_option_id === $self_id ) {
                    wp_send_json_error(
                        array(
                            'message' => pili__( '未收到完整表单数据，请刷新页面后重试。' ),
                        )
                    );
                }
                return;
            }
            $nonce = isset( $_POST['PILI_Options_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['PILI_Options_nonce'] ) ) : '';
            if ( '' === $nonce ) {
                $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            }
            $nonce_valid = false;
            if ( '' !== $nonce ) {
                $nonce_valid = (bool) wp_verify_nonce( $nonce, 'PILI_Options_' . $this->unique );
                if ( ! $nonce_valid ) {
                    $nonce_valid = (bool) wp_verify_nonce( $nonce, 'PILI_Options_nonce' );
                }
            }
            if ( ! $nonce_valid ) {
                if ( $is_ajax ) {
                    wp_send_json_error( array( 'message' => pili__( '安全验证失败' ) ) );
                }
                $this->set_admin_notice( pili__( '安全验证失败，请刷新页面后重试。' ), 'error' );
                add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                return;
            }
            if ( ! current_user_can( $this->args['menu_capability'] ) ) {
                if ( $is_ajax ) {
                    wp_send_json_error( array( 'message' => pili__( '权限不足' ) ) );
                }
                $this->set_admin_notice( pili__( '权限不足，无法保存当前配置。' ), 'error' );
                add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                return;
            }
            if ( isset( $_POST[ $this->unique ] ) ) {
                // Budget/Guard：本 handler 内 boot；shutdown 兜底 end（弱机保险丝）。
                if ( function_exists( 'pili_options_save_runtime_boot' ) ) {
                    pili_options_save_runtime_boot();
                }
                // 必须用前导 \，本文件在 namespace Pili\Core 内。
                if ( class_exists( '\PILI_Options_Save_Budget' ) && \PILI_Options_Save_Budget::is_active() && ! \PILI_Options_Save_Budget::check( 'save_start' ) ) {
                    if ( $is_ajax ) {
                        wp_send_json_error(
                            array(
                                'message' => \PILI_Options_Save_Budget::friendly_message(),
                                'budget'  => \PILI_Options_Save_Budget::snapshot(),
                            )
                        );
                    }
                    $this->set_admin_notice( \PILI_Options_Save_Budget::friendly_message(), 'error' );
                    add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                    return;
                }

                $this->get_options();
                $existing_options = is_array( $this->options ) ? $this->options : array();
                $data = wp_unslash( $_POST[ $this->unique ] );
                if ( ! is_array( $data ) ) {
                    $data = array();
                }

                $save_scope = isset( $_POST['xun_save_scope'] ) ? sanitize_key( wp_unslash( (string) $_POST['xun_save_scope'] ) ) : 'section';
                if ( ! in_array( $save_scope, array( 'section', 'all', 'dirty' ), true ) ) {
                    $save_scope = 'section';
                }
                $dirty_ids = array();
                if ( 'dirty' === $save_scope ) {
                    $raw_dirty = isset( $_POST['xun_dirty_fields'] ) ? wp_unslash( $_POST['xun_dirty_fields'] ) : '';
                    if ( is_string( $raw_dirty ) && '' !== $raw_dirty ) {
                        $decoded = json_decode( $raw_dirty, true );
                        if ( is_array( $decoded ) ) {
                            foreach ( $decoded as $fid ) {
                                $fid = is_string( $fid ) || is_numeric( $fid ) ? sanitize_text_field( (string) $fid ) : '';
                                if ( '' !== $fid ) {
                                    $dirty_ids[] = $fid;
                                }
                            }
                            $dirty_ids = array_values( array_unique( $dirty_ids ) );
                        }
                    }
                    if ( empty( $dirty_ids ) ) {
                        $save_scope = 'section';
                    }
                }
                if ( 'section' === $save_scope || 'dirty' === $save_scope ) {
                    $section_index = $this->resolve_save_section_index();
                    // M3 deferred：保存前必须 hydrate，否则 pre_fields 为空 → sanitize 返回空 → 假成功不落库。
                    if ( null !== $section_index ) {
                        $this->ensure_section_fields( $section_index );
                    }
                    $allowed_ids   = ( null === $section_index ) ? array() : $this->get_section_field_ids( $section_index );
                    /**
                     * 业务模块若确需扩展白名单，用 filter（禁止在内核硬编码业务分区配对）。
                     *
                     * @param string[]            $allowed_ids
                     * @param int|string|null     $section_index
                     * @param array<string,mixed> $data
                     * @param self                $instance
                     */
                    $allowed_ids = apply_filters( "pili_{$this->unique}_save_allowed_field_ids", $allowed_ids, $section_index, $data, $this );
                    // 临时/中转键（未在任何分区 fields.id 登记）放行；正式跨区字段仍拒绝。
                    $allowed_ids = $this->expand_ephemeral_save_field_ids( $allowed_ids, $data );
                    if ( empty( $allowed_ids ) ) {
                        if ( $is_ajax ) {
                            wp_send_json_error(
                                array(
                                    'message' => pili__( '无法确定当前分区字段，数据库未改动。请打开一个配置页后再保存。' ),
                                )
                            );
                        }
                        $this->set_admin_notice( pili__( '无法确定当前分区，配置未保存。' ), 'error' );
                        add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                        return;
                    }
                    $allowed_flip = array_flip( $allowed_ids );
                    $extras       = array();
                    foreach ( array_keys( $data ) as $field_key ) {
                        if ( ! isset( $allowed_flip[ (string) $field_key ] ) ) {
                            $extras[] = (string) $field_key;
                        }
                    }
                    if ( ! empty( $extras ) ) {
                        if ( $is_ajax ) {
                            wp_send_json_error(
                                array(
                                    'message' => pili__( '检测到跨分区字段，已拒绝保存。请只保存当前页，或使用「保存全部」。' ),
                                    'extraFields' => array_slice( $extras, 0, 20 ),
                                )
                            );
                        }
                        $this->set_admin_notice( pili__( '检测到跨分区字段，配置未保存。' ), 'error' );
                        add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                        return;
                    }
                    $scoped = array();
                    foreach ( $allowed_ids as $field_id ) {
                        if ( array_key_exists( $field_id, $data ) ) {
                            $scoped[ $field_id ] = $data[ $field_id ];
                        }
                    }
                    if ( 'dirty' === $save_scope ) {
                        $dirty_flip = array_flip( $dirty_ids );
                        $only_dirty = array();
                        foreach ( $scoped as $field_id => $value ) {
                            if ( isset( $dirty_flip[ (string) $field_id ] ) ) {
                                $only_dirty[ $field_id ] = $value;
                            }
                        }
                        if ( empty( $only_dirty ) ) {
                            $save_scope = 'section';
                        } else {
                            $scoped = $only_dirty;
                        }
                    }
                    $data = $scoped;
                }

                unset( $GLOBALS['PILI_Options_save_partial'], $GLOBALS['PILI_Options_save_submitted_ids'] );
                $field_errors = array();
                $sanitized_data = $this->sanitize_options_data( $data, $field_errors );
                $GLOBALS['PILI_Options_save_submitted_ids'] = array_keys( $sanitized_data );
                if ( ! empty( $this->args['preserve_existing'] ) && is_array( $existing_options ) ) {
                    $sanitized_data = wp_parse_args( $sanitized_data, $existing_options );
                }
                $sanitized_data = apply_filters( "pili_{$this->unique}_save", $sanitized_data, $this );
                if ( class_exists( '\PILI_Options_Save_Budget' ) && \PILI_Options_Save_Budget::is_active() && ! \PILI_Options_Save_Budget::check( 'before_write' ) ) {
                    if ( $is_ajax ) {
                        wp_send_json_error(
                            array(
                                'message' => \PILI_Options_Save_Budget::friendly_message(),
                                'budget'  => \PILI_Options_Save_Budget::snapshot(),
                            )
                        );
                    }
                    $this->set_admin_notice( \PILI_Options_Save_Budget::friendly_message(), 'error' );
                    add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                    return;
                }
                do_action( "xun_{$this->unique}_save_before", $sanitized_data, $this );
                $save_diagnostics = $this->build_save_diagnostics( $data, $sanitized_data, $existing_options, $field_errors );
                $save_diagnostics['saveScope'] = $save_scope;
                $result = $this->save_options( $sanitized_data );
                if ( class_exists( '\PILI_Options_Save_Budget' ) && \PILI_Options_Save_Budget::tripped() ) {
                    if ( $is_ajax ) {
                        wp_send_json_error(
                            array(
                                'message'   => \PILI_Options_Save_Budget::friendly_message(),
                                'budget'    => \PILI_Options_Save_Budget::snapshot(),
                                'saveScope' => $save_scope,
                            )
                        );
                    }
                    $this->set_admin_notice( \PILI_Options_Save_Budget::friendly_message(), 'error' );
                    add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                    return;
                }
                do_action( "xun_{$this->unique}_save_after", $sanitized_data, $this );
                $this->get_options();
                if ( isset( $_POST['xun_ajax'] ) ) {
                    if ( $result ) {
                        $message = pili__( '设置已保存' );
                        if ( 'section' === $save_scope ) {
                            $message = pili__( '当前页设置已保存' );
                        } elseif ( 'dirty' === $save_scope ) {
                            $message = pili__( '已保存变更字段' );
                        }
                        if ( ! empty( $field_errors ) ) {
                            $message = pili__( '设置已保存，但部分字段验证未通过，已保留原值' );
                        }
                        wp_send_json_success( array(
                            'message'         => $message,
                            'errors'          => $field_errors,
                            'saveScope'       => $save_scope,
                            'saveDiagnostics' => $save_diagnostics,
                        ) );
                    } else {
                        wp_send_json_error( array(
                            'message' => pili__( '保存失败：数据库写入未通过校验' ),
                            'errors'  => $field_errors,
                        ) );
                    }
                } else {
                    if ( $result ) {
                        if ( ! empty( $field_errors ) ) {
                            $this->set_admin_notice( pili__( '设置已保存，但部分字段验证未通过，已保留原值。' ), 'warning' );
                        } elseif ( 'section' === $save_scope ) {
                            $this->set_admin_notice( pili__( '当前页设置已保存。' ), 'success' );
                        } else {
                            $this->set_admin_notice( pili__( '设置已保存。' ), 'success' );
                        }
                    } else {
                        $this->set_admin_notice( pili__( '保存失败：数据库写入未通过校验。' ), 'error' );
                    }
                    add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
                }
            } elseif ( isset( $_POST['xun_ajax'] ) ) {
                wp_send_json_error( array( 'message' => pili__( '未检测到可保存的数据' ) ) );
            } else {
                $this->set_admin_notice( pili__( '未检测到可保存的数据。' ), 'warning' );
                add_action( 'admin_notices', array( $this, 'admin_notice_success' ) );
            }
        }
        /**
         * 显示成功消息
         *
         * 在管理页面显示保存成功的消息。
         *
         * @since 1.0
         */
        
        /**
         * 分区字段 id 列表（仅顶层字段）。
         *
         * @param int|string $section_index Section index.
         * @return string[]
         */
        public function get_section_field_ids( $section_index ) {
            if ( ! isset( $this->sections[ $section_index ] ) ) {
                return array();
            }
            $section = $this->sections[ $section_index ];
            $fields  = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : array();
            $ids     = array();
            foreach ( $fields as $field ) {
                if ( is_array( $field ) && ! empty( $field['id'] ) ) {
                    $ids[] = (string) $field['id'];
                }
            }
            return array_values( array_unique( $ids ) );
        }

        /**
         * 收集全部已注册顶层字段 id（跨分区）。
         *
         * @return array<string,true>
         */
        protected function get_all_registered_field_id_flip() {
            $flip = array();
            foreach ( $this->sections as $idx => $section ) {
                unset( $section );
                foreach ( $this->get_section_field_ids( $idx ) as $fid ) {
                    $flip[ (string) $fid ] = true;
                }
            }
            return $flip;
        }

        /**
         * L2：payload 中「未在任何分区注册」的键视为临时/中转键，自动并入白名单。
         * 真正属于其它分区 fields.id 的键仍会触发跨分区拒绝。
         *
         * @param string[]            $allowed_ids Allowed ids.
         * @param array<string,mixed> $data        Submitted map.
         * @return string[]
         */
        protected function expand_ephemeral_save_field_ids( array $allowed_ids, array $data ) {
            if ( ! is_array( $data ) || empty( $data ) ) {
                return $allowed_ids;
            }
            $allowed_flip    = array_flip( $allowed_ids );
            $registered_flip = $this->get_all_registered_field_id_flip();
            foreach ( array_keys( $data ) as $field_key ) {
                $fk = is_string( $field_key ) || is_numeric( $field_key ) ? (string) $field_key : '';
                if ( '' === $fk || isset( $allowed_flip[ $fk ] ) ) {
                    continue;
                }
                // 已在其它分区正式注册 → 真跨区，留给后续 extras 拒绝。
                if ( isset( $registered_flip[ $fk ] ) ) {
                    continue;
                }
                $allowed_ids[]       = $fk;
                $allowed_flip[ $fk ] = true;
            }
            return array_values( array_unique( array_filter( array_map( 'strval', $allowed_ids ) ) ) );
        }

        /**
         * 从保存请求解析分区索引（优先 section_id）。
         *
         * @return int|string|null
         */
        public function resolve_save_section_index() {
            $section_id = isset( $_POST['xun_section_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['xun_section_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( '' !== $section_id ) {
                foreach ( $this->sections as $index => $section ) {
                    if ( ! is_array( $section ) ) {
                        continue;
                    }
                    $sid = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
                    if ( $sid === $section_id ) {
                        return $index;
                    }
                }
            }
            if ( ! isset( $_POST['xun_section_index'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return null;
            }
            $raw = wp_unslash( $_POST['xun_section_index'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( is_numeric( $raw ) ) {
                $index = (int) $raw;
                return isset( $this->sections[ $index ] ) ? $index : null;
            }
            $index = sanitize_key( (string) $raw );
            return ( '' !== $index && isset( $this->sections[ $index ] ) ) ? $index : null;
        }

        public function admin_notice_success() {
            if ( empty( $this->notice_message ) ) {
                return;
            }

            $notice_class = 'notice-success';
            if ( $this->notice_type === 'error' ) {
                $notice_class = 'notice-error';
            } elseif ( $this->notice_type === 'warning' ) {
                $notice_class = 'notice-warning';
            }

            echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $this->notice_message ) . '</p></div>';
        }

        private function set_admin_notice( $message, $type = 'success' ) {
            $this->notice_message = (string) $message;
            $this->notice_type = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success';
        }

        /**
         * 保存诊断：供 Console 观测懒加载 / preserve_existing 是否按预期合并。
         *
         * @param array<string,mixed> $raw_post         本次 POST 中的字段（未清洗）。
         * @param array<string,mixed> $sanitized_final  即将写入库的数据。
         * @param array<string,mixed> $existing_options 保存前库中已有数据。
         * @param array<string,string> $field_errors    字段验证错误。
         * @return array<string,mixed>
         */
        private function build_save_diagnostics( $raw_post, $sanitized_final, $existing_options, $field_errors ) {
            $registered_ids = array();
            foreach ( $this->pre_fields as $field ) {
                if ( ! empty( $field['id'] ) ) {
                    $registered_ids[] = (string) $field['id'];
                }
            }

            $submitted_ids = array_keys( $raw_post );
            $preserve_existing = ! empty( $this->args['preserve_existing'] );
            $skipped_not_submitted = array();
            $preserved_from_db     = array();
            $empty_overwrites      = array();
            $changed_keys          = array();

            foreach ( $registered_ids as $field_id ) {
                $was_submitted = array_key_exists( $field_id, $raw_post );
                if ( $preserve_existing && ! $was_submitted ) {
                    $skipped_not_submitted[] = $field_id;
                    if ( array_key_exists( $field_id, $existing_options ) ) {
                        $preserved_from_db[] = $field_id;
                    }
                    continue;
                }

                $new_val = array_key_exists( $field_id, $sanitized_final ) ? $sanitized_final[ $field_id ] : null;
                $old_val = array_key_exists( $field_id, $existing_options ) ? $existing_options[ $field_id ] : null;
                if ( $new_val !== $old_val ) {
                    $changed_keys[] = $field_id;
                }

                if ( $was_submitted && $this->is_effectively_empty_option_value( $raw_post[ $field_id ] ) && ! $this->is_effectively_empty_option_value( $old_val ) ) {
                    $empty_overwrites[] = $field_id;
                }
            }

            $risk_level = 'ok';
            $risk_hints = array();
            if ( $preserve_existing && count( $skipped_not_submitted ) > 0 && ! $this->lazy_sections_enabled() ) {
                $risk_level = 'info';
                $risk_hints[] = pili__( '部分字段未出现在 POST 中，已通过 preserve_existing 保留库中旧值。' );
            }
            if ( ! empty( $empty_overwrites ) ) {
                $risk_level = 'warn';
                $risk_hints[] = pili__( '下列字段本次提交为空，但库中曾有非空值（可能是用户清空，也可能是表单异常）。' );
            }
            if ( ! $preserve_existing && count( $skipped_not_submitted ) > 0 ) {
                $risk_level = 'danger';
                $risk_hints[] = pili__( 'preserve_existing=false 且存在未提交字段，这些字段可能被清空。' );
            }

            return array(
                'preserveExisting'      => $preserve_existing,
                'lazySectionsEnabled'   => $this->lazy_sections_enabled(),
                'registeredFieldCount'  => count( $registered_ids ),
                'submittedFieldCount'   => count( $submitted_ids ),
                'skippedNotSubmitted'   => count( $skipped_not_submitted ),
                'preservedFromDbCount'  => count( $preserved_from_db ),
                'changedKeyCount'       => count( $changed_keys ),
                'emptyOverwriteCount'   => count( $empty_overwrites ),
                'validationErrorCount'  => count( $field_errors ),
                'riskLevel'             => $risk_level,
                'riskHints'             => $risk_hints,
                'skippedFieldSample'    => array_slice( $skipped_not_submitted, 0, 30 ),
                'emptyOverwriteSample'  => array_slice( $empty_overwrites, 0, 30 ),
                'changedKeySample'      => array_slice( $changed_keys, 0, 30 ),
            );
        }

        /**
         * 判断选项值是否「等效为空」（用于保存诊断，非业务校验）。
         *
         * @param mixed $value 字段值。
         * @return bool
         */
        private function is_effectively_empty_option_value( $value ) {
            if ( null === $value ) {
                return true;
            }
            if ( is_array( $value ) ) {
                return empty( $value );
            }
            if ( is_bool( $value ) ) {
                return false;
            }
            if ( is_numeric( $value ) ) {
                return false;
            }
            return '' === trim( (string) $value );
        }

        private function sanitize_options_data( $data, &$errors = array() ) {
            $sanitized = array();
            $errors = array();

            if ( ! is_array( $data ) || empty( $this->pre_fields ) ) {
                return $sanitized;
            }

            $preserve_existing = ! empty( $this->args['preserve_existing'] );

            foreach ( $this->pre_fields as $field ) {
                if ( empty( $field['id'] ) ) {
                    continue;
                }

                $field_id = $field['id'];
                if ( $preserve_existing && ! array_key_exists( $field_id, $data ) ) {
                    continue;
                }

                $field_value = array_key_exists( $field_id, $data ) ? $data[ $field_id ] : '';

                if ( ! isset( $field['sanitize'] ) ) {
                    if ( is_array( $field_value ) ) {
                        $field_value = wp_kses_post_deep( $field_value );
                    } else {
                        $field_value = wp_kses_post( $field_value );
                    }
                } elseif ( is_callable( $field['sanitize'] ) ) {
                    $field_value = call_user_func( $field['sanitize'], $field_value, $field, $this );
                }

                $field_value = $this->validate_field( $field_value, $field );

                if ( isset( $field['validate'] ) && is_callable( $field['validate'] ) ) {
                    $validated = call_user_func( $field['validate'], $field_value, $field, $this );
                    if ( ! empty( $validated ) ) {
                        $errors[ $field_id ] = $validated;
                        if ( isset( $this->options[ $field_id ] ) ) {
                            $field_value = $this->options[ $field_id ];
                        } else {
                            $field_value = $this->get_default( $field );
                        }
                    }
                } elseif ( class_exists( __NAMESPACE__ . '\\PILI_Field_text' ) ) {
                    $rule = PILI_Field_text::resolve_rule( $field );
                    if ( '' !== $rule ) {
                        $rule_msg = PILI_Field_text::check_rule( $rule, is_scalar( $field_value ) ? (string) $field_value : '', $field );
                        if ( '' !== $rule_msg ) {
                            $errors[ $field_id ] = $rule_msg;
                            if ( isset( $this->options[ $field_id ] ) ) {
                                $field_value = $this->options[ $field_id ];
                            } else {
                                $field_value = $this->get_default( $field );
                            }
                        }
                    }
                }

                $sanitized[ $field_id ] = $field_value;
            }

            return $sanitized;
        }
        /**
         * 加载管理页面脚本
         *
         * 在选项页面加载必要的CSS和JavaScript文件。
         *
         * @since 1.0
         *
         * @param string $hook 当前页面钩子
         */
        public function admin_enqueue_scripts( $hook ) {
            if ( isset( $_GET['page'] ) && $_GET['page'] === $this->args['menu_slug'] ) {
                $this->use_bound_instance();
                $framework_url = $this->get_framework_url();

                // 侧栏品牌表情 + 确认弹窗 icon（须先于 dialog 注册引擎）
                $pilibot_file = trailingslashit( (string) PILI_Setup::$dir ) . 'fields/pilibot/pilibot.php';
                if ( is_readable( $pilibot_file ) && ! class_exists( __NAMESPACE__ . '\\PILI_Field_pilibot', false ) ) {
                    require_once $pilibot_file;
                }
                if ( class_exists( __NAMESPACE__ . '\\PILI_Field_pilibot' ) ) {
                    PILI_Field_pilibot::enqueue_assets();
                }

                $fw             = pili_asset_handle( 'framework' );
                $dlg            = pili_asset_handle( 'dialog' );
                $pilibot_engine = pili_asset_handle( 'pilibot-engine' );

                // 先入队核心 CSS/JS，i18n 失败也不能挡住 framework（否则整页点击无响应）。
                wp_enqueue_style(
                    $dlg,
                    $framework_url . 'assets/css/dialog.css',
                    array(),
                    defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.1.2'
                );

                $dialog_deps = array();
                if ( function_exists( 'pili_enqueue_js_i18n_runtime' ) ) {
                    $i18n_rt = pili_enqueue_js_i18n_runtime();
                    if ( is_string( $i18n_rt ) && '' !== $i18n_rt ) {
                        $dialog_deps[] = $i18n_rt;
                    }
                }
                if ( wp_script_is( $pilibot_engine, 'registered' ) || wp_script_is( $pilibot_engine, 'enqueued' ) ) {
                    $dialog_deps[] = $pilibot_engine;
                }
                $dialog_js_ver = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.1.2';
                $dialog_js_fs  = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/js/dialog.js';
                if ( is_readable( $dialog_js_fs ) ) {
                    $dialog_js_ver .= '.' . (string) filemtime( $dialog_js_fs );
                }
                wp_enqueue_script(
                    $dlg,
                    $framework_url . 'assets/js/dialog.js',
                    $dialog_deps,
                    $dialog_js_ver,
                    true
                );
				pili_localize_bag( $dlg, 'dialogDefaults',
					array(
						'confirmTitle' => pili__( '确认' ),
						'confirmMessage' => pili__( '确定要执行此操作吗？' ),
						'confirmText' => pili__( '确定' ),
						'cancelText' => pili__( '取消' ),
						'alertTitle' => pili__( '提示' ),
						'alertMessage' => pili__( '操作完成' ),
						'promptTitle' => pili__( '输入' ),
						'closeLabel' => pili__( '关闭弹窗' ),
						'bot'            => true,
						'botSize'        => 72,
					)
				);
                $framework_deps = array( 'jquery', $dlg );
                if ( function_exists( 'pili_enqueue_js_i18n_runtime' ) ) {
                    $i18n_fw = pili_enqueue_js_i18n_runtime();
                    if ( is_string( $i18n_fw ) && '' !== $i18n_fw ) {
                        $framework_deps[] = $i18n_fw;
                    }
                }
                // setup 可能已用静态 1.0 注册同 handle；须先 deregister，filemtime 才进 URL。
                if ( wp_script_is( $fw, 'registered' ) || wp_script_is( $fw, 'enqueued' ) ) {
                    wp_deregister_script( $fw );
                }
                $framework_js_ver = (string) ( @filemtime( dirname( __DIR__ ) . '/assets/js/pili-framework.min.js' ) ?: ( defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.1.2' ) );
                wp_enqueue_script(
                    $fw,
                    $framework_url . 'assets/js/pili-framework.min.js',
                    $framework_deps,
                    $framework_js_ver,
                    true
                );
                $deps_handle = pili_asset_handle( 'admin-ui-deps' );
                $deps_js     = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/js/admin-ui-deps.js';
                $deps_ver    = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.1.2';
                if ( is_readable( $deps_js ) ) {
                    $deps_ver .= '.' . (string) filemtime( $deps_js );
                    wp_enqueue_script(
                        $deps_handle,
                        $framework_url . 'assets/js/admin-ui-deps.js',
                        array( 'jquery', $fw ),
                        $deps_ver,
                        true
                    );
                }
                $event_ns    = 'pili';
                $css_scope   = 'pili-inst-default';
                $instance_id = 'default';
                $asset_prefix = 'pili';
                if ( class_exists( '\PILI_Config', false ) ) {
                    $event_ns     = (string) \PILI_Config::get( 'event_ns', 'pili' );
                    $css_scope    = (string) \PILI_Config::get( 'css_scope', 'pili-inst-default' );
                    $instance_id  = (string) \PILI_Config::get( 'instance_id', 'default' );
                    $asset_prefix = (string) \PILI_Config::get( 'asset_prefix', 'pili' );
                }

                $ajax_payload = array(
                    'ajaxurl'             => admin_url( 'admin-ajax.php' ),
                    'nonce'               => wp_create_nonce( 'PILI_Options_' . $this->unique ),
                    'optionId'            => $this->unique,
                    'maxOptionsJsonBytes' => (int) apply_filters( 'pili_max_options_json_bytes', 1572864, $this->unique, $this ),
                    'defaultSaveScope'    => 'section',
                    'ajaxNs'              => $this->ajax_ns,
                );
                $runtime_payload = array(
                    'instanceId'  => $instance_id,
                    'eventNs'     => $event_ns,
                    'cssScope'    => $css_scope,
                    'ajaxNs'      => $this->ajax_ns,
                    'assetPrefix' => $asset_prefix,
                    'optionId'    => $this->unique,
                    'writeOkKey'  => function_exists( 'pili_options_write_ok_key' ) ? pili_options_write_ok_key() : 'pili_options_domain_write_ok',
                );
                $lazy_payload = null;
                if ( $this->lazy_sections_enabled() ) {
                    $lazy_payload = array(
                        'sections' => array(
                            'enabled' => true,
                            'unique'  => $this->unique,
                            'action'  => $this->ajax_ns . '_load_section',
                        ),
                        'assets'   => array(
                            'enabled' => $this->lazy_assets_enabled(),
                        ),
                    );
                }
                if ( function_exists( 'pili_localize_options_runtime' ) ) {
                    pili_localize_options_runtime( $fw, $this->unique, $ajax_payload, $runtime_payload, $lazy_payload );
                } else {
                    wp_localize_script( $fw, 'piliAjax', $ajax_payload );
                    wp_localize_script( $fw, 'piliRuntime', $runtime_payload );
                    if ( is_array( $lazy_payload ) ) {
                        wp_localize_script( $fw, 'piliLazySections', $lazy_payload['sections'] );
                        wp_localize_script( $fw, 'piliLazyAssets', $lazy_payload['assets'] );
                    }
                }
                if ( class_exists( __NAMESPACE__ . '\PILI_Field_gallery' ) ) {
                    PILI_Field_gallery::ensure_ajax( $this->ajax_ns );
                }
                if ( $this->lazy_sections_enabled() && $this->lazy_assets_enabled() ) {
                    // P4 v2：首屏核心字段 + 重交互类型预载（media/gallery/repeater 等，见 pili_interactive_field_types）。
                    $this->enqueue_fields_assets( $this->pre_fields, 'core_only' );
                    // table/chart 及交互型字段须首屏预载脚本，避免 P2 分区注入后无法点击。
                    $this->enqueue_fields_assets_by_types(
                        $this->pre_fields,
                        apply_filters(
                            'pili_preload_field_types',
                            array( 'table', 'chart' ),
                            $this->unique,
                            $this
                        )
                    );
                } else {
                    $this->enqueue_fields_assets( $this->pre_fields );
                }
            }
        }

        /**
         * 扁平化字段列表（含 repeater 子字段）。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @return array<int,array<string,mixed>>
         */
        public function flatten_fields( $fields ) {
            $out = array();
            if ( ! is_array( $fields ) ) {
                return $out;
            }
            foreach ( $fields as $field ) {
                if ( ! is_array( $field ) || empty( $field['type'] ) ) {
                    continue;
                }
                $out[] = $field;
                if ( 'repeater' === $field['type'] && ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    $out = array_merge( $out, $this->flatten_fields( $field['fields'] ) );
                }
            }
            return $out;
        }

        /**
         * 分区是否含需 wp.media 的字段。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @return bool
         */
        public function section_fields_need_media( $fields ) {
            foreach ( $this->flatten_fields( $fields ) as $field ) {
                $type = isset( $field['type'] ) ? (string) $field['type'] : '';
                if ( in_array( $type, array( 'media', 'gallery', 'background', 'upload' ), true ) ) {
                    return true;
                }
            }
            return false;
        }

        /**
         * 首屏核心字段类型（P4 v2，其余随分区 assets 加载）。
         *
         * @return array<int,string>
         */
        public function get_core_field_types() {
            if ( function_exists( 'pili_core_field_types' ) ) {
                return pili_core_field_types();
            }
            return array( 'switch', 'select', 'text', 'textarea', 'number', 'checkbox', 'radio', 'subheading', 'content', 'button', 'password', 'date', 'color' );
        }

        /**
         * 字段列表是否包含指定类型（含 repeater 子字段）。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @param array<int,string>              $types  类型列表。
         * @return bool
         */
        public function fields_contain_types( $fields, $types ) {
            $want = array_flip( array_map( 'strval', (array) $types ) );
            foreach ( $this->flatten_fields( $fields ) as $field ) {
                $type = isset( $field['type'] ) ? (string) $field['type'] : '';
                if ( '' !== $type && isset( $want[ $type ] ) ) {
                    return true;
                }
            }
            return false;
        }

        /**
         * 按类型入队字段脚本（去重，仅当 fields 中存在该类型时）。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @param array<int,string>              $types  须入队的类型。
         * @return void
         */
        public function enqueue_fields_assets_by_types( $fields, $types ) {
            $types = array_values( array_filter( array_map( 'strval', (array) $types ) ) );
            if ( empty( $types ) || ! $this->fields_contain_types( $fields, $types ) ) {
                return;
            }
            $want = array_flip( $types );
            $seen = array();
            foreach ( $this->flatten_fields( $fields ) as $field ) {
                if ( empty( $field['type'] ) ) {
                    continue;
                }
                $type = (string) $field['type'];
                if ( ! isset( $want[ $type ] ) || isset( $seen[ $type ] ) ) {
                    continue;
                }
                $seen[ $type ] = true;
                // 字段类在命名空间内；勿用全局 PILI_Field_*（class_exists 恒为 false，table/chart 脚本预载会静默失败）。
                $field_class = __NAMESPACE__ . '\\PILI_Field_' . $type;
                if ( class_exists( $field_class ) ) {
                    $field_instance = new $field_class( $field, '', $this->unique, 'options' );
                    $field_instance->enqueue();
                }
            }
        }

        /**
         * 为字段列表入队脚本/样式（字段类 enqueue）。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @param string                       $scope  `all` 全部类型；`core_only` 仅首屏核心类型（按类型去重）。
         * @return void
         */
        public function enqueue_fields_assets( $fields, $scope = 'all' ) {
            $core_types = array_flip( $this->get_core_field_types() );
            $seen_types = array();

            if ( 'all' === $scope ) {
                $this->enqueue_inputs_grid_compute_script_if_needed( $fields );
            }

            foreach ( $this->flatten_fields( $fields ) as $field ) {
                if ( empty( $field['type'] ) ) {
                    continue;
                }
                $type = (string) $field['type'];
                if ( 'core_only' === $scope && ! isset( $core_types[ $type ] ) ) {
                    continue;
                }
                if ( isset( $seen_types[ $type ] ) ) {
                    continue;
                }
                $seen_types[ $type ] = true;
                // 字段类在命名空间内；勿用全局 PILI_Field_*（class_exists 恒为 false，table/chart 脚本预载会静默失败）。
                $field_class = __NAMESPACE__ . '\\PILI_Field_' . $type;
                if ( class_exists( $field_class ) ) {
                    $field_instance = new $field_class( $field, '', $this->unique, 'options' );
                    $field_instance->enqueue();
                }
            }
        }

        /**
         * 任一 inputs_grid 含 product_round 计算时入队 compute 脚本（避免按 type 去重后首个无 compute 的 inputs_grid 挡住后续定价字段）。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @return void
         */
        private function enqueue_inputs_grid_compute_script_if_needed( $fields ) {
            foreach ( $this->flatten_fields( $fields ) as $field ) {
                if ( empty( $field['type'] ) || 'inputs_grid' !== (string) $field['type'] ) {
                    continue;
                }
                $compute = isset( $field['inputs_grid_compute'] ) && is_array( $field['inputs_grid_compute'] )
                    ? $field['inputs_grid_compute']
                    : null;
                if ( null === $compute || empty( $compute['type'] ) || 'product_round' !== (string) $compute['type'] ) {
                    continue;
                }
                if ( ! class_exists( __NAMESPACE__ . '\PILI_Setup' ) ) {
                    return;
                }
                $compute_handle = pili_asset_handle( 'field-inputs-grid-compute' );
                wp_enqueue_script(
                    $compute_handle,
                    PILI_Setup::$url . '/assets/js/fields/inputs-grid-compute.js',
                    array( 'jquery' ),
                    PILI_Setup::$version,
                    true
                );
                return;
            }
        }

        /**
         * 收集字段入队后的 script/style 清单（供分区 AJAX 返回）。
         *
         * @param array<int,array<string,mixed>> $fields 字段数组。
         * @return array{scripts:array<int,array<string,mixed>>,styles:array<int,array<string,mixed>>}
         */
        public function collect_asset_manifest_for_fields( $fields, $scripts_before = null, $styles_before = null ) {
            global $wp_scripts, $wp_styles;

            if ( ! is_array( $scripts_before ) ) {
                $scripts_before = array();
                if ( $wp_scripts instanceof \WP_Scripts ) {
                    $scripts_before = array_fill_keys( $wp_scripts->queue, true );
                }
            }
            if ( ! is_array( $styles_before ) ) {
                $styles_before = array();
                if ( $wp_styles instanceof \WP_Styles ) {
                    $styles_before = array_fill_keys( $wp_styles->queue, true );
                }
            }

            if ( $this->section_fields_need_media( $fields ) && ! did_action( 'wp_enqueue_media' ) ) {
                wp_enqueue_media();
            }

            $this->enqueue_fields_assets( $fields );

            $media_templates = '';
            // 懒加载不会跑 admin_footer，须把媒体 Underscore 模板随 AJAX 带回，否则 open() 报 Template not found: #tmpl-media-modal。
            if ( $this->section_fields_need_media( $fields ) && function_exists( 'wp_print_media_templates' ) ) {
                ob_start();
                wp_print_media_templates();
                $media_templates = (string) ob_get_clean();
            }

            return array(
                'scripts'          => $this->manifest_from_queue_diff( $wp_scripts, $scripts_before ),
                'styles'           => $this->manifest_from_queue_diff( $wp_styles, $styles_before ),
                'media_templates'  => $media_templates,
            );
        }

        /**
         * 从 queue 增量构建资源 manifest。
         *
         * @param WP_Scripts|WP_Styles|null $wp_asset WP 资源对象。
         * @param array<string,bool>        $before    入队前的 queue 快照。
         * @return array<int,array<string,mixed>>
         */
        /**
         * 懒加载 assets：queue diff + 递归 deps，拓扑序输出（避免 media 栈 _/Backbone 未定义）。
         *
         * @param \WP_Scripts|\WP_Styles $wp_asset WP 资源对象。
         * @param array<string,bool>     $before   入队前 queue 快照。
         * @return array<int,string> 有序 handle 列表。
         */
        private function manifest_ordered_handles( $wp_asset, $before ) {
            $roots = array();
            foreach ( (array) $wp_asset->queue as $handle ) {
                if ( ! isset( $before[ $handle ] ) ) {
                    $roots[] = (string) $handle;
                }
            }
            $ordered = array();
            $seen    = array();
            $visit   = function ( $handle ) use ( &$visit, &$ordered, &$seen, $wp_asset, $before ) {
                $handle = (string) $handle;
                if ( isset( $seen[ $handle ] ) ) {
                    return;
                }
                $seen[ $handle ] = true;
                if ( empty( $wp_asset->registered[ $handle ] ) ) {
                    return;
                }
                $reg = $wp_asset->registered[ $handle ];
                foreach ( (array) $reg->deps as $dep ) {
                    $dep = (string) $dep;
                    if ( ! isset( $before[ $dep ] ) ) {
                        $visit( $dep );
                    }
                }
                if ( ! isset( $before[ $handle ] ) ) {
                    $ordered[] = $handle;
                }
            };
            foreach ( $roots as $root ) {
                $visit( $root );
            }
            return $ordered;
        }

        /**
         * 从 queue 增量构建资源 manifest（含 deps 展开 + 正确 URL）。
         *
         * @param \WP_Scripts|\WP_Styles|null $wp_asset WP 资源对象。
         * @param array<string,bool>          $before   入队前的 queue 快照。
         * @return array<int,array<string,mixed>>
         */
        private function manifest_from_queue_diff( $wp_asset, $before ) {
            // 本文件在 namespace Pili\Core 内，须写全局 \WP_Scripts / \WP_Styles，否则 instanceof 恒假 → assets 空包。
            if ( ! ( $wp_asset instanceof \WP_Scripts || $wp_asset instanceof \WP_Styles ) ) {
                return array();
            }
            $items = array();
            foreach ( $this->manifest_ordered_handles( $wp_asset, $before ) as $handle ) {
                if ( empty( $wp_asset->registered[ $handle ] ) ) {
                    continue;
                }
                $reg = $wp_asset->registered[ $handle ];
                $src = isset( $reg->src ) ? (string) $reg->src : '';
                if ( '' === $src ) {
                    continue;
                }
                if ( ! preg_match( '#^(https?:)?//#', $src ) ) {
                    // base_url 常无尾斜杠；src 可能是 /wp-includes/...，ltrim 后必须再拼 '/'，
                    // 否则会出现 pilidz.comwp-includes（DNS/404 → 控制台 ERR_NAME_NOT_RESOLVED）。
                    $base = rtrim( (string) $wp_asset->base_url, '/' );
                    $src  = $base . '/' . ltrim( $src, '/' );
                }
                $inline = '';
                if ( ! empty( $reg->extra['data'] ) && is_string( $reg->extra['data'] ) ) {
                    $inline = $reg->extra['data'];
                }
                // wp_add_inline_script( ..., 'after' ) → extra['after']；含 PILI.setBag，懒加载必须一并注入。
                if ( ! empty( $reg->extra['after'] ) && is_array( $reg->extra['after'] ) ) {
                    $after = implode( "\n", array_map( 'strval', $reg->extra['after'] ) );
                    if ( '' !== $after ) {
                        $inline = ( '' !== $inline ? $inline . "\n" : '' ) . $after;
                    }
                }
                $items[] = array(
                    'handle' => (string) $handle,
                    'src'    => $src,
                    'deps'   => array_values( array_map( 'strval', (array) $reg->deps ) ),
                    'inline' => $inline,
                );
            }
            return $items;
        }
        /**
         * 获取框架资源URL
         * 智能检测主题或插件环境
         *
         * @since 1.0
         * @return string 框架资源的基础URL
         */
        private function get_framework_url() {
            $framework_dir = PILI_CORE_DIR;
            $framework_dir = wp_normalize_path( $framework_dir );
            $wp_content_dir = wp_normalize_path( WP_CONTENT_DIR );
            if ( strpos( $framework_dir, $wp_content_dir ) === 0 ) {
                $relative_path = substr( $framework_dir, strlen( $wp_content_dir ) );
                return WP_CONTENT_URL . $relative_path . '/';
            }
            $theme_dir = wp_normalize_path( get_template_directory() );
            if ( strpos( $framework_dir, $theme_dir ) === 0 ) {
                $relative_path = substr( $framework_dir, strlen( $theme_dir ) );
                return get_template_directory_uri() . $relative_path . '/';
            }
            $plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
            if ( strpos( $framework_dir, $plugin_dir ) === 0 ) {
                $relative_path = substr( $framework_dir, strlen( $plugin_dir ) );
                return WP_PLUGIN_URL . $relative_path . '/';
            }
            $abspath = wp_normalize_path( ABSPATH );
            if ( strpos( $framework_dir, $abspath ) === 0 ) {
                $relative_path = substr( $framework_dir, strlen( $abspath ) );
                return home_url( '/' . $relative_path . '/' );
            }
            return plugins_url( '', PILI_CORE_FILE ) . '/';
        }
        /**
         * 获取section在pre_sections中的索引
         *
         * @since 1.0
         * @param array $section 要查找的section
         * @return int pre_sections中的索引，找不到返回-1
         */
        public function get_pre_section_index( $section ) {
            foreach ( $this->pre_sections as $index => $pre_section ) {
                if ( isset( $section['id'] ) && isset( $pre_section['id'] ) &&
                     $section['id'] === $pre_section['id'] ) {
                    return $index;
                }
            }
            return -1;
        }

        /**
         * 侧栏/页头展示用版本号。
         *
         * 优先级：args.version → args.version_callback → pilipost_version_label() → PILIPOST_VERSION。
         *
         * @return string 可能为空（不渲染角标）。
         */
        public function resolve_display_version() {
            $raw = isset( $this->args['version'] ) ? $this->args['version'] : '';
            if ( is_string( $raw ) && '' !== trim( $raw ) ) {
                return trim( $raw );
            }

            $cb = $this->args['version_callback'] ?? null;
            if ( is_callable( $cb ) ) {
                $from_cb = call_user_func( $cb );
                if ( is_string( $from_cb ) || is_numeric( $from_cb ) ) {
                    $from_cb = trim( (string) $from_cb );
                    if ( '' !== $from_cb ) {
                        return $from_cb;
                    }
                }
            }

            if ( function_exists( 'pilipost_version_label' ) ) {
                return (string) pilipost_version_label();
            }

            if ( defined( 'PILIPOST_VERSION' ) && '' !== (string) PILIPOST_VERSION ) {
                return 'v' . ltrim( (string) PILIPOST_VERSION, 'vV' );
            }

            return '';
        }

        /**
         * 渲染选项页面HTML
         *
         * 输出选项页面的完整HTML内容。
         *
         * @since 1.0
         */
        public function add_options_html() {
            $this->use_bound_instance();
            $css_scope   = 'pili-inst-default';
            $instance_id = 'default';
            if ( class_exists( '\PILI_Config', false ) ) {
                $css_scope   = (string) \PILI_Config::get( 'css_scope', 'pili-inst-default' );
                $instance_id = (string) \PILI_Config::get( 'instance_id', 'default' );
            }
            echo '<div class="pili-framework-page pili-fullscreen-container ' . esc_attr( $css_scope ) . '"';
            echo ' data-option-id="' . esc_attr( $this->unique ) . '"';
            echo ' data-pili-instance="' . esc_attr( $instance_id ) . '">';
            echo '<div id="mobile-menu-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 lg:hidden hidden" hidden style="display:none;pointer-events:none;z-index:999998;top:32px;"></div>';
            echo '<div class="flex h-full bg-gray-50">';
            echo '<div id="sidebar" class="fixed left-0 z-50 w-64 bg-white shadow-sm border-r border-gray-200 flex flex-col overflow-hidden transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0" style="top: 32px; bottom: 0; z-index: 999999;">';
            echo '<div class="px-6 py-4 relative">';
            echo '<button id="close-sidebar" class="absolute top-2 right-2 lg:hidden p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 z-10">';
            echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
            echo '</svg>';
            echo '</button>';
            echo '<div class="flex items-center justify-center gap-2 pili-brand-title">';
            if ( class_exists( __NAMESPACE__ . '\\PILI_Field_pilibot' ) ) {
                echo PILI_Field_pilibot::render_host_html(
                    array(
                        'id'             => 'pili-brand-bot',
                        'placement'      => 'inline',
                        'size'           => 36,
                        'state'          => 'idle',
                        'show_meta'      => false,
                        'follow_pointer' => false,
                        'transparent_bg' => true,
                        'wander'         => 'look',
                        'class'          => 'pili-pilibot--brand',
                        'aria_label' => pili__( '加载中' ),
                    )
                );
            }
            $sidebar_mark = '';
            if ( ! empty( $this->args['sidebar_brand_mark'] ) ) {
                $sidebar_mark = (string) $this->args['sidebar_brand_mark'];
            } elseif ( ! empty( $this->args['welcome_brand_mark'] ) ) {
                $sidebar_mark = (string) $this->args['welcome_brand_mark'];
            }
            if ( '' !== $sidebar_mark ) {
                echo '<img class="pili-brand-mark" src="' . esc_url( $sidebar_mark ) . '" alt="' . esc_attr( (string) $this->args['menu_title'] ) . '" width="28" height="34" />';
            }
            echo '<h1 class="text-lg font-semibold text-gray-900 text-center">' . esc_html( $this->args['menu_title'] ) . '</h1>';
            echo '</div>';
            $version = $this->resolve_display_version();
            if ( '' !== $version ) {
                echo '<div class="absolute top-4 right-6">';
                echo '<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-700/10 ring-inset">' . esc_html( $version ) . '</span>';
                echo '</div>';
            }
            echo '<div class="flex items-center justify-center gap-2 pili-sidebar-brand-actions">';
            /**
             * 侧栏品牌区操作入口（原装饰标签位）。
             *
             * @param self $admin Options instance.
             */
            do_action( 'pili_sidebar_brand_actions', $this );
            do_action( "pili_{$this->unique}_sidebar_brand_actions", $this );
            echo '</div>';
            echo '</div>';
            echo '<div class="border-b border-gray-200"></div>';
            echo '<nav class="flex-1 p-4 overflow-y-auto">';
            echo '<div class="space-y-1">';

            $this->render_welcome_menu_item();

            $grouped_sections = $this->group_sections_by_parent();

            foreach ( $grouped_sections as $group_index => $group ) {
                if ( $group['type'] === 'parent' ) {
                    $this->render_parent_menu_item( $group, $group_index );
                } else {
                    $this->render_single_menu_item( $group, $group_index );
                }
            }

            echo '</div>';
            echo '</nav>';

            echo '<div class="mt-auto shrink-0 border-t border-gray-100 bg-gray-50 p-3">';

            $sidebar_footer_cb = $this->args['sidebar_footer_callback'] ?? null;
            if ( is_callable( $sidebar_footer_cb ) ) {
                $sidebar_footer_html = call_user_func( $sidebar_footer_cb, $this );
                if ( is_string( $sidebar_footer_html ) && '' !== $sidebar_footer_html ) {
                    echo $sidebar_footer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callback returns escaped HTML.
                }
            }

            /**
             * 侧栏底部扩展（在版权上方）。
             *
             * @param self $admin Options instance.
             */
            do_action( 'pili_sidebar_footer', $this );
            do_action( "pili_{$this->unique}_sidebar_footer", $this );

            // copyright 显式为空字符串时不渲染（本插件侧栏仅保留用户条）。
            $copyright     = array_key_exists( 'copyright', $this->args ) ? $this->args['copyright'] : null;
            $copyright_url = $this->args['copyright_url'] ?? '';
            if ( null === $copyright ) {
                $copyright = 'Powered by PILI Framework v1.1.0';
            }
            if ( false !== $copyright && '' !== (string) $copyright ) {
                if ( empty( $copyright_url ) ) {
                    $copyright_url = 'https://www.xuntheme.com';
                }
                echo '<div class="px-6 py-3">';
                echo '<div class="text-center text-xs text-gray-500">';
                echo '<a href="' . esc_url( $copyright_url ) . '" target="_blank" rel="noopener noreferrer" class="text-gray-500 hover:text-blue-600 transition-colors duration-200">';
                echo esc_html( (string) $copyright );
                echo '</a>';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

            echo '<div class="flex-1 flex flex-col min-h-screen min-w-0 lg:ml-0">';

            echo '<div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4">';
            echo '<div class="flex items-center justify-between gap-3">';

            echo '<div class="flex items-center shrink-0">';

            echo '<button type="button" id="open-sidebar" class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">';
            echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
            echo '</svg>';
            echo '</button>';

            echo '<div class="pili-options-header-title hidden sm:block text-sm font-medium text-gray-700 ml-1" aria-live="polite"></div>';

            echo '</div>';

			echo '<div class="pili-options-header-actions flex items-center justify-end flex-wrap gap-2 sm:gap-3 min-w-0">';
            $header_bar_cb = $this->args['header_bar_callback'] ?? null;
            if ( is_callable( $header_bar_cb ) ) {
                $header_bar_html = call_user_func( $header_bar_cb, $this );
                if ( is_string( $header_bar_html ) && '' !== $header_bar_html ) {
                    echo $header_bar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callback returns escaped HTML.
                }
            }
            /**
             * 顶栏工具区左侧扩展（语言切换等）。
             *
             * @param self $admin Options instance.
             */
            do_action( 'pili_header_bar', $this );
            do_action( "pili_{$this->unique}_header_bar", $this );
            $this->render_options_header_toolbars();
            echo '</div>';

            // 视觉隐藏即可；勿 display:none。与顶栏 label[for=pili-import-file] 配合原生打开选框。
            // 键盘：label tabindex=0；input 保持 aria-hidden，避免双焦点。
            echo '<input type="file" id="pili-import-file" accept=".json" class="pili-import-file-input" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" tabindex="-1" aria-hidden="true">';

            echo '</div>';
            echo '</div>';

            echo '<div class="flex-1 p-4 sm:p-6 bg-gray-50 overflow-y-auto pili-options-pane" style="min-height: 0;">';

            echo '<form method="post" id="pili-options-form" class="pili-form" enctype="multipart/form-data" data-option-id="' . esc_attr( $this->unique ) . '">';

            wp_nonce_field( 'PILI_Options_' . $this->unique, 'PILI_Options_nonce' );

            echo '<div class="w-full">';

            echo '<div class="pili-section-wrapper hidden" data-section="welcome" data-section-id="welcome" data-header-toolbar="none">';
            $this->render_welcome_page();
            echo '</div>';

            foreach ( $this->sections as $index => $section ) {
                if ( $this->section_is_panel( $section ) ) {
                    $section_id = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
                    $toolbar    = $this->resolve_section_header_toolbar_mode( $section );
                    echo '<div class="pili-section-wrapper hidden" data-section="' . esc_attr( (string) $index ) . '"';
                    if ( '' !== $section_id ) {
                        echo ' data-section-id="' . esc_attr( $section_id ) . '"';
                    }
                    echo ' data-header-toolbar="' . esc_attr( $toolbar ) . '"';
                    if ( $this->lazy_sections_enabled() ) {
                        echo ' data-lazy-section="1" data-loaded="0"';
                    }
                    echo '>';
                    if ( $this->lazy_sections_enabled() ) {
                        $this->render_lazy_section_placeholder();
                    } else {
                        $this->ensure_section_fields( $index );
                        $section = $this->sections[ $index ];
                        $this->render_section( $section );
                    }
                    echo '</div>';
                }
            }
            echo '</div>';

            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '<style>
            .pili-fullscreen-container {
                top: 32px !important;
                left: 160px !important;
            }
            .folded .pili-fullscreen-container {
                left: 36px !important;
            }
            @media screen and (max-width: 782px) {
                .pili-fullscreen-container {
                    top: 46px !important;
                    left: 0 !important;
                }
            }
            .pili-options-pane {
                display: flex;
                flex-direction: column;
            }
            .pili-options-pane > .pili-form {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                min-height: 100%;
            }
            .pili-options-pane > .pili-form > .w-full {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                min-height: 100%;
            }
            .pili-options-pane .pili-section-wrapper.block:has(.pili-section-loading),
            .pili-options-pane .pili-section-wrapper:not(.hidden):has(.pili-section-loading) {
                flex: 1 1 auto;
                width: 100%;
                min-height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .pili-section-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin: 0;
                padding: 0;
                background: transparent;
                border: none;
                box-shadow: none;
                color: #6b7280;
            }
            .pili-section-loading--error {
                color: #b45309;
                background: transparent;
                border: none;
            }
            .pili-section-loading__spin {
                width: 36px;
                height: 36px;
                border: 3px solid #dbeafe;
                border-top-color: #2563eb;
                border-radius: 50%;
                animation: pili-section-spin 0.7s linear infinite;
            }
            @keyframes pili-section-spin {
                to { transform: rotate(360deg); }
            }
            .pili-section-loading__text {
                margin: 0;
                font-size: 14px;
                line-height: 1.5;
            }
            </style>';
        }

        /**
         * 渲染区块
         *
         * 渲染所有配置的区块和字段。
         *
         * @since 1.0
         */
        public function render_sections() {

            if ( empty( $this->sections ) ) {
                return;
            }

            foreach ( $this->sections as $section ) {
                $this->render_section( $section );
            }
        }

        /**
         * 顶栏工具区：配置页显示保存/导入；操作页显示分区 header_actions。
         *
         * 分区可设：
         * - header_toolbar: config|ops|none（默认 config）
         * - header_actions: 按钮数组
         * - header_actions_callback: callable，返回 HTML 或 echo
         */
        public function render_options_header_toolbars() {
            $btn = 'inline-flex items-center px-2 sm:px-3 py-2 border border-gray-300 shadow-sm text-xs sm:text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors duration-200';
            $btn_danger = 'inline-flex items-center px-2 sm:px-3 py-2 border border-red-300 shadow-sm text-xs sm:text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none transition-colors duration-200';
            $btn_primary = 'inline-flex items-center justify-center px-3 py-2 border border-transparent text-xs sm:text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none transition-colors duration-200 w-auto whitespace-nowrap';

            echo '<div class="pili-options-toolbar-config flex items-center flex-wrap justify-end gap-2 sm:gap-3" data-toolbar="config">';

            // 用 label[for] 原生打开选文件框：避免 button+preventDefault / display:none / showPicker 静默失败。
            echo '<label for="pili-import-file" class="' . esc_attr( $btn ) . ' pili-import" style="cursor:pointer;" tabindex="0" role="button" aria-label="' . pili_esc_attr__( '导入配置' ) . '">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>';
            echo '<span class="hidden xs:inline">' . pili_esc_html__( '导入配置' ) . '</span>';
            echo '</label>';

            echo '<button type="button" class="' . esc_attr( $btn ) . ' pili-export" title="' . pili_esc_attr__( '导出配置' ) . '">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>';
            echo '<span class="hidden xs:inline">' . pili_esc_html__( '导出配置' ) . '</span>';
            echo '</button>';

            if ( ! empty( $this->args['show_reset_section'] ) ) {
                echo '<button type="button" class="' . esc_attr( $btn ) . ' pili-reset-current">';
                echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';
                echo '<span class="hidden xs:inline">' . pili_esc_html__( '重置当前页' ) . '</span>';
                echo '</button>';

                echo '<button type="button" class="' . esc_attr( $btn ) . ' pili-reset-empty-current">';
                echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
                echo '<span class="hidden xs:inline">' . pili_esc_html__( '重置空白项' ) . '</span>';
                echo '</button>';
            }

            if ( ! empty( $this->args['show_reset_all'] ) ) {
                echo '<button type="button" class="' . esc_attr( $btn_danger ) . ' pili-reset-all">';
                echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
                echo '<span class="hidden xs:inline">' . pili_esc_html__( '重置全部' ) . '</span>';
                echo '</button>';
            }

            echo '<button type="submit" form="pili-options-form" class="' . esc_attr( $btn_primary ) . ' pili-submit">';
            echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            echo '<span class="hidden xs:inline">' . pili_esc_html__( '保存设置' ) . '</span>';
            echo '</button>';

            echo '</div>';

            foreach ( $this->sections as $index => $section ) {
                // 懒加载壳分区 fields 可能尚未 hydrate，但不能因此跳过 ops 顶栏（header_actions 仍在壳上）。
                if ( ! $this->section_is_panel( $section ) ) {
                    continue;
                }
                if ( 'ops' !== $this->resolve_section_header_toolbar_mode( $section ) ) {
                    continue;
                }
                $section_id = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
                echo '<div class="pili-options-toolbar-ops hidden flex items-center flex-wrap justify-end gap-2 sm:gap-3" data-toolbar="ops" data-section="' . esc_attr( (string) $index ) . '"'
                    . ( '' !== $section_id ? ' data-section-id="' . esc_attr( $section_id ) . '"' : '' )
                    . '>';

                $html = $this->get_section_header_actions_html( $section );
                if ( '' !== $html ) {
                    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built via esc_* helpers.
                } else {
                    echo '<span class="text-xs text-gray-400">' . pili_esc_html__( '暂无操作按钮' ) . '</span>';
                }
                echo '</div>';
            }
        }

        /**
         * @param array<string,mixed> $section Section.
         * @return string config|ops|none
         */
        public function resolve_section_header_toolbar_mode( array $section ) {
            $mode = isset( $section['header_toolbar'] ) ? sanitize_key( (string) $section['header_toolbar'] ) : 'config';
            if ( ! in_array( $mode, array( 'config', 'ops', 'none' ), true ) ) {
                return 'config';
            }
            return $mode;
        }

        /**
         * @param array<string,mixed> $section Section.
         * @return string HTML
         */
        public function get_section_header_actions_html( array $section ) {
            if ( ! empty( $section['header_actions_callback'] ) && is_callable( $section['header_actions_callback'] ) ) {
                ob_start();
                $returned = call_user_func( $section['header_actions_callback'], $section, $this );
                $buffered = (string) ob_get_clean();
                if ( is_string( $returned ) && '' !== $returned ) {
                    return $returned;
                }
                return $buffered;
            }

            if ( empty( $section['header_actions'] ) || ! is_array( $section['header_actions'] ) ) {
                return '';
            }

            $html = '';
            foreach ( $section['header_actions'] as $action ) {
                if ( ! is_array( $action ) ) {
                    continue;
                }
                $html .= $this->render_header_action_button( $action );
            }
            return $html;
        }

        /**
         * @param array<string,mixed> $action Action.
         * @return string
         */
        public function render_header_action_button( array $action ) {
            $label = isset( $action['label'] ) ? (string) $action['label'] : '';
            if ( '' === $label ) {
                return '';
            }
            $id      = isset( $action['id'] ) ? (string) $action['id'] : '';
            $class   = isset( $action['class'] ) ? trim( (string) $action['class'] ) : '';
            $primary = ! empty( $action['primary'] );
            $base    = $primary
                ? 'inline-flex items-center justify-center px-3 py-2 border border-transparent text-xs sm:text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none transition-colors duration-200 whitespace-nowrap'
                : 'inline-flex items-center px-2 sm:px-3 py-2 border border-gray-300 shadow-sm text-xs sm:text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors duration-200';
            $classes = trim( $base . ' ' . $class );

            $icon_html = '';
            if ( ! empty( $action['icon'] ) ) {
                $icon = preg_replace( '/[^a-z0-9\-_ ]/i', '', (string) $action['icon'] );
                if ( '' !== $icon ) {
                    $icon_html = '<i class="' . esc_attr( $icon ) . '" style="font-size:1rem;line-height:1;margin-right:0.375rem" aria-hidden="true"></i>';
                }
            }

            $type = ! empty( $action['submit'] ) || ( isset( $action['type'] ) && 'submit' === $action['type'] )
                ? 'submit'
                : 'button';
            $extra = ' type="' . esc_attr( $type ) . '"';
            if ( $id ) {
                $extra .= ' id="' . esc_attr( $id ) . '"';
            }
            if ( ! empty( $action['form'] ) ) {
                $extra .= ' form="' . esc_attr( (string) $action['form'] ) . '"';
            } elseif ( 'submit' === $type ) {
                $extra .= ' form="pili-options-form"';
            }
            if ( ! empty( $action['disabled'] ) ) {
                $extra .= ' disabled';
            }
            if ( ! empty( $action['attrs'] ) && is_array( $action['attrs'] ) ) {
                foreach ( $action['attrs'] as $an => $av ) {
                    $an = preg_replace( '/[^a-zA-Z0-9_\-:]/', '', (string) $an );
                    if ( '' === $an || 'type' === $an || 'form' === $an || 'id' === $an ) {
                        continue;
                    }
                    if ( true === $av ) {
                        $extra .= ' ' . esc_attr( $an );
                        continue;
                    }
                    $extra .= ' ' . esc_attr( $an ) . '="' . esc_attr( is_scalar( $av ) ? (string) $av : '' ) . '"';
                }
            }

            return '<button class="' . esc_attr( $classes ) . '"' . $extra . '>' . $icon_html . esc_html( $label ) . '</button>';
        }

        /**
         * 懒加载分区占位（P2）。
         */
        public function render_lazy_section_placeholder() {
            echo '<div class="pili-lazy-section-placeholder pili-section-loading" role="status" aria-live="polite">';
            echo '<div class="pili-section-loading__spin" aria-hidden="true"></div>';
            echo '<p class="pili-section-loading__text">' . pili_esc_html__( '正在加载设置…' ) . '</p>';
            echo '</div>';
        }

        /**
         * 输出单个分区的 HTML（供 AJAX 懒加载复用）。
         *
         * @param int|string $section_index sections 数组键。
         * @return string
         */
        public function get_section_html( $section_index ) {
            if ( ! isset( $this->sections[ $section_index ] ) ) {
                return '';
            }
            $this->ensure_section_fields( $section_index );
            $section = $this->sections[ $section_index ];
            if ( empty( $section['fields'] ) ) {
                return '';
            }
            $this->hydrate_options_for_section( $section_index );
            $GLOBALS['pili_lazy_section_render'] = true;
            ob_start();
            $this->render_section( $section );
            $html = ob_get_clean();
            unset( $GLOBALS['pili_lazy_section_render'] );
            return is_string( $html ) ? $html : '';
        }

        /**
         * 按当前分区字段所属域灌入 options（兑现懒加载 ≠ 少读）。
         *
         * @param int|string $section_index Section index.
         * @return void
         */
        public function hydrate_options_for_section( $section_index ) {
            if ( ! class_exists( '\PILI_Options_Domain_Store' ) || ! class_exists( '\PILI_Options_Migrate' ) || ! \PILI_Options_Migrate::is_done() ) {
                $this->get_options();
                return;
            }
            $ids     = $this->get_section_field_ids( $section_index );
            $domains = array();
            foreach ( $ids as $field_id ) {
                $domains[ \PILI_Options_Domain_Registry::domain_for_field( $field_id ) ] = true;
            }
            $this->options = \PILI_Options_Domain_Store::merge_domains( array_keys( $domains ) );
        }

        /**
         * 校验设置页 AJAX nonce（新：PILI_Options_{unique}；旧：PILI_Options_nonce 兼容）。
         *
         * @param string $nonce Nonce string.
         * @return bool
         */
        protected function verify_PILI_Options_nonce( $nonce ) {
            $nonce = is_string( $nonce ) ? $nonce : '';
            if ( '' === $nonce ) {
                return false;
            }
            if ( wp_verify_nonce( $nonce, 'PILI_Options_' . $this->unique ) ) {
                return true;
            }
            /**
             * Allow legacy shared nonce action `PILI_Options_nonce` (pre–unique scope).
             * Default false (Batch C H6). Hosts may re-enable during migration.
             *
             * @param bool   $allow  Allow shared fallback.
             * @param string $nonce  Submitted nonce.
             * @param self   $admin  Options instance.
             */
            if ( ! (bool) apply_filters( 'pili_allow_legacy_shared_options_nonce', false, $nonce, $this ) ) {
                return false;
            }
            return (bool) wp_verify_nonce( $nonce, 'PILI_Options_nonce' );
        }
        /**
         * AJAX：懒加载单个分区 HTML。
         */
        public function ajax_load_section() {
            $this->use_bound_instance();

            // 同 ajax_ns 多 Options 页并存：非本 unique 静默放行，避免先注册实例 nonce 失败后 wp_die 截胡。
            $posted_option_id = '';
            if ( isset( $_POST['option_id'] ) ) {
                $posted_option_id = sanitize_key( wp_unslash( (string) $_POST['option_id'] ) );
            } elseif ( isset( $_POST['unique'] ) ) {
                $posted_option_id = sanitize_key( wp_unslash( (string) $_POST['unique'] ) );
            }
            $self_id = sanitize_key( (string) $this->unique );
            if ( '' !== $posted_option_id && $posted_option_id !== $self_id ) {
                return;
            }

            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
            if ( '' === $posted_option_id ) {
                // 旧客户端未传 option_id：仅当本 unique 专用 nonce 通过才处理，否则放行给其它实例。
                if ( ! wp_verify_nonce( $nonce, 'PILI_Options_' . $this->unique ) ) {
                    return;
                }
            } elseif ( ! $this->verify_PILI_Options_nonce( $nonce ) ) {
                wp_send_json_error( array( 'message' => pili__( '安全校验失败，请刷新页面后重试' ) ) );
            }

            if ( ! current_user_can( $this->args['menu_capability'] ) ) {
                wp_send_json_error( array( 'message' => pili__( '权限不足' ) ) );
            }

            if ( ! $this->lazy_sections_enabled() ) {
                wp_send_json_error( array( 'message' => pili__( '未启用分区懒加载' ) ) );
            }

            $section_index = isset( $_POST['section'] ) ? wp_unslash( $_POST['section'] ) : '';
            if ( $section_index === '' || $section_index === 'welcome' ) {
                wp_send_json_error( array( 'message' => pili__( '无效分区' ) ) );
            }

            if ( is_numeric( $section_index ) ) {
                $section_index = (int) $section_index;
            }

            if ( ! isset( $this->sections[ $section_index ] ) ) {
                wp_send_json_error( array( 'message' => pili__( '分区不存在' ) ) );
            }

            while ( ob_get_level() > 0 ) {
                ob_end_clean();
            }

            // DEBT-49（一次性书面豁免）：仅当 lazy_assets 开启才快照 queue 并打包 assets；
            // lazy_assets=false（pilipost 当前）首屏已 enqueue 全字段脚本，AJAX 只回 HTML。
            $pack_assets    = $this->lazy_sections_enabled() && $this->lazy_assets_enabled();
            $scripts_before = array();
            $styles_before  = array();
            if ( $pack_assets ) {
                // 先快照 queue：render 回调里 enqueue 的 CSS/JS（如引导样式）才能进 assets diff。
                global $wp_scripts, $wp_styles;
                $scripts_before = ( $wp_scripts instanceof \WP_Scripts ) ? array_fill_keys( $wp_scripts->queue, true ) : array();
                $styles_before  = ( $wp_styles instanceof \WP_Styles ) ? array_fill_keys( $wp_styles->queue, true ) : array();
            }

            $html = $this->get_section_html( $section_index );
            if ( '' === $html ) {
                wp_send_json_error( array( 'message' => pili__( '分区 HTML 为空' ) ) );
            }

            $payload = array(
                'html'    => $html,
                'section' => (string) $section_index,
            );

            if ( $pack_assets ) {
                $section = $this->sections[ $section_index ];
                $payload['assets'] = $this->collect_asset_manifest_for_fields( $section['fields'] ?? array(), $scripts_before, $styles_before );
            }

            wp_send_json_success( $payload );
        }

        /**
         * 在 pre_fields 中按 id 查找字段配置。
         *
         * @param string $field_id 字段 id。
         * @return array<string,mixed>|null
         */
        public function find_pre_field_by_id( $field_id ) {
            $field_id = sanitize_key( (string) $field_id );
            if ( '' === $field_id ) {
                return null;
            }
            if ( ! empty( $this->pre_fields ) && is_array( $this->pre_fields ) ) {
                foreach ( $this->pre_fields as $field ) {
                    if ( is_array( $field ) && ! empty( $field['id'] ) && (string) $field['id'] === $field_id ) {
                        return $field;
                    }
                }
            }
            // Mode B：延迟分区尚未 hydrate 时 pre_fields 不含该字段；按需跑 loader 再查。
            return $this->find_field_hydrate_deferred( $field_id );
        }

        /**
         * 在未加载的延迟分区中查找字段（找到即停）。
         *
         * @param string $field_id Field id.
         * @return array<string,mixed>|null
         */
        protected function find_field_hydrate_deferred( $field_id ) {
            $field_id = sanitize_key( (string) $field_id );
            if ( '' === $field_id || empty( $this->sections ) || ! is_array( $this->sections ) ) {
                return null;
            }
            foreach ( $this->sections as $section_index => $section ) {
                if ( ! is_array( $section ) ) {
                    continue;
                }
                if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
                    $this->ensure_section_fields( $section_index );
                    $section = isset( $this->sections[ $section_index ] ) ? $this->sections[ $section_index ] : null;
                    if ( ! is_array( $section ) || empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
                        continue;
                    }
                }
                foreach ( $this->flatten_fields( $section['fields'] ) as $field ) {
                    if ( is_array( $field ) && ! empty( $field['id'] ) && (string) $field['id'] === $field_id ) {
                        return $field;
                    }
                }
            }
            return null;
        }

        /**
         * AJAX：懒加载 table/chart 的 data_callback 数据（P3）。
         */
        public function ajax_load_field_data() {
            $this->use_bound_instance();

            $posted_option_id = '';
            if ( isset( $_POST['option_id'] ) ) {
                $posted_option_id = sanitize_key( wp_unslash( (string) $_POST['option_id'] ) );
            } elseif ( isset( $_POST['unique'] ) ) {
                $posted_option_id = sanitize_key( wp_unslash( (string) $_POST['unique'] ) );
            }
            $self_id = sanitize_key( (string) $this->unique );
            if ( '' !== $posted_option_id && $posted_option_id !== $self_id ) {
                return;
            }

            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
            if ( '' === $posted_option_id ) {
                if ( ! wp_verify_nonce( $nonce, 'PILI_Options_' . $this->unique ) ) {
                    return;
                }
            } elseif ( ! $this->verify_PILI_Options_nonce( $nonce ) ) {
                wp_send_json_error( array( 'message' => pili__( '安全校验失败，请刷新页面后重试' ) ) );
            }

            if ( ! current_user_can( $this->args['menu_capability'] ) ) {
                wp_send_json_error( array( 'message' => pili__( '权限不足' ) ) );
            }

            $field_id = isset( $_POST['field_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['field_id'] ) ) : '';
            if ( '' === $field_id ) {
                wp_send_json_error( array( 'message' => pili__( '缺少字段 id' ) ) );
            }

            // 客户端若带分区下标/ id，先 hydrate 该分区（避免扫全表延迟 loader）。
            if ( isset( $_POST['section'] ) && '' !== (string) wp_unslash( $_POST['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $section_ref = wp_unslash( $_POST['section'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                if ( is_numeric( $section_ref ) ) {
                    $this->ensure_section_fields( (int) $section_ref );
                } else {
                    $sid = sanitize_key( (string) $section_ref );
                    foreach ( $this->sections as $si => $sec ) {
                        if ( is_array( $sec ) && isset( $sec['id'] ) && sanitize_key( (string) $sec['id'] ) === $sid ) {
                            $this->ensure_section_fields( $si );
                            break;
                        }
                    }
                }
            }

            $field = $this->find_pre_field_by_id( $field_id );
            if ( ! is_array( $field ) || empty( $field['type'] ) ) {
                wp_send_json_error( array( 'message' => pili__( '字段不存在' ) ) );
            }

            $type = (string) $field['type'];
            if ( 'table' === $type ) {
                if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_table' ) ) {
                    wp_send_json_error( array( 'message' => pili__( '表格字段类未加载' ) ) );
                }
                $payload = PILI_Field_table::ajax_render_tbody_payload( $field, $this->unique );
                wp_send_json_success(
                    array(
                        'type'         => 'table',
                        'tbody'        => isset( $payload['tbody'] ) ? (string) $payload['tbody'] : '',
                        'field'        => $field_id,
                        'total'        => isset( $payload['total'] ) ? (int) $payload['total'] : 0,
                        'page'         => isset( $payload['page'] ) ? (int) $payload['page'] : 1,
                        'per_page'     => isset( $payload['per_page'] ) ? (int) $payload['per_page'] : 10,
                        'server_paged' => ! empty( $payload['server_paged'] ),
                    )
                );
            }

            if ( 'chart' === $type ) {
                if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_chart' ) ) {
                    wp_send_json_error( array( 'message' => pili__( '图表字段类未加载' ) ) );
                }
                $option = PILI_Field_chart::ajax_resolve_option( $field, $this->unique );
                wp_send_json_success(
                    array(
                        'type'   => 'chart',
                        'option' => is_array( $option ) ? $option : array(),
                        'field'  => $field_id,
                    )
                );
            }

            wp_send_json_error( array( 'message' => pili__( '不支持的字段类型' ) ) );
        }

        /**
         * 渲染单个区块
         *
         * 渲染指定的区块和其包含的字段。
         *
         * @since 1.0
         *
         * @param array $section 区块配置
         */
        public function render_section( $section ) {

            echo '<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">';

            if ( ! empty( $section['title'] ) || ! empty( $section['desc'] ) ) {
                echo '<div class="px-8 border-b border-gray-200 bg-gray-50 rounded-t-lg">';

                if ( ! empty( $section['title'] ) ) {
                    $icon = $section['icon'] ?? 'dashicons-admin-generic';
                    echo '<div class="flex items-center mb-2">';
                    $this->render_menu_icon( $icon, 'mr-3 text-xl text-blue-600' );
                    echo '<h2 class="text-xl font-semibold text-gray-900">' . esc_html( $section['title'] ) . '</h2>';
                    echo '</div>';
                }

                if ( ! empty( $section['desc'] ) ) {
                    echo '<p class="text-gray-600 ml-9">' . wp_kses_post( $section['desc'] ) . '</p>';
                }

                echo '</div>';
            }

            echo '<div class="p-8 rounded-b-lg">';
            if ( ! empty( $section['fields'] ) ) {
                echo '<div class="space-y-6">';
                foreach ( $section['fields'] as $field ) {
                    $this->render_field( $field );
                }
                echo '</div>';
            } else {
                echo '<div class="text-center py-12 text-gray-500">';
                echo '<svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />';
                echo '</svg>';
                echo '<p class="text-lg">' . pili_esc_html__( '暂无配置字段' ) . '</p>';
                echo '</div>';
            }
            echo '</div>';

            echo '</div>';
        }

        /**
         * 渲染单个字段
         *
         * 使用统一的字段工厂方法渲染指定的字段。
         *
         * @since 1.0
         *
         * @param array $field 字段配置
         */
        /**
         * 将字段 dependency 配置编码为 data-dependency（JSON），供 admin-ui-deps 通用联动脚本消费。
         *
         * @param array<int|string,mixed> $dependency 单条 array( field, op, value ) 或多条数组的列表。
         * @return string JSON 或空字符串。
         */
        private function encode_field_dependency_attr( $dependency ) {
            if ( empty( $dependency ) || ! is_array( $dependency ) ) {
                return '';
            }
            if ( isset( $dependency[0] ) && is_array( $dependency[0] ) ) {
                $list = $dependency;
            } else {
                $list = array( $dependency );
            }
            $rules = array();
            foreach ( $list as $rule ) {
                if ( ! is_array( $rule ) || count( $rule ) < 3 ) {
                    continue;
                }
                $rules[] = array(
                    'field' => (string) $rule[0],
                    'op'    => (string) $rule[1],
                    'value' => $rule[2],
                );
            }
            if ( empty( $rules ) ) {
                return '';
            }
            $out = wp_json_encode( $rules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE );
            return false === $out ? '' : $out;
        }

        public function render_field( $field ) {

            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                return;
            }

            $field_value = $this->get_field_value( $field['id'], $this->options, $this->get_default( $field ) );
            if ( isset( $field['value_callback'] ) && is_callable( $field['value_callback'] ) ) {
                $field_value = call_user_func( $field['value_callback'], $field_value, $field, $this );
            }

            echo '<div class="space-y-2" data-field-id="' . esc_attr( $field['id'] ) . '"';
            echo ' data-field-type="' . esc_attr( $field['type'] ) . '"';
            if ( array_key_exists( 'default', $field ) ) {
                echo ' data-field-default="' . esc_attr( wp_json_encode( $field['default'] ) ) . '"';
            }
            if ( ! empty( $field['required'] ) ) {
                echo ' data-required="true"';
            }
            $dep_json = ! empty( $field['dependency'] ) ? $this->encode_field_dependency_attr( $field['dependency'] ) : '';
            if ( '' !== $dep_json ) {
                echo ' data-dependency="' . esc_attr( $dep_json ) . '"';
            }
            echo '>';

            PILI::field( $field, $field_value, $this->unique, 'options' );

            echo '</div>';
        }

        /**
         * 渲染欢迎页面
         *
         * @since 1.0
         */
        public function render_welcome_page() {
            $welcome_content  = $this->args['welcome_content'] ?? '';
            $welcome_callback = $this->args['welcome_content_callback'] ?? null;

            if ( is_callable( $welcome_callback ) ) {
                // 仪表盘字段自带卡片；外层不再套白底 overflow，避免裁切图表。
                echo '<div class="pilipost-dashboard-welcome">';
                call_user_func( $welcome_callback );
                echo '</div>';
                return;
            }

            if ( is_callable( $welcome_content ) ) {
                echo '<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">';
                call_user_func( $welcome_content );
                echo '</div>';
                return;
            }

            if ( ! empty( $welcome_content ) ) {
                echo '<div class="bg-white rounded-lg shadow-sm border border-gray-200">';
                echo wp_kses_post($welcome_content);
                echo '</div>';
            } else {
                $this->render_default_welcome_page();
            }
        }

        /**
         * 渲染默认欢迎页面
         *
         * @since 1.0
         */
        public function render_default_welcome_page() {
            echo '<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">';

            echo '<div class="bg-gradient-to-r from-blue-600 to-blue-500 px-8 py-12 text-white text-center relative overflow-hidden">';

            echo '<div class="absolute inset-0 opacity-30">';
            echo '<div class="bg-circle-1"></div>';
            echo '<div class="bg-circle-3"></div>';
            echo '</div>';
            echo '<style>
            .bg-circle-1 {
                position: absolute;
                top: 1rem;
                left: 2rem;
                width: 6rem;
                height: 6rem;
                border-radius: 50%;
                background: linear-gradient(45deg, #60a5fa, #3b82f6);
                animation: bounce 3s ease-in-out infinite;
            }
            .bg-circle-3 {
                position: absolute;
                bottom: 2rem;
                left: 4rem;
                width: 7rem;
                height: 7rem;
                border-radius: 50%;
                background: linear-gradient(45deg, #a78bfa, #8b5cf6);
                animation: spin 8s linear infinite;
                animation-delay: 2s;
            }
            .logo-rotate {
                animation: logo-spin 20s linear infinite;
                transform-origin: center;
            }

            @keyframes bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-0.5rem); }
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            @keyframes logo-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            </style>';

            echo '<div class="max-w-2xl mx-auto relative z-10">';
            echo '<div class="mb-6">';
            echo '<span class="inline-block p-4 bg-opacity-20 rounded-full backdrop-blur-sm">';
            $brand_mark = isset( $this->args['welcome_brand_mark'] ) ? (string) $this->args['welcome_brand_mark'] : '';
            if ( '' === $brand_mark ) {
                $brand_mark = get_template_directory_uri() . '/assets/images/brand-mark.svg';
            }
            echo '<img class="w-16 h-16 logo-rotate" src="' . esc_url( $brand_mark ) . '" alt="' . pili_esc_attr__( '品牌' ) . '">';
            echo '</span>';
            echo '</div>';
            echo '<h1 class="text-4xl font-bold mb-4 tracking-tight text-white" style="color:#ffffff!important;">' . pili_esc_html__( '欢迎使用霹雳框架' ) . '</h1>';
            echo '<p class="text-blue-100 text-xl leading-relaxed mb-6">' . pili_esc_html__( '从左侧选择分区开始配置。组件测试页请点「基础输入」等分组。' ) . '</p>';
            echo '<div class="flex flex-wrap justify-center gap-4 mt-8">';
            echo '<span class="inline-flex items-center rounded-full bg-green-500 bg-opacity-90 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm shadow-lg">';
            echo '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
            echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>';
            echo '</svg>';
            echo 'v' . esc_html( wp_get_theme()->get( 'Version' ) ) . ' ' . pili_esc_html__( '稳定版' );
            echo '</span>';
            echo '<span class="inline-flex items-center rounded-full bg-blue-500 bg-opacity-90 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm shadow-lg">';
            echo '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
            echo '<path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>';
            echo '</svg>';
            echo pili_esc_html__( '字段组件' );
            echo '</span>';
            echo '<span class="inline-flex items-center rounded-full bg-purple-500 bg-opacity-90 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm shadow-lg">';
            echo '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
            echo '<path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>';
            echo '</svg>';
            echo pili_esc_html__( '设置面板' );
            echo '</span>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo '<div class="p-8">';
            echo '<div class="grid md:grid-cols-3 gap-6 mb-8">';
            echo '<div class="text-center p-6 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-300 cursor-pointer feature-card" data-hover-border="#60a5fa" data-hover-bg="#eff6ff">';
            echo '<div class="mb-4">';
            echo '<span class="dashicons dashicons-art text-3xl text-blue-500"></span>';
            echo '</div>';
            echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '组件库' ) . '</h3>';
            echo '<p class="text-gray-600 text-sm">' . pili_esc_html__( '可复用的后台字段与交互组件' ) . '</p>';
            echo '</div>';
            echo '<div class="text-center p-6 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-300 cursor-pointer feature-card" data-hover-border="#4ade80" data-hover-bg="#f0fdf4">';
            echo '<div class="mb-4">';
            echo '<span class="dashicons dashicons-performance text-3xl text-green-500"></span>';
            echo '</div>';
            echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '性能友好' ) . '</h3>';
            echo '<p class="text-gray-600 text-sm">' . pili_esc_html__( '懒加载分区与按需资源，减轻首屏压力' ) . '</p>';
            echo '</div>';
            echo '<div class="text-center p-6 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-300 cursor-pointer feature-card" data-hover-border="#a855f7" data-hover-bg="#faf5ff">';
            echo '<div class="mb-4">';
            echo '<span class="dashicons dashicons-admin-tools text-3xl text-purple-500"></span>';
            echo '</div>';
            echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '开发友好' ) . '</h3>';
            echo '<p class="text-gray-600 text-sm">' . pili_esc_html__( '统一配置读写与校验，便于宿主接入' ) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '<div class="mb-8">';
            echo '<h3 class="text-xl font-semibold mb-6 text-gray-900 text-center">' . pili_esc_html__( '核心能力一览' ) . '</h3>';
            echo '<div class="grid grid-cols-6 gap-4">';
            $field_types = array(
                array('icon' => 'dashicons-welcome-learn-more', 'name' => pili__( '文本' ), 'color' => 'text-blue-500'),
                array('icon' => 'dashicons-book-alt', 'name' => pili__( '选择' ), 'color' => 'text-green-500'),
                array('icon' => 'dashicons-superhero', 'name' => pili__( '开关' ), 'color' => 'text-purple-500'),
                array('icon' => 'dashicons-cloud', 'name' => pili__( '媒体' ), 'color' => 'text-red-500'),
                array('icon' => 'dashicons-email', 'name' => pili__( '颜色' ), 'color' => 'text-blue-600'),
                array('icon' => 'dashicons-admin-settings', 'name' => pili__( '表格' ), 'color' => 'text-yellow-600'),
                array('icon' => 'dashicons-database-import', 'name' => pili__( '重复器' ), 'color' => 'text-green-600'),
                array('icon' => 'dashicons-shield', 'name' => pili__( '图表' ), 'color' => 'text-purple-600'),
                array('icon' => 'dashicons-groups', 'name' => pili__( '图标' ), 'color' => 'text-red-600'),
                array('icon' => 'dashicons-chart-bar', 'name' => pili__( '日期' ), 'color' => 'text-blue-700'),
                array('icon' => 'dashicons-media-document', 'name' => pili__( '密码' ), 'color' => 'text-green-700'),
                array('icon' => 'dashicons-admin-plugins', 'name' => pili__( '进度' ), 'color' => 'text-purple-700')
            );
            foreach ($field_types as $index => $field) {
                $colors = array(
                    0 => array('border' => '#93c5fd', 'bg' => '#eff6ff'),
                    1 => array('border' => '#86efac', 'bg' => '#f0fdf4'),
                    2 => array('border' => '#c4b5fd', 'bg' => '#faf5ff'),
                    3 => array('border' => '#fca5a5', 'bg' => '#fef2f2'),
                    4 => array('border' => '#93c5fd', 'bg' => '#eff6ff'),
                    5 => array('border' => '#fde047', 'bg' => '#fefce8'),
                    6 => array('border' => '#86efac', 'bg' => '#f0fdf4'),
                    7 => array('border' => '#c4b5fd', 'bg' => '#faf5ff'),
                    8 => array('border' => '#fca5a5', 'bg' => '#fef2f2'),
                    9 => array('border' => '#93c5fd', 'bg' => '#eff6ff'),
                    10 => array('border' => '#86efac', 'bg' => '#f0fdf4'),
                    11 => array('border' => '#c4b5fd', 'bg' => '#faf5ff')
                );

                $color = $colors[$index] ?? array('border' => '#d1d5db', 'bg' => '#f9fafb');

                echo '<div class="text-center p-3 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-200 cursor-pointer field-type-card" data-hover-border="' . $color['border'] . '" data-hover-bg="' . $color['bg'] . '">';
                echo '<span class="dashicons ' . $field['icon'] . ' text-xl ' . $field['color'] . '"></span>';
                echo '<div class="text-xs text-gray-600 mt-1">' . esc_html( $field['name'] ) . '</div>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
            echo '<style>
            .field-type-card:hover, .feature-card:hover, .help-card:hover {
                border-color: var(--hover-border-color) !important;
                background-color: var(--hover-bg-color) !important;
            }
            </style>';

            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const cards = document.querySelectorAll(".field-type-card, .feature-card, .help-card");
                cards.forEach(function(card) {
                    const hoverBorder = card.getAttribute("data-hover-border");
                    const hoverBg = card.getAttribute("data-hover-bg");

                    card.addEventListener("mouseenter", function() {
                        card.style.setProperty("--hover-border-color", hoverBorder);
                        card.style.setProperty("--hover-bg-color", hoverBg);
                        card.style.borderColor = hoverBorder;
                        card.style.backgroundColor = hoverBg;
                    });

                    card.addEventListener("mouseleave", function() {
                        if (card.classList.contains("help-card")) {
                            card.style.borderColor = "#d1d5db"; // gray-300
                            card.style.backgroundColor = "#ffffff"; // white
                        } else {
                            card.style.borderColor = "#d1d5db"; // gray-300
                            card.style.backgroundColor = "#f9fafb"; // gray-50
                        }
                    });
                });
            });
            </script>';
            echo '<div class="grid md:grid-cols-2 gap-6 mb-8">';
            echo '<div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-6">';
            echo '<div class="flex items-center mb-4">';
            echo '<span class="dashicons dashicons-lightbulb text-2xl text-blue-600 mr-3"></span>';
            echo '<h3 class="text-lg font-semibold text-blue-900">' . pili_esc_html__( '快速开始' ) . '</h3>';
            echo '</div>';
            echo '<div class="text-blue-800 space-y-3 text-sm">';
            echo '<div class="flex items-start">';
            echo '<span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs rounded-full mr-3 mt-0.5 flex-shrink-0">1</span>';
            echo '<span>' . pili_esc_html__( '从左侧菜单进入对应分区开始配置' ) . '</span>';
            echo '</div>';
            echo '<div class="flex items-start">';
            echo '<span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs rounded-full mr-3 mt-0.5 flex-shrink-0">2</span>';
            echo '<span>' . pili_esc_html__( '配置保存在 WordPress 选项表，支持导入导出与重置' ) . '</span>';
            echo '</div>';
            echo '<div class="flex items-start">';
            echo '<span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs rounded-full mr-3 mt-0.5 flex-shrink-0">3</span>';
            echo '<span>' . pili_esc_html__( '修改后保存即可生效，组件测试页可逐项验证' ) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-6">';
            echo '<div class="flex items-center mb-4">';
            echo '<span class="dashicons dashicons-admin-tools text-2xl text-green-600 mr-3"></span>';
            echo '<h3 class="text-lg font-semibold text-green-900">' . pili_esc_html__( '使用技巧' ) . '</h3>';
            echo '</div>';
            echo '<div class="text-green-800 space-y-3 text-sm">';
            echo '<div class="flex items-start">';
            echo '<span class="dashicons dashicons-yes text-green-600 mr-2 mt-0.5 flex-shrink-0"></span>';
            echo '<span>' . pili_esc_html__( '善用导入/导出备份当前配置' ) . '</span>';
            echo '</div>';
            echo '<div class="flex items-start">';
            echo '<span class="dashicons dashicons-yes text-green-600 mr-2 mt-0.5 flex-shrink-0"></span>';
            echo '<span>' . pili_esc_html__( '各字段均附带说明，便于快速理解用途' ) . '</span>';
            echo '</div>';
            echo '<div class="flex items-start">';
            echo '<span class="dashicons dashicons-yes text-green-600 mr-2 mt-0.5 flex-shrink-0"></span>';
            echo '<span>' . pili_esc_html__( '按需启用懒加载与分区保存，降低误改风险' ) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '<div class="bg-gray-50 rounded-lg p-6 mb-8">';
            echo '<h3 class="text-lg font-semibold mb-4 text-gray-900 flex items-center">';
            echo '<span class="dashicons dashicons-info text-blue-600 mr-2" style="transform: translateY(4px);"></span>';
            echo pili_esc_html__( '系统信息' );
            echo '</h3>';
            echo '<div class="grid md:grid-cols-3 gap-4 text-sm">';
            echo '<div class="bg-white rounded-lg p-4 border border-gray-200">';
            echo '<div class="flex items-center justify-between">';
            echo '<span class="text-gray-600">' . pili_esc_html__( 'WordPress 版本' ) . '</span>';
            echo '<span class="font-medium text-gray-900">' . get_bloginfo('version') . '</span>';
            echo '</div>';
            echo '</div>';

            echo '<div class="bg-white rounded-lg p-4 border border-gray-200">';
            echo '<div class="flex items-center justify-between">';
            echo '<span class="text-gray-600">' . pili_esc_html__( 'PHP 版本' ) . '</span>';
            echo '<span class="font-medium text-gray-900">' . PHP_VERSION . '</span>';
            echo '</div>';
            echo '</div>';

            echo '<div class="bg-white rounded-lg p-4 border border-gray-200">';
            echo '<div class="flex items-center justify-between">';
            echo '<span class="text-gray-600">' . pili_esc_html__( '当前主题' ) . '</span>';
            echo '<span class="font-medium text-gray-900">' . wp_get_theme()->get('Name') . '</span>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo '<div class="grid md:grid-cols-3 gap-6 mb-8">';

            echo '<div class="text-center p-6 bg-white border-2 border-gray-200 rounded-lg transition-all duration-300 help-card" data-hover-border="#93c5fd" data-hover-bg="#eff6ff">';
            echo '<div class="mb-4">';
            echo '<span class="dashicons dashicons-book text-3xl text-blue-500"></span>';
            echo '</div>';
            echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '使用文档' ) . '</h3>';
            echo '<p class="text-gray-600 text-sm mb-4">' . pili_esc_html__( '框架说明与接入指南' ) . '</p>';
            echo '<a href="http://pilidz.cn" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors duration-200" style="color: white !important;">';
            echo '<span class="dashicons dashicons-external mr-1 text-white"></span>';
            echo '<span class="text-white">' . pili_esc_html__( '访问官网' ) . '</span>';
            echo '</a>';
            echo '</div>';

            echo '<div class="text-center p-6 bg-white border-2 border-gray-200 rounded-lg transition-all duration-300 help-card" data-hover-border="#86efac" data-hover-bg="#f0fdf4">';
            echo '<div class="mb-4">';
            echo '<span class="dashicons dashicons-groups text-3xl text-green-500"></span>';
            echo '</div>';
            echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '前台站点' ) . '</h3>';
            echo '<p class="text-gray-600 text-sm mb-4">' . pili_esc_html__( '打开站点首页预览效果' ) . '</p>';
            echo '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors duration-200" style="color: white !important;">';
            echo '<span class="dashicons dashicons-external mr-1 text-white"></span>';
            echo '<span class="text-white">' . pili_esc_html__( '打开站点' ) . '</span>';
            echo '</a>';
            echo '</div>';

            echo '<div class="text-center p-6 bg-white border-2 border-gray-200 rounded-lg transition-all duration-300 help-card" data-hover-border="#9ca3af" data-hover-bg="#f9fafb">';
            echo '<div class="mb-4">';
            echo '<span class="dashicons dashicons-admin-site text-3xl text-gray-600"></span>';
            echo '</div>';
            echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '技术支持' ) . '</h3>';
            echo '<p class="text-gray-600 text-sm mb-4">' . pili_esc_html__( '遇到问题可查阅文档或联系霹雳设计团队' ) . '</p>';
            echo '<a href="http://pilidz.cn" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900 transition-colors duration-200" style="color: white !important;">';
            echo '<span class="dashicons dashicons-external mr-1 text-white"></span>';
            echo '<span class="text-white">' . pili_esc_html__( '联系我们' ) . '</span>';
            echo '</a>';
            echo '</div>';

            echo '</div>';

            echo '<div class="mt-8 pt-6 border-t border-gray-200">';
            echo '<div class="text-center text-gray-500 text-sm mb-4">';
            echo '<p class="mb-2">';
            echo '<a href="http://pilidz.cn" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 transition-colors duration-200 font-medium">' . pili_esc_html__( '霹雳 PILI' ) . '</a>';
            echo ' | ' . pili_esc_html__( '霹雳设计出品' );
            echo '</p>';
            echo '<p class="text-xs text-gray-400">' . pili_esc_html__( '感谢使用霹雳框架，让后台配置更清晰可靠' ) . '</p>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
        }

        /**
         * 渲染欢迎页菜单项
         *
         * @since 1.0
         */
        public function render_welcome_menu_item() {
            $welcome_children = $this->get_welcome_child_sections();

            if ( empty( $welcome_children ) ) {
                $is_active = true;

                echo '<div class="pili-nav-item relative" data-section="welcome" data-slug="welcome">';

                echo '<div class="pili-nav-indicator absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 ' . ($is_active ? 'opacity-100' : 'opacity-0') . '"></div>';

                $content_classes = $is_active
                    ? 'bg-blue-50 text-blue-700 border border-blue-200'
                    : 'text-gray-700 hover:bg-gray-50 border border-transparent';
                echo '<div class="pili-nav-content pl-4 pr-3 py-3 rounded-lg cursor-pointer transition-all duration-200 ' . $content_classes . '">';

                echo '<div class="flex items-center">';
                echo '<span class="dashicons dashicons-welcome-learn-more mr-3 flex-shrink-0 text-base"></span>';
                echo '<div class="flex-1 min-w-0">';
                echo '<span class="font-medium text-sm truncate block">' . pili_esc_html__( '欢迎' ) . '</span>';
                echo '</div>';
                echo '</div>';

                echo '</div>';
                echo '</div>';
                return;
            }

            // 有子分区时：父级只负责展开（与「基础设置」一致），欢迎内容作为第一个子项。
            echo '<div class="pili-nav-group" data-group="welcome">';
            echo '<div class="pili-nav-parent relative cursor-pointer">';
            echo '<div class="absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 opacity-0"></div>';
            echo '<div class="pl-4 pr-3 py-3 rounded-lg transition-all duration-200 text-gray-700 hover:bg-gray-50 border border-transparent">';
            echo '<div class="flex items-center justify-between">';
            echo '<div class="flex items-center">';
            echo '<span class="dashicons dashicons-welcome-learn-more mr-3 flex-shrink-0 text-base"></span>';
            echo '<span class="font-medium text-sm truncate">' . pili_esc_html__( '从这里开始' ) . '</span>';
            echo '</div>';
            echo '<svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>';
            echo '</svg>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="pili-nav-children ml-4 mt-1 space-y-1 hidden">';

            // 欢迎首页（特殊 section：welcome）。
            echo '<div class="pili-nav-item pili-nav-child relative" data-section="welcome" data-slug="welcome">';
            echo '<div class="pili-nav-indicator absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 opacity-0"></div>';
            echo '<div class="pili-nav-content pl-6 pr-3 py-2 rounded-lg cursor-pointer transition-all duration-200 text-gray-600 hover:bg-gray-50 hover:text-gray-700 border border-transparent">';
            echo '<div class="flex items-center">';
            echo '<span class="dashicons dashicons-dashboard mr-3 flex-shrink-0 text-sm"></span>';
            echo '<span class="font-medium text-sm truncate">' . pili_esc_html__( '从这里开始' ) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            foreach ( $welcome_children as $child ) {
                $this->render_child_menu_item( $child, 'welcome' );
            }
            echo '</div>';
            echo '</div>';
        }

        /**
         * 挂在欢迎页下的子分区（parent = welcome）。
         *
         * @return array<int, array{section:array, index:int|string}>
         */
        public function get_welcome_child_sections() {
            $children = array();
            foreach ( $this->sections as $index => $section ) {
                if ( empty( $section['parent'] ) || 'welcome' !== (string) $section['parent'] ) {
                    continue;
                }
                if ( ! empty( $section['nav_hidden'] ) ) {
                    continue;
                }
                $children[] = array(
                    'section' => $section,
                    'index'   => $index,
                );
            }
            return $children;
        }

        /**
         * 将sections按层级分组
         *
         * 分析sections数组，将其按父子关系分组
         *
         * @since 1.0
         * @return array 分组后的sections
         */
        public function group_sections_by_parent() {
            $grouped = array();

            foreach ( $this->sections as $index => $section ) {
                if ( isset( $section['parent'] ) && ! empty( $section['parent'] ) ) {
                    continue;
                }

                $section_id = $section['id'] ?? 'section_' . $index;
                $children = array();

                foreach ( $this->sections as $child_index => $child_section ) {
                    if ( isset( $child_section['parent'] ) && $child_section['parent'] === $section_id ) {
                        $children[] = array(
                            'section' => $child_section,
                            'index' => $child_index
                        );
                    }
                }

                if ( ! empty( $children ) ) {
                    $grouped[] = array(
                        'type' => 'parent',
                        'id' => $section_id,
                        'section' => $section,
                        'index' => $index,
                        'children' => $children,
                        'expanded' => false
                    );
                } else {
                    $grouped[] = array(
                        'type' => 'single',
                        'section' => $section,
                        'index' => $index
                    );
                }
            }

            foreach ( $this->sections as $index => $section ) {
                if ( isset( $section['parent'] ) && ! empty( $section['parent'] ) ) {
                    $parent_id = $section['parent'];
                    // welcome 由 render_welcome_menu_item 单独挂载，勿当孤儿顶栏项。
                    if ( 'welcome' === (string) $parent_id ) {
                        continue;
                    }
                    $parent_found = false;

                    foreach ( $grouped as $group ) {
                        if ( $group['type'] === 'parent' && $group['id'] === $parent_id ) {
                            $parent_found = true;
                            break;
                        }
                    }

                    if ( ! $parent_found ) {
                        $grouped[] = array(
                            'type' => 'single',
                            'section' => $section,
                            'index' => $index
                        );
                    }
                }
            }

            return $grouped;
        }

        /**
         * 获取第一个有效的section索引（排除父菜单项）
         * 注意：现在默认显示欢迎页面，所以这个方法返回-1表示不激活任何菜单项
         *
         * @since 1.0
         * @return int 第一个有效的section索引，-1表示显示欢迎页面
         */
        public function get_first_valid_section_index() {
            return -1;
        }

        /**
         * 生成URL slug
         *
         * @since 1.0
         * @param string $text 文本
         * @return string slug
         */
        public function generate_slug( $text ) {
            $slug = str_replace( ' ', '-', $text );
            $slug = preg_replace( '/[^\w\-\x{4e00}-\x{9fa5}]/u', '', $slug );
            $slug = preg_replace( '/-+/', '-', $slug );
            $slug = trim( $slug, '-' );

            if ( empty( $slug ) ) {
                $slug = 'section-' . time();
            }

            return $slug;
        }

        /**
         * 获取 section 的唯一 URL slug。
         *
         * 优先使用 section id（规范要求唯一），避免多个菜单项 title 相同时 slug 冲突导致无法打开正确设置页。
         *
         * @since 1.0
         * @param array    $section section 配置。
         * @param int|null $index   sections 数组索引，id 缺失时用于兜底唯一化。
         * @return string slug
         */
        public function get_section_slug( $section, $index = null ) {
            if ( ! empty( $section['id'] ) && is_string( $section['id'] ) ) {
                return sanitize_key( $section['id'] );
            }

            $slug = $this->generate_slug( $section['title'] ?? '' );
            if ( null !== $index ) {
                $slug .= '-' . (int) $index;
            }

            return $slug;
        }

        /**
         * 输出侧栏/区块图标：支持 Remix（ri-*）与 Dashicons（dashicons-*）。
         *
         * @param string $icon        图标类名。
         * @param string $extra_class 额外 Tailwind / 工具类。
         */
        public function render_menu_icon( $icon, $extra_class = '' ) {
            $icon  = is_string( $icon ) ? trim( $icon ) : '';
            $extra = is_string( $extra_class ) ? trim( $extra_class ) : '';
            if ( $icon === '' ) {
                $icon = 'dashicons-admin-generic';
            }
            if ( strpos( $icon, 'ri-' ) === 0 ) {
                echo '<i class="' . esc_attr( trim( $icon . ' ' . $extra ) ) . '" aria-hidden="true"></i>';
                return;
            }
            if ( strpos( $icon, 'dashicons-' ) !== 0 && function_exists( 'mb_strlen' ) && mb_strlen( $icon ) <= 4 ) {
                // 极少数占位用 emoji。
                echo '<span class="' . esc_attr( $extra ) . '" aria-hidden="true">' . esc_html( $icon ) . '</span>';
                return;
            }
            $dash = ( strpos( $icon, 'dashicons-' ) === 0 ) ? $icon : 'dashicons-' . $icon;
            echo '<span class="dashicons ' . esc_attr( trim( $dash . ' ' . $extra ) ) . '" aria-hidden="true"></span>';
        }

        /**
         * 渲染父菜单项（带子菜单）
         *
         * @since 1.0
         * @param array $group 菜单组数据
         * @param int $group_index 组索引
         */
        public function render_parent_menu_item( $group, $group_index ) {
            $section = $group['section'];
            $is_expanded = $group['expanded'];
            $icon = $section['icon'] ?? 'dashicons-admin-generic';
            $title = $section['title'] ?? sprintf( /* translators: %d: group index */ pili__( '菜单组 %d' ), $group_index + 1 );
            $section_index = $group['index'];
            $has_fields = $this->section_is_panel( $section );

            echo '<div class="pili-nav-group" data-group="' . $group_index . '">';

            if ( $has_fields ) {
                $slug = $this->get_section_slug( $section, $section_index );
                echo '<div class="pili-nav-parent pili-nav-item relative cursor-pointer" data-section="' . $section_index . '" data-slug="' . esc_attr( $slug ) . '">';
            } else {
                echo '<div class="pili-nav-parent relative cursor-pointer">';
            }

            if ( $has_fields ) {
                echo '<div class="pili-nav-indicator absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 opacity-0"></div>';
            } else {
                echo '<div class="absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 opacity-0"></div>';
            }

            if ( $has_fields ) {
                echo '<div class="pili-nav-content pl-4 pr-3 py-3 rounded-lg transition-all duration-200 text-gray-700 hover:bg-gray-50 border border-transparent">';
            } else {
                echo '<div class="pl-4 pr-3 py-3 rounded-lg transition-all duration-200 text-gray-700 hover:bg-gray-50 border border-transparent">';
            }

            $desc = $section['desc'] ?? '';
            if ( ! empty( $desc ) ) {
                echo '<div class="flex items-center justify-between">';
                echo '<div class="flex items-center">';
                $this->render_menu_icon( $icon, 'mr-3 flex-shrink-0 text-base' );
                echo '<div class="flex-1 min-w-0">';
                echo '<span class="font-medium text-sm truncate block">' . esc_html( $title ) . '</span>';
                $desc_color = 'text-gray-500';
                echo '<div class="text-xs ' . $desc_color . ' mt-1 truncate">' . esc_html( $desc ) . '</div>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="flex items-center justify-between">';
                echo '<div class="flex items-center">';
                $this->render_menu_icon( $icon, 'mr-3 flex-shrink-0 text-base' );
                echo '<span class="font-medium text-sm truncate">' . esc_html( $title ) . '</span>';
                echo '</div>';
            }

            echo '<svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>';
            echo '</svg>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo '<div class="pili-nav-children ml-4 mt-1 space-y-1 hidden">';

            foreach ( $group['children'] as $child ) {
                $this->render_child_menu_item( $child, $group_index );
            }

            echo '</div>';
            echo '</div>';
        }

        /**
         * 渲染子菜单项
         *
         * @since 1.0
         * @param array $child 子菜单数据
         * @param int $group_index 父组索引
         */
        public function render_child_menu_item( $child, $group_index ) {
            $section = $child['section'];
            $index = $child['index'];
            $first_valid_index = $this->get_first_valid_section_index();
            $is_active = false;
            $icon = $section['icon'] ?? '📄';
            $title = $section['title'] ?? sprintf( /* translators: %d: item index */ pili__( '子项 %d' ), $index + 1 );
            $desc = $section['desc'] ?? '';

            $slug = $this->get_section_slug( $section, $index );
            echo '<div class="pili-nav-item pili-nav-child relative" data-section="' . $index . '" data-slug="' . esc_attr( $slug ) . '">';

            echo '<div class="pili-nav-indicator absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 ' . ($is_active ? 'opacity-100' : 'opacity-0') . '"></div>';

            $content_classes = $is_active
                ? 'bg-blue-50 text-blue-700 border border-blue-200'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-700 border border-transparent';
            echo '<div class="pili-nav-content pl-6 pr-3 py-2 rounded-lg cursor-pointer transition-all duration-200 ' . $content_classes . '">';

            $desc = $section['desc'] ?? '';
            if ( ! empty( $desc ) ) {
                echo '<div class="flex items-center">';
                $this->render_menu_icon( $icon, 'mr-2 flex-shrink-0 text-sm' );
                echo '<div class="flex-1 min-w-0">';
                echo '<span class="font-medium text-sm truncate block">' . esc_html( $title ) . '</span>';
                $desc_color = $is_active ? 'text-blue-600' : 'text-gray-500';
                echo '<div class="pili-nav-desc text-xs ' . $desc_color . ' mt-1 truncate">' . esc_html( $desc ) . '</div>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="flex items-center">';
                $this->render_menu_icon( $icon, 'mr-2 flex-shrink-0 text-sm' );
                echo '<span class="font-medium text-sm truncate">' . esc_html( $title ) . '</span>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }

        /**
         * 渲染单独菜单项（无子菜单）
         *
         * @since 1.0
         * @param array $group 菜单数据
         * @param int $group_index 组索引
         */
        public function render_single_menu_item( $group, $group_index ) {
            $section = $group['section'];
            $index = $group['index'];
            $first_valid_index = $this->get_first_valid_section_index();
            $is_active = false;
            $icon = $section['icon'] ?? 'dashicons-admin-generic';
            $title = $section['title'] ?? sprintf( /* translators: %d: section index */ pili__( '区块 %d' ), $index + 1 );
            $desc = $section['desc'] ?? '';

            $slug = $this->get_section_slug( $section, $index );
            echo '<div class="pili-nav-item relative" data-section="' . $index . '" data-slug="' . esc_attr( $slug ) . '">';

            echo '<div class="pili-nav-indicator absolute left-0 top-0 bottom-0 w-1 rounded-r transition-all duration-300 ' . ($is_active ? 'opacity-100' : 'opacity-0') . '"></div>';

            $content_classes = $is_active
                ? 'bg-blue-50 text-blue-700 border border-blue-200'
                : 'text-gray-700 hover:bg-gray-50 border border-transparent';
            echo '<div class="pili-nav-content pl-4 pr-3 py-3 rounded-lg cursor-pointer transition-all duration-200 ' . $content_classes . '">';

            if ( ! empty( $desc ) ) {
                echo '<div class="flex items-center">';
                $this->render_menu_icon( $icon, 'mr-3 flex-shrink-0 text-base' );
                echo '<div class="flex-1 min-w-0">';
                echo '<span class="font-medium text-sm truncate block">' . esc_html( $title ) . '</span>';
                $desc_color = $is_active ? 'text-blue-600' : 'text-gray-500';
                echo '<div class="pili-nav-desc text-xs ' . $desc_color . ' mt-1 truncate">' . esc_html( $desc ) . '</div>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="flex items-center">';
                $this->render_menu_icon( $icon, 'mr-3 flex-shrink-0 text-base' );
                echo '<span class="font-medium text-sm truncate">' . esc_html( $title ) . '</span>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }

        /**
         * AJAX导出配置
         */
        public function ajax_export() {
            $this->use_bound_instance();
            if ( ! $this->ajax_is_for_this_instance() ) {
                return;
            }
            if ( ! $this->verify_ajax_request() ) {
                return;
            }

            // 导出走域组装时也尽量 hydrate，避免空壳误导；读 option/域不依赖 pre_fields。
            $this->ensure_all_section_fields();

            $options = get_option( $this->unique, array() );
            if ( ! is_array( $options ) ) {
                $options = array();
            }
            // 停写后 live 别名可能从域拼装；优先读当前组装结果。
            if ( empty( $options ) && function_exists( 'pilidoc_pili_live_assemble_from_domains' )
                && (string) $this->unique === ( defined( 'PILIDOC_PILI_LIVE_OPTION_ID' ) ? PILIDOC_PILI_LIVE_OPTION_ID : 'pilidoc_pili_live_options' )
            ) {
                $assembled = pilidoc_pili_live_assemble_from_domains();
                if ( is_array( $assembled ) ) {
                    $options = $assembled;
                }
            }

            $export_data = array(
                'pili_framework_export' => true,
                'export_time'                      => current_time( 'mysql' ),
                'site_url'                         => home_url(),
                'option_id'                        => $this->unique,
                'framework_version'                => '1.0',
                'options'                          => $options,
            );

            wp_send_json_success(
                array(
                    'data'     => $export_data,
                    'filename' => sanitize_file_name( $this->unique . '-' . gmdate( 'Y-m-d-H-i-s' ) . '.json' ),
                )
            );
        }

        /**
         * AJAX导入配置
         */
        public function ajax_import() {
            $this->use_bound_instance();
            if ( ! $this->ajax_is_for_this_instance() ) {
                return;
            }
            if ( ! $this->verify_ajax_request() ) {
                return;
            }

            // 延迟分区：AJAX 上下文默认只有壳，导入前必须装齐字段表。
            $this->ensure_all_section_fields();

            $locked = false;
            if ( class_exists( '\PILI_Options_Import_Guard', false ) ) {
                if ( ! \PILI_Options_Import_Guard::acquire_lock() ) {
                    wp_send_json_error( array( 'message' => pili__( '另有导入正在进行，请稍后再试' ) ) );
                    return;
                }
                $locked = true;
            }

            try {
                if ( ! isset( $_POST['import_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
                    wp_send_json_error( array( 'message' => pili__( '没有找到导入数据' ) ) );
                    return;
                }

                $raw = wp_unslash( $_POST['import_data'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
                if ( ! is_string( $raw ) || '' === $raw ) {
                    wp_send_json_error( array( 'message' => pili__( '没有找到导入数据' ) ) );
                    return;
                }
                // 防止超大 JSON 拖垮 PHP 内存。
                if ( strlen( $raw ) > 5 * 1024 * 1024 ) {
                    wp_send_json_error( array( 'message' => pili__( '配置文件过大（上限 5MB）' ) ) );
                    return;
                }

                $import_data = json_decode( $raw, true );
                if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $import_data ) ) {
                    wp_send_json_error( array( 'message' => pili__( '配置文件格式错误' ) ) );
                    return;
                }

                // 业务轻校验 + 字段表就绪（fail-closed）；未加载 Guard 时回退原校验。
                if ( class_exists( '\PILI_Options_Import_Guard', false ) ) {
                    $gate = \PILI_Options_Import_Guard::lightweight_validate( $import_data, $this );
                    if ( is_wp_error( $gate ) ) {
                        wp_send_json_error( array( 'message' => $gate->get_error_message() ) );
                        return;
                    }
                } else {
                    if ( empty( $import_data['pili_framework_export'] ) ) {
                        wp_send_json_error( array( 'message' => pili__( '不是有效的配置文件' ) ) );
                        return;
                    }
                    if ( ! empty( $import_data['option_id'] ) && (string) $import_data['option_id'] !== (string) $this->unique ) {
                        wp_send_json_error( array( 'message' => pili__( '配置文件与当前选项不匹配' ) ) );
                        return;
                    }
                    if ( ! isset( $import_data['options'] ) || ! is_array( $import_data['options'] ) ) {
                        wp_send_json_error( array( 'message' => pili__( '配置文件中没有找到选项数据' ) ) );
                        return;
                    }
                    if ( empty( $this->pre_fields ) ) {
                        wp_send_json_error( array( 'message' => pili__( '配置字段尚未就绪，请刷新设置页后重试导入' ) ) );
                        return;
                    }
                }

                // 导入前快照（仅 1 份，autoload=no）。
                if ( class_exists( '\PILI_Options_Import_Guard', false ) ) {
                    \PILI_Options_Import_Guard::store_pre_snapshot( (string) $this->unique );
                }

                $options = $this->filter_imported_options( $import_data['options'] );
                // 导入为整包真源：须 replace_all，否则半包防护会残留旧域键。
                $GLOBALS['pili_options_split_replace_all'] = true;
                $saved = $this->save_options( $options );
                unset( $GLOBALS['pili_options_split_replace_all'] );
                if ( ! $saved ) {
                    wp_send_json_error( array( 'message' => pili__( '配置导入失败' ) ) );
                    return;
                }

                do_action( "xun_{$this->unique}_imported", $options, $this );

                $msg = pili__( '配置导入成功' );
                if ( class_exists( '\PILI_Options_Import_Guard', false ) ) {
                    $msg = \PILI_Options_Import_Guard::success_message_with_backup_note( $msg );
                }
                // 配置包不含会员授权（B3）。
                $msg .= ' ' . pili__( '配置包不含会员授权状态。' );
                wp_send_json_success( array( 'message' => $msg ) );
            } finally {
                if ( $locked && class_exists( '\PILI_Options_Import_Guard', false ) ) {
                    \PILI_Options_Import_Guard::release_lock();
                }
            }
        }

        /**
         * 仅保留本实例已声明字段 id，避免导入任意键污染 option。
         * 字段表为空时返回空数组（fail-closed）；调用方须先拒绝写入。
         *
         * @param array<string,mixed> $raw Raw options.
         * @return array<string,mixed>
         */
        protected function filter_imported_options( array $raw ) {
            $allowed = array();
            foreach ( (array) $this->pre_fields as $field ) {
                if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
                    continue;
                }
                $allowed[ $field['id'] ] = true;
            }
            // B1：注册表为空不再原样放行（防 replace_all 清空站）。
            if ( empty( $allowed ) ) {
                return array();
            }
            $out = array();
            foreach ( $raw as $key => $value ) {
                if ( isset( $allowed[ $key ] ) ) {
                    $out[ $key ] = $value;
                }
            }
            return $out;
        }

        /**
         * 统一校验 AJAX：capability + nonce。
         *
         * @return bool
         */
        protected function verify_ajax_request() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'message' => pili__( '权限不足' ) ), 403 );
                return false;
            }
            $nonce = '';
            if ( isset( $_POST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $nonce = sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } elseif ( isset( $_POST['nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $nonce = sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
            if ( '' === $nonce || ! $this->verify_PILI_Options_nonce( $nonce ) ) {
                wp_send_json_error( array( 'message' => pili__( '安全验证失败' ) ), 403 );
                return false;
            }
            return true;
        }

        /**
         * AJAX重置当前页面
         */
        public function ajax_reset_current() {
            $this->use_bound_instance();

            if ( ! $this->ajax_is_for_this_instance() ) {
                return;
            }

            if ( ! $this->verify_ajax_request() ) {
                return;
            }

            $section_index = isset( $_POST['section_index'] ) ? intval( $_POST['section_index'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            // 懒加载分区可能尚未 hydrate：按需装配字段，避免「页面不存在」误报。
            if ( ! isset( $this->sections[ $section_index ] ) && method_exists( $this, 'ensure_section_fields' ) ) {
                $this->ensure_section_fields( $section_index );
            }

            if ( ! isset( $this->sections[ $section_index ] ) ) {
                wp_send_json_error( array( 'message' => pili__( '页面不存在' ) ) );
            }

            $section         = $this->sections[ $section_index ];
            $current_options = get_option( $this->unique, array() );
            if ( ! is_array( $current_options ) ) {
                $current_options = array();
            }

            if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) {
                foreach ( $section['fields'] as $field ) {
                    if ( empty( $field['id'] ) ) {
                        continue;
                    }
                    $field_id = $field['id'];
                    if ( array_key_exists( 'default', $field ) ) {
                        $current_options[ $field_id ] = $field['default'];
                    } else {
                        unset( $current_options[ $field_id ] );
                    }
                }
            }

            // 重置须删键：Path A 默认半包防护，这里显式整包替换触及域。
            $GLOBALS['pili_options_split_replace_all'] = true;
            $reset_ok = $this->save_options( $current_options );
            unset( $GLOBALS['pili_options_split_replace_all'] );
            if ( ! $reset_ok ) {
                wp_send_json_error( array( 'message' => pili__( '重置失败' ) ) );
            }

            wp_send_json_success( array( 'message' => pili__( '当前页面已重置' ) ) );
        }

        /**
         * 同 ajax_ns 多 Options：请求带 option_id/unique 且非本实例时返回 false（调用方应静默 return）。
         *
         * @return bool True = 继续处理本请求。
         */
        protected function ajax_is_for_this_instance() {
            $posted_option_id = '';
            if ( isset( $_POST['option_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $posted_option_id = sanitize_key( wp_unslash( (string) $_POST['option_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } elseif ( isset( $_POST['unique'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $posted_option_id = sanitize_key( wp_unslash( (string) $_POST['unique'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
            if ( '' === $posted_option_id ) {
                return true;
            }
            return $posted_option_id === sanitize_key( (string) $this->unique );
        }

        /**
         * AJAX仅重置当前页面的空白项
         */
        public function ajax_reset_empty_current() {
            $this->use_bound_instance();
            if ( ! $this->ajax_is_for_this_instance() ) {
                return;
            }
            if ( ! $this->verify_ajax_request() ) {
                return;
            }

            $section_index = isset( $_POST['section_index'] ) ? intval( $_POST['section_index'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            if ( ! isset( $this->sections[ $section_index ] ) && method_exists( $this, 'ensure_section_fields' ) ) {
                $this->ensure_section_fields( $section_index );
            }

            if ( ! isset( $this->sections[ $section_index ] ) ) {
                wp_send_json_error( array( 'message' => pili__( '页面不存在' ) ) );
            }

            $section         = $this->sections[ $section_index ];
            $current_options = get_option( $this->unique, array() );
            if ( ! is_array( $current_options ) ) {
                $current_options = array();
            }
            $changed = 0;

            if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) {
                foreach ( $section['fields'] as $field ) {
                    if ( empty( $field['id'] ) ) {
                        continue;
                    }

                    $field_id      = $field['id'];
                    $current_value = $current_options[ $field_id ] ?? null;
                    $before        = $changed;
                    $new_value     = $this->fill_empty_with_defaults( $field, $current_value, $changed );

                    // 仅在 fill_empty_with_defaults 实际补齐时写回，避免对「无默认且缺失」的键误计数。
                    if ( $changed > $before ) {
                        $current_options[ $field_id ] = $new_value;
                    }
                }
            }

            if ( $changed < 1 ) {
                wp_send_json_success( array( 'message' => pili__( '当前页面没有可填充的空白项' ) ) );
            }

            if ( ! $this->save_options( $current_options ) ) {
                wp_send_json_error( array( 'message' => pili__( '补齐空白项失败' ) ) );
            }

            wp_send_json_success( array( 'message' => pili__( '空白项已按默认值补齐' ) ) );
        }

        /**
         * 判断字段值是否为空白。
         *
         * @param mixed  $value 当前值。
         * @param string $type  字段类型。
         * @return bool
         */
        protected function is_blank_field_value( $value, $type = '' ) {
            if ( is_null( $value ) ) {
                return true;
            }

            if ( is_string( $value ) ) {
                return trim( $value ) === '';
            }

            if ( is_array( $value ) ) {
                return $value === array();
            }

            if ( $type === 'media' && empty( $value ) ) {
                return true;
            }

            return false;
        }

        /**
         * 仅为当前空白字段填充默认值，不覆盖已有值。
         *
         * @param array $field   字段配置。
         * @param mixed $value   当前值。
         * @param int   $changed 变更计数器（引用）。
         * @return mixed
         */
        protected function fill_empty_with_defaults( $field, $value, &$changed ) {
            $type        = $field['type'] ?? '';
            $has_default = array_key_exists( 'default', $field );
            $default     = $has_default ? $field['default'] : null;

            if ( $type === 'repeater' ) {
                if ( $this->is_blank_field_value( $value, $type ) ) {
                    if ( $has_default ) {
                        $changed++;
                        return $default;
                    }
                    return is_array( $value ) ? $value : array();
                }

                if ( ! is_array( $value ) ) {
                    return $value;
                }

                $fields = $field['fields'] ?? array();
                if ( empty( $fields ) ) {
                    return $value;
                }

                foreach ( $value as $row_index => $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }
                    foreach ( $fields as $sub_field ) {
                        if ( empty( $sub_field['id'] ) ) {
                            continue;
                        }
                        $sub_id      = $sub_field['id'];
                        $sub_current = $row[ $sub_id ] ?? null;
                        $sub_new     = $this->fill_empty_with_defaults( $sub_field, $sub_current, $changed );
                        if ( $sub_new !== null ) {
                            $value[ $row_index ][ $sub_id ] = $sub_new;
                        }
                    }
                }

                return $value;
            }

            if ( $this->is_blank_field_value( $value, $type ) && $has_default ) {
                $changed++;
                return $default;
            }

            return $value;
        }

        /**
         * AJAX重置全部配置（仅清空本 Options 实例；不含独立数据表/队列）。
         */
        public function ajax_reset_all() {
            $this->use_bound_instance();
            if ( ! $this->ajax_is_for_this_instance() ) {
                return;
            }
            if ( ! $this->verify_ajax_request() ) {
                return;
            }

            // 域存储启用后必须清各 pilipost__*；仅 delete 旧大包会被 Migrate 的 pre_option 合并结果骗过。
            if ( class_exists( '\PILI_Options_Migrate' ) && \PILI_Options_Migrate::is_done()
                && class_exists( '\PILI_Options_Domain_Registry' ) && class_exists( '\PILI_Options_Domain_Store' ) ) {
                foreach ( \PILI_Options_Domain_Registry::domains() as $domain ) {
                    $opt = \PILI_Options_Domain_Registry::option_name( $domain );
                    delete_option( $opt );
                    wp_cache_delete( $opt, 'options' );
                }
                \PILI_Options_Domain_Store::flush_cache();
            }

            delete_option( $this->unique );
            wp_cache_delete( $this->unique, 'options' );

            if ( class_exists( '\PILI_Options_Domain_Store' ) ) {
                \PILI_Options_Domain_Store::flush_cache();
                $merged = \PILI_Options_Domain_Store::merge_all();
                if ( ! empty( $merged ) ) {
                    wp_send_json_error( array( 'message' => pili__( '重置失败' ) ) );
                }
            } else {
                // 已不存在也算成功（避免二次重置误报失败）。
                $left = get_option( $this->unique, null );
                if ( null !== $left && false !== $left && array() !== $left ) {
                    wp_send_json_error( array( 'message' => pili__( '重置失败' ) ) );
                }
            }

            $this->options = array();
            // save_defaults 在 migrate+lazy 下会直接 return；重置后必须手写默认再 split。
            $this->ensure_all_section_fields();
            $defaults = array();
            foreach ( $this->pre_fields as $field ) {
                if ( ! empty( $field['id'] ) && array_key_exists( 'default', $field ) ) {
                    $defaults[ $field['id'] ] = $field['default'];
                }
            }
            if ( ! empty( $defaults ) ) {
                $this->options = $defaults;
                $this->save_options( $this->options );
            }

            do_action( "xun_{$this->unique}_reset_all", $this );

            wp_send_json_success( array( 'message' => pili__( '所有配置已重置' ) ) );
        }
    }
}
