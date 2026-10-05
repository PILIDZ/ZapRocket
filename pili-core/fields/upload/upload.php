<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field: upload
 *
 * 本地文件选择组件（参考 gallery 拖拽区视觉）。
 * 默认不入库、不走媒体库，供业务页自行 AJAX 上传。
 *
 * @package PILI Framework
 * @since   1.1.8
 */
if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_upload' ) ) {

	/**
	 * PILI_Field_upload 文件上传字段。
	 */
	class PILI_Field_upload extends PILI_Fields {

		/**
		 * @param array  $field  字段配置。
		 * @param mixed  $value  字段值（本组件通常为空）。
		 * @param string $unique 唯一前缀。
		 * @param string $where  位置。
		 * @param string $parent 父级。
		 */
		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * 渲染字段。
		 */
		public function render() {
			$args = wp_parse_args(
				$this->field,
				array(
					'multiple'       => false,
					'accept'         => '',
					'max_files'      => 1,
					'max_file_size'  => 0, // bytes；0 = 不限制（前端提示用）。
					'button_title' => pili__( '选择媒体' ),
					'clear_title' => pili__( '清空选择' ),
					'upload_text' => pili__( '点击或拖拽文件到此处' ),
					'upload_hint' => pili__( '或点击浏览' ),
					'show_list'      => true,
					'show_filesize'  => true,
					'input_id'       => '',
					'input_class'    => 'pili-upload-native',
					// local：仅选文件，由外部 JS 处理；不写 name，避免进 options。
					'mode'           => 'local',
				)
			);

			$field_id   = $this->field_id();
			$input_id   = ! empty( $args['input_id'] ) ? (string) $args['input_id'] : ( $field_id ? $field_id . '-file' : 'pili-upload-file' );
			$max_files  = max( 1, (int) $args['max_files'] );
			$multiple   = ! empty( $args['multiple'] ) || $max_files > 1;
			$accept     = (string) $args['accept'];
			$max_size   = max( 0, (int) $args['max_file_size'] );

			echo $this->field_before();

			echo '<div class="pili-upload-field" data-field-id="' . esc_attr( $field_id ) . '" data-max-files="' . esc_attr( (string) $max_files ) . '" data-max-file-size="' . esc_attr( (string) $max_size ) . '" data-mode="' . esc_attr( (string) $args['mode'] ) . '">';

			echo '<div class="pili-upload-container pili-upload-empty">';

			echo '<div class="pili-upload-toolbar flex flex-wrap items-center justify-between gap-3 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">';
			echo '<div class="flex flex-wrap items-center gap-2">';
			echo '<button type="button" class="pili-upload-browse-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
			echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>';
			echo esc_html( (string) $args['button_title'] );
			echo '</button>';
			echo '</div>';
			echo '<button type="button" class="pili-upload-clear-btn hidden text-sm text-red-600 hover:text-red-800 font-medium">' . esc_html( (string) $args['clear_title'] ) . '</button>';
			echo '</div>';

			echo '<div class="pili-upload-dropzone border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-gray-400 transition-colors duration-200 mb-4 cursor-pointer" role="button" tabindex="0" aria-label="' . esc_attr( (string) $args['upload_text'] ) . '">';
			echo '<div class="space-y-4 pointer-events-none">';
			echo '<div class="mx-auto w-12 h-12 text-gray-400">';
			echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>';
			echo '</div>';
			echo '<div>';
			echo '<p class="text-lg font-medium text-gray-900">' . esc_html( (string) $args['upload_text'] ) . '</p>';
			echo '<p class="text-sm text-gray-500 mt-1">' . esc_html( (string) $args['upload_hint'] ) . '</p>';
			echo '</div>';
			echo '</div>';
			echo '</div>';

			$native_attrs = array(
				'type'  => 'file',
				'id'    => $input_id,
				'class' => (string) $args['input_class'],
				'style' => 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;',
			);
			if ( $accept ) {
				$native_attrs['accept'] = $accept;
			}
			if ( $multiple ) {
				$native_attrs['multiple'] = 'multiple';
			}

			echo '<input';
			foreach ( $native_attrs as $attr_key => $attr_val ) {
				echo ' ' . esc_attr( $attr_key ) . '="' . esc_attr( $attr_val ) . '"';
			}
			echo ' />';

			if ( ! empty( $args['show_list'] ) ) {
				echo '<ul class="pili-upload-file-list hidden space-y-2 mb-2" data-show-filesize="' . ( ! empty( $args['show_filesize'] ) ? '1' : '0' ) . '"></ul>';
			}

			echo '</div>'; // container
			echo '</div>'; // field

			echo $this->field_after();
		}

		/**
		 * 加载脚本。
		 */
		public function enqueue() {
			$handle = pili_asset_handle( 'field-upload' );
			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/upload.js',
				array( 'jquery' ),
				defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.1.8',
				true
			);

			pili_localize_bag(
				$handle,
				'upload',
				array(
					'i18n' => array(
						'noticeTitle' => pili__( '提示' ),
						'fileTooLarge' => pili__( '文件过大' ),
						'remove' => pili__( '移除' ),
					),
				)
			);
		}
	}
}
