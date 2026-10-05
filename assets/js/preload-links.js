/**
 * Hover prefetch for same-origin links.
 */
(function () {
	'use strict';
	if (!document.addEventListener) {
		return;
	}
	var seen = Object.create(null);
	document.addEventListener(
		'mouseover',
		function (e) {
			var t = e.target;
			if (!t || !t.closest) {
				return;
			}
			var a = t.closest('a[href]');
			if (!a) {
				return;
			}
			var href = a.href;
			if (!href || href.indexOf(location.origin) !== 0 || href.indexOf('#') !== -1) {
				return;
			}
			if (a.hasAttribute('download') || (a.getAttribute('rel') || '').indexOf('nofollow') !== -1) {
				return;
			}
			if (seen[href]) {
				return;
			}
			seen[href] = 1;
			var l = document.createElement('link');
			l.rel = 'prefetch';
			l.href = href;
			document.head.appendChild(l);
		},
		{ capture: true, passive: true }
	);
})();
