<?php
/**
 * Field: steps — 圆形阶段指示器（向导 / 安装流程）。
 *
 * @package Xun Framework
 */

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_steps' ) ) {

	/**
	 * 圆形分步进度条；不存业务数据时可作展示，value 为当前步索引（从 0 起）。
	 */
	class PILI_Field_steps extends PILI_Fields {

		/**
		 * @param array $field Field config.
		 * @return array{steps:array<int,array{title:string,desc?:string}>,current:int,clickable:bool,size:string,color:string,show_labels:bool,id:string}
		 */
		public static function normalize_args( $field ) {
			$field = is_array( $field ) ? $field : array();
			$steps = array();
			if ( ! empty( $field['steps'] ) && is_array( $field['steps'] ) ) {
				foreach ( $field['steps'] as $item ) {
					if ( is_string( $item ) ) {
						$steps[] = array(
							'title' => $item,
							'desc'  => '',
						);
						continue;
					}
					if ( ! is_array( $item ) ) {
						continue;
					}
					$steps[] = array(
						'title' => isset( $item['title'] ) ? (string) $item['title'] : '',
						'desc'  => isset( $item['desc'] ) ? (string) $item['desc'] : '',
					);
				}
			}

			$current = 0;
			if ( isset( $field['current'] ) ) {
				$current = (int) $field['current'];
			}

			return array(
				'steps'       => $steps,
				'current'     => max( 0, $current ),
				'clickable'   => ! isset( $field['clickable'] ) || (bool) $field['clickable'],
				'size'        => isset( $field['size'] ) ? sanitize_key( (string) $field['size'] ) : 'md',
				'color'       => isset( $field['color'] ) ? sanitize_key( (string) $field['color'] ) : 'blue',
				'show_labels' => ! isset( $field['show_labels'] ) || (bool) $field['show_labels'],
				'id'          => isset( $field['id'] ) ? (string) $field['id'] : '',
				'class'       => isset( $field['class'] ) ? (string) $field['class'] : '',
			);
		}

		/**
		 * 渲染阶段条 HTML（供字段 render 或业务回调复用）。
		 *
		 * @param array      $args    见 normalize_args；可含 done_steps (int[]).
		 * @param int|string $current 当前步（优先于 args.current）。
		 * @return string
		 */
		public static function render_bar( $args = array(), $current = null ) {
			$cfg = self::normalize_args( $args );
			if ( null !== $current && '' !== $current ) {
				$cfg['current'] = max( 0, (int) $current );
			}
			if ( empty( $cfg['steps'] ) ) {
				return '';
			}

			$done_steps = array();
			if ( ! empty( $args['done_steps'] ) && is_array( $args['done_steps'] ) ) {
				foreach ( $args['done_steps'] as $d ) {
					$done_steps[] = (int) $d;
				}
			}

			$total = count( $cfg['steps'] );
			if ( $cfg['current'] > $total - 1 ) {
				$cfg['current'] = $total - 1;
			}

			$size  = in_array( $cfg['size'], array( 'sm', 'md', 'lg' ), true ) ? $cfg['size'] : 'md';
			$color = $cfg['color'] ? $cfg['color'] : 'blue';
			$uid   = $cfg['id'] ? $cfg['id'] : 'pili-steps-' . wp_unique_id();

			$classes = array(
				'pili-steps',
				'pili-steps--' . $size,
				'pili-steps--' . $color,
			);
			if ( $cfg['clickable'] ) {
				$classes[] = 'pili-steps--clickable';
			}
			if ( $cfg['class'] ) {
				$classes[] = $cfg['class'];
			}

			$html  = '<div id="' . esc_attr( $uid ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" data-pili-steps="1" data-current="' . esc_attr( (string) $cfg['current'] ) . '" role="list">';
			$html .= '<ol class="pili-steps__list">';

			foreach ( $cfg['steps'] as $i => $step ) {
				$is_current = ( (int) $i === (int) $cfg['current'] );
				$is_done    = in_array( (int) $i, $done_steps, true ) || ( (int) $i < (int) $cfg['current'] );
				$item_class = 'pili-steps__item';
				if ( $is_current ) {
					$item_class .= ' is-current';
				}
				if ( $is_done ) {
					$item_class .= ' is-done';
				}

				$tag = $cfg['clickable'] ? 'button' : 'span';

				$html .= '<li class="' . esc_attr( $item_class ) . '" data-step="' . esc_attr( (string) $i ) . '" role="listitem">';
				if ( $i > 0 ) {
					$html .= '<span class="pili-steps__connector" aria-hidden="true"></span>';
				}
				$html .= '<' . $tag . ' class="pili-steps__node" data-step="' . esc_attr( (string) $i ) . '"';
				if ( 'button' === $tag ) {
					$html .= ' type="button" aria-current="' . ( $is_current ? 'step' : 'false' ) . '"';
				}
				$html .= '>';
				$html .= '<span class="pili-steps__circle">';
				if ( $is_done && ! $is_current ) {
					$html .= '<span class="pili-steps__check" aria-hidden="true">✓</span>';
				} else {
					$html .= '<span class="pili-steps__num">' . esc_html( (string) ( $i + 1 ) ) . '</span>';
				}
				$html .= '</span>';
				if ( $cfg['show_labels'] ) {
					$html .= '<span class="pili-steps__label">' . esc_html( $step['title'] ) . '</span>';
					if ( ! empty( $step['desc'] ) ) {
						$html .= '<span class="pili-steps__desc">' . esc_html( $step['desc'] ) . '</span>';
					}
				}
				$html .= '</' . $tag . '>';
				$html .= '</li>';
			}

			$html .= '</ol>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 入队样式。
		 */
		public static function enqueue_assets() {
			$handle = pili_asset_handle( 'field-steps' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/steps.css',
				array(),
				defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.0'
			);
		}

		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		public function render() {
			echo $this->field_before();

			$args            = $this->field;
			$args['current'] = is_numeric( $this->value ) ? (int) $this->value : ( isset( $args['current'] ) ? (int) $args['current'] : 0 );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 受控后台 HTML
			echo self::render_bar( $args, $args['current'] );

			if ( ! empty( $this->field['id'] ) && empty( $this->field['display_only'] ) ) {
				echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( (string) $args['current'] ) . '" class="pili-steps-input" />';
			}

			echo $this->field_after();
		}

		public function enqueue() {
			self::enqueue_assets();
		}
	}
}
