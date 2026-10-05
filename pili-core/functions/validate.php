<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PILI Framework 数据验证函数
 *
 * @package PILI Framework
 * @since   1.0
 */

if ( ! function_exists( 'pili_validate_required' ) ) {
	function pili_validate_required( $value, $field ) {
		if ( empty( $field['required'] ) ) {
			return true;
		}
		if ( empty( $value ) && $value !== '0' && $value !== 0 ) {
			$title = isset( $field['title'] ) ? $field['title'] : pili__( '此字段' );
			return sprintf(
				/* translators: %s: field title */
				pili__( '%s是必填项' ),
				$title
			);
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_email' ) ) {
	function pili_validate_email( $value, $field ) {
		if ( empty( $value ) ) {
			return true;
		}
		if ( ! is_email( $value ) ) {
			return pili__( '请输入有效的邮箱地址' );
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_url' ) ) {
	function pili_validate_url( $value, $field ) {
		if ( empty( $value ) ) {
			return true;
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return pili__( '请输入有效的URL地址' );
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_number_range' ) ) {
	function pili_validate_number_range( $value, $field ) {
		if ( empty( $value ) && $value !== '0' && $value !== 0 ) {
			return true;
		}
		$number = floatval( $value );
		if ( isset( $field['min'] ) && $number < $field['min'] ) {
			return sprintf(
				/* translators: %s: minimum value */
				pili__( '值不能小于 %s' ),
				$field['min']
			);
		}
		if ( isset( $field['max'] ) && $number > $field['max'] ) {
			return sprintf(
				/* translators: %s: maximum value */
				pili__( '值不能大于 %s' ),
				$field['max']
			);
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_string_length' ) ) {
	function pili_validate_string_length( $value, $field ) {
		if ( empty( $value ) ) {
			return true;
		}
		$length = strlen( $value );
		if ( isset( $field['min_length'] ) && $length < $field['min_length'] ) {
			return sprintf(
				/* translators: %d: minimum length */
				pili__( '长度不能少于 %d 个字符' ),
				(int) $field['min_length']
			);
		}
		if ( isset( $field['max_length'] ) && $length > $field['max_length'] ) {
			return sprintf(
				/* translators: %d: maximum length */
				pili__( '长度不能超过 %d 个字符' ),
				(int) $field['max_length']
			);
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_pattern' ) ) {
	function pili_validate_pattern( $value, $field ) {
		if ( empty( $value ) || empty( $field['pattern'] ) ) {
			return true;
		}
		if ( ! preg_match( $field['pattern'], $value ) ) {
			$message = isset( $field['pattern_message'] ) ? $field['pattern_message'] : pili__( '输入格式不正确' );
			return $message;
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_color' ) ) {
	function pili_validate_color( $value, $field ) {
		if ( empty( $value ) ) {
			return true;
		}
		if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
			return true;
		}
		if ( preg_match( '/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/', $value ) ) {
			return true;
		}
		if ( preg_match( '/^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/', $value ) ) {
			return true;
		}
		return pili__( '请输入有效的颜色值' );
	}
}

if ( ! function_exists( 'pili_validate_options' ) ) {
	function pili_validate_options( $value, $field ) {
		if ( empty( $value ) || empty( $field['options'] ) ) {
			return true;
		}
		$options = function_exists( 'pili_resolve_field_options' )
			? pili_resolve_field_options( $field['options'] )
			: ( is_array( $field['options'] ) ? $field['options'] : array() );
		if ( empty( $options ) ) {
			return true;
		}
		$flat = array();
		foreach ( $options as $k => $opt ) {
			if ( is_array( $opt ) && ! array_key_exists( 'label', $opt ) && ! array_key_exists( 'text', $opt ) ) {
				foreach ( $opt as $sk => $sl ) {
					$flat[ (string) $sk ] = true;
				}
			} else {
				$flat[ (string) $k ] = true;
			}
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $val ) {
				if ( ! isset( $flat[ (string) $val ] ) ) {
					return pili__( '选择的值无效' );
				}
			}
		} else {
			if ( ! isset( $flat[ (string) $value ] ) ) {
				return pili__( '选择的值无效' );
			}
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_file_type' ) ) {
	function pili_validate_file_type( $value, $field ) {
		if ( empty( $value ) || empty( $field['allowed_types'] ) ) {
			return true;
		}
		$file_info     = pathinfo( $value );
		$extension     = isset( $file_info['extension'] ) ? strtolower( $file_info['extension'] ) : '';
		$allowed_types = $field['allowed_types'];
		if ( is_string( $allowed_types ) ) {
			$allowed_types = explode( ',', $allowed_types );
		}
		$allowed_types = array_map( 'trim', $allowed_types );
		$allowed_types = array_map( 'strtolower', $allowed_types );
		if ( ! in_array( $extension, $allowed_types, true ) ) {
			return sprintf(
				/* translators: %s: allowed file extensions */
				pili__( '不支持的文件类型。允许的类型：%s' ),
				implode( ', ', $allowed_types )
			);
		}
		return true;
	}
}

if ( ! function_exists( 'pili_validate_date' ) ) {
	function pili_validate_date( $value, $field ) {
		if ( empty( $value ) ) {
			return true;
		}
		$format = isset( $field['date_format'] ) ? $field['date_format'] : 'Y-m-d';
		$date   = \DateTime::createFromFormat( $format, $value );
		if ( ! $date || $date->format( $format ) !== $value ) {
			return sprintf(
				/* translators: %s: date format */
				pili__( '请输入有效的日期格式：%s' ),
				$format
			);
		}
		return true;
	}
}

/**
 * 通用字段验证函数
 *
 * @since 1.0
 *
 * @param mixed $value 要验证的值
 * @param array $field 字段配置
 *
 * @return array 验证结果数组，包含是否通过和错误信息
 */
if ( ! function_exists( 'pili_validate_field' ) ) {
	function pili_validate_field( $value, $field ) {
		$errors = array();

		$result = pili_validate_required( $value, $field );
		if ( $result !== true ) {
			$errors[] = $result;
		}

		if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {
			foreach ( $field['validate'] as $validation ) {
				$function_name = 'pili_validate_' . $validation;
				if ( function_exists( $function_name ) ) {
					$result = call_user_func( $function_name, $value, $field );
					if ( $result !== true ) {
						$errors[] = $result;
					}
				}
			}
		}

		$auto_validations = array();
		if ( isset( $field['min'] ) || isset( $field['max'] ) ) {
			$auto_validations[] = 'number_range';
		}
		if ( isset( $field['min_length'] ) || isset( $field['max_length'] ) ) {
			$auto_validations[] = 'string_length';
		}
		if ( ! empty( $field['pattern'] ) ) {
			$auto_validations[] = 'pattern';
		}
		if ( ! empty( $field['options'] ) ) {
			$auto_validations[] = 'options';
		}
		if ( ! empty( $field['allowed_types'] ) ) {
			$auto_validations[] = 'file_type';
		}

		foreach ( $auto_validations as $validation ) {
			$function_name = 'pili_validate_' . $validation;
			if ( function_exists( $function_name ) ) {
				$result = call_user_func( $function_name, $value, $field );
				if ( $result !== true ) {
					$errors[] = $result;
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}
}
