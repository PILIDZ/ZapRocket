<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 密码字段类型
 *
 * 独立 password 字段，支持图标显示/隐藏切换；可选 secret_status_callback 展示密钥是否已保存。
 */
	class PILI_Field_password extends PILI_Fields {

		/**
		 * @return array<string,array<string,string>>
		 */
		private static function get_status_presets() {
			return array(
				'configured' => array(
					'icon'  => 'ri-shield-check-line',
					'label' => pili__( '已保存密钥' ),
				),
				'empty'      => array(
					'icon'  => 'ri-key-line',
					'label' => pili__( '未配置密钥' ),
				),
				'pending'    => array(
					'icon'  => 'ri-edit-line',
					'label' => pili__( '已填写，保存后生效' ),
				),
			);
		}

		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * @param array<string,mixed> $field
		 * @param mixed               $value
		 * @param array<string,mixed> $context unique, where, parent
		 * @return array<string,mixed>|null
		 */
		public static function resolve_secret_status( $field, $value, $context ) {
			if ( empty( $field['secret_status_callback'] ) || ! is_callable( $field['secret_status_callback'] ) ) {
				return null;
			}
			return call_user_func( $field['secret_status_callback'], $field, $value, $context );
		}

		/**
		 * @param array<string,mixed>|null $status
		 * @return void
		 */
		public static function render_secret_status_badge( $status ) {
			if ( ! is_array( $status ) ) {
				return;
			}
			$configured = ! empty( $status['configured'] );
			$state      = $configured ? 'configured' : 'empty';
			$presets    = self::get_status_presets();
			$preset     = isset( $presets[ $state ] ) ? $presets[ $state ] : $presets['empty'];
			$icon       = ! empty( $status['icon'] ) ? (string) $status['icon'] : $preset['icon'];
			$label      = ! empty( $status['label'] ) ? (string) $status['label'] : $preset['label'];
			$hint       = isset( $status['hint'] ) ? (string) $status['hint'] : '';

			echo '<div class="pili-password-secret-status pili-password-secret-status--' . esc_attr( $state ) . '" data-secret-status="1" data-initial="' . esc_attr( $state ) . '" role="status">';
			if ( class_exists( Remix_Icons::class ) ) {
				echo Remix_Icons::get_icon( $icon, array( 'class' => 'pili-password-secret-status__icon', 'size' => '16' ) );
			} else {
				echo '<i class="' . esc_attr( $icon ) . ' pili-password-secret-status__icon pili-ri-icon" style="font-size:16px" aria-hidden="true"></i>';
			}
			echo '<span class="pili-password-secret-status__text">' . esc_html( $label ) . '</span>';
			echo '</div>';
			if ( '' !== $hint ) {
				echo '<p class="pili-password-secret-status__hint m-0">' . esc_html( $hint ) . '</p>';
			}
		}

		public function render() {
			$placeholder = ! empty( $this->field['placeholder'] ) ? $this->field['placeholder'] : '';
			$desc        = ! empty( $this->field['desc'] ) ? $this->field['desc'] : '';
			$status      = self::resolve_secret_status(
				$this->field,
				$this->value,
				array(
					'unique' => $this->unique,
					'where'  => $this->where,
					'parent' => $this->parent,
				)
			);

			echo $this->field_before();
			echo '<div>';
			echo '<div class="mt-2 relative">';

			$input_classes = 'block w-full !min-h-10 !h-10 rounded-md bg-white !px-3 !py-2.5 pr-10 appearance-none !text-base/6 !leading-6 box-border text-gray-900 border border-gray-300 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none sm:text-sm/6';
			echo '<input type="password" ';
			echo 'name="' . esc_attr( $this->field_name() ) . '" ';
			echo 'id="' . esc_attr( $this->field_id() ) . '" ';
			echo 'value="' . esc_attr( $this->value ) . '" ';
			echo 'class="pili-input pili-focusable pili-password-input ' . esc_attr( $input_classes ) . '" ';
			echo 'autocomplete="new-password" ';
			if ( null !== $status ) {
				echo 'data-has-secret-status="1" ';
			}
			if ( ! empty( $placeholder ) ) {
				echo 'placeholder="' . esc_attr( $placeholder ) . '" ';
			}
			if ( ! empty( $desc ) ) {
				echo 'aria-describedby="' . esc_attr( $this->field_id() . '-description' ) . '" ';
			}
			echo $this->field_attributes() . ' />';

			$show_password_label = pili_esc_attr__( '显示密码' );
			$hide_password_label = pili_esc_attr__( '隐藏密码' );
			echo '<button type="button" class="pili-password-toggle absolute right-2 top-1/2 -translate-y-1/2 inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="' . $show_password_label . '" data-target="' . esc_attr( $this->field_id() ) . '" data-show-label="' . $show_password_label . '" data-hide-label="' . $hide_password_label . '" data-state="hidden">';
			if ( class_exists( Remix_Icons::class ) ) {
				echo Remix_Icons::get_icon(
					'ri-eye-line',
					array(
						'class' => 'pili-password-toggle__show text-base leading-none',
						'size'  => '16',
					)
				);
				echo Remix_Icons::get_icon(
					'ri-eye-off-line',
					array(
						'class' => 'pili-password-toggle__hide hidden text-base leading-none',
						'size'  => '16',
					)
				);
			} else {
				echo '<i class="ri-eye-line pili-password-toggle__show pili-ri-icon text-base leading-none" style="font-size:16px" aria-hidden="true"></i>';
				echo '<i class="ri-eye-off-line pili-password-toggle__hide hidden pili-ri-icon text-base leading-none" style="font-size:16px" aria-hidden="true"></i>';
			}
			echo '</button>';

			echo '</div>';

			if ( null !== $status ) {
				self::render_secret_status_badge( $status );
			}

			if ( ! empty( $desc ) ) {
				echo '<p id="' . esc_attr( $this->field_id() . '-description' ) . '" class="mt-2 text-sm text-gray-500">';
				echo wp_kses_post( $desc );
				echo '</p>';
			}

			if ( ! empty( $this->field['_error'] ) ) {
				echo '<p class="mt-2 text-sm text-red-600">';
				echo esc_html( $this->field['_error'] );
				echo '</p>';
			}

			echo '</div>';
			// Toggle JS 走 enqueue()：懒分区 innerHTML 不会执行 render 内联 script。
		}

		public function enqueue() {
			$deps = array();
			if ( class_exists( Remix_Icons::class ) ) {
				Remix_Icons::enqueue_style( 'pilidoc-remixicon' );
				$deps[] = 'pilidoc-remixicon';
			}
			$handle = pili_asset_handle( 'field-password' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/password.css',
				$deps,
				PILI_CORE_VERSION
			);

			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/password.js',
				array( 'jquery' ),
				PILI_CORE_VERSION,
				true
			);
			pili_localize_bag( $handle, 'password',
				array(
					'presets'   => self::get_status_presets(),
					'showLabel' => pili__( '显示密码' ),
					'hideLabel' => pili__( '隐藏密码' ),
				)
			);
		}

		public function validate( $value ) {
			return (string) $value;
		}
	}
