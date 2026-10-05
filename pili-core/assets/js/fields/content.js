/**
 * XUN content field — dismissible callouts (localStorage).
 */
(function (window, document) {
	'use strict';

	var STORAGE_PREFIX = 'xun_content_dismiss:';

	function storageKey(key) {
		return STORAGE_PREFIX + String(key || '');
	}

	function isDismissed(key) {
		try {
			return window.localStorage.getItem(storageKey(key)) === '1';
		} catch (e) {
			return false;
		}
	}

	function setDismissed(key, dismissed) {
		try {
			if (dismissed) {
				window.localStorage.setItem(storageKey(key), '1');
			} else {
				window.localStorage.removeItem(storageKey(key));
			}
		} catch (e) {}
	}

	function applyState(host) {
		if (!host || !host.getAttribute) return;
		var key = host.getAttribute('data-pili-content-dismiss-key') || '';
		if (!key) return;
		var callout = host.querySelector('.pili-content-callout');
		var restore = host.querySelector('.pili-content-dismiss-restore');
		var dismissed = isDismissed(key);
		host.classList.toggle('is-dismissed', dismissed);
		if (callout) {
			callout.hidden = !!dismissed;
		}
		if (restore) {
			restore.hidden = !dismissed;
		}
	}

	function scan(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var hosts = scope.querySelectorAll
			? scope.querySelectorAll('[data-pili-content-dismissible="1"]')
			: [];
		for (var i = 0; i < hosts.length; i++) {
			applyState(hosts[i]);
		}
		// 若 root 本身就是 host。
		if (root && root.getAttribute && root.getAttribute('data-pili-content-dismissible') === '1') {
			applyState(root);
		}
	}

	function onClick(e) {
		var t = e.target;
		if (!t || !t.closest) return;

		var dismissBtn = t.closest('[data-pili-content-dismiss-btn]');
		if (dismissBtn) {
			var host = dismissBtn.closest('[data-pili-content-dismissible="1"]');
			if (!host) return;
			e.preventDefault();
			var key = host.getAttribute('data-pili-content-dismiss-key') || '';
			setDismissed(key, true);
			applyState(host);
			// 同步同 key 的其它实例（授权状态 / 激活认证共用）。
			scan(document);
			return;
		}

		var restoreBtn = t.closest('[data-pili-content-restore-btn]');
		if (restoreBtn) {
			var host2 = restoreBtn.closest('[data-pili-content-dismissible="1"]');
			if (!host2) return;
			e.preventDefault();
			var key2 = host2.getAttribute('data-pili-content-dismiss-key') || '';
			setDismissed(key2, false);
			scan(document);
		}
	}

	function boot() {
		scan(document);
		document.addEventListener('click', onClick, false);
		document.addEventListener('xun:section-loaded', function (ev) {
			var detail = ev && ev.detail ? ev.detail : null;
			var root = detail && detail.el ? detail.el : document;
			scan(root);
		});
		// 懒加载分区插入后的兜底。
		if (typeof MutationObserver !== 'undefined') {
			var obs = new MutationObserver(function (mutations) {
				for (var i = 0; i < mutations.length; i++) {
					var nodes = mutations[i].addedNodes || [];
					for (var j = 0; j < nodes.length; j++) {
						var n = nodes[j];
						if (n && n.nodeType === 1) {
							scan(n);
						}
					}
				}
			});
			obs.observe(document.documentElement, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
