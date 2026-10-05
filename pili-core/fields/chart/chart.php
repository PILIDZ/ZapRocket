<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Field: chart
 *
 * 基于 ECharts 的图表展示字段。
 */
	class PILI_Field_chart extends PILI_Fields {

		public function render() {
			$args = wp_parse_args(
				$this->field,
				array(
					'height'        => 360,
					'theme'         => '',
					'renderer'      => 'canvas',
					'row_group'     => '',
					'columns'       => 1,
					'column_span'   => 1,
					'row_gap'       => 12,
					'chart_type'    => 'line',
					'title_text' => pili__( '图表' ),
					'subtitle_text' => '',
					'labels'        => array(
						pili__( '周一' ),
						pili__( '周二' ),
						pili__( '周三' ),
						pili__( '周四' ),
						pili__( '周五' ),
						pili__( '周六' ),
						pili__( '周日' ),
					),
					'series_name' => pili__( '系列' ),
					'series_data'   => array( 120, 200, 150, 80, 70, 110, 130 ),
					'option'        => array(),
					'data_callback' => '',
					'animate_entry' => true,
				)
			);

			$height = max( 180, (int) $args['height'] );
			$row_group = isset( $args['row_group'] ) ? sanitize_key( (string) $args['row_group'] ) : '';
			$columns = max( 1, (int) ( $args['columns'] ?? 1 ) );
			$column_span = max( 1, (int) ( $args['column_span'] ?? 1 ) );
			if ( $column_span > $columns ) {
				$column_span = $columns;
			}
			$row_gap = max( 0, (int) ( $args['row_gap'] ?? 12 ) );
			$lazy_data = $this->should_lazy_load_data( $args );
			$option    = array();
			if ( ! $lazy_data ) {
				try {
					$option = $this->resolve_chart_option( $args );
				} catch ( \Throwable $e ) {
					$option = array(
						'title' => array(
							'text' => pili__( '文本' ),
							'subtext' => $e->getMessage(),
							'left'    => 'center',
						),
					);
				}
				if ( ! is_array( $option ) ) {
					$option = array();
				}
			}
			$option_json = wp_json_encode( $option, JSON_UNESCAPED_UNICODE );
			if ( false === $option_json ) {
				$option_json = '{}';
			}
			$animate_entry = ! isset( $args['animate_entry'] ) || (bool) $args['animate_entry'];

			echo $this->field_before();
			echo '<div class="pili-chart-field" data-field-id="' . esc_attr( $this->field_id() ) . '" data-height="' . esc_attr( (string) $height ) . '" data-theme="' . esc_attr( (string) $args['theme'] ) . '" data-renderer="' . esc_attr( (string) $args['renderer'] ) . '" data-row-group="' . esc_attr( $row_group ) . '" data-columns="' . esc_attr( (string) $columns ) . '" data-column-span="' . esc_attr( (string) $column_span ) . '" data-row-gap="' . esc_attr( (string) $row_gap ) . '" data-animate-entry="' . ( $animate_entry ? '1' : '0' ) . '" data-option="' . esc_attr( $option_json ) . '"';
			if ( $lazy_data ) {
				echo ' data-lazy-data="1" data-lazy-field-id="' . esc_attr( (string) ( $this->field['id'] ?? '' ) ) . '" data-unique="' . esc_attr( $this->unique ) . '"';
			}
			echo '>';
			if ( $lazy_data ) {
				echo '<div class="pili-chart-lazy-placeholder flex items-center justify-center text-sm text-gray-500" style="width:100%;height:' . esc_attr( (string) $height ) . 'px;border:0;border-radius:10px;background:#fff;">' . pili_esc_html__( '图表数据加载中…' ) . '</div>';
			}
			echo '<div class="pili-chart-canvas" style="width:100%;height:' . esc_attr( (string) $height ) . 'px;' . ( $lazy_data ? 'display:none;' : '' ) . '"></div>';
			// JSON 用 script 标签承载，避免 data-option / esc_attr 转义破坏结构。
			echo '<script type="application/json" class="pili-chart-option">' . $option_json . '</script>';
			echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" id="' . esc_attr( $this->field_id() ) . '" value="' . esc_attr( $option_json ) . '" ' . $this->field_attributes() . ' />';
			echo '</div>';
			echo $this->field_after();
		}

		/**
		 * 是否延迟执行 data_callback（P3）。
		 *
		 * @param array<string,mixed> $args 字段参数。
		 * @return bool
		 */
		public function should_lazy_load_data( $args = null ) {
			$args = is_array( $args ) ? $args : $this->field;
			$data_callback = isset( $args['data_callback'] ) ? $args['data_callback'] : '';
			if ( ! is_string( $data_callback ) || '' === $data_callback || ! is_callable( $data_callback ) ) {
				return false;
			}
			// 显式关闭（含 false / 0 / '0' / ''），避免被 empty() 误判成开启。
			if ( array_key_exists( 'lazy_load', $args ) && ! $args['lazy_load'] ) {
				return false;
			}
			if ( ! empty( $args['lazy_load'] ) ) {
				return true;
			}
			return (bool) apply_filters( 'pili_PILI_Field_lazy_data_default', false, 'chart', $this->field, $this->unique );
		}

		/**
		 * AJAX：拉取 chart option。
		 *
		 * @param array<string,mixed> $field  字段配置。
		 * @param string              $unique options id。
		 * @return array<string,mixed>
		 */
		public static function ajax_resolve_option( $field, $unique ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) || 'chart' !== $field['type'] ) {
				return array();
			}
			$instance = new self( $field, array(), $unique, 'options' );
			$args     = wp_parse_args( $field, array() );
			return $instance->resolve_chart_option( $args );
		}

		private function resolve_chart_option( $args ) {
			$data_callback = isset( $args['data_callback'] ) ? $args['data_callback'] : '';
			if ( is_string( $data_callback ) && '' !== $data_callback && is_callable( $data_callback ) ) {
				$data = call_user_func( $data_callback, $this->field, $this->value, $this->unique, $this->where, $this->parent );
				if ( is_array( $data ) ) {
					return $data;
				}
			}

			if ( is_array( $this->value ) && ! empty( $this->value ) ) {
				return $this->value;
			}

			if ( is_string( $this->value ) && '' !== trim( $this->value ) ) {
				$decoded = json_decode( (string) $this->value, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}

			if ( isset( $args['option'] ) && is_array( $args['option'] ) && ! empty( $args['option'] ) ) {
				return $args['option'];
			}

			$labels      = isset( $args['labels'] ) && is_array( $args['labels'] ) ? $args['labels'] : array();
			$series_data = isset( $args['series_data'] ) && is_array( $args['series_data'] ) ? $args['series_data'] : array();
			$type        = sanitize_key( (string) $args['chart_type'] );
			$series_name = isset( $args['series_name'] ) ? (string) $args['series_name'] : pili__( '数据' );
			$title_text  = isset( $args['title_text'] ) ? (string) $args['title_text'] : '';
			$subtitle_text = isset( $args['subtitle_text'] ) ? (string) $args['subtitle_text'] : '';
			$title_block = array(
				'text'        => $title_text,
				'subtext'     => $subtitle_text,
				'left'        => 'left',
				'textStyle'   => array(
					'fontSize'   => 14,
					'fontWeight' => 600,
				),
				'subtextStyle' => array(
					'fontSize' => 12,
					'color'    => '#6b7280',
				),
			);

			if ( 'pie' === $type ) {
				$pie_data = array();
				foreach ( $labels as $idx => $label ) {
					$pie_data[] = array(
						'name'  => (string) $label,
						'value' => isset( $series_data[ $idx ] ) ? (float) $series_data[ $idx ] : 0,
					);
				}
				$pie_variant = isset( $args['pie_variant'] ) ? sanitize_key( (string) $args['pie_variant'] ) : 'solid';
				if ( function_exists( 'pili_chart_donut_option' ) && 'donut' === $pie_variant ) {
					return pili_chart_donut_option(
						$pie_data,
						array(
							'title'       => $title_text,
							'subtext'     => $subtitle_text,
							'series_name' => $series_name,
							'center_text' => isset( $args['center_text'] ) ? (string) $args['center_text'] : pili__( '合计' ),
							'color_map'   => 'palette',
						)
					);
				}
				if ( function_exists( 'pili_chart_pie_option' ) ) {
					return pili_chart_pie_option(
						$pie_data,
						array(
							'title'       => $title_text,
							'subtext'     => $subtitle_text,
							'series_name' => $series_name,
							'color_map'   => 'palette',
						)
					);
				}
				return array(
					'title'   => $title_block,
					'tooltip' => array( 'trigger' => 'item' ),
					'legend'  => array( 'top' => 'bottom' ),
					'series'  => array(
						array(
							'name'   => $series_name,
							'type'   => 'pie',
							'radius' => '60%',
							'data'   => $pie_data,
						),
					),
				);
			}

			$chart_type = in_array( $type, array( 'bar', 'line' ), true ) ? $type : 'line';
			if ( function_exists( 'pili_chart_apply_theme' ) && function_exists( 'pili_chart_style_line_series' ) && function_exists( 'pili_chart_style_bar_series' ) ) {
				$series = array();
				if ( 'bar' === $chart_type ) {
					$series[] = pili_chart_style_bar_series( $series_name, $series_data, 0 );
				} else {
					$series[] = pili_chart_style_line_series( $series_name, $series_data, 0, array( 'area' => true ) );
				}
				return pili_chart_apply_theme(
					array(
						'title'   => pili_chart_title_block( $title_text, $subtitle_text ),
						'tooltip' => array( 'trigger' => 'axis' ),
						'legend'  => pili_chart_legend_bottom( array( $series_name ) ),
						'grid'    => pili_chart_grid(),
						'xAxis'   => pili_chart_axis_category( $labels ),
						'yAxis'   => pili_chart_axis_value(),
						'series'  => $series,
					)
				);
			}
			return array(
				'title'   => $title_block,
				'tooltip' => array( 'trigger' => 'axis' ),
				'xAxis'   => array( 'type' => 'category', 'data' => array_values( $labels ) ),
				'yAxis'   => array( 'type' => 'value' ),
				'series'  => array(
					array(
						'name'      => $series_name,
						'type'      => $chart_type,
						'data'      => array_values( $series_data ),
						'smooth'    => 'line' === $chart_type,
						'areaStyle' => 'line' === $chart_type ? array() : null,
					),
				),
			);
		}

		public function validate( $value ) {
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				$value   = is_array( $decoded ) ? $decoded : array();
			}
			return $this->sanitize_option_tree( $value );
		}

		private function sanitize_option_tree( $value ) {
			if ( is_array( $value ) ) {
				$out = array();
				foreach ( $value as $k => $v ) {
					$key = is_string( $k ) ? sanitize_key( $k ) : (int) $k;
					$out[ $key ] = $this->sanitize_option_tree( $v );
				}
				return $out;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}
			return sanitize_text_field( (string) $value );
		}

		public function enqueue() {
			$ver = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : PILI_Setup::$version;
			$echarts_handle = 'pili-echarts';
			$echarts_rel    = '/assets/js/vendor/echarts/echarts.min.js';
			$echarts_path   = trailingslashit( (string) PILI_Setup::$dir ) . ltrim( $echarts_rel, '/' );
			$echarts_src    = trailingslashit( (string) PILI_Setup::$url ) . ltrim( $echarts_rel, '/' );

			// 仅使用包内本地 ECharts，禁止 CDN。
			// 不急载 echarts.min.js；chart.js 在分区可见时 ensureEcharts 动态插脚本。
			if ( is_readable( $echarts_path ) && ! wp_script_is( $echarts_handle, 'registered' ) ) {
				wp_register_script( $echarts_handle, $echarts_src, array(), $ver, true );
			}

			$handle = pili_asset_handle( 'field-chart' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/chart.css',
				array(),
				$ver
			);

			$chart_js_path = PILI_Setup::$dir . '/assets/js/fields/chart.js';
			$chart_js_ver  = is_readable( $chart_js_path ) ? (string) filemtime( $chart_js_path ) : $ver;

			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/chart.js',
				array( 'jquery' ),
				$chart_js_ver,
				true
			);

			pili_localize_bag(
				$handle,
				'chart',
				array(
					'init_error' => pili__( '图表初始化失败' ),
					'loader_error' => pili__( '本地 ECharts 加载失败' ),
					'hint' => pili__( '提示' ),
					'load_fail' => pili__( '图表加载失败' ),
					'ajax_missing' => pili__( '缺少 field_id 或 ajaxurl' ),
					'request_fail' => pili__( '图表数据请求失败' ),
					'no_data' => pili__( '暂无数据' ),
					'echarts_fail' => pili__( 'ECharts 加载失败' ),
					'echarts_src'     => is_readable( $echarts_path ) ? $echarts_src : '',
					'entry_animation' => true,
					'entry_duration'  => 800,
					'entry_easing'    => 'cubicOut',
					'entry_stagger'   => 0,
				)
			);
		}
	}
