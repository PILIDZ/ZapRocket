/**
 * Load delayed scripts after first input, or after 8s.
 */
(function () {
	'use strict';

	var loaded = false;
	var events = ['keydown', 'mousedown', 'mousemove', 'touchstart', 'touchmove', 'wheel', 'click'];

	function loadAll() {
		if (loaded) {
			return;
		}
		loaded = true;
		events.forEach(function (name) {
			window.removeEventListener(name, loadAll, true);
		});
		var nodes = document.querySelectorAll('script[data-zr-delay]');
		var i = 0;
		function next() {
			if (i >= nodes.length) {
				return;
			}
			var old = nodes[i++];
			var neu = document.createElement('script');
			var attrs = old.attributes;
			var a;
			for (a = 0; a < attrs.length; a++) {
				if (attrs[a].name === 'type' || attrs[a].name === 'data-zr-delay') {
					continue;
				}
				neu.setAttribute(attrs[a].name, attrs[a].value);
			}
			neu.onload = neu.onerror = next;
			old.parentNode.replaceChild(neu, old);
			if (!neu.src) {
				next();
			}
		}
		next();
	}

	events.forEach(function (name) {
		window.addEventListener(name, loadAll, { capture: true, passive: true });
	});
	window.setTimeout(loadAll, 8000);
})();
