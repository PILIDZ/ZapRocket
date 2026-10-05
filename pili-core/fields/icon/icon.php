<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PILI Framework 图标选择字段类型
 *
 * 使用主题内 Remix Icon（assets/libs/remixicon 或 vendor 包），保存值为完整类名如 ri-home-line。
 *
 * @package PILI Framework
 * @author  June
 * @link    https://www.xuntheme.com
 * @since   1.0
 * @version 1.0
 */
	class PILI_Field_icon extends PILI_Fields {

		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		public function render() {

			$size = ! empty( $this->field['size'] ) ? $this->field['size'] : '24';
			$show_search  = array_key_exists( 'show_search', $this->field ) ? (bool) $this->field['show_search'] : true;
			$show_preview = array_key_exists( 'show_preview', $this->field ) ? (bool) $this->field['show_preview'] : true;
			$placeholder = ! empty( $this->field['placeholder'] ) ? $this->field['placeholder'] : pili__( '选择图标...' );

			$current_value = ! empty( $this->value ) ? $this->value : '';

			$available_icons = array();
			$icon_catalog    = ! empty( $this->field['icon_catalog'] ) ? (string) $this->field['icon_catalog'] : '';
			if ( 'global' !== $icon_catalog && class_exists( Remix_Icons::class ) ) {
				$available_icons = Remix_Icons::get_available_icons();
			}

			echo $this->field_before();

			static $pili_icon_inline_styles_printed = false;
			if ( ! $pili_icon_inline_styles_printed ) {
				$pili_icon_inline_styles_printed = true;
				echo '<style>
				.pili-icon-field { position: relative; }
				.pili-icon-current {
					display: flex; align-items: center; gap: 12px; padding: 12px;
					border: 1px solid #d1d5db; border-radius: 6px; background: #ffffff;
					cursor: pointer; transition: all 0.2s ease;
				}
				.pili-icon-current:hover { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
				.pili-icon-preview { display: flex; align-items: center; gap: 8px; flex: 1; }
				.pili-icon-preview .pili-ri-icon { flex-shrink: 0; color: #374151; line-height: 1; }
				.pili-icon-name { font-size: 14px; color: #374151; font-weight: 500; }
				.pili-icon-placeholder { flex: 1; color: #9ca3af; font-size: 14px; }
				.pili-icon-select-btn, .pili-icon-clear-btn {
					padding: 6px 12px; font-size: 12px; border: 1px solid #d1d5db;
					border-radius: 4px; background: #ffffff; color: #374151;
					cursor: pointer; transition: all 0.2s ease;
				}
				.pili-icon-select-btn:hover, .pili-icon-clear-btn:hover { background: #f9fafb; border-color: #3b82f6; }
				.pili-icon-clear-btn { color: #dc2626; border-color: #fecaca; }
				.pili-icon-clear-btn:hover { background: #fef2f2; border-color: #dc2626; }
				.pili-icon-modal {
					position: fixed; top: 0; left: 0; right: 0; bottom: 0;
					background: rgba(0, 0, 0, 0.5); z-index: 99999;
					display: flex; align-items: center; justify-content: center;
					backdrop-filter: blur(12px);
					-webkit-backdrop-filter: blur(12px);
				}
				.pili-icon-modal-content {
					background: #ffffff; border-radius: 8px;
					box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
					max-width: 600px; width: 90%; max-height: 80vh;
					display: flex; flex-direction: column;
				}
				.pili-icon-modal-header {
					display: flex; align-items: center; justify-content: space-between;
					padding: 20px; border-bottom: 1px solid #e5e7eb;
				}
				.pili-icon-modal-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #111827; }
				.pili-icon-modal-close {
					background: none; border: none; font-size: 24px; color: #6b7280;
					cursor: pointer; padding: 0; width: 32px; height: 32px;
					display: flex; align-items: center; justify-content: center;
					border-radius: 4px; transition: all 0.2s ease;
				}
				.pili-icon-modal-close:hover { background: #f3f4f6; color: #374151; }
				.pili-icon-search { padding: 20px; border-bottom: 1px solid #e5e7eb; }
				.pili-icon-search-input {
					width: 100%; padding: 12px; border: 1px solid #d1d5db;
					border-radius: 6px; font-size: 14px; outline: none;
					transition: all 0.2s ease;
				}
				.pili-icon-search-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
				.pili-icon-grid {
					padding: 20px; display: grid;
					grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
					gap: 12px; max-height: 400px; overflow-y: auto;
				}
				.pili-icon-item {
					display: flex; flex-direction: column; align-items: center; gap: 8px;
					padding: 16px 8px; border: 1px solid #e5e7eb; border-radius: 6px;
					cursor: pointer; transition: all 0.2s ease; background: #ffffff;
				}
				.pili-icon-item:hover {
					border-color: #3b82f6; background: #f8fafc;
					transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
				}
				.pili-icon-item.selected {
					border-color: #3b82f6; background: #eff6ff;
					box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
				}
				.pili-icon-item .pili-ri-icon { color: #374151; transition: color 0.2s ease; font-size: 24px; line-height: 1; }
				.pili-icon-item:hover .pili-ri-icon, .pili-icon-item.selected .pili-ri-icon { color: #3b82f6; }
				.pili-icon-item-name {
					font-size: 11px; color: #6b7280; text-align: center;
					line-height: 1.3; word-break: break-all;
				}
				.pili-icon-item.selected .pili-icon-item-name { color: #3b82f6; font-weight: 500; }
				.pili-icon-modal-footer {
					display: flex; justify-content: flex-end; gap: 12px;
					padding: 20px; border-top: 1px solid #e5e7eb;
				}
				.pili-icon-modal-cancel, .pili-icon-modal-confirm {
					padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 4px;
					font-size: 14px; cursor: pointer; transition: all 0.2s ease;
				}
				.pili-icon-modal-cancel { background: #ffffff; color: #374151; }
				.pili-icon-modal-cancel:hover { background: #f9fafb; }
				.pili-icon-modal-confirm { background: #3b82f6; color: #ffffff; border-color: #3b82f6; }
				.pili-icon-modal-confirm:hover { background: #2563eb; border-color: #2563eb; }
				.pili-icon-live-preview {
					margin-top: 16px; padding: 16px; background: #f9fafb;
					border: 1px solid #e5e7eb; border-radius: 6px;
				}
				.pili-icon-live-preview h4 { margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #374151; }
				.pili-icon-preview-sizes { display: flex; gap: 16px; align-items: center; }
				.pili-icon-preview-item { display: flex; align-items: center; gap: 8px; }
				.pili-icon-preview-label { font-size: 12px; color: #6b7280; font-weight: 500; }
				.pili-icon-no-results { text-align: center; padding: 40px 20px; color: #6b7280; font-size: 14px; }
				@media (max-width: 640px) {
					.pili-icon-modal-content { width: 95%; max-height: 90vh; }
					.pili-icon-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
					.pili-icon-item { padding: 12px 6px; }
					.pili-icon-preview-sizes { flex-direction: column; align-items: flex-start; gap: 8px; }
				}
			</style>';
			}

			// fieldId 必须与 field_id() 一致：Repeater 子字段若仅用 field['id']（如 icon），多行会共用同一 key，
			// icon.js 会以 instances[fieldId] 去重，导致除第一行外「选择图标」未绑定、点击无反应。
			$pili_icon_field_id = $this->field_id();

			echo '<div class="pili-icon-field" data-field-id="' . esc_attr( $pili_icon_field_id ) . '" data-size="' . esc_attr( $size ) . '">';

			echo '<input type="hidden" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $current_value ) . '" class="pili-icon-input" />';

			echo '<div class="pili-icon-selector">';

			echo '<div class="pili-icon-current" data-placeholder="' . esc_attr( $placeholder ) . '">';
			if ( ! empty( $current_value ) && class_exists( Remix_Icons::class ) ) {
				echo '<div class="pili-icon-preview">';
				echo Remix_Icons::get_icon( $current_value, array( 'size' => $size, 'class' => 'w-6 h-6' ) );
				echo '<span class="pili-icon-name">' . esc_html( $current_value ) . '</span>';
				echo '</div>';
				echo '<button type="button" class="pili-icon-select-btn">' . pili_esc_html__( '选择图标' ) . '</button>';
				echo '<button type="button" class="pili-icon-clear-btn">' . pili_esc_html__( '清除' ) . '</button>';
			} else {
				echo '<div class="pili-icon-placeholder">' . esc_html( $placeholder ) . '</div>';
				echo '<button type="button" class="pili-icon-select-btn">' . pili_esc_html__( '选择图标' ) . '</button>';
			}
			echo '</div>';

			echo '<div class="pili-icon-modal-data" style="display: none;">';
			echo '<script type="application/json" class="pili-icon-config">';
			$icon_payload = array(
				'fieldId'        => (string) $pili_icon_field_id,
				'currentValue'   => (string) $current_value,
				'placeholder'    => (string) $placeholder,
				'showSearch'     => (bool) $show_search,
				'showPreview'    => (bool) $show_preview,
				'size'           => (string) $size,
				'availableIcons' => array_values( $available_icons ),
				'messages'       => array(
					'selectIcon' => pili__( '选择图标' ),
					'searchPlaceholder' => pili__( '搜索…' ),
					'cancel' => pili__( '取消' ),
					'confirm' => pili__( '确认' ),
					'noIconsFound' => pili__( '未找到匹配的图标' ),
				),
			);
			$json_flags  = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
			$json_out    = wp_json_encode( $icon_payload, $json_flags );
			echo ( false === $json_out ) ? '{}' : $json_out;
			echo '</script>';
			echo '</div>';

			echo '</div>';

			if ( $show_preview && ! empty( $current_value ) && class_exists( Remix_Icons::class ) && Remix_Icons::icon_exists( $current_value ) ) {
				echo '<div class="pili-icon-live-preview">';
				echo '<h4>' . pili_esc_html__( '预览效果：' ) . '</h4>';
				echo '<div class="pili-icon-preview-sizes">';

				$preview_sizes = array( '16', '20', '24' );
				foreach ( $preview_sizes as $preview_size ) {
					echo '<div class="pili-icon-preview-item">';
					echo '<span class="pili-icon-preview-label">' . esc_html( $preview_size ) . 'px:</span>';
					echo Remix_Icons::get_icon(
						$current_value,
						array(
							'size'  => $preview_size,
							'class' => 'inline-block',
						)
					);
					echo '</div>';
				}

				echo '</div>';
				echo '</div>';
			}

			echo '</div>';

			echo $this->field_after();
		}

		public function enqueue() {

			if ( class_exists( Remix_Icons::class ) ) {
				Remix_Icons::enqueue_style();
			}

			// 脚本仅依赖 jquery：勿将样式作为 script 依赖，否则样式未注册时整段 icon.js 可能不加载，导致点击无反应。
			$handle = pili_asset_handle( 'field-icon' );
			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/icon.js',
				array( 'jquery' ),
				PILI_Setup::$version,
				true
			);

			pili_localize_bag( $handle, 'icon',
				array(
					'searchPlaceholder' => pili__( '搜索…' ),
					'noResults' => pili__( '无结果' ),
					'selectIcon' => pili__( '选择图标' ),
					'clearIcon' => pili__( '清空图标' ),
					'confirm' => pili__( '确认' ),
					'cancel' => pili__( '取消' ),
					'clear' => pili__( '清空' ),
					'searchIconsLabel' => pili__( '搜索图标' ),
					'previewEffect' => pili__( '预览效果：' ),
				)
			);
		}

		public function validate( $value ) {

			if ( empty( $value ) ) {
				return '';
			}

			if ( ! is_string( $value ) ) {
				return '';
			}

			if ( ! preg_match( '/^ri-[a-z0-9-]+$/', $value ) ) {
				return '';
			}

			// 目录未就绪时仍允许合法 ri-*（避免误清空已保存值）。
			if ( class_exists( Remix_Icons::class ) && Remix_Icons::is_stylesheet_ready() && ! Remix_Icons::icon_exists( $value ) ) {
				return '';
			}

			return sanitize_text_field( $value );
		}
	}
