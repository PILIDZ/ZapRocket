/**
 * PILI Framework 选择字段 JavaScript
 * 
 * 提供现代化的选择字段交互功能，包括：
 * - 下拉列表展开/收起
 * - 键盘导航支持
 * - 搜索过滤功能
 * - 多选支持
 * - 无障碍访问优化
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    /**
     * 选择字段类
     * 
     * 管理单个选择字段的所有交互功能
     */
    class XunSelectField {
        
        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$button = $container.find('.pili-select-button');
            this.$dropdown = $container.find('.pili-select-dropdown');
            this.$search = $container.find('.pili-select-search');
            this.$options = $container.find('.pili-select-options');
            this.$nativeSelect = $container.find('.pili-select-native');
            this.$display = $container.find('.pili-select-display');
            this.$arrow = $container.find('.pili-select-arrow');
            this.$clear = $container.find('.pili-select-clear');
            this.$noResults = $container.find('.pili-select-no-results');
            this.$loading = $container.find('.pili-select-loading');
            
            this.config = {
                fieldId: $container.data('field-id'),
                multiple: this.readBoolAttr($container, 'multiple'),
                searchable: this.readBoolAttr($container, 'searchable'),
                clearable: this.readBoolAttr($container, 'clearable'),
                closeOnSelect: this.readBoolAttr($container, 'close-on-select', true)
            };
            
            this.isOpen = false;
            this.highlightedIndex = -1;
            this.searchTerm = '';
            this.filteredOptions = [];
            this.mouseHighlight = false;
            this._dropdownHome = null;
            this._onReposition = null;
            this._onScroll = null;
            /** @type {Set<string>} 禁止选择的 option value（业务可动态改） */
            this.disabledValues = new Set();
            
            this.init();
        }
        
        /**
         * 读取布尔 data 属性（兼容 jQuery data 缓存与 attr 字符串）。
         *
         * @param {jQuery} $el 元素
         * @param {string} name 属性名（不含 data-）
         * @param {boolean} fallback 缺省值
         * @return {boolean}
         */
        readBoolAttr($el, name, fallback) {
            if (typeof fallback === 'undefined') {
                fallback = false;
            }
            const attr = $el.attr('data-' + name);
            if (typeof attr !== 'undefined' && attr !== null && attr !== '') {
                return attr === 'true' || attr === '1';
            }
            const data = $el.data(name);
            if (typeof data === 'boolean') {
                return data;
            }
            if (data === 1 || data === '1' || data === 'true') {
                return true;
            }
            if (data === 0 || data === '0' || data === 'false') {
                return false;
            }
            return !!fallback;
        }

        /**
         * 读取字段级 i18n。
         * 优先级：data-i18n-*（PHP __()）→ xunSelectField → pilipost__（仅当确有译文）→ msgid。
         * 说明：pilipostL10n 短串袋常缺字段模板；直接 pilipost__(msgid) 会原样回落中文。
         *
         * @param {string} key data 后缀，如 selected / placeholder
         * @param {string} localizeKey xunSelectField 键名
         * @param {string} msgid 中文 msgid
         * @return {string}
         */
        t(key, localizeKey, msgid) {
            const attr = this.$container.attr('data-i18n-' + key);
            if (attr != null && String(attr).trim() !== '') {
                return String(attr);
            }
            // jQuery data 缓存把 data-i18n-selected 收成 i18nSelected
            const camel = 'i18n' + String(key).split('-').map(function(p) {
                return p ? (p.charAt(0).toUpperCase() + p.slice(1)) : '';
            }).join('');
            const fromData = this.$container.data(camel);
            if (fromData != null && String(fromData).trim() !== '') {
                return String(fromData);
            }
            const bag = PILI.bag('select') || {};
            if (localizeKey && bag[localizeKey]) {
                return String(bag[localizeKey]);
            }
            if (typeof window.pilipost__ === 'function') {
                const tr = String(window.pilipost__(msgid));
                if (tr && tr !== String(msgid)) {
                    return tr;
                }
            }
            return String(msgid);
        }

        /**
         * 填 %d / %1$d / %2$d 占位符（关闭态计数文案）。
         *
         * @param {string} tpl 模板
         * @param {Array<string|number>} values 按序数值
         * @return {string}
         */
        formatTpl(tpl, values) {
            let out = String(tpl == null ? '' : tpl);
            const list = Array.isArray(values) ? values : [values];
            list.forEach(function(v, i) {
                const n = String(i + 1);
                const s = String(v);
                out = out.replace(new RegExp('%' + n + '\\$d', 'g'), s);
                out = out.replace(new RegExp('%' + n + '\\$s', 'g'), s);
            });
            if (list.length === 1) {
                out = out.replace(/%d/g, String(list[0])).replace(/%s/g, String(list[0]));
            }
            return out;
        }

        /**
         * 初始化字段
         */
        init() {
            this.bindEvents();
            this.updateFilteredOptions();
            this.updateDisplay();
            this.updateNativeSelect();
        }
        
        /**
         * 绑定事件
         */
        bindEvents() {
            this.$button.on('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
            
            this.$clear.on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.clearSelection();
            });
            
            this.$options.on('click', '.pili-select-option', (e) => {
                e.preventDefault();
                const $option = $(e.currentTarget);
                if (this.isOptionDisabled($option)) {
                    this.$container.trigger('xun:select:disabled-click', [String($option.data('value') || ''), $option]);
                    return;
                }
                this.selectOption($option);
            });
            
            this.$search.on('input', (e) => {
                this.handleSearch(e.target.value);
            });
            
            this.$button.on('keydown', (e) => {
                this.handleKeydown(e);
            });
            
            this.$search.on('keydown', (e) => {
                this.handleKeydown(e);
            });
            
            this.$container.find('.pili-select-all').on('click', (e) => {
                e.preventDefault();
                this.selectAll();
            });
            
            $(document).on('click.pili-select-' + this.config.fieldId, (e) => {
                const t = e.target;
                if (this.$container.is(t) || this.$container.has(t).length > 0) {
                    return;
                }
                // 下拉挂到 body 后，点击面板内部（搜索/选项）不算「外部」。
                if (this.$dropdown.length && (this.$dropdown.is(t) || this.$dropdown.has(t).length > 0)) {
                    return;
                }
                this.close();
            });
            
            this.$options.on('mouseenter', '.pili-select-option', (e) => {
                const $option = $(e.currentTarget);
                this.mouseHighlight = true;
                this.highlightOption($option.index(), false);
            });

            this.$options.on('mouseleave', '.pili-select-option', (e) => {
                this.mouseHighlight = false;
            });

            this.$options.on('wheel', (e) => {
                e.stopPropagation();
            });

            this.$options.on('mousemove', (e) => {
                const container = this.$options[0];
                const rect = container.getBoundingClientRect();
                const scrollbarWidth = container.offsetWidth - container.clientWidth;

                if (scrollbarWidth > 0 && e.clientX > rect.right - scrollbarWidth) {
                    return;
                }
            });
        }
        
        /**
         * 切换下拉列表显示状态
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
		/**
		 * 打开下拉列表
		 */
		open() {
			if (this.isOpen) return;

			this.closeOtherSelects();

			this.isOpen = true;
			this.$container.addClass('is-open');
			this.$button.attr('aria-expanded', 'true');
			this.resetSearch();
			this.highlightSelectedOption();

			// 先挂到 body 并保持不可见，算好坐标后再显示，避免从页面顶部闪一下。
			this.mountDropdownPortal(true);
			this.positionDropdown();
			this.revealDropdown();
			this.bindPositionListeners();
			this.syncDisabledUi();
			this.$container.trigger('xun:select:opened');
		}

		/**
		 * 关闭下拉列表
		 */
		close() {
			if (!this.isOpen) return;

			this.isOpen = false;
			this.$container.removeClass('is-open');
			this.$dropdown.addClass('hidden');
			this.$button.attr('aria-expanded', 'false');
			this.highlightedIndex = -1;
			this.mouseHighlight = false;
			this.resetSearch();
			this.unmountDropdownPortal();
			this.unbindPositionListeners();
			this.$container.trigger('xun:select:closed');
		}

		/**
		 * 关闭页面上其他已打开的 select，避免多层叠开。
		 */
		closeOtherSelects() {
			const self = this;
			$('.pili-select-field').each(function() {
				const inst = $(this).data('pili-select-instance');
				if (inst && inst !== self && inst.isOpen) {
					inst.close();
				}
			});
		}

		/**
		 * 将下拉挂到 body。
		 *
		 * @param {boolean} prepareOnly 为 true 时保持 visibility:hidden，供定位后再显示。
		 */
		mountDropdownPortal(prepareOnly) {
			if (!this.$dropdown.length || !this.$button.length) {
				return;
			}
			if (!this._dropdownHome) {
				this._dropdownHome = this.$dropdown.parent();
			}
			if (!this.$dropdown.parent().is('body')) {
				this.$dropdown.appendTo(document.body);
			}
			this.$dropdown.addClass('pili-select-dropdown--portal');
			if (prepareOnly) {
				this.$dropdown.css({
					visibility: 'hidden',
					pointerEvents: 'none',
					top: '0',
					left: '0',
				});
				this.$dropdown.removeClass('hidden');
			} else {
				this.positionDropdown();
			}
		}

		/**
		 * 定位完成后显示下拉（同帧，避免闪顶）。
		 */
		revealDropdown() {
			if (!this.$dropdown.length) {
				return;
			}
			this.$dropdown.css({
				visibility: 'visible',
				pointerEvents: '',
			});
		}

		/**
		 * 收起后还原 DOM 位置与样式。
		 */
		unmountDropdownPortal() {
			if (!this.$dropdown.length) {
				return;
			}
			this.$dropdown.removeClass('pili-select-dropdown--portal');
			this.$dropdown.css({
				top: '',
				left: '',
				width: '',
				maxWidth: '',
				visibility: '',
				pointerEvents: '',
			});
			if (this._dropdownHome && this._dropdownHome.length && !this.$dropdown.parent().is(this._dropdownHome)) {
				this.$dropdown.appendTo(this._dropdownHome);
			}
		}

		/**
		 * 按触发按钮的视口位置对齐下拉。
		 */
		positionDropdown() {
			if (!this.isOpen || !this.$button.length || !this.$dropdown.length) {
				return;
			}
			const el = this.$button[0];
			const rect = el.getBoundingClientRect();
			const gap = 4;
			const vw = window.innerWidth || document.documentElement.clientWidth;
			const vh = window.innerHeight || document.documentElement.clientHeight;
			const width = Math.max(rect.width, 120);
			let left = rect.left;
			if (left + width > vw - 8) {
				left = Math.max(8, vw - width - 8);
			}
			if (left < 8) {
				left = 8;
			}

			// 先定宽再量高（此时可为 visibility:hidden，不会闪）。
			this.$dropdown.css({
				left: left + 'px',
				width: width + 'px',
				maxWidth: (vw - 16) + 'px',
				top: '0',
			});
			const dropH = this.$dropdown.outerHeight() || 180;
			const spaceBelow = vh - rect.bottom - gap;
			const spaceAbove = rect.top - gap;
			let top = rect.bottom + gap;
			if (spaceBelow < dropH && spaceAbove > spaceBelow) {
				top = Math.max(8, rect.top - gap - dropH);
			}
			this.$dropdown.css({
				top: top + 'px',
				left: left + 'px',
				width: width + 'px',
				maxWidth: (vw - 16) + 'px',
			});
		}

		bindPositionListeners() {
			if (this._onReposition) {
				return;
			}
			this._onReposition = () => {
				if (this.isOpen) {
					this.positionDropdown();
				}
			};
			// 滚动内容区时同步；下拉内部滚动不处理（capture 里再判断）。
			this._onScroll = (e) => {
				if (!this.isOpen) {
					return;
				}
				const t = e.target;
				if (t && this.$dropdown.length && (this.$dropdown[0] === t || this.$dropdown[0].contains(t))) {
					return;
				}
				this.positionDropdown();
			};
			window.addEventListener('resize', this._onReposition);
			document.addEventListener('scroll', this._onScroll, true);
		}

		unbindPositionListeners() {
			if (this._onReposition) {
				window.removeEventListener('resize', this._onReposition);
				this._onReposition = null;
			}
			if (this._onScroll) {
				document.removeEventListener('scroll', this._onScroll, true);
				this._onScroll = null;
			}
		}
        
        /**
         * 选项是否禁用（data-disabled / aria-disabled / disabledValues）。
         *
         * @param {jQuery} $option 选项节点
         * @return {boolean}
         */
        isOptionDisabled($option) {
            if (!$option || !$option.length) {
                return true;
            }
            if ($option.attr('aria-disabled') === 'true' || $option.attr('data-disabled') === '1') {
                return true;
            }
            if ($option.data('disabled') === true || $option.data('disabled') === 1 || $option.data('disabled') === '1') {
                return true;
            }
            const value = String($option.data('value') == null ? '' : $option.data('value'));
            return value !== '' && this.disabledValues.has(value);
        }

        /**
         * 批量设置禁用 value（覆盖式）。空数组 = 全部可点。
         *
         * @param {Array<string|number>} values 禁用值列表
         * @return {XunSelectField}
         */
        setDisabledValues(values) {
            this.disabledValues = new Set();
            const list = Array.isArray(values) ? values : [];
            list.forEach((v) => {
                const s = String(v == null ? '' : v).trim();
                if (s !== '') {
                    this.disabledValues.add(s);
                }
            });
            this.syncDisabledUi();
            return this;
        }

        /**
         * 单独启用/禁用某个 value。
         *
         * @param {string|number} value 选项值
         * @param {boolean} disabled 是否禁用
         * @return {XunSelectField}
         */
        setOptionDisabled(value, disabled) {
            const s = String(value == null ? '' : value).trim();
            if (s === '') {
                return this;
            }
            if (disabled) {
                this.disabledValues.add(s);
            } else {
                this.disabledValues.delete(s);
            }
            this.syncDisabledUi();
            return this;
        }

        /**
         * 清空全部动态禁用。
         *
         * @return {XunSelectField}
         */
        clearDisabledValues() {
            this.disabledValues.clear();
            this.syncDisabledUi();
            return this;
        }

        /**
         * 把 disabledValues 同步到 UI 选项 + 原生 select。
         */
        syncDisabledUi() {
            if (!this.$options || !this.$options.length) {
                return;
            }
            this.$options.find('.pili-select-option').each((_, element) => {
                const $option = $(element);
                const value = String($option.data('value') == null ? '' : $option.data('value'));
                const disabled = value !== '' && this.disabledValues.has(value);
                $option.attr('aria-disabled', disabled ? 'true' : 'false');
                $option.attr('data-disabled', disabled ? '1' : '0');
                $option.toggleClass('is-disabled', disabled);
            });
            if (this.$nativeSelect && this.$nativeSelect.length) {
                this.$nativeSelect.find('option').each((_, element) => {
                    const $opt = $(element);
                    const value = String($opt.attr('value') == null ? '' : $opt.attr('value'));
                    if (!value) {
                        $opt.prop('disabled', false);
                        return;
                    }
                    $opt.prop('disabled', this.disabledValues.has(value));
                });
            }
            this.updateFilteredOptions();
        }

        /**
         * 选择选项
         *
         * @param {jQuery} $option 选项元素
         */
        selectOption($option) {
            if (this.isOptionDisabled($option)) {
                this.$container.trigger('xun:select:disabled-click', [String($option.data('value') || ''), $option]);
                return;
            }
            const value = $option.data('value');
            const text = $option.data('text');

            if (this.config.multiple) {
                this.toggleMultipleSelection(value, $option);
            } else {
                this.setSingleSelection(value, $option);
                if (this.config.closeOnSelect) {
                    this.close();
                }
            }

            this.updateNativeSelect();
            this.updateDisplay();
            this.updateClearButton();
            this.$container.trigger('xun:select:changed', [value, text]);
        }
        
        /**
         * 设置单选值
         *
         * @param {string} value   选项值
         * @param {jQuery} $option 选项元素
         */
        setSingleSelection(value, $option) {
            this.$options.find('.pili-select-option').removeClass('bg-blue-600 text-white bg-gray-100').addClass('text-gray-900');
            this.$options.find('.pili-select-option .pili-select-option-label').removeClass('font-semibold').addClass('font-normal');
            this.$options.find('.pili-select-option .pili-select-option-check').addClass('hidden');
            $option.removeClass('text-gray-900 bg-gray-100').addClass('bg-blue-600 text-white');
            $option.find('.pili-select-option-label').removeClass('font-normal').addClass('font-semibold');
            $option.find('.pili-select-option-check').removeClass('hidden');
            $option.attr('aria-selected', 'true');
            this.$options.find('.pili-select-option').not($option).attr('aria-selected', 'false');
        }
        
        /**
         * 切换多选值
         *
         * @param {string} value   选项值
         * @param {jQuery} $option 选项元素
         */
        toggleMultipleSelection(value, $option) {
            const isSelected = $option.hasClass('bg-blue-600');

            if (isSelected) {
                $option.removeClass('bg-blue-600 text-white').addClass('text-gray-900');
                $option.find('.pili-select-option-label').removeClass('font-semibold').addClass('font-normal');
                $option.find('.pili-select-option-check').addClass('hidden');
                $option.attr('aria-selected', 'false');
            } else {
                $option.removeClass('text-gray-900').addClass('bg-blue-600 text-white');
                $option.find('.pili-select-option-label').removeClass('font-normal').addClass('font-semibold');
                $option.find('.pili-select-option-check').removeClass('hidden');
                $option.attr('aria-selected', 'true');
            }
        }
        
        /**
         * 清空选择
         */
        clearSelection() {
            this.$options.find('.pili-select-option').removeClass('bg-blue-600 text-white bg-gray-100').addClass('text-gray-900');
            this.$options.find('.pili-select-option .pili-select-option-label').removeClass('font-semibold').addClass('font-normal');
            this.$options.find('.pili-select-option .pili-select-option-check').addClass('hidden');
            this.$options.find('.pili-select-option').attr('aria-selected', 'false');

            this.updateNativeSelect();
            this.updateDisplay();
            this.updateClearButton();
            this.$container.trigger('xun:select:cleared');
        }
        
        /**
         * 全选（多选模式）
         */
        selectAll() {
            if (!this.config.multiple) return;

            this.filteredOptions.forEach(($option) => {
                if (this.isOptionDisabled($option)) {
                    return;
                }
                if (!$option.hasClass('bg-blue-600')) {
                    $option.removeClass('text-gray-900 bg-gray-100').addClass('bg-blue-600 text-white');
                    $option.find('.pili-select-option-label').removeClass('font-normal').addClass('font-semibold');
                    $option.find('.pili-select-option-check').removeClass('hidden');
                    $option.attr('aria-selected', 'true');
                }
            });

            this.updateNativeSelect();
            this.updateDisplay();
            this.updateClearButton();
            this.$container.trigger('xun:select:select-all');
        }

        /**
         * 转义 HTML 文本
         *
         * @param {string} text 原始文本
         * @return {string}
         */
        escapeHtml(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        /**
         * 从选项节点构建带 icon 的展示 HTML
         *
         * @param {jQuery} $option 选项元素
         * @return {string}
         */
        buildDisplayHtml($option) {
            const text = $option.data('text');
            const $icon = $option.find('.pili-select-option-icon').first();
            let html = '<span class="pili-select-option-content inline-flex items-center gap-2 min-w-0 max-w-full">';
            if ($icon.length) {
                html += $icon.prop('outerHTML');
            }
            html += '<span class="pili-select-option-label truncate">' + this.escapeHtml(text) + '</span></span>';
            return html;
        }

        /**
         * 处理搜索
         *
         * @param {string} term 搜索词
         */
        handleSearch(term) {
            this.searchTerm = term.toLowerCase();
            this.updateFilteredOptions();
            this.updateOptionsDisplay();
            this.highlightedIndex = -1;
        }

        /**
         * 重置搜索
         */
        resetSearch() {
            this.searchTerm = '';
            this.$search.val('');
            this.updateFilteredOptions();
            this.updateOptionsDisplay();
        }

        /**
         * 更新过滤后的选项
         */
        updateFilteredOptions() {
            this.filteredOptions = [];

            this.$options.find('.pili-select-option').each((index, element) => {
                const $option = $(element);
                const rawText = $option.data('text');
                const text = String(rawText == null ? '' : rawText).toLowerCase();

                if (!this.searchTerm || text.includes(this.searchTerm)) {
                    this.filteredOptions.push($option);
                    $option.show();
                } else {
                    $option.hide();
                }
            });
        }

        /**
         * 更新选项显示
         */
        updateOptionsDisplay() {
            const hasVisibleOptions = this.filteredOptions.length > 0;

            if (hasVisibleOptions) {
                this.$noResults.addClass('hidden');
                this.$options.removeClass('hidden');
            } else {
                this.$noResults.removeClass('hidden');
                this.$options.addClass('hidden');
            }
        }

        /**
         * 处理键盘事件
         *
         * @param {Event} e 键盘事件
         */
        handleKeydown(e) {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.open();
                    } else {
                        this.highlightNext();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (this.isOpen) {
                        this.highlightPrevious();
                    }
                    break;

                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.open();
                    } else if (this.highlightedIndex >= 0) {
                        this.selectHighlightedOption();
                    }
                    break;

                case 'Escape':
                    e.preventDefault();
                    this.close();
                    this.$button.focus();
                    break;

                case 'Tab':
                    if (this.isOpen) {
                        this.close();
                    }
                    break;
            }
        }

        /**
         * 高亮下一个选项（跳过禁用项）
         */
        highlightNext() {
            if (this.filteredOptions.length === 0) return;

            this.mouseHighlight = false;
            let idx = this.highlightedIndex + 1;
            while (idx < this.filteredOptions.length) {
                if (!this.isOptionDisabled(this.filteredOptions[idx])) {
                    this.highlightedIndex = idx;
                    this.updateHighlight(true);
                    return;
                }
                idx++;
            }
        }

        /**
         * 高亮上一个选项（跳过禁用项）
         */
        highlightPrevious() {
            if (this.filteredOptions.length === 0) return;

            this.mouseHighlight = false;
            let idx = this.highlightedIndex - 1;
            while (idx >= 0) {
                if (!this.isOptionDisabled(this.filteredOptions[idx])) {
                    this.highlightedIndex = idx;
                    this.updateHighlight(true);
                    return;
                }
                idx--;
            }
        }

        /**
         * 高亮指定选项
         *
         * @param {number} index 选项索引
         * @param {boolean} shouldScroll 是否需要滚动到可见区域
         */
        highlightOption(index, shouldScroll = true) {
            this.highlightedIndex = index;
            this.updateHighlight(shouldScroll);
        }

        /**
         * 更新高亮显示
         *
         * @param {boolean} shouldScroll 是否需要滚动到可见区域
         */
        updateHighlight(shouldScroll = true) {
            this.$options.find('.pili-select-option').each((index, element) => {
                const $option = $(element);
                if (!$option.hasClass('bg-blue-600')) {
                    $option.removeClass('bg-gray-100');
                }
            });

            if (this.highlightedIndex >= 0 && this.filteredOptions[this.highlightedIndex]) {
                const $highlighted = this.filteredOptions[this.highlightedIndex];
                if (!$highlighted.hasClass('bg-blue-600')) {
                    $highlighted.addClass('bg-gray-100');
                }
                if (shouldScroll) {
                    this.scrollToHighlighted($highlighted);
                }
            }
        }

        /**
         * 滚动到高亮选项
         *
         * @param {jQuery} $option 选项元素
         */
        scrollToHighlighted($option) {
            if (this.mouseHighlight) {
                return;
            }

            const optionTop = $option.position().top;
            const optionHeight = $option.outerHeight();
            const containerHeight = this.$options.height();
            const scrollTop = this.$options.scrollTop();

            const buffer = 5;

            if (optionTop < buffer) {
                this.$options.scrollTop(scrollTop + optionTop - buffer);
            } else if (optionTop + optionHeight > containerHeight - buffer) {
                this.$options.scrollTop(scrollTop + optionTop + optionHeight - containerHeight + buffer);
            }
        }

        /**
         * 选择高亮的选项
         */
        selectHighlightedOption() {
            if (this.highlightedIndex >= 0 && this.filteredOptions[this.highlightedIndex]) {
                this.selectOption(this.filteredOptions[this.highlightedIndex]);
            }
        }

        /**
         * 高亮当前选中项
         */
        highlightSelectedOption() {
            const $selected = this.$options.find('.pili-select-option[aria-selected="true"]').first();
            if ($selected.length) {
                const index = this.filteredOptions.indexOf($selected);
                if (index >= 0) {
                    this.highlightOption(index);
                }
            }
        }

        /**
         * 更新原生select
         */
        updateNativeSelect() {
            const selectedValues = [];

            this.$options.find('.pili-select-option[aria-selected="true"]').each((index, element) => {
                selectedValues.push($(element).data('value'));
            });

            this.$nativeSelect.find('option').prop('selected', false);
            selectedValues.forEach(value => {
                this.$nativeSelect.find(`option[value="${value}"]`).prop('selected', true);
            });

            this.$nativeSelect.trigger('change');
        }

        /**
         * 更新显示文本（含选项 icon）
         */
        updateDisplay() {
            const selectedOptions = this.$options.find('.pili-select-option[aria-selected="true"]');
            const selectedCount = selectedOptions.length;
            const selectedText = this.t('selected', 'selected_text', '已选择 %d 项');

            if (selectedCount === 0) {
                const placeholder = this.$container.attr('data-placeholder')
                    || this.$container.data('placeholder')
                    || this.t('placeholder', 'placeholder', '请选择…');
                this.$display.html('<span class="text-gray-500">' + this.escapeHtml(placeholder) + '</span>');
            } else if (this.config.multiple) {
                if (selectedCount === 1) {
                    this.$display.html(this.buildDisplayHtml(selectedOptions.first()));
                } else {
                    // 少量子项：直接拼标签；过长则用 PHP 已译的「已选择 N 项」模板（勿依赖 pilipost__ 空袋）。
                    const labels = [];
                    selectedOptions.each((_, el) => {
                        const text = ($(el).attr('data-text') || $(el).data('text') || '').toString().trim();
                        if (text) {
                            labels.push(text);
                        }
                    });
                    const joined = labels.join(', ');
                    if (joined && joined.length <= 96) {
                        this.$display.text(joined);
                    } else {
                        this.$display.text(this.formatTpl(selectedText, [selectedCount]));
                    }
                }
            } else {
                this.$display.html(this.buildDisplayHtml(selectedOptions.first()));
            }
        }

        /**
         * 更新清空按钮显示
         */
        updateClearButton() {
            const hasSelection = this.$options.find('.pili-select-option[aria-selected="true"]').length > 0;

            if (this.config.clearable && hasSelection) {
                this.$clear.removeClass('hidden');
            } else {
                this.$clear.addClass('hidden');
            }
        }



        /**
         * 销毁字段实例
         */
        destroy() {
            this.close();
            this.$container.off('.pili-select');
            $(document).off('click.pili-select-' + this.config.fieldId);
            this.unbindPositionListeners();
            this.unmountDropdownPortal();
        }
    }

    /**
     * 初始化所有选择字段
     */
    function bootSelectFields($root) {
        const $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-select-field').each(function() {
            const $this = $(this);
            if (!$this.data('pili-select-initialized')) {
                const instance = new XunSelectField($this);
                $this.data('pili-select-instance', instance);
                $this.data('pili-select-initialized', true);
            }
        });
    }

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        bootSelectFields($container && $container.length ? $container : null);
    });

    $(document).ready(function() {
        bootSelectFields(null);
    });

    window.piliSelectFieldBoot = bootSelectFields;

    /**
     * 表格 editor=select 挂载：在空壳宿主上注入 select 控件（不经 PHP PILI::field）。
     *
     * @param {HTMLElement} host
     * @returns {Object|null}
     */
    PILI.registerMount('select', function (host) {
        if (!host || !host.getAttribute) {
            return null;
        }
        if (host.querySelector('.pili-select-field')) {
            bootSelectFields($(host));
            return $(host).find('.pili-select-field').data('pili-select-instance') || null;
        }

        var settings = {};
        try {
            settings = JSON.parse(host.getAttribute('data-editor-settings') || '{}') || {};
        } catch (err) {
            settings = {};
        }

        var value = String(host.getAttribute('data-editor-value') || '');
        var options = settings.options && typeof settings.options === 'object' ? settings.options : {};
        var searchable = settings.searchable !== false;
        var clearable = !!settings.clearable;
        var closeOnSelect = settings.close_on_select !== false;
        var placeholder = String(settings.placeholder || (PILI.bag('select') && PILI.bag('select').placeholder) || '请选择…');
        var noResults = String(settings.no_results_text || (PILI.bag('select') && PILI.bag('select').no_results_text) || '未找到匹配项');
        var loadingText = String(settings.loading_text || (PILI.bag('select') && PILI.bag('select').loading_text) || '加载中…');
        var searchPh = String(settings.search_placeholder || (PILI.bag('select') && PILI.bag('select').search_placeholder) || '搜索选项…');
        var selectedTpl = String((PILI.bag('select') && PILI.bag('select').selected_text) || '已选择 %d 项');
		var clearTitle = String((PILI.bag('select') && PILI.bag('select').clear_title) || '清空选择');

        if (value && !Object.prototype.hasOwnProperty.call(options, value)) {
            options = Object.assign({}, options);
            options[value] = String(settings.orphan_label || '') || value;
        }

        var rowKey = String(host.getAttribute('data-post-id') || '');
        var fieldId = 'xun_tbl_sel_' + (rowKey || Math.random().toString(36).slice(2, 10));
        var listboxId = fieldId + '_listbox';

        function esc(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        var displayLabel = '';
        if (value && Object.prototype.hasOwnProperty.call(options, value)) {
            displayLabel = String(options[value] || value);
        }

        var optionLis = '';
        var nativeOpts = '';
        var idx = 0;
        Object.keys(options).forEach(function (key) {
            var label = String(options[key] == null ? key : options[key]);
            var selected = String(key) === value;
            var selClass = selected ? 'bg-blue-600 text-white' : 'text-gray-900 hover:bg-gray-50';
            var weight = selected ? 'font-semibold' : 'font-normal';
            var checkHidden = selected ? '' : 'hidden';
            optionLis +=
                '<li id="listbox-option-' + idx + '" role="option" aria-selected="' + (selected ? 'true' : 'false') + '"' +
                ' class="pili-select-option relative cursor-pointer py-3 px-4 sm:py-2 sm:px-3 md:py-2 md:px-3 pr-12 sm:pr-10 md:pr-9 select-none transition-colors duration-150 touch-manipulation ' + selClass + '"' +
                ' data-value="' + esc(key) + '" data-text="' + esc(label) + '">' +
                '<span class="pili-select-option-content flex items-center gap-2 min-w-0 pr-1">' +
                '<span class="pili-select-option-label block truncate text-base sm:text-sm md:text-sm ' + weight + '">' + esc(label) + '</span>' +
                '</span>' +
                '<span class="pili-select-option-check absolute inset-y-0 right-0 flex items-center pr-4 sm:pr-3 md:pr-3 ' + checkHidden + '">' +
                '<svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="w-6 h-6 sm:w-5 sm:h-5 md:w-4 md:h-4">' +
                '<path d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" fill-rule="evenodd" />' +
                '</svg></span></li>';
            nativeOpts += '<option value="' + esc(key) + '"' + (selected ? ' selected' : '') + '>' + esc(label) + '</option>';
            idx += 1;
        });

        var displayHtml = displayLabel
            ? '<span class="pili-select-option-content inline-flex items-center gap-2 min-w-0 max-w-full"><span class="pili-select-option-label truncate">' + esc(displayLabel) + '</span></span>'
            : '<span class="text-gray-500">' + esc(placeholder) + '</span>';

        var searchHtml = searchable
            ? '<div class="p-3 sm:p-2 md:p-2 border-b border-gray-100"><input type="text" class="pili-select-search w-full px-4 py-3 sm:px-3 sm:py-2 md:px-3 md:py-2 text-base sm:text-sm md:text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150 touch-manipulation" placeholder="' + esc(searchPh) + '"></div>'
            : '';

        host.innerHTML =
            '<div class="pili-select-field w-full touch-manipulation"' +
            ' data-field-id="' + esc(fieldId) + '"' +
            ' data-multiple="false"' +
            ' data-searchable="' + (searchable ? 'true' : 'false') + '"' +
            ' data-clearable="' + (clearable ? 'true' : 'false') + '"' +
            ' data-close-on-select="' + (closeOnSelect ? 'true' : 'false') + '"' +
            ' data-placeholder="' + esc(placeholder) + '"' +
            ' data-i18n-selected="' + esc(selectedTpl) + '"' +
            ' data-i18n-clear="' + esc(clearTitle) + '"' +
            ' data-i18n-no-results="' + esc(noResults) + '"' +
            ' data-i18n-loading="' + esc(loadingText) + '"' +
            ' data-i18n-search="' + esc(searchPh) + '">' +
            '<div class="relative">' +
            '<button type="button" aria-expanded="false" aria-haspopup="listbox" class="pili-select-button relative grid w-full cursor-default grid-cols-1 rounded-md bg-white min-h-10 py-2.5 pr-2 pl-3 text-left text-gray-900 sm:text-sm/6 touch-manipulation" data-listbox="' + esc(listboxId) + '">' +
            '<span class="col-start-1 row-start-1 truncate pili-select-display pr-8 sm:pr-7 md:pr-6">' + displayHtml + '</span>' +
            '<svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="absolute right-2 sm:right-1 md:right-1 top-1/2 -translate-y-1/2 w-5 h-5 sm:w-4 sm:h-4 md:w-4 md:h-4 text-gray-500 pili-select-arrow">' +
            '<path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd" />' +
            '</svg></button>' +
            '<div class="pili-select-dropdown absolute z-10 mt-1 w-full bg-white shadow-lg ring-1 ring-black/5 rounded-md hidden transition-all duration-200" style="max-height:240px">' +
            searchHtml +
            '<ul role="listbox" tabindex="-1" class="pili-select-options py-2 sm:py-1 md:py-1 overflow-auto" style="max-height:140px">' + optionLis + '</ul>' +
            '<div class="pili-select-no-results hidden p-6 sm:p-4 md:p-4 text-center text-gray-500 text-base sm:text-sm md:text-sm">' + esc(noResults) + '</div>' +
            '<div class="pili-select-loading hidden p-6 sm:p-4 md:p-4 text-center text-gray-500 text-base sm:text-sm md:text-sm">' + esc(loadingText) + '</div>' +
            '</div></div>' +
            '<select class="pili-select-native hidden" tabindex="-1" aria-hidden="true">' +
            (value ? '' : '<option value=""></option>') +
            nativeOpts +
            '</select></div>';

        bootSelectFields($(host));
        return $(host).find('.pili-select-field').data('pili-select-instance') || null;
    });

})(jQuery);
