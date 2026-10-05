/**
 * XUN loading field — 展示 / 容器 loading 态 API。
 */
(function ($, window) {
	'use strict';

	function findText($root) {
		var $text = $root.find('.pili-loading-preview__text, [id$="-text"], [id$="-count-text"]').first();
		return $text.length ? $text : $root;
	}

	function findSlot($root) {
		return $root.find('.pili-loading-field--slot').first();
	}

	/**
	 * @param {jQuery|string} target Container or selector.
	 * @param {boolean} loading Loading on/off.
	 * @param {string} [text] Optional status text.
	 */
	function setContainerLoading(target, loading, text) {
		var $root = target instanceof $ ? target : $(target);
		if (!$root.length) {
			return;
		}

		$root.toggleClass('is-loading', !!loading);
		$root.attr('aria-busy', loading ? 'true' : 'false');
		$root.attr('data-state', loading ? 'loading' : 'idle');

		var $slot = findSlot($root);
		if ($slot.length) {
			$slot.toggleClass('is-hidden', !loading);
			$slot.attr('aria-hidden', loading ? 'false' : 'true');
		}

		if (typeof text === 'string' && text !== '') {
			findText($root).text(text);
		}

		$root.trigger('xun:loading:change', [loading, text]);
	}

	function initLoading($root) {
		if (!$root || !$root.length) {
			return;
		}
		$root.find('.pili-loading-field[data-field-id]').each(function () {
			var $el = $(this);
			if ($el.data('xunLoadingInited')) {
				return;
			}
			$el.data('xunLoadingInited', true);
		});
	}

	PILI.registerBoot('loading', function ($root) {
		if (!$root || !$root.length) {
			if (window.console && typeof console.warn === 'function') {
				console.warn('PILI.boot(loading): missing container, no-op');
			}
			return;
		}
		initLoading($root);
	});

	$(document).ready(function () {
		var $scope = $('.pili-framework-page, .pili-options, [data-pili-loading-root]').first();
		if ($scope.length) {
			initLoading($scope);
		}
	});

	$(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
		if ($container && $container.length) {
			PILI.boot('loading', $container);
		}
	});

	window.PiliXunLoading = {
		setContainerLoading: setContainerLoading,
		setLoading: setContainerLoading
	};
})(jQuery, window);
