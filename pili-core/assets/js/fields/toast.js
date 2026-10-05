/**
 * XUN toast field
 *
 * Global API: window.PiliXunToast
 *   - show(message, options?)
 *   - success / error / warning / info(message, options?)
 *   - dismiss(id?)
 *   - clear()
 *
 * options: {
 *   type, title, icon, duration, dismissible, position, id, html,
 *   bot, botSize, state, queue
 * }
 */
(function ($, window, document) {
	'use strict';

	var cfg = {};
	if (typeof PILI !== 'undefined' && typeof PILI.bag === 'function') {
		cfg = PILI.bag('toast') || {};
	} else if (window._piliBag_toast && typeof window._piliBag_toast === 'object') {
		cfg = window._piliBag_toast;
	}
	var defaults = $.extend(
		{
			duration: 3200,
			position: 'bottom-center',
			dismissible: true,
			max: 1,
			bot: true,
			botSize: 56,
			queue: true
		},
		cfg.defaults || {}
	);
	var strings = $.extend(
		{
			close: '',
			success: '',
			error: '',
			warning: '',
			info: ''
		},
		cfg.strings || {}
	);
	var icons = $.extend(
		{
			success: 'ri-checkbox-circle-fill',
			error: 'ri-error-warning-fill',
			warning: 'ri-alert-fill',
			info: 'ri-information-fill'
		},
		cfg.icons || {}
	);
	var botStateMap = $.extend(
		{
			success: 'success',
			error: 'error',
			warning: 'rate_limit',
			info: 'streaming',
			ok: 'success',
			done: 'success',
			danger: 'error',
			fail: 'error',
			warn: 'rate_limit',
			loading: 'bounce',
			thinking: 'bounce',
			working: 'bounce',
			streaming: 'streaming',
			searching: 'searching',
			listening: 'listening',
			approval: 'approval',
			idle: 'idle',
			bounce: 'bounce'
		},
		cfg.botStateMap || {}
	);

	var TYPE_ALIAS = {
		ok: 'success',
		done: 'success',
		danger: 'error',
		fail: 'error',
		warn: 'warning',
		caution: 'warning',
		default: 'info',
		neutral: 'info',
		primary: 'info'
	};

	var POSITIONS = {
		'top-right': 1,
		'top-left': 1,
		'top-center': 1,
		'bottom-right': 1,
		'bottom-left': 1,
		'bottom-center': 1
	};

	var seq = 0;
	var items = {};
	var queue = [];
	var activeId = null;
	var dismissGapMs = 200;

	function normalizeType(type) {
		var key = String(type || 'info').toLowerCase();
		if (TYPE_ALIAS[key]) {
			key = TYPE_ALIAS[key];
		}
		if (icons[key]) {
			return key;
		}
		if (botStateMap[key]) {
			return key;
		}
		return 'info';
	}

	function toastVisualType(type) {
		var key = normalizeType(type);
		return icons[key] ? key : 'info';
	}

	function normalizePosition(pos) {
		var key = String(pos || defaults.position || 'bottom-center');
		return POSITIONS[key] ? key : 'bottom-center';
	}

	function resolveBotSize(size) {
		if (typeof size === 'number' && size > 0) {
			return Math.round(size);
		}
		var n = parseInt(size, 10);
		return n > 0 ? n : parseInt(defaults.botSize, 10) || 56;
	}

	function mapBotState(type, explicit) {
		var core = window.PiliBotCore;
		if (explicit && core && core.STATES && core.STATES[explicit]) {
			return explicit;
		}
		var key = String(type || 'info').toLowerCase();
		if (TYPE_ALIAS[key]) {
			key = TYPE_ALIAS[key];
		}
		if (botStateMap[key]) {
			return botStateMap[key];
		}
		if (core && core.STATES && core.STATES[key]) {
			return key;
		}
		return botStateMap.info || 'talking';
	}

	function canUseBot() {
		return !!(
			window.PiliBotCore &&
			typeof window.PiliBotCore.createInstance === 'function' &&
			window.PiliBotCore.STATES
		);
	}

	function escapeHtml(text) {
		return $('<div/>').text(String(text == null ? '' : text)).html();
	}

	function sanitizeIcon(icon) {
		var raw = String(icon || '');
		if (/^ri-[a-z0-9-]+$/i.test(raw)) {
			return raw.toLowerCase();
		}
		return '';
	}

	function hostIdFor(position) {
		return 'pili-toast-host--' + position;
	}

	function ensureHost(position) {
		position = normalizePosition(position);
		var id = hostIdFor(position);
		var $host = $('#' + id);
		if ($host.length) {
			return $host.first();
		}
		$host = $(
			'<div id="' +
				id +
				'" class="pili-toast-host pili-toast-host--' +
				position +
				'" data-pili-toast-host="1" data-position="' +
				position +
				'" aria-live="polite" aria-relevant="additions"></div>'
		);
		$host.appendTo('body');
		return $host;
	}

	function destroyBot(item) {
		if (!item || !item.bot) {
			return;
		}
		try {
			item.bot.destroy();
		} catch (e) {
			/* noop */
		}
		item.bot = null;
	}

	function forceRemoveToastEl($el) {
		if (!$el || !$el.length) {
			return;
		}
		var id = String($el.attr('data-toast-id') || $el.attr('id') || '');
		if (id && items[id]) {
			window.clearTimeout(items[id].timer);
			destroyBot(items[id]);
			delete items[id];
			if (activeId === id) {
				activeId = null;
			}
		}
		$el.remove();
	}

	function trimStack($host) {
		var max = Math.max(1, parseInt(defaults.max, 10) || 1);
		var guard = 0;
		var $list = $host.children('.pili-toast');
		while ($list.length > max && guard < 32) {
			guard++;
			var $oldest = $list.first();
			var oldestId = String($oldest.attr('data-toast-id') || '');
			if (oldestId && items[oldestId]) {
				dismiss(oldestId, true);
			} else {
				// 已从 items 摘掉但仍在 DOM（动画中）：必须硬删，否则 while 死循环卡死页面
				forceRemoveToastEl($oldest);
			}
			$list = $host.children('.pili-toast');
		}
	}

	function buildToastHtml(id, opts) {
		var visual = toastVisualType(opts.type);
		var useBot = !!opts.useBot;
		var icon = sanitizeIcon(opts.icon) || icons[visual] || icons.info;
		var title = opts.title ? String(opts.title) : '';
		var message = opts.message != null ? String(opts.message) : '';
		var dismissible = opts.dismissible !== false;
		var msgHtml = opts.html ? String(opts.html) : escapeHtml(message);
		var botSize = resolveBotSize(opts.botSize);

		var cls = 'pili-toast pili-toast--' + visual;
		if (useBot) {
			cls += ' pili-toast--pilibot';
		}

		var html =
			'<div class="' +
			cls +
			'" id="' +
			id +
			'" data-toast-id="' +
			id +
			'" data-type="' +
			escapeHtml(String(opts.type || visual)) +
			'" role="status">';

		if (useBot) {
			html +=
				'<div class="pili-toast__bot" aria-hidden="true" style="width:' +
				botSize +
				'px;height:' +
				botSize +
				'px">' +
				'<canvas class="pili-toast__bot-canvas" width="' +
				botSize +
				'" height="' +
				botSize +
				'"></canvas>' +
				'</div>';
		} else {
			html +=
				'<div class="pili-toast__icon" aria-hidden="true"><i class="' +
				icon +
				'"></i></div>';
		}

		html += '<div class="pili-toast__body">';
		if (title) {
			html += '<p class="pili-toast__title">' + escapeHtml(title) + '</p>';
		}
		html += '<p class="pili-toast__message">' + msgHtml + '</p></div>';

		if (dismissible) {
			html +=
				'<button type="button" class="pili-toast__close" data-pili-toast-close="1" aria-label="' +
				escapeHtml(strings.close) +
				'"><i class="ri-close-line" aria-hidden="true"></i></button>';
		}

		html += '</div>';
		return html;
	}

	function mountBot($toast, state, botSize) {
		if (!canUseBot()) {
			return null;
		}
		var canvas = $toast.find('canvas.pili-toast__bot-canvas')[0];
		if (!canvas) {
			return null;
		}
		var px = resolveBotSize(botSize);
		canvas.width = px;
		canvas.height = px;
		// Start idle then morph so check/cross stroke animates.
		var bot = window.PiliBotCore.createInstance(canvas, {
			id: 'toast-bot-' + Date.now() + '-' + Math.floor(Math.random() * 9999),
			state: 'idle',
			followPointer: false,
			transparentBg: true
		});
		if (bot && state && state !== 'idle') {
			window.requestAnimationFrame(function () {
				try {
					bot.setState(state);
				} catch (e) {
					/* noop */
				}
			});
		}
		return bot;
	}

	function dismiss(id, immediate) {
		if (!id) {
			var keys = Object.keys(items);
			if (!keys.length) {
				return;
			}
			id = keys[keys.length - 1];
		}
		var item = items[id];
		if (!item) {
			// items 已空但 DOM 可能残留（连点/竞态），清掉避免 trimStack 死循环
			forceRemoveToastEl($('.pili-toast[data-toast-id="' + id.replace(/"/g, '') + '"]'));
			return;
		}
		window.clearTimeout(item.timer);
		destroyBot(item);
		var $el = item.$el;
		delete items[id];
		if (activeId === id) {
			activeId = null;
		}
		if (!$el || !$el.length) {
			pumpQueue();
			return;
		}
		if (immediate) {
			$el.remove();
			pumpQueue();
			return;
		}
		$el.removeClass('is-in').addClass('is-out');
		window.setTimeout(function () {
			if ($el && $el.length) {
				$el.remove();
			}
			pumpQueue();
		}, dismissGapMs);
	}

	function clear() {
		queue = [];
		Object.keys(items).forEach(function (tid) {
			var item = items[tid];
			if (!item) {
				return;
			}
			window.clearTimeout(item.timer);
			destroyBot(item);
			if (item.$el && item.$el.length) {
				item.$el.remove();
			}
			delete items[tid];
		});
		activeId = null;
		$('.pili-toast-host').empty();
	}

	function prepareOpts(message, options) {
		var opts = {};
		if (message && typeof message === 'object' && !Array.isArray(message)) {
			opts = $.extend({}, message);
		} else {
			opts = $.extend({}, options || {});
			opts.message = message;
		}

		opts.type = normalizeType(opts.type || 'info');
		opts.position = normalizePosition(opts.position || defaults.position);
		opts.duration =
			typeof opts.duration === 'number'
				? opts.duration
				: typeof defaults.duration === 'number'
					? defaults.duration
					: 3200;
		opts.dismissible = opts.dismissible !== false && defaults.dismissible !== false;
		opts.id = opts.id ? String(opts.id) : 'pili-toast-' + Date.now() + '-' + ++seq;

		var wantBot = opts.bot !== false && defaults.bot !== false;
		if (opts.icon && opts.bot == null) {
			wantBot = false;
		}
		opts._useBot = wantBot && canUseBot();
		opts._botState = mapBotState(opts.type, opts.state);
		opts._botSize = resolveBotSize(opts.botSize != null ? opts.botSize : defaults.botSize);
		opts._visual = toastVisualType(opts.type);
		return opts;
	}

	function showNow(opts) {
		var id = opts.id;
		var type = opts.type;
		var visual = opts._visual;
		var position = opts.position;
		var duration = opts.duration;
		var dismissible = opts.dismissible;
		var useBot = opts._useBot;
		var botState = opts._botState;
		var botSize = opts._botSize;

		if (items[id]) {
			dismiss(id, true);
		}

		var $host = ensureHost(position);
		var $toast = $(
			buildToastHtml(id, {
				type: type,
				title: opts.title || '',
				message: opts.message != null ? opts.message : '',
				html: opts.html || '',
				icon: opts.icon || '',
				dismissible: dismissible,
				useBot: useBot,
				botSize: botSize
			})
		);

		$host.append($toast);
		trimStack($host);

		var bot = null;
		if (useBot) {
			bot = mountBot($toast, botState, botSize);
			if (!bot) {
				$toast.removeClass('pili-toast--pilibot');
				$toast.find('.pili-toast__bot').replaceWith(
					$(
						'<div class="pili-toast__icon" aria-hidden="true"><i class="' +
							(sanitizeIcon(opts.icon) || icons[visual] || icons.info) +
							'"></i></div>'
					)
				);
			}
		}

		$toast[0].offsetWidth; // eslint-disable-line no-unused-expressions
		$toast.addClass('is-in');

		var timer = null;
		if (duration > 0) {
			timer = window.setTimeout(function () {
				dismiss(id);
			}, duration);
		}

		items[id] = {
			$el: $toast,
			timer: timer,
			type: type,
			position: position,
			bot: bot,
			duration: duration
		};
		activeId = id;

		$toast.trigger('xun:toast:show', [id, type, botState]);
		return id;
	}

	function pumpQueue() {
		if (activeId && items[activeId]) {
			return;
		}
		activeId = null;
		if (!queue.length) {
			return;
		}
		showNow(queue.shift());
	}

	/**
	 * Default serial queue: one toast at a time.
	 * Pass queue:false to show immediately.
	 * 连点时只保留最新一条，避免 queue 无限膨胀 + DOM 竞态卡死。
	 */
	function show(message, options) {
		var opts = prepareOpts(message, options);
		var useQueue = defaults.queue !== false && opts.queue !== false;

		if (!useQueue) {
			queue = [];
			if (activeId) {
				dismiss(activeId, true);
			}
			$('.pili-toast-host .pili-toast.is-out').each(function () {
				forceRemoveToastEl($(this));
			});
			return showNow(opts);
		}

		// max=1：队列只留最新，替换当前展示
		queue = [opts];

		if (activeId && items[activeId]) {
			dismiss(activeId, true);
		} else {
			$('.pili-toast-host .pili-toast.is-out').each(function () {
				forceRemoveToastEl($(this));
			});
			pumpQueue();
		}

		return opts.id;
	}

	function typed(type) {
		return function (message, options) {
			var opts = $.extend({}, options || {}, { type: type });
			return show(message, opts);
		};
	}

	$(document).on('click.xunToast', '[data-pili-toast-close]', function (e) {
		e.preventDefault();
		var id = String($(this).closest('.pili-toast').attr('data-toast-id') || '');
		dismiss(id);
	});

	window.PiliXunToast = {
		show: show,
		success: typed('success'),
		error: typed('error'),
		warning: typed('warning'),
		info: typed('info'),
		dismiss: function (id) {
			dismiss(id, false);
		},
		clear: clear,
		defaults: defaults,
		mapBotState: mapBotState,
		canUseBot: canUseBot
	};
})(jQuery, window, document);
