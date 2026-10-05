<?php
/**
 * Field: stat_cards
 *
 * 只读指标卡片：网格 KPI 或横向摘要，不参与存储。
 *
 * @package PILI Framework
 */

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_stat_cards' ) ) {

	/**
	 * Stat cards field.
	 */
	class PILI_Field_stat_cards extends PILI_Fields {

		/**
		 * @return array<string, array{icon_wrap:string, value_color:string, spark_color:string}>
		 */
		public static function tone_registry() {
			return array(
				'blue'   => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--blue',
					'value_color' => '#2563eb',
					'spark_color' => '#3b82f6',
				),
				'orange' => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--orange',
					'value_color' => '#ea580c',
					'spark_color' => '#f97316',
				),
				'green'  => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--green',
					'value_color' => '#16a34a',
					'spark_color' => '#22c55e',
				),
				'purple' => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--purple',
					'value_color' => '#9333ea',
					'spark_color' => '#a855f7',
				),
				'yellow' => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--yellow',
					'value_color' => '#ca8a04',
					'spark_color' => '#eab308',
				),
				'red'    => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--red',
					'value_color' => '#dc2626',
					'spark_color' => '#ef4444',
				),
				'teal'   => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--teal',
					'value_color' => '#0d9488',
					'spark_color' => '#14b8a6',
				),
				'cyan'   => array(
					'icon_wrap'   => 'pili-stat-card__icon-wrap--cyan',
					'value_color' => '#0891b2',
					'spark_color' => '#06b6d4',
				),
			);
		}

		/**
		 * @param string $tone Tone key.
		 * @return array{icon_wrap:string, value_color:string, spark_color:string}
		 */
		public static function resolve_tone( $tone ) {
			$key      = sanitize_key( (string) $tone );
			$registry = self::tone_registry();
			return isset( $registry[ $key ] ) ? $registry[ $key ] : $registry['blue'];
		}

		/**
		 * @param array<int|float> $values Values.
		 * @param string           $color  Stroke.
		 * @return string
		 */
		public static function render_sparkline_svg( array $values, $color = '#3b82f6' ) {
			$values = array_values( array_map( 'floatval', $values ) );
			if ( count( $values ) < 2 ) {
				return '';
			}
			$width  = 60;
			$height = 20;
			$max    = max( $values );
			$min    = min( $values );
			$range  = $max - $min;
			if ( $range <= 0 ) {
				$range = 1.0;
			}
			$points = array();
			$last   = count( $values ) - 1;
			foreach ( $values as $i => $val ) {
				$x        = ( $i / $last ) * $width;
				$y        = $height - ( ( (float) $val - $min ) / $range ) * $height;
				$points[] = round( $x, 2 ) . ',' . round( $y, 2 );
			}
			$stroke = preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $color ) ? (string) $color : '#3b82f6';

			return '<svg class="pili-stat-card__sparkline" width="' . esc_attr( (string) $width ) . '" height="' . esc_attr( (string) $height ) . '" viewBox="0 -2 ' . esc_attr( (string) $width ) . ' ' . esc_attr( (string) ( $height + 4 ) ) . '" aria-hidden="true"><polyline points="' . esc_attr( implode( ' ', $points ) ) . '" fill="none" stroke="' . esc_attr( $stroke ) . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		/**
		 * @param array<string,mixed> $card Card.
		 * @return string
		 */
		public static function render_grid_card( array $card ) {
			$label = isset( $card['label'] ) ? (string) $card['label'] : '';
			$value = array_key_exists( 'value', $card ) ? $card['value'] : '';
			if ( is_numeric( $value ) ) {
				$value = number_format_i18n( (float) $value );
			} else {
				$value = (string) $value;
			}

			$tone_meta   = self::resolve_tone( isset( $card['tone'] ) ? (string) $card['tone'] : 'blue' );
			$value_color = ! empty( $card['value_color'] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $card['value_color'] )
				? (string) $card['value_color']
				: $tone_meta['value_color'];

			$icon = isset( $card['icon'] ) ? trim( (string) $card['icon'] ) : '';
			if ( '' === $icon || ! preg_match( '/^ri-[a-z0-9-]+$/', $icon ) ) {
				$icon = 'ri-bar-chart-fill';
			}

			$html  = '<div class="pili-stat-card bg-white border border-gray-100 rounded-lg p-3 hover:shadow-md transition-shadow">';
			$html .= '<div class="pili-stat-card__head">';
			$html .= '<div>';
			$html .= '<div class="pili-stat-card__label text-xs text-gray-500 mb-1">' . esc_html( $label ) . '</div>';
			$html .= '<div class="pili-stat-card__value text-2xl font-bold leading-none" style="color:' . esc_attr( $value_color ) . ';">' . esc_html( $value ) . '</div>';
			$html .= '</div>';
			$html .= '<div class="pili-stat-card__icon-wrap ' . esc_attr( $tone_meta['icon_wrap'] ) . '">';
			$html .= '<i class="' . esc_attr( $icon ) . ' pili-stat-card__icon" aria-hidden="true"></i>';
			$html .= '</div>';
			$html .= '</div>';

			$trend_html     = '';
			$sparkline_html = '';
			if ( ! empty( $card['trend'] ) && is_array( $card['trend'] ) ) {
				$trend      = $card['trend'];
				$direction  = isset( $trend['direction'] ) ? sanitize_key( (string) $trend['direction'] ) : 'flat';
				$trend_text = isset( $trend['text'] ) ? (string) $trend['text'] : '';
				$suffix     = isset( $trend['suffix'] ) ? (string) $trend['suffix'] : '较昨日';
				$icon_class = 'ri-subtract-line';
				$mod_class  = 'pili-stat-card__trend--flat';
				if ( 'up' === $direction ) {
					$icon_class = 'ri-arrow-up-fill';
					$mod_class  = 'pili-stat-card__trend--up';
				} elseif ( 'down' === $direction ) {
					$icon_class = 'ri-arrow-down-fill';
					$mod_class  = 'pili-stat-card__trend--down';
				}
				$trend_html  = '<div class="pili-stat-card__trend ' . esc_attr( $mod_class ) . '">';
				$trend_html .= '<i class="' . esc_attr( $icon_class ) . '" aria-hidden="true"></i>';
				$trend_html .= '<span>' . esc_html( $trend_text ) . '</span>';
				if ( '' !== $suffix ) {
					$trend_html .= '<span class="text-gray-400 ml-1">' . esc_html( $suffix ) . '</span>';
				}
				$trend_html .= '</div>';
			}
			if ( ! empty( $card['sparkline'] ) && is_array( $card['sparkline'] ) && ! empty( $card['sparkline']['values'] ) && is_array( $card['sparkline']['values'] ) ) {
				$spark_color    = ! empty( $card['sparkline']['color'] ) ? (string) $card['sparkline']['color'] : $tone_meta['spark_color'];
				$sparkline_html = self::render_sparkline_svg( $card['sparkline']['values'], $spark_color );
			}
			if ( '' !== $trend_html || '' !== $sparkline_html ) {
				$html .= '<div class="pili-stat-card__foot">';
				$html .= $trend_html;
				$html .= $sparkline_html;
				$html .= '</div>';
			}
			$html .= '</div>';

			return $html;
		}

		/**
		 * @param array<string,mixed> $card Card.
		 * @return string
		 */
		public static function render_feature_card( array $card ) {
			$label = isset( $card['label'] ) ? (string) $card['label'] : '';
			$value = array_key_exists( 'value', $card ) ? $card['value'] : '';
			if ( is_numeric( $value ) ) {
				$value = number_format_i18n( (float) $value );
			} else {
				$value = (string) $value;
			}
			$tone_meta = self::resolve_tone( isset( $card['tone'] ) ? (string) $card['tone'] : 'blue' );
			$icon      = isset( $card['icon'] ) ? trim( (string) $card['icon'] ) : '';
			if ( '' === $icon || ! preg_match( '/^ri-[a-z0-9-]+$/', $icon ) ) {
				$icon = 'ri-bar-chart-line';
			}

			$html  = '<div class="pili-stat-feature-card">';
			$html .= '<div class="pili-stat-feature-card__icon-wrap ' . esc_attr( $tone_meta['icon_wrap'] ) . '">';
			$html .= '<i class="' . esc_attr( $icon ) . ' pili-stat-feature-card__icon" aria-hidden="true"></i>';
			$html .= '</div>';
			$html .= '<div>';
			$html .= '<div class="pili-stat-feature-card__label">' . esc_html( $label ) . '</div>';
			$html .= '<div class="pili-stat-feature-card__value">' . esc_html( $value ) . '</div>';
			$html .= '</div>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * @param array<string,mixed> $columns Columns.
		 * @param string              $layout  Layout.
		 * @param array<string,mixed> $args    Args.
		 * @return string
		 */
		public static function build_grid_container_attrs( $columns, $layout, $args = array() ) {
			$columns = is_array( $columns ) ? $columns : array();
			$classes = array();
			$styles  = array(
				'display:grid',
				'width:100%',
			);

			if ( 'feature' === $layout ) {
				$classes[] = 'pili-stat-cards-feature-grid';
				$styles[]  = 'gap:1.5rem';
				$styles[]  = 'grid-template-columns:repeat(auto-fit,minmax(12.5rem,1fr))';
				if ( ! empty( $args['divided'] ) ) {
					$classes[] = 'pili-stat-cards-feature-grid--divided';
				}
				if ( ! empty( $columns['md'] ) ) {
					$n         = max( 1, min( 6, (int) $columns['md'] ) );
					$classes[] = 'pili-stat-cards-feature-grid--md-' . $n;
					$styles[]  = '--pili-stat-feature-cols-md:' . $n;
				}
			} else {
				$classes[] = 'pili-stat-cards-grid';
				$styles[]  = 'gap:1rem';
				$styles[]  = 'grid-template-columns:repeat(auto-fit,minmax(9.5rem,1fr))';

				$breakpoints = array( 'sm', 'md', 'lg', 'xl' );
				foreach ( $breakpoints as $bp ) {
					if ( empty( $columns[ $bp ] ) ) {
						continue;
					}
					$n         = max( 1, min( 8, (int) $columns[ $bp ] ) );
					$classes[] = 'pili-stat-cards-grid--' . $bp . '-' . $n;
					$styles[]  = '--pili-stat-cols-' . $bp . ':' . $n;
				}
			}

			return 'class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( implode( ';', $styles ) ) . '"';
		}

		/**
		 * @param array<int,array<string,mixed>> $cards Cards.
		 * @param array<string,mixed>            $args  Args.
		 * @return string
		 */
		public static function render_grid( array $cards, array $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'layout'  => 'grid',
					'columns' => array(
						'default' => 1,
						'sm'      => 2,
						'md'      => 4,
						'lg'      => 8,
					),
					'section' => array(),
					'footer'  => '',
					'divided' => false,
				)
			);

			$layout  = sanitize_key( (string) $args['layout'] );
			$columns = is_array( $args['columns'] ) ? $args['columns'] : array();
			$section = is_array( $args['section'] ) ? $args['section'] : array();
			$footer  = isset( $args['footer'] ) ? (string) $args['footer'] : '';

			$html         = '';
			$wrap_section = ! isset( $section['wrap'] ) || (bool) $section['wrap'];
			if ( $wrap_section ) {
				$html .= '<section class="pili-stat-cards-section">';
			}
			if ( ! empty( $section['title'] ) ) {
				$html .= '<div class="pili-stat-cards-section__head">';
				if ( ! empty( $section['icon'] ) && preg_match( '/^ri-[a-z0-9-]+$/', (string) $section['icon'] ) ) {
					$html .= '<i class="' . esc_attr( (string) $section['icon'] ) . ' pili-stat-cards-section__head-icon" aria-hidden="true"></i>';
				}
				$html .= '<h2 class="pili-stat-cards-section__title">' . esc_html( (string) $section['title'] ) . '</h2>';
				$html .= '</div>';
			}
			$html .= '<div ' . self::build_grid_container_attrs( $columns, $layout, $args ) . '>';
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) ) {
					continue;
				}
				$html .= ( 'feature' === $layout ) ? self::render_feature_card( $card ) : self::render_grid_card( $card );
			}
			$html .= '</div>';
			if ( '' !== trim( $footer ) ) {
				$html .= '<div class="pili-stat-cards-footer">' . $footer . '</div>';
			}
			if ( $wrap_section ) {
				$html .= '</section>';
			}

			return $html;
		}

		/**
		 * @param array  $field  Field.
		 * @param mixed  $value  Value.
		 * @param string $unique Unique.
		 * @param string $where  Where.
		 * @param string $parent Parent.
		 * @return array<int,array<string,mixed>>
		 */
		private function resolve_cards( $field, $value, $unique, $where, $parent ) {
			$cards = array();
			if ( ! empty( $field['cards_callback'] ) && is_callable( $field['cards_callback'] ) ) {
				$data = call_user_func( $field['cards_callback'], $field, $value, $unique, $where, $parent );
				if ( is_array( $data ) ) {
					$cards = $data;
				}
			} elseif ( ! empty( $field['cards'] ) && is_array( $field['cards'] ) ) {
				$cards = $field['cards'];
			}
			return $cards;
		}

		/**
		 * @param array  $field  Field.
		 * @param mixed  $value  Value.
		 * @param string $unique Unique.
		 * @param string $where  Where.
		 * @param string $parent Parent.
		 * @return string
		 */
		private function resolve_footer_html( $field, $value, $unique, $where, $parent ) {
			if ( ! empty( $field['footer_callback'] ) && is_callable( $field['footer_callback'] ) ) {
				$footer = call_user_func( $field['footer_callback'], $field, $value, $unique, $where, $parent );
				return is_string( $footer ) ? $footer : '';
			}
			return isset( $field['footer'] ) ? (string) $field['footer'] : '';
		}

		/**
		 * Render.
		 *
		 * @return void
		 */
		public function render() {
			$args = wp_parse_args(
				$this->field,
				array(
					'layout'  => 'grid',
					'columns' => array(
						'sm' => 2,
						'md' => 4,
						'lg' => 8,
					),
					'section' => array(),
					'divided' => false,
				)
			);

			$section = is_array( $args['section'] ) ? $args['section'] : array();
			if ( empty( $section['title'] ) && ! empty( $args['title'] ) ) {
				$section['title'] = (string) $args['title'];
			}
			if ( empty( $section['icon'] ) && ! empty( $args['section_icon'] ) ) {
				$section['icon'] = (string) $args['section_icon'];
			}

			$cards  = $this->resolve_cards( $this->field, $this->value, $this->unique, $this->where, $this->parent );
			$footer = $this->resolve_footer_html( $this->field, $this->value, $this->unique, $this->where, $this->parent );

			echo $this->field_before();
			echo self::render_grid(
				$cards,
				array(
					'layout'  => $args['layout'],
					'columns' => $args['columns'],
					'section' => $section,
					'footer'  => $footer,
					'divided' => ! empty( $args['divided'] ),
				)
			);
			echo $this->field_after();
		}

		/**
		 * Enqueue assets.
		 *
		 * @return void
		 */
		public function enqueue() {
			if ( class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
				Remix_Icons::enqueue_style( 'pilidoc-remixicon-stat-cards' );
			} elseif ( class_exists( 'Pilidoc_Remix_Icons' ) ) {
				\Pilidoc_Remix_Icons::enqueue_style( 'pilidoc-remixicon-stat-cards' );
			}
			$handle = pili_asset_handle( 'field-stat-cards' );
			$ver    = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1';
			$path   = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/css/fields/stat-cards.css';
			if ( is_readable( $path ) ) {
				$ver = (string) filemtime( $path );
			}
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/stat-cards.css',
				array(),
				$ver
			);
		}

		/**
		 * @param mixed $value Value.
		 * @return string
		 */
		public function validate( $value ) {
			unset( $value );
			return '';
		}
	}
}
