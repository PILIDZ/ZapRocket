<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field: modal
 *
 * 可复用 SaaS 弹窗（只读展示壳，不参与存储）。
 * - 可在分区 fields 中声明预渲染内容
 * - JS API：window.PiliXunModal.open / close / setBody
 */
class PILI_Field_modal extends PILI_Fields {

	/**
	 * @return array<string,string>
	 */
	public static function size_map() {
		return array(
			'sm' => 'pili-modal--sm',
			'md' => 'pili-modal--md',
			'lg' => 'pili-modal--lg',
			'xl' => 'pili-modal--xl',
		);
	}

	public function render() {
		$args = wp_parse_args(
			$this->field,
			array(
				'modal_title'   => '',
				'size'          => 'md',
				'content'       => '',
				'callback'      => null,
				'buttons'       => array(),
				'close_on_esc'  => true,
				'close_on_mask' => true,
				'open'          => false,
			)
		);

		$body = '';
		if ( is_callable( $args['callback'] ) ) {
			$rendered = call_user_func( $args['callback'], $this->field, $this->value, $this->unique, $this->where, $this->parent );
			$body     = is_string( $rendered ) ? $rendered : '';
		} elseif ( is_string( $args['content'] ) ) {
			$body = $args['content'];
		}

		$modal_title = (string) $args['modal_title'];
		if ( '' === $modal_title && ! empty( $this->field['title'] ) ) {
			$modal_title = (string) $this->field['title'];
		}

		$size_key = sanitize_key( (string) $args['size'] );
		$sizes    = self::size_map();
		$size_cls = isset( $sizes[ $size_key ] ) ? $sizes[ $size_key ] : $sizes['md'];
		$field_id = isset( $this->field['id'] ) ? sanitize_key( (string) $this->field['id'] ) : '';

		$buttons = is_array( $args['buttons'] ) ? $args['buttons'] : array();
		$buttons_json = wp_json_encode( array_values( $this->normalize_buttons( $buttons ) ), JSON_UNESCAPED_UNICODE );

		echo $this->field_before();

		echo '<div class="pili-modal"'
			. ' id="' . esc_attr( $field_id ) . '"'
			. ' data-field-id="' . esc_attr( $field_id ) . '"'
			. ' data-pili-modal="1"'
			. ' data-size="' . esc_attr( $size_key ) . '"'
			. ' data-close-on-esc="' . ( $args['close_on_esc'] ? '1' : '0' ) . '"'
			. ' data-close-on-mask="' . ( $args['close_on_mask'] ? '1' : '0' ) . '"'
			. ' data-buttons="' . esc_attr( $buttons_json ? $buttons_json : '[]' ) . '"'
			. ' hidden'
			. ' aria-hidden="true"'
			. '>';

		echo '<div class="pili-modal__mask" data-pili-modal-mask="1"></div>';
		echo '<div class="pili-modal__dialog ' . esc_attr( $size_cls ) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $field_id . '_title' ) . '">';

		echo '<div class="pili-modal__header">';
		echo '<h3 class="pili-modal__title" id="' . esc_attr( $field_id . '_title' ) . '">' . esc_html( $modal_title ) . '</h3>';
		echo '<button type="button" class="pili-modal__close" data-pili-modal-close="1" aria-label="' . pili_esc_attr__( '关闭弹窗' ) . '">';
		echo '<i class="ri-close-line" aria-hidden="true"></i>';
		echo '</button>';
		echo '</div>';

		echo '<div class="pili-modal__body" data-pili-modal-body="1">';
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 由回调/声明方负责安全输出.
		echo '</div>';

		echo '<div class="pili-modal__footer" data-pili-modal-footer="1">';
		echo $this->render_buttons_html( $buttons );
		echo '</div>';

		echo '</div>'; // dialog
		echo '</div>'; // root

		echo $this->field_after();
	}

	/**
	 * @param array<int,mixed> $buttons Raw buttons.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_buttons( $buttons ) {
		$out = array();
		foreach ( $buttons as $btn ) {
			if ( ! is_array( $btn ) || empty( $btn['id'] ) ) {
				continue;
			}
			$out[] = array(
				'id'      => sanitize_key( (string) $btn['id'] ),
				'label'   => isset( $btn['label'] ) ? (string) $btn['label'] : (string) $btn['id'],
				'variant' => isset( $btn['variant'] ) ? sanitize_key( (string) $btn['variant'] ) : 'secondary',
				'icon'    => isset( $btn['icon'] ) ? (string) $btn['icon'] : '',
				'close'   => ! empty( $btn['close'] ),
			);
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $buttons Buttons.
	 * @return string
	 */
	private function render_buttons_html( $buttons ) {
		$buttons = $this->normalize_buttons( $buttons );
		if ( empty( $buttons ) ) {
			return '';
		}
		$html = '';
		foreach ( $buttons as $btn ) {
			$variant = in_array( $btn['variant'], array( 'primary', 'secondary', 'danger' ), true ) ? $btn['variant'] : 'secondary';
			$class   = 'pili-modal__btn pili-modal__btn--' . $variant;
			$html   .= '<button type="button" class="' . esc_attr( $class ) . '" data-pili-modal-action="' . esc_attr( $btn['id'] ) . '"';
			if ( ! empty( $btn['close'] ) ) {
				$html .= ' data-pili-modal-close="1"';
			}
			$html .= '>';
			if ( ! empty( $btn['icon'] ) && preg_match( '/^ri-[a-z0-9-]+$/', (string) $btn['icon'] ) ) {
				$html .= '<i class="' . esc_attr( (string) $btn['icon'] ) . '" aria-hidden="true"></i>';
			}
			$html .= '<span>' . esc_html( $btn['label'] ) . '</span>';
			$html .= '</button>';
		}
		return $html;
	}

	public function validate( $value ) {
		return $this->value;
	}

	public function enqueue() {
		$handle = pili_asset_handle( 'field-modal' );
		wp_enqueue_style(
			$handle,
			PILI_Setup::$url . '/assets/css/fields/modal.css',
			array(),
			PILI_CORE_VERSION
		);

		wp_enqueue_script(
			$handle,
			PILI_Setup::$url . '/assets/js/fields/modal.js',
			array( 'jquery' ),
			PILI_CORE_VERSION,
			true
		);

		pili_localize_bag(
			$handle,
			'modal',
			array(
				'strings' => array(
					'close' => pili__( '关闭' ),
					'confirm' => pili__( '确认' ),
					'cancel' => pili__( '取消' ),
				),
			)
		);
	}
}
