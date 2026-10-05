<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILIDOC 后台 ECharts 主题 — 统一配色与 option 片段
 *
 * 对齐 Tailwind 色板与安全仪表台参考稿，避免 ECharts 默认色。
 */

if ( ! function_exists( 'pili_chart_palette' ) ) {
	/**
	 * @return array<int,string>
	 */
	function pili_chart_palette() {
		return array(
			'#3b82f6', // blue-500
			'#10b981', // emerald-500
			'#8b5cf6', // violet-500
			'#f59e0b', // amber-500
			'#06b6d4', // cyan-500
			'#ec4899', // pink-500
			'#6366f1', // indigo-500
			'#14b8a6', // teal-500
		);
	}
}

if ( ! function_exists( 'pili_chart_color_at' ) ) {
	/**
	 * @param int $index
	 * @return string
	 */
	function pili_chart_color_at( $index ) {
		$palette = pili_chart_palette();
		$index   = (int) $index;
		if ( empty( $palette ) ) {
			return '#3b82f6';
		}
		return $palette[ $index % count( $palette ) ];
	}
}

if ( ! function_exists( 'pili_chart_risk_level_color' ) ) {
	/**
	 * 风险等级名称 → 语义色（高红 / 中橙 / 低黄 / 提示蓝）。
	 *
	 * @param string $label
	 * @return string
	 */
	function pili_chart_risk_level_color( $label ) {
		$tier = function_exists( 'pili_security_normalize_risk_tier' )
			? pili_security_normalize_risk_tier( $label )
			: trim( (string) $label );

		switch ( $tier ) {
			case '高风险':
				return '#ef4444';
			case '中风险':
				return '#f97316';
			case '低风险':
				return '#eab308';
			case '提示':
				return '#3b82f6';
		}

		$text = mb_strtolower( trim( (string) $label ) );
		if ( '' === $text ) {
			return '#9ca3af';
		}
		if ( false !== mb_strpos( $text, '高' ) || 'high' === $text ) {
			return '#ef4444';
		}
		if ( false !== mb_strpos( $text, '中' ) || 'medium' === $text ) {
			return '#f97316';
		}
		if ( false !== mb_strpos( $text, '低' ) || 'low' === $text ) {
			return '#eab308';
		}
		if ( false !== mb_strpos( $text, '提示' ) || false !== mb_strpos( $text, '信息' ) || 'info' === $text ) {
			return '#3b82f6';
		}
		return '#6b7280';
	}
}

if ( ! function_exists( 'pili_chart_title_block' ) ) {
	/**
	 * @param string $text
	 * @param string $subtext
	 * @return array<string,mixed>
	 */
	function pili_chart_title_block( $text, $subtext = '' ) {
		return array(
			'text'         => (string) $text,
			'subtext'      => (string) $subtext,
			'left'         => 'left',
			'textStyle'    => array(
				'fontSize'   => 14,
				'fontWeight' => 600,
				'color'      => '#1f2937',
			),
			'subtextStyle' => array(
				'fontSize' => 12,
				'color'    => '#6b7280',
			),
		);
	}
}

if ( ! function_exists( 'pili_chart_grid' ) ) {
	/**
	 * @param string $top
	 * @return array<string,mixed>
	 */
	function pili_chart_grid( $top = '40px' ) {
		return array(
			'left'          => '3%',
			'right'         => '4%',
			'bottom'        => '3%',
			'top'           => (string) $top,
			'containLabel'  => true,
		);
	}
}

if ( ! function_exists( 'pili_chart_axis_category' ) ) {
	/**
	 * @param array<int,string> $data
	 * @return array<string,mixed>
	 */
	function pili_chart_axis_category( array $data ) {
		return array(
			'type'        => 'category',
			'boundaryGap' => false,
			'data'        => array_values( $data ),
			'axisLine'    => array(
				'lineStyle' => array( 'color' => '#e5e7eb' ),
			),
			'axisLabel'   => array(
				'color'    => '#9ca3af',
				'fontSize' => 11,
			),
			'axisTick'    => array( 'show' => false ),
		);
	}
}

if ( ! function_exists( 'pili_chart_axis_value' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function pili_chart_axis_value() {
		return array(
			'type'      => 'value',
			'splitLine' => array(
				'lineStyle' => array(
					'color' => '#f3f4f6',
					'type'  => 'dashed',
				),
			),
			'axisLabel' => array(
				'color'    => '#9ca3af',
				'fontSize' => 11,
			),
		);
	}
}

if ( ! function_exists( 'pili_chart_legend_bottom' ) ) {
	/**
	 * @param array<int,string> $data
	 * @return array<string,mixed>
	 */
	function pili_chart_legend_bottom( array $data = array() ) {
		$legend = array(
			'top'        => 0,
			'icon'       => 'roundRect',
			'itemWidth'  => 12,
			'itemHeight' => 4,
			'textStyle'  => array(
				'color'    => '#6b7280',
				'fontSize' => 12,
			),
		);
		if ( ! empty( $data ) ) {
			$legend['data'] = array_values( $data );
		}
		return $legend;
	}
}

if ( ! function_exists( 'pili_chart_legend_donut_right' ) ) {
	/**
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	function pili_chart_legend_donut_right( array $extra = array() ) {
		return array_merge(
			array(
				'orient'      => 'vertical',
				'right'       => 8,
				'top'         => 'middle',
				'left'        => 'auto',
				'width'       => '38%',
				'type'        => 'scroll',
				'pageIconSize'=> 10,
				'icon'        => 'circle',
				'itemWidth'   => 8,
				'itemHeight'  => 8,
				'itemGap'     => 10,
				'textStyle'   => array(
					'color'      => '#4b5563',
					'fontSize'   => 12,
					'overflow'   => 'truncate',
					'width'      => 88,
				),
			),
			$extra
		);
	}
}

if ( ! function_exists( 'pili_chart_style_line_series' ) ) {
	/**
	 * @param string              $name
	 * @param array<int|float>    $data
	 * @param int                 $color_index
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	function pili_chart_style_line_series( $name, array $data, $color_index = 0, array $extra = array() ) {
		$color = pili_chart_color_at( $color_index );
		$series = array(
			'name'      => (string) $name,
			'type'      => 'line',
			'smooth'    => true,
			'symbol'    => 'none',
			'lineStyle' => array( 'width' => 2 ),
			'itemStyle' => array( 'color' => $color ),
			'data'      => array_values( $data ),
		);
		if ( ! empty( $extra['area'] ) ) {
			$series['areaStyle'] = array(
				'color' => array(
					'type'       => 'linear',
					'x'          => 0,
					'y'          => 0,
					'x2'         => 0,
					'y2'         => 1,
					'colorStops' => array(
						array( 'offset' => 0, 'color' => $color . '33' ),
						array( 'offset' => 1, 'color' => $color . '05' ),
					),
				),
			);
		}
		return array_merge( $series, $extra );
	}
}

if ( ! function_exists( 'pili_chart_style_bar_series' ) ) {
	/**
	 * @param string           $name
	 * @param array<int|float> $data
	 * @param int              $color_index
	 * @return array<string,mixed>
	 */
	function pili_chart_style_bar_series( $name, array $data, $color_index = 0 ) {
		$color = pili_chart_color_at( $color_index );
		return array(
			'name'      => (string) $name,
			'type'      => 'bar',
			'barWidth'  => '52%',
			'itemStyle' => array(
				'color'        => $color,
				'borderRadius' => array( 4, 4, 0, 0 ),
			),
			'data'      => array_values( $data ),
		);
	}
}

if ( ! function_exists( 'pili_chart_apply_theme' ) ) {
	/**
	 * 为 option 注入全局 color 调色板（若未显式指定）。
	 *
	 * @param array<string,mixed> $option
	 * @return array<string,mixed>
	 */
	function pili_chart_apply_theme( array $option ) {
		if ( empty( $option['color'] ) ) {
			$option['color'] = pili_chart_palette();
		}
		return $option;
	}
}

if ( ! function_exists( 'pili_chart_donut_option' ) ) {
	/**
	 * 环形图 option（参考安全仪表台 riskChart）。
	 *
	 * @param array<int,array{name:string,value:int|float}> $data
	 * @param array<string,mixed>                           $args
	 * @return array<string,mixed>
	 */
	function pili_chart_donut_option( array $data, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'        => '',
				'subtext'      => '',
				'series_name'  => '',
				'center_text'  => '',
				'center_value' => '',
				'color_map'    => 'risk', // risk|palette|none
				'show_title'   => false,
				'layout'       => 'side', // side|stack
				'show_zero'    => false,
			)
		);

		$total = 0;
		$items = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name  = isset( $row['name'] ) ? (string) $row['name'] : '';
			$value = isset( $row['value'] ) ? (float) $row['value'] : 0;
			$item  = array(
				'name'  => $name,
				'value' => $value,
			);
			if ( 'risk' === $args['color_map'] ) {
				$item['itemStyle'] = array( 'color' => pili_chart_risk_level_color( $name ) );
			}
			$items[] = $item;
			$total  += $value;
		}

		if ( 'palette' === $args['color_map'] ) {
			foreach ( $items as $i => &$item ) {
				$item['itemStyle'] = array( 'color' => pili_chart_color_at( $i ) );
			}
			unset( $item );
		}

		if ( empty( $items ) || ( $total <= 0 && empty( $args['show_zero'] ) ) ) {
			$items[] = array(
				'name' => pili__( '名称' ),
				'value'      => 1,
				'itemStyle'  => array( 'color' => '#e5e7eb' ),
				'tooltip'    => array( 'show' => false ),
				'emphasis'   => array( 'disabled' => true ),
			);
			$total = 0;
		}

		$center_label = '' !== (string) $args['center_text'] ? (string) $args['center_text'] : pili__( '合计' );
		$center_value = '' !== (string) $args['center_value'] ? (string) $args['center_value'] : (string) (int) $total;

		$center_formatter = '{label|' . $center_label . '}' . "\n" . '{value|' . $center_value . '}';
		$center_rich      = array(
			'label' => array(
				'fontSize'   => 12,
				'color'      => '#6b7280',
				'lineHeight' => 18,
			),
			'value' => array(
				'fontSize'   => 22,
				'fontWeight' => 'bold',
				'color'      => '#1f2937',
				'lineHeight' => 28,
			),
		);
		$center_label_cfg = array(
			'show'      => true,
			'position'  => 'center',
			'formatter' => $center_formatter,
			'rich'      => $center_rich,
		);

		$is_side = 'side' === sanitize_key( (string) $args['layout'] );
		$series  = array(
			'name'              => (string) $args['series_name'],
			'type'              => 'pie',
			'radius'            => $is_side ? array( '42%', '52%' ) : array( '46%', '58%' ),
			'center'            => $is_side ? array( '34%', '52%' ) : array( '50%', '46%' ),
			'avoidLabelOverlap' => true,
			'itemStyle'         => array(
				'borderColor' => '#fff',
				'borderWidth' => 2,
			),
			'label'             => $center_label_cfg,
			'labelLine'         => array( 'show' => false ),
			'emphasis'          => array(
				'scale'     => true,
				'scaleSize' => 5,
				'label'     => $center_label_cfg,
			),
			'data'              => $items,
		);

		$option = array(
			'color'   => pili_chart_palette(),
			'tooltip' => array(
				'trigger'         => 'item',
				'borderWidth'     => 0,
				'backgroundColor' => 'rgba(255,255,255,0.96)',
				'textStyle'       => array( 'color' => '#374151', 'fontSize' => 12 ),
				'confine'         => true,
			),
			'legend'  => array_merge(
				$is_side
					? pili_chart_legend_donut_right()
					: array(
						'bottom'      => 0,
						'left'        => 'center',
						'icon'        => 'circle',
						'itemWidth'   => 8,
						'itemHeight'  => 8,
						'textStyle'   => array( 'color' => '#6b7280', 'fontSize' => 12 ),
					),
				array(
					'data' => array_values(
						array_map(
							static function ( $item ) {
								return isset( $item['name'] ) ? (string) $item['name'] : '';
							},
							$items
						)
					),
				)
			),
			'series'  => array( $series ),
			'media'   => array(
				array(
					'query'  => array( 'maxWidth' => 420 ),
					'option' => array(
						'legend' => array(
							'orient'    => 'horizontal',
							'bottom'    => 0,
							'left'      => 'center',
							'right'     => 'auto',
							'top'       => 'auto',
							'width'     => 'auto',
							'type'      => 'plain',
							'itemGap'   => 12,
							'textStyle' => array(
								'color'    => '#6b7280',
								'fontSize' => 11,
								'width'    => 'auto',
								'overflow' => 'none',
							),
						),
						'series' => array(
							array(
								'center' => array( '50%', '44%' ),
								'radius' => array( '40%', '50%' ),
							),
						),
					),
				),
			),
		);

		if ( ! empty( $args['show_title'] ) && ( '' !== (string) $args['title'] || '' !== (string) $args['subtext'] ) ) {
			$option['title'] = pili_chart_title_block( (string) $args['title'], (string) $args['subtext'] );
			$option['series'][0]['center'] = $is_side ? array( '34%', '56%' ) : array( '50%', '48%' );
			$option['series'][0]['radius']   = $is_side ? array( '38%', '48%' ) : array( '42%', '52%' );
		}

		return pili_chart_apply_theme( $option );
	}
}

if ( ! function_exists( 'pili_chart_pie_option' ) ) {
	/**
	 * 实心饼图（带主题 legend）。
	 *
	 * @param array<int,array{name:string,value:int|float}> $data
	 * @param array<string,mixed>                           $args
	 * @return array<string,mixed>
	 */
	function pili_chart_pie_option( array $data, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'       => '',
				'subtext'     => '',
				'series_name' => '',
				'color_map'   => 'palette',
			)
		);

		$items = array();
		foreach ( $data as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name  = isset( $row['name'] ) ? (string) $row['name'] : '';
			$value = isset( $row['value'] ) ? (float) $row['value'] : 0;
			$item  = array(
				'name'  => $name,
				'value' => $value,
			);
			if ( 'risk' === $args['color_map'] ) {
				$item['itemStyle'] = array( 'color' => pili_chart_risk_level_color( $name ) );
			} elseif ( 'palette' === $args['color_map'] ) {
				$item['itemStyle'] = array( 'color' => pili_chart_color_at( (int) $i ) );
			}
			$items[] = $item;
		}

		$option = array(
			'color'   => pili_chart_palette(),
			'tooltip' => array(
				'trigger'         => 'item',
				'borderWidth'     => 0,
				'backgroundColor' => 'rgba(255,255,255,0.96)',
				'textStyle'       => array( 'color' => '#374151', 'fontSize' => 12 ),
			),
			'legend'  => array(
				'top'       => 'bottom',
				'icon'      => 'circle',
				'itemWidth' => 8,
				'textStyle' => array( 'color' => '#6b7280', 'fontSize' => 12 ),
			),
			'series'  => array(
				array(
					'name'              => (string) $args['series_name'],
					'type'              => 'pie',
					'radius'            => '62%',
					'center'            => array( '50%', '46%' ),
					'avoidLabelOverlap' => true,
					'itemStyle'         => array(
						'borderColor' => '#fff',
						'borderWidth' => 2,
					),
					'label'             => array(
						'color'    => '#4b5563',
						'fontSize' => 11,
					),
					'data'              => $items,
				),
			),
		);

		if ( '' !== (string) $args['title'] || '' !== (string) $args['subtext'] ) {
			$option['title'] = pili_chart_title_block( (string) $args['title'], (string) $args['subtext'] );
		}

		return pili_chart_apply_theme( $option );
	}
}
