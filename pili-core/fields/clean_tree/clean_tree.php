<?php
/**
 * Field: clean_tree
 *
 * 单一面板内的两级树：分类行 + 带连接线的子项。条目由 schema 传入。
 *
 * @package PILI
 */

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\PILI_Field_clean_tree' ) ) {

	/**
	 * Clean tree field.
	 */
	class PILI_Field_clean_tree extends PILI_Fields {

		/**
		 * Render unified tree.
		 *
		 * @return void
		 */
		public function render() {
			$groups = isset( $this->field['groups'] ) && is_array( $this->field['groups'] ) ? $this->field['groups'] : array();
			$saved  = array();
			if ( is_array( $this->value ) ) {
				$saved = $this->value;
			} elseif ( isset( $this->field['default'] ) && is_array( $this->field['default'] ) ) {
				$saved = $this->field['default'];
			}
			$saved_map = array();
			foreach ( $saved as $key ) {
				$saved_map[ (string) $key ] = true;
			}

			$field_id = isset( $this->field['id'] ) ? (string) $this->field['id'] : '';
			$col_item   = isset( $this->field['col_item'] ) ? (string) $this->field['col_item'] : pili__( '清理项目' );
			$col_risk   = isset( $this->field['col_risk'] ) ? (string) $this->field['col_risk'] : pili__( '风险' );
			$col_count  = isset( $this->field['col_count'] ) ? (string) $this->field['col_count'] : pili__( '条目' );
			$col_size   = isset( $this->field['col_size'] ) ? (string) $this->field['col_size'] : pili__( '预估大小' );
			$col_action = isset( $this->field['col_action'] ) ? (string) $this->field['col_action'] : pili__( '处理' );

			echo $this->field_before();
			echo '<div class="pili-clean-tree" data-field-id="' . esc_attr( $field_id ) . '" data-label-open="' . esc_attr( pili__( '展开' ) ) . '" data-label-fold="' . esc_attr( pili__( '收起' ) ) . '">';
			echo '<div class="pili-clean-tree__panel">';

			echo '<div class="pili-clean-tree__tools">';
			echo '<span class="pili-clean-tree__tools-label">' . esc_html( pili__( '快捷选择' ) ) . '</span>';
			$this->render_action_button( pili__( '全选' ), 'default', array( 'data-clean-act' => 'all' ) );
			$this->render_action_button( pili__( '勾选安全项' ), 'success', array( 'data-clean-act' => 'safe' ) );
			$this->render_action_button( pili__( '取消勾选' ), 'default', array( 'data-clean-act' => 'none' ) );
			$this->render_action_button(
				pili__( '全部折叠' ),
				'default',
				array(
					'data-clean-act'        => 'fold',
					'data-clean-fold-label' => pili__( '全部折叠' ),
					'data-clean-open-label' => pili__( '全部展开' ),
				)
			);
			echo '</div>';

			echo '<div class="pili-clean-tree__cols" role="row">';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--item">';
			echo '<input class="pili-clean-tree__check pili-clean-tree__check--global" type="checkbox" data-clean-global title="' . esc_attr( pili__( '全选' ) ) . '" />';
			echo '<span>' . esc_html( $col_item ) . '</span>';
			echo '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--risk">' . esc_html( $col_risk ) . '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--count">' . esc_html( $col_count ) . '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--size">' . esc_html( $col_size ) . '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--action">' . esc_html( $col_action ) . '</div>';
			echo '</div>';

			echo '<ul class="pili-clean-tree__list">';
			foreach ( $groups as $group ) {
				$this->render_group( $group, $field_id, $saved_map );
			}
			echo '</ul>';

			echo '</div>';
			echo '</div>';
			echo $this->field_after();
		}

		/**
		 * Render one category + children.
		 *
		 * @param array               $group     Group schema.
		 * @param string              $field_id  Field id.
		 * @param array<string,bool>  $saved_map Selected keys.
		 * @return void
		 */
		private function render_group( $group, $field_id, $saved_map ) {
			if ( ! is_array( $group ) ) {
				return;
			}
			$gid    = isset( $group['id'] ) ? sanitize_key( (string) $group['id'] ) : '';
			$gtitle = isset( $group['title'] ) ? (string) $group['title'] : '';
			$gdesc  = isset( $group['desc'] ) ? (string) $group['desc'] : '';
			$open   = ! isset( $group['open'] ) || ! empty( $group['open'] );
			$items  = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
			$n         = 0;
			$bytes_sum = 0;
			$has_bytes = false;
			$count_sum = 0;
			$has_count = false;
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || empty( $item['id'] ) ) {
					continue;
				}
				++$n;
				if ( isset( $item['bytes'] ) && is_numeric( $item['bytes'] ) ) {
					$bytes_sum += (int) $item['bytes'];
					$has_bytes  = true;
				}
				if ( isset( $item['count'] ) && is_numeric( $item['count'] ) ) {
					$count_sum += (int) $item['count'];
					$has_count  = true;
				}
			}

			echo '<li class="pili-clean-tree__cat' . ( $open ? ' is-open' : '' ) . '" data-group="' . esc_attr( $gid ) . '">';
			echo '<div class="pili-clean-tree__row pili-clean-tree__row--cat">';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--item">';
			echo '<div class="pili-clean-tree__main">';
			echo '<input class="pili-clean-tree__check pili-clean-tree__check--cat" type="checkbox" data-cat-check />';
			echo '<button type="button" class="pili-clean-tree__caret" data-clean-toggle aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-label="' . esc_attr( pili__( '展开或收起' ) ) . '">';
			echo '<span class="pili-clean-tree__chevron" aria-hidden="true"></span>';
			echo '</button>';
			echo '<div class="pili-clean-tree__copy">';
			echo '<div class="pili-clean-tree__name">';
			echo '<span class="pili-clean-tree__title">' . esc_html( $gtitle ) . '</span>';
			echo '<span class="pili-clean-tree__badge">' . esc_html( sprintf( /* translators: %d item count */ pili__( '%d 项' ), $n ) ) . '</span>';
			echo '</div>';
			if ( '' !== $gdesc ) {
				echo '<p class="pili-clean-tree__desc">' . esc_html( $gdesc ) . '</p>';
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--risk pili-clean-tree__muted">' . esc_html( pili__( '分类' ) ) . '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--count" data-cat-count>' . esc_html( $has_count ? sprintf( /* translators: %d row count */ pili__( '%d 条' ), $count_sum ) : '—' ) . '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--size" data-cat-size>' . esc_html( $has_bytes ? $this->format_bytes( $bytes_sum ) : '—' ) . '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--action">';
			$this->render_action_button(
				$open ? pili__( '收起' ) : pili__( '展开' ),
				'default',
				array(
					'data-clean-toggle' => '1',
				)
			);
			echo '</div>';
			echo '</div>';

			echo '<ul class="pili-clean-tree__leaves"' . ( $open ? '' : ' hidden' ) . '>';
			foreach ( $items as $item ) {
				$this->render_item( $item, $field_id, $saved_map );
			}
			echo '</ul>';
			echo '</li>';
		}

		/**
		 * Render a leaf.
		 *
		 * @param mixed               $item      Item schema.
		 * @param string              $field_id  Field id.
		 * @param array<string,bool>  $saved_map Selected keys.
		 * @return void
		 */
		private function render_item( $item, $field_id, $saved_map ) {
			if ( ! is_array( $item ) ) {
				return;
			}
			$iid = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
			if ( '' === $iid ) {
				return;
			}
			$title      = isset( $item['title'] ) ? (string) $item['title'] : $iid;
			$desc       = isset( $item['desc'] ) ? (string) $item['desc'] : '';
			$risk       = isset( $item['risk'] ) ? sanitize_key( (string) $item['risk'] ) : 'safe';
			$checked    = isset( $saved_map[ $iid ] );
			$uid        = $field_id . '_' . $iid;
			$risk_label = ( 'caution' === $risk ) ? pili__( '谨慎' ) : pili__( '较安全' );
			$size_text  = $this->item_size_label( $item );
			$count_text = $this->item_count_label( $item );
			$count_n    = isset( $item['count'] ) && is_numeric( $item['count'] ) ? (int) $item['count'] : 0;
			$bytes_n    = isset( $item['bytes'] ) && is_numeric( $item['bytes'] ) ? (int) $item['bytes'] : 0;

			echo '<li class="pili-clean-tree__leaf" data-count="' . esc_attr( (string) $count_n ) . '" data-bytes="' . esc_attr( (string) $bytes_n ) . '">';
			echo '<div class="pili-clean-tree__row pili-clean-tree__row--leaf">';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--item">';
			echo '<div class="pili-clean-tree__main">';
			echo '<input class="pili-clean-tree__check pili-clean-tree__check--item" type="checkbox" id="' . esc_attr( $uid ) . '" name="' . esc_attr( $this->field_name() ) . '[]" value="' . esc_attr( $iid ) . '" data-risk="' . esc_attr( $risk ) . '"' . ( $checked ? ' checked' : '' ) . ' />';
			echo '<div class="pili-clean-tree__copy">';
			echo '<label class="pili-clean-tree__title" for="' . esc_attr( $uid ) . '">' . esc_html( $title ) . '</label>';
			if ( '' !== $desc ) {
				echo '<p class="pili-clean-tree__desc">' . esc_html( $desc ) . '</p>';
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--risk">';
			echo '<span class="pili-clean-tree__risk pili-clean-tree__risk--' . esc_attr( $risk ) . '">' . esc_html( $risk_label ) . '</span>';
			echo '</div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--count"><span class="pili-clean-tree__count" data-item-count>' . esc_html( $count_text ) . '</span></div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--size"><span class="pili-clean-tree__size" data-item-size>' . esc_html( $size_text ) . '</span></div>';
			echo '<div class="pili-clean-tree__col pili-clean-tree__col--action">';
			$this->render_action_button(
				pili__( '清理' ),
				'primary',
				array(
					'data-clean-one' => $iid,
				)
			);
			echo '</div>';
			echo '</div>';
			echo '</li>';
		}

		/**
		 * Count label for a leaf.
		 *
		 * @param array<string,mixed> $item Item schema.
		 * @return string
		 */
		private function item_count_label( $item ) {
			if ( isset( $item['count_label'] ) && '' !== (string) $item['count_label'] ) {
				return (string) $item['count_label'];
			}
			if ( isset( $item['count'] ) && is_numeric( $item['count'] ) ) {
				return sprintf( /* translators: %d row count */ pili__( '%d 条' ), (int) $item['count'] );
			}
			return '—';
		}

		/**
		 * Size label for a leaf.
		 *
		 * @param array<string,mixed> $item Item schema.
		 * @return string
		 */
		private function item_size_label( $item ) {
			if ( isset( $item['size_label'] ) && '' !== (string) $item['size_label'] ) {
				return (string) $item['size_label'];
			}
			if ( isset( $item['bytes'] ) && is_numeric( $item['bytes'] ) ) {
				return $this->format_bytes( (int) $item['bytes'] );
			}
			if ( isset( $item['size'] ) && '' !== $item['size'] && null !== $item['size'] ) {
				$unit = isset( $item['unit'] ) ? (string) $item['unit'] : 'MB';
				return trim( (string) $item['size'] . ' ' . $unit );
			}
			return '—';
		}

		/**
		 * Format bytes for display.
		 *
		 * @param int $bytes Bytes.
		 * @return string
		 */
		private function format_bytes( $bytes ) {
			if ( function_exists( 'size_format' ) ) {
				return (string) size_format( max( 0, (int) $bytes ), 1 );
			}
			return (string) $bytes;
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
			$css = pili_asset_handle( 'field-clean-tree' );
			$js  = pili_asset_handle( 'field-clean-tree-js' );
			if ( ! wp_style_is( $css, 'enqueued' ) ) {
				wp_enqueue_style(
					$css,
					PILI_Setup::$url . '/assets/css/fields/clean_tree.css',
					array(),
					(string) ( @filemtime( trailingslashit( (string) PILI_Setup::$dir ) . 'assets/css/fields/clean_tree.css' ) ?: PILI_CORE_VERSION )
				);
			}
			if ( ! wp_script_is( $js, 'enqueued' ) ) {
				wp_enqueue_script(
					$js,
					PILI_Setup::$url . '/assets/js/fields/clean_tree.js',
					array( 'jquery' ),
					PILI_CORE_VERSION,
					true
				);
			}
		}
	}
}
