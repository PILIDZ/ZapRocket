/**
 * XUN record_viewer field — 分页键值对数据查看器
 *
 * 全局 API：window.PiliXunRecordViewer
 *   - setRecords(target, records)
 *   - goToPage(target, page)
 *   - getCurrentRecord(target)
 */
(function ($, window) {
	'use strict';

	var cfg = PILI.bag('record_viewer') || {};
	var strings = cfg.strings || {};

	function str(key, fallback) {
		return strings[key] || fallback;
	}

	function parseJsonAttr($el, name, fallback) {
		var raw = $el.attr(name);
		if (!raw) {
			return fallback;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return fallback;
		}
	}

	function openIdb(dbName, storeName) {
		return new Promise(function (resolve, reject) {
			if (!window.indexedDB) {
				reject(new Error('IndexedDB unavailable'));
				return;
			}
			var request = indexedDB.open(dbName, 1);
			request.onupgradeneeded = function (e) {
				var db = e.target.result;
				if (!db.objectStoreNames.contains(storeName)) {
					db.createObjectStore(storeName, { keyPath: 'id' });
				}
			};
			request.onsuccess = function (e) {
				resolve(e.target.result);
			};
			request.onerror = function (e) {
				reject(e.target.error);
			};
		});
	}

	function idbGetAll(dbName, storeName) {
		return openIdb(dbName, storeName).then(function (db) {
			return new Promise(function (resolve, reject) {
				var tx = db.transaction([storeName], 'readonly');
				var store = tx.objectStore(storeName);
				var req = store.getAll();
				req.onsuccess = function () {
					resolve(req.result || []);
				};
				req.onerror = function () {
					reject(req.error);
				};
			});
		});
	}

	function formatValue(val) {
		if (val == null || val === '') {
			return '-';
		}
		if (typeof val === 'object') {
			return JSON.stringify(val);
		}
		return String(val);
	}

	function initOne($field) {
		if ($field.data('xunRecordViewerInited')) {
			return;
		}
		$field.data('xunRecordViewerInited', true);

		var state = {
			records: [],
			currentPage: 1,
			expandedKeys: {}
		};

		var truncateLen = parseInt($field.data('truncate-length'), 10) || 120;
		var emptyText = $field.data('empty-text') || str('empty', '暂无数据');
		var loadingText = $field.data('loading-text') || str('loading', '加载中…');
		var idbDb = $field.data('idb-db') || '';
		var idbStore = $field.data('idb-store') || '';
		var keys = parseJsonAttr($field, 'data-keys', []);

		var $prev = $field.find('.pili-record-viewer-prev');
		var $next = $field.find('.pili-record-viewer-next');
		var $pageInput = $field.find('.pili-record-viewer-page-wrap input[type="number"]').first();
		var $total = $field.find('.pili-record-viewer-total');
		var $rows = $field.find('.pili-record-viewer-rows');

		function setRecords(records) {
			keys = parseJsonAttr($field, 'data-keys', []);
			if (!keys.length && Array.isArray(records) && records.length && records[0]) {
				keys = Object.keys(records[0]).map(function (k) {
					return { id: k, label: k, expandable: k === 'content', allow_html: false };
				});
			}
			state.records = Array.isArray(records) ? records : [];
			if (state.currentPage > state.records.length) {
				state.currentPage = Math.max(1, state.records.length);
			}
			if (state.records.length === 0) {
				state.currentPage = 1;
			}
			state.expandedKeys = {};
			render();
		}

		var tdStyle =
			'border:1px solid #e5e7eb;padding:0.65rem 0.875rem;font-size:13px;color:#374151;background:#ffffff;vertical-align:top;word-break:break-word;line-height:1.5;';

		function renderRow(keyCfg, value) {
			var keyId = keyCfg.id;
			var label = keyCfg.label || keyId;
			var text = formatValue(value);
			var expandable = !!keyCfg.expandable;
			var isExpanded = !!state.expandedKeys[keyId];

			var $row = $('<tr class="pili-record-viewer-row hover:bg-gray-50/80"></tr>');
			var $keyCol = $('<th scope="row" class="pili-record-viewer-key"></th>').text(label);
			var $valCol = $('<td></td>').attr('style', tdStyle);

			if (expandable && text.length > truncateLen && !isExpanded) {
				$valCol.text(text.substring(0, truncateLen) + '… ');
				var $more = $('<span class="pili-record-viewer-toggle"></span>')
					.text('[' + str('showMore', '展开') + ']')
					.on('click', function () {
						state.expandedKeys[keyId] = true;
						render();
					});
				$valCol.append($more);
			} else {
				if (keyCfg.allow_html) {
					$valCol.html(text);
				} else {
					$valCol.text(text);
				}
				if (expandable && text.length > truncateLen) {
					var $less = $('<span class="pili-record-viewer-toggle"></span>')
						.text('[' + str('showLess', '收起') + ']')
						.on('click', function () {
							state.expandedKeys[keyId] = false;
							render();
						});
					$valCol.append(' ').append($less);
				}
			}

			$row.append($keyCol, $valCol);
			return $row;
		}

		function render() {
			var total = state.records.length;
			$total.text(total);
			$pageInput.val(state.currentPage);
			$pageInput.attr('max', Math.max(1, total));

			$prev.prop('disabled', state.currentPage <= 1 || total === 0);
			$next.prop('disabled', state.currentPage >= total || total === 0);

			$rows.empty();

			if (total === 0) {
				$rows.html(
					'<tr class="pili-record-viewer-placeholder-row"><td colspan="2" style="text-align:center;color:#64748b;border:1px solid #e5e7eb;padding:1rem;font-size:13px;">' +
						emptyText +
						'</td></tr>'
				);
				$field.trigger('xun:record_viewer:change', [{ page: 0, total: 0, record: null }]);
				return;
			}

			var record = state.records[state.currentPage - 1];
			if (!record) {
				$rows.html(
					'<tr class="pili-record-viewer-placeholder-row"><td colspan="2" style="text-align:center;color:#64748b;border:1px solid #e5e7eb;padding:1rem;font-size:13px;">' +
						str('notFound', '该记录不存在') +
						'</td></tr>'
				);
				return;
			}

			if (!keys.length) {
				keys = Object.keys(record).map(function (k) {
					return { id: k, label: k, expandable: k === 'content', allow_html: false };
				});
			}

			keys.forEach(function (keyCfg) {
				var val = record[keyCfg.id];
				$rows.append(renderRow(keyCfg, val));
			});

			$field.trigger('xun:record_viewer:change', [{
				page: state.currentPage,
				total: total,
				record: record
			}]);
		}

		function goToPage(page) {
			var total = state.records.length;
			var val = parseInt(page, 10);
			if (isNaN(val) || val < 1) {
				val = 1;
			}
			if (val > total && total > 0) {
				val = total;
			}
			state.currentPage = val;
			state.expandedKeys = {};
			render();
		}

		$prev.on('click', function () {
			if (state.currentPage > 1) {
				goToPage(state.currentPage - 1);
			}
		});

		$next.on('click', function () {
			if (state.currentPage < state.records.length) {
				goToPage(state.currentPage + 1);
			}
		});

		$pageInput.on('change', function () {
			goToPage($(this).val());
		});

		$field.data('xunRecordViewerSetRecords', setRecords);
		$field.data('xunRecordViewerGoToPage', goToPage);
		$field.data('xunRecordViewerGetRecord', function () {
			var total = state.records.length;
			if (total === 0 || !state.records[state.currentPage - 1]) {
				return null;
			}
			return {
				page: state.currentPage,
				total: total,
				record: state.records[state.currentPage - 1]
			};
		});

		// 初始数据：优先 IndexedDB，否则 data-records
		var embedded = parseJsonAttr($field, 'data-records', []);
		if (idbDb && idbStore) {
			$rows.html(
				'<tr class="pili-record-viewer-placeholder-row is-loading"><td colspan="2" style="text-align:center;color:#64748b;border:1px solid #e5e7eb;padding:1rem;font-size:13px;">' +
					loadingText +
					'</td></tr>'
			);
			idbGetAll(idbDb, idbStore)
				.then(function (records) {
					setRecords(records.length ? records : embedded);
				})
				.catch(function () {
					setRecords(embedded);
				});
		} else {
			setRecords(embedded);
		}
	}

	function initRecordViewer($root) {
		var $scope = ($root && $root.length) ? $root : $(document);
		$scope.find('.pili-record-viewer-field').each(function () {
			initOne($(this));
		});
	}

	PILI.registerBoot('record_viewer', function ($root) {
		initRecordViewer($root && $root.length ? $root : $(document));
	});

	function resolveField(target) {
		var $field = target instanceof $ ? target : $(target);
		if (!$field.hasClass('pili-record-viewer-field')) {
			$field = $field.closest('.pili-record-viewer-field');
		}
		return $field;
	}

	$(document).ready(function () {
		initRecordViewer($(document));
	});

	$(document).on('xun:field:added', function (e, $container) {
		PILI.boot('record_viewer', $container && $container.length ? $container : $(document));
	});

	window.PiliXunRecordViewer = {
		setRecords: function (target, records) {
			var $field = resolveField(target);
			if (!$field.length) {
				return;
			}
			var fn = $field.data('xunRecordViewerSetRecords');
			if (typeof fn === 'function') {
				fn(records);
			} else {
				$field.attr('data-records', JSON.stringify(records || []));
				initOne($field);
			}
		},
		goToPage: function (target, page) {
			var $field = resolveField(target);
			var fn = $field.data('xunRecordViewerGoToPage');
			if (typeof fn === 'function') {
				fn(page);
			}
		},
		getCurrentRecord: function (target) {
			var $field = resolveField(target);
			var fn = $field.data('xunRecordViewerGetRecord');
			return typeof fn === 'function' ? fn() : null;
		}
	};
})(jQuery, window);
