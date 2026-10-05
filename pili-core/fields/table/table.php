<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Field: table
 *
 * 在配置页中以表格方式展示结构化数据（只读展示）。
 */
	class PILI_Field_table extends PILI_Fields {

		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		public function render() {
			$columns = isset( $this->field['columns'] ) && is_array( $this->field['columns'] ) ? $this->field['columns'] : array();
			$server_paged = $this->is_server_paged();
			$lazy_data = $this->should_lazy_load_data();
			$rows_pack = $lazy_data ? array( 'items' => array(), 'total' => 0 ) : $this->get_rows();
			$rows      = isset( $rows_pack['items'] ) && is_array( $rows_pack['items'] ) ? $rows_pack['items'] : array();
			$empty   = isset( $this->field['empty_text'] ) ? (string) $this->field['empty_text'] : pili__( '暂无数据' );
			$page_size = isset( $this->field['page_size'] ) ? max( 1, min( 100, (int) $this->field['page_size'] ) ) : 10;
			$page_size_choices = array( 10, 20, 50 );
			if ( ! empty( $this->field['page_size_choices'] ) && is_array( $this->field['page_size_choices'] ) ) {
				$parsed = array();
				foreach ( $this->field['page_size_choices'] as $choice ) {
					$n = (int) $choice;
					if ( $n >= 1 && $n <= 100 ) {
						$parsed[] = $n;
					}
				}
				$parsed = array_values( array_unique( $parsed ) );
				sort( $parsed, SORT_NUMERIC );
				if ( ! empty( $parsed ) ) {
					$page_size_choices = $parsed;
				}
			}
			if ( ! in_array( $page_size, $page_size_choices, true ) ) {
				$page_size = (int) $page_size_choices[0];
			}
			$selectable = isset( $this->field['selectable'] ) ? (bool) $this->field['selectable'] : true;
			$row_key = isset( $this->field['row_key'] ) ? (string) $this->field['row_key'] : 'id';
			$delete_action = isset( $this->field['delete_action'] ) ? (string) $this->field['delete_action'] : '';
			$delete_nonce = isset( $this->field['delete_nonce'] ) ? (string) $this->field['delete_nonce'] : '';
			$delete_label = isset( $this->field['delete_label'] ) && '' !== trim( (string) $this->field['delete_label'] )
				? (string) $this->field['delete_label']
				: pili__( '删除已选' );
			$delete_confirm_title = isset( $this->field['delete_confirm_title'] ) && '' !== trim( (string) $this->field['delete_confirm_title'] )
				? (string) $this->field['delete_confirm_title']
				: '';
			$delete_confirm_text = isset( $this->field['delete_confirm_text'] ) && '' !== trim( (string) $this->field['delete_confirm_text'] )
				? (string) $this->field['delete_confirm_text']
				: '';
			$delete_confirm_ok = isset( $this->field['delete_confirm_ok'] ) && '' !== trim( (string) $this->field['delete_confirm_ok'] )
				? (string) $this->field['delete_confirm_ok']
				: '';
			$clear_action = isset( $this->field['clear_action'] ) ? (string) $this->field['clear_action'] : '';
			$clear_nonce = isset( $this->field['clear_nonce'] ) ? (string) $this->field['clear_nonce'] : '';
			$selection_actions = ( $selectable && ! empty( $this->field['selection_actions'] ) && is_array( $this->field['selection_actions'] ) )
				? array_values(
					array_filter(
						$this->field['selection_actions'],
						static function ( $sa ) {
							return is_array( $sa ) && ! empty( $sa['label'] ) && ! empty( $sa['action'] );
						}
					)
				)
				: array();
			$show_delete = $selectable && '' !== $delete_action;
			$show_clear_all = '' !== $clear_action;
			$show_export = ! array_key_exists( 'toolbar_export', $this->field ) || (bool) $this->field['toolbar_export'];
			$show_search = ! array_key_exists( 'toolbar_search', $this->field ) || (bool) $this->field['toolbar_search'];
			$toolbar_buttons = ( ! empty( $this->field['toolbar_buttons'] ) && is_array( $this->field['toolbar_buttons'] ) )
				? $this->field['toolbar_buttons']
				: array();
			$input_classes = 'pili-input pili-focusable block w-full !min-h-10 !h-10 rounded-md bg-white !px-3 !py-2.5 appearance-none !text-base/6 !leading-6 box-border text-gray-900 border border-gray-300 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none sm:text-sm/6';
			$btn_classes = 'pili-table-btn inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white';
			$pager_btn_classes = 'pili-table-pager-btn pili-table-pager-nav inline-flex items-center justify-center w-[34px] h-8 min-w-[34px] p-0 border border-gray-200 rounded-md text-gray-600 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';
			$action_btn_style = 'display:inline-flex;align-items:center;justify-content:center;padding:0.5rem 0.75rem;line-height:1.2;font-size:13px;font-weight:600;color:#374151;background:#fff;border:1px solid #d1d5db;border-radius:0.375rem;box-shadow:0 1px 2px rgba(0,0,0,.04);cursor:pointer;white-space:nowrap;transition:all .15s ease;font-family:inherit;text-transform:none;text-decoration:none;';

			echo $this->field_before();

			// 关键：WP 后台 form-table/td 可能会被内容无限撑宽，这里用 grid + minmax(0,1fr) 强制“可收缩”。
			echo '<div class="pili-field-safe-wrapper" style="display:grid;grid-template-columns:minmax(0,1fr);width:100%;box-sizing:border-box;">';

			$field_id_attr = isset( $this->field['id'] ) ? (string) $this->field['id'] : '';
			$expand_modal  = isset( $this->field['expand_modal'] ) ? sanitize_key( (string) $this->field['expand_modal'] ) : '';
			$selection_actions_json = $selection_actions ? wp_json_encode( $selection_actions, JSON_UNESCAPED_UNICODE ) : '';
			echo '<div class="pili-table-field space-y-3 rounded-md border border-gray-200 p-4 bg-white" style="width:100%;box-sizing:border-box;" data-page-size="' . esc_attr( (string) $page_size ) . '" data-empty-text="' . esc_attr( $empty ) . '" data-selectable="' . ( $selectable ? 'true' : 'false' ) . '" data-show-delete="' . ( $show_delete ? 'true' : 'false' ) . '" data-show-clear-all="' . ( $show_clear_all ? 'true' : 'false' ) . '" data-row-key="' . esc_attr( $row_key ) . '" data-delete-action="' . esc_attr( $delete_action ) . '" data-delete-nonce="' . esc_attr( $delete_nonce ) . '" data-delete-label="' . esc_attr( $delete_label ) . '" data-delete-confirm-title="' . esc_attr( $delete_confirm_title ) . '" data-delete-confirm-text="' . esc_attr( $delete_confirm_text ) . '" data-delete-confirm-ok="' . esc_attr( $delete_confirm_ok ) . '" data-clear-action="' . esc_attr( $clear_action ) . '" data-clear-nonce="' . esc_attr( $clear_nonce ) . '" data-selection-actions="' . esc_attr( (string) $selection_actions_json ) . '" data-pili-table-ns="pilipost" data-pili-table-build="' . esc_attr( self::asset_build() ) . '" data-lazy-field-id="' . esc_attr( $field_id_attr ) . '" data-unique="' . esc_attr( (string) $this->unique ) . '"';
			if ( '' !== $expand_modal ) {
				echo ' data-expand-modal="' . esc_attr( $expand_modal ) . '"';
			}
			if ( $lazy_data ) {
				echo ' data-lazy-data="1"';
			}
			if ( $server_paged ) {
				// data-server-fetch=1：框架自拉 xun_load_field_data；业务外挂分页（任务队列）只打 data-server-paged、勿写 fetch。
				echo ' data-server-paged="1" data-server-fetch="1"';
			}
			echo '>';
			echo '<div class="pili-table-toolbar flex flex-wrap items-center justify-between gap-3">';
			if ( $show_search ) {
				echo '<div class="pili-table-search-wrap">';
				echo '<span class="pili-table-search-icon" aria-hidden="true"><i class="ri-search-line"></i></span>';
				echo '<input type="text" class="pili-table-search" placeholder="' . pili_esc_attr__( '搜索...' ) . '" autocomplete="off" spellcheck="false" />';
				echo '</div>';
			}
			echo '<div class="flex items-center gap-2">';
			foreach ( $selection_actions as $sa_idx => $sa ) {
				$sa_key   = isset( $sa['key'] ) && (string) $sa['key'] !== '' ? (string) $sa['key'] : ( 'sa' . (string) $sa_idx );
				$sa_label = (string) $sa['label'];
				$sa_var   = isset( $sa['variant'] ) ? (string) $sa['variant'] : 'default';
				$sa_style = $action_btn_style;
				if ( 'success' === $sa_var ) {
					$sa_style .= 'border-color:#bbf7d0;color:#15803d;';
				} elseif ( 'danger' === $sa_var ) {
					$sa_style .= 'border-color:#fecaca;color:#dc2626;';
				} elseif ( 'warning' === $sa_var ) {
					$sa_style .= 'border-color:#fed7aa;color:#ea580c;';
				}
				echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-table-selection-action" data-sa-key="' . esc_attr( $sa_key ) . '" style="' . esc_attr( $sa_style ) . '" disabled>' . esc_html( $sa_label ) . '</button>';
			}
			if ( $show_delete ) {
				echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-table-delete-selected" style="' . esc_attr( $action_btn_style . 'border-color:#fecaca;color:#dc2626;' ) . '">' . esc_html( $delete_label ) . '</button>';
			}
			if ( $show_clear_all ) {
				echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-table-clear-all" style="' . esc_attr( $action_btn_style . 'border-color:#fed7aa;color:#ea580c;' ) . '">' . pili_esc_html__( '清空全部' ) . '</button>';
			}
			if ( $show_export ) {
				echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-table-export" style="' . esc_attr( $action_btn_style ) . '">' . pili_esc_html__( '导出 CSV' ) . '</button>';
			}
			foreach ( $toolbar_buttons as $tb ) {
				if ( ! is_array( $tb ) || empty( $tb['label'] ) ) {
					continue;
				}
				$tb_id    = isset( $tb['id'] ) ? (string) $tb['id'] : '';
				$tb_label = (string) $tb['label'];
				$tb_class = isset( $tb['class'] ) ? trim( (string) $tb['class'] ) : '';
				$tb_style = $action_btn_style;
				if ( ! empty( $tb['primary'] ) ) {
					$tb_style .= 'border-color:#93c5fd;color:#1d4ed8;background:#eff6ff;';
				}
				$extra = '';
				if ( $tb_id ) {
					$extra .= ' id="' . esc_attr( $tb_id ) . '"';
				}
				if ( ! empty( $tb['disabled'] ) ) {
					$extra .= ' disabled';
				}
				if ( ! empty( $tb['attrs'] ) && is_array( $tb['attrs'] ) ) {
					foreach ( $tb['attrs'] as $an => $av ) {
						$an = preg_replace( '/[^a-zA-Z0-9_\-:]/', '', (string) $an );
						if ( '' === $an ) {
							continue;
						}
						$extra .= ' ' . esc_attr( $an ) . '="' . esc_attr( is_scalar( $av ) ? (string) $av : '' ) . '"';
					}
				}
				echo '<button type="button" class="' . esc_attr( trim( $btn_classes . ' ' . $tb_class ) ) . '" style="' . esc_attr( $tb_style ) . '"' . $extra . '>' . esc_html( $tb_label ) . '</button>';
			}
			echo '</div>';
			echo '</div>';

			// 横向滚动壳：只负责滚动，不让外层被撑爆。
			echo '<div class="pili-table-wrap pili-table-scroll-shell rounded-md border border-gray-200 bg-white" style="display:block;width:100%;box-sizing:border-box;border:1px solid #e5e7eb;border-radius:0.375rem;background:#ffffff;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;">';
			echo '<table class="pili-table-grid text-left border-collapse whitespace-nowrap" style="margin:0;min-width:100%;width:max-content;text-align:left;border-collapse:collapse;white-space:nowrap;">';

			if ( ! empty( $columns ) ) {
				echo '<thead><tr>';
				if ( $selectable ) {
					echo '<th class="pili-table-select-col" style="width:56px;text-align:center;border:1px solid #e5e7eb;background:#f8fafc;padding:0.75rem 0.625rem;">';
					echo '<input type="checkbox" class="pili-table-select-all" aria-label="' . pili_esc_attr__( '全选当前页' ) . '" title="' . pili_esc_attr__( '全选当前页' ) . '" />';
					echo '</th>';
				}
				foreach ( $columns as $idx => $col ) {
					$title = isset( $col['title'] ) ? (string) $col['title'] : '';
					echo '<th class="pili-table-sort" data-col-index="' . esc_attr( (string) $idx ) . '" style="cursor:pointer;user-select:none;border:1px solid #e5e7eb;background:#f8fafc;color:#6b7280;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:0.75rem 0.875rem;">';
					echo '<span class="pili-table-sort-label" style="display:inline-flex;align-items:center;gap:6px;">';
					echo '<span>' . esc_html( $title ) . '</span>';
					echo '<span class="pili-table-sort-flag is-none" aria-hidden="true" title="' . pili_esc_attr__( '排序' ) . '">';
					echo '<svg class="pili-table-sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 15 12 20 17 15"></polyline><polyline points="7 9 12 4 17 9"></polyline></svg>';
					echo '</span>';
					echo '</span>';
					echo '</th>';
				}
				echo '</tr></thead>';
			}

			echo '<tbody>';
			echo $lazy_data ? $this->render_tbody_loading_row( $columns, $selectable ) : $this->render_tbody_rows( $rows, $columns, $selectable, $row_key, $empty );
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
			echo '<div class="pili-table-footer p-4 bg-white border-t border-gray-100 flex flex-wrap items-center justify-between gap-3" style="padding:1rem;background:#ffffff;border-top:1px solid #f3f4f6;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">';
			echo '<div class="pili-table-summary flex items-center gap-2 text-[12px] text-gray-500" style="display:flex;align-items:center;gap:.5rem;font-size:12px;color:#6b7280;">';
			echo '<span>' . pili_esc_html__( '每页显示' ) . '</span>';
			echo '<div class="pili-table-page-size-wrap pili-select-field" data-field-id="' . esc_attr( $this->field_id() . '_page_size' ) . '" data-multiple="false" data-searchable="false" data-clearable="false" data-close-on-select="true" style="min-width:72px;">';
			echo '<div class="relative">';
			echo '<button type="button" aria-expanded="false" aria-haspopup="listbox" class="pili-select-button relative grid w-full cursor-default grid-cols-1 rounded-md bg-white min-h-8 py-1.5 pr-2 pl-3 text-left text-gray-900" data-listbox="' . esc_attr( $this->field_id() . '_page_size_listbox' ) . '" style="min-height:28px;border:1px solid #e5e7eb;font-size:12px;">';
			echo '<span class="col-start-1 row-start-1 truncate pili-select-display pr-7">' . esc_html( (string) $page_size ) . '</span>';
			echo '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" class="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pili-select-arrow">';
			echo '<path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd" />';
			echo '</svg>';
			echo '</button>';
			echo '<div class="pili-select-dropdown absolute z-10 mt-1 w-full bg-white shadow-lg ring-1 ring-black/5 rounded-md hidden transition-all duration-200" style="max-height:180px;">';
			echo '<ul role="listbox" tabindex="-1" class="pili-select-options py-1 overflow-auto" style="max-height:180px;">';
			foreach ( $page_size_choices as $choice ) {
				$choice     = (int) $choice;
				$is_current = ( $choice === $page_size );
				$li_class   = 'pili-select-option relative cursor-pointer py-2 px-3 pr-9 select-none transition-colors duration-150 ' . ( $is_current ? 'bg-blue-600 text-white' : 'text-gray-900 hover:bg-gray-50' );
				$span_class = 'block truncate text-sm ' . ( $is_current ? 'font-semibold' : 'font-normal' );
				$check_hide = $is_current ? '' : 'hidden';
				echo '<li role="option" aria-selected="' . ( $is_current ? 'true' : 'false' ) . '" class="' . esc_attr( $li_class ) . '" data-value="' . esc_attr( (string) $choice ) . '" data-text="' . esc_attr( (string) $choice ) . '">';
				echo '<span class="' . esc_attr( $span_class ) . '">' . esc_html( (string) $choice ) . '</span>';
				echo '<span class="absolute inset-y-0 right-0 flex items-center pr-3 ' . esc_attr( $check_hide ) . '"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="w-4 h-4"><path d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" fill-rule="evenodd" /></svg></span>';
				echo '</li>';
			}
			echo '</ul>';
			echo '<div class="pili-select-no-results hidden p-3 text-center text-gray-500 text-sm">' . pili_esc_html__( '未找到匹配项' ) . '</div>';
			echo '<div class="pili-select-loading hidden p-3 text-center text-gray-500 text-sm">' . pili_esc_html__( '加载中...' ) . '</div>';
			echo '</div>';
			echo '</div>';
			echo '<select class="pili-table-page-size pili-select-native hidden">';
			foreach ( $page_size_choices as $choice ) {
				$choice = (int) $choice;
				echo '<option value="' . esc_attr( (string) $choice ) . '"' . selected( $page_size, $choice, false ) . '>' . esc_html( (string) $choice ) . '</option>';
			}
			echo '</select>';
			echo '</div>';
			echo '<span>' . wp_kses(
				sprintf(
					/* translators: %s: total count strong tag placeholder */
					pili__( '条数据，共 %s 条' ),
					'<strong class="pili-table-total-count">0</strong>'
				),
				array( 'strong' => array( 'class' => true ) )
			) . '</span>';
			echo '</div>';
			echo '<div class="pili-table-pagination flex items-center gap-1.5" style="display:flex;align-items:center;gap:0.375rem;">';
			echo '<button type="button" class="' . esc_attr( $pager_btn_classes ) . ' pili-table-prev" aria-label="' . pili_esc_attr__( '上一页' ) . '"><i class="ri-arrow-left-s-line" aria-hidden="true"></i></button>';
			echo '<div class="pili-table-pages flex items-center gap-1.5"></div>';
			echo '<button type="button" class="' . esc_attr( $pager_btn_classes ) . ' pili-table-next" aria-label="' . pili_esc_attr__( '下一页' ) . '"><i class="ri-arrow-right-s-line" aria-hidden="true"></i></button>';
			echo '<span class="pili-table-page text-xs text-gray-500 ml-2"></span>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo $this->field_after();

			echo '</div>'; // pili-field-safe-wrapper
		}

		/**
		 * 是否服务端分页（PHP 声明；框架自拉数，写出 data-server-fetch）。
		 *
		 * @return bool
		 */
		public function is_server_paged() {
			$data_callback = isset( $this->field['data_callback'] ) ? $this->field['data_callback'] : null;
			if ( ! is_string( $data_callback ) || '' === $data_callback || ! is_callable( $data_callback ) ) {
				return false;
			}
			return ! empty( $this->field['server_paged'] );
		}

		/**
		 * 是否延迟执行 data_callback（P3）。
		 *
		 * P2 分区 AJAX 渲染时由 filter `pili_field_lazy_data_default` 强制返回 false，同步输出 tbody。
		 * 单字段可用 `lazy_load => false` 关闭；`lazy_load => true` 强制 P3。
		 * `server_paged => true` 时强制懒壳（避免首屏全量渲染）。
		 *
		 * @return bool
		 */
		public function should_lazy_load_data() {
			$data_callback = isset( $this->field['data_callback'] ) ? $this->field['data_callback'] : null;
			if ( ! is_string( $data_callback ) || '' === $data_callback || ! is_callable( $data_callback ) ) {
				return false;
			}
			if ( $this->is_server_paged() ) {
				return true;
			}
			if ( isset( $this->field['lazy_load'] ) && false === $this->field['lazy_load'] ) {
				return false;
			}
			if ( ! empty( $this->field['lazy_load'] ) ) {
				return true;
			}
			return (bool) apply_filters( 'pili_field_lazy_data_default', false, 'table', $this->field, $this->unique );
		}

		/**
		 * 规范化分页查询参数。
		 *
		 * @param array<string,mixed>|null $query Raw.
		 * @return array{page:int,per_page:int,search:string}
		 */
		public static function normalize_query( $query = null ) {
			if ( ! is_array( $query ) ) {
				$query = array();
			}
			$page     = isset( $query['page'] ) ? max( 1, (int) $query['page'] ) : 1;
			$per_page = isset( $query['per_page'] ) ? (int) $query['per_page'] : 10;
			$per_page = max( 1, min( 100, $per_page > 0 ? $per_page : 10 ) );
			$search   = isset( $query['search'] ) ? sanitize_text_field( (string) $query['search'] ) : '';
			return array(
				'page'     => $page,
				'per_page' => $per_page,
				'search'   => $search,
			);
		}

		/**
		 * 从当前 AJAX 请求读取分页参数。
		 *
		 * @return array{page:int,per_page:int,search:string}
		 */
		public static function query_from_request() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- 调用方已验 nonce。
			$page = isset( $_POST['page'] ) ? (int) wp_unslash( $_POST['page'] ) : 1;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$per_page = isset( $_POST['per_page'] ) ? (int) wp_unslash( $_POST['per_page'] ) : 10;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['search'] ) ) : '';
			return self::normalize_query(
				array(
					'page'     => $page,
					'per_page' => $per_page,
					'search'   => $search,
				)
			);
		}

		/**
		 * 对扁平行数组做搜索过滤 + 分页。
		 *
		 * @param array<int,array<string,mixed>> $rows     Rows.
		 * @param array<string,mixed>|null       $query    Query.
		 * @param int                            $max_scan Hard cap.
		 * @return array{items:array<int,array<string,mixed>>,total:int}
		 */
		public static function paginate_rows( array $rows, $query = null, $max_scan = 2000 ) {
			$query    = self::normalize_query( $query );
			$max_scan = max( 50, (int) $max_scan );
			if ( count( $rows ) > $max_scan ) {
				$rows = array_slice( $rows, 0, $max_scan );
			}
			$search = $query['search'];
			if ( '' !== $search ) {
				$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );
				$rows   = array_values(
					array_filter(
						$rows,
						static function ( $row ) use ( $needle ) {
							if ( ! is_array( $row ) ) {
								return false;
							}
							$hay = wp_json_encode( $row, JSON_UNESCAPED_UNICODE );
							if ( ! is_string( $hay ) ) {
								$hay = implode( ' ', array_map( 'strval', $row ) );
							}
							$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
							return false !== strpos( $hay, $needle );
						}
					)
				);
			}
			$total  = count( $rows );
			$offset = ( $query['page'] - 1 ) * $query['per_page'];
			$items  = array_slice( $rows, $offset, $query['per_page'] );
			return array(
				'items' => $items,
				'total' => $total,
			);
		}

		/**
		 * 空状态文案拆成标题 + 补充说明（按首个句号/叹号/问号切分）。
		 *
		 * @param string $empty 原始 empty_text。
		 * @return array{title:string,hint:string}
		 */
		public static function parse_empty_copy( $empty ) {
			$empty = trim( (string) $empty );
			if ( '' === $empty ) {
				$empty = pili__( '暂无数据' );
			}
			if ( preg_match( '/^(.+?)[。．.!！？?](.+)$/u', $empty, $m ) ) {
				return array(
					'title' => trim( (string) $m[1] ),
					'hint'  => trim( (string) $m[2] ),
				);
			}
			return array(
				'title' => $empty,
				'hint'  => '',
			);
		}

		/**
		 * 表格空状态 HTML（图标 + 标题 + 可选说明）。
		 *
		 * @param string $empty empty_text。
		 * @return string
		 */
		public static function render_empty_state_html( $empty ) {
			$copy  = self::parse_empty_copy( $empty );
			$html  = '<div class="pili-table-empty" role="status">';
			$html .= '<div class="pili-table-empty__icon" aria-hidden="true">';
			$html .= '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
			$html .= '<path d="M3 7.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.5"></path>';
			$html .= '<path d="M3 7.5 5.4 4.2A2 2 0 0 1 7.1 3.5h9.8a2 2 0 0 1 1.7.7L21 7.5"></path>';
			$html .= '<path d="M9.5 12.5h5"></path>';
			$html .= '</svg>';
			$html .= '</div>';
			$html .= '<p class="pili-table-empty__title">' . esc_html( $copy['title'] ) . '</p>';
			if ( '' !== $copy['hint'] ) {
				$html .= '<p class="pili-table-empty__hint">' . esc_html( $copy['hint'] ) . '</p>';
			}
			$html .= '</div>';
			return $html;
		}

		/**
		 * 渲染 tbody 行 HTML（供首屏与 AJAX 复用）。
		 *
		 * @param array<int,array<string,mixed>> $rows       行数据。
		 * @param array<int,array<string,mixed>> $columns    列配置。
		 * @param bool                           $selectable 是否可选。
		 * @param string                         $row_key    行主键字段。
		 * @param string                         $empty      空态文案。
		 * @return string
		 */
		public function render_tbody_rows( $rows, $columns, $selectable, $row_key, $empty ) {
			ob_start();
			if ( empty( $rows ) ) {
				$colspan = max( 1, count( $columns ) + ( $selectable ? 1 : 0 ) );
				echo '<tr class="pili-table-empty-row"><td class="pili-table-empty-cell" colspan="' . esc_attr( (string) $colspan ) . '">' . self::render_empty_state_html( $empty ) . '</td></tr>';
			} else {
				foreach ( $rows as $index => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$row_key_value = isset( $row[ $row_key ] ) ? (string) $row[ $row_key ] : (string) $index;
					$row_attrs     = '';
					if ( ! empty( $row['_row_attrs'] ) && is_array( $row['_row_attrs'] ) ) {
						foreach ( $row['_row_attrs'] as $attr_key => $attr_val ) {
							$attr_key = preg_replace( '/[^a-zA-Z0-9_\-:]/', '', (string) $attr_key );
							if ( '' === $attr_key ) {
								continue;
							}
							$row_attrs .= ' ' . esc_attr( $attr_key ) . '="' . esc_attr( (string) $attr_val ) . '"';
						}
					}
					echo '<tr class="pili-table-row hover:bg-gray-50/80 transition-colors" data-row-index="' . esc_attr( (string) $index ) . '" data-row-key="' . esc_attr( $row_key_value ) . '"' . $row_attrs . ' style="transition:background-color .2s ease;">';
					if ( $selectable ) {
						echo '<td style="text-align:center;border:1px solid #e5e7eb;padding:0.65rem 0.625rem;font-size:13px;color:#374151;background:#ffffff;">';
						echo '<input type="checkbox" class="pili-table-row-radio" value="' . esc_attr( $row_key_value ) . '" aria-label="' . esc_attr( sprintf( /* translators: %d: 1-based row number */ pili__( '选择第 %d 行' ), (int) ( $index + 1 ) ) ) . '" />';
						echo '</td>';
					}
					foreach ( $columns as $col ) {
						echo '<td style="border:1px solid #e5e7eb;padding:0.65rem 0.875rem;font-size:13px;color:#374151;background:#ffffff;vertical-align:middle;">' . $this->render_cell_value( $col, $row ) . '</td>';
					}
					echo '</tr>';
				}
			}
			return (string) ob_get_clean();
		}

		/**
		 * 懒加载占位 tbody。
		 *
		 * @param array<int,array<string,mixed>> $columns    列配置。
		 * @param bool                           $selectable 是否可选。
		 * @return string
		 */
		public function render_tbody_loading_row( $columns, $selectable ) {
			$colspan = max( 1, count( $columns ) + ( $selectable ? 1 : 0 ) );
			return '<tr class="pili-table-lazy-loading"><td colspan="' . esc_attr( (string) $colspan ) . '" style="text-align:center;color:#64748b;border:1px solid #e5e7eb;padding:1.25rem;">' . pili_esc_html__( '表格数据加载中…' ) . '</td></tr>';
		}

		/**
		 * AJAX：拉取并渲染 tbody HTML（兼容旧调用，仅返回字符串）。
		 *
		 * @param array<string,mixed>      $field  字段配置。
		 * @param string                   $unique options id。
		 * @param array<string,mixed>|null $query  分页查询。
		 * @return string
		 */
		public static function ajax_render_tbody_html( $field, $unique, $query = null ) {
			$payload = self::ajax_render_tbody_payload( $field, $unique, $query );
			return isset( $payload['tbody'] ) ? (string) $payload['tbody'] : '';
		}

		/**
		 * AJAX：渲染 tbody + 分页元数据。
		 *
		 * @param array<string,mixed>      $field  字段配置。
		 * @param string                   $unique options id。
		 * @param array<string,mixed>|null $query  分页查询；null 且 server_paged 时从请求读取。
		 * @return array{tbody:string,total:int,page:int,per_page:int,server_paged:bool,field:string}
		 */
		public static function ajax_render_tbody_payload( $field, $unique, $query = null ) {
			$field_id = is_array( $field ) && ! empty( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
			$empty_payload = array(
				'tbody'        => '',
				'total'        => 0,
				'page'         => 1,
				'per_page'     => 10,
				'server_paged' => false,
				'field'        => $field_id,
			);
			if ( ! is_array( $field ) || empty( $field['type'] ) || 'table' !== $field['type'] ) {
				return $empty_payload;
			}
			$instance     = new self( $field, array(), $unique, 'options' );
			$server_paged = $instance->is_server_paged();
			if ( null === $query ) {
				$query = $server_paged
					? self::query_from_request()
					: self::normalize_query(
						array(
							'page'     => 1,
							'per_page' => isset( $field['page_size'] ) ? (int) $field['page_size'] : 10,
						)
					);
			} else {
				$query = self::normalize_query( $query );
			}
			$columns    = isset( $field['columns'] ) && is_array( $field['columns'] ) ? $field['columns'] : array();
			$empty      = isset( $field['empty_text'] ) ? (string) $field['empty_text'] : pili__( '暂无数据' );
			$selectable = isset( $field['selectable'] ) ? (bool) $field['selectable'] : true;
			$row_key    = isset( $field['row_key'] ) ? (string) $field['row_key'] : 'id';
			$pack       = $instance->get_rows( $server_paged ? $query : null );
			$items      = isset( $pack['items'] ) && is_array( $pack['items'] ) ? $pack['items'] : array();
			$total      = isset( $pack['total'] ) ? (int) $pack['total'] : count( $items );
			return array(
				'tbody'        => $instance->render_tbody_rows( $items, $columns, $selectable, $row_key, $empty ),
				'total'        => max( 0, $total ),
				'page'         => $query['page'],
				'per_page'     => $query['per_page'],
				'server_paged' => $server_paged,
				'field'        => $field_id,
			);
		}

		public static function cell_html_allowed_tags() {
			$allowed = wp_kses_allowed_html( 'post' );
			$allowed['button'] = array(
				'type'                        => true,
				'class'                       => true,
				'disabled'                    => true,
				'aria-busy'                   => true,
				'aria-label'                  => true,
				'title'                       => true,
				'data-action'                 => true,
				'data-event'                  => true,
				'data-id'                     => true,
				'data-tags'                   => true,
				'data-title'                  => true,
				'data-format'                 => true,
				'data-skill'                  => true,
				'data-code'                   => true,
				'data-user-id'                => true,
				'data-note'                   => true,
				'data-ip'                     => true,
				'data-security-action'        => true,
				'data-pili-table-action-key'   => true,
				'data-pili-table-field-id'     => true,
				'data-pili-table-action-label' => true,
				'data-pili-table-loading-label'=> true,
				'data-expand-mode'            => true,
				'data-expand-title'           => true,
				'data-expand-modal'           => true,
				'data-time'                   => true,
			);
			if ( ! isset( $allowed['div'] ) || ! is_array( $allowed['div'] ) ) {
				$allowed['div'] = array();
			}
			$allowed['div']['class']            = true;
			$allowed['div']['style']            = true;
			$allowed['div']['data-skill']       = true;
			$allowed['div']['data-settings']    = true;
			$allowed['div']['data-range-type']  = true;
			$allowed['div']['data-pili-boot']    = true;
			$allowed['div']['data-post-id']     = true;
			$allowed['div']['data-editor']      = true;
			$allowed['div']['data-editor-value']= true;
			$allowed['div']['data-editor-settings'] = true;
			$allowed['div']['data-schedule-at'] = true;
			$allowed['div']['data-editor-mounted'] = true;
			$allowed['div']['data-defer-picker'] = true;
			$allowed['div']['id']               = true;
			$allowed['div']['tabindex']         = true;
			if ( ! isset( $allowed['span'] ) || ! is_array( $allowed['span'] ) ) {
				$allowed['span'] = array();
			}
			$allowed['span']['class']  = true;
			$allowed['span']['style']  = true;
			$allowed['span']['title']  = true;
			$allowed['span']['hidden'] = true;
			$allowed['dl'] = array(
				'class' => true,
				'style' => true,
			);
			$allowed['dt'] = array(
				'class' => true,
				'style' => true,
			);
			$allowed['dd'] = array(
				'class' => true,
				'style' => true,
			);
			$allowed['ul'] = array(
				'class' => true,
				'style' => true,
			);
			$allowed['li'] = array(
				'class' => true,
				'style' => true,
			);
			$allowed['p'] = array(
				'class' => true,
				'style' => true,
			);
			$allowed['label'] = array(
				'class' => true,
				'for'   => true,
				'style' => true,
			);
			$allowed['code'] = array(
				'class' => true,
			);
			$allowed['i'] = array(
				'class'       => true,
				'aria-hidden' => true,
				'style'       => true,
			);
			$allowed['input'] = array(
				'type'        => true,
				'class'       => true,
				'checked'     => true,
				'value'       => true,
				'disabled'    => true,
				'readonly'    => true,
				'placeholder' => true,
				'min'         => true,
				'max'         => true,
				'step'        => true,
				'style'       => true,
				'data-skill'  => true,
				'data-event'  => true,
				'aria-label'  => true,
				'name'        => true,
				'id'          => true,
			);
			$allowed['select'] = array(
				'class'    => true,
				'disabled' => true,
				'name'     => true,
				'id'       => true,
				'style'    => true,
			);
			$allowed['option'] = array(
				'value'    => true,
				'selected' => true,
			);
			$allowed['svg'] = array(
				'class'           => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'stroke'          => true,
				'viewbox'         => true,
				'xmlns'           => true,
				'aria-hidden'     => true,
				'aria-label'      => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			);
			$allowed['path'] = array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'fill-rule'       => true,
				'clip-rule'       => true,
			);
			$allowed['rect'] = array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			);
			$allowed['line'] = array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
			);
			$allowed['polyline'] = array(
				'points'            => true,
				'fill'              => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
			);
			return $allowed;
		}

		/**
		 * 表级默认截断字数（纯文本列未写 truncate 时生效）。
		 * 字段可配 default_truncate：false/0=关闭默认；true 或正整数=默认字数。
		 *
		 * @return int
		 */
		private function resolve_default_truncate_length() {
			$fallback = 40;
			if ( ! is_array( $this->field ) || ! array_key_exists( 'default_truncate', $this->field ) ) {
				return $fallback;
			}
			$raw = $this->field['default_truncate'];
			if ( false === $raw || null === $raw || '' === $raw || 0 === $raw || '0' === $raw ) {
				return 0;
			}
			if ( true === $raw || '1' === $raw || 1 === $raw ) {
				return $fallback;
			}
			$len = (int) $raw;
			return $len > 0 ? max( 8, $len ) : 0;
		}

		/**
		 * 列截断长度（组件规范）：
		 * - 纯文本列：未写 truncate 时默认截断（default_truncate，默认 40）
		 * - allow_html 列：默认不截断（进度条/徽标等），需显式 truncate
		 * - truncate => false/0：强制不截断
		 * - truncate => true：默认 40（可用 truncate_length 覆盖）
		 * - truncate => 正整数：自定义字数
		 *
		 * @param array<string,mixed> $col 列配置。
		 * @return int
		 */
		private function resolve_truncate_length( $col ) {
			if ( ! is_array( $col ) ) {
				return 0;
			}

			if ( ! array_key_exists( 'truncate', $col ) ) {
				// HTML 列常含进度条/操作按钮，勿默认截断；纯文本列统一走组件长度规范。
				if ( ! empty( $col['allow_html'] ) ) {
					return 0;
				}
				return $this->resolve_default_truncate_length();
			}

			$raw = $col['truncate'];
			if ( false === $raw || null === $raw || '' === $raw || 0 === $raw || '0' === $raw ) {
				return 0;
			}
			if ( true === $raw || '1' === $raw || 1 === $raw ) {
				$len = isset( $col['truncate_length'] ) ? (int) $col['truncate_length'] : 40;
				return max( 8, $len );
			}
			$len = (int) $raw;
			if ( $len <= 0 && isset( $col['truncate_length'] ) ) {
				$len = (int) $col['truncate_length'];
			}
			return $len > 0 ? max( 8, $len ) : 0;
		}

		/**
		 * @param string $str 文本。
		 * @return int
		 */
		private static function unicode_strlen( $str ) {
			$str = (string) $str;
			if ( function_exists( 'mb_strlen' ) ) {
				return (int) mb_strlen( $str );
			}
			return strlen( $str );
		}

		/**
		 * @param string $str 文本。
		 * @param int    $len 字数。
		 * @return string
		 */
		private static function unicode_substr( $str, $len ) {
			$str = (string) $str;
			$len = max( 0, (int) $len );
			if ( function_exists( 'mb_substr' ) ) {
				return (string) mb_substr( $str, 0, $len );
			}
			return substr( $str, 0, $len );
		}

		/**
		 * 超长单元格：省略预览 + 点击弹窗看全文（复用 xunAlert）。
		 *
		 * @param string              $display_html 已消毒的展示 HTML（短内容直接返回）。
		 * @param string              $plain        用于计长的纯文本。
		 * @param string              $full_html    弹窗全文（HTML，已消毒）；纯文本列传空。
		 * @param string              $full_text    弹窗全文（纯文本）；HTML 列可传 strip 后的文本作兜底。
		 * @param int                 $max_len      截断字数。
		 * @param array<string,mixed> $col          列配置。
		 * @return string
		 */
		private function render_truncated_cell( $display_html, $plain, $full_html, $full_text, $max_len, $col ) {
			$plain = trim( preg_replace( '/\s+/u', ' ', (string) $plain ) );
			if ( '' === $plain || self::unicode_strlen( $plain ) <= $max_len ) {
				return $display_html;
			}

			$preview = self::unicode_substr( $plain, $max_len ) . '…';
			$col_title = isset( $col['title'] ) ? (string) $col['title'] : '';
			$mode      = '' !== (string) $full_html ? 'html' : 'text';
			$view_label = pili__( '查看' );

			$html  = '<span class="pili-table-cell-clip">';
			$html .= '<button type="button" class="pili-table-cell-expand"';
			$html .= ' data-expand-mode="' . esc_attr( $mode ) . '"';
			$html .= ' data-expand-title="' . esc_attr( $col_title ) . '"';
			$html .= ' title="' . pili_esc_attr__( '点击查看完整内容' ) . '"';
			$html .= ' aria-label="' . pili_esc_attr__( '查看完整内容' ) . '">';
			$html .= '<span class="pili-table-cell-preview">' . esc_html( $preview ) . '</span>';
			$html .= ' <span class="pili-table-cell-expand-hint">[' . esc_html( $view_label ) . ']</span>';
			$html .= '</button>';
			if ( 'html' === $mode ) {
				$html .= '<span class="pili-table-cell-full" hidden>' . $full_html . '</span>';
			} else {
				$html .= '<span class="pili-table-cell-full" hidden>' . esc_html( $full_text ) . '</span>';
			}
			$html .= '</span>';
			return $html;
		}

		private function render_cell_value( $col, $row ) {
			// 组件约定：editor 列由前端挂载控件，禁止在单元格 PHP 里嵌 PILI::field（懒加载分区会炸 JSON）。
			if ( ! empty( $col['editor'] ) && is_string( $col['editor'] ) ) {
				return $this->render_editor_mount_cell( $col, is_array( $row ) ? $row : array() );
			}

			$key = isset( $col['id'] ) ? (string) $col['id'] : '';
			$val = isset( $row[ $key ] ) ? $row[ $key ] : '';

			$render_callback = isset( $col['render'] ) ? $col['render'] : null;
			if ( is_string( $render_callback ) && '' !== $render_callback && is_callable( $render_callback ) ) {
				$val = call_user_func( $render_callback, $val, $row, $col, $this->field );
			}

			if ( is_array( $val ) || is_object( $val ) ) {
				$val = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
			}

			if ( ! is_string( $val ) ) {
				$val = (string) $val;
			}

			$allow_html = ! empty( $col['allow_html'] );
			$truncate   = $this->resolve_truncate_length( $col );

			if ( $allow_html ) {
				$safe = wp_kses( $val, self::cell_html_allowed_tags() );
				if ( $truncate <= 0 ) {
					return $safe;
				}
				$plain = trim( wp_strip_all_tags( $val ) );
				return $this->render_truncated_cell( $safe, $plain, $safe, $plain, $truncate, $col );
			}

			if ( $truncate <= 0 ) {
				return esc_html( $val );
			}

			$escaped = esc_html( $val );
			return $this->render_truncated_cell( $escaped, $val, '', $val, $truncate, $col );
		}

		/**
		 * 单元格编辑器挂载点（轻量 data-*；控件由 table.js + 对应字段 mount API 注入）。
		 *
		 * @param array<string,mixed> $col Column.
		 * @param array<string,mixed> $row Row.
		 * @return string
		 */
		private function render_editor_mount_cell( $col, $row ) {
			$type = sanitize_key( (string) $col['editor'] );
			if ( '' === $type ) {
				return '';
			}

			$value_key = isset( $col['editor_value_key'] ) ? (string) $col['editor_value_key'] : ( isset( $col['id'] ) ? (string) $col['id'] : '' );
			$value     = ( '' !== $value_key && isset( $row[ $value_key ] ) ) ? $row[ $value_key ] : '';
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				$value = '';
			}
			$value = (string) $value;

			$readonly = false;
			if ( isset( $col['editor_readonly'] ) && is_callable( $col['editor_readonly'] ) ) {
				$readonly = (bool) call_user_func( $col['editor_readonly'], $row, $col, $this->field );
			}
			if ( $readonly ) {
				return esc_html( '' !== $value ? $value : '—' );
			}

			$settings = ( isset( $col['editor_settings'] ) && is_array( $col['editor_settings'] ) )
				? $col['editor_settings']
				: array();
			$settings_json = wp_json_encode(
				$settings,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
			);
			if ( false === $settings_json ) {
				$settings_json = '{}';
			}

			$row_id = '';
			if ( isset( $row['id'] ) ) {
				$row_id = (string) $row['id'];
			} elseif ( isset( $this->field['row_key'] ) && isset( $row[ (string) $this->field['row_key'] ] ) ) {
				$row_id = (string) $row[ (string) $this->field['row_key'] ];
			}

			$extra_class = '';
			if ( isset( $col['editor_class'] ) ) {
				$extra_class = preg_replace( '/[^a-zA-Z0-9_\-\s]/', '', (string) $col['editor_class'] );
			}
			$class = trim( 'pili-table-cell-editor ' . $extra_class );

			$html  = '<div class="' . esc_attr( $class ) . '"';
			$html .= ' data-editor="' . esc_attr( $type ) . '"';
			$html .= ' data-editor-value="' . esc_attr( $value ) . '"';
			$html .= ' data-editor-settings="' . esc_attr( $settings_json ) . '"';
			if ( '' !== $row_id ) {
				$html .= ' data-post-id="' . esc_attr( $row_id ) . '"';
			}
			// 业务可选：定时列表自动保存对照。
			if ( 'date' === $type ) {
				$html .= ' data-schedule-at="' . esc_attr( $value ) . '"';
			}
			$html .= '></div>';
			return $html;
		}

		/**
		 * 解析行数据。
		 *
		 * 有 data_callback 时一律以回调为准（运维表不存 option）。
		 * 若优先读 $this->value，L2 保存会把「当前页/快照」写进域包，刷新后只剩一页。
		 *
		 * @param array<string,mixed>|null $query 服务端分页查询；null 表示非分页 / 全量。
		 * @return array{items:array<int,array<string,mixed>>,total:int}
		 */
		private function get_rows( $query = null ) {
			$server_paged = $this->is_server_paged();
			if ( $server_paged ) {
				$query = self::normalize_query(
					null !== $query
						? $query
						: array(
							'page'     => 1,
							'per_page' => isset( $this->field['page_size'] ) ? (int) $this->field['page_size'] : 10,
						)
				);
			}

			$data_callback = isset( $this->field['data_callback'] ) ? $this->field['data_callback'] : null;
			if ( is_string( $data_callback ) && '' !== $data_callback && is_callable( $data_callback ) ) {
				$callback_rows = call_user_func(
					$data_callback,
					$this->field,
					$this->value,
					$this->unique,
					$this->where,
					$this->parent,
					$server_paged ? $query : null
				);
				return $this->normalize_callback_result( $callback_rows, $server_paged ? $query : null );
			}

			$rows = is_array( $this->value ) ? $this->value : array();
			if ( ! empty( $rows ) ) {
				return $server_paged
					? self::paginate_rows( $rows, $query )
					: array(
						'items' => $rows,
						'total' => count( $rows ),
					);
			}

			$data_source = isset( $this->field['data_source'] ) ? (string) $this->field['data_source'] : '';
			if ( '' === $data_source || ! class_exists( PILI::class ) ) {
				return array(
					'items' => array(),
					'total' => 0,
				);
			}

			$source_rows = PILI::get_option( $this->unique, $data_source, array() );
			$source_rows = is_array( $source_rows ) ? $source_rows : array();
			return $server_paged
				? self::paginate_rows( $source_rows, $query )
				: array(
					'items' => $source_rows,
					'total' => count( $source_rows ),
				);
		}

		/**
		 * 统一 callback 返回值：支持 {rows,total} / {items,total} 或扁平数组。
		 *
		 * @param mixed                    $raw   Callback 原始返回。
		 * @param array<string,mixed>|null $query 分页查询；null 表示不分页。
		 * @return array{items:array<int,array<string,mixed>>,total:int}
		 */
		private function normalize_callback_result( $raw, $query = null ) {
			if ( is_array( $raw ) ) {
				$pack_rows = null;
				if ( isset( $raw['rows'] ) && is_array( $raw['rows'] ) ) {
					$pack_rows = $raw['rows'];
				} elseif ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
					$pack_rows = $raw['items'];
				}
				if ( null !== $pack_rows ) {
					$total = isset( $raw['total'] ) ? (int) $raw['total'] : count( $pack_rows );
					return array(
						'items' => array_values( $pack_rows ),
						'total' => max( 0, $total ),
					);
				}
			}
			$list = is_array( $raw ) ? array_values( $raw ) : array();
			$list = array_values(
				array_filter(
					$list,
					static function ( $row ) {
						return is_array( $row );
					}
				)
			);
			if ( null !== $query ) {
				return self::paginate_rows( $list, $query );
			}
			return array(
				'items' => $list,
				'total' => count( $list ),
			);
		}

		/**
		 * data_callback 表不落库；清空历史误存的行快照。
		 *
		 * @param mixed $value Incoming.
		 * @return array
		 */
		public function sanitize( $value ) {
			unset( $value );
			if ( ! empty( $this->field['data_callback'] ) ) {
				return array();
			}
			return is_array( $this->value ) ? $this->value : array();
		}

		public function validate( $value ) {
			if ( ! empty( $this->field['data_callback'] ) ) {
				return array();
			}
			return is_array( $value ) ? $value : ( is_array( $this->value ) ? $this->value : array() );
		}

		/**
		 * 列是否声明了指定 editor 类型。
		 *
		 * @param string $editor_type Editor type.
		 * @return bool
		 */
		private function columns_need_editor( $editor_type ) {
			$editor_type = sanitize_key( (string) $editor_type );
			if ( '' === $editor_type || empty( $this->field['columns'] ) || ! is_array( $this->field['columns'] ) ) {
				return false;
			}
			foreach ( $this->field['columns'] as $col ) {
				if ( ! is_array( $col ) || empty( $col['editor'] ) ) {
					continue;
				}
				if ( sanitize_key( (string) $col['editor'] ) === $editor_type ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * 静态资源版本（filemtime），避免浏览器/代理继续命中旧 table.js。
		 *
		 * @return string
		 */
		public static function asset_build() {
			$path = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/js/fields/table.js';
			if ( is_readable( $path ) ) {
				return (string) filemtime( $path );
			}
			return defined( 'PILI_CORE_VERSION' ) ? (string) PILI_CORE_VERSION : '1';
		}

		public function enqueue() {
			$build         = self::asset_build();
			$select_handle = pili_asset_handle( 'field-select' );
			$date_handle   = pili_asset_handle( 'field-date' );
			$modal_handle  = pili_asset_handle( 'field-modal' );
			$table_handle  = pili_asset_handle( 'field-table' );
			$cell_editor   = pili_asset_handle( 'table-cell-editor' );

			// 复用 select 字段交互，避免 WP 原生 select 样式污染。
			wp_deregister_script( $select_handle );
			wp_enqueue_script(
				$select_handle,
				PILI_Setup::$url . '/assets/js/fields/select.js',
				array( 'jquery' ),
				$build,
				true
			);

			$deps = array( 'jquery', $select_handle );
			if ( class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
				Remix_Icons::enqueue_style( 'pilidoc-remixicon' );
			}
			if ( function_exists( 'pili_enqueue_js_i18n_runtime' ) ) {
				$i18n_rt = pili_enqueue_js_i18n_runtime();
				if ( is_string( $i18n_rt ) && '' !== $i18n_rt ) {
					$deps[] = $i18n_rt;
				}
			}

			// 列声明 editor=date 时拉起 date 资源（单元格只挂载点，不在 PHP 嵌字段）。
			if ( $this->columns_need_editor( 'date' ) && class_exists( __NAMESPACE__ . '\PILI_Field_date' ) ) {
				$date_field = new PILI_Field_date(
					array(
						'id'   => '_xun_table_date_editor',
						'type' => 'date',
					),
					'',
					'',
					'',
					''
				);
				if ( method_exists( $date_field, 'enqueue' ) ) {
					$date_field->enqueue();
				}
				$deps[] = $date_handle;
			}

			// editor=select：复用 select 字段资源 + xunSelectFieldMount。
			if ( $this->columns_need_editor( 'select' ) && class_exists( __NAMESPACE__ . '\PILI_Field_select' ) ) {
				$select_field = new PILI_Field_select(
					array(
						'id'   => '_xun_table_select_editor',
						'type' => 'select',
					),
					'',
					'',
					'',
					''
				);
				if ( method_exists( $select_field, 'enqueue' ) ) {
					$select_field->enqueue();
				}
				$deps[] = $select_handle;
			}

			// editor=text：table.js 内 xunTextFieldMount，无需独立脚本。
			if ( $this->columns_need_editor( 'select' ) || $this->columns_need_editor( 'text' ) || $this->columns_need_editor( 'date' ) ) {
				$editor_css = '.pili-table-grid td:has(.pili-table-cell-editor){overflow:visible;position:relative;}'
					. '.pili-table-grid .pili-table-cell-editor[data-editor="select"]{min-width:11rem;max-width:16rem;}'
					. '.pili-table-grid .pili-table-cell-editor[data-editor="text"]{min-width:10rem;max-width:14rem;}';
				wp_register_style( $cell_editor, false, array(), $build );
				wp_enqueue_style( $cell_editor );
				wp_add_inline_style( $cell_editor, $editor_css );
			}

			$expand_modal = isset( $this->field['expand_modal'] ) ? sanitize_key( (string) $this->field['expand_modal'] ) : '';
			if ( '' !== $expand_modal ) {
				wp_enqueue_style(
					$modal_handle,
					PILI_Setup::$url . '/assets/css/fields/modal.css',
					array(),
					PILI_CORE_VERSION
				);
				wp_enqueue_script(
					$modal_handle,
					PILI_Setup::$url . '/assets/js/fields/modal.js',
					array( 'jquery' ),
					PILI_CORE_VERSION,
					true
				);
				$deps[] = $modal_handle;
			}

			wp_deregister_script( $table_handle );
			wp_enqueue_script(
				$table_handle,
				PILI_Setup::$url . '/assets/js/fields/table.js',
				$deps,
				$build,
				true
			);

			// 独立 table.css：懒加载只注入 link.href，挂 framework 的 inline 进不了前台。
			$table_css_handle = pili_asset_handle( 'field-table-css' );
			$table_css_path   = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/css/fields/table.css';
			$table_css_ver    = is_readable( $table_css_path ) ? (string) filemtime( $table_css_path ) : $build;
			wp_enqueue_style(
				$table_css_handle,
				untrailingslashit( (string) PILI_Setup::$url ) . '/assets/css/fields/table.css',
				array(),
				$table_css_ver
			);

			pili_localize_bag( $table_handle, 'table',
				array(
					'build'         => $build,
					'ns'            => 'pilipost',
					'actionLoading' => pili__( '处理中…' ),
					'i18n'          => array(
						'deleteSelected' => pili__( '删除所选' ),
						'clearAll' => pili__( '清空全部' ),
						'exportCsv' => pili__( '导出 CSV' ),
						'deleting' => pili__( '删除中…' ),
						'clearing' => pili__( '清理中…' ),
						'confirmDelete' => pili__( '确定要删除此项吗？' ),
						'confirmClear' => pili__( '确认清空' ),
						'deleteConfirm' => pili__( '确定要删除已勾选的 %d 项吗？此操作不可撤销。' ),
						'clearConfirm' => pili__( '确定要清空当前表格中的所有数据吗？此操作不可撤销。' ),
						'delete' => pili__( '删除' ),
						'clear' => pili__( '清空' ),
						'cancel' => pili__( '取消' ),
						'ok' => pili__( '确定' ),
						'confirm' => pili__( '确认' ),
						'success' => pili__( '成功' ),
						'notice' => pili__( '提示' ),
						'empty' => pili__( '暂无数据' ),
						'pageLabel' => pili__( '第 %1$d / %2$d 页' ),
						'sort' => pili__( '排序' ),
						'sortAsc' => pili__( '升序' ),
						'sortDesc' => pili__( '降序' ),
						'processing' => pili__( '处理中…' ),
						'noClearData' => pili__( '当前没有可清空的数据。' ),
						'clearFail' => pili__( '清空失败' ),
						'cleared' => pili__( '数据已清空。' ),
						'clearedLocal' => pili__( '数据已清空（仅前端，未配置 clear_action 时不会写入数据库）。' ),
						'deleteFail' => pili__( '删除失败' ),
						'deleteOk' => pili__( '删除成功。' ),
						'okQuestion' => pili__( '确定？' ),
						'selectionConfirm' => pili__( '确定对已勾选的 %d 项执行「%s」？' ),
						'missingAjax' => pili__( '缺少 ajaxurl' ),
						'deletedLocal' => pili__( '已从前端列表移除（未配置 delete_action 时不会写入数据库）。' ),
						'actionFail' => pili__( '操作失败' ),
						'actionDone' => pili__( '已完成。' ),
						'refreshFail' => pili__( '表格刷新失败' ),
						'rangeTitle' => pili__( '当前显示区间：%1$d-%2$d' ),
						'viewFull' => pili__( '完整内容' ),
						'viewFullEmpty' => pili__( '（无内容）' ),
					),
				)
			);
		}

		/**
		 * 操作列按钮 — 预设 variant 的 Tailwind 类名。
		 *
		 * @param string $variant default|primary|danger|warning|success|solid。
		 * @return string
		 */
		public static function action_button_variant_classes( $variant = 'default' ) {
			$base = 'pili-table-btn pili-table-action-btn inline-flex items-center px-2.5 py-1.5 border shadow-sm text-xs font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2';
			$map  = array(
				'default' => 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:ring-blue-500',
				'primary' => 'border-blue-300 text-blue-800 bg-blue-50 hover:bg-blue-100 focus:ring-blue-500',
				'danger'  => 'border-red-300 text-red-800 bg-red-50 hover:bg-red-100 focus:ring-red-500',
				'warning' => 'border-amber-300 text-amber-800 bg-amber-50 hover:bg-amber-100 focus:ring-amber-500',
				'success' => 'border-emerald-300 text-emerald-900 bg-emerald-50 hover:bg-emerald-100 focus:ring-emerald-500',
				'solid'   => 'border-transparent text-white bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
			);
			$key = isset( $map[ $variant ] ) ? $variant : 'default';
			return trim( $base . ' ' . $map[ $key ] );
		}

		/**
		 * 操作列按钮容器。
		 *
		 * @param string $inner_html 按钮 HTML。
		 * @return string
		 */
		public static function render_action_buttons_wrap( $inner_html ) {
			return '<div class="pili-table-action-group" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">'
				. $inner_html
				. '</div>';
		}

		/**
		 * 渲染单个操作列按钮（配合 table.js `XunTableField.runAction` 加载态）。
		 *
		 * @param array<string,mixed> $args {
		 *     @type string $label          按钮文案。
		 *     @type string $action_key    行内唯一键，写入 data-pili-table-action-key。
		 *     @type string $field_id      可选，所属 table 字段 id。
		 *     @type string $loading_label 加载中文案，默认「处理中…」。
		 *     @type string $variant       见 action_button_variant_classes。
		 *     @type string $class         附加 class。
		 *     @type array<string,string> $attrs 额外 data-* / aria-* 属性。
		 * }
		 * @return string
		 */
		public static function render_action_button( $args ) {
			$args = wp_parse_args(
				is_array( $args ) ? $args : array(),
				array(
					'label'         => '',
					'action_key'    => '',
					'field_id'      => '',
					'loading_label' => pili__( '处理中…' ),
					'variant'       => 'default',
					'class'         => '',
					'attrs'         => array(),
				)
			);

			$label = (string) $args['label'];
			if ( '' === $label ) {
				return '';
			}

			$action_key = sanitize_key( (string) $args['action_key'] );
			$field_id   = sanitize_key( (string) $args['field_id'] );
			$classes    = trim( self::action_button_variant_classes( (string) $args['variant'] ) . ' ' . (string) $args['class'] );

			$attr_html = '';
			if ( '' !== $action_key ) {
				$attr_html .= ' data-pili-table-action-key="' . esc_attr( $action_key ) . '"';
			}
			if ( '' !== $field_id ) {
				$attr_html .= ' data-pili-table-field-id="' . esc_attr( $field_id ) . '"';
			}
			$attr_html .= ' data-pili-table-action-label="' . esc_attr( $label ) . '"';
			$attr_html .= ' data-pili-table-loading-label="' . esc_attr( (string) $args['loading_label'] ) . '"';

			if ( is_array( $args['attrs'] ) ) {
				foreach ( $args['attrs'] as $name => $value ) {
					$name = preg_replace( '/[^a-zA-Z0-9_\-:]/', '', (string) $name );
					if ( '' === $name ) {
						continue;
					}
					$attr_html .= ' ' . esc_attr( $name ) . '="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '"';
				}
			}

			return '<button type="button" class="' . esc_attr( $classes ) . '"' . $attr_html . '>'
				. esc_html( $label )
				. '</button>';
		}
	}
