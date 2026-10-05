/**
 * XUN password field — show/hide + secret-status badge.
 * Bound on document so lazy-loaded sections (innerHTML, no inline script) still work.
 */
(function () {
	'use strict';

	if (window.__xunPasswordFieldBound) {
		return;
	}
	window.__xunPasswordFieldBound = true;

	var presets = PILI.bag('password') && PILI.bag('password').presets
		? PILI.bag('password').presets
		: {};

	function setBadgeState(badge, state) {
		if (!badge || !presets[state]) {
			return;
		}
		var p = presets[state];
		badge.classList.remove(
			'pili-password-secret-status--configured',
			'pili-password-secret-status--empty',
			'pili-password-secret-status--pending'
		);
		badge.classList.add('pili-password-secret-status--' + state);
		badge.setAttribute('data-current', state);
		var icon = badge.querySelector('.pili-password-secret-status__icon');
		if (icon && icon.classList && p.icon) {
			var keep = ['pili-password-secret-status__icon', 'pili-ri-icon'];
			icon.className = keep.concat([p.icon]).join(' ');
		}
		var text = badge.querySelector('.pili-password-secret-status__text');
		if (text && p.label) {
			text.textContent = p.label;
		}
	}

	function resolveInput(btn) {
		var wrap = btn.closest('.mt-2.relative') || btn.parentElement;
		if (wrap) {
			var near = wrap.querySelector('input.pili-password-input');
			if (near) {
				return near;
			}
		}
		var targetId = btn.getAttribute('data-target') || '';
		if (!targetId) {
			return null;
		}
		return document.getElementById(targetId);
	}

	document.addEventListener(
		'click',
		function (e) {
			var btn =
				e.target && e.target.closest
					? e.target.closest('.pili-password-toggle')
					: null;
			if (!btn) {
				return;
			}
			e.preventDefault();
			var input = resolveInput(btn);
			if (!input) {
				return;
			}
			var isPwd = input.getAttribute('type') === 'password';
			input.setAttribute('type', isPwd ? 'text' : 'password');
			var state = isPwd ? 'shown' : 'hidden';
			btn.setAttribute('data-state', state);
			var showIcon = btn.querySelector('.pili-password-toggle__show');
			var hideIcon = btn.querySelector('.pili-password-toggle__hide');
			if (showIcon && hideIcon) {
				showIcon.classList.toggle('hidden', state === 'shown');
				hideIcon.classList.toggle('hidden', state !== 'shown');
			}
			var hideLabel =
				btn.getAttribute('data-hide-label') ||
				(PILI.bag('password') && PILI.bag('password').hideLabel) ||
				'隐藏密码';
			var showLabel =
				btn.getAttribute('data-show-label') ||
				(PILI.bag('password') && PILI.bag('password').showLabel) ||
				'显示密码';
			btn.setAttribute('aria-label', isPwd ? hideLabel : showLabel);
		},
		true
	);

	document.addEventListener(
		'input',
		function (e) {
			var input = e.target;
			if (
				!input ||
				!input.classList ||
				!input.classList.contains('pili-password-input')
			) {
				return;
			}
			if (input.getAttribute('data-has-secret-status') !== '1') {
				return;
			}
			var field = input.closest('.pili-field-password');
			if (!field) {
				return;
			}
			var badge = field.querySelector(
				'.pili-password-secret-status[data-secret-status="1"]'
			);
			if (!badge) {
				return;
			}
			var initial = badge.getAttribute('data-initial') || 'empty';
			if ((input.value || '').trim() !== '') {
				setBadgeState(badge, 'pending');
			} else {
				setBadgeState(badge, initial);
			}
		},
		true
	);

	if (window.jQuery) {
		window.jQuery(document).ajaxSuccess(function (_evt, xhr, settings) {
			var payload = settings && settings.data;
			if (
				!payload ||
				typeof payload !== 'string' ||
				payload.indexOf('pili_ajax') === -1
			) {
				return;
			}
			var json = xhr.responseJSON;
			if (!json || !json.success) {
				return;
			}
			document
				.querySelectorAll('.pili-password-input[data-has-secret-status="1"]')
				.forEach(function (input) {
					if ((input.value || '').trim() === '') {
						return;
					}
					input.value = '';
					var field = input.closest('.pili-field-password');
					if (!field) {
						return;
					}
					var badge = field.querySelector(
						'.pili-password-secret-status[data-secret-status="1"]'
					);
					if (!badge) {
						return;
					}
					badge.setAttribute('data-initial', 'configured');
					setBadgeState(badge, 'configured');
				});
		});
	}

	PILI.registerBoot('password', function () {
		/* Document-level listeners only); boot is a no-op for FieldBoot callers. */
	});
})();
