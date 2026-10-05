/**
 * clean_toolbar — ring + stats + filters, no DELETE.
 */
(function ($, window) {
	'use strict';

	var RING = 2 * Math.PI * 50;

	function i18n(key, fallback) {
		var bag = window.piliCleanToolbarI18n || {};
		return bag[key] || fallback;
	}

	function findTree($toolbar) {
		var id = $toolbar.attr('data-tree-id') || '';
		if (!id) {
			return $();
		}
		var $page = $toolbar.closest('.pili-framework-page, .pili-options, form');
		if (!$page.length) {
			return $();
		}
		return $page.find('.pili-clean-tree[data-field-id="' + id + '"]').first();
	}

	function setStat($toolbar, key, value) {
		$toolbar.find('[data-stat="' + key + '"] [data-stat-value]').text(String(value));
	}

	function setGauge($toolbar, selected, total) {
		var pct = total > 0 ? selected / total : 0;
		$toolbar.find('[data-clean-arc]').css('stroke-dashoffset', String(RING * (1 - pct)));
		setStat($toolbar, 'selected', selected);
		setStat($toolbar, 'items', total);
	}

	function syncFromTree($toolbar, $tree) {
		if (!$tree.length) {
			setGauge($toolbar, 0, 0);
			return;
		}
		var $items = $tree.find('.pili-clean-tree__check--item');
		setGauge($toolbar, $items.filter(':checked').length, $items.length);
	}

	function showProgress($toolbar, pct, text) {
		var $p = $toolbar.find('[data-clean-progress]');
		$p.prop('hidden', false);
		$toolbar.find('[data-clean-bar]').css('width', pct + '%');
		$toolbar.find('[data-clean-status]').text(text);
	}

	function bindTree($tb) {
		var $tree = findTree($tb);
		if (!$tree.length) {
			return;
		}
		$tree.off('pili:clean-tree:change.piliToolbar');
		$tree.on('pili:clean-tree:change.piliToolbar', function () {
			syncFromTree($tb, $tree);
		});
		$tree.off('pili:clean-tree:run-one.piliToolbar');
		$tree.on('pili:clean-tree:run-one.piliToolbar', function (e, item) {
			var name = item && item.title ? item.title : '';
			showProgress($tb, 70, i18n('runOne', '') + name);
		});
		syncFromTree($tb, $tree);
	}

	function initToolbar($root) {
		if (!$root || !$root.length) {
			return;
		}
		$root.find('.pili-clean-toolbar').addBack('.pili-clean-toolbar').each(function () {
			var $tb = $(this);
			if ($tb.data('piliCleanToolbarInited')) {
				bindTree($tb);
				return;
			}
			$tb.data('piliCleanToolbarInited', true);
			$tb.find('[data-clean-arc]').css('stroke-dasharray', String(RING));
			bindTree($tb);
			window.setTimeout(function () {
				bindTree($tb);
			}, 0);

			$tb.on('click', '[data-clean-act]', function (e) {
				e.preventDefault();
				var btn = this;
				var act = btn.getAttribute('data-clean-act');
				var $t = findTree($tb);
				if (act === 'scan') {
					btn.disabled = true;
					showProgress($tb, 40, i18n('scanPending', ''));
					window.setTimeout(function () {
						btn.disabled = false;
						btn.setAttribute('data-clean-act', 'run');
						btn.textContent = btn.getAttribute('data-label-run') || '';
						if (btn.getAttribute('data-pili-table-action-label') !== null) {
							btn.setAttribute('data-pili-table-action-label', btn.textContent);
						}
						showProgress($tb, 100, i18n('scanDone', ''));
					}, 400);
					return;
				}
				if (act === 'run') {
					var n = $t.find('.pili-clean-tree__check--item:checked').length;
					if (!n) {
						showProgress($tb, 0, i18n('needSelect', ''));
						return;
					}
					showProgress($tb, 70, i18n('runPending', ''));
				}
			});
		});
	}

	if (window.PILI && typeof PILI.registerBoot === 'function') {
		PILI.registerBoot('clean_toolbar', function ($root) {
			initToolbar($root);
		});
	}

	$(document).ready(function () {
		var $scope = $('.pili-framework-page, .pili-options').first();
		if ($scope.length) {
			initToolbar($scope);
		}
	});

	$(document).on((window.PILI && PILI.ev ? PILI.ev('field:added') : 'pili:field:added'), function (e, $container) {
		if ($container && $container.length && window.PILI && typeof PILI.boot === 'function') {
			PILI.boot('clean_toolbar', $container);
		} else if ($container && $container.length) {
			initToolbar($container);
		}
	});
})(jQuery, window);
