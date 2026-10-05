/**
 * PILI log_viewer — 自绘多选下拉 / 搜索 / 开关
 *
 * 全局 API：window.PiliLogViewer
 */
(function ($, window, document) {
	'use strict';

	var cfg = (window.PILI && PILI.bag ? PILI.bag('log_viewer') : null) || window.piliLogViewerField || window.xunLogViewerField || {};
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

	function escapeHtml(text) {
		return String(text == null ? '' : text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function channelLabel(channels, entry) {
		var key = entry.channel || '';
		if (entry.channel_label) {
			return entry.channel_label;
		}
		if (channels && channels[key]) {
			return channels[key];
		}
		return key || 'system';
	}

	function closeAllDropdowns($except) {
		$('.pili-log-dd.is-open, .pili-log-dd.is-open').each(function () {
			var $dd = $(this);
			if ($except && $except.length && $dd.is($except)) {
				return;
			}
			$dd.removeClass('is-open');
			$dd.find('.pili-log-dd-trigger').attr('aria-expanded', 'false');
			$dd.find('.pili-log-dd-panel').prop('hidden', true);
			$dd.find('.pili-log-dd-filter').val('');
			$dd.find('.pili-log-dd-option').removeClass('is-hidden');
			$dd.find('.pili-log-dd-empty').prop('hidden', true);
		});
	}

	function initOne($field) {
		if ($field.data('piliLogViewerInited')) {
			return;
		}
		$field.data('piliLogViewerInited', true);

		var state = {
			entries: [],
			filtered: [],
			page: 1,
			expanded: {},
			levels: [],
			channels: [],
			query: '',
			autoscroll: true
		};

		var pageSize = parseInt($field.data('page-size'), 10) || 50;
		var serverPaging = String($field.attr('data-refresh-action') || '') !== '';
		var pagingBusy = false;
		var emptyText = $field.data('empty-text') || str('empty', '暂无运行日志。');
		var channelsMap = parseJsonAttr($field, 'data-channels', {});
		var i18nLevelAll = $field.attr('data-i18n-level-all') || '全部级别';
		var i18nChannelAll = $field.attr('data-i18n-channel-all') || '全部通道';

		var $stream = $field.find('.pili-log-viewer-stream');
		var $body = $field.find('.pili-log-viewer-body');
		var $visible = $field.find('.pili-log-viewer-visible-count');
		var $total = $field.find('.pili-log-viewer-total-count');
		var $pageLabel = $field.find('.pili-log-viewer-page-label');
		var $prev = $field.find('.pili-log-viewer-prev');
		var $next = $field.find('.pili-log-viewer-next');
		var $search = $field.find('.pili-log-viewer-search');
		var $switch = $field.find('.pili-log-viewer-switch[data-role="autoscroll"]');
		var $levelDd = $field.find('.pili-log-dd[data-ms="level"]');
		var $channelDd = $field.find('.pili-log-dd[data-ms="channel"]');

		function showToast(message, type) {
			var msg = String(message == null ? '' : message);
			if (!msg) {
				return;
			}
			var tipType = type || 'info';

			if (window.PILI && typeof window.PILI.toast === 'function') {
				window.PILI.toast(msg, { type: tipType });
				return;
			}
			if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
				window.PilipostUi.toast(msg, tipType);
				return;
			}
			if (window.PiliXunToast && typeof window.PiliXunToast.show === 'function') {
				window.PiliXunToast.show(msg, { type: tipType });
				return;
			}

			// 兜底：Toast 未入队时仍用组件内提示条
			var $toast = $field.find('.pili-log-viewer-toast');
			if (!$toast.length) {
				$toast = $('<div class="pili-log-viewer-toast" aria-live="polite"></div>');
				$field.append($toast);
			}
			$toast.text(msg).addClass('is-visible');
			window.clearTimeout($field.data('piliLogViewerToastTimer'));
			$field.data(
				'piliLogViewerToastTimer',
				window.setTimeout(function () {
					$toast.removeClass('is-visible');
				}, 1600)
			);
		}

		function readDdValues($dd) {
			var values = [];
			$dd.find('.pili-log-dd-option[aria-selected="true"]').each(function () {
				var v = String($(this).attr('data-value') || '');
				if (v) {
					values.push(v);
				}
			});
			return values;
		}

		function updateDdTags($dd, values, allText) {
			var $tags = $dd.find('.pili-log-dd-tags');
			var total = $dd.find('.pili-log-dd-option').length;
			$tags.empty();

			if (!values.length || values.length === total) {
				$tags.append(
					$('<span class="pili-log-dd-placeholder"></span>').text(allText)
				);
				return;
			}

			var maxShow = 2;
			values.slice(0, maxShow).forEach(function (val) {
				var label =
					$dd.find('.pili-log-dd-option[data-value="' + val + '"]').attr('data-label') ||
					val;
				var $tag = $(
					'<span class="pili-log-dd-tag"><span class="pili-log-dd-tag-text"></span><span class="pili-log-dd-tag-x" data-value="' +
						escapeHtml(val) +
						'" aria-label="remove">×</span></span>'
				);
				$tag.find('.pili-log-dd-tag-text').text(label);
				$tags.append($tag);
			});

			if (values.length > maxShow) {
				$tags.append(
					$('<span class="pili-log-dd-more"></span>').text(
						'+' + (values.length - maxShow)
					)
				);
			}
		}

		function syncDdFromDom() {
			state.levels = readDdValues($levelDd);
			state.channels = readDdValues($channelDd);
			updateDdTags($levelDd, state.levels, i18nLevelAll);
			updateDdTags($channelDd, state.channels, i18nChannelAll);
		}

		function setDdOpen($dd, open) {
			if (!$dd.length) {
				return;
			}
			if (open) {
				closeAllDropdowns($dd);
				$dd.addClass('is-open');
				$dd.find('.pili-log-dd-trigger').attr('aria-expanded', 'true');
				$dd.find('.pili-log-dd-panel').prop('hidden', false);
				window.setTimeout(function () {
					$dd.find('.pili-log-dd-filter').trigger('focus');
				}, 0);
			} else {
				$dd.removeClass('is-open');
				$dd.find('.pili-log-dd-trigger').attr('aria-expanded', 'false');
				$dd.find('.pili-log-dd-panel').prop('hidden', true);
				$dd.find('.pili-log-dd-filter').val('');
				$dd.find('.pili-log-dd-option').removeClass('is-hidden');
				$dd.find('.pili-log-dd-empty').prop('hidden', true);
			}
		}

		function toggleOption($option) {
			var selected = $option.attr('aria-selected') === 'true';
			$option.attr('aria-selected', selected ? 'false' : 'true');
			syncDdFromDom();
			state.page = 1;
			if (serverPaging) {
				requestPage(1, false);
				return;
			}
			render();
		}

		function setAllOptions($dd, selected) {
			$dd.find('.pili-log-dd-option').attr('aria-selected', selected ? 'true' : 'false');
			syncDdFromDom();
			state.page = 1;
			if (serverPaging) {
				requestPage(1, false);
				return;
			}
			render();
		}

		function filterDdOptions($dd, q) {
			q = String(q || '')
				.trim()
				.toLowerCase();
			var visible = 0;
			$dd.find('.pili-log-dd-option').each(function () {
				var $opt = $(this);
				var label = String($opt.attr('data-label') || '').toLowerCase();
				var value = String($opt.attr('data-value') || '').toLowerCase();
				var show = !q || label.indexOf(q) !== -1 || value.indexOf(q) !== -1;
				$opt.toggleClass('is-hidden', !show);
				if (show) {
					visible += 1;
				}
			});
			$dd.find('.pili-log-dd-empty').prop('hidden', visible > 0);
		}

		function applyFilter() {
			var q = String(state.query || '')
				.trim()
				.toLowerCase();
			var levelSet = state.levels;
			var channelSet = state.channels;
			var levelTotal = $levelDd.find('.pili-log-dd-option').length;
			var channelTotal = $channelDd.find('.pili-log-dd-option').length;
			var filterLevels = levelSet.length > 0 && levelSet.length < levelTotal;
			var filterChannels = channelSet.length > 0 && channelSet.length < channelTotal;

			state.filtered = state.entries.filter(function (entry) {
				if (filterLevels && levelSet.indexOf(entry.level || 'info') === -1) {
					return false;
				}
				if (filterChannels && channelSet.indexOf(entry.channel || 'system') === -1) {
					return false;
				}
				if (!q) {
					return true;
				}
				var hay = [
					entry.message || '',
					entry.context || '',
					entry.channel || '',
					entry.channel_label || '',
					entry.level || '',
					entry.time || ''
				]
					.join(' ')
					.toLowerCase();
				return hay.indexOf(q) !== -1;
			});

			var totalPages = Math.max(1, Math.ceil(state.filtered.length / pageSize) || 1);
			if (state.page > totalPages) {
				state.page = totalPages;
			}
			if (state.filtered.length === 0) {
				state.page = 1;
			}
		}

		function pageSlice() {
			var start = (state.page - 1) * pageSize;
			return state.filtered.slice(start, start + pageSize);
		}

		function levelTag(level) {
			var key = String(level || 'info').toUpperCase();
			var alias = { WARNING: 'WARN', SUCCESS: 'OK' };
			if (alias[key]) {
				key = alias[key];
			}
			while (key.length < 5) {
				key += ' ';
			}
			return key.slice(0, 5);
		}

		function renderRow(entry) {
			var level = entry.level || 'info';
			var id = String(entry.id || '');
			var msg = entry.message || '';
			var context = entry.context || '';
			var expanded = !!state.expanded[id];
			var ch = channelLabel(channelsMap, entry);

			var html =
				'<div class="pili-log-viewer-row is-' +
				escapeHtml(level) +
				'" data-id="' +
				escapeHtml(id) +
				'">';
			html +=
				'<span class="pili-log-viewer-time">' +
				escapeHtml(entry.time || '---- -- -- --:--:--') +
				'</span> ';
			html +=
				'<span class="pili-log-viewer-level-tag">[' +
				escapeHtml(levelTag(level)) +
				']</span> ';
			html +=
				'<span class="pili-log-viewer-channel-tag">[' +
				escapeHtml(ch) +
				']</span> ';
			html += '<span class="pili-log-viewer-message">' + escapeHtml(msg) + '</span>';
			if (context) {
				html +=
					'<span class="pili-log-viewer-context-toggle" data-id="' +
					escapeHtml(id) +
					'">' +
					escapeHtml(expanded ? '[-]' : '[+]') +
					'</span>';
			}
			if (context && expanded) {
				html +=
					'<div class="pili-log-viewer-context">' + escapeHtml(context) + '</div>';
			}
			html += '</div>';
			return html;
		}

		function renderServer(opts) {
			opts = opts || {};
			var bodyEl = $body.length ? $body[0] : null;
			var prevTop = bodyEl ? bodyEl.scrollTop : 0;
			var rows = state.entries || [];
			var total = state.serverTotal || 0;
			var totalPages = state.serverPages || 1;
			$visible.text(rows.length);
			$total.text(total);
			$pageLabel.text(
				str('pageOf', '第 %1$s / %2$s 页')
					.replace('%1$s', String(state.page || 1))
					.replace('%2$s', String(totalPages))
			);
			$prev.prop('disabled', (state.page || 1) <= 1 || total === 0);
			$next.prop('disabled', (state.page || 1) >= totalPages || total === 0);
			if (!rows.length) {
				$stream.html(
					'<div class="pili-log-viewer-placeholder">' +
						escapeHtml(emptyText || str('empty', '暂无运行日志。')) +
						'</div>'
				);
				return;
			}
			var html = '';
			rows.forEach(function (entry) {
				html += renderRow(entry);
			});
			$stream.html(html);
			if (bodyEl) {
				if (opts.followNewest && state.autoscroll) {
					bodyEl.scrollTop = 0;
				} else if (opts.resetScroll) {
					bodyEl.scrollTop = 0;
				} else {
					bodyEl.scrollTop = prevTop;
				}
			}
		}

		/**
		 * @param {{preserveScroll?:boolean,followNewest?:boolean,resetScroll?:boolean}} [opts]
		 * preserveScroll — 展开/筛选项保持视口，避免跳到底部
		 * followNewest — 刷新后贴最新（列表新在上 → scrollTop=0）
		 * resetScroll — 翻页时回到顶部
		 */
		function render(opts) {
			opts = opts || {};
			if (serverPaging) {
				renderServer(opts);
				return;
			}
			var bodyEl = $body.length ? $body[0] : null;
			var prevTop = bodyEl ? bodyEl.scrollTop : 0;

			applyFilter();
			var rows = pageSlice();
			var total = state.filtered.length;
			var totalPages = Math.max(1, Math.ceil(total / pageSize) || 1);

			$visible.text(rows.length);
			$total.text(total);
			$pageLabel.text(
				str('pageOf', '第 %1$s / %2$s 页')
					.replace('%1$s', String(state.page))
					.replace('%2$s', String(totalPages))
			);
			$prev.prop('disabled', state.page <= 1 || total === 0);
			$next.prop('disabled', state.page >= totalPages || total === 0);

			if (!total) {
				$stream.html(
					'<div class="pili-log-viewer-placeholder">' +
						escapeHtml(emptyText || str('empty', '暂无运行日志。')) +
						'</div>'
				);
				$field.trigger('pili:log_viewer:change', [
					{ page: 0, total: 0, visible: 0, entries: [] }
				]);
				return;
			}

			var html = '';
			rows.forEach(function (entry) {
				html += renderRow(entry);
			});
			$stream.html(html);

			if (bodyEl) {
				if (opts.preserveScroll) {
					bodyEl.scrollTop = prevTop;
				} else if (opts.followNewest && state.autoscroll) {
					// 新日志在列表顶部，不是底部。
					bodyEl.scrollTop = 0;
				} else if (opts.resetScroll) {
					bodyEl.scrollTop = 0;
				} else if (opts.preserveScroll !== false) {
					// 默认保持当前位置，禁止再滚到 scrollHeight。
					bodyEl.scrollTop = prevTop;
				}
			}

			$field.trigger('pili:log_viewer:change', [
				{
					page: state.page,
					total: total,
					visible: rows.length,
					entries: rows
				}
			]);
		}

		function findEntryById(id) {
			id = String(id || '');
			var list = state.filtered.length ? state.filtered : state.entries;
			for (var i = 0; i < list.length; i++) {
				if (String(list[i].id || '') === id) {
					return list[i];
				}
			}
			return null;
		}

		function setEntries(entries) {
			state.entries = Array.isArray(entries) ? entries.slice() : [];
			state.expanded = {};
			state.page = 1;
			render({ followNewest: true });
		}

		function clearEntries() {
			setEntries([]);
		}

		function goToPage(page) {
			var val = parseInt(page, 10);
			if (isNaN(val) || val < 1) {
				val = 1;
			}
			if (serverPaging) {
				requestPage(val, false);
				return;
			}
			state.page = val;
			render({ resetScroll: true });
		}

		function entriesAsText() {
			return state.filtered
				.map(function (entry) {
					var line =
						'[' +
						(entry.time || '-') +
						'] [' +
						(entry.level || 'info').toUpperCase() +
						'] [' +
						channelLabel(channelsMap, entry) +
						'] ' +
						(entry.message || '');
					if (entry.context) {
						line += '\n  ' + String(entry.context).replace(/\n/g, '\n  ');
					}
					return line;
				})
				.join('\n');
		}

		$field.on('click', '.pili-log-dd-trigger', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $dd = $(this).closest('.pili-log-dd');
			setDdOpen($dd, !$dd.hasClass('is-open'));
		});

		$field.on('click', '.pili-log-dd-option', function (e) {
			e.preventDefault();
			e.stopPropagation();
			toggleOption($(this));
		});

		$field.on('keydown', '.pili-log-dd-option', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				toggleOption($(this));
			}
		});

		$field.on('click', '.pili-log-dd-all', function (e) {
			e.preventDefault();
			e.stopPropagation();
			setAllOptions($(this).closest('.pili-log-dd'), true);
		});

		$field.on('click', '.pili-log-dd-clear', function (e) {
			e.preventDefault();
			e.stopPropagation();
			setAllOptions($(this).closest('.pili-log-dd'), false);
		});

		$field.on('click', '.pili-log-dd-tag-x', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var val = String($(this).attr('data-value') || '');
			var $dd = $(this).closest('.pili-log-dd');
			if (!val) {
				return;
			}
			$dd.find('.pili-log-dd-option[data-value="' + val + '"]').attr('aria-selected', 'false');
			syncDdFromDom();
			state.page = 1;
			if (serverPaging) {
				requestPage(1, false);
				return;
			}
			render();
		});

		$field.on('input', '.pili-log-dd-filter', function (e) {
			e.stopPropagation();
			filterDdOptions($(this).closest('.pili-log-dd'), $(this).val());
		});

		$field.on('click', '.pili-log-dd-panel', function (e) {
			e.stopPropagation();
		});

		var searchTimer = null;
		$search.on('input', function () {
			var val = $(this).val() || '';
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(function () {
				state.query = val;
				state.page = 1;
				if (serverPaging) {
					requestPage(1, false);
					return;
				}
				render();
			}, 180);
		});

		$switch.on('click', function (e) {
			e.preventDefault();
			var on = !$(this).hasClass('is-on');
			$(this).toggleClass('is-on', on).attr('aria-checked', on ? 'true' : 'false');
			state.autoscroll = on;
		});

		$prev.on('click', function () {
			if (state.page > 1) {
				goToPage(state.page - 1);
			}
		});

		$next.on('click', function () {
			var totalPages = serverPaging
				? state.serverPages || 1
				: Math.max(1, Math.ceil(state.filtered.length / pageSize) || 1);
			if (state.page < totalPages) {
				goToPage(state.page + 1);
			}
		});

		$field.on('click', '.pili-log-viewer-context-toggle', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var id = String($(this).data('id') || '');
			if (!id) {
				return;
			}
			state.expanded[id] = !state.expanded[id];
			// 只替换当前行，避免整表重绘把滚动条甩到底部。
			var entry = findEntryById(id);
			var $row = $(this).closest('.pili-log-viewer-row');
			if (entry && $row.length) {
				var $next = $(renderRow(entry));
				$row.replaceWith($next);
				return;
			}
			render({ preserveScroll: true });
		});

		function postAjax(action, extra) {
			var ajaxUrl = String($field.attr('data-ajax-url') || window.ajaxurl || '');
			var nonce = String($field.attr('data-ajax-nonce') || '');
			if (!ajaxUrl || !action) {
				return $.Deferred().reject(new Error('missing ajax')).promise();
			}
			var data = $.extend(
				{
					action: action,
					nonce: nonce
				},
				extra || {}
			);
			return $.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: data
			});
		}

		function applyEntriesFromResponse(json) {
			if (serverPaging && json && json.data) {
				applyServerPayload(json.data, { followNewest: true });
				return json;
			}
			var entries =
				json && json.data && $.isArray(json.data.entries)
					? json.data.entries
					: [];
			setEntries(entries);
			return json;
		}

		function buildListExtra(page) {
			var extra = buildExportExtra();
			delete extra.as_json;
			delete extra.time_from;
			delete extra.time_to;
			extra.page = page;
			extra.page_size = pageSize;
			return extra;
		}

		function paintLiveCount(total) {
			var $live = $('#pilidoc-log-files-live-count, #pilipost-logs-live-count');
			if (!$live.length) {
				return;
			}
			var tpl = String($live.attr('data-tpl') || '当前 %d 条。');
			$live.text(tpl.replace('%d', String(total)));
		}

		function applyServerPayload(data, opts) {
			data = data || {};
			state.entries = $.isArray(data.entries) ? data.entries : [];
			state.filtered = state.entries.slice();
			state.page = parseInt(data.page, 10) || 1;
			state.serverTotal = parseInt(data.total, 10);
			if (isNaN(state.serverTotal)) {
				state.serverTotal = parseInt(data.count, 10) || state.entries.length;
			}
			state.serverPages = parseInt(data.pages, 10) || 1;
			paintLiveCount(state.serverTotal);
			render(opts || {});
		}

		function requestPage(page, toastOk) {
			if (!serverPaging || pagingBusy) {
				return;
			}
			var action = String($field.attr('data-refresh-action') || '');
			if (!action) {
				return;
			}
			pagingBusy = true;
			postAjax(action, buildListExtra(page))
				.done(function (json) {
					if (!json || !json.success) {
						showToast(
							(json && json.data && json.data.message) || str('refreshFail', '刷新失败'),
							'error'
						);
						return;
					}
					applyServerPayload(json.data, {
						followNewest: page <= 1,
						resetScroll: page > 1
					});
					if (toastOk) {
						showToast(
							(json.data && json.data.message) || str('refreshOk', '已刷新'),
							'success'
						);
					}
				})
				.fail(function () {
					showToast(str('refreshFail', '刷新失败'), 'error');
				})
				.always(function () {
					pagingBusy = false;
				});
		}

		$field.find('.pili-log-viewer-refresh').on('click', function () {
			var $btn = $(this);
			var action = String($field.attr('data-refresh-action') || '');
			$field.trigger('pili:log_viewer:refresh');
			if (!action) {
				render();
				showToast(str('localOnly', '未配置服务端动作，仅清空当前页面列表'), 'warning');
				return;
			}
			if ($btn.prop('disabled')) {
				return;
			}
			$btn.prop('disabled', true);
			if (serverPaging) {
				pagingBusy = false;
				requestPage(1, true);
				window.setTimeout(function () {
					$btn.prop('disabled', false);
				}, 400);
				return;
			}
			showToast(str('refreshing', '刷新中'), 'bounce');
			postAjax(action)
				.done(function (json) {
					if (!json || !json.success) {
						var msg =
							(json && json.data && json.data.message) ||
							str('refreshFail', '刷新失败');
						showToast(msg, 'error');
						return;
					}
					applyEntriesFromResponse(json);
					showToast(
						(json.data && json.data.message) || str('refreshOk', '已刷新'),
						'success'
					);
				})
				.fail(function () {
					showToast(str('refreshFail', '刷新失败'), 'error');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		$field.find('.pili-log-viewer-clear').on('click', function () {
			var $btn = $(this);
			var action = String($field.attr('data-clear-action') || '');
			$field.trigger('pili:log_viewer:clear');
			if (!action) {
				clearEntries();
				showToast(str('localOnly', '未配置服务端动作，仅清空当前页面列表'), 'warning');
				return;
			}
			if ($btn.prop('disabled')) {
				return;
			}
			var clearOpts = {
							title: str('clearTitle', '清空日志'),
							message: str('clearConfirm', '确定从列表隐藏近 7 天可见条目？JSONL 文件仍保留，可用下方文件表整文件删除。'),
							type: 'warning',
							confirmText: str('clearOkBtn', '清空'),
							cancelText: str('cancel', '取消')
					  };
			var ask =
				window.PILI && typeof window.PILI.confirm === 'function'
					? window.PILI.confirm(clearOpts)
					: window.PilipostUi && typeof window.PilipostUi.confirm === 'function'
					? window.PilipostUi.confirm(clearOpts.message, clearOpts)
					: typeof window.xunConfirm === 'function'
						? window.xunConfirm(clearOpts)
						: Promise.resolve(false);
			Promise.resolve(ask).then(function (ok) {
				if (!ok) {
					return;
				}
				$btn.prop('disabled', true);
				showToast(str('clearing', '清空中'), 'bounce');
				postAjax(action)
					.done(function (json) {
						if (!json || !json.success) {
							var msg =
								(json && json.data && json.data.message) ||
								str('clearFail', '清空失败');
							showToast(msg, 'error');
							return;
						}
						if (json.data && $.isArray(json.data.entries)) {
							applyEntriesFromResponse(json);
						} else {
							clearEntries();
						}
						showToast(
							(json.data && json.data.message) || str('clearOk', '已清空日志'),
							'success'
						);
					})
					.fail(function () {
						showToast(str('clearFail', '清空失败'), 'error');
					})
					.always(function () {
						$btn.prop('disabled', false);
					});
			});
		});

		$field.find('.pili-log-viewer-copy').on('click', function () {
			var text = entriesAsText();
			if (!text) {
				showToast(emptyText, 'info');
				return;
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(
					function () {
						showToast(str('copied', '已复制到剪贴板'), 'success');
					},
					function () {
						showToast(str('copyFail', '复制失败'), 'error');
					}
				);
				return;
			}
			var $tmp = $('<textarea>')
				.css({ position: 'fixed', left: '-9999px' })
				.val(text)
				.appendTo('body');
			$tmp.trigger('select');
			try {
				document.execCommand('copy');
				showToast(str('copied', '已复制到剪贴板'), 'success');
			} catch (err) {
				showToast(str('copyFail', '复制失败'), 'error');
			}
			$tmp.remove();
		});

		function downloadTextFile(filename, content) {
			var blob = new Blob([content || ''], { type: 'text/plain;charset=utf-8' });
			var url = window.URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = filename || 'pilipost-runtime.log';
			document.body.appendChild(a);
			a.click();
			a.remove();
			window.setTimeout(function () {
				window.URL.revokeObjectURL(url);
			}, 800);
		}

		function pad2(n) {
			return (n < 10 ? '0' : '') + n;
		}

		function toDatetimeLocalValue(date) {
			if (!(date instanceof Date) || isNaN(date.getTime())) {
				return '';
			}
			return (
				date.getFullYear() +
				'-' +
				pad2(date.getMonth() + 1) +
				'-' +
				pad2(date.getDate()) +
				'T' +
				pad2(date.getHours()) +
				':' +
				pad2(date.getMinutes())
			);
		}

		function parseDatetimeLocal(value) {
			var raw = String(value || '').trim();
			if (!raw) {
				return null;
			}
			// datetime-local → treat as local wall time.
			var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/);
			if (!m) {
				var d = new Date(raw);
				return isNaN(d.getTime()) ? null : d;
			}
			return new Date(
				parseInt(m[1], 10),
				parseInt(m[2], 10) - 1,
				parseInt(m[3], 10),
				parseInt(m[4], 10),
				parseInt(m[5], 10),
				m[6] ? parseInt(m[6], 10) : 0,
				0
			);
		}

		function entryTs(entry) {
			if (entry && entry.ts) {
				var n = parseInt(entry.ts, 10);
				if (!isNaN(n) && n > 0) {
					// 兼容误传毫秒。
					return n > 1e12 ? Math.floor(n / 1000) : n;
				}
			}
			if (entry && entry.time) {
				var raw = String(entry.time);
				var d = parseDatetimeLocal(raw.indexOf('T') !== -1 ? raw.slice(0, 16) : raw.replace(' ', 'T').slice(0, 16));
				if (!d) {
					d = new Date(raw);
				}
				if (d && !isNaN(d.getTime())) {
					return Math.floor(d.getTime() / 1000);
				}
			}
			return 0;
		}

		function readExportRange() {
			var $panel = $field.find('.pili-log-viewer-export-panel');
			return {
				from: String($panel.find('.pili-log-viewer-export-from').val() || '').trim(),
				to: String($panel.find('.pili-log-viewer-export-to').val() || '').trim()
			};
		}

		function setExportRange(fromVal, toVal) {
			var $panel = $field.find('.pili-log-viewer-export-panel');
			$panel.find('.pili-log-viewer-export-from').val(fromVal || '');
			$panel.find('.pili-log-viewer-export-to').val(toVal || '');
		}

		function applyExportPreset(range) {
			var now = new Date();
			now.setSeconds(0, 0);
			if (range === 'all') {
				setExportRange('', '');
				return;
			}
			var from = new Date(now.getTime());
			if (range === '1h') {
				from = new Date(now.getTime() - 60 * 60 * 1000);
			} else if (range === '24h') {
				from = new Date(now.getTime() - 24 * 60 * 60 * 1000);
			} else if (range === '7d') {
				from = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
			}
			setExportRange(toDatetimeLocalValue(from), toDatetimeLocalValue(now));
		}

		function filterEntriesByRange(entries, fromVal, toVal) {
			var fromDate = parseDatetimeLocal(fromVal);
			var toDate = parseDatetimeLocal(toVal);
			var fromTs = fromDate ? Math.floor(fromDate.getTime() / 1000) : null;
			var toTs = null;
			if (toDate) {
				// 结束到所选分钟的最后一秒。
				toTs = Math.floor(toDate.getTime() / 1000) + 59;
			}
			return (entries || []).filter(function (entry) {
				var ts = entryTs(entry);
				if (ts < 1) {
					return false;
				}
				if (fromTs !== null && ts < fromTs) {
					return false;
				}
				if (toTs !== null && ts > toTs) {
					return false;
				}
				return true;
			});
		}

		function entriesAsTextFor(list) {
			return (list || [])
				.map(function (entry) {
					var line =
						'[' +
						(entry.time || '-') +
						'] [' +
						(entry.level || 'info').toUpperCase() +
						'] [' +
						channelLabel(channelsMap, entry) +
						'] ' +
						(entry.message || '');
					if (entry.context) {
						line += '\n  ' + String(entry.context).replace(/\n/g, '\n  ');
					}
					return line;
				})
				.join('\n');
		}

		function showExportPanel(show) {
			var $panel = $field.find('.pili-log-viewer-export-panel');
			if (!$panel.length) {
				return false;
			}
			if (show) {
				$panel.prop('hidden', false);
			} else {
				$panel.prop('hidden', true);
			}
			return true;
		}

		function buildExportExtra() {
			var extra = { as_json: 1 };
			var levelTotal = $levelDd.find('.pili-log-dd-option').length;
			var channelTotal = $channelDd.find('.pili-log-dd-option').length;
			var levels = state.levels || [];
			var channels = state.channels || [];
			if (levels.length > 0 && levels.length < levelTotal) {
				levels.forEach(function (lv, idx) {
					extra['levels[' + idx + ']'] = lv;
				});
			}
			if (channels.length > 0 && channels.length < channelTotal) {
				channels.forEach(function (ch, idx) {
					extra['channels[' + idx + ']'] = ch;
				});
			}
			var q = String(state.query || '').trim();
			if (q) {
				extra.q = q;
			}
			var range = readExportRange();
			if (range.from) {
				extra.time_from = range.from;
			}
			if (range.to) {
				extra.time_to = range.to;
			}
			return extra;
		}

		function runExport($btn) {
			var action = String($field.attr('data-export-action') || '');
			var range = readExportRange();
			var fromDate = parseDatetimeLocal(range.from);
			var toDate = parseDatetimeLocal(range.to);
			if (fromDate && toDate && fromDate.getTime() > toDate.getTime()) {
				showToast(str('exportRangeInvalid', '开始时间不能晚于结束时间'), 'warning');
				return;
			}

			$field.trigger('pili:log_viewer:export');
			if ($btn && $btn.prop('disabled')) {
				return;
			}

			// 无服务端动作时：按时间段过滤当前筛选结果后导出。
			if (!action) {
				var localList = filterEntriesByRange(state.filtered, range.from, range.to);
				var localText = entriesAsTextFor(localList);
				if (!localText) {
					showToast(str('exportEmptyRange', '该时间段内没有可导出的日志'), 'warning');
					return;
				}
				var stamp = new Date();
				var localName =
					'pilipost-runtime-' +
					stamp.getFullYear() +
					pad2(stamp.getMonth() + 1) +
					pad2(stamp.getDate()) +
					'-' +
					pad2(stamp.getHours()) +
					pad2(stamp.getMinutes()) +
					pad2(stamp.getSeconds()) +
					'.log';
				downloadTextFile(localName, localText + '\n');
				showToast(str('exported', '已开始下载日志文件'), 'success');
				showExportPanel(false);
				return;
			}

			if ($btn) {
				$btn.prop('disabled', true);
			}
			showToast(str('exporting', '导出中'), 'bounce');
			postAjax(action, buildExportExtra())
				.done(function (json) {
					if (!json || !json.success || !json.data || typeof json.data.content !== 'string') {
						var msg =
							(json && json.data && json.data.message) ||
							str('exportFail', '导出失败');
						showToast(msg, 'error');
						return;
					}
					if (json.data.count === 0) {
						showToast(str('exportEmptyRange', '该时间段内没有可导出的日志'), 'warning');
						return;
					}
					downloadTextFile(
						json.data.filename || 'pilipost-runtime.log',
						json.data.content
					);
					showToast(
						(json.data && json.data.message) || str('exported', '已开始下载日志文件'),
						'success'
					);
					showExportPanel(false);
				})
				.fail(function () {
					showToast(str('exportFail', '导出失败'), 'error');
				})
				.always(function () {
					if ($btn) {
						$btn.prop('disabled', false);
					}
				});
		}

		$field.find('.pili-log-viewer-export').on('click', function (e) {
			e.preventDefault();
			var $panel = $field.find('.pili-log-viewer-export-panel');
			if (!$panel.length) {
				runExport($(this));
				return;
			}
			var willShow = $panel.prop('hidden');
			showExportPanel(willShow);
			if (willShow && !$panel.find('.pili-log-viewer-export-from').val() && !$panel.find('.pili-log-viewer-export-to').val()) {
				applyExportPreset('24h');
			}
		});

		$field.on('click', '.pili-log-viewer-export-preset', function (e) {
			e.preventDefault();
			applyExportPreset(String($(this).attr('data-range') || 'all'));
		});

		$field.on('click', '.pili-log-viewer-export-cancel', function (e) {
			e.preventDefault();
			showExportPanel(false);
		});

		$field.on('click', '.pili-log-viewer-export-go', function (e) {
			e.preventDefault();
			runExport($(this));
		});

		$field.data('piliLogViewerSetEntries', setEntries);
		$field.data('piliLogViewerClear', clearEntries);
		$field.data('piliLogViewerGoToPage', goToPage);
		$field.data('piliLogViewerGetFiltered', function () {
			return state.filtered.slice();
		});
		$field.data('piliLogViewerExport', function () {
			var $panel = $field.find('.pili-log-viewer-export-panel');
			if ($panel.length) {
				showExportPanel(true);
				if (!$panel.find('.pili-log-viewer-export-from').val() && !$panel.find('.pili-log-viewer-export-to').val()) {
					applyExportPreset('24h');
				}
				return;
			}
			$field.find('.pili-log-viewer-export').trigger('click');
		});

		syncDdFromDom();
		state.autoscroll = $switch.hasClass('is-on');
		state.serverTotal = 0;
		state.serverPages = 1;
		if (serverPaging) {
			state.entries = [];
			state.filtered = [];
			requestPage(1, false);
		} else {
			setEntries(parseJsonAttr($field, 'data-entries', []));
		}
	}

	function initLogViewer($root) {
		($root && $root.length ? $root : $(document)).find('.pili-log-viewer-field, .xun-log-viewer-field').each(function () {
			initOne($(this));
		});
	}

	window.piliLogViewerFieldBoot = function ($root) {
		initLogViewer($root && $root.length ? $root : $(document));
	};

	function resolveField(target) {
		var $field = target instanceof $ ? target : $(target);
		if (!$field.is('.pili-log-viewer-field, .xun-log-viewer-field')) {
			$field = $field.closest('.pili-log-viewer-field, .xun-log-viewer-field');
		}
		return $field;
	}

	$(document).ready(function () {
		initLogViewer($(document));
	});

	$(document).on('pili:field:added xun:field:added', function (e, $container) {
		window.piliLogViewerFieldBoot($container && $container.length ? $container : $(document));
	});

	$(document).on('click', function () {
		closeAllDropdowns(null);
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			closeAllDropdowns(null);
		}
	});

	window.PiliXunLogViewer = window.PiliLogViewer = {
		setEntries: function (target, entries) {
			var $field = resolveField(target);
			if (!$field.length) {
				return;
			}
			var fn = $field.data('piliLogViewerSetEntries');
			if (typeof fn === 'function') {
				fn(entries);
			} else {
				$field.attr('data-entries', JSON.stringify(entries || []));
				initOne($field);
			}
		},
		clear: function (target) {
			var $field = resolveField(target);
			var fn = $field.data('piliLogViewerClear');
			if (typeof fn === 'function') {
				fn();
			}
		},
		goToPage: function (target, page) {
			var $field = resolveField(target);
			var fn = $field.data('piliLogViewerGoToPage');
			if (typeof fn === 'function') {
				fn(page);
			}
		},
		getFilteredEntries: function (target) {
			var $field = resolveField(target);
			var fn = $field.data('piliLogViewerGetFiltered');
			return typeof fn === 'function' ? fn() : [];
		}
	};

})(jQuery, window, document);

	window.xunLogViewerFieldBoot = window.piliLogViewerFieldBoot;
