/**
 * XUN modal field
 *
 * 全局 API：window.PiliXunModal
 *   - open(targetOrOptions)
 *   - close(target?)
 *   - setBody(target, html)
 *   - setTitle(target, title)
 */
(function ($, window, document) {
	'use strict';

	var cfg = PILI.bag('modal') || {};
	var strings = cfg.strings || {};

	function str(key, fallback) {
		return strings[key] || fallback;
	}
	var OPEN_CLASS = 'is-open';
	var BODY_LOCK = 'pili-modal-open';
	var stack = [];

	function resolveModal(target) {
		if (!target) {
			return $();
		}
		if (target.jquery) {
			return target.hasClass('pili-modal') ? target.first() : target.find('.pili-modal').addBack('.pili-modal').first();
		}
		if (typeof target === 'string') {
			var key = target.replace(/^#/, '');
			var $byId = $('#' + key);
			if ($byId.hasClass('pili-modal')) {
				return $byId.first();
			}
			var $byData = $('.pili-modal[data-field-id="' + key + '"]');
			if ($byData.length) {
				return $byData.first();
			}
			var $bySel = $(target);
			if ($bySel.hasClass('pili-modal')) {
				return $bySel.first();
			}
			return $bySel.find('.pili-modal').addBack('.pili-modal').first();
		}
		return $(target).closest('.pili-modal');
	}

	function lockBody() {
		$('body').addClass(BODY_LOCK);
	}

	function unlockBody() {
		if (!stack.length) {
			$('body').removeClass(BODY_LOCK);
		}
	}

	function bindOne($modal) {
		if (!$modal.length || $modal.data('xunModalBound')) {
			return;
		}
		$modal.data('xunModalBound', true);

		// 仅关闭钮；带 action 的按钮由下方 action 处理（可带 close）。
		$modal.on('click.xunModal', '[data-pili-modal-close]:not([data-pili-modal-action])', function (e) {
			e.preventDefault();
			closeModal($modal, 'close');
		});

		$modal.on('click.xunModal', '[data-pili-modal-mask]', function () {
			if (String($modal.attr('data-close-on-mask') || '1') === '1') {
				closeModal($modal, 'mask');
			}
		});

		$modal.on('click.xunModal', '[data-pili-modal-action]', function (e) {
			e.preventDefault();
			var action = String($(this).attr('data-pili-modal-action') || '');
			var autoClose = $(this).is('[data-pili-modal-close]');
			var handler = $modal.data('xunModalOnAction');
			var api = {
				close: function () {
					closeModal($modal, action || 'action');
				},
				el: $modal.get(0),
				$el: $modal
			};
			if (typeof handler === 'function') {
				handler(action, api);
			}
			$modal.trigger('xun:modal:action', [action, api]);
			if (autoClose) {
				closeModal($modal, action || 'close');
			}
		});
	}

	function openModal($modal, options) {
		options = options || {};
		if (!$modal.length) {
			return null;
		}
		bindOne($modal);

		if (typeof options.title === 'string') {
			$modal.find('.pili-modal__title').first().text(options.title);
		}
		if (typeof options.body === 'string') {
			$modal.find('[data-pili-modal-body]').html(options.body);
		}
		if (typeof options.onAction === 'function') {
			$modal.data('xunModalOnAction', options.onAction);
		}
		if (typeof options.onClose === 'function') {
			$modal.data('xunModalOnClose', options.onClose);
		}

		// 提到 body，避免被后台布局 overflow 裁切。
		if (!$modal.parent().is('body')) {
			$modal.data('xunModalOriginParent', $modal.parent());
			$modal.appendTo('body');
		}

		$modal.prop('hidden', false).attr('aria-hidden', 'false');
		// reflow
		$modal[0].offsetWidth; // eslint-disable-line no-unused-expressions
		$modal.addClass(OPEN_CLASS);

		if (stack.indexOf($modal[0]) === -1) {
			stack.push($modal[0]);
		}
		lockBody();
		$modal.trigger('xun:modal:open');
		return $modal;
	}

	function closeModal($modal, reason) {
		if (!$modal || !$modal.length) {
			return;
		}
		if (!$modal.hasClass(OPEN_CLASS) && $modal.prop('hidden')) {
			return;
		}
		$modal.removeClass(OPEN_CLASS).attr('aria-hidden', 'true');
		window.setTimeout(function () {
			$modal.prop('hidden', true);
			var $origin = $modal.data('xunModalOriginParent');
			if ($origin && $origin.length && $modal.parent().is('body')) {
				$modal.appendTo($origin);
			}
		}, 180);

		stack = stack.filter(function (el) {
			return el !== $modal[0];
		});
		unlockBody();

		var onClose = $modal.data('xunModalOnClose');
		if (typeof onClose === 'function') {
			onClose(reason || 'close');
		}
		$modal.trigger('xun:modal:close', [reason || 'close']);
	}

	function createDynamicModal(options) {
		options = options || {};
		var id = options.id ? String(options.id) : 'pili-modal-dyn-' + Date.now();
		var size = options.size || 'md';
		var title = options.title || '';
		var body = options.body || '';
		var buttons = Array.isArray(options.buttons) ? options.buttons : [];
		var closeOnEsc = options.closeOnEsc !== false;
		var closeOnMask = options.closeOnMask !== false;

		var $existing = $('#' + id);
		if ($existing.length) {
			$existing.remove();
		}

		var footerHtml = buttons
			.map(function (btn) {
				var variant = btn.variant || 'secondary';
				var cls = 'pili-modal__btn pili-modal__btn--' + variant;
				var closeAttr = btn.close ? ' data-pili-modal-close="1"' : '';
				var icon = btn.icon
					? '<i class="' + String(btn.icon).replace(/[^a-z0-9\-_]/gi, '') + '" aria-hidden="true"></i>'
					: '';
				return (
					'<button type="button" class="' +
					cls +
					'" data-pili-modal-action="' +
					String(btn.id || '') +
					'"' +
					closeAttr +
					'>' +
					icon +
					'<span>' +
					$('<div/>').text(btn.label || btn.id || '').html() +
					'</span></button>'
				);
			})
			.join('');

		var html =
			'<div class="pili-modal" id="' +
			id +
			'" data-field-id="' +
			id +
			'" data-pili-modal="1" data-size="' +
			size +
			'" data-close-on-esc="' +
			(closeOnEsc ? '1' : '0') +
			'" data-close-on-mask="' +
			(closeOnMask ? '1' : '0') +
			'" hidden aria-hidden="true">' +
			'<div class="pili-modal__mask" data-pili-modal-mask="1"></div>' +
			'<div class="pili-modal__dialog pili-modal--' +
			size +
			'" role="dialog" aria-modal="true">' +
			'<div class="pili-modal__header">' +
			'<h3 class="pili-modal__title">' +
			$('<div/>').text(title).html() +
			'</h3>' +
			'<button type="button" class="pili-modal__close" data-pili-modal-close="1" aria-label="' + $('<div/>').text(str('close', '关闭弹窗')).html() + '"><i class="ri-close-line" aria-hidden="true"></i></button>' +
			'</div>' +
			'<div class="pili-modal__body" data-pili-modal-body="1">' +
			body +
			'</div>' +
			'<div class="pili-modal__footer" data-pili-modal-footer="1">' +
			footerHtml +
			'</div>' +
			'</div></div>';

		var $modal = $(html).appendTo('body');
		return $modal;
	}

	function initModals($root) {
		var $scope = ($root && $root.length) ? $root : $(document);
		$scope.find('.pili-modal').each(function () {
			bindOne($(this));
		});
	}

	PILI.registerBoot('modal', function ($root) {
		initModals($root && $root.length ? $root : $(document));
	});

	$(document).ready(function () {
		initModals($(document));
	});

	$(document).on('xun:field:added', function (e, $container) {
		PILI.boot('modal', $container && $container.length ? $container : $(document));
	});

	$(document).on('keydown.xunModal', function (e) {
		if (e.key !== 'Escape' || !stack.length) {
			return;
		}
		var top = stack[stack.length - 1];
		var $modal = $(top);
		if (String($modal.attr('data-close-on-esc') || '1') === '1') {
			closeModal($modal, 'esc');
		}
	});

	window.PiliXunModal = {
		open: function (targetOrOptions, maybeOptions) {
			var options = {};
			var $modal;
			if (targetOrOptions && typeof targetOrOptions === 'object' && !targetOrOptions.jquery && !targetOrOptions.nodeType && !targetOrOptions.tagName) {
				// open({ id, title, body, buttons, ... })
				options = targetOrOptions;
				if (options.target || options.id) {
					$modal = resolveModal(options.target || ('#' + options.id));
				}
				if (!$modal || !$modal.length) {
					$modal = createDynamicModal(options);
				}
			} else {
				$modal = resolveModal(targetOrOptions);
				options = maybeOptions || {};
			}
			return openModal($modal, options);
		},
		close: function (target) {
			var $modal = target ? resolveModal(target) : (stack.length ? $(stack[stack.length - 1]) : $());
			closeModal($modal, 'api');
		},
		setBody: function (target, html) {
			var $modal = resolveModal(target);
			if ($modal.length) {
				$modal.find('[data-pili-modal-body]').html(html || '');
			}
		},
		setTitle: function (target, title) {
			var $modal = resolveModal(target);
			if ($modal.length) {
				$modal.find('.pili-modal__title').first().text(title || '');
			}
		},
		get: function (target) {
			return resolveModal(target);
		}
	};
})(jQuery, window, document);
