<?php
/**
 * Field: clean_toolbar
 *
 * 清理工具总览：环形图、指标卡、扫描/清理。不参与业务删除（P3）。
 *
 * @package PILI
 */

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_clean_toolbar' ) ) {

	/**
	 * Clean toolbar field.
	 */
	class PILI_Field_clean_toolbar extends PILI_Fields {

		/**
		 * Render toolbar.
		 *
		 * @return void
		 */
		public function render() {
			$tree_id = isset( $this->field['tree_id'] ) ? sanitize_key( (string) $this->field['tree_id'] ) : '';
			$heading = isset( $this->field['heading'] ) ? (string) $this->field['heading'] : pili__( '数据库清理' );
			$lead    = isset( $this->field['lead'] ) ? (string) $this->field['lead'] : '';
			$cached_size  = isset( $this->field['cached_size'] ) && '' !== (string) $this->field['cached_size']
				? (string) $this->field['cached_size']
				: '—';
			$cached_note  = isset( $this->field['cached_note'] ) && '' !== (string) $this->field['cached_note']
				? (string) $this->field['cached_note']
				: pili__( '暂无扫描记录，请点击「重新扫描」计算预估数据' );
			$cached_found = isset( $this->field['cached_found'] ) ? (int) $this->field['cached_found'] : 0;

			echo $this->field_before();
			echo '<div class="pili-clean-toolbar" data-field-id="' . esc_attr( (string) $this->field['id'] ) . '" data-tree-id="' . esc_attr( $tree_id ) . '">';

			echo '<div class="pili-clean-toolbar__hero">';
			echo '<div class="pili-clean-toolbar__intro">';
			echo '<div class="pili-clean-toolbar__gauge" data-clean-gauge>';
			echo '<svg class="pili-clean-toolbar__dial" viewBox="0 0 120 120" aria-hidden="true">';
			echo '<circle class="pili-clean-toolbar__track" cx="60" cy="60" r="50" fill="none" stroke-width="10" />';
			echo '<circle class="pili-clean-toolbar__arc" data-clean-arc cx="60" cy="60" r="50" fill="none" stroke-width="10" stroke-linecap="round" transform="rotate(-90 60 60)" />';
			echo '</svg>';
			echo '<div class="pili-clean-toolbar__gauge-center" data-stat="size">';
			echo '<span class="pili-clean-toolbar__gauge-kicker">' . esc_html( pili__( '可释放空间' ) ) . '</span>';
			echo '<span class="pili-clean-toolbar__gauge-value" data-stat-value>' . esc_html( $cached_size ) . '</span>';
			echo $this->loading_markup();
			echo '<span class="pili-clean-toolbar__gauge-unit">' . esc_html( pili__( '估算' ) ) . '</span>';
			echo '</div>';
			echo '</div>';

			echo '<div class="pili-clean-toolbar__copy">';
			echo '<p class="pili-clean-toolbar__badge">' . esc_html( $heading ) . '</p>';
			echo '<h3 class="pili-clean-toolbar__title">';
			echo esc_html( pili__( '发现' ) );
			echo ' <em data-clean-found>' . esc_html( (string) $cached_found ) . '</em> ';
			echo esc_html( pili__( '条可清理' ) );
			echo '</h3>';
			if ( '' !== $lead ) {
				echo '<p class="pili-clean-toolbar__lead">' . esc_html( $lead ) . '</p>';
			}
			echo '</div>';
			echo '</div>';

			echo '<div class="pili-clean-toolbar__stats">';
			$this->render_stat( 'selected', pili__( '已勾选' ), '0', pili__( '当前勾选的清理项' ), 'ri-checkbox-multiple-line' );
			$this->render_stat( 'size', pili__( '预计体积' ), $cached_size, $cached_note, 'ri-database-2-line', true );
			$this->render_stat( 'last', pili__( '上次清理' ), '—', pili__( '还没有清理记录' ), 'ri-calendar-check-line' );
			$this->render_stat( 'items', pili__( '可清理项' ), '—', pili__( '列表里的项目数' ), 'ri-file-list-3-line' );
			echo '</div>';

			echo '<div class="pili-clean-toolbar__cta">';
			$this->render_action_button(
				pili__( '重新扫描' ),
				'default',
				array(
					'data-clean-act'  => 'scan',
					'data-label-scan' => pili__( '重新扫描' ),
				)
			);
			$this->render_action_button(
				pili__( '清理' ),
				'solid',
				array(
					'data-clean-act' => 'run',
					'data-label-run' => pili__( '清理' ),
				)
			);
			echo '<p class="pili-clean-toolbar__cta-note">' . esc_html( pili__( '删了不能恢复，请先备份。预估体积仅参考，数据不会自动刷新。' ) ) . '</p>';
			echo '</div>';
			echo '</div>';

			echo '<div class="pili-clean-toolbar__progress" data-clean-progress hidden>';
			echo '<div class="pili-clean-toolbar__bar"><span class="pili-clean-toolbar__bar-fill" data-clean-bar></span></div>';
			echo '<p class="pili-clean-toolbar__status" data-clean-status></p>';
			echo '</div>';

			echo '</div>';
			echo $this->field_after();
		}

		/**
		 * One metric card.
		 *
		 * @param string $key     Stat key.
		 * @param string $label   Label.
		 * @param string $value   Value.
		 * @param string $hint    Hint.
		 * @param string $icon    Remix icon class.
		 * @param bool   $loading Show loading slot.
		 * @return void
		 */
		private function render_stat( $key, $label, $value, $hint, $icon, $loading = false ) {
			if ( ! preg_match( '/^ri-[a-z0-9-]+$/', $icon ) ) {
				$icon = 'ri-bar-chart-line';
			}
			echo '<div class="pili-clean-toolbar__stat pili-clean-toolbar__stat--' . esc_attr( $key ) . '" data-stat="' . esc_attr( $key ) . '">';
			echo '<div class="pili-clean-toolbar__stat-head">';
			echo '<p class="pili-clean-toolbar__stat-label">' . esc_html( $label ) . '</p>';
			echo '<i class="' . esc_attr( $icon ) . ' pili-clean-toolbar__stat-icon" aria-hidden="true"></i>';
			echo '</div>';
			echo '<p class="pili-clean-toolbar__stat-value">';
			echo '<span data-stat-value>' . esc_html( $value ) . '</span>';
			if ( $loading ) {
				echo $this->loading_markup();
			}
			echo '</p>';
			echo '<p class="pili-clean-toolbar__stat-hint" data-stat-hint>' . esc_html( $hint ) . '</p>';
			echo '</div>';
		}

		/**
		 * PILI loading spinner (xs).
		 *
		 * @return string
		 */
		private function loading_markup() {
			$html = '';
			if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_loading' ) ) {
				$file = trailingslashit( (string) PILI_Setup::$dir ) . 'fields/loading/loading.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
			$html .= '<span class="pili-clean-toolbar__loading" data-stat-loading hidden>';
			if ( class_exists( __NAMESPACE__ . '\PILI_Field_loading' ) ) {
				$html .= PILI_Field_loading::render_spinner(
					array(
						'size'       => 'xs',
						'aria_label' => pili__( '正在计算体积' ),
					)
				);
			} else {
				$html .= '…';
			}
			$html .= '</span>';
			return $html;
		}

		/**
		 * Spec action button (table field helper).
		 *
		 * @param string               $label   Label.
		 * @param string               $variant Variant.
		 * @param array<string,string> $attrs   Extra attributes.
		 * @return void
		 */
		private function render_action_button( $label, $variant, $attrs ) {
			if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_table' ) ) {
				$table = trailingslashit( (string) PILI_Setup::$dir ) . 'fields/table/table.php';
				if ( is_readable( $table ) ) {
					require_once $table;
				}
			}
			if ( class_exists( __NAMESPACE__ . '\PILI_Field_table' ) ) {
				echo PILI_Field_table::render_action_button(
					array(
						'label'   => $label,
						'variant' => $variant,
						'attrs'   => $attrs,
					)
				);
				return;
			}
			echo '<button type="button"';
			foreach ( $attrs as $name => $value ) {
				echo ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
			}
			echo '>' . esc_html( $label ) . '</button>';
		}

		/**
		 * Enqueue assets.
		 *
		 * @return void
		 */
		public function enqueue() {
			if ( class_exists( __NAMESPACE__ . '\Remix_Icons' ) ) {
				Remix_Icons::enqueue_style( 'pilidoc-remixicon-clean-toolbar' );
			} elseif ( class_exists( 'Pilidoc_Remix_Icons' ) ) {
				\Pilidoc_Remix_Icons::enqueue_style( 'pilidoc-remixicon-clean-toolbar' );
			}
			$css = pili_asset_handle( 'field-clean-toolbar' );
			$js  = pili_asset_handle( 'field-clean-toolbar-js' );
			$load = pili_asset_handle( 'field-loading' );
			if ( ! wp_style_is( $load, 'enqueued' ) ) {
				wp_enqueue_style(
					$load,
					PILI_Setup::$url . '/assets/css/fields/loading.css',
					array(),
					PILI_CORE_VERSION
				);
			}
			if ( ! wp_style_is( $css, 'enqueued' ) ) {
				wp_enqueue_style(
					$css,
					PILI_Setup::$url . '/assets/css/fields/clean_toolbar.css',
					array(),
					(string) ( @filemtime( trailingslashit( (string) PILI_Setup::$dir ) . 'assets/css/fields/clean_toolbar.css' ) ?: PILI_CORE_VERSION )
				);
			}
			if ( ! wp_script_is( $js, 'enqueued' ) ) {
				wp_enqueue_script(
					$js,
					PILI_Setup::$url . '/assets/js/fields/clean_toolbar.js',
					array( 'jquery' ),
					(string) ( @filemtime( trailingslashit( (string) PILI_Setup::$dir ) . 'assets/js/fields/clean_toolbar.js' ) ?: PILI_CORE_VERSION ),
					true
				);
				wp_localize_script(
					$js,
					'piliCleanToolbarI18n',
					array(
						'scanPending' => pili__( '扫描接口将在后续版本接入，体积先显示为 —。' ),
						'scanDone'    => pili__( '扫描占位完成：尚未连接数据库统计。' ),
						'needSelect'  => pili__( '请先勾选要清理的项目。' ),
						'runPending'  => pili__( '清理不会在本页立即删库。批量删除将在后续版本接入。' ),
						'runOne'      => pili__( '单项清理尚未接删除接口：' ),
					)
				);
			}
		}
	}
}
