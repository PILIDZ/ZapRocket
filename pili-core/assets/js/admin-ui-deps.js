/**
 * PILI admin-ui-deps — 字段 dependency 条件显隐
 *
 * 读取 [data-dependency] JSON（encode_field_dependency_attr 输出），
 * 按控制字段当前值显示/隐藏被控字段。移植自 XUN admin-ui-deps。
 */
(function ($) {
	'use strict';

	var TRUE_TOKENS = { '1': 1, true: 1, on: 1, yes: 1 };
	var FALSE_TOKENS = { '0': 1, false: 1, off: 1, no: 1, '': 1 };

	function formRoot() {
		var $form = $('#pili-options-form');
		if ($form.length) {
			return $form;
		}
		return $('form.pili-form[data-option-id]').first();
	}

	var PiliAdminUiDeps = {
		_moTimer: null,

		init: function () {
			this.ensureStyle();
			this.bind();
			this.refresh();
		},

		ensureStyle: function () {
			if (document.getElementById('pili-admin-ui-deps-style')) {
				return;
			}
			var style = document.createElement('style');
			style.id = 'pili-admin-ui-deps-style';
			style.textContent = '.pili-dep-hidden{display:none!important;}';
			document.head.appendChild(style);
		},

		bind: function () {
			var self = this;
			var $doc = $(document);

			$doc.on('change input', '#pili-options-form, form.pili-form', function (e) {
				self.refreshNear(e.target);
			});

			$doc.on('xun:select:changed', function (e) {
				self.refreshNear(e.target);
			});

			$doc.on('xun:switch:change', function (e) {
				self.refreshNear(e.target);
			});

			$doc.on('pili:section:loaded pili:field:added pili:field:loaded', function (e, $container) {
				if ($container && $container.jquery && $container.length) {
					self.refreshIn($container);
					return;
				}
				self.refresh();
			});

			var form = document.getElementById('pili-options-form') || document.querySelector('form.pili-form[data-option-id]');
			if (form && window.MutationObserver) {
				var mo = new MutationObserver(function (mutations) {
					var relevant = false;
					for (var i = 0; i < mutations.length; i++) {
						if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
							relevant = true;
							break;
						}
					}
					if (!relevant) {
						return;
					}
					clearTimeout(self._moTimer);
					self._moTimer = setTimeout(function () {
						self.refresh();
					}, 40);
				});
				mo.observe(form, { childList: true, subtree: true });
			}
		},

		refresh: function () {
			this.refreshIn(formRoot());
			this.refreshIn($('.pili-framework-page'));
		},

		refreshNear: function (el) {
			var $scope = this.resolveScope($(el));
			if ($scope && $scope.length) {
				this.refreshIn($scope);
			} else {
				this.refresh();
			}
		},

		resolveScope: function ($el) {
			if (!$el || !$el.length) {
				return formRoot();
			}
			var $item = $el.closest('.pili-repeater-item');
			if ($item.length && !$item.closest('.pili-repeater-template').length) {
				return $item;
			}
			var $section = $el.closest('.pili-section-wrapper');
			if ($section.length) {
				return $section;
			}
			return formRoot();
		},

		refreshIn: function ($root) {
			var self = this;
			if (!$root || !$root.length) {
				return;
			}
			var $nodes = $root.find('[data-dependency]');
			if ($root.is('[data-dependency]')) {
				$nodes = $nodes.add($root);
			}
			$nodes.each(function () {
				var $field = $(this);
				if ($field.closest('.pili-repeater-template').length) {
					return;
				}
				self.apply($field);
			});
		},

		parseRules: function (raw) {
			if (!raw) {
				return [];
			}
			try {
				var parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
				return $.isArray(parsed) ? parsed : [];
			} catch (err) {
				return [];
			}
		},

		apply: function ($field) {
			var rules = this.parseRules($field.attr('data-dependency'));
			if (!rules.length) {
				return;
			}
			var wasHidden = $field.hasClass('pili-dep-hidden');
			var visible = true;
			for (var i = 0; i < rules.length; i++) {
				if (!this.evalRule($field, rules[i])) {
					visible = false;
					break;
				}
			}
			$field.toggleClass('pili-dep-hidden', !visible);
			$field.attr('aria-hidden', visible ? 'false' : 'true');
			$field.find('input, select, textarea, button').each(function () {
				var $el = $(this);
				if (visible) {
					if ($el.data('piliDepDisabled')) {
						$el.prop('disabled', false);
						$el.removeData('piliDepDisabled');
						$el.removeAttr('data-pili-dep-disabled');
					}
				} else if (!$el.prop('disabled')) {
					$el.data('piliDepDisabled', true);
					$el.attr('data-pili-dep-disabled', '1');
					$el.prop('disabled', true);
				}
			});

			if (visible && wasHidden && window.PILI && typeof window.PILI.bootLazySectionFields === 'function') {
				window.PILI.bootLazySectionFields($field, { skip: ['chart'] });
			}
		},

		findController: function ($dependent, fieldId) {
			fieldId = String(fieldId || '');
			if (!fieldId) {
				return $();
			}

			var $item = $dependent.closest('.pili-repeater-item');
			if ($item.length && !$item.closest('.pili-repeater-template').length) {
				var $content = $item.children('.pili-repeater-item-content').first();
				var $direct = $content.children('[data-field-id="' + fieldId + '"]');
				if ($direct.length) {
					return $direct.first();
				}
				var itemEl = $item[0];
				var $nested = $content.find('[data-field-id="' + fieldId + '"]').filter(function () {
					return $(this).closest('.pili-repeater-item')[0] === itemEl;
				});
				if ($nested.length) {
					return $nested.first();
				}
			}

			var $section = $dependent.closest('.pili-section-wrapper');
			var $scope = $section.length ? $section : formRoot();
			if (!$scope.length) {
				$scope = $('.pili-framework-page');
			}
			return $scope
				.find('[data-field-id="' + fieldId + '"]')
				.filter(function () {
					return !$(this).closest('.pili-repeater-item').length;
				})
				.first();
		},

		getFieldValue: function ($ctrl) {
			if (!$ctrl || !$ctrl.length) {
				return '';
			}
			var type = String($ctrl.attr('data-field-type') || '').toLowerCase();

			if (type === 'switch') {
				var $sw = $ctrl.find('[data-pili-switch] input[type="hidden"]').first();
				if (!$sw.length) {
					$sw = $ctrl.find('input[type="hidden"]').first();
				}
				if ($sw.length) {
					return String($sw.val() == null ? '' : $sw.val());
				}
				var $role = $ctrl.find('[role="switch"]').first();
				if ($role.length && ($role.attr('aria-checked') === 'true' || $role.attr('data-checked') === 'true')) {
					return '1';
				}
				return '';
			}

			if (type === 'select') {
				var $sel = $ctrl.find('select').first();
				return $sel.length ? String($sel.val() == null ? '' : $sel.val()) : '';
			}

			if (type === 'radio') {
				var $radio = $ctrl.find('input[type="radio"]:checked').first();
				return $radio.length ? String($radio.val()) : '';
			}

			if (type === 'checkbox') {
				var vals = [];
				$ctrl.find('input[type="checkbox"]:checked').each(function () {
					vals.push(String($(this).val()));
				});
				return vals;
			}

			var $input = $ctrl
				.find('input, select, textarea')
				.filter(function () {
					var t = String(this.type || '').toLowerCase();
					return t !== 'button' && t !== 'submit' && t !== 'file';
				})
				.first();
			return $input.length ? String($input.val() == null ? '' : $input.val()) : '';
		},

		isTrueToken: function (v) {
			return !!TRUE_TOKENS[String(v).toLowerCase()];
		},

		isFalseToken: function (v) {
			return Object.prototype.hasOwnProperty.call(FALSE_TOKENS, String(v).toLowerCase());
		},

		isTruthy: function (v) {
			return this.isTrueToken(v);
		},

		isFalsy: function (v) {
			return this.isFalseToken(v);
		},

		evalRule: function ($dependent, rule) {
			if (!rule || typeof rule !== 'object') {
				return true;
			}
			var fieldId = rule.field;
			var op = String(rule.op || '==');
			var expected = rule.value;
			var actual = this.getFieldValue(this.findController($dependent, fieldId));

			if ($.isArray(actual)) {
				var expStr = expected == null ? '' : String(expected);
				if (op === '==' || op === '=') {
					if (this.isTrueToken(expStr)) {
						return actual.length > 0;
					}
					return $.inArray(expStr, actual) !== -1;
				}
				if (op === '!=' || op === '!==' || op === 'not') {
					if (this.isTrueToken(expStr)) {
						return actual.length === 0;
					}
					return $.inArray(expStr, actual) === -1;
				}
				if (op === 'empty') {
					return actual.length === 0;
				}
				if (op === '!empty' || op === 'not_empty') {
					return actual.length > 0;
				}
			}

			var a = String(actual == null ? '' : actual);
			var e = expected == null ? '' : String(expected);

			if (op === '==' || op === '=') {
				if (this.isTrueToken(e)) {
					return this.isTruthy(a);
				}
				if (this.isFalseToken(e) && e !== '') {
					return this.isFalsy(a);
				}
				return a === e;
			}

			if (op === '!=' || op === '!==' || op === 'not') {
				if (this.isTrueToken(e)) {
					return !this.isTruthy(a);
				}
				if (this.isFalseToken(e) && e !== '') {
					return !this.isFalsy(a);
				}
				return a !== e;
			}

			if (op === 'empty') {
				return a === '';
			}
			if (op === '!empty' || op === 'not_empty') {
				return a !== '';
			}
			if (op === 'any' || op === 'in') {
				var list = e.split(',').map(function (s) {
					return $.trim(s);
				});
				return $.inArray(a, list) !== -1;
			}

			return a === e;
		}
	};

	$(function () {
		PiliAdminUiDeps.init();
	});

	window.PiliAdminUiDeps = PiliAdminUiDeps;
})(jQuery);
