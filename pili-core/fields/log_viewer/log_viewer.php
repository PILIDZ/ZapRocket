<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field: log_viewer
 *
 * 运行日志查看器（只读展示，不参与存储）。
 * 视觉对齐 table / record_viewer：白底卡片 + 工具栏 + 等宽日志流。
 */
class PILI_Field_log_viewer extends PILI_Fields {

	/**
	 * 默认级别配置。
	 *
	 * @return array<string, array{label:string,tone:string}>
	 */
	public static function default_levels() {
		return array(
			'debug'   => array(
				'label' => pili__( '调试' ),
				'tone'  => 'gray',
			),
			'info'    => array(
				'label' => pili__( '信息' ),
				'tone'  => 'blue',
			),
			'success' => array(
				'label' => pili__( '成功' ),
				'tone'  => 'green',
			),
			'warning' => array(
				'label' => pili__( '警告' ),
				'tone'  => 'amber',
			),
			'error'   => array(
				'label' => pili__( '错误' ),
				'tone'  => 'red',
			),
		);
	}

	public function render() {
		$args = wp_parse_args(
			$this->field,
			array(
				'empty_text'         => pili__( '暂无运行日志。' ),
				'loading_text'       => pili__( '加载中…' ),
				'height'             => 460,
				'page_size'          => 50,
				'row_key'            => 'id',
				'theme'              => 'console',
				'toolbar_search'     => true,
				'toolbar_level'      => true,
				'toolbar_channel'    => true,
				'toolbar_refresh'    => true,
				'toolbar_copy'       => true,
				'toolbar_export'     => true,
				'toolbar_clear'      => true,
				'toolbar_autoscroll' => true,
				'levels'             => array(),
				'channels'           => array(),
				'refresh_action'     => '',
				'clear_action'       => '',
				'export_action'      => '',
				'ajax_nonce'         => '',
				'ajax_url'           => '',
			)
		);

		$levels   = $this->normalize_levels( $args['levels'] );
		$entries  = $this->get_entries();
		$channels = $this->resolve_channels( $args['channels'], $entries );

		$height    = max( 240, (int) $args['height'] );
		$page_size = max( 10, (int) $args['page_size'] );
		$theme_raw = sanitize_key( (string) $args['theme'] );
		// console = 白卡片外壳 + 深色日志区；light = 全亮；terminal/dark 兼容旧配置。
		if ( in_array( $theme_raw, array( 'terminal', 'dark' ), true ) ) {
			$theme = 'console';
		} elseif ( 'light' === $theme_raw ) {
			$theme = 'light';
		} else {
			$theme = 'console';
		}

		$entries_json  = wp_json_encode( array_values( $entries ), JSON_UNESCAPED_UNICODE );
		$levels_json   = wp_json_encode( $levels, JSON_UNESCAPED_UNICODE );
		$channels_json = wp_json_encode( $channels, JSON_UNESCAPED_UNICODE );
		$btn_classes   = 'pili-log-viewer-btn';
		$ajax_url      = (string) $args['ajax_url'];
		if ( '' === $ajax_url ) {
			$ajax_url = admin_url( 'admin-ajax.php' );
		}

		$level_options = array();
		foreach ( $levels as $level_key => $level_cfg ) {
			$level_options[ $level_key ] = $level_cfg['label'];
		}

		echo $this->field_before();

		echo '<div class="pili-field-safe-wrapper" style="display:grid;grid-template-columns:minmax(0,1fr);width:100%;box-sizing:border-box;">';

		echo '<div class="pili-log-viewer-field pili-log-viewer-field--' . esc_attr( $theme ) . '"'
			. ' data-field-id="' . esc_attr( $this->field['id'] ) . '"'
			. ' data-row-key="' . esc_attr( (string) $args['row_key'] ) . '"'
			. ' data-page-size="' . esc_attr( (string) $page_size ) . '"'
			. ' data-theme="' . esc_attr( $theme ) . '"'
			. ' data-empty-text="' . esc_attr( (string) $args['empty_text'] ) . '"'
			. ' data-loading-text="' . esc_attr( (string) $args['loading_text'] ) . '"'
			. ' data-entries="' . esc_attr( $entries_json ? $entries_json : '[]' ) . '"'
			. ' data-levels="' . esc_attr( $levels_json ? $levels_json : '{}' ) . '"'
			. ' data-channels="' . esc_attr( $channels_json ? $channels_json : '{}' ) . '"'
			. ' data-refresh-action="' . esc_attr( (string) $args['refresh_action'] ) . '"'
			. ' data-clear-action="' . esc_attr( (string) $args['clear_action'] ) . '"'
			. ' data-export-action="' . esc_attr( (string) $args['export_action'] ) . '"'
			. ' data-ajax-nonce="' . esc_attr( (string) $args['ajax_nonce'] ) . '"'
			. ' data-ajax-url="' . esc_url( $ajax_url ) . '"'
			. ' data-i18n-level-all="' . pili_esc_attr__( '全部级别' ) . '"'
			. ' data-i18n-channel-all="' . pili_esc_attr__( '全部通道' ) . '"'
			. ' data-i18n-selected="' . pili_esc_attr__( '已选 %d 项' ) . '">';

		echo '<div class="pili-log-viewer-toolbar">';
		echo '<div class="pili-log-viewer-filters">';

		if ( $args['toolbar_level'] ) {
			$this->render_multiselect(
				'level',
				pili__( '级别' ),
				pili__( '全部级别' ),
				$level_options
			);
		}

		if ( $args['toolbar_channel'] ) {
			$this->render_multiselect(
				'channel',
				pili__( '通道' ),
				pili__( '全部通道' ),
				$channels
			);
		}

		if ( $args['toolbar_search'] ) {
			echo '<div class="pili-log-viewer-search-wrap">';
			echo '<span class="pili-log-viewer-search-icon" aria-hidden="true"><i class="ri-search-line"></i></span>';
			echo '<input type="text" class="pili-log-viewer-search" placeholder="' . pili_esc_attr__( '搜索日志内容…' ) . '" autocomplete="off" spellcheck="false" />';
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="pili-log-viewer-actions">';
		if ( $args['toolbar_autoscroll'] ) {
			echo '<button type="button" class="pili-log-viewer-switch is-on" data-role="autoscroll" role="switch" aria-checked="true">';
			echo '<span class="pili-log-viewer-switch-track" aria-hidden="true"><span class="pili-log-viewer-switch-thumb"></span></span>';
			echo '<span class="pili-log-viewer-switch-label">' . pili_esc_html__( '自动滚动' ) . '</span>';
			echo '</button>';
		}
		if ( $args['toolbar_refresh'] ) {
			echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-log-viewer-refresh">';
			echo '<i class="ri-refresh-line" aria-hidden="true"></i><span>' . pili_esc_html__( '刷新' ) . '</span>';
			echo '</button>';
		}
		if ( $args['toolbar_copy'] ) {
			echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-log-viewer-copy">';
			echo '<i class="ri-file-copy-line" aria-hidden="true"></i><span>' . pili_esc_html__( '复制' ) . '</span>';
			echo '</button>';
		}
		if ( $args['toolbar_export'] ) {
			echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-log-viewer-export" title="' . pili_esc_attr__( '按当前筛选与时间段导出为 .log 文件' ) . '">';
			echo '<i class="ri-download-2-line" aria-hidden="true"></i><span>' . pili_esc_html__( '导出' ) . '</span>';
			echo '</button>';
		}
		if ( $args['toolbar_clear'] ) {
			echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-log-viewer-btn--danger pili-log-viewer-clear">';
			echo '<i class="ri-delete-bin-line" aria-hidden="true"></i><span>' . pili_esc_html__( '清空' ) . '</span>';
			echo '</button>';
		}
		echo '</div>';
		echo '</div>';

		if ( $args['toolbar_export'] ) {
			echo '<div class="pili-log-viewer-export-panel" hidden>';
			echo '<div class="pili-log-viewer-export-panel-title">' . pili_esc_html__( '导出时间段' ) . '</div>';
			echo '<div class="pili-log-viewer-export-fields">';
			echo '<label class="pili-log-viewer-export-field"><span>' . pili_esc_html__( '开始' ) . '</span>';
			echo '<input type="datetime-local" class="pili-log-viewer-export-from" step="60" /></label>';
			echo '<label class="pili-log-viewer-export-field"><span>' . pili_esc_html__( '结束' ) . '</span>';
			echo '<input type="datetime-local" class="pili-log-viewer-export-to" step="60" /></label>';
			echo '</div>';
			echo '<div class="pili-log-viewer-export-presets" role="group" aria-label="' . pili_esc_attr__( '快捷时间段' ) . '">';
			echo '<button type="button" class="pili-log-viewer-export-preset" data-range="1h">' . pili_esc_html__( '近1小时' ) . '</button>';
			echo '<button type="button" class="pili-log-viewer-export-preset" data-range="24h">' . pili_esc_html__( '近24小时' ) . '</button>';
			echo '<button type="button" class="pili-log-viewer-export-preset" data-range="7d">' . pili_esc_html__( '近7天' ) . '</button>';
			echo '<button type="button" class="pili-log-viewer-export-preset" data-range="all">' . pili_esc_html__( '全部时间' ) . '</button>';
			echo '</div>';
			echo '<p class="pili-log-viewer-export-hint">' . pili_esc_html__( '留空表示不限制；仍会套用上方级别 / 通道 / 搜索筛选。' ) . '</p>';
			echo '<div class="pili-log-viewer-export-actions">';
			echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-log-viewer-export-go">';
			echo '<i class="ri-download-2-line" aria-hidden="true"></i><span>' . pili_esc_html__( '开始导出' ) . '</span>';
			echo '</button>';
			echo '<button type="button" class="' . esc_attr( $btn_classes ) . ' pili-log-viewer-export-cancel">';
			echo '<span>' . pili_esc_html__( '取消' ) . '</span>';
			echo '</button>';
			echo '</div>';
			echo '</div>';
		}

		echo '<div class="pili-log-viewer-body" style="height:' . esc_attr( (string) $height ) . 'px;">';
		echo '<div class="pili-log-viewer-stream" role="log" aria-live="polite" aria-relevant="additions">';
		echo '<div class="pili-log-viewer-placeholder">' . esc_html( (string) $args['loading_text'] ) . '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="pili-log-viewer-footer">';
		echo '<div class="pili-log-viewer-summary">';
		echo '<span>' . pili_esc_html__( '显示' ) . ' <strong class="pili-log-viewer-visible-count">0</strong> / <strong class="pili-log-viewer-total-count">0</strong> ' . pili_esc_html__( '条' ) . '</span>';
		echo '<span class="pili-log-viewer-page-sep">·</span>';
		echo '<span class="pili-log-viewer-page-label"></span>';
		echo '</div>';
		echo '<div class="pili-log-viewer-pagination">';
		echo '<button type="button" class="pili-log-viewer-pager-btn pili-log-viewer-prev" aria-label="' . pili_esc_attr__( '上一页' ) . '">‹</button>';
		echo '<button type="button" class="pili-log-viewer-pager-btn pili-log-viewer-next" aria-label="' . pili_esc_attr__( '下一页' ) . '">›</button>';
		echo '</div>';
		echo '</div>';

		echo '</div>'; // pili-log-viewer-field
		echo '</div>'; // pili-field-safe-wrapper

		echo $this->field_after();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_entries() {
		$entries = is_array( $this->value ) ? $this->value : array();
		if ( ! empty( $entries ) ) {
			return $this->normalize_entries( $entries );
		}

		$data_callback = isset( $this->field['data_callback'] ) ? $this->field['data_callback'] : null;
		if ( is_string( $data_callback ) && '' !== $data_callback && is_callable( $data_callback ) ) {
			$callback_rows = call_user_func( $data_callback, $this->field, $this->value, $this->unique, $this->where, $this->parent );
			return $this->normalize_entries( is_array( $callback_rows ) ? $callback_rows : array() );
		}

		$data_source = isset( $this->field['data_source'] ) ? (string) $this->field['data_source'] : '';
		if ( '' === $data_source || ! class_exists( PILI_Setup::class ) ) {
			return array();
		}

		$source_rows = PILI_Setup::get_option( $this->unique, $data_source, array() );
		return $this->normalize_entries( is_array( $source_rows ) ? $source_rows : array() );
	}

	/**
	 * @param mixed $levels Raw levels.
	 * @return array<string, array{label:string,tone:string}>
	 */
	private function normalize_levels( $levels ) {
		$defaults = self::default_levels();
		if ( ! is_array( $levels ) || empty( $levels ) ) {
			return $defaults;
		}

		$out = array();
		foreach ( $levels as $key => $item ) {
			if ( is_int( $key ) && is_string( $item ) ) {
				$level_key = sanitize_key( $item );
				if ( '' === $level_key ) {
					continue;
				}
				$out[ $level_key ] = isset( $defaults[ $level_key ] )
					? $defaults[ $level_key ]
					: array(
						'label' => $level_key,
						'tone'  => 'gray',
					);
				continue;
			}

			$level_key = sanitize_key( (string) $key );
			if ( '' === $level_key ) {
				continue;
			}
			if ( is_string( $item ) ) {
				$out[ $level_key ] = array(
					'label' => $item,
					'tone'  => isset( $defaults[ $level_key ]['tone'] ) ? $defaults[ $level_key ]['tone'] : 'gray',
				);
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[ $level_key ] = array(
				'label' => isset( $item['label'] ) ? (string) $item['label'] : ( isset( $defaults[ $level_key ]['label'] ) ? $defaults[ $level_key ]['label'] : $level_key ),
				'tone'  => isset( $item['tone'] ) ? sanitize_key( (string) $item['tone'] ) : ( isset( $defaults[ $level_key ]['tone'] ) ? $defaults[ $level_key ]['tone'] : 'gray' ),
			);
		}

		return ! empty( $out ) ? $out : $defaults;
	}

	/**
	 * @param mixed                          $channels Configured channels.
	 * @param array<int, array<string,mixed>> $entries  Entries.
	 * @return array<string, string>
	 */
	private function resolve_channels( $channels, $entries ) {
		$out = array();
		if ( is_array( $channels ) ) {
			foreach ( $channels as $key => $label ) {
				if ( is_int( $key ) && is_string( $label ) ) {
					$channel_key = sanitize_key( $label );
					if ( '' !== $channel_key ) {
						$out[ $channel_key ] = $label;
					}
					continue;
				}
				$channel_key = sanitize_key( (string) $key );
				if ( '' === $channel_key ) {
					continue;
				}
				$out[ $channel_key ] = is_string( $label ) ? $label : $channel_key;
			}
		}

		if ( empty( $out ) ) {
			foreach ( $entries as $entry ) {
				$channel = isset( $entry['channel'] ) ? sanitize_key( (string) $entry['channel'] ) : '';
				if ( '' === $channel || isset( $out[ $channel ] ) ) {
					continue;
				}
				$out[ $channel ] = isset( $entry['channel_label'] ) && '' !== (string) $entry['channel_label']
					? (string) $entry['channel_label']
					: $channel;
			}
		}

		return $out;
	}

	/**
	 * @param array<int, mixed> $entries Raw entries.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_entries( $entries ) {
		$out = array();
		$i   = 0;
		foreach ( $entries as $entry ) {
			if ( is_string( $entry ) ) {
				$out[] = array(
					'id'      => (string) ( $i + 1 ),
					'time'    => '',
					'level'   => 'info',
					'channel' => 'system',
					'message' => $entry,
					'context' => '',
				);
				++$i;
				continue;
			}
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id = isset( $entry['id'] ) ? (string) $entry['id'] : (string) ( $i + 1 );
			$out[] = array(
				'id'            => $id,
				'time'          => isset( $entry['time'] ) ? (string) $entry['time'] : ( isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '' ),
				'level'         => isset( $entry['level'] ) ? sanitize_key( (string) $entry['level'] ) : 'info',
				'channel'       => isset( $entry['channel'] ) ? sanitize_key( (string) $entry['channel'] ) : 'system',
				'channel_label' => isset( $entry['channel_label'] ) ? (string) $entry['channel_label'] : '',
				'message'       => isset( $entry['message'] ) ? (string) $entry['message'] : ( isset( $entry['msg'] ) ? (string) $entry['msg'] : '' ),
				'context'       => isset( $entry['context'] ) ? (string) $entry['context'] : ( isset( $entry['detail'] ) ? (string) $entry['detail'] : '' ),
			);
			++$i;
		}
		return $out;
	}

	/**
	 * 自绘多选下拉（标签触发器 + 勾选面板，不依赖原生 select）。
	 *
	 * @param string               $key         level|channel.
	 * @param string               $title       字段名.
	 * @param string               $placeholder 未选时文案.
	 * @param array<string,string> $options     value => label.
	 */
	private function render_multiselect( $key, $title, $placeholder, $options ) {
		$key = sanitize_key( (string) $key );
		echo '<div class="pili-log-dd" data-ms="' . esc_attr( $key ) . '" data-placeholder="' . esc_attr( $placeholder ) . '">';
		echo '<div class="pili-log-dd-label">' . esc_html( $title ) . '</div>';
		echo '<button type="button" class="pili-log-dd-trigger" aria-haspopup="listbox" aria-expanded="false">';
		echo '<span class="pili-log-dd-tags">';
		echo '<span class="pili-log-dd-placeholder">' . esc_html( $placeholder ) . '</span>';
		echo '</span>';
		echo '<i class="ri-arrow-down-s-line pili-log-dd-caret" aria-hidden="true"></i>';
		echo '</button>';
		echo '<div class="pili-log-dd-panel" hidden>';
		echo '<div class="pili-log-dd-panel-head">';
		echo '<div class="pili-log-dd-filter-wrap">';
		echo '<i class="ri-search-line" aria-hidden="true"></i>';
		echo '<input type="text" class="pili-log-dd-filter" placeholder="' . pili_esc_attr__( '筛选选项…' ) . '" autocomplete="off" spellcheck="false" />';
		echo '</div>';
		echo '<div class="pili-log-dd-actions">';
		echo '<button type="button" class="pili-log-dd-link pili-log-dd-all">' . pili_esc_html__( '全选' ) . '</button>';
		echo '<button type="button" class="pili-log-dd-link pili-log-dd-clear">' . pili_esc_html__( '清空' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '<ul class="pili-log-dd-list" role="listbox" aria-multiselectable="true">';
		foreach ( $options as $value => $label ) {
			$value = (string) $value;
			if ( '' === $value ) {
				continue;
			}
			echo '<li class="pili-log-dd-option" role="option" aria-selected="false" data-value="' . esc_attr( $value ) . '" data-label="' . esc_attr( (string) $label ) . '" tabindex="0">';
			echo '<span class="pili-log-dd-check" aria-hidden="true"><i class="ri-check-line"></i></span>';
			echo '<span class="pili-log-dd-option-text">' . esc_html( (string) $label ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<div class="pili-log-dd-empty" hidden>' . pili_esc_html__( '无匹配项' ) . '</div>';
		echo '</div>';
		echo '</div>';
	}

	public function validate( $value ) {
		return $this->value;
	}

	public function enqueue() {
		// 工具栏操作反馈走 PiliXunToast（带 PiliBot）
		$toast_file = trailingslashit( (string) PILI_Setup::$dir ) . 'fields/toast/toast.php';
		if ( is_readable( $toast_file ) && ! class_exists( __NAMESPACE__ . '\\PILI_Field_toast', false ) ) {
			require_once $toast_file;
		}
		if ( class_exists( __NAMESPACE__ . '\\PILI_Field_toast' ) ) {
			$toast = new PILI_Field_toast(
				array(
					'id'    => 'xun-toast-host',
					'type'  => 'toast',
					'mount' => false,
				)
			);
			if ( method_exists( $toast, 'enqueue' ) ) {
				$toast->enqueue();
			}
		}

		wp_enqueue_style(
			pili_asset_handle( 'field-log-viewer' ),
			PILI_Setup::$url . '/assets/css/fields/log_viewer.css',
			array(),
			PILI_CORE_VERSION
		);

		$script_deps = array( 'jquery' );
		if ( wp_script_is( pili_asset_handle( 'field-toast' ), 'registered' ) || wp_script_is( pili_asset_handle( 'field-toast' ), 'enqueued' ) ) {
			$script_deps[] = pili_asset_handle( 'field-toast' );
		}

		$log_viewer_js = PILI_Setup::$dir . '/assets/js/fields/log_viewer.js';
		$log_viewer_ver = is_readable( $log_viewer_js ) ? (string) filemtime( $log_viewer_js ) : PILI_CORE_VERSION;
		wp_enqueue_script(
			pili_asset_handle( 'field-log-viewer' ),
			PILI_Setup::$url . '/assets/js/fields/log_viewer.js',
			$script_deps,
			$log_viewer_ver,
			true
		);

		$handle = pili_asset_handle( 'field-log-viewer' );
		pili_localize_bag(
			$handle,
			'log_viewer',
			array(
				'strings' => array(
					'empty'         => pili__( '暂无运行日志。' ),
					'copied'        => pili__( '已复制到剪贴板' ),
					'copyFail'      => pili__( '复制失败' ),
					'pageOf'        => pili__( '第 %1$s / %2$s 页' ),
					'refreshOk'     => pili__( '已刷新' ),
					'refreshFail'   => pili__( '刷新失败' ),
					'clearConfirm'  => pili__( '确定清空全部运行日志？此操作不可恢复。' ),
					'clearOk'       => pili__( '已清空日志' ),
					'clearFail'     => pili__( '清空失败' ),
					'clearing'      => pili__( '清空中' ),
					'refreshing'    => pili__( '刷新中' ),
					'exporting'     => pili__( '导出中' ),
					'exported'      => pili__( '已开始下载日志文件' ),
					'exportFail'    => pili__( '导出失败' ),
					'exportRangeInvalid' => pili__( '开始时间不能晚于结束时间' ),
					'exportEmptyRange'   => pili__( '该时间段内没有可导出的日志' ),
					'localOnly'     => pili__( '未配置服务端动作，仅清空当前页面列表' ),
					'clearTitle'    => pili__( '清空日志' ),
					'clearOkBtn'    => pili__( '清空' ),
					'cancel'        => pili__( '取消' ),
				),
			)
		);
	}
}
