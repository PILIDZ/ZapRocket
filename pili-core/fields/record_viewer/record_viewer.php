<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Field: record_viewer
 *
 * 分页键值对数据查看器（只读展示，不参与存储）。
 * 视觉参考：导入插件《UI组件/字段示例组件.html》。
 */
	class PILI_Field_record_viewer extends PILI_Fields {

		public function render() {
			$keys            = $this->get_keys_config();
			$records         = $this->get_records();
			$empty           = isset( $this->field['empty_text'] ) ? (string) $this->field['empty_text'] : pili__( '暂无数据' );
			$loading         = isset( $this->field['loading_text'] ) ? (string) $this->field['loading_text'] : pili__( '加载中…' );
			$truncate        = isset( $this->field['truncate_length'] ) ? max( 20, (int) $this->field['truncate_length'] ) : 120;
			$record_key = isset( $this->field['record_key'] ) ? (string) $this->field['record_key'] : 'id';
			$idb_db     = isset( $this->field['idb_db'] ) ? sanitize_key( (string) $this->field['idb_db'] ) : '';
			$idb_store  = isset( $this->field['idb_store'] ) ? sanitize_key( (string) $this->field['idb_store'] ) : '';

			$records_json = wp_json_encode( array_values( $records ), JSON_UNESCAPED_UNICODE );
			$keys_json    = wp_json_encode( $keys, JSON_UNESCAPED_UNICODE );

			echo $this->field_before();

			echo '<div class="pili-field-safe-wrapper" style="display:grid;grid-template-columns:minmax(0,1fr);width:100%;box-sizing:border-box;">';

			echo '<div class="pili-record-viewer-field w-full bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden"'
				. ' data-field-id="' . esc_attr( $this->field['id'] ) . '"'
				. ' data-record-key="' . esc_attr( $record_key ) . '"'
				. ' data-truncate-length="' . esc_attr( (string) $truncate ) . '"'
				. ' data-empty-text="' . esc_attr( $empty ) . '"'
				. ' data-loading-text="' . esc_attr( $loading ) . '"'
				. ' data-records="' . esc_attr( $records_json ? $records_json : '[]' ) . '"'
				. ' data-keys="' . esc_attr( $keys_json ? $keys_json : '[]' ) . '"'
				. ( '' !== $idb_db ? ' data-idb-db="' . esc_attr( $idb_db ) . '"' : '' )
				. ( '' !== $idb_store ? ' data-idb-store="' . esc_attr( $idb_store ) . '"' : '' )
				. ' style="width:100%;box-sizing:border-box;">';

			echo '<div class="pili-record-viewer-nav flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white">';
			echo '<button type="button" class="pili-record-viewer-prev pili-record-viewer-nav-btn" aria-label="' . pili_esc_attr__( '上一条' ) . '">';
			echo '<i class="ri-arrow-left-s-line" aria-hidden="true"></i>';
			echo '</button>';

			echo '<div class="pili-record-viewer-indicator flex-1 flex justify-center items-center gap-3 min-w-0">';
			$this->render_page_input();
			echo '<span class="pili-record-viewer-of text-[12px] text-gray-500 whitespace-nowrap">' . pili_esc_html__( 'of' ) . ' <span class="pili-record-viewer-total text-gray-800 font-medium">0</span></span>';
			echo '</div>';

			echo '<button type="button" class="pili-record-viewer-next pili-record-viewer-nav-btn" aria-label="' . pili_esc_attr__( '下一条' ) . '">';
			echo '<i class="ri-arrow-right-s-line" aria-hidden="true"></i>';
			echo '</button>';
			echo '</div>';

			echo '<div class="pili-record-viewer-body p-3 bg-gray-50/50">';
			echo '<div class="pili-table-wrap pili-table-scroll-shell rounded-md border border-gray-200 bg-white" style="display:block;width:100%;box-sizing:border-box;border:1px solid #e5e7eb;border-radius:0.375rem;background:#ffffff;overflow-x:auto;">';
			echo '<table class="pili-record-viewer-grid w-full border-collapse text-left" style="margin:0;width:100%;border-collapse:collapse;">';
			echo '<tbody class="pili-record-viewer-rows">';
			echo '<tr class="pili-record-viewer-placeholder-row"><td colspan="2" style="text-align:center;color:#64748b;border:1px solid #e5e7eb;padding:1rem;font-size:13px;">' . esc_html( $loading ) . '</td></tr>';
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
			echo '</div>';

			echo '</div>'; // pili-record-viewer-field
			echo '</div>'; // pili-field-safe-wrapper

			echo $this->field_after();
		}

		/**
		 * @return array<int, array<string, mixed>>
		 */
		private function get_records() {
			$records = is_array( $this->value ) ? $this->value : array();
			if ( ! empty( $records ) ) {
				return $records;
			}

			$data_callback = isset( $this->field['data_callback'] ) ? $this->field['data_callback'] : null;
			if ( is_string( $data_callback ) && '' !== $data_callback && is_callable( $data_callback ) ) {
				$callback_rows = call_user_func( $data_callback, $this->field, $this->value, $this->unique, $this->where, $this->parent );
				return is_array( $callback_rows ) ? $callback_rows : array();
			}

			$data_source = isset( $this->field['data_source'] ) ? (string) $this->field['data_source'] : '';
			if ( '' === $data_source || ! class_exists( PILI::class ) ) {
				return array();
			}

			$source_rows = PILI::get_option( $this->unique, $data_source, array() );
			return is_array( $source_rows ) ? $source_rows : array();
		}

		/**
		 * @return array<int, array<string, mixed>>
		 */
		private function get_keys_config() {
			$keys = isset( $this->field['keys'] ) && is_array( $this->field['keys'] ) ? $this->field['keys'] : array();
			if ( ! empty( $keys ) ) {
				return $this->normalize_keys( $keys );
			}

			$records = $this->get_records();
			if ( empty( $records ) || ! is_array( $records[0] ) ) {
				return array();
			}

			$auto = array();
			foreach ( array_keys( $records[0] ) as $key ) {
				$auto[] = array(
					'id'         => (string) $key,
					'label'      => (string) $key,
					'expandable' => 'content' === $key,
				);
			}
			return $auto;
		}

		/**
		 * @param array<int, mixed> $keys Raw keys config.
		 * @return array<int, array<string, mixed>>
		 */
		private function normalize_keys( $keys ) {
			$out = array();
			foreach ( $keys as $item ) {
				if ( is_string( $item ) && '' !== $item ) {
					$out[] = array(
						'id'         => $item,
						'label'      => $item,
						'expandable' => false,
						'allow_html' => false,
					);
					continue;
				}
				if ( ! is_array( $item ) || empty( $item['id'] ) ) {
					continue;
				}
				$out[] = array(
					'id'         => (string) $item['id'],
					'label'      => isset( $item['label'] ) ? (string) $item['label'] : (string) $item['id'],
					'expandable' => ! empty( $item['expandable'] ),
					'allow_html' => ! empty( $item['allow_html'] ),
				);
			}
			return $out;
		}

		private function render_page_input() {
			$page_field_id = sanitize_key( $this->field['id'] . '_page' );

			echo '<div class="pili-record-viewer-page-wrap">';
			PILI::field(
				array(
					'id'         => $page_field_id,
					'type'       => 'text',
					'attributes' => array(
						'type'       => 'number',
						'min'        => '1',
						'class'      => 'pili-record-viewer-page-input text-center',
						'aria-label' => pili__( '当前页码' ),
					),
				),
				1,
				$this->unique,
				$this->where,
				$this->parent
			);
			echo '</div>';
		}

		public function validate( $value ) {
			return $this->value;
		}

		public function enqueue() {
			$handle = pili_asset_handle( 'field-record-viewer' );
			wp_enqueue_style(
				$handle,
				PILI_Setup::$url . '/assets/css/fields/record_viewer.css',
				array(),
				PILI_CORE_VERSION
			);

			wp_enqueue_script(
				$handle,
				PILI_Setup::$url . '/assets/js/fields/record_viewer.js',
				array( 'jquery' ),
				PILI_CORE_VERSION,
				true
			);

			pili_localize_bag(
				$handle,
				'record_viewer',
				array(
					'strings' => array(
						'showMore' => pili__( '展开' ),
						'showLess' => pili__( '收起' ),
						'empty' => pili__( '暂无数据' ),
						'loading' => pili__( '加载中…' ),
						'loadFail' => pili__( '加载数据失败' ),
						'notFound' => pili__( '该记录不存在' ),
					),
				)
			);
		}
	}
