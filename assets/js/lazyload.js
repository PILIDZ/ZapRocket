/**
 * CSS background lazyload + YouTube facade.
 */
(function () {
	'use strict';

	function applyBg(el) {
		var url = el.getAttribute('data-zr-bg');
		if (!url) {
			return;
		}
		el.style.backgroundImage = 'url("' + url.replace(/"/g, '\\"') + '")';
		el.removeAttribute('data-zr-bg');
	}

	function runBg() {
		var nodes = document.querySelectorAll('[data-zr-bg]');
		if (!nodes.length) {
			return;
		}
		if (!('IntersectionObserver' in window)) {
			Array.prototype.forEach.call(nodes, applyBg);
			return;
		}
		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (en) {
					if (!en.isIntersecting) {
						return;
					}
					applyBg(en.target);
					io.unobserve(en.target);
				});
			},
			{ rootMargin: '200px 0px' }
		);
		Array.prototype.forEach.call(nodes, function (el) {
			io.observe(el);
		});
	}

	document.addEventListener(
		'click',
		function (e) {
			var t = e.target;
			if (!t || !t.closest) {
				return;
			}
			var btn = t.closest('.zr-yt__play');
			if (!btn) {
				return;
			}
			var box = btn.closest('.zr-yt');
			if (!box) {
				return;
			}
			var id = box.getAttribute('data-zr-yt') || '';
			if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
				return;
			}
			e.preventDefault();
			var ifr = document.createElement('iframe');
			ifr.src =
				'https://www.youtube-nocookie.com/embed/' +
				id +
				'?autoplay=1';
			ifr.setAttribute('allowfullscreen', '');
			ifr.setAttribute(
				'allow',
				'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
			);
			ifr.setAttribute(
				'style',
				'position:absolute;inset:0;width:100%;height:100%;border:0'
			);
			ifr.setAttribute('title', 'YouTube');
			box.innerHTML = '';
			box.appendChild(ifr);
		},
		true
	);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runBg);
	} else {
		runBg();
	}
})();
