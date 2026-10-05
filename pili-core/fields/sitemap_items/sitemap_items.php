<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field: sitemap_items
 *
 * Sitemap 内容比例表：每行一类内容，复用 switch / select / slider。
 * 存储：key => { enabled, changefreq, priority }
 *
 * @package PILI
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_sitemap_items' ) ) {

	/**
	 * Sitemap 内容比例字段。
	 */
	class PILI_Field_sitemap_items extends PILI_Fields {

		/**
		 * @param array  $field  Field.
		 * @param mixed  $value  Value.
		 * @param string $unique Unique.
		 * @param string $where  Where.
		 * @param string $parent Parent.
		 */
		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * Enqueue layout CSS.
		 *
		 * @return void
		 */
		public function enqueue() {
			$handle = pili_asset_handle( 'field-sitemap-items' );
			$css    = PILI_Setup::$dir . '/assets/css/fields/sitemap-items.css';
			$ver    = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : (string) PILI_Setup::$version;
			if ( is_readable( $css ) ) {
				$ver .= '.' . (string) filemtime( $css );
			}
			if ( ! wp_style_is( $handle, 'enqueued' ) ) {
				wp_enqueue_style(
					$handle,
					PILI_Setup::$url . '/assets/css/fields/sitemap-items.css',
					array(),
					$ver
				);
			}
			if ( class_exists( 'Remix_Icons' ) ) {
				Remix_Icons::enqueue_style( 'pili-remixicon-sitemap-items' );
			}
		}

		/**
		 * 更新频率选项。
		 *
		 * @return array<string,string>
		 */
		public static function frequency_options() {
			return array(
				'always'  => pili__( '总是（always）' ),
				'hourly'  => pili__( '每小时（hourly）' ),
				'daily'   => pili__( '每天（daily）' ),
				'weekly'  => pili__( '每周（weekly）' ),
				'monthly' => pili__( '每月（monthly）' ),
				'yearly'  => pili__( '每年（yearly）' ),
				'never'   => pili__( '从不（never）' ),
			);
		}

		/**
		 * 清洗优先级到 0.0～1.0（一位小数）。
		 *
		 * @param mixed $raw Raw.
		 * @return string
		 */
		public static function sanitize_priority( $raw ) {
			$prio = (float) $raw;
			if ( $prio < 0 ) {
				$prio = 0;
			}
			if ( $prio > 1 ) {
				$prio = 1;
			}
			return number_format( $prio, 1, '.', '' );
		}

		/**
		 * 清洗提交值。
		 *
		 * @param mixed $value Raw.
		 * @param array $field Field.
		 * @return array<string,array{enabled:int,changefreq:string,priority:string}>
		 */
		public static function sanitize_value( $value, $field = array() ) {
			$defs       = self::resolve_items_def( $field );
			$raw        = is_array( $value ) ? $value : array();
			$out        = array();
			$freq_allow = array_keys( self::frequency_options() );

			foreach ( $defs as $key => $def ) {
				$row     = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
				$enabled = ! empty( $row['enabled'] ) ? 1 : 0;
				$freq    = isset( $row['changefreq'] ) ? sanitize_key( (string) $row['changefreq'] ) : '';
				if ( ! in_array( $freq, $freq_allow, true ) ) {
					$freq = isset( $def['changefreq'] ) ? (string) $def['changefreq'] : 'weekly';
				}
				$prio_raw = array_key_exists( 'priority', $row )
					? $row['priority']
					: ( isset( $def['priority'] ) ? $def['priority'] : '0.5' );
				$out[ $key ] = array(
					'enabled'    => $enabled,
					'changefreq' => $freq,
					'priority'   => self::sanitize_priority( $prio_raw ),
				);
			}
			return $out;
		}

		/**
		 * @param array $field Field.
		 * @return array<string,array{label:string,enabled?:int,changefreq?:string,priority?:string,hint?:string,group?:string}>
		 */
		public static function resolve_items_def( $field ) {
			if ( ! empty( $field['items'] ) && is_array( $field['items'] ) ) {
				return $field['items'];
			}
			if ( ! empty( $field['items_callback'] ) && is_callable( $field['items_callback'] ) ) {
				$items = call_user_func( $field['items_callback'], $field );
				return is_array( $items ) ? $items : array();
			}
			return array();
		}

		/**
		 * Render.
		 *
		 * @return void
		 */
		public function render() {
			$this->enqueue();
			$items = self::resolve_items_def( $this->field );
			$value = is_array( $this->value ) ? $this->value : array();
			if ( empty( $value ) && ! empty( $this->field['default'] ) && is_array( $this->field['default'] ) ) {
				$value = $this->field['default'];
			}

			echo $this->field_before();

			echo '<div class="pili-sitemap-items" data-field-id="' . esc_attr( isset( $this->field['id'] ) ? (string) $this->field['id'] : '' ) . '">';
			echo '<div class="pili-sitemap-items__head">';
			echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--label">' . pili_esc_html__( '内容类型' ) . '</div>';
			echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--switch">' . pili_esc_html__( '纳入地图' ) . '</div>';
			echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--freq">' . pili_esc_html__( '更新频率' ) . '</div>';
			echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--prio">' . pili_esc_html__( '优先级' ) . '</div>';
			echo '</div>';

			$freq_opts     = self::frequency_options();
			$parent_unique = ( ! empty( $this->unique ) )
				? $this->unique . '[' . $this->field['id'] . ']'
				: (string) $this->field['id'];

			foreach ( $items as $key => $def ) {
				$key   = sanitize_key( (string) $key );
				$label = isset( $def['label'] ) ? (string) $def['label'] : $key;
				$row   = isset( $value[ $key ] ) && is_array( $value[ $key ] ) ? $value[ $key ] : array();
				$enabled = array_key_exists( 'enabled', $row )
					? ( ! empty( $row['enabled'] ) ? 1 : 0 )
					: ( ! empty( $def['enabled'] ) ? 1 : 0 );
				$freq = isset( $row['changefreq'] ) ? (string) $row['changefreq'] : ( isset( $def['changefreq'] ) ? (string) $def['changefreq'] : 'weekly' );
				$prio = isset( $row['priority'] ) ? $row['priority'] : ( isset( $def['priority'] ) ? $def['priority'] : '0.5' );
				$prio = (float) self::sanitize_priority( $prio );
				$row_unique = $parent_unique . '[' . $key . ']';

				echo '<div class="pili-sitemap-items__row" data-item-key="' . esc_attr( $key ) . '">';
				echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--label">';
				echo '<span class="pili-sitemap-items__label">' . esc_html( $label ) . '</span>';
				if ( ! empty( $def['hint'] ) ) {
					echo '<span class="pili-sitemap-items__hint">' . esc_html( (string) $def['hint'] ) . '</span>';
				}
				echo '</div>';

				echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--switch" data-mobile-label="' . pili_esc_attr__( '纳入地图' ) . '">';
				PILI::field(
					array(
						'id'    => 'enabled',
						'type'  => 'switch',
						'size'  => 'small',
						'color' => 'blue',
					),
					$enabled,
					$row_unique,
					'field/sitemap_items'
				);
				echo '</div>';

				echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--freq" data-mobile-label="' . pili_esc_attr__( '更新频率' ) . '">';
				PILI::field(
					array(
						'id'         => 'changefreq',
						'type'       => 'select',
						'options'    => $freq_opts,
						'searchable' => false,
						'clearable'  => false,
					),
					$freq,
					$row_unique,
					'field/sitemap_items'
				);
				echo '</div>';

				echo '<div class="pili-sitemap-items__col pili-sitemap-items__col--prio" data-mobile-label="' . pili_esc_attr__( '优先级' ) . '">';
				PILI::field(
					array(
						'id'          => 'priority',
						'type'        => 'slider',
						'min'         => 0,
						'max'         => 1,
						'step'        => 0.1,
						'precision'   => 1,
						'show_input'  => true,
						'show_labels' => false,
						'show_ticks'  => false,
						'color'       => 'blue',
						'size'        => 'small',
					),
					$prio,
					$row_unique,
					'field/sitemap_items'
				);
				echo '</div>';

				echo '</div>';
			}

			echo '</div>';

			$desc = ! empty( $this->field['desc'] ) ? (string) $this->field['desc'] : '';
			if ( '' !== $desc ) {
				echo '<p class="mt-3 text-sm text-gray-500 mb-0">' . wp_kses_post( $desc ) . '</p>';
			}

			echo $this->field_after();
		}
	}
}

add_filter(
	'pili_validate_field_sitemap_items',
	static function ( $value, $field ) {
		return \Pili\Core\PILI_Field_sitemap_items::sanitize_value( $value, is_array( $field ) ? $field : array() );
	},
	10,
	2
);
