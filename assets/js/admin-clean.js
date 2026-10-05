/**
 * ZapRocket db clean: intercept scan/run (capture, skip placeholder toolbar).
 * i18n: 文案来自 zaprocketClean.i18n（PHP pili__()），不要在此硬写中英文。
 */
(function ($, window) {
	'use strict';

	var cfg = window.zaprocketClean || {};
	var busy = false;

	function getCfg() {
		return window.zaprocketClean || cfg || {};
	}

	function i18n(key) {
		var bag = getCfg().i18n || {};
		return bag[key] || '';
	}

	function countLabel(n) {
		var t = i18n('countFmt') || '%d 条';
		return String(t).replace('%d', String(n));
	}

	function setLoading(on) {
		var text = i18n('sizing') || '…';
		$toolbar()
			.find('[data-stat-loading]')
			.prop('hidden', !on);
		if (on) {
			$toolbar().find('[data-stat="size"] [data-stat-value]').text(text);
			$tree().find('[data-item-count], [data-item-size], [data-cat-count], [data-cat-size]').text(text);
		}
	}

	function fillScan(data) {
		var items = (data && data.items) || {};
		var timedOut = !!(data && data.timed_out);
		var timeoutMsg = (data && data.size_label) || i18n('sizeTimeout');
		$tree()
			.find('.pili-clean-tree__check--item')
			.each(function () {
				var id = this.value;
				var row = items[id] || {};
				var $leaf = $(this).closest('.pili-clean-tree__leaf');
				var count = row.count != null ? parseInt(row.count, 10) : 0;
				if (isNaN(count)) {
					count = 0;
				}
				$leaf.find('[data-item-count]').text(row.label || countLabel(count));
				$leaf.find('[data-item-size]').text(
					timedOut && !row.size_ok ? timeoutMsg : row.size_label || '—'
				);
				$leaf.attr('data-bytes', String(row.bytes || 0));
				$leaf.attr('data-count', String(count));
			});
		$tree()
			.find('.pili-clean-tree__cat')
			.each(function () {
				var $cat = $(this);
				var n = 0;
				var bytes = 0;
				$cat.find('.pili-clean-tree__leaf').each(function () {
					n += parseInt($(this).attr('data-count') || '0', 10) || 0;
					bytes += parseInt($(this).attr('data-bytes') || '0', 10) || 0;
				});
				$cat.find('[data-cat-count]').text(countLabel(n));
				$cat.find('[data-cat-size]').text(timedOut ? timeoutMsg : sizeFromBytes(bytes, data));
			});
		var total = data && data.total_count ? data.total_count : 0;
		var sizeText = timedOut ? timeoutMsg : (data && data.size_label ? data.size_label : '—');
		$toolbar().find('[data-clean-found]').text(String(total));
		$toolbar().find('[data-stat="size"] [data-stat-value]').text(sizeText);
		if (data && data.size_note) {
			$toolbar().find('[data-stat="size"] [data-stat-hint]').text(String(data.size_note));
		}
	}

	function fillEmpty() {
		setLoading(false);
		$tree().find('[data-item-count], [data-item-size], [data-cat-count], [data-cat-size]').text('—');
		$tree().find('.pili-clean-tree__leaf').attr('data-bytes', '0').attr('data-count', '0');
		$toolbar().find('[data-clean-found]').text('0');
		$toolbar().find('[data-stat="size"] [data-stat-value]').text('—');
		$toolbar().find('[data-stat="size"] [data-stat-hint]').text(i18n('emptyScan'));
	}

	function restoreScan() {
		var cache = getCfg().scanCache;
		if (cache && cache.items && Object.keys(cache.items).length) {
			fillScan(cache);
			return true;
		}
		return false;
	}

	function sizeFromBytes(bytes, data) {
		if (data && data.timed_out) {
			return data.size_label || i18n('sizeTimeout');
		}
		bytes = parseInt(bytes, 10) || 0;
		if (bytes < 1024) {
			return bytes + ' B';
		}
		if (bytes < 1048576) {
			return (bytes / 1024).toFixed(1) + ' KB';
		}
		if (bytes < 1073741824) {
			return (bytes / 1048576).toFixed(1) + ' MB';
		}
		return (bytes / 1073741824).toFixed(2) + ' GB';
	}

	function post(action, extra, timeoutMs) {
		return $.ajax({
			url: getCfg().ajax,
			method: 'POST',
			timeout: timeoutMs || 0,
			data: $.extend(
				{
					action: action,
					nonce: getCfg().nonce || '',
				},
				extra || {}
			),
		});
	}

	function $page() {
		var $p = $('.pili-inst-zaprocket').first();
		return $p.length ? $p : $('.pili-framework-page, .pili-options').first();
	}

	function $toolbar() {
		var $tb = $page().find('.pili-clean-toolbar').first();
		return $tb.length ? $tb : $('.pili-clean-toolbar').first();
	}

	function $tree() {
		var id = $toolbar().attr('data-tree-id') || 'zr_db_clean_items';
		return $page().find('.pili-clean-tree[data-field-id="' + id + '"]').first();
	}

	function selected() {
		var out = [];
		$tree()
			.find('.pili-clean-tree__check--item:checked')
			.each(function () {
				out.push(this.value);
			});
		return out;
	}

	function progress(pct, text) {
		var $tb = $toolbar();
		$tb.find('[data-clean-progress]').prop('hidden', false);
		$tb.find('[data-clean-bar]').css('width', pct + '%');
		$tb.find('[data-clean-status]').text(text || '');
	}

	function onScan($btn) {
		if (busy) {
			return;
		}
		busy = true;
		$btn.prop('disabled', true);
		setLoading(true);
		progress(30, i18n('scanning'));
		var scanItems = [];
		$tree()
			.find('.pili-clean-tree__check--item')
			.each(function () {
				scanItems.push(this.value);
			});
		post(getCfg().preview, { items: scanItems }, 20000)
			.done(function (res) {
				if (!res || !res.success) {
					progress(0, i18n('fail'));
					restoreScan();
					return;
				}
				var data = res.data || {};
				if (data.timed_out) {
					progress(0, i18n('sizeTimeout'));
					restoreScan();
					return;
				}
				getCfg().scanCache = data;
				fillScan(data);
				progress(100, i18n('scanOk'));
			})
			.fail(function (xhr, status) {
				var msg = status === 'timeout' ? i18n('sizeTimeout') : i18n('fail');
				progress(0, msg);
				restoreScan();
			})
			.always(function () {
				setLoading(false);
				busy = false;
				$btn.prop('disabled', false);
			});
	}

	function onRun($btn, one) {
		if (busy) {
			return;
		}
		var items = one ? [one] : selected();
		if (!items.length) {
			progress(0, i18n('needSel'));
			return;
		}
		busy = true;
		$btn.prop('disabled', true);
		progress(50, i18n('running'));
		var extra = one ? { item: one } : { items: items };
		post(getCfg().run, extra)
			.done(function (res) {
				if (!res || !res.success) {
					progress(0, (res && res.data && res.data.message) || i18n('fail'));
					return;
				}
				var d = res.data || {};
				if (d.more) {
					progress(80, i18n('runMore'));
				} else {
					progress(100, i18n('runOk'));
				}
				if (d.cache_cleared || (d.deleted && d.deleted > 0)) {
					getCfg().scanCache = {};
					fillEmpty();
				}
			})
			.fail(function () {
				progress(0, i18n('fail'));
			})
			.always(function () {
				busy = false;
				$btn.prop('disabled', false);
			});
	}

	function onRecommend($btn) {
		if (busy) {
			return;
		}
		busy = true;
		$btn.prop('disabled', true);
		var $st = $page().find('[data-zr-recommend-status]').first();
		$st.text(i18n('recBusy'));
		post(getCfg().recommend, { nonce: getCfg().recNonce || '' })
			.done(function (res) {
				if (!res || !res.success) {
					$st.text((res && res.data && res.data.message) || i18n('fail'));
					return;
				}
				$st.text((res.data && res.data.message) || i18n('recOk'));
			})
			.fail(function () {
				$st.text(i18n('fail'));
			})
			.always(function () {
				busy = false;
				$btn.prop('disabled', false);
			});
	}

	function onPurge($btn) {
		if (busy) {
			return;
		}
		busy = true;
		$btn.prop('disabled', true);
		var $st = $page().find('[data-zr-purge-status]').first();
		$st.text(i18n('purgeBusy'));
		post(getCfg().purge, { nonce: getCfg().purgeNonce || '' })
			.done(function (res) {
				if (!res || !res.success) {
					$st.text((res && res.data && res.data.message) || i18n('fail'));
					return;
				}
				$st.text((res.data && res.data.message) || i18n('purgeOk'));
			})
			.fail(function () {
				$st.text(i18n('fail'));
			})
			.always(function () {
				busy = false;
				$btn.prop('disabled', false);
			});
	}

	document.addEventListener(
		'click',
		function (e) {
			if (!$page().length) {
				return;
			}
			var t = e.target;
			if (!t || !t.closest) {
				return;
			}
			var rec = t.closest('[data-zr-act="recommend"]');
			if (rec && $page()[0].contains(rec)) {
				e.preventDefault();
				e.stopPropagation();
				onRecommend($(rec));
				return;
			}
			var purge = t.closest('[data-zr-act="purge-cache"]');
			if (purge && $page()[0].contains(purge)) {
				e.preventDefault();
				e.stopPropagation();
				onPurge($(purge));
				return;
			}
			var one = t.closest('[data-clean-one]');
			if (one && $page()[0].contains(one)) {
				e.preventDefault();
				e.stopPropagation();
				onRun($(one), one.getAttribute('data-clean-one') || '');
				return;
			}
			var btn = t.closest('[data-clean-act]');
			if (!btn || !$page()[0].contains(btn)) {
				return;
			}
			var act = btn.getAttribute('data-clean-act');
			if (act !== 'scan' && act !== 'run') {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			if (act === 'scan') {
				onScan($(btn));
			} else {
				onRun($(btn), '');
			}
		},
		true
	);

	function bootFromCache() {
		if (!$toolbar().length) {
			return false;
		}
		if (!restoreScan()) {
			return true;
		}
		var shown = $toolbar().find('[data-stat="size"] [data-stat-value]').first().text();
		return shown && shown !== '—' && shown !== '-' && shown !== (i18n('sizing') || '');
	}

	function watchToolbar() {
		if (bootFromCache()) {
			return;
		}
		var tries = 0;
		var timer = window.setInterval(function () {
			tries += 1;
			if (bootFromCache() || tries > 50) {
				window.clearInterval(timer);
			}
		}, 80);
	}

	$(document).on('pili:section:loaded pili:field:added pili:field:loaded', watchToolbar);
	$(document).on((window.PILI && PILI.ev ? PILI.ev('field:added') : 'pili:field:added'), watchToolbar);
	$(window).on('hashchange', function () {
		window.setTimeout(watchToolbar, 0);
	});
	$(document).ready(watchToolbar);
})(jQuery, window);
