/**
 * inputs_grid：声明式乘积取整（product_round）前端同步。
 * 与 PILI_Field_inputs_grid::sanitize_stored_value 规则一致；兼容旧 xun 类名/事件。
 *
 * @package Pili\Core
 */
(function ($) {
	'use strict';

	function parseNum(str) {
		var s = String(str == null ? '' : str).trim().replace(',', '.');
		if (s === '') {
			return NaN;
		}
		return parseFloat(s);
	}

	function allFactorStringsEmpty($wrap, factorKeys) {
		var i, k, $in;
		for (i = 0; i < factorKeys.length; i++) {
			k = factorKeys[i];
			$in = $wrap.find('input[name*="[' + k + ']"]').first();
			if (($in.val() || '').trim() !== '') {
				return false;
			}
		}
		return true;
	}

	function recalcOne($wrap, cfg) {
		if (!cfg || cfg.type !== 'product_round' || !cfg.factor_keys || !cfg.result_key) {
			return;
		}
		var factorKeys = cfg.factor_keys;
		var resultKey = cfg.result_key;
		var clampMax = typeof cfg.clamp_max === 'number' && cfg.clamp_max > 0 ? cfg.clamp_max : 999999;
		var $out = $wrap.find('input[name*="[' + resultKey + ']"]').first();
		if (!$out.length) {
			return;
		}
		$out.prop('readonly', true);

		var i, fk, $in, t, prod, pts, ok;
		prod = 1;
		ok = true;
		for (i = 0; i < factorKeys.length; i++) {
			fk = factorKeys[i];
			$in = $wrap.find('input[name*="[' + fk + ']"]').first();
			t = parseNum($in.val());
			if (!isFinite(t) || t <= 0) {
				ok = false;
				break;
			}
			prod *= t;
		}
		if (ok) {
			pts = Math.round(prod);
			if (!isFinite(pts)) {
				pts = 0;
			}
			pts = Math.max(0, Math.min(clampMax, pts));
			$out.val(String(pts));
		} else {
			if (cfg.preserve_result_if_factors_empty && allFactorStringsEmpty($wrap, factorKeys)) {
				return;
			}
			$out.val('0');
		}

		var $item = $wrap.closest('.pili-repeater-item, .xun-repeater-item');
		$(document).trigger('pili:inputs_grid:recalculated', [$item]);
		$(document).trigger('pilidoc:inputs_grid:recalculated', [$item]);
	}

	function readComputeCfg($wrap) {
		var raw =
			$wrap.attr('data-pili-inputs-grid-compute') ||
			$wrap.attr('data-pilidoc-inputs-grid-compute') ||
			'';
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (err) {
			return null;
		}
	}

	function bindWrap($wrap) {
		var cfg = readComputeCfg($wrap);
		if (!cfg || cfg.type !== 'product_round' || !$.isArray(cfg.factor_keys)) {
			return;
		}
		var sel = $.map(cfg.factor_keys, function (k) {
			return 'input[name*="[' + k + ']"]';
		}).join(',');
		if (!sel) {
			return;
		}
		recalcOne($wrap, cfg);
		$wrap
			.off('input.piliIgCompute change.piliIgCompute', sel)
			.on('input.piliIgCompute change.piliIgCompute', sel, function () {
				recalcOne($wrap, cfg);
			});
	}

	function scan($root) {
		var $ctx = $root && $root.length ? $root : $(document);
		$ctx
			.find(
				'.pili-inputs-grid-field[data-pili-inputs-grid-compute], .xun-inputs-grid-field[data-pilidoc-inputs-grid-compute], .xun-inputs-grid-field[data-pili-inputs-grid-compute]'
			)
			.each(function () {
				bindWrap($(this));
			});
	}

	$(function () {
		scan($(document));
	});

	window.piliInputsGridComputeBoot = function ($root) {
		scan($root && $root.length ? $root : $(document));
	};
	window.xunInputsGridComputeBoot = window.piliInputsGridComputeBoot;

	$(document).on('pili:field:loaded xun:field:loaded', function () {
		scan($(document));
	});

	$(document).on('pili:field:added xun:field:added', function (e, $container) {
		if (!$container || !$container.length) {
			return;
		}
		setTimeout(function () {
			scan($container);
		}, 0);
	});

	$(document).on('pili:repeater:item:toggled xun:repeater:item:toggled', function (e, data) {
		var $item = data && data.item ? $(data.item) : null;
		if (!$item || !$item.length) {
			return;
		}
		scan($item);
	});
})(jQuery);
