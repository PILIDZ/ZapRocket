/**
 * ZapRocket OSS admin: test / retry / dismiss + provider presets.
 * i18n: 文案来自 zaprocketOss.i18n / presets（PHP pili__()），不要在此硬写中英文。
 */
function ossAdminMain($) {
	'use strict';

	var FIELD_MAP = {
		bucket: 'zr_oss_bucket',
		appid: 'zr_oss_app_id',
		region: 'zr_oss_region',
		region_aliyun: 'zr_oss_region_aliyun',
		region_tencent: 'zr_oss_region_tencent',
		endpoint: 'zr_oss_endpoint',
		domain: 'zr_oss_custom_domain',
		prefix: 'zr_oss_object_prefix',
		ak: 'zr_oss_access_key',
		sk: 'zr_oss_secret_key'
	};

	var packCache = null;
	var layoutPatched = false;

	function cfg() {
		return window.zaprocketOss || {};
	}

	function ossScope() {
		var $vis = $('.pili-section-wrapper').not('.hidden').filter(function () {
			return $(this).find('[data-field-id="zr_oss_provider"]').length;
		}).first();
		if ($vis.length) {
			return $vis;
		}
		return $('[data-field-id="zr_oss_provider"]').closest('.pili-section-wrapper').first();
	}

	function fieldWrap(suffix) {
		var $scope = ossScope();
		if ($scope.length) {
			var $w = $scope.find('[data-field-id="' + suffix + '"]').first();
			if ($w.length) {
				return $w;
			}
		}
		return $('[data-field-id="' + suffix + '"]').first();
	}

	function pack() {
		if (packCache) {
			return packCache;
		}
		if (cfg().presets && cfg().presets.providers) {
			packCache = cfg().presets;
			return packCache;
		}
		var el = document.getElementById('zr-oss-presets');
		if (el && el.textContent) {
			try {
				packCache = JSON.parse(el.textContent);
			} catch (e) {
				packCache = null;
			}
		}
		return packCache || {};
	}

	function presets() {
		return pack().providers || {};
	}

	function i18n(key) {
		var map = pack().i18n || {};
		if (map[key]) {
			return map[key];
		}
		return (cfg().i18n && cfg().i18n[key]) || '';
	}

	function fieldVal(suffix) {
		var $wrap = fieldWrap(suffix);
		var $el = $wrap.find('input, select, textarea').filter(function () {
			var n = this.getAttribute('name') || '';
			return n.indexOf('[' + suffix + ']') !== -1;
		}).first();
		if (!$el.length) {
			$el = $wrap.find('[name$="[' + suffix + ']"]').first();
		}
		if (!$el.length) {
			$el = $wrap.find('[data-depend-id="' + suffix + '"]').first();
		}
		if (!$el.length) {
			var $scope = ossScope();
			var $root = $scope.length ? $scope : $(document);
			$el = $root.find('[name$="[' + suffix + ']"]').first();
		}
		return $el.length ? $.trim(String($el.val() || '')) : '';
	}

	function isOn(id) {
		var $wrap = fieldWrap(id);
		var $hidden = $wrap.find('[data-pili-switch] input[type="hidden"]').first();
		if (!$hidden.length) {
			$hidden = $wrap.find('input[type="hidden"][name*="[' + id + ']"]').first();
		}
		if ($hidden.length) {
			var hv = String($hidden.val() == null ? '' : $hidden.val()).toLowerCase();
			return hv === '1' || hv === 'true' || hv === 'on' || hv === 'yes';
		}
		if (window.PiliAdminUiDeps && typeof window.PiliAdminUiDeps.getFieldValue === 'function' && $wrap.length) {
			var depVal = String(window.PiliAdminUiDeps.getFieldValue($wrap) || '').toLowerCase();
			return depVal === '1' || depVal === 'true' || depVal === 'on' || depVal === 'yes';
		}
		var v = String(fieldVal(id) || '').toLowerCase();
		return v === '1' || v === 'true' || v === 'on' || v === 'yes';
	}

	function scheduleLayout() {
		[0, 80, 250, 600, 1200, 2000].forEach(function (ms) {
			window.setTimeout(runLayout, ms);
		});
	}

	function applyVendorFields(row) {
		var ui = (row && row.ui) ? row.ui : {};
		var enabled = isOn('zr_oss_enable');
		var flags = {
			zr_oss_app_id: !!ui.show_app_id,
			zr_oss_region_aliyun: !!ui.show_aliyun_region,
			zr_oss_region_tencent: !!ui.show_tencent_region,
			zr_oss_region: !!ui.show_custom_region,
			zr_oss_endpoint: !!ui.show_endpoint
		};
		Object.keys(flags).forEach(function (id) {
			var $w = fieldWrap(id);
			if (!$w.length) {
				return;
			}
			var show = enabled && flags[id];
			$w.toggleClass('pili-dep-hidden', !show);
			$w.attr('aria-hidden', show ? 'false' : 'true');
		});
	}

	function runLayout() {
		var row = presets()[currentProvider()] || {};
		applyProvider(currentProvider());
		var $scope = ossScope();
		if (window.PiliAdminUiDeps && typeof window.PiliAdminUiDeps.refreshIn === 'function' && $scope.length) {
			window.PiliAdminUiDeps.refreshIn($scope);
		} else if (window.PiliAdminUiDeps && typeof window.PiliAdminUiDeps.refresh === 'function') {
			window.PiliAdminUiDeps.refresh();
		}
		applyVendorFields(row);
		syncOssLayout(row);
	}

	function patchDepsRefresh() {
		var api = window.PiliAdminUiDeps;
		if (!api || api._zrOssVendorPatched) {
			return;
		}
		api._zrOssVendorPatched = true;
		['refresh', 'refreshIn'].forEach(function (name) {
			var orig = api[name];
			if (typeof orig !== 'function') {
				return;
			}
			api[name] = function () {
				var ret = orig.apply(this, arguments);
				var row = presets()[currentProvider()] || {};
				applyVendorFields(row);
				syncOssLayout(row);
				return ret;
			};
		});
	}

	function refreshVisibleOssSection() {
		packCache = null;
		runLayout();
		scheduleLayout();
	}

	function wrapBootVisible() {
		var fw = window.PILI && window.PILI.Framework;
		if (!fw || typeof fw.bootVisibleSectionFields !== 'function') {
			return false;
		}
		if (fw.bootVisibleSectionFields._zrOssWrapped) {
			return true;
		}
		var orig = fw.bootVisibleSectionFields;
		var wrapped = function ($root) {
			orig.apply(this, arguments);
			if ($root && $root.find && $root.find('[data-field-id="zr_oss_provider"]').length) {
				window.setTimeout(refreshVisibleOssSection, 0);
			}
		};
		wrapped._zrOssWrapped = true;
		fw.bootVisibleSectionFields = wrapped;
		return true;
	}

	function observeOssSectionShown() {
		var form = document.getElementById('pili-options-form') || document.querySelector('.pili-inst-zaprocket');
		if (!form || form.getAttribute('data-zr-oss-reshow') === '1' || typeof MutationObserver === 'undefined') {
			return;
		}
		form.setAttribute('data-zr-oss-reshow', '1');
		var mo = new MutationObserver(function (mutations) {
			var i;
			for (i = 0; i < mutations.length; i++) {
				var t = mutations[i].target;
				if (!t || !t.getAttribute || t.getAttribute('data-lazy-section') !== '1') {
					continue;
				}
				if (t.classList.contains('hidden')) {
					continue;
				}
				if (t.querySelector('[data-field-id="zr_oss_provider"]')) {
					refreshVisibleOssSection();
					return;
				}
			}
		});
		mo.observe(form, { attributes: true, attributeFilter: ['class'], subtree: true });
	}

	function patchSectionReshow() {
		if (wrapBootVisible()) {
			observeOssSectionShown();
			return;
		}
		if (layoutPatched) {
			return;
		}
		layoutPatched = true;
		var n = 0;
		var tick = window.setInterval(function () {
			n += 1;
			if (wrapBootVisible() || n > 40) {
				window.clearInterval(tick);
				observeOssSectionShown();
			}
		}, 50);
	}

	function toast(type, message) {
		var api = window.PiliXunToast;
		if (!api) {
			return;
		}
		if (type === 'success' && typeof api.success === 'function') {
			api.success(message);
			return;
		}
		if (type === 'error' && typeof api.error === 'function') {
			api.error(message);
			return;
		}
		if (typeof api.show === 'function') {
			api.show({ message: message, type: type || 'info' });
		}
	}

	function setBusy($btn, busy) {
		if (!$btn || !$btn.length) {
			return;
		}
		var label = $btn.attr('data-pili-table-action-label') || $btn.text();
		var loading = $btn.attr('data-pili-table-loading-label') || label;
		$btn.prop('disabled', !!busy);
		$btn.attr('aria-busy', busy ? 'true' : 'false');
		$btn.text(busy ? loading : label);
	}

	function esc(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function listHtml(items, ordered) {
		if (!items || !items.length) {
			return '';
		}
		var tag = ordered ? 'ol' : 'ul';
		var html = '<' + tag + '>';
		items.forEach(function (item) {
			html += '<li>' + esc(item) + '</li>';
		});
		return html + '</' + tag + '>';
	}

	function renderGuide(row) {
		var $box = $('[data-oss-guide="1"]').first();
		if (!$box.length) {
			var $host = $('#zaprocket_options_zr_oss_guide, [data-depend-id="zr_oss_guide"]').first();
			if ($host.length) {
				$host.html('<div class="zr-oss-guide" data-oss-guide="1"></div>');
				$box = $host.find('[data-oss-guide="1"]');
			}
		}
		if (!$box.length) {
			return;
		}
		var html = '';
		html += '<div class="zr-oss-guide__head">';
		html += '<h3 class="zr-oss-guide__title">' + esc(i18n('guideTitle')) + '</h3>';
		html += '<p class="zr-oss-guide__brand">' + esc(row.label) + '</p></div>';
		html += '<p class="zr-oss-guide__summary">' + esc(row.summary) + '</p>';
		if (row.domainNote) {
			html += '<p class="zr-oss-guide__note">' + esc(row.domainNote) + '</p>';
		}
		if (row.docs) {
			html += '<p class="zr-oss-guide__docs"><a href="' + esc(row.docs) + '" target="_blank" rel="noopener noreferrer">' + esc(i18n('docs')) + '</a></p>';
		}
		html += '<section class="zr-oss-guide__block zr-oss-guide__block--prepare"><h4>' + esc(i18n('prepare')) + '</h4>' + listHtml(row.prepare, true) + '</section>';
		html += '<section class="zr-oss-guide__block zr-oss-guide__block--keys"><h4>' + esc(i18n('keys')) + '</h4>' + listHtml(row.keys, true) + '</section>';
		html += '<section class="zr-oss-guide__block zr-oss-guide__block--pits"><h4>' + esc(i18n('pits')) + '</h4>' + listHtml(row.pits, false) + '</section>';
		$box.addClass('zr-oss-guide--updating');
		$box.html(html);
		window.requestAnimationFrame(function () {
			$box.removeClass('zr-oss-guide--updating');
		});
	}

	function setFieldCopy(ossKey, pack) {
		var id = FIELD_MAP[ossKey];
		if (!id || !pack) {
			return;
		}
		var fid = 'zaprocket_options_' + id;
		var $input = $('#' + fid);
		if (!$input.length) {
			$input = $('[name="zaprocket_options[' + id + ']"]').first();
		}
		var $desc = $('#' + fid + '-description');
		if (!$desc.length) {
			$desc = $input.closest('div').find('p.mt-2.text-sm').first();
		}
		if ($desc.length && pack.hint) {
			$desc.text(pack.hint);
		}
		if ($input.length && pack.example) {
			$input.attr('placeholder', pack.example);
			var $ex = $input.siblings('.zr-oss-example');
			if (!$ex.length) {
				$ex = $('<p class="zr-oss-example mt-1 text-xs text-gray-400"></p>');
				$input.after($ex);
			}
			var fmt = i18n('exampleFmt') || ((i18n('example') || '') + '：%s');
			$ex.text(String(fmt).replace('%s', pack.example));
		}
	}

	function bindSearchClear() {
		$(document).on('input', '.pili-select-search', function () {
			var $inp = $(this);
			var regionOpen = $('[data-field-id*="zr_oss_region"] button[aria-expanded="true"]').length;
			var inRegion = $inp.closest('[data-field-id*="zr_oss_region"]').length;
			var ph = String($inp.attr('placeholder') || '');
			var isRegion = inRegion || regionOpen || /oss-cn|ap-guangzhou|杭州|广州/.test(ph);
			if (!isRegion) {
				return;
			}
			var $host = $inp.parent();
			$host.css('position', 'relative');
			var $btn = $host.find('.zr-oss-search-clear');
			if (!$btn.length) {
				$btn = $('<button type="button" class="zr-oss-search-clear" aria-label="clear"></button>');
				$inp.after($btn);
			}
			$btn.css('display', $inp.val() ? 'block' : 'none');
		});
		$(document).on('click', '.zr-oss-search-clear', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $inp = $(this).siblings('.pili-select-search');
			$inp.val('').trigger('input');
			$(this).hide();
		});
	}

	function prefixIllegal(v) {
		return /\.\.|[<>:"|?*\\]/.test(String(v || ''));
	}

	function showTestResult(ok, message) {
		var $box = $('.zr-oss-test__result').first();
		if (!$box.length) {
			toast(ok ? 'success' : 'error', message);
			return;
		}
		var text = String(message || '');
		var fold = text.length > 96;
		var short = fold ? text.slice(0, 96) + '…' : text;
		var html = '<p class="zr-oss-test__msg">' + esc(short) + '</p>';
		if (fold) {
			html += '<button type="button" class="zr-oss-test__fold" data-full="' + esc(text) + '">' + esc(i18n('foldMore')) + '</button>';
		}
		$box
			.attr('data-ok', ok ? '1' : '0')
			.removeAttr('hidden')
			.html(html);
		toast(ok ? 'success' : 'error', short);
	}
	function setCapNotice(row) {
		var $notice = $('.zr-oss-cap-notice').first();
		var notes = [];
		if (!row.supports_rename || !row.supports_prefix) {
			notes.push(row.cap_note || i18n('capHide'));
		}
		if ($notice.length) {
			if (notes.length) {
				$notice.text(notes.join(' ')).prop('hidden', false);
			} else {
				$notice.text('').prop('hidden', true);
			}
		}
	}

	function currentProvider() {
		var v = fieldVal('zr_oss_provider');
		if (!v) {
			v = pack().defaultProvider || 'aliyun';
		}
		return v;
	}

	function syncOssLayout(row) {
		row = row || presets()[currentProvider()] || {};
		var $root = $('.pili-inst-zaprocket').first();
		if ($root.length) {
			$root.attr('data-zr-oss-on', isOn('zr_oss_enable') ? '1' : '0');
			$root.attr('data-zr-oss-provider', currentProvider());
		}
		setCapNotice(row);
	}

	function applyProvider(id) {
		var row = presets()[id];
		if (row) {
			renderGuide(row);
			Object.keys(FIELD_MAP).forEach(function (k) {
				if (row.fields && row.fields[k]) {
					setFieldCopy(k, row.fields[k]);
				}
			});
			if (row.fields && row.fields.region) {
				setFieldCopy('region_aliyun', row.fields.region);
				setFieldCopy('region_tencent', row.fields.region);
			}
			if (row.fields && row.fields.appid) {
				setFieldCopy('appid', row.fields.appid);
			}
		}
		syncOssLayout(row || {});
	}

	function bindProvider() {
		$(document).off('.zrOssLayout');
		$(document).on('change.zrOssLayout', 'select[name*="[zr_oss_provider]"], select[name*="[zr_oss_rename_rule]"]', scheduleLayout);
		$(document).on('click.zrOssLayout', '.pili-select-option, [role="switch"]', scheduleLayout);
		$(document).on('xun:switch:change.zrOssLayout', '[data-pili-switch]', scheduleLayout);
		$(document).on('change.zrOssLayout', '[data-depend-id="zr_oss_enable"], [data-depend-id="zr_oss_rename_enable"]', scheduleLayout);
		$(document).on('pili:section:loaded.zrOssLayout pili:field:added.zrOssLayout pili:field:loaded.zrOssLayout', function () {
			packCache = null;
			scheduleLayout();
		});
		$(window).off('hashchange.zrOssLayout').on('hashchange.zrOssLayout', function () {
			if (String(window.location.hash || '').indexOf('zr_oss_setup') !== -1) {
				refreshVisibleOssSection();
			}
		});
		patchSectionReshow();
	}

	function startOssUi() {
		if (window.__zrOssUiStarted) {
			return;
		}
		window.__zrOssUiStarted = 1;
		bindProvider();
		bindSearchClear();
		patchDepsRefresh();
		patchSectionReshow();
		scheduleLayout();
		var n = 0;
		var tick = window.setInterval(function () {
			n += 1;
			if (ossScope().length) {
				runLayout();
			}
			var root = document.querySelector('.pili-inst-zaprocket');
			var onOss = ossScope().length && root && root.getAttribute('data-zr-oss-on') === '1';
			if (onOss || n > 20) {
				window.clearInterval(tick);
			}
		}, 200);
	}

	if (document.readyState === 'loading') {
		$(startOssUi);
	} else {
		startOssUi();
	}

	document.addEventListener(
		'click',
		function (e) {
			var btn = e.target && e.target.closest ? e.target.closest('.pili-submit') : null;
			if (!btn) {
				return;
			}
			if (!document.querySelector('.pili-inst-zaprocket [data-depend-id="zr_oss_provider"]') && !document.querySelector('[name*="[zr_oss_provider]"]')) {
				return;
			}
			if (!isOn('zr_oss_enable')) {
				return;
			}
			var provider = currentProvider();
			if (provider === 'tencent' && !String(fieldVal('zr_oss_app_id') || '').replace(/\D+/g, '')) {
				e.preventDefault();
				e.stopPropagation();
				toast('error', i18n('needAppId') || zaprocketOss.i18n.needAppId);
				return;
			}
			if (prefixIllegal(fieldVal('zr_oss_object_prefix'))) {
				e.preventDefault();
				e.stopPropagation();
				toast('error', i18n('prefixBad') || zaprocketOss.i18n.prefixBad);
				return;
			}
			if (provider === 'r2' && !fieldVal('zr_oss_custom_domain')) {
				toast('error', i18n('r2Domain') || (zaprocketOss.i18n && zaprocketOss.i18n.r2Domain) || '');
			}
		},
		true
	);

	$(document).on('click', '#zr-oss-test, [data-pili-table-action-key="oss-test"]', function (e) {
		e.preventDefault();
		var $btn = $(this);
		if (typeof zaprocketOss === 'undefined') {
			return;
		}
		if (window.PiliTextField && typeof window.PiliTextField.checkForm === 'function') {
			var form = $btn.closest('form')[0] || document.querySelector('form');
			if (form && !window.PiliTextField.checkForm(form, true)) {
				return;
			}
		}
		setBusy($btn, true);
		var $panel = $btn.closest('.zr-oss-test').find('.zr-oss-test__result').first();
		if ($panel.length) {
			$panel.removeAttr('hidden').attr('data-ok', '').html('<p class="zr-oss-test__msg">' + esc($btn.attr('data-loading-label') || '…') + '</p>');
		}
		$.post(zaprocketOss.ajax, {
			action: 'zaprocket_oss_test',
			nonce: zaprocketOss.nonce,
			zr_oss_provider: fieldVal('zr_oss_provider'),
			zr_oss_bucket: fieldVal('zr_oss_bucket'),
			zr_oss_app_id: fieldVal('zr_oss_app_id'),
			zr_oss_region: fieldVal('zr_oss_region'),
			zr_oss_region_aliyun: fieldVal('zr_oss_region_aliyun'),
			zr_oss_region_tencent: fieldVal('zr_oss_region_tencent'),
			zr_oss_endpoint: fieldVal('zr_oss_endpoint'),
			zr_oss_custom_domain: fieldVal('zr_oss_custom_domain'),
			zr_oss_access_key: fieldVal('zr_oss_access_key'),
			zr_oss_secret_key: fieldVal('zr_oss_secret_key')
		})
			.done(function (res) {
				var msg = (res && res.data && res.data.message) || (res && res.success ? zaprocketOss.i18n.ok : zaprocketOss.i18n.fail);
				showTestResult(!!(res && res.success), msg);
			})
			.fail(function () {
				showTestResult(false, zaprocketOss.i18n.fail);
			})
			.always(function () {
				setBusy($btn, false);
			});
	});

	$(document).on('click', '.zr-oss-test__fold', function (e) {
		e.preventDefault();
		var $b = $(this);
		var $msg = $b.siblings('.zr-oss-test__msg');
		var full = String($b.attr('data-full') || '');
		var open = $b.attr('data-open') === '1';
		if (open) {
			$msg.text(full.length > 96 ? full.slice(0, 96) + '…' : full);
			$b.attr('data-open', '0').text(i18n('foldMore'));
		} else {
			$msg.text(full);
			$b.attr('data-open', '1').text(i18n('foldLess'));
		}
	});

	$(document).on('keydown', '[data-field-id="zr_oss_replace_old"] .pili-input, [data-field-id="zr_oss_replace_new"] .pili-input', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$('#zr-oss-replace').trigger('click');
		}
	});

	$(document).on('click', '#zr-oss-replace, [data-pili-table-action-key="oss-replace"]', function (e) {
		e.preventDefault();
		if (typeof zaprocketOss === 'undefined') {
			return;
		}
		var $btn = $(this);
		var oldUrl = fieldVal('zr_oss_replace_old');
		var newUrl = fieldVal('zr_oss_replace_new');
		if (!oldUrl || !newUrl) {
			toast('error', zaprocketOss.i18n.replaceNeed);
			return;
		}
		if (oldUrl === newUrl) {
			toast('error', zaprocketOss.i18n.replaceSame);
			return;
		}
		if (!/^https?:\/\//i.test(oldUrl) || !/^https?:\/\//i.test(newUrl)) {
			toast('error', zaprocketOss.i18n.replaceBad);
			return;
		}
		setBusy($btn, true);
		var $panel = $btn.closest('.zr-oss-replace').find('.zr-oss-replace__result').first();
		if ($panel.length) {
			$panel.removeAttr('hidden').attr('data-ok', '').html('<p class="zr-oss-test__msg">' + esc($btn.attr('data-loading-label') || '…') + '</p>');
		}
		$.post(zaprocketOss.ajax, {
			action: 'zaprocket_oss_replace_urls',
			nonce: zaprocketOss.nonce,
			old_url: oldUrl,
			new_url: newUrl
		})
			.done(function (res) {
				var msg = (res && res.data && res.data.message) || (res && res.success ? zaprocketOss.i18n.replaceOk : zaprocketOss.i18n.fail);
				if ($panel.length) {
					$panel.attr('data-ok', res && res.success ? '1' : '0').html('<p class="zr-oss-test__msg">' + esc(msg) + '</p>');
				}
				toast(res && res.success ? 'success' : 'error', msg);
			})
			.fail(function () {
				toast('error', zaprocketOss.i18n.fail);
			})
			.always(function () {
				setBusy($btn, false);
			});
	});

	$(document).on('click', '[data-pili-table-action-key="oss-retry"], [data-pili-table-action-key="oss-dismiss"]', function (e) {
		e.preventDefault();
		if (typeof zaprocketOss === 'undefined') {
			return;
		}
		var $btn = $(this);
		var key = $btn.attr('data-pili-table-action-key');
		var action = key === 'oss-retry' ? 'zaprocket_oss_retry_log' : 'zaprocket_oss_dismiss_log';
		setBusy($btn, true);
		$.post(zaprocketOss.ajax, {
			action: action,
			nonce: zaprocketOss.nonce,
			index: $btn.attr('data-id')
		})
			.done(function (res) {
				if (res && res.success) {
					$btn.closest('tr').remove();
					toast('success', zaprocketOss.i18n.done);
				} else {
					toast('error', zaprocketOss.i18n.fail);
					setBusy($btn, false);
				}
			})
			.fail(function () {
				toast('error', zaprocketOss.i18n.fail);
				setBusy($btn, false);
			});
	});
}

(function bootOssAdmin(start) {
	'use strict';
	if (typeof window.jQuery === 'undefined') {
		if (Date.now() - start < 10000) {
			window.setTimeout(function () {
				bootOssAdmin(start);
			}, 20);
		}
		return;
	}
	ossAdminMain(window.jQuery);
})(Date.now());
