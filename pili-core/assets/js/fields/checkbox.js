/**
 * Checkbox Field JavaScript - 基础字段增强
 *
 * 提供checkbox字段的增强功能：搜索、全选、验证、计数等
 *
 * @package PILI Framework
 * @author  June
 * @since   1.1.0
 */

(function($) {
    'use strict';

    /**
     * XunCheckboxField Checkbox字段类
     */
    class XunCheckboxField {
        constructor(container) {
            this.$container = $(container);
            this.fieldId = this.$container.data('field-id');
            this.options = [];
            this.selectedValues = [];
            this.filteredOptions = [];

            this.init();
        }

        /**
         * 初始化
         */
        init() {
            this.cacheElements();
            this.bindEvents();
            this.initializeOptions();
            this.updateState();
        }

        /**
         * 缓存DOM元素
         */
        cacheElements() {
            this.$toolbar = this.$container.find('.pili-checkbox-toolbar');
            this.$searchInput = this.$container.find('.pili-checkbox-search');
            this.$selectAllBtn = this.$container.find('.pili-checkbox-select-all');
            this.$clearAllBtn = this.$container.find('.pili-checkbox-clear-all');
            this.$countDisplay = this.$container.find('.pili-checkbox-count');
            this.$countCounter = this.$container.find('.pili-checkbox-counter');
            this.$optionsContainer = this.$container.find('.pili-checkbox-options');
            this.$validationWrap = this.$container.find('.pili-checkbox-validation');
            this.$validationMsg = this.$container.find('.pili-checkbox-validation-message');
            if (!this.$validationMsg.length) {
                this.$validationMsg = this.$validationWrap;
            }
            this.$checkboxInputs = this.$container.find('.pili-checkbox-input');
            this.$hiddenInputs = this.$container.find('.pili-checkbox-hidden-input');
            this.$emptyInput = this.$container.find('.pili-checkbox-empty-input');
        }

        /**
         * 字段级 i18n：data-i18n-* → xunCheckboxL10n → xunCheckbox.messages → pilipost__（仅确有译文）→ msgid。
         *
         * @param {string} dataKey data-i18n 后缀
         * @param {string} localizeKey L10n 键
         * @param {string} msgid 中文 msgid
         * @return {string}
         */
        t(dataKey, localizeKey, msgid) {
            const attr = this.$container.attr('data-i18n-' + dataKey);
            if (attr != null && String(attr).trim() !== '') {
                return String(attr);
            }
            const camel = 'i18n' + String(dataKey).split('-').map(function(p) {
                return p ? (p.charAt(0).toUpperCase() + p.slice(1)) : '';
            }).join('');
            const fromData = this.$container.data(camel);
            if (fromData != null && String(fromData).trim() !== '') {
                return String(fromData);
            }
            var cbag = (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('checkbox')) || {};
            if (cbag[localizeKey]) {
                return String(cbag[localizeKey]);
            }
            if (cbag.messages && cbag.messages[localizeKey]) {
                return String(cbag.messages[localizeKey]);
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
         * @param {string} tpl
         * @param {Array<string|number>} values
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
         * 绑定事件
         */
        bindEvents() {
            this.$searchInput.on('input', this.handleSearch.bind(this));
            this.$selectAllBtn.on('click', this.selectAll.bind(this));
            this.$clearAllBtn.on('click', this.clearAll.bind(this));
            this.$container.on('change', '.pili-checkbox-input', this.handleOptionChange.bind(this));
            this.$container.on('click', '.pili-checkbox-option', this.handleOptionClick.bind(this));
            this.$container.on('keydown', '.pili-checkbox-option[role="button"]', this.handleKeyboardNavigation.bind(this));
        }

        /**
         * 初始化选项数据
         */
        initializeOptions() {
            this.options = [];
            this.$container.find('.pili-checkbox-option').each((_, element) => {
                const $option = $(element);
                const $input = $option.find('.pili-checkbox-input');

                if ($input.length) {
                    const option = {
                        element: $option,
                        input: $input,
                        value: $input.val(),
                        label: $input.next('label').text() || $option.find('label').text(),
                        checked: $input.is(':checked'),
                        disabled: $input.is(':disabled'),
                        visible: true
                    };

                    this.options.push(option);
                }
            });

            this.filteredOptions = [...this.options];
            this.updateSelectedValues();
        }

        /**
         * 处理搜索
         */
        handleSearch(e) {
            const searchTerm = $(e.target).val().toLowerCase();

            this.filteredOptions = this.options.filter(option => {
                const matches = option.label.toLowerCase().includes(searchTerm);
                option.visible = matches;

                if (matches) {
                    option.element.show();
                } else {
                    option.element.hide();
                }

                return matches;
            });

            this.$container.find('.pili-checkbox-no-results').remove();
            if (searchTerm && this.filteredOptions.length === 0) {
                const msg = this.t('no-results', 'noResults', '没有找到匹配的选项');
                const $nr = $('<div class="pili-checkbox-no-results text-sm text-gray-500 text-center py-6"></div>');
                $nr.text(msg);
                this.$optionsContainer.append($nr);
            }

            this.updateState();
        }

        /**
         * 全选
         */
        selectAll() {
            this.filteredOptions.forEach(option => {
                if (!option.disabled && !option.checked) {
                    option.input.prop('checked', true);
                    option.checked = true;
                    this.updateCheckmarkIcon(option.input, true);
                }
            });

            this.updateSelectedValues();
            this.updateState();
            this.validate();
            this.triggerChange();
        }

        /**
         * 清空选择
         */
        clearAll() {
            this.filteredOptions.forEach(option => {
                if (!option.disabled && option.checked) {
                    option.input.prop('checked', false);
                    option.checked = false;
                }
            });

            this.updateSelectedValues();
            this.updateState();
            this.validate();
            this.triggerChange();
        }

        /**
         * 处理选项变化
         */
        handleOptionChange(e) {
            const $input = $(e.target);
            const value = $input.val();
            const isChecked = $input.is(':checked');

            const option = this.options.find(opt => opt.value === value);
            if (option) {
                option.checked = isChecked;
            }

            this.updateCheckmarkIcon($input, isChecked);

            this.updateSelectedValues();

            const $option = $input.closest('.pili-checkbox-option');
            this.updateAriaStates($option, $input);

            this.updateState();

            this.validate();

            this.triggerChange();
        }

        /**
         * 更新勾号图标显示
         */
        updateCheckmarkIcon($input, isChecked) {
            const $checkmark = $input.siblings('svg').find('.pili-checkbox-checkmark');
            if ($checkmark.length) {
                if (isChecked) {
                    $checkmark.removeClass('opacity-0').addClass('opacity-100');
                } else {
                    $checkmark.removeClass('opacity-100').addClass('opacity-0');
                }
            }
        }

        /**
         * 处理选项点击（用于整个选项区域） - 无障碍增强
         */
        handleOptionClick(e) {
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }

            const $option = $(e.currentTarget);
            const $input = $option.find('.pili-checkbox-input');

            if ($input.length && !$input.is(':disabled') && $option.attr('aria-disabled') !== 'true') {
                e.preventDefault();
                $input.prop('checked', !$input.is(':checked')).trigger('change');

                this.updateAriaStates($option, $input);
            }
        }

        /**
         * 更新选中值
         */
        updateSelectedValues() {
            this.selectedValues = [];
            this.options.forEach(option => {
                option.checked = option.input.is(':checked');
                if (option.checked) {
                    this.selectedValues.push(option.value);
                }
            });

            this.updateHiddenInputs();
        }

        /**
         * 更新隐藏输入字段
         */
        updateHiddenInputs() {
            this.$hiddenInputs.remove();
            this.$emptyInput.remove();

            if (this.selectedValues.length > 0) {
                this.selectedValues.forEach(value => {
                    const $hidden = $('<input type="hidden" class="pili-checkbox-hidden-input">');
                    $hidden.attr('name', this.getFieldName() + '[]');
                    $hidden.val(value);
                    this.$container.append($hidden);
                });
            } else {
                const $empty = $('<input type="hidden" class="pili-checkbox-empty-input">');
                $empty.attr('name', this.getFieldName());
                $empty.val('');
                this.$container.append($empty);
            }

            this.$hiddenInputs = this.$container.find('.pili-checkbox-hidden-input');
            this.$emptyInput = this.$container.find('.pili-checkbox-empty-input');
        }

        /**
         * 获取字段名称
         */
        getFieldName() {
            const firstName = this.$checkboxInputs.first().attr('name');
            return firstName ? firstName.replace('[]', '') : '';
        }

        /**
         * 更新UI状态
         */
        updateState() {
            const selectedCount = this.selectedValues.length;
            const totalCount = this.filteredOptions.length;
            const allSelected = selectedCount === totalCount && totalCount > 0;
            const noneSelected = selectedCount === 0;
            const tpl = this.t('selected-count', 'selectedCount', '已选择 %1$d / %2$d 项');
            const label = this.formatTpl(tpl, [selectedCount, this.options.length]);

            if (this.$countDisplay.length) {
                this.$countDisplay.text(label);
            }
            if (this.$countCounter && this.$countCounter.length) {
                this.$countCounter.text(label);
            }

            if (allSelected) {
                this.$selectAllBtn.hide();
                this.$clearAllBtn.show();
            } else if (noneSelected) {
                this.$selectAllBtn.show();
                this.$clearAllBtn.hide();
            } else {
                this.$selectAllBtn.show();
                this.$clearAllBtn.show();
            }
        }

        /**
         * 验证
         */
        validate() {
            const selectedCount = this.selectedValues.length;
            const minRequired = parseInt(this.$container.attr('data-min-required') || this.$container.data('min-required'), 10) || 0;
            const maxAllowed = parseInt(this.$container.attr('data-max-allowed') || this.$container.data('max-allowed'), 10) || 0;

            let isValid = true;
            let message = '';

            if (minRequired > 0 && selectedCount < minRequired) {
                isValid = false;
                message = this.formatTpl(
                    this.t('min-required', 'minRequired', '至少需要选择 %d 项'),
                    [minRequired]
                );
            }

            if (maxAllowed > 0 && selectedCount > maxAllowed) {
                isValid = false;
                message = this.formatTpl(
                    this.t('max-exceeded', 'maxExceeded', '最多只能选择 %d 项'),
                    [maxAllowed]
                );
            }

            if (isValid) {
                this.$validationWrap.addClass('hidden');
                this.$validationMsg.text('');
            } else {
                this.$validationWrap.removeClass('hidden');
                this.$validationMsg.text(message);
            }

            return isValid;
        }

        /**
         * 触发变化事件
         */
        triggerChange() {
            this.$container.trigger('xun:checkbox:change', {
                selectedValues: this.selectedValues,
                selectedCount: this.selectedValues.length,
                totalCount: this.options.length
            });
        }

        /**
         * 处理键盘导航 - 无障碍支持
         */
        handleKeyboardNavigation(e) {
            const $option = $(e.currentTarget);
            const $input = $option.find('.pili-checkbox-input');

            if (e.keyCode === 32 || e.keyCode === 13) {
                e.preventDefault();

                if ($input.is(':disabled') || $option.attr('aria-disabled') === 'true') {
                    return;
                }

                $input.prop('checked', !$input.is(':checked')).trigger('change');
                this.updateAriaStates($option, $input);
            }

            else if (e.keyCode === 38 || e.keyCode === 40) {
                e.preventDefault();
                const $options = this.$container.find('.pili-checkbox-option[role="button"]:visible');
                const currentIndex = $options.index($option);
                let nextIndex;

                if (e.keyCode === 38) {
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : $options.length - 1;
                } else {
                    nextIndex = currentIndex < $options.length - 1 ? currentIndex + 1 : 0;
                }

                $options.eq(nextIndex).focus();
            }
        }

        /**
         * 更新ARIA状态 - 无障碍支持
         */
        updateAriaStates($option, $input) {
            if ($option && $option.attr('role') === 'button') {
                const isChecked = $input ? $input.is(':checked') : $option.find('.pili-checkbox-input').is(':checked');
                $option.attr('aria-checked', isChecked ? 'true' : 'false');
            }
        }
    }

    /**
     * jQuery插件
     */
    $.fn.xunCheckbox = function() {
        return this.each(function() {
            if (!$(this).data('pili-checkbox')) {
                $(this).data('pili-checkbox', new XunCheckboxField(this));
            }
        });
    };

    /**
     * 自动初始化
     */
    $(document).ready(function() {
        $('.pili-checkbox-field').xunCheckbox();
    });

    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            const $node = $(node);
                            $node.find('.pili-checkbox-field').xunCheckbox();
                            if ($node.hasClass('pili-checkbox-field')) {
                                $node.xunCheckbox();
                            }
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

})(jQuery);