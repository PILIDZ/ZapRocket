/**
 * clean_tree — one panel; category caret + tri-state + leaf checks.
 */
(function ($, window) {
	'use strict';

	function itemBoxes($scope) {
		return $scope.find('.pili-clean-tree__check--item');
	}

	function selectedValues($tree) {
		var out = [];
		itemBoxes($tree).filter(':checked').each(function () {
			out.push(this.value);
		});
		return out;
	}

	function emitChange($tree) {
		$tree.trigger('pili:clean-tree:change', [selectedValues($tree)]);
	}

	function syncCat($cat) {
		var $items = itemBoxes($cat);
		var total = $items.length;
		var n = $items.filter(':checked').length;
		var el = $cat.find('.pili-clean-tree__check--cat').get(0);
		if (!el) {
			return;
		}
		el.indeterminate = n > 0 && n < total;
		el.checked = total > 0 && n === total;
	}

	function syncGlobal($tree) {
		var $items = itemBoxes($tree);
		var total = $items.length;
		var n = $items.filter(':checked').length;
		var el = $tree.find('.pili-clean-tree__check--global').get(0);
		if (!el) {
			return;
		}
		el.indeterminate = n > 0 && n < total;
		el.checked = total > 0 && n === total;
	}

	function syncAll($tree) {
		$tree.find('.pili-clean-tree__cat').each(function () {
			syncCat($(this));
		});
		syncGlobal($tree);
		emitChange($tree);
	}

	function setOpen($cat, open, $tree) {
		$cat.toggleClass('is-open', open);
		$cat.find('[data-clean-toggle]').attr('aria-expanded', open ? 'true' : 'false');
		$cat.children('.pili-clean-tree__leaves').prop('hidden', !open);
		var fold = $tree.attr('data-label-fold') || '';
		var show = $tree.attr('data-label-open') || '';
		$cat.find('.pili-clean-tree__col--action [data-clean-toggle]').text(open ? fold : show);
	}

	function initTree($root) {
		if (!$root || !$root.length) {
			return;
		}
		$root.find('.pili-clean-tree').addBack('.pili-clean-tree').each(function () {
			var $tree = $(this);
			if ($tree.data('piliCleanTreeInited')) {
				return;
			}
			$tree.data('piliCleanTreeInited', true);

			$tree.on('click', '[data-clean-toggle]', function (e) {
				e.preventDefault();
				var $cat = $(this).closest('.pili-clean-tree__cat');
				setOpen($cat, !$cat.hasClass('is-open'), $tree);
			});

			$tree.on('click', '[data-clean-one]', function (e) {
				e.preventDefault();
				var $leaf = $(this).closest('.pili-clean-tree__leaf');
				var id = this.getAttribute('data-clean-one') || '';
				var title = $.trim($leaf.find('.pili-clean-tree__title').text());
				$tree.trigger('pili:clean-tree:run-one', [{ id: id, title: title }]);
			});

			$tree.on('change', '.pili-clean-tree__check--item', function () {
				syncAll($tree);
			});

			$tree.on('change', '.pili-clean-tree__check--cat', function () {
				var on = this.checked;
				itemBoxes($(this).closest('.pili-clean-tree__cat')).prop('checked', on);
				syncAll($tree);
			});

			$tree.on('change', '.pili-clean-tree__check--global', function () {
				itemBoxes($tree).prop('checked', this.checked);
				syncAll($tree);
			});

			$tree.on('pili:clean-tree:select-safe', function () {
				itemBoxes($tree).each(function () {
					this.checked = this.getAttribute('data-risk') !== 'caution';
				});
				syncAll($tree);
			});

			$tree.on('pili:clean-tree:select-none', function () {
				itemBoxes($tree).prop('checked', false);
				syncAll($tree);
			});

			$tree.on('pili:clean-tree:select-all', function () {
				itemBoxes($tree).prop('checked', true);
				syncAll($tree);
			});

			$tree.on('pili:clean-tree:fold-all', function (e, folded) {
				$tree.find('.pili-clean-tree__cat').each(function () {
					setOpen($(this), !folded, $tree);
				});
			});

			$tree.on('click', '.pili-clean-tree__tools [data-clean-act]', function (e) {
				e.preventDefault();
				var act = this.getAttribute('data-clean-act');
				if (act === 'all') {
					$tree.trigger('pili:clean-tree:select-all');
					return;
				}
				if (act === 'safe') {
					$tree.trigger('pili:clean-tree:select-safe');
					return;
				}
				if (act === 'none') {
					$tree.trigger('pili:clean-tree:select-none');
					return;
				}
				if (act === 'fold') {
					var folded = $tree.data('piliCleanFolded') ? false : true;
					$tree.data('piliCleanFolded', folded);
					$tree.trigger('pili:clean-tree:fold-all', [folded]);
					this.textContent = folded
						? (this.getAttribute('data-clean-open-label') || '')
						: (this.getAttribute('data-clean-fold-label') || '');
				}
			});

			syncAll($tree);
		});
	}

	if (window.PILI && typeof PILI.registerBoot === 'function') {
		PILI.registerBoot('clean_tree', function ($root) {
			initTree($root);
		});
	}

	$(document).ready(function () {
		var $scope = $('.pili-framework-page, .pili-options').first();
		if ($scope.length) {
			initTree($scope);
		}
	});

	$(document).on((window.PILI && PILI.ev ? PILI.ev('field:added') : 'pili:field:added'), function (e, $container) {
		if ($container && $container.length && window.PILI && typeof PILI.boot === 'function') {
			PILI.boot('clean_tree', $container);
		} else if ($container && $container.length) {
			initTree($container);
		}
	});
})(jQuery, window);
