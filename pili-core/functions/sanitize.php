<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 数据清理函数
 * 
 * 这个文件包含了框架中使用的各种数据清理和验证函数。
 * 这些函数确保用户输入的数据是安全和有效的。
 * 
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */

/**
 * 清理文本字段
 * 
 * 清理和验证文本输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的文本
 */
if ( ! function_exists( 'pili_sanitize_text' ) ) {
    function pili_sanitize_text( $value ) {
        return sanitize_text_field( $value );
    }
}

/**
 * 清理文本域字段
 * 
 * 清理和验证文本域输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的文本
 */
if ( ! function_exists( 'pili_sanitize_textarea' ) ) {
    function pili_sanitize_textarea( $value ) {
        return sanitize_textarea_field( $value );
    }
}

/**
 * 清理邮箱字段
 * 
 * 清理和验证邮箱输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的邮箱地址
 */
if ( ! function_exists( 'pili_sanitize_email' ) ) {
    function pili_sanitize_email( $value ) {
        return sanitize_email( $value );
    }
}

/**
 * 清理URL字段
 * 
 * 清理和验证URL输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的URL
 */
if ( ! function_exists( 'pili_sanitize_url' ) ) {
    function pili_sanitize_url( $value ) {
        return esc_url_raw( $value );
    }
}

/**
 * 清理数字字段
 * 
 * 清理和验证数字输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * @param array $field 可选字段配置（用于 precision 等）。
 *
 * @return int|float|string 清理后的数值；空输入返回空字符串。
 */
if ( ! function_exists( 'pili_sanitize_number' ) ) {
    function pili_sanitize_number( $value, $field = array() ) {
        if ( $value === '' || $value === null ) {
            return '';
        }
        if ( is_string( $value ) ) {
            $value = sanitize_text_field( str_replace( ',', '', $value ) );
        }
        if ( ! is_numeric( $value ) ) {
            return 0;
        }
        $numeric = (float) $value;
        if ( is_array( $field ) && isset( $field['precision'] ) && is_numeric( $field['precision'] ) && (int) $field['precision'] >= 0 ) {
            $numeric = round( $numeric, (int) $field['precision'] );
        }
        return $numeric;
    }
}

/**
 * 清理浮点数字段
 * 
 * 清理和验证浮点数输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return float 清理后的浮点数
 */
if ( ! function_exists( 'pili_sanitize_float' ) ) {
    function pili_sanitize_float( $value ) {
        return floatval( $value );
    }
}

/**
 * 清理布尔值字段
 * 
 * 清理和验证布尔值输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return bool 清理后的布尔值
 */
if ( ! function_exists( 'pili_sanitize_boolean' ) ) {
    function pili_sanitize_boolean( $value ) {
        return (bool) $value;
    }
}

/**
 * 清理颜色值字段
 * 
 * 清理和验证颜色值输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的颜色值
 */
if ( ! function_exists( 'pili_sanitize_color' ) ) {
    function pili_sanitize_color( $value ) {
        $value = trim( $value );
        if ( empty( $value ) ) {
            return '';
        }
        if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
            return $value;
        }
        if ( preg_match( '/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/', $value ) ) {
            return $value;
        }
        if ( preg_match( '/^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/', $value ) ) {
            return $value;
        }
        return '';
    }
}

/**
 * 清理数组字段
 * 
 * 清理和验证数组输入字段的值。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return array 清理后的数组
 */
if ( ! function_exists( 'pili_sanitize_array' ) ) {
    function pili_sanitize_array( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        $sanitized = array();
        foreach ( $value as $key => $val ) {
            $key = sanitize_key( $key );
            if ( is_array( $val ) ) {
                $sanitized[ $key ] = pili_sanitize_array( $val );
            } else {
                $sanitized[ $key ] = sanitize_text_field( $val );
            }
        }
        return $sanitized;
    }
}

/**
 * 清理HTML内容
 * 
 * 清理HTML内容，允许安全的HTML标签。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的HTML内容
 */
if ( ! function_exists( 'pili_sanitize_html' ) ) {
    function pili_sanitize_html( $value ) {
        $allowed_tags = array(
            'a'      => array(
                'href'   => array(),
                'title'  => array(),
                'target' => array(),
            ),
            'br'     => array(),
            'em'     => array(),
            'strong' => array(),
            'p'      => array(),
            'ul'     => array(),
            'ol'     => array(),
            'li'     => array(),
            'h1'     => array(),
            'h2'     => array(),
            'h3'     => array(),
            'h4'     => array(),
            'h5'     => array(),
            'h6'     => array(),
        );
        return wp_kses( $value, $allowed_tags );
    }
}

/**
 * 清理CSS代码
 * 
 * 清理CSS代码，移除危险的内容。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的CSS代码
 */
if ( ! function_exists( 'pili_sanitize_css' ) ) {
    function pili_sanitize_css( $value ) {
        $value = preg_replace( '/javascript:/i', '', $value );
        $value = preg_replace( '/expression\s*\(/i', '', $value );
        $value = preg_replace( '/@import/i', '', $value );
        return strip_tags( $value );
    }
}

/**
 * 清理文件名
 * 
 * 清理文件名，确保安全性。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的文件名
 */
if ( ! function_exists( 'pili_sanitize_filename' ) ) {
    function pili_sanitize_filename( $value ) {
        return sanitize_file_name( $value );
    }
}

/**
 * 清理键名
 * 
 * 清理数组键名或选项名称。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的键名
 */
if ( ! function_exists( 'pili_sanitize_key' ) ) {
    function pili_sanitize_key( $value ) {
        return sanitize_key( $value );
    }
}

/**
 * 清理用户名
 * 
 * 清理用户名输入。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的用户名
 */
if ( ! function_exists( 'pili_sanitize_user' ) ) {
    function pili_sanitize_user( $value ) {
        return sanitize_user( $value );
    }
}

/**
 * 清理标题
 * 
 * 清理标题文本。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * 
 * @return string 清理后的标题
 */
if ( ! function_exists( 'pili_sanitize_title' ) ) {
    function pili_sanitize_title( $value ) {
        return sanitize_title( $value );
    }
}

/**
 * 通用字段清理函数
 * 
 * 根据字段类型自动选择合适的清理函数。
 * 
 * @since 1.0
 * 
 * @param mixed $value 要清理的值
 * @param array $field 字段配置
 * 
 * @return mixed 清理后的值
 */
if ( ! function_exists( 'pili_sanitize_field' ) ) {
    function pili_sanitize_field( $value, $field ) {
        $field_type = isset( $field['type'] ) ? $field['type'] : 'text';
        switch ( $field_type ) {
            case 'text':
                return pili_sanitize_text( $value );
            case 'textarea':
                return pili_sanitize_textarea( $value );
            case 'email':
                return pili_sanitize_email( $value );
            case 'url':
                return pili_sanitize_url( $value );
            case 'number':
                return pili_sanitize_number( $value, $field );
            case 'color':
                return pili_sanitize_color( $value );
            case 'checkbox':
            case 'switcher':
                return pili_sanitize_boolean( $value );
            case 'select':
            case 'radio':
				$opts = function_exists( 'pili_resolve_field_options' )
					? pili_resolve_field_options( isset( $field['options'] ) ? $field['options'] : array() )
					: ( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array() );
                if ( ( 'radio' === $field_type && ! empty( $field['multiple'] ) ) || ( 'select' === $field_type && ! empty( $field['multiple'] ) ) ) {
                    if ( ! is_array( $value ) ) {
                        $value = ( '' === $value || null === $value ) ? array() : array( $value );
                    }
                    $flat = array();
                    foreach ( $opts as $k => $opt ) {
                        if ( is_array( $opt ) && ! array_key_exists( 'label', $opt ) && ! array_key_exists( 'text', $opt ) ) {
                            foreach ( $opt as $sk => $sl ) {
                                if ( ! is_array( $sl ) || array_key_exists( 'label', $sl ) || array_key_exists( 'text', $sl ) ) {
                                    $flat[ (string) $sk ] = true;
                                }
                            }
                        } else {
                            $flat[ (string) $k ] = true;
                        }
                    }
                    $out = array();
                    foreach ( $value as $v ) {
                        $v = is_scalar( $v ) ? (string) $v : '';
                        if ( '' !== $v && isset( $flat[ $v ] ) ) {
                            $out[] = $v;
                        }
                    }
                    return array_values( array_unique( $out ) );
                }
                if ( ! empty( $opts ) ) {
					$val = is_scalar( $value ) ? (string) $value : '';
                    if ( array_key_exists( $val, $opts ) ) {
                        return $val;
                    }
					// 分组选项。
					foreach ( $opts as $opt ) {
						if ( is_array( $opt ) && ! array_key_exists( 'label', $opt ) && array_key_exists( $val, $opt ) ) {
							return $val;
						}
					}
                }
                return '';
            case 'media':
                return pili_sanitize_media( $value, $field );
            default:
                return apply_filters( "pili_sanitize_{$field_type}", $value, $field );
        }
    }
}

/**
 * 清理媒体字段值
 *
 * 验证和清理媒体选择器字段的数据，确保所有附件ID有效且用户有权限访问。
 *
 * @since 1.1.0
 *
 * @param mixed $value 要清理的值
 * @param array $field 字段配置
 *
 * @return array 清理后的媒体数据数组
 */
if ( ! function_exists( 'pili_sanitize_media' ) ) {
    function pili_sanitize_media( $value, $field = array() ) {
        if ( empty( $value ) ) {
            return array();
        }
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $value = $decoded;
            } else {
                $ids = array_filter( array_map( 'intval', explode( ',', $value ) ) );
                $value = array();
                foreach ( $ids as $id ) {
                    $attachment_data = pili_get_attachment_data( $id );
                    if ( $attachment_data ) {
                        $value[] = $attachment_data;
                    }
                }
            }
        }
        if ( is_array( $value ) ) {
            $validated_attachments = array();
            foreach ( $value as $attachment ) {
                if ( is_array( $attachment ) && ! empty( $attachment['id'] ) ) {
                    $attachment_id = intval( $attachment['id'] );
                    if ( ! get_post( $attachment_id ) ) {
                        continue;
                    }
                    if ( ! current_user_can( 'read_post', $attachment_id ) ) {
                        continue;
                    }
                    if ( ! empty( $field['library'] ) && is_array( $field['library'] ) ) {
                        $attachment_type = get_post_mime_type( $attachment_id );
                        $type_allowed = false;
                        foreach ( $field['library'] as $allowed_type ) {
                            if ( strpos( $attachment_type, $allowed_type ) === 0 ) {
                                $type_allowed = true;
                                break;
                            }
                        }
                        if ( ! $type_allowed ) {
                            continue;
                        }
                    }
                    $attachment_data = pili_get_attachment_data( $attachment_id );
                    if ( $attachment_data ) {
                        $validated_attachments[] = $attachment_data;
                    }
                }
            }
            if ( ! empty( $field['max_files'] ) && is_numeric( $field['max_files'] ) ) {
                $max_files = intval( $field['max_files'] );
                if ( $max_files > 0 && count( $validated_attachments ) > $max_files ) {
                    $validated_attachments = array_slice( $validated_attachments, 0, $max_files );
                }
            }
            return $validated_attachments;
        }
        return array();
    }
}

/**
 * 获取附件数据
 *
 * 根据附件ID获取完整的附件信息，用于媒体字段。
 *
 * @since 1.1.0
 *
 * @param int $attachment_id 附件ID
 *
 * @return array|false 附件数据数组或false
 */
if ( ! function_exists( 'pili_get_attachment_data' ) ) {
    function pili_get_attachment_data( $attachment_id ) {
        $attachment_id = intval( $attachment_id );
        if ( ! $attachment_id || ! get_post( $attachment_id ) ) {
            return false;
        }
        $attachment = get_post( $attachment_id );
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $data = array(
            'id'          => $attachment_id,
            'title'       => get_the_title( $attachment_id ),
            'filename'    => basename( get_attached_file( $attachment_id ) ),
            'url'         => wp_get_attachment_url( $attachment_id ),
            'type'        => get_post_mime_type( $attachment_id ),
            'subtype'     => '',
            'filesize'    => 0,
            'width'       => 0,
            'height'      => 0,
            'thumbnail'   => '',
            'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            'description' => $attachment->post_content,
            'caption'     => $attachment->post_excerpt,
        );
        if ( $data['type'] ) {
            $type_parts = explode( '/', $data['type'] );
            $data['subtype'] = isset( $type_parts[1] ) ? $type_parts[1] : '';
        }
        $file_path = get_attached_file( $attachment_id );
        if ( $file_path && file_exists( $file_path ) ) {
            $data['filesize'] = filesize( $file_path );
        }
        if ( wp_attachment_is_image( $attachment_id ) ) {
            if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
                $data['width'] = $metadata['width'];
                $data['height'] = $metadata['height'];
            }
            $thumbnail_url = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
            if ( $thumbnail_url ) {
                $data['thumbnail'] = $thumbnail_url[0];
            }
        }
        return $data;
    }
}
