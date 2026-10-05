<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Field: inputs_grid
 *
 * 多个单行文本（或 number）输入项；可选在相邻输入框之间插入 Remix Icon（与 icon 字段同源：ri-* + Remix_Icons）。
 *
 * @package Xun Framework
 */
	class PILI_Field_inputs_grid extends PILI_Fields {

		/**
		 * @param string $name 完整类名如 ri-add-line。
		 *
		 * @return bool
		 */
		public static function is_valid_remix_icon( $name ) {
			if ( ! is_string( $name ) || $name === '' ) {
				return false;
			}
			if ( ! preg_match( '/^ri-[a-z0-9-]+$/', $name ) ) {
				return false;
			}
			if ( ! class_exists( 'Remix_Icons' ) ) {
				return false;
			}
			return Remix_Icons::icon_exists( $name );
		}

		/**
		 * 保存时清洗为稳定数组（仅保留 keys 白名单）
		 *
		 * @param mixed $value 原始提交值。
		 * @param array $field 字段配置。
		 *
		 * @return array
		 */
		public static function sanitize_stored_value( $value, $field ) {
			$keys = isset( $field['keys'] ) && is_array( $field['keys'] ) ? $field['keys'] : array();
			$allowed = array_keys( $keys );
			if ( empty( $allowed ) ) {
				return array();
			}

			$defaults = ( isset( $field['default'] ) && is_array( $field['default'] ) ) ? $field['default'] : array();
			$compute  = isset( $field['inputs_grid_compute'] ) && is_array( $field['inputs_grid_compute'] ) ? $field['inputs_grid_compute'] : null;

			if ( null !== $compute && ! empty( $compute['legacy_scalar_maps_to_result'] ) && ! empty( $compute['result_key'] ) && ! is_array( $value ) && is_numeric( $value ) ) {
				$rk = (string) $compute['result_key'];
				$n  = max( 0, (int) $value );
				$value = array( $rk => $n > 0 ? (string) $n : '' );
			} elseif ( ! is_array( $value ) ) {
				$value = array();
			}

			$out = array();
			foreach ( $allowed as $key ) {
				if ( isset( $value[ $key ] ) ) {
					$raw = $value[ $key ];
				} elseif ( isset( $defaults[ $key ] ) ) {
					$raw = $defaults[ $key ];
				} else {
					$raw = '';
				}

				$out[ $key ] = sanitize_text_field( (string) $raw );
			}

			if ( null !== $compute && isset( $compute['type'] ) && 'product_round' === $compute['type'] ) {
				$out = self::apply_product_round_to_grid( $out, $compute );
			}

			return $out;
		}

		/**
		 * 解析 inputs_grid 中用户输入的数字字符串（支持逗号作小数点）。
		 *
		 * @param mixed $s 原始值。
		 */
		public static function parse_numeric_string( $s ) {
			$s = is_string( $s ) ? trim( str_replace( ',', '.', $s ) ) : '';
			if ( '' === $s ) {
				return 0.0;
			}
			return (float) $s;
		}

		/**
		 * @param array<string,string> $grid
		 * @param string[]               $factor_keys
		 */
		private static function factors_all_empty_strings( array $grid, array $factor_keys ) {
			foreach ( $factor_keys as $fk ) {
				$fk = (string) $fk;
				if ( isset( $grid[ $fk ] ) && trim( (string) $grid[ $fk ] ) !== '' ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * 乘积四舍五入为整数；任一因子 ≤0 或无法解析则返回 null。
		 *
		 * @param array<string,string> $grid    已按白名单清洗后的键值。
		 * @param array<string,mixed>  $compute 字段上的 inputs_grid_compute 配置。
		 * @return int|null
		 */
		public static function compute_product_round_result( array $grid, array $compute ) {
			if ( ! isset( $compute['type'] ) || 'product_round' !== $compute['type'] ) {
				return null;
			}
			$factors = isset( $compute['factor_keys'] ) && is_array( $compute['factor_keys'] ) ? $compute['factor_keys'] : array();
			if ( empty( $factors ) ) {
				return null;
			}
			$prod = 1.0;
			foreach ( $factors as $fk ) {
				$fk = (string) $fk;
				$x  = self::parse_numeric_string( isset( $grid[ $fk ] ) ? $grid[ $fk ] : '' );
				if ( $x <= 0 ) {
					return null;
				}
				$prod *= $x;
			}
			$max = isset( $compute['clamp_max'] ) ? (int) $compute['clamp_max'] : 999999;
			$max = max( 1, $max );
			$n   = (int) round( $prod );
			return max( 0, min( $max, $n ) );
		}

		/**
		 * 写入 result_key；与 sanitize_stored_value 内逻辑一致，供保存钩子二次确认。
		 *
		 * @param array<string,string> $out
		 * @param array<string,mixed>  $compute
		 * @return array<string,string>
		 */
		public static function apply_product_round_to_grid( array $out, array $compute ) {
			$rk = isset( $compute['result_key'] ) ? (string) $compute['result_key'] : '';
			if ( '' === $rk ) {
				return $out;
			}
			$computed = self::compute_product_round_result( $out, $compute );
			if ( null !== $computed ) {
				$out[ $rk ] = (string) $computed;
				return $out;
			}
			$factors = isset( $compute['factor_keys'] ) && is_array( $compute['factor_keys'] ) ? $compute['factor_keys'] : array();
			if ( ! empty( $compute['preserve_result_if_factors_empty'] ) && self::factors_all_empty_strings( $out, $factors ) ) {
				return $out;
			}
			$out[ $rk ] = '0';
			return $out;
		}

		/**
		 * 从已清洗的 grid 得到用于业务判断的整型结果（如扣费积分）：优先乘积结果，其次兼容仅结果格有值的旧数据。
		 *
		 * @param array<string,string> $grid
		 * @param array<string,mixed>  $compute
		 */
		public static function effective_integer_from_computed_grid( array $grid, array $compute ) {
			$computed = self::compute_product_round_result( $grid, $compute );
			if ( null !== $computed && $computed >= 1 ) {
				return $computed;
			}
			$rk = isset( $compute['result_key'] ) ? (string) $compute['result_key'] : '';
			if ( '' === $rk ) {
				return 0;
			}
			$factors = isset( $compute['factor_keys'] ) && is_array( $compute['factor_keys'] ) ? $compute['factor_keys'] : array();
			if ( ! empty( $compute['preserve_result_if_factors_empty'] ) && self::factors_all_empty_strings( $grid, $factors ) ) {
				$max = isset( $compute['clamp_max'] ) ? (int) $compute['clamp_max'] : 999999;
				$max = max( 1, $max );
				$pp  = (int) round( self::parse_numeric_string( isset( $grid[ $rk ] ) ? $grid[ $rk ] : '' ) );
				$pp  = max( 0, min( $max, $pp ) );
				return $pp >= 1 ? $pp : 0;
			}
			return 0;
		}

		public function validate( $value ) {
			return self::sanitize_stored_value( $value, $this->field );
		}

		/**
		 * @param array $between_icons Raw between_icons from field config.
		 * @param int   $gap_count     count(keys) - 1.
		 *
		 * @return array<string>
		 */
		private function normalize_between_icons( $between_icons, $gap_count ) {
			if ( $gap_count < 1 ) {
				return array();
			}
			if ( ! is_array( $between_icons ) ) {
				return array_fill( 0, $gap_count, '' );
			}
			$between_icons = array_values( $between_icons );
			if ( count( $between_icons ) < $gap_count ) {
				$between_icons = array_pad( $between_icons, $gap_count, '' );
			} else {
				$between_icons = array_slice( $between_icons, 0, $gap_count );
			}
			return $between_icons;
		}

		/**
		 * @param array<string> $normalized_between Icons already trimmed to gap_count entries.
		 *
		 * @return bool
		 */
		private function has_any_valid_between_icon( $normalized_between ) {
			foreach ( $normalized_between as $icon ) {
				if ( is_string( $icon ) && $icon !== '' && self::is_valid_remix_icon( $icon ) ) {
					return true;
				}
			}
			return false;
		}

		public function enqueue() {
			parent::enqueue();

			$compute = isset( $this->field['inputs_grid_compute'] ) && is_array( $this->field['inputs_grid_compute'] ) ? $this->field['inputs_grid_compute'] : null;
			if ( null !== $compute && isset( $compute['type'] ) && 'product_round' === $compute['type'] ) {
				$compute_handle = pili_asset_handle( 'field-inputs-grid-compute' );
				wp_enqueue_script(
					$compute_handle,
					PILI_Setup::$url . '/assets/js/fields/inputs-grid-compute.js',
					array( 'jquery' ),
					PILI_Setup::$version,
					true
				);
			}

			$keys = isset( $this->field['keys'] ) && is_array( $this->field['keys'] ) ? $this->field['keys'] : array();
			$n    = count( $keys );
			if ( $n < 2 ) {
				return;
			}
			$between = isset( $this->field['between_icons'] ) ? $this->field['between_icons'] : array();
			$between = $this->normalize_between_icons( $between, $n - 1 );
			if ( ! $this->has_any_valid_between_icon( $between ) ) {
				return;
			}
			if ( class_exists( 'Remix_Icons' ) ) {
				Remix_Icons::enqueue_style();
			}
		}

		public function render() {
			$keys = isset( $this->field['keys'] ) && is_array( $this->field['keys'] ) ? $this->field['keys'] : array();

			$defaults = array();
			if ( ! empty( $this->field['default'] ) && is_array( $this->field['default'] ) ) {
				$defaults = $this->field['default'];
			}

			$coerce = isset( $this->field['inputs_grid_compute'] ) && is_array( $this->field['inputs_grid_compute'] ) ? $this->field['inputs_grid_compute'] : null;
			if ( null !== $coerce && ! empty( $coerce['legacy_scalar_maps_to_result'] ) && ! empty( $coerce['result_key'] ) && ! is_array( $this->value ) && is_numeric( $this->value ) ) {
				$rk         = (string) $coerce['result_key'];
				$n          = max( 0, (int) $this->value );
				$this->value = array( $rk => $n > 0 ? (string) $n : '' );
			} elseif ( ! is_array( $this->value ) ) {
				$this->value = array();
			}

			$this->value = wp_parse_args( $this->value, $defaults );

			$columns = isset( $this->field['columns'] ) ? (int) $this->field['columns'] : 4;
			$columns = min( 16, max( 1, $columns ) );

			$gap = isset( $this->field['gap'] ) ? (string) $this->field['gap'] : '0.75rem';
			$gap = preg_match( '/^[0-9.]+(px|rem|em|%)$/', $gap ) ? $gap : '0.75rem';

			$subsection_title = isset( $this->field['subsection_title'] ) ? (string) $this->field['subsection_title'] : '';
			$input_type       = isset( $this->field['input_type'] ) ? (string) $this->field['input_type'] : 'text';
			if ( ! in_array( $input_type, array( 'text', 'number' ), true ) ) {
				$input_type = 'text';
			}

			$placeholders = isset( $this->field['placeholders'] ) && is_array( $this->field['placeholders'] ) ? $this->field['placeholders'] : array();

			$input_attributes = array();
			if ( ! empty( $this->field['input_attributes'] ) && is_array( $this->field['input_attributes'] ) ) {
				$input_attributes = $this->field['input_attributes'];
			}

			$icon_size = isset( $this->field['between_icon_size'] ) ? (string) $this->field['between_icon_size'] : '20';
			if ( ! preg_match( '/^[0-9]{1,3}$/', $icon_size ) ) {
				$icon_size = '20';
			}

			$n             = count( $keys );
			$between_icons = $this->normalize_between_icons(
				isset( $this->field['between_icons'] ) ? $this->field['between_icons'] : array(),
				max( 0, $n - 1 )
			);
			$use_flex      = $n > 1 && $this->has_any_valid_between_icon( $between_icons );

			echo $this->field_before();

			$compute_attr = '';
			if ( ! empty( $this->field['inputs_grid_compute'] ) && is_array( $this->field['inputs_grid_compute'] ) ) {
				$compute_attr = ' data-pili-inputs-grid-compute="' . esc_attr( wp_json_encode( $this->field['inputs_grid_compute'] ) ) . '"';
			}

			echo '<div class="pili-inputs-grid-field"' . $compute_attr . '>';

			if ( empty( $keys ) ) {
				echo '<p class="text-sm text-amber-800 m-0">' . pili_esc_html__( 'inputs_grid：请在字段配置中设置 keys（键名 => 标签）。' ) . '</p>';
				echo '</div>';
				echo $this->field_after();
				return;
			}

			if ( '' !== $subsection_title ) {
				echo '<h4 class="text-sm font-medium text-gray-700 mb-3">' . esc_html( $subsection_title ) . '</h4>';
			}

			$container_style = $use_flex
				? sprintf(
					'display:flex;flex-wrap:wrap;align-items:flex-start;gap:%s;width:100%%;box-sizing:border-box;',
					$gap
				)
				: sprintf(
					'display:grid;grid-template-columns:repeat(%d,minmax(0,1fr));gap:%s;width:100%%;box-sizing:border-box;',
					$columns,
					$gap
				);

			$class = $use_flex ? 'pili-inputs-grid pili-inputs-grid--flex' : 'pili-inputs-grid';

			echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $container_style ) . '">';

			$gap_index = 0;
			foreach ( $keys as $key => $label ) {
				$key = (string) $key;

				$cell_style = $use_flex ? 'flex:1 1 0;min-width:0;' : '';
				$cell_class = 'min-w-0 pili-inputs-grid-cell' . ( $use_flex ? ' flex flex-col' : '' );
				echo '<div class="' . esc_attr( $cell_class ) . '"' . ( $cell_style !== '' ? ' style="' . esc_attr( $cell_style ) . '"' : '' ) . '>';

				echo '<label class="block text-xs font-medium text-gray-600 mb-1" for="' . esc_attr( $this->field_id() . '_' . $key ) . '">' . esc_html( $label ) . '</label>';

				$base_attrs = array();
				if ( 'number' === $input_type ) {
					$base_attrs = array(
						'type'      => 'number',
						'inputmode' => 'decimal',
					);
				}

				$sub_field = array(
					'id'          => $key,
					'type'        => 'text',
					'placeholder' => isset( $placeholders[ $key ] ) ? (string) $placeholders[ $key ] : '',
					'attributes'  => wp_parse_args( $input_attributes, $base_attrs ),
				);

				$sub_field['id']   = $this->field_id() . '_' . $key;
				$sub_field['name'] = $this->field_name( '[' . $key . ']' );

				$cell_value = isset( $this->value[ $key ] ) ? $this->value[ $key ] : '';
				PILI::field( $sub_field, $cell_value, '', '' );

				echo '</div>';

				if ( $use_flex && $gap_index < $n - 1 ) {
					$ic = isset( $between_icons[ $gap_index ] ) ? $between_icons[ $gap_index ] : '';
					if ( self::is_valid_remix_icon( $ic ) && class_exists( 'Remix_Icons' ) ) {
						// 与左侧「标签 + text 字段 mt-2 + h-10 输入框」同结构，保证图标与输入框垂直居中对齐。
						echo '<div class="pili-inputs-grid-sep shrink-0 flex flex-col text-gray-500" aria-hidden="true">';
						echo '<div class="block text-xs font-medium text-gray-600 mb-1 select-none overflow-hidden leading-snug" style="visibility:hidden;">' . "\xc2\xa0" . '</div>';
						echo '<div class="mt-2 flex items-center justify-center leading-none" style="min-height:2.5rem;">';
						echo Remix_Icons::get_icon(
							$ic,
							array(
								'size'  => $icon_size,
								'class' => 'flex-shrink-0 block leading-none',
							)
						);
						echo '</div>';
						echo '</div>';
					}
				}
				$gap_index++;
			}

			echo '</div>';
			echo '</div>';

			echo $this->field_after();
		}
	}

if ( class_exists( __NAMESPACE__ . '\PILI_Field_inputs_grid' ) ) {
	add_filter( 'pili_validate_field_inputs_grid', [ __NAMESPACE__ . '\PILI_Field_inputs_grid', 'sanitize_stored_value' ], 10, 2 );
}
