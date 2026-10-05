/**
 * XUN Button Field JavaScript
 * 
 * 现代化的按钮选择字段交互逻辑
 * 支持单选/多选、搜索、动画效果和无障碍访问
 * 
 * @since 1.0.0
 * @version 1.0.0
 */

(function($) {
    'use strict';

    function buttonI18n(key, fallback) {
        return (PILI.bag('button') && PILI.bag('button').i18n && PILI.bag('button').i18n[key]) || fallback;
    }

    function buttonFormatCount(template, visible, total) {
        return template.replace('%1$d', visible).replace('%2$d', total);
    }

    /**
     * Button Field 类
     */
    var XunButtonField = {
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initializeFields();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;

            $(document).on('change', '.pili-button-field .pili-button-input', function() {
                self.handleButtonChange($(this));
            });

            $(document).on('input', '.pili-button-search', function() {
                self.handleSearch($(this));
            });

            $(document).on('keydown', '.pili-button-option', function(e) {
                self.handleKeyboardNavigation(e, $(this));
            });

            $(document).on('click', '.pili-button-option button', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $button = $(this);
                var $option = $button.closest('.pili-button-option');
                var $input = $option.find('.pili-button-input');
                var $field = $option.closest('.pili-button-field');
                var $container = $option.closest('[data-multiple]');
                var isMultiple = $container.data('multiple') === true || $container.data('multiple') === 'true';

                if (!isMultiple) {
                    $container.find('.pili-button-option').removeClass('selected');
                    $container.find('.pili-button-input').prop('checked', false);

                    $input.prop('checked', true);
                    $option.addClass('selected');
                } else {
                    var isCurrentlyChecked = $input.is(':checked');
                    $input.prop('checked', !isCurrentlyChecked);

                    if (!isCurrentlyChecked) {
                        $option.addClass('selected');
                    } else {
                        $option.removeClass('selected');
                    }
                }

                XunButtonField.updateFieldState($field);
                XunButtonField.triggerChangeEvent($field);
            });

            $(document).on('keydown', function(e) {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    $('.pili-button-search:visible').first().focus();
                }
            });

            $(document).on('focus', '.pili-button-search', function() {
                $(this).addClass('ring-2 ring-blue-500 border-blue-500');
            });

            $(document).on('blur', '.pili-button-search', function() {
                $(this).removeClass('ring-2 ring-blue-500 border-blue-500');
            });
        },

        /**
         * 初始化所有字段
         */
        initializeFields: function() {
            $('.pili-button-field').each(function() {
                var $field = $(this);
                XunButtonField.updateFieldState($field);
            });
        },

        /**
         * 处理按钮状态变化
         */
        handleButtonChange: function($input) {
            var $field = $input.closest('.pili-button-field');
            var $group = $input.closest('.pili-button-group');
            var isMultiple = $group.data('multiple') === true || $group.data('multiple') === 'true';
            var $option = $input.closest('.pili-button-option');

            if (!isMultiple) {
                $group.find('.pili-button-option').removeClass('selected');
                $group.find('.pili-button-input').prop('checked', false);
                
                $input.prop('checked', true);
                $option.addClass('selected');
            } else {
                if ($input.is(':checked')) {
                    $option.addClass('selected');
                } else {
                    $option.removeClass('selected');
                }
            }

            this.updateFieldState($field);
            this.triggerChangeEvent($field);
        },

        /**
         * 处理搜索
         */
        handleSearch: function($searchInput) {
            var searchTerm = $searchInput.val().toLowerCase();
            var $field = $searchInput.closest('.pili-button-field');
            var $options = $field.find('.pili-button-option');

            $options.each(function() {
                var $option = $(this);
                var text = $option.find('div').first().text().toLowerCase();
                var description = $option.find('.text-xs').text().toLowerCase();
                
                if (text.includes(searchTerm) || description.includes(searchTerm)) {
                    $option.removeClass('hidden').addClass('animate-fadeIn');
                } else {
                    $option.addClass('hidden').removeClass('animate-fadeIn');
                }
            });

            this.updateSearchResults($field, searchTerm);
        },

        /**
         * 键盘导航
         */
        handleKeyboardNavigation: function(e, $option) {
            var $field = $option.closest('.pili-button-field');
            var $visibleOptions = $field.find('.pili-button-option:not(.hidden)');
            var currentIndex = $visibleOptions.index($option);
            var $target = null;

            switch (e.which) {
                case 37:
                case 38:
                    e.preventDefault();
                    $target = currentIndex > 0 ? $visibleOptions.eq(currentIndex - 1) : $visibleOptions.last();
                    break;
                case 39:
                case 40:
                    e.preventDefault();
                    $target = currentIndex < $visibleOptions.length - 1 ? $visibleOptions.eq(currentIndex + 1) : $visibleOptions.first();
                    break;
                case 32:
                case 13:
                    e.preventDefault();
                    $option.find('.pili-button-input').trigger('click');
                    break;
            }

            if ($target) {
                $target.find('.pili-button-input').focus();
            }
        },

        /**
         * 更新字段状态
         */
        updateFieldState: function($field) {
            $field.find('.pili-button-option').each(function() {
                var $option = $(this);
                var $input = $option.find('.pili-button-input');
                var $button = $option.find('button');

                if ($input.is(':checked')) {
                    $option.addClass('selected');
                    XunButtonField.updateButtonVisualState($button, true);
                } else {
                    $option.removeClass('selected');
                    XunButtonField.updateButtonVisualState($button, false);
                }
            });
        },

        /**
         * 更新按钮的视觉状态
         */
        updateButtonVisualState: function($button, isChecked) {
            var allStateClasses = [
                'bg-transparent', 'bg-blue-600', 'bg-green-600', 'bg-purple-600', 'bg-red-600', 'bg-gray-600',
                'bg-white', 'bg-gray-50', 'bg-gray-100', 'bg-gray-200', 'bg-gray-300',
                'bg-blue-50', 'bg-green-50', 'bg-purple-50', 'bg-red-50',
                'bg-blue-100', 'bg-green-100', 'bg-purple-100', 'bg-red-100',
                'bg-blue-200', 'bg-green-200', 'bg-purple-200', 'bg-red-200',
                'text-white', 'text-gray-700', 'text-gray-600', 'text-gray-500', 'text-gray-900',
                'text-blue-600', 'text-green-600', 'text-purple-600', 'text-red-600',
                'text-blue-500', 'text-green-500', 'text-purple-500', 'text-red-500',
                'text-blue-700', 'text-green-700', 'text-purple-700', 'text-red-700',
                'text-blue-900', 'text-green-900', 'text-purple-900', 'text-red-900',
                'border-blue-600', 'border-green-600', 'border-purple-600',
                'border-red-600', 'border-gray-600', 'border-gray-300', 'border-gray-200',
                'shadow-md', 'shadow-sm', 'shadow-lg', 'shadow-xl', 'shadow-none', 'shadow-inner',
                'hover:bg-gray-50', 'hover:bg-gray-100', 'hover:bg-blue-50', 'hover:bg-blue-100',
                'hover:bg-green-50', 'hover:bg-green-100', 'hover:bg-purple-50', 'hover:bg-purple-100',
                'hover:bg-red-50', 'hover:bg-red-100', 'hover:text-blue-700', 'hover:text-green-700',
                'hover:text-purple-700', 'hover:text-red-700'
            ];

            $button.removeClass(allStateClasses.join(' '));

            var $container = $button.closest('.pili-button-group');
            var color = $container.data('color') || 'blue';
            var style = $container.data('style') || 'default';

            if (isChecked) {
                $button.attr('style', '');

                switch(color) {
                    case 'green':
                        $button.addClass('bg-green-600 text-white border-green-600 shadow-md');
                        $button.css({
                            'background-color': '#059669 !important',
                            'color': '#ffffff !important',
                            'border-color': '#059669 !important'
                        });
                        break;
                    case 'purple':
                        $button.addClass('bg-purple-600 text-white border-purple-600 shadow-md');
                        $button.css({
                            'background-color': '#9333ea !important',
                            'color': '#ffffff !important',
                            'border-color': '#9333ea !important'
                        });
                        break;
                    case 'red':
                        $button.addClass('bg-red-600 text-white border-red-600 shadow-md');
                        $button.css({
                            'background-color': '#dc2626 !important',
                            'color': '#ffffff !important',
                            'border-color': '#dc2626 !important'
                        });
                        break;
                    case 'gray':
                        $button.addClass('bg-gray-600 text-white border-gray-600 shadow-md');
                        $button.css({
                            'background-color': '#4b5563 !important',
                            'color': '#ffffff !important',
                            'border-color': '#4b5563 !important'
                        });
                        break;
                    default:
                        $button.addClass('bg-blue-600 text-white border-blue-600 shadow-md');
                        $button.css({
                            'background-color': '#2563eb !important',
                            'color': '#ffffff !important',
                            'border-color': '#2563eb !important'
                        });
                        break;
                }
            } else {
                $button.addClass('bg-white text-gray-700 border-gray-300 hover:bg-gray-50');
                $button.css({
                    'background-color': '#ffffff !important',
                    'color': '#374151 !important',
                    'border-color': '#d1d5db !important'
                });
            }
        },

        /**
         * 更新搜索结果
         */
        updateSearchResults: function($field, searchTerm) {
            var $visibleOptions = $field.find('.pili-button-option:not(.hidden)');

            if (searchTerm && $visibleOptions.length === 0) {
                if (!$field.find('.no-results').length) {
                    var noResultsHtml = '<div class="no-results flex flex-col items-center justify-center py-12 text-gray-500">' +
                        '<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">' +
                        '<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>' +
                        '</svg>' +
                        '</div>' +
                        '<p class="text-sm font-medium text-gray-600 mb-1">' + buttonI18n('noResults', '未找到匹配的选项') + '</p>' +
                        '<p class="text-xs text-gray-400">' + buttonI18n('tryOtherKeyword', '尝试使用其他关键词搜索') + '</p>' +
                        '</div>';
                    $field.find('.pili-button-group').after(noResultsHtml);
                }
            } else {
                $field.find('.no-results').remove();
            }

            if (searchTerm) {
                var totalCount = $field.find('.pili-button-option').length;
                var visibleCount = $visibleOptions.length;

                if (!$field.find('.search-stats').length && visibleCount > 0) {
                    var statsHtml = '<div class="search-stats text-center mb-4">' +
                        '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' +
                        buttonFormatCount(buttonI18n('showingCount', '显示 %1$d / %2$d 项'), visibleCount, totalCount) +
                        '</span>' +
                        '</div>';
                    $field.find('.pili-button-group').before(statsHtml);
                }
            } else {
                $field.find('.search-stats').remove();
            }
        },

        /**
         * 触发变化事件
         */
        triggerChangeEvent: function($field) {
            var fieldId = $field.data('field-id');
            var selectedValues = [];
            
            $field.find('.pili-button-input:checked').each(function() {
                selectedValues.push($(this).val());
            });

            $field.trigger('xun:button:change', {
                fieldId: fieldId,
                values: selectedValues,
                count: selectedValues.length
            });
        },

        /**
         * 重新初始化字段（用于动态添加的字段）
         */
        reinit: function() {
            this.initializeFields();
        },

        /**
         * 获取字段值
         */
        getValue: function(fieldId) {
            var $field = $('.pili-button-field[data-field-id="' + fieldId + '"]');
            var values = [];
            
            $field.find('.pili-button-input:checked').each(function() {
                values.push($(this).val());
            });
            
            return values;
        },

        /**
         * 设置字段值
         */
        setValue: function(fieldId, values) {
            var $field = $('.pili-button-field[data-field-id="' + fieldId + '"]');
            values = Array.isArray(values) ? values : [values];
            
            $field.find('.pili-button-input').prop('checked', false);
            
            values.forEach(function(value) {
                $field.find('.pili-button-input[value="' + value + '"]').prop('checked', true);
            });
            
            this.updateFieldState($field);
        },

        /**
         * 禁用/启用选项
         */
        toggleOption: function(fieldId, optionValue, disabled) {
            var $field = $('.pili-button-field[data-field-id="' + fieldId + '"]');
            var $option = $field.find('.pili-button-option[data-value="' + optionValue + '"]');
            var $input = $option.find('.pili-button-input');
            
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
        XunButtonField.init();
    });

    PILI.ButtonField = XunButtonField;

})(jQuery);
