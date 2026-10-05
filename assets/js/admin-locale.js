/**
 * ZapRocket header language switcher (PILI select, same pattern as pilipost).
 * i18n: 只写 user meta，刷新后由 PHP 加载 zaprocket 语言包。
 */
(function ($, window) {
	'use strict';

	function cfg() {
		return window.zaprocketLocale || {};
	}

	function i18n(key) {
		return (cfg().i18n && cfg().i18n[key]) || '';
	}

	function reload(url) {
		var target = String(url || window.location.href || '');
		try {
			var u = new URL(target, window.location.href);
			u.searchParams.set('_zrlang', String(Date.now()));
			window.location.replace(u.toString());
		} catch (e) {
			window.location.reload();
		}
	}

	function toast(type, message) {
		var api = window.PiliXunToast;
		if (api && typeof api[type] === 'function') {
			api[type](message);
			return;
		}
		if (api && typeof api.show === 'function') {
			api.show({ message: message, type: type || 'info' });
			return;
		}
		if (type === 'error') {
			window.alert(message);
		}
	}

	function readSelect($root) {
		var $sel = $root.find('#zaprocket-admin-locale');
		if (!$sel.length) {
			$sel = $root.find('select.pili-select-native').first();
		}
		return $sel;
	}

	var busy = false;

	function onLocaleChange($sel) {
		if (busy || !$sel || !$sel.length) {
			return;
		}
		var $root = $sel.closest('[data-zr-lang-switcher="1"]');
		if (!$root.length) {
			$root = $('[data-zr-lang-switcher="1"]').first();
		}
		var locale = String($sel.val() || '');
		var prev = String($root.attr('data-current') || $sel.attr('data-current') || '');
		if (locale === prev) {
			return;
		}
		busy = true;
		$sel.prop('disabled', true);
		$root.find('.pili-select-button').prop('disabled', true).addClass('opacity-60');
		toast('info', i18n('switching') || '正在切换语言…');
		$.post(cfg().ajax || '', {
			action: cfg().action || 'zaprocket_set_admin_locale',
			nonce: cfg().nonce || '',
			locale: locale,
			redirect: window.location.href
		})
			.done(function (res) {
				if (!res || !res.success) {
					busy = false;
					$sel.prop('disabled', false);
					$root.find('.pili-select-button').prop('disabled', false).removeClass('opacity-60');
					toast('error', (res && res.data && res.data.message) || i18n('fail') || '语言切换失败。');
					return;
				}
				$root.attr('data-current', locale);
				$sel.attr('data-current', locale);
				reload(res.data && res.data.redirect ? res.data.redirect : window.location.href);
			})
			.fail(function () {
				busy = false;
				$sel.prop('disabled', false);
				$root.find('.pili-select-button').prop('disabled', false).removeClass('opacity-60');
				toast('error', i18n('fail') || '语言切换失败。');
			});
	}

	$(document)
		.off('change.zaprocketLang')
		.on('change.zaprocketLang', '[data-zr-lang-switcher="1"] select.pili-select-native, #zaprocket-admin-locale', function () {
			onLocaleChange($(this));
		});

	$(document)
		.off('click.zaprocketLangOpt')
		.on('click.zaprocketLangOpt', '[data-zr-lang-switcher="1"] .pili-select-option', function () {
			var $root = $(this).closest('[data-zr-lang-switcher="1"]');
			var $sel = readSelect($root);
			if (!$sel.length) {
				return;
			}
			window.setTimeout(function () {
				onLocaleChange($sel);
			}, 30);
		});
})(jQuery, window);
