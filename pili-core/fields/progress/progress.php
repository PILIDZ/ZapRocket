<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework Progress 字段类型（进度条）
 *
 * 后台「进度条」语义：默认仅彩色填充条 + 数字框改值；轨道只读展示，点击轨道不改数（与 slider 区分）。
 * 需要「点轨道改值」时设 click_track=true；需要拖柄时设 show_thumb=true（二者均可与滑块类似交互）。
 * 主题色使用内置色板十六进制，避免依赖 Tailwind 动态拼接类未被打进 style.min.css 时无色。
 *
 * @package PILI Framework
 */
	class PILI_Field_progress extends PILI_Fields {

		/**
		 * 与 Tailwind 500/600 接近的色值，供填充条、文案、焦点环使用（不依赖编译期扫描）。
		 *
		 * @param string $slug color 参数（如 blue、green）。
		 * @return array{ fill: string, label: string }
		 */
		protected function get_progress_accent( $slug ) {
			$defaults = array(
				'blue'    => array( 'fill' => '#3b82f6', 'label' => '#2563eb' ),
				'green'   => array( 'fill' => '#22c55e', 'label' => '#16a34a' ),
				'purple'  => array( 'fill' => '#a855f7', 'label' => '#9333ea' ),
				'red'     => array( 'fill' => '#ef4444', 'label' => '#dc2626' ),
				'orange'  => array( 'fill' => '#f97316', 'label' => '#ea580c' ),
				'amber'   => array( 'fill' => '#f59e0b', 'label' => '#d97706' ),
				'yellow'  => array( 'fill' => '#eab308', 'label' => '#ca8a04' ),
				'teal'    => array( 'fill' => '#14b8a6', 'label' => '#0d9488' ),
				'cyan'    => array( 'fill' => '#06b6d4', 'label' => '#0891b2' ),
				'sky'     => array( 'fill' => '#0ea5e9', 'label' => '#0284c7' ),
				'indigo'  => array( 'fill' => '#6366f1', 'label' => '#4f46e5' ),
				'pink'    => array( 'fill' => '#ec4899', 'label' => '#db2777' ),
				'rose'    => array( 'fill' => '#f43f5e', 'label' => '#e11d48' ),
				'violet'  => array( 'fill' => '#8b5cf6', 'label' => '#7c3aed' ),
				'fuchsia' => array( 'fill' => '#d946ef', 'label' => '#c026d3' ),
				'emerald' => array( 'fill' => '#10b981', 'label' => '#059669' ),
				'lime'    => array( 'fill' => '#84cc16', 'label' => '#65a30d' ),
				'gray'    => array( 'fill' => '#6b7280', 'label' => '#4b5563' ),
			);

			$custom = apply_filters( 'pili_progress_field_color_map', array() );
			$map    = array_merge( $defaults, is_array( $custom ) ? $custom : array() );
			$key    = sanitize_key( (string) $slug );
			$entry  = isset( $map[ $key ] ) && is_array( $map[ $key ] ) ? $map[ $key ] : $map['blue'];
			$fill   = isset( $entry['fill'] ) ? (string) $entry['fill'] : $defaults['blue']['fill'];
			$lab    = isset( $entry['label'] ) ? (string) $entry['label'] : $fill;

			return array(
				'fill'  => $fill,
				'label' => $lab,
			);
		}

		public function render() {

			echo $this->field_before();

			$args = wp_parse_args(
				$this->field,
				array(
					'min'               => 0,
					'max'               => 100,
					'step'              => 1,
					'precision'         => 0,
					'unit'              => '',
					'prefix'            => '',
					'suffix'            => '',
					'color'             => 'blue',
					'size'              => 'medium',
					'show_thumb'        => false,
					'click_track'       => false,
					'show_input'        => true,
					'show_scale'        => true,
					'show_percentage'   => false,
					'show_min_max'      => true,
					'label_position'    => 'right', // right=轨道右侧；below=轨道下方（旧布局）
					'keyboard'          => true,
					'animate'           => true,
				)
			);

			$value = $this->value;
			if ( $value === '' || $value === null ) {
				$value = $args['min'];
			}
			$value = (float) $value;
			$value = max( $args['min'], min( $args['max'], $value ) );

			$accent = $this->get_progress_accent( $args['color'] );

			$container_classes = array(
				'pili-progress-field',
				'pili-progress-' . $args['color'],
				'pili-progress-' . $args['size'],
			);
			if ( $args['animate'] ) {
				$container_classes[] = 'pili-progress-animate';
			}
			if ( ! empty( $args['show_thumb'] ) ) {
				$container_classes[] = 'pili-progress-has-thumb';
			}

			$track_interactive = ! empty( $args['show_thumb'] ) || ! empty( $args['click_track'] );
			if ( ! $track_interactive ) {
				$container_classes[] = 'pili-progress-readonly-track';
			}

			$track_heights = array(
				'small'  => 'h-1.5',
				'medium' => 'h-2.5',
				'large'  => 'h-3.5',
			);
			$track_h         = isset( $track_heights[ $args['size'] ] ) ? $track_heights[ $args['size'] ] : $track_heights['medium'];
			$track_cursor    = $track_interactive ? 'cursor-pointer hover:bg-gray-300' : 'cursor-default';
			$track_classes   = 'pili-progress-track relative w-full ' . $track_h . ' bg-gray-200 rounded-full ' . $track_cursor . ' transition-colors duration-200';
			$track_pe_style    = ! $track_interactive ? ' style="pointer-events:none;"' : '';

			$progress_data = array(
				'min'              => (float) $args['min'],
				'max'              => (float) $args['max'],
				'step'             => (float) $args['step'],
				'precision'        => (int) $args['precision'],
				'keyboard'         => (bool) $args['keyboard'],
				'animate'          => (bool) $args['animate'],
				'showThumb'        => (bool) ! empty( $args['show_thumb'] ),
				'trackInteractive' => (bool) $track_interactive,
			);

			$container_style = '--pili-progress-accent:' . esc_attr( $accent['fill'] ) . ';--pili-progress-label:' . esc_attr( $accent['label'] ) . ';';
			$label_position  = sanitize_key( (string) $args['label_position'] );
			if ( 'below' !== $label_position ) {
				$label_position = 'right';
			}
			if ( 'right' === $label_position ) {
				$container_classes[] = 'pili-progress-label-right';
			}

			echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '" style="' . esc_attr( $container_style ) . '" data-field-id="' . esc_attr( $this->field['id'] ) . '" data-progress-display="' . esc_attr( $args['show_percentage'] ? 'percentage' : 'value' ) . '" data-progress-prefix="' . esc_attr( $args['prefix'] ) . '" data-progress-suffix="' . esc_attr( $args['suffix'] ) . '" data-progress-label-position="' . esc_attr( $label_position ) . '">';

			echo '<div class="pili-progress-bar-row relative mb-3">';
			echo '<div class="pili-progress-track-wrap">';
			echo '<div class="' . esc_attr( $track_classes ) . '"' . $track_pe_style . ' data-progress-config="' . esc_attr( wp_json_encode( $progress_data ) ) . '" role="progressbar" aria-valuemin="' . esc_attr( $args['min'] ) . '" aria-valuemax="' . esc_attr( $args['max'] ) . '" aria-valuenow="' . esc_attr( $value ) . '">';

			echo '<div class="pili-progress-fill absolute top-0 left-0 h-full rounded-full transition-all duration-200" style="background-color:' . esc_attr( $accent['fill'] ) . ';"></div>';

			if ( ! empty( $args['show_thumb'] ) ) {
				echo '<div class="pili-progress-thumb absolute top-1/2 w-5 h-5 rounded-full bg-white border-2 shadow-md transition-all duration-200 hover:scale-110 focus:outline-none cursor-grab" style="border-color:' . esc_attr( $accent['fill'] ) . ';" tabindex="0" role="slider" aria-valuemin="' . esc_attr( $args['min'] ) . '" aria-valuemax="' . esc_attr( $args['max'] ) . '" aria-valuenow="' . esc_attr( $value ) . '"></div>';
			}

			echo '</div>'; // track
			echo '</div>'; // track-wrap

			if ( $args['show_scale'] && 'right' === $label_position ) {
				echo '<span class="pili-progress-value-display font-medium" style="color:' . esc_attr( $accent['label'] ) . ';">' . esc_html( $this->format_progress_display( $value, $args ) ) . '</span>';
			}

			echo '</div>'; // bar-row

			if ( $args['show_scale'] && 'below' === $label_position ) {
				echo '<div class="pili-progress-scale-below flex justify-between items-center mt-2 text-sm text-gray-600">';
				if ( $args['show_min_max'] ) {
					echo '<span>' . esc_html( $args['prefix'] ) . esc_html( $args['min'] ) . esc_html( $args['suffix'] ) . '</span>';
				} else {
					echo '<span></span>';
				}
				echo '<span class="pili-progress-value-display font-medium" style="color:' . esc_attr( $accent['label'] ) . ';">' . esc_html( $this->format_progress_display( $value, $args ) ) . '</span>';
				if ( $args['show_min_max'] ) {
					echo '<span>' . esc_html( $args['prefix'] ) . esc_html( $args['max'] ) . esc_html( $args['suffix'] ) . '</span>';
				} else {
					echo '<span></span>';
				}
				echo '</div>';
			}

			if ( $args['show_input'] ) {
				echo '<div class="flex items-center space-x-2 flex-wrap gap-y-2">';
				echo '<label class="text-sm font-medium text-gray-700">' . pili_esc_html__( '数值:' ) . '</label>';
				echo '<input type="number" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $args['min'] ) . '" max="' . esc_attr( $args['max'] ) . '" step="' . esc_attr( $args['step'] ) . '" class="pili-progress-input w-24 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:border-transparent" />';
				if ( ! empty( $args['unit'] ) ) {
					echo '<span class="text-sm text-gray-500">' . esc_html( $args['unit'] ) . '</span>';
				}
				echo '</div>';
			} else {
				echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $value ) . '" class="pili-progress-input" />';
			}

			echo '</div>';

			echo $this->field_after();
		}

		/**
		 * @param float $value 当前值.
		 * @param array $args  解析后的字段参数.
		 */
		protected function format_progress_display( $value, $args ) {
			if ( ! empty( $args['show_percentage'] ) ) {
				$span = (float) $args['max'] - (float) $args['min'];
				if ( abs( $span ) < 0.00001 ) {
					return '0%';
				}
				$pct = ( ( $value - (float) $args['min'] ) / $span ) * 100;
				return sprintf( '%s%%', round( $pct, $args['precision'] > 0 ? $args['precision'] : 0 ) );
			}
			return $args['prefix'] . $value . $args['suffix'];
		}

		public function enqueue() {
			$handle = pili_asset_handle( 'field-progress' );

			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/progress.js',
				array( 'jquery' ),
				PILI_Setup::$version,
				true
			);

			pili_localize_bag(
				$handle,
				'progress',
				array(
					'strings' => array(
						'value' => pili__( '值' ),
					),
				)
			);
		}

		public function validate( $value ) {

			$args = wp_parse_args(
				$this->field,
				array(
					'min'  => 0,
					'max'  => 100,
					'step' => 1,
				)
			);

			$validated = (float) $value;
			$validated = max( (float) $args['min'], min( (float) $args['max'], $validated ) );

			if ( ! empty( $args['step'] ) && (float) $args['step'] > 0 ) {
				$validated = round( $validated / (float) $args['step'] ) * (float) $args['step'];
				$validated = max( (float) $args['min'], min( (float) $args['max'], $validated ) );
			}

			if ( isset( $args['precision'] ) && (int) $args['precision'] >= 0 ) {
				$validated = (float) number_format( (float) $validated, (int) $args['precision'], '.', '' );
			}

			$validated = apply_filters( 'pili_validate_progress_field', $validated, $this->field );
			$validated = apply_filters( "pili_validate_progress_field_{$this->field['id']}", $validated, $this->field );

			return $validated;
		}
	}
