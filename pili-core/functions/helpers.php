<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 辅助函数
 * 
 * 这个文件包含了框架中使用的各种辅助函数。
 * 这些函数提供了通用的功能，可以在框架的各个部分中使用。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */

/**
 * 数组搜索函数
 * 
 * 在多维数组中搜索指定键值对。
 * 
 * @since 1.0
 * 
 * @param array  $array 要搜索的数组
 * @param string $key   要搜索的键名
 * @param mixed  $value 要搜索的值
 * 
 * @return array 匹配的结果数组
 */
if ( ! function_exists( 'pili_array_search' ) ) {
    function pili_array_search( $array, $key, $value ) {
        
        $results = array();
        
        if ( is_array( $array ) ) {
            if ( isset( $array[ $key ] ) && $array[ $key ] == $value ) {
                $results[] = $array;
            }
            
            foreach ( $array as $sub_array ) {
                $results = array_merge( $results, pili_array_search( $sub_array, $key, $value ) );
            }
        }
        
        return $results;
    }
}

/**
 * 解析字段 options（支持关联数组 / 函数名 / Closure / [Class, method]）。
 *
 * @param mixed $options Raw field options.
 * @return array<string|int,mixed>
 */
if ( ! function_exists( 'pili_resolve_field_options' ) ) {
	function pili_resolve_field_options( $options ) {
		// [ 'Class', 'method' ]：类未加载时 is_callable 为 false，切勿当 option map（否则 UI 显示类名）。
		if (
			is_array( $options )
			&& isset( $options[0], $options[1] )
			&& is_string( $options[0] )
			&& is_string( $options[1] )
			&& 2 === count( $options )
		) {
			$cb = array( $options[0], $options[1] );
			if ( is_callable( $cb ) ) {
				$resolved = call_user_func( $cb );
				return is_array( $resolved ) ? $resolved : array();
			}
			return array();
		}
		if ( is_callable( $options ) ) {
			$resolved = call_user_func( $options );
			return is_array( $resolved ) ? $resolved : array();
		}
		if ( is_string( $options ) && '' !== $options && function_exists( $options ) ) {
			$resolved = call_user_func( $options );
			return is_array( $resolved ) ? $resolved : array();
		}
		return is_array( $options ) ? $options : array();
	}
}

/**
 * 超时检查函数
 * 
 * 检查当前时间与开始时间的差值是否小于超时时间。
 * 
 * @since 1.0
 * 
 * @param float $timenow   当前时间
 * @param float $starttime 开始时间
 * @param int   $timeout   超时时间（秒）
 * 
 * @return bool 是否未超时
 */
if ( ! function_exists( 'pili_timeout' ) ) {
    function pili_timeout( $timenow, $starttime, $timeout = 30 ) {
        return ( ( $timenow - $starttime ) < $timeout ) ? true : false;
    }
}

/**
 * 获取选项值函数
 * 
 * 从指定的选项组中获取特定字段的值。
 * 
 * @since 1.0
 * 
 * @param string $option_name 选项组名称
 * @param string $field_id    字段ID
 * @param mixed  $default     默认值
 * 
 * @return mixed 字段值
 */
if ( ! function_exists( 'pili_get_option' ) ) {
    function pili_get_option( $option_name, $field_id = '', $default = '' ) {
        
        $options = get_option( $option_name, array() );
        
        if ( empty( $field_id ) ) {
            return $options;
        }
        
        if ( isset( $options[ $field_id ] ) ) {
            return $options[ $field_id ];
        }
        
        return $default;
    }
}

/**
 * 设置选项值函数
 * 
 * 设置指定选项组中特定字段的值。
 * 
 * @since 1.0
 * 
 * @param string $option_name 选项组名称
 * @param string $field_id    字段ID
 * @param mixed  $value       要设置的值
 * 
 * @return bool 是否设置成功
 */
if ( ! function_exists( 'pili_set_option' ) ) {
    function pili_set_option( $option_name, $field_id, $value ) {
        
        $options = get_option( $option_name, array() );
        $options[ $field_id ] = $value;
        
        return update_option( $option_name, $options );
    }
}

/**
 * 多维数组合并函数
 * 
 * 递归合并多维数组，保持数组结构。
 * 
 * @since 1.0
 * 
 * @param array $array1 第一个数组
 * @param array $array2 第二个数组
 * 
 * @return array 合并后的数组
 */
if ( ! function_exists( 'pili_array_merge_recursive' ) ) {
    function pili_array_merge_recursive( $array1, $array2 ) {
        
        if ( ! is_array( $array1 ) || ! is_array( $array2 ) ) {
            return $array2;
        }
        
        foreach ( $array2 as $key => $value ) {
            if ( isset( $array1[ $key ] ) && is_array( $array1[ $key ] ) && is_array( $value ) ) {
                $array1[ $key ] = pili_array_merge_recursive( $array1[ $key ], $value );
            } else {
                $array1[ $key ] = $value;
            }
        }
        
        return $array1;
    }
}

/**
 * 字符串转换为数组函数
 * 
 * 将字符串按指定分隔符转换为数组，并清理空值。
 * 
 * @since 1.0
 * 
 * @param string $string    要转换的字符串
 * @param string $delimiter 分隔符
 * 
 * @return array 转换后的数组
 */
if ( ! function_exists( 'pili_string_to_array' ) ) {
    function pili_string_to_array( $string, $delimiter = ',' ) {
        
        if ( ! is_string( $string ) ) {
            return array();
        }
        
        $array = explode( $delimiter, $string );
        $array = array_map( 'trim', $array );
        $array = array_filter( $array );
        
        return $array;
    }
}

/**
 * 数组转换为字符串函数
 * 
 * 将数组按指定分隔符转换为字符串。
 * 
 * @since 1.0
 * 
 * @param array  $array     要转换的数组
 * @param string $delimiter 分隔符
 * 
 * @return string 转换后的字符串
 */
if ( ! function_exists( 'pili_array_to_string' ) ) {
    function pili_array_to_string( $array, $delimiter = ',' ) {
        
        if ( ! is_array( $array ) ) {
            return '';
        }
        
        return implode( $delimiter, $array );
    }
}

/**
 * 检查是否为有效URL函数
 * 
 * 检查给定的字符串是否为有效的URL。
 * 
 * @since 1.0
 * 
 * @param string $url 要检查的URL
 * 
 * @return bool 是否为有效URL
 */
if ( ! function_exists( 'pili_is_valid_url' ) ) {
    function pili_is_valid_url( $url ) {
        return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
    }
}

/**
 * 检查是否为有效邮箱函数
 * 
 * 检查给定的字符串是否为有效的邮箱地址。
 * 
 * @since 1.0
 * 
 * @param string $email 要检查的邮箱
 * 
 * @return bool 是否为有效邮箱
 */
if ( ! function_exists( 'pili_is_valid_email' ) ) {
    function pili_is_valid_email( $email ) {
        return is_email( $email );
    }
}

/**
 * 生成随机字符串函数
 * 
 * 生成指定长度的随机字符串。
 * 
 * @since 1.0
 * 
 * @param int    $length 字符串长度
 * @param string $chars  可用字符集
 * 
 * @return string 随机字符串
 */
if ( ! function_exists( 'pili_generate_random_string' ) ) {
    function pili_generate_random_string( $length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ) {
        
        $string = '';
        $chars_length = strlen( $chars );
        
        for ( $i = 0; $i < $length; $i++ ) {
            $string .= $chars[ rand( 0, $chars_length - 1 ) ];
        }
        
        return $string;
    }
}

/**
 * 格式化文件大小函数
 * 
 * 将字节数转换为人类可读的文件大小格式。
 * 
 * @since 1.0
 * 
 * @param int $bytes 字节数
 * @param int $precision 小数位数
 * 
 * @return string 格式化后的文件大小
 */
if ( ! function_exists( 'pili_format_file_size' ) ) {
    function pili_format_file_size( $bytes, $precision = 2 ) {
        
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
        
        for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }
        
        return round( $bytes, $precision ) . ' ' . $units[ $i ];
    }
}

/**
 * 调试输出函数
 * 
 * 在开发模式下输出调试信息。
 * 
 * @since 1.0
 * 
 * @param mixed  $data 要输出的数据
 * @param string $title 标题
 * @param bool   $exit 是否退出执行
 */
if ( ! function_exists( 'pili_debug' ) ) {
    function pili_debug( $data, $title = 'Debug', $exit = false ) {
        
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        echo '<div style="background: #f1f1f1; border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
        echo '<h4>' . esc_html( $title ) . '</h4>';
        echo '<pre>' . print_r( $data, true ) . '</pre>';
        echo '</div>';
        
        if ( $exit ) {
            exit;
        }
    }
}

/**
 * 获取当前页面URL函数
 *
 * 获取当前页面的完整URL。
 *
 * @since 1.0
 *
 * @return string 当前页面URL
 */
if ( ! function_exists( 'pili_get_current_url' ) ) {
    function pili_get_current_url() {

        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];

        return $protocol . $host . $uri;
    }
}

/**
 * 获取图标HTML函数
 *
 * 获取指定名称的Heroicons图标HTML代码。
 * 这是XUN::icon()方法的函数版本，方便在模板中使用。
 *
 * @since 1.0
 *
 * @param string $name       图标名称（如：academic-cap）
 * @param array  $attributes 图标属性配置
 *
 * @return string 图标HTML代码
 */
if ( ! function_exists( 'pili_icon' ) ) {
    function pili_icon( $name, $attributes = array() ) {

        if ( ! class_exists( PILI::class ) ) {
            return '';
        }

        return PILI::icon( $name, $attributes );
    }
}

/**
 * 输出图标HTML函数
 *
 * 直接输出指定名称的图标HTML代码。
 * 这是XUN::the_icon()方法的函数版本，方便在模板中使用。
 *
 * @since 1.0
 *
 * @param string $name       图标名称
 * @param array  $attributes 图标属性配置
 */
if ( ! function_exists( 'pili_the_icon' ) ) {
    function pili_the_icon( $name, $attributes = array() ) {

        echo pili_icon( $name, $attributes );
    }
}

/**
 * 检查图标是否存在函数
 *
 * 检查指定的图标是否存在于图标库中。
 *
 * @since 1.0
 *
 * @param string $name  图标名称
 * @param string $size  图标尺寸（16、20、24）
 * @param string $style 图标样式（outline、solid）
 *
 * @return bool 图标是否存在
 */
if ( ! function_exists( 'pili_icon_exists' ) ) {
    function pili_icon_exists( $name, $size = '24', $style = 'outline' ) {

        if ( ! class_exists( PILI::class ) ) {
            return false;
        }

        return PILI::icon_exists( $name, $size, $style );
    }
}

/**
 * 获取可用图标列表函数
 *
 * 获取指定尺寸和样式下所有可用的图标名称列表。
 *
 * @since 1.0
 *
 * @param string $size  图标尺寸（16、20、24）
 * @param string $style 图标样式（outline、solid）
 *
 * @return array 图标名称数组
 */
if ( ! function_exists( 'pili_get_available_icons' ) ) {
    function pili_get_available_icons( $size = '24', $style = 'outline' ) {

        if ( ! class_exists( PILI::class ) ) {
            return array();
        }

        return PILI::get_available_icons( $size, $style );
    }
}

/**
 * 搜索图标函数
 *
 * 根据关键词搜索匹配的图标名称。
 *
 * @since 1.0
 *
 * @param string $keyword 搜索关键词
 * @param string $size    图标尺寸（16、20、24）
 * @param string $style   图标样式（outline、solid）
 *
 * @return array 匹配的图标名称数组
 */
if ( ! function_exists( 'pili_search_icons' ) ) {
    function pili_search_icons( $keyword, $size = '24', $style = 'outline' ) {

        if ( ! class_exists( PILI::class ) ) {
            return array();
        }

        return PILI::search_icons( $keyword, $size, $style );
    }
}

/**
 * 获取图标统计信息函数
 *
 * 获取图标库的统计信息，包括各种尺寸和样式的图标数量。
 *
 * @since 1.0
 *
 * @return array 统计信息数组
 */
if ( ! function_exists( 'pili_get_icon_stats' ) ) {
    function pili_get_icon_stats() {

        if ( ! class_exists( __NAMESPACE__ . '\PILI_Icons' ) ) {
            return array();
        }

        return PILI_Icons::get_icon_stats();
    }
}

/**
 * 渲染字段图标函数
 *
 * 渲染字段标题中的图标，支持多种图标类型。
 *
 * @since 1.0
 *
 * @param string $icon_config 图标配置
 *
 * @return string 图标HTML代码
 */
if ( ! function_exists( 'pili_render_field_icon' ) ) {
    function pili_render_field_icon( $icon_config ) {

        if ( ! class_exists( PILI::class ) ) {
            return '';
        }

        return PILI::render_field_icon( $icon_config );
    }
}

/**
 * 输出字段图标函数
 *
 * 直接输出字段图标HTML代码。
 *
 * @since 1.0
 *
 * @param string $icon_config 图标配置
 */
if ( ! function_exists( 'pili_the_field_icon' ) ) {
    function pili_the_field_icon( $icon_config ) {
        echo pili_render_field_icon( $icon_config );
    }
}

/**
 * 清除图标缓存函数
 *
 * 清除所有图标相关的缓存数据。
 *
 * @since 1.0
 *
 * @return bool 是否清除成功
 */
if ( ! function_exists( 'pili_clear_icon_cache' ) ) {
    function pili_clear_icon_cache() {

        if ( ! class_exists( __NAMESPACE__ . '\PILI_Icons' ) ) {
            return false;
        }

        return PILI_Icons::clear_cache();
    }
}
