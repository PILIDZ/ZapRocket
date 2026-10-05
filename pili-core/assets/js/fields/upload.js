/**
 * PILI Framework Upload Field
 *
 * 本地文件拖拽/选择（不依赖媒体库）。选中后触发 pili:upload:change。
 *
 * @package PILI Framework
 * @since   1.1.8
 */
(function ($) {
	'use strict';

	function formatBytes(bytes) {
		var n = Number(bytes) || 0;
		if (n < 1024) {
			return n + ' B';
		}
		if (n < 1048576) {
			return (n / 1024).toFixed(1) + ' KB';
		}
		return (n / 1048576).toFixed(1) + ' MB';
	}

	function fileIconSvg() {
		return (
			'<svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>' +
			'</svg>'
		);
	}

	function uploadBag() {
		return (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('upload')) || {};
	}

	class PiliUploadField {
		/**
		 * @param {jQuery} $container
		 */
		constructor($container) {
			this.$container = $container;
			this.$wrap = $container.find('.pili-upload-container');
			this.$drop = $container.find('.pili-upload-dropzone');
			this.$input = $container.find('.pili-upload-native');
			this.$list = $container.find('.pili-upload-file-list');
			this.$clear = $container.find('.pili-upload-clear-btn');
			this.$browse = $container.find('.pili-upload-browse-btn');
			this.maxFiles = parseInt($container.data('max-files'), 10) || 1;
			this.maxFileSize = parseInt($container.data('max-file-size'), 10) || 0;
			this.showFilesize = this.$list.data('show-filesize') === 1 || this.$list.data('show-filesize') === '1';
			this.files = [];
			this.bind();
		}

		bind() {
			var self = this;

			this.$browse.on('click', function (e) {
				e.preventDefault();
				self.$input.trigger('click');
			});

			this.$drop.on('click', function (e) {
				e.preventDefault();
				self.$input.trigger('click');
			});

			this.$drop.on('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					self.$input.trigger('click');
				}
			});

			this.$input.on('change', function () {
				self.applyFileList(this.files);
			});

			this.$clear.on('click', function (e) {
				e.preventDefault();
				self.clear();
			});

			this.$list.on('click', '.pili-upload-remove-file', function (e) {
				e.preventDefault();
				var idx = parseInt($(this).data('index'), 10);
				self.removeAt(idx);
			});

			this.$drop.on('dragenter dragover', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.$drop.addClass('border-blue-500 bg-blue-50');
			});

			this.$drop.on('dragleave drop', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.$drop.removeClass('border-blue-500 bg-blue-50');
			});

			this.$drop.on('drop', function (e) {
				var dt = e.originalEvent && e.originalEvent.dataTransfer;
				if (!dt || !dt.files || !dt.files.length) {
					return;
				}
				self.applyFileList(dt.files);
			});
		}

		/**
		 * @param {FileList|File[]} list
		 */
		applyFileList(list) {
			var incoming = Array.prototype.slice.call(list || []);
			if (!incoming.length) {
				return;
			}

			var next = this.maxFiles > 1 ? this.files.slice() : [];
			var i;
			var f;
			for (i = 0; i < incoming.length; i++) {
				f = incoming[i];
				if (this.maxFileSize > 0 && f.size > this.maxFileSize) {
					var uploadI18n = (uploadBag().i18n) ? uploadBag().i18n : {};
					var sizeTemplate = uploadI18n.fileTooLarge || 'File “%1$s” exceeds the size limit (%2$s)';
					var sizeMsg = sizeTemplate.replace('%1$s', f.name).replace('%2$s', formatBytes(this.maxFileSize));
					if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
						window.PilipostUi.toast(sizeMsg, 'warning');
					} else if (window.PiliXunToast && typeof window.PiliXunToast.show === 'function') {
						window.PiliXunToast.show({ message: sizeMsg, type: 'warning' });
					} else if (typeof PILI.alert === 'function') {
						PILI.alert({ title: uploadI18n.noticeTitle || '提示', message: sizeMsg, type: 'warning' });
					}
					continue;
				}
				next.push(f);
				if (next.length >= this.maxFiles) {
					break;
				}
			}

			if (next.length > this.maxFiles) {
				next = next.slice(0, this.maxFiles);
			}

			this.files = next;
			this.syncNativeInput();
			this.renderList();
			this.emitChange();
		}

		removeAt(index) {
			if (index < 0 || index >= this.files.length) {
				return;
			}
			this.files.splice(index, 1);
			this.syncNativeInput();
			this.renderList();
			this.emitChange();
		}

		clear() {
			this.files = [];
			this.syncNativeInput();
			this.renderList();
			this.emitChange();
		}

		syncNativeInput() {
			var input = this.$input.get(0);
			if (!input) {
				return;
			}
			try {
				var dt = new DataTransfer();
				var i;
				for (i = 0; i < this.files.length; i++) {
					dt.items.add(this.files[i]);
				}
				input.files = dt.files;
			} catch (err) {
				// 旧浏览器无法写回 FileList；仍保留 this.files 供事件消费。
				if (!this.files.length) {
					input.value = '';
				}
			}
		}

		renderList() {
			var self = this;
			if (!this.$list.length) {
				this.$wrap.toggleClass('pili-upload-empty', this.files.length === 0);
				this.$clear.toggleClass('hidden', this.files.length === 0);
				return;
			}

			if (!this.files.length) {
				this.$list.empty().addClass('hidden');
				this.$wrap.addClass('pili-upload-empty');
				this.$clear.addClass('hidden');
				return;
			}

			var html = '';
			this.files.forEach(function (file, index) {
				html +=
					'<li class="flex items-center justify-between gap-3 px-3 py-2 bg-white border border-gray-200 rounded-lg">' +
					'<div class="flex items-center gap-2 min-w-0">' +
					fileIconSvg() +
					'<div class="min-w-0">' +
					'<div class="text-sm font-medium text-gray-900 truncate">' +
					$('<div/>').text(file.name).html() +
					'</div>';
				if (self.showFilesize) {
					html +=
						'<div class="text-xs text-gray-500">' +
						formatBytes(file.size) +
						'</div>';
				}
				html +=
					'</div></div>' +
					'<button type="button" class="pili-upload-remove-file text-sm text-red-600 hover:text-red-800" data-index="' +
					index +
					'">' + ((uploadBag().i18n && uploadBag().i18n.remove) || '移除') + '</button></li>';
			});

			this.$list.html(html).removeClass('hidden');
			this.$wrap.removeClass('pili-upload-empty');
			this.$clear.removeClass('hidden');
		}

		emitChange() {
			this.$container.trigger('pili:upload:change', [this.files.slice()]);
		}

		getFiles() {
			return this.files.slice();
		}
	}

	function initAll(root) {
		$(root)
			.find('.pili-upload-field')
			.addBack('.pili-upload-field')
			.each(function () {
				var $el = $(this);
				if ($el.data('xunUpload')) {
					return;
				}
				$el.data('xunUpload', new PiliUploadField($el));
			});
	}

	$(function () {
		initAll(document);
	});

	$(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
		initAll($container && $container.length ? $container : document);
	});

	window.PILI = window.PILI || {};
	window.PILI.UploadField = PiliUploadField;
	PILI.registerBoot('upload', initAll);
})(jQuery);
