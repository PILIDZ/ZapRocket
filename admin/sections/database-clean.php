<?php
/**
 * 数据库 · 垃圾清理工具页 (zr_db_*).
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zr_scan_cache = ( class_exists( 'ZapRocket_Db_Size_Estimator', false ) )
	? ZapRocket_Db_Size_Estimator::get_cache()
	: null;
$zr_scan_items = ( is_array( $zr_scan_cache ) && isset( $zr_scan_cache['items'] ) && is_array( $zr_scan_cache['items'] ) )
	? $zr_scan_cache['items']
	: array();

/**
 * Merge cached count/size into a tree item.
 *
 * @param array<string,mixed> $item Item.
 * @return array<string,mixed>
 */
$zr_attach_scan = static function ( array $item ) use ( $zr_scan_items ) {
	$id = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
	if ( '' === $id || ! isset( $zr_scan_items[ $id ] ) || ! is_array( $zr_scan_items[ $id ] ) ) {
		return $item;
	}
	$row = $zr_scan_items[ $id ];
	if ( isset( $row['count'] ) ) {
		$item['count'] = (int) $row['count'];
	}
	if ( isset( $row['label'] ) ) {
		$item['count_label'] = (string) $row['label'];
	}
	if ( isset( $row['bytes'] ) ) {
		$item['bytes'] = (int) $row['bytes'];
	}
	if ( isset( $row['size_label'] ) ) {
		$item['size_label'] = (string) $row['size_label'];
	}
	return $item;
};

$zr_size_value = ( is_array( $zr_scan_cache ) && ! empty( $zr_scan_cache['size_label'] ) )
	? (string) $zr_scan_cache['size_label']
	: '—';
$zr_size_note  = ( is_array( $zr_scan_cache ) && ! empty( $zr_scan_cache['size_note'] ) )
	? (string) $zr_scan_cache['size_note']
	: ( class_exists( 'ZapRocket_Db_Size_Estimator', false ) ? ZapRocket_Db_Size_Estimator::empty_note() : pili__( '暂无扫描记录，请点击「重新扫描」计算预估数据' ) );
$zr_found      = ( is_array( $zr_scan_cache ) && isset( $zr_scan_cache['total_count'] ) )
	? (int) $zr_scan_cache['total_count']
	: 0;

$zr_groups = array(
	array(
		'id'    => 'posts',
		'title' => pili__( '文章与草稿' ),
		'desc'  => pili__( '编辑过程中堆积的历史稿、自动草稿和回收站内容。' ),
		'open'  => true,
		'items' => array(
			$zr_attach_scan(
				array(
					'id'    => 'revisions',
					'title' => pili__( '修订版本（保存时多出来的历史稿）' ),
					'desc'  => pili__( '每次保存都会多一份历史。保留份数由「定时清理」决定。' ),
					'risk'  => 'caution',
				)
			),
			$zr_attach_scan(
				array(
					'id'    => 'autodrafts',
					'title' => pili__( '自动草稿（编辑时临时存的稿）' ),
					'desc'  => pili__( '编辑时自动存的临时稿。超过保留天数的才会清。' ),
					'risk'  => 'safe',
				)
			),
			$zr_attach_scan(
				array(
					'id'    => 'trash_posts',
					'title' => pili__( '回收站文章（丢掉还没清空的）' ),
					'desc'  => pili__( '丢进回收站还没清空的文章。' ),
					'risk'  => 'safe',
				)
			),
		),
	),
	array(
		'id'    => 'comments',
		'title' => pili__( '评论' ),
		'desc'  => pili__( '垃圾留言和回收站里的评论。' ),
		'open'  => true,
		'items' => array(
			$zr_attach_scan(
				array(
					'id'    => 'spam_comments',
					'title' => pili__( '垃圾评论（标成垃圾的留言）' ),
					'desc'  => pili__( '标成垃圾的评论。也可配合反垃圾插件减少来源。' ),
					'risk'  => 'safe',
				)
			),
			$zr_attach_scan(
				array(
					'id'    => 'trash_comments',
					'title' => pili__( '回收站评论（丢掉还没清空的）' ),
					'desc'  => pili__( '丢掉但仍暂存在回收站的评论。' ),
					'risk'  => 'safe',
				)
			),
		),
	),
	array(
		'id'    => 'meta',
		'title' => pili__( '临时数据' ),
		'desc'  => pili__( '过期缓存和已经没主数据的附加信息。' ),
		'open'  => true,
		'items' => array(
			$zr_attach_scan(
				array(
					'id'    => 'expired_transients',
					'title' => pili__( '过期临时数据（过期缓存）' ),
					'desc'  => pili__( 'options 表里过期的 transient。未过期的不会动。' ),
					'risk'  => 'safe',
				)
			),
			$zr_attach_scan(
				array(
					'id'    => 'orphaned_meta',
					'title' => pili__( '孤立元数据（主数据没了还留着的）' ),
					'desc'  => pili__( '对应文章或评论已经没了，却还留着的附加信息。' ),
					'risk'  => 'caution',
				)
			),
		),
	),
	array(
		'id'    => 'tables',
		'title' => pili__( '数据表' ),
		'desc'  => pili__( '整理表空间，不等于删除内容。大站请在访问少时再做。' ),
		'open'  => false,
		'items' => array(
			$zr_attach_scan(
				array(
					'id'    => 'optimize_tables',
					'title' => pili__( '整理数据表（回收空出来的空间）' ),
					'desc'  => pili__( '回收表里空出来的空间。对常见 InnoDB 效果有限，白天可能短暂停一下。' ),
					'risk'  => 'caution',
				)
			),
		),
	),
);

return array(
	array(
		'id'           => 'zr_db_clean_toolbar',
		'type'         => 'clean_toolbar',
		'sanitize'     => static function () {
			return '';
		},
		'tree_id'      => 'zr_db_clean_items',
		'heading'      => pili__( '数据库清理' ),
		'lead'         => pili__( '先勾选再清理。删了不能恢复，动手前请备份。预估体积仅供参考，实际释放空间以清理完成后为准。数据不会自动刷新，如需最新预估，请手动点击重新扫描。' ),
		'cached_size'  => $zr_size_value,
		'cached_note'  => $zr_size_note,
		'cached_found' => $zr_found,
	),
	array(
		'id'        => 'zr_db_clean_items',
		'type'      => 'clean_tree',
		'sanitize'  => static function ( $value ) {
			if ( ! is_array( $value ) ) {
				return array();
			}
			$out = array();
			foreach ( $value as $item ) {
				$key = sanitize_key( (string) $item );
				if ( '' !== $key ) {
					$out[] = $key;
				}
			}
			return array_values( array_unique( $out ) );
		},
		'col_count' => pili__( '条目' ),
		'col_size'  => pili__( '预估大小' ),
		'default'   => array(
			'autodrafts',
			'trash_posts',
			'spam_comments',
			'trash_comments',
			'expired_transients',
		),
		'groups'    => $zr_groups,
	),
);
