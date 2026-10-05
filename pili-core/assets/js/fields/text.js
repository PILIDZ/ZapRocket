/**
 * PILI text field: rule validation + red shake.
 */
(function (window, $) {
	'use strict';

	function bag() {
		if (typeof PILI !== 'undefined' && typeof PILI.bag === 'function') {
			return PILI.bag('text') || {};
		}
		return {};
	}

	function t(key, fallback) {
		var map = bag();
		return map[key] || fallback || key;
	}

	function errorEl(input) {
		var id = input.getAttribute('aria-describedby') || '';
		var ids = id.split(/\s+/);
		var i;
		for (i = 0; i < ids.length; i++) {
			if (ids[i] && ids[i].slice(-6) === '-error') {
				var byId = document.getElementById(ids[i]);
				if (byId) {
					return byId;
				}
			}
		}
		var wrap = input.closest('.pili-field, [class*="pili-field"]') || input.parentElement;
		if (wrap) {
			return wrap.querySelector('.pili-input-error');
		}
		return null;
	}

	function isActive(input) {
		if (!input || input.disabled) {
			return false;
		}
		var wrap = input.closest('.pili-field, [class*="pili-field"]');
		if (wrap) {
			if (wrap.hidden || wrap.getAttribute('hidden') !== null) {
				return false;
			}
			if (wrap.style && wrap.style.display === 'none') {
				return false;
			}
			if (window.getComputedStyle && window.getComputedStyle(wrap).display === 'none') {
				return false;
			}
		}
		return true;
	}

	function checkValue(rule, value, custom) {
		value = String(value || '').replace(/^\s+|\s+$/g, '');
		if (!rule || !value) {
			return '';
		}
		if (rule === 'url') {
			if (!/^https?:\/\//i.test(value)) {
				return custom || t('invalidUrl', '请填写完整网址，必须以 http:// 或 https:// 开头。');
			}
			try {
				var parsed = new URL(value);
				if (!parsed.hostname) {
					return custom || t('invalidHost', '网址格式不正确。');
				}
			} catch (e) {
				return custom || t('invalidHost', '网址格式不正确。');
			}
			return '';
		}
		if (rule === 'email') {
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
				return custom || t('invalidEmail', '请填写有效的邮箱地址。');
			}
		}
		return '';
	}

	function markInvalid(input, message, shake) {
		if (!input) {
			return;
		}
		input.classList.add('pili-input--invalid');
		input.setAttribute('aria-invalid', 'true');
		var err = errorEl(input);
		if (err) {
			err.textContent = message || '';
			err.classList.add('is-visible');
		}
		if (shake) {
			input.classList.remove('pili-input--shake');
			void input.offsetWidth;
			input.classList.add('pili-input--shake');
			window.setTimeout(function () {
				input.classList.remove('pili-input--shake');
			}, 450);
		}
	}

	function clearInvalid(input) {
		if (!input) {
			return;
		}
		input.classList.remove('pili-input--invalid', 'pili-input--shake');
		input.removeAttribute('aria-invalid');
		var err = errorEl(input);
		if (err) {
			err.textContent = '';
			err.classList.remove('is-visible');
		}
	}

	function checkInput(input, shake) {
		if (!input || !isActive(input)) {
			return true;
		}
		var rule = input.getAttribute('data-pili-rule') || '';
		if (!rule) {
			return true;
		}
		var custom = input.getAttribute('data-pili-rule-message') || '';
		var msg = checkValue(rule, input.value, custom);
		if (msg) {
			markInvalid(input, msg, !!shake);
			return false;
		}
		clearInvalid(input);
		return true;
	}

	function checkForm(form, shake) {
		if (!form) {
			return true;
		}
		var inputs = form.querySelectorAll('.pili-input[data-pili-rule]');
		var ok = true;
		var first = null;
		var i;
		for (i = 0; i < inputs.length; i++) {
			if (!checkInput(inputs[i], shake)) {
				ok = false;
				if (!first) {
					first = inputs[i];
				}
			}
		}
		if (first && typeof first.focus === 'function') {
			first.focus();
		}
		return ok;
	}

	window.PiliTextField = {
		markInvalid: markInvalid,
		clearInvalid: clearInvalid,
		checkInput: checkInput,
		checkForm: checkForm
	};

	$(document).on('blur', '.pili-input[data-pili-rule]', function () {
		checkInput(this, true);
	});

	$(document).on('input', '.pili-input[data-pili-rule]', function () {
		var input = this;
		if (input.classList.contains('pili-input--invalid')) {
			checkInput(input, false);
		}
	});

	document.addEventListener(
		'submit',
		function (e) {
			var form = e.target;
			if (!form || !form.querySelectorAll) {
				return;
			}
			if (!form.querySelector('.pili-input[data-pili-rule]')) {
				return;
			}
			if (!checkForm(form, true)) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},
		true
	);
})(window, jQuery);
