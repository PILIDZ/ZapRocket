/**
 * XUN Radio Field JavaScript
 *
 * 支持 multiple：保持 radio 卡片 UI，底层 checkbox 可多选。
 *
 * @since 1.0.0
 * @version 1.1.0
 */

(function($) {
    'use strict';

    var XunRadioField = {

        init: function() {
            this.bindEvents();
            this.initializeFields();
        },

        isMultiple: function($field) {
            return String($field.attr('data-multiple') || $field.data('multiple') || '0') === '1';
        },

        /**
         * 字段级 i18n：data-i18n-* → xunRadioL10n → pilipost__（仅确有译文）→ msgid。
         * 禁止把空袋 pilipost__ 当「已翻译」——会原样回落中文硬编码观感。
         */
        t: function($field, dataKey, localizeKey, msgid) {
            var fromAttr = $field && $field.length
                ? $field.attr('data-i18n-' + dataKey)
                : '';
            if (fromAttr != null && String(fromAttr).trim() !== '') {
                return String(fromAttr);
            }
            if ($field && $field.length) {
                var camel = 'i18n' + String(dataKey).split('-').map(function(p) {
                    return p ? (p.charAt(0).toUpperCase() + p.slice(1)) : '';
                }).join('');
                var fromData = $field.data(camel);
                if (fromData != null && String(fromData).trim() !== '') {
                    return String(fromData);
                }
            }
            if (PILI.bag('radio') && PILI.bag('radio')[localizeKey]) {
                return String(PILI.bag('radio')[localizeKey]);
            }
            if (typeof window.pilipost__ === 'function') {
                var tr = String(window.pilipost__(msgid));
                if (tr && tr !== String(msgid)) {
                    return tr;
                }
            }
            return String(msgid);
        },

        /**
         * @param {string} tpl
         * @param {Array} values
         * @return {string}
         */
        formatTpl: function(tpl, values) {
            var out = String(tpl == null ? '' : tpl);
            var list = Array.isArray(values) ? values : [values];
            list.forEach(function(v, i) {
                var n = String(i + 1);
                var s = String(v);
                out = out.replace(new RegExp('%' + n + '\\$d', 'g'), s);
                out = out.replace(new RegExp('%' + n + '\\$s', 'g'), s);
            });
            if (list.length === 1) {
                out = out.replace(/%d/g, String(list[0])).replace(/%s/g, String(list[0]));
            }
            return out;
        },

        bindEvents: function() {
            var self = this;

            $(document).on('change', '.pili-radio-field .pili-radio-input', function() {
                self.handleRadioChange($(this));
            });

            $(document).on('input', '.pili-radio-search', function() {
                self.handleSearch($(this));
            });

            $(document).on('click', '.pili-radio-option', function(e) {
                var $target = $(e.target);
                if ($target.is('.pili-radio-input') || $target.closest('label').length) {
                    return;
                }
                var $option = $(this);
                var $input = $option.find('.pili-radio-input');
                var $field = $option.closest('.pili-radio-field');
                if (!$input.length || $input.is(':disabled')) {
                    return;
                }
                if (self.isMultiple($field)) {
                    $input.prop('checked', !$input.prop('checked')).trigger('change');
                } else {
                    $input.prop('checked', true).trigger('change');
                }
            });

            $(document).on('keydown', '.pili-radio-input', function(e) {
                self.handleKeyboardNavigation(e, $(this));
            });

            $(document).on('focus', '.pili-radio-search', function() {
                $(this).addClass('ring-2 ring-blue-500 border-blue-500');
            });

            $(document).on('blur', '.pili-radio-search', function() {
                $(this).removeClass('ring-2 ring-blue-500 border-blue-500');
            });

            $(document).on('keydown', function(e) {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    $('.pili-radio-search:visible').first().focus();
                }
            });
        },

        initializeFields: function() {
            $('.pili-radio-field').each(function() {
                var $field = $(this);
                XunRadioField.updateFieldState($field);
                XunRadioField.syncEmptyHidden($field);
                XunRadioField.addAnimations($field);
            });
        },

        handleRadioChange: function($input) {
            var $field = $input.closest('.pili-radio-field');
            var $option = $input.closest('.pili-radio-option');

            this.updateFieldState($field);
            this.syncEmptyHidden($field);
            this.addSelectionAnimation($option);
            this.triggerChangeEvent($field, this.getFieldValue($field));
        },

        /**
         * 多选未选时保留空 hidden，避免整字段不提交。
         */
        syncEmptyHidden: function($field) {
            if (!this.isMultiple($field)) {
                return;
            }
            var $empty = $field.find('.pili-radio-empty');
            var hasChecked = $field.find('.pili-radio-input:checked').length > 0;
            if (hasChecked) {
                $empty.prop('disabled', true);
            } else if ($empty.length) {
                $empty.prop('disabled', false);
            }
        },

        handleSearch: function($searchInput) {
            var searchTerm = $searchInput.val().toLowerCase();
            var $field = $searchInput.closest('.pili-radio-field');
            var $options = $field.find('.pili-radio-option');
            var visibleCount = 0;

            $options.each(function() {
                var $option = $(this);
                var text = $option.find('label').text().toLowerCase();
                var description = $option.find('.text-gray-500').text().toLowerCase();

                if (text.includes(searchTerm) || description.includes(searchTerm)) {
                    $option.removeClass('hidden').addClass('animate-fadeIn');
                    visibleCount++;
                } else {
                    $option.addClass('hidden').removeClass('animate-fadeIn');
                }
            });

            this.updateSearchResults($field, searchTerm, visibleCount, $options.length);
        },

        handleKeyboardNavigation: function(e, $input) {
            var $field = $input.closest('.pili-radio-field');
            var multiple = this.isMultiple($field);
            var $visibleInputs = $field.find('.pili-radio-option:not(.hidden) .pili-radio-input').filter(':visible');
            var currentIndex = $visibleInputs.index($input);
            var $target = null;

            switch (e.which) {
                case 38:
                    e.preventDefault();
                    $target = currentIndex > 0 ? $visibleInputs.eq(currentIndex - 1) : $visibleInputs.last();
                    break;
                case 40:
                    e.preventDefault();
                    $target = currentIndex < $visibleInputs.length - 1 ? $visibleInputs.eq(currentIndex + 1) : $visibleInputs.first();
                    break;
                case 32:
                case 13:
                    e.preventDefault();
                    if (multiple) {
                        $input.prop('checked', !$input.prop('checked')).trigger('change');
                    } else {
                        $input.prop('checked', true).trigger('change');
                    }
                    break;
            }

            if ($target) {
                $target.focus();
            }
        },

        updateFieldState: function($field) {
            var color = $field.data('color') || 'blue';
            var multiple = this.isMultiple($field);
            var selectedCount = 0;
            var totalCount = $field.find('.pili-radio-option').length;

            $field.find('.pili-radio-option').each(function() {
                var $option = $(this);
                var $input = $option.find('.pili-radio-input');
                var isChecked = $input.is(':checked');

                $option.removeClass([
                    'bg-blue-50', 'bg-green-50', 'bg-purple-50', 'bg-red-50', 'bg-gray-50',
                    'border-blue-200', 'border-green-200', 'border-purple-200', 'border-red-200', 'border-gray-200',
                    'ring-1', 'ring-blue-500', 'ring-green-500', 'ring-purple-500', 'ring-red-500', 'ring-gray-500'
                ].join(' '));

                if (isChecked) {
                    selectedCount++;
                    $option.addClass('bg-' + color + '-50 border-' + color + '-200 ring-1 ring-' + color + '-500');
                } else {
                    $option.addClass('border-gray-200');
                }
            });

            var $count = $field.find('.pili-radio-count');
            if ($count.length && multiple) {
                var tpl = this.t($field, 'selected', 'selectedCount', '已选 %1$d / 共 %2$d 个选项');
                $count.text(this.formatTpl(tpl, [selectedCount, totalCount]));
            }
        },

        addAnimations: function($field) {
            $field.find('.pili-radio-option').each(function(index) {
                var $option = $(this);
                setTimeout(function() {
                    $option.addClass('animate-slideInUp');
                }, index * 50);
            });
        },

        addSelectionAnimation: function($option) {
            $option.addClass('animate-pulse');
            setTimeout(function() {
                $option.removeClass('animate-pulse');
            }, 600);
        },

        updateSearchResults: function($field, searchTerm, visibleCount, totalCount) {
            $field.find('.search-stats, .no-results').remove();

            if (searchTerm) {
                if (visibleCount === 0) {
                    var noResults = this.t($field, 'no-results', 'noResults', '未找到匹配的选项');
                    var tryOther = this.t($field, 'try-other', 'tryOtherKeyword', '尝试使用其他关键词搜索');
                    var noResultsHtml = '<div class="no-results flex flex-col items-center justify-center py-12 text-gray-500">' +
                        '<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">' +
                        '<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>' +
                        '</svg>' +
                        '</div>' +
                        '<p class="text-sm font-medium text-gray-600 mb-1"></p>' +
                        '<p class="text-xs text-gray-400"></p>' +
                        '</div>';
                    var $nr = $(noResultsHtml);
                    $nr.find('p.text-sm').text(noResults);
                    $nr.find('p.text-xs').text(tryOther);
                    $field.find('.pili-radio-group').after($nr);
                } else {
                    var showingItems = this.t($field, 'showing-items', 'showingItems', '显示 %1$d / %2$d 项');
                    var statsLabel = this.formatTpl(showingItems, [visibleCount, totalCount]);
                    var $stats = $('<div class="search-stats text-center mb-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"></span></div>');
                    $stats.find('span').text(statsLabel);
                    $field.find('.pili-radio-group').before($stats);
                }
            }

            var $count = $field.find('.pili-radio-count');
            if ($count.length && !this.isMultiple($field)) {
                if (searchTerm && visibleCount !== totalCount) {
                    var showing = this.t($field, 'showing', 'showingCount', '显示 %1$d / %2$d 个选项');
                    $count.text(this.formatTpl(showing, [visibleCount, totalCount]));
                } else {
                    var totalTpl = this.t($field, 'total', 'totalCount', '共 %d 个选项');
                    $count.text(this.formatTpl(totalTpl, [totalCount]));
                }
            }
        },

        getFieldValue: function($field) {
            var vals = [];
            $field.find('.pili-radio-input:checked').each(function() {
                vals.push(String($(this).val() || ''));
            });
            if (this.isMultiple($field)) {
                return vals.filter(Boolean);
            }
            return vals.length ? vals[0] : '';
        },

        triggerChangeEvent: function($field, value) {
            var fieldId = $field.data('field-id');

            $field.trigger('xun:radio:change', {
                fieldId: fieldId,
                value: value,
                multiple: this.isMultiple($field)
            });
        },

        reinit: function() {
            this.initializeFields();
        },

        getValue: function(fieldId) {
            var $field = $('.pili-radio-field[data-field-id="' + fieldId + '"]');
            return this.getFieldValue($field);
        },

        setValue: function(fieldId, value) {
            var $field = $('.pili-radio-field[data-field-id="' + fieldId + '"]');
            var multiple = this.isMultiple($field);
            var values = [];

            if (multiple) {
                if (Array.isArray(value)) {
                    values = value.map(String);
                } else if (value !== null && value !== undefined && value !== '') {
                    values = [String(value)];
                }
            } else if (value !== null && value !== undefined && value !== '') {
                values = [String(value)];
            }

            $field.find('.pili-radio-input').prop('checked', false);
            values.forEach(function(v) {
                $field.find('.pili-radio-input[value="' + v.replace(/"/g, '\\"') + '"]').prop('checked', true);
            });

            this.updateFieldState($field);
            this.syncEmptyHidden($field);
        },

        toggleOption: function(fieldId, optionValue, disabled) {
            var $field = $('.pili-radio-field[data-field-id="' + fieldId + '"]');
            var $option = $field.find('.pili-radio-option[data-value="' + optionValue + '"]');
            var $input = $option.find('.pili-radio-input');

            if (disabled) {
                $input.prop('disabled', true);
                $option.addClass('disabled opacity-50 cursor-not-allowed');
            } else {
                $input.prop('disabled', false);
                $option.removeClass('disabled opacity-50 cursor-not-allowed');
            }
        }
    };

    $(document).ready(function() {
        XunRadioField.init();
    });

    PILI.RadioField = XunRadioField;

})(jQuery);
