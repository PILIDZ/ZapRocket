<?php
/**
 * Welcome page: same layout as PILI default, ZapRocket copy.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render ZapRocket welcome.
 *
 * @return void
 */
function zaprocket_render_welcome_page() {
	$logo = ZAPROCKET_URL . 'assets/img/danlogo.svg';
	$ver  = ZAPROCKET_VERSION;

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
	echo '<img class="w-16 h-16 logo-rotate" src="' . esc_url( $logo ) . '" alt="' . pili_esc_attr__( '品牌' ) . '">';
	echo '</span>';
	echo '</div>';
	echo '<h1 class="text-4xl font-bold mb-4 tracking-tight text-white" style="color:#ffffff!important;">' . pili_esc_html__( '欢迎使用闪电WP性能' ) . '</h1>';
	echo '<p class="text-blue-100 text-xl leading-relaxed mb-6">' . pili_esc_html__( '针对 WordPress 的性能优化：站点瘦身、CDN / 预载 / 懒加载 / 脚本、可选整页缓存、对象存储、数据库清理。' ) . '</p>';
	echo '<div class="flex flex-wrap justify-center gap-4 mt-8">';
	echo '<span class="inline-flex items-center rounded-full bg-green-500 bg-opacity-90 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm shadow-lg">';
	echo '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
	echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>';
	echo '</svg>';
	echo 'v' . esc_html( $ver ) . ' ' . pili_esc_html__( '稳定版' );
	echo '</span>';
	echo '<span class="inline-flex items-center rounded-full bg-blue-500 bg-opacity-90 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm shadow-lg">';
	echo '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
	echo '<path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>';
	echo '</svg>';
	echo pili_esc_html__( '高风险默认关' );
	echo '</span>';
	echo '<span class="inline-flex items-center rounded-full bg-purple-500 bg-opacity-90 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm shadow-lg">';
	echo '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
	echo '<path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>';
	echo '</svg>';
	echo pili_esc_html__( '对象存储' );
	echo '</span>';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	echo '<div class="p-8">';
	echo '<div class="grid md:grid-cols-3 gap-6 mb-8">';
	echo '<div class="text-center p-6 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-300 cursor-pointer feature-card" data-hover-border="#60a5fa" data-hover-bg="#eff6ff">';
	echo '<div class="mb-4">';
	echo '<span class="dashicons dashicons-hammer text-3xl text-blue-500"></span>';
	echo '</div>';
	echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '站点瘦身' ) . '</h3>';
	echo '<p class="text-gray-600 text-sm">' . pili_esc_html__( '页头冗余、Emoji、访客 REST、心跳与评论头像。不确定的项保持关闭。' ) . '</p>';
	echo '</div>';
	echo '<div class="text-center p-6 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-300 cursor-pointer feature-card" data-hover-border="#4ade80" data-hover-bg="#f0fdf4">';
	echo '<div class="mb-4">';
	echo '<span class="dashicons dashicons-performance text-3xl text-green-500"></span>';
	echo '</div>';
	echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '速度优化' ) . '</h3>';
	echo '<p class="text-gray-600 text-sm">' . pili_esc_html__( 'CDN、预载、懒加载、脚本压缩与延迟。压缩、Delay JS、整页缓存默认关。' ) . '</p>';
	echo '</div>';
	echo '<div class="text-center p-6 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-300 cursor-pointer feature-card" data-hover-border="#a855f7" data-hover-bg="#faf5ff">';
	echo '<div class="mb-4">';
	echo '<span class="dashicons dashicons-cloud text-3xl text-purple-500"></span>';
	echo '</div>';
	echo '<h3 class="text-lg font-semibold mb-2 text-gray-900">' . pili_esc_html__( '对象存储' ) . '</h3>';
	echo '<p class="text-gray-600 text-sm">' . pili_esc_html__( '阿里云、腾讯云、R2、七牛与兼容 S3。自定义域名、桶内前缀与一键迁移。' ) . '</p>';
	echo '</div>';
	echo '</div>';
	echo '<div class="mb-8">';
	echo '<h3 class="text-xl font-semibold mb-6 text-gray-900 text-center">' . pili_esc_html__( '核心能力一览' ) . '</h3>';
	echo '<div class="grid grid-cols-6 gap-4">';
	$field_types = array(
		array( 'icon' => 'dashicons-editor-removeformatting', 'name' => pili__( '页头清理' ), 'color' => 'text-blue-500' ),
		array( 'icon' => 'dashicons-dismiss', 'name' => pili__( '功能开关' ), 'color' => 'text-green-500' ),
		array( 'icon' => 'dashicons-admin-users', 'name' => pili__( '评论头像' ), 'color' => 'text-purple-500' ),
		array( 'icon' => 'dashicons-rest-api', 'name' => pili__( '访客接口' ), 'color' => 'text-red-500' ),
		array( 'icon' => 'dashicons-admin-site-alt3', 'name' => pili__( 'CDN加速' ), 'color' => 'text-blue-600' ),
		array( 'icon' => 'dashicons-download', 'name' => pili__( '资源预载' ), 'color' => 'text-yellow-600' ),
		array( 'icon' => 'dashicons-format-image', 'name' => pili__( '媒体优化' ), 'color' => 'text-green-600' ),
		array( 'icon' => 'dashicons-media-code', 'name' => pili__( '文件优化' ), 'color' => 'text-purple-600' ),
		array( 'icon' => 'dashicons-cloud', 'name' => pili__( '存储设置' ), 'color' => 'text-red-600' ),
		array( 'icon' => 'dashicons-migrate', 'name' => pili__( '一键迁移' ), 'color' => 'text-blue-700' ),
		array( 'icon' => 'dashicons-trash', 'name' => pili__( '垃圾清理' ), 'color' => 'text-green-700' ),
		array( 'icon' => 'dashicons-media-text', 'name' => pili__( '插件运行日志' ), 'color' => 'text-purple-700' ),
	);
	$colors = array(
		0  => array( 'border' => '#93c5fd', 'bg' => '#eff6ff' ),
		1  => array( 'border' => '#86efac', 'bg' => '#f0fdf4' ),
		2  => array( 'border' => '#c4b5fd', 'bg' => '#faf5ff' ),
		3  => array( 'border' => '#fca5a5', 'bg' => '#fef2f2' ),
		4  => array( 'border' => '#93c5fd', 'bg' => '#eff6ff' ),
		5  => array( 'border' => '#fde047', 'bg' => '#fefce8' ),
		6  => array( 'border' => '#86efac', 'bg' => '#f0fdf4' ),
		7  => array( 'border' => '#c4b5fd', 'bg' => '#faf5ff' ),
		8  => array( 'border' => '#fca5a5', 'bg' => '#fef2f2' ),
		9  => array( 'border' => '#93c5fd', 'bg' => '#eff6ff' ),
		10 => array( 'border' => '#86efac', 'bg' => '#f0fdf4' ),
		11 => array( 'border' => '#c4b5fd', 'bg' => '#faf5ff' ),
	);
	foreach ( $field_types as $index => $field ) {
		$color = $colors[ $index ] ?? array( 'border' => '#d1d5db', 'bg' => '#f9fafb' );
		echo '<div class="text-center p-3 bg-gray-50 rounded-lg border-2 border-gray-200 transition-all duration-200 cursor-pointer field-type-card" data-hover-border="' . esc_attr( $color['border'] ) . '" data-hover-bg="' . esc_attr( $color['bg'] ) . '">';
		echo '<span class="dashicons ' . esc_attr( $field['icon'] ) . ' text-xl ' . esc_attr( $field['color'] ) . '"></span>';
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
                            card.style.borderColor = "#d1d5db";
                            card.style.backgroundColor = "#ffffff";
                        } else {
                            card.style.borderColor = "#d1d5db";
                            card.style.backgroundColor = "#f9fafb";
                        }
                    });
                });
                const openDev = document.querySelector(".zr-welcome-open-dev");
                if (openDev) {
                    openDev.addEventListener("click", function(e) {
                        e.preventDefault();
                        const fold = document.getElementById("zr-sidebar-dev-fold");
                        if (!fold) {
                            return;
                        }
                        fold.open = true;
                        fold.scrollIntoView({ behavior: "smooth", block: "nearest" });
                    });
                }
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
	echo '<span>' . pili_esc_html__( '打开「概览」总开关，需要时套用低风险推荐' ) . '</span>';
	echo '</div>';
	echo '<div class="flex items-start">';
	echo '<span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs rounded-full mr-3 mt-0.5 flex-shrink-0">2</span>';
	echo '<span>' . pili_esc_html__( '瘦身与速度按需打开。Delay JS、压缩、整页缓存确认站点后再开' ) . '</span>';
	echo '</div>';
	echo '<div class="flex items-start">';
	echo '<span class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs rounded-full mr-3 mt-0.5 flex-shrink-0">3</span>';
	echo '<span>' . pili_esc_html__( '对象存储先测试连接再迁移；数据库清理前请备份' ) . '</span>';
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
	echo '<span>' . pili_esc_html__( '不要与 WP Rocket、LiteSpeed Cache 等同时开同类缓存或压缩' ) . '</span>';
	echo '</div>';
	echo '<div class="flex items-start">';
	echo '<span class="dashicons dashicons-yes text-green-600 mr-2 mt-0.5 flex-shrink-0"></span>';
	echo '<span>' . pili_esc_html__( '历史媒体用「一键迁移」；只改正文死链用「快捷操作」' ) . '</span>';
	echo '</div>';
	echo '<div class="flex items-start">';
	echo '<span class="dashicons dashicons-yes text-green-600 mr-2 mt-0.5 flex-shrink-0"></span>';
	echo '<span>' . pili_esc_html__( '运行日志记录失败与清理结果，前台缓存命中不会写入' ) . '</span>';
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
	echo '<span class="font-medium text-gray-900">' . esc_html( get_bloginfo( 'version' ) ) . '</span>';
	echo '</div>';
	echo '</div>';

	echo '<div class="bg-white rounded-lg p-4 border border-gray-200">';
	echo '<div class="flex items-center justify-between">';
	echo '<span class="text-gray-600">' . pili_esc_html__( 'PHP 版本' ) . '</span>';
	echo '<span class="font-medium text-gray-900">' . esc_html( PHP_VERSION ) . '</span>';
	echo '</div>';
	echo '</div>';

	echo '<div class="bg-white rounded-lg p-4 border border-gray-200">';
	echo '<div class="flex items-center justify-between">';
	echo '<span class="text-gray-600">' . pili_esc_html__( '当前主题' ) . '</span>';
	echo '<span class="font-medium text-gray-900">' . esc_html( wp_get_theme()->get( 'Name' ) ) . '</span>';
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
	echo '<p class="text-gray-600 text-sm mb-4">' . pili_esc_html__( '更多插件产品与说明' ) . '</p>';
	echo '<a href="https://www.pilipost.net/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors duration-200" style="color: white !important;">';
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
	echo '<p class="text-gray-600 text-sm mb-4">' . pili_esc_html__( '点击后展开侧栏「定制插件联系开发者」二维码' ) . '</p>';
	echo '<button type="button" class="zr-welcome-open-dev inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900 transition-colors duration-200" style="color: white !important;">';
	echo '<span class="dashicons dashicons-smartphone mr-1 text-white"></span>';
	echo '<span class="text-white">' . pili_esc_html__( '联系我们' ) . '</span>';
	echo '</button>';
	echo '</div>';

	echo '</div>';

	echo '<div class="mt-8 pt-6 border-t border-gray-200">';
	echo '<div class="text-center text-gray-500 text-sm mb-4">';
	echo '<p class="mb-2">';
	echo '<a href="https://www.pilipost.net/" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 transition-colors duration-200 font-medium">' . pili_esc_html__( '闪电WP性能' ) . '</a>';
	echo ' | ' . pili_esc_html__( '霹雳设计出品' );
	echo '</p>';
	echo '<p class="text-xs text-gray-400">' . pili_esc_html__( '高风险项默认关闭，按需打开后再保存' ) . '</p>';
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '</div>';
}
