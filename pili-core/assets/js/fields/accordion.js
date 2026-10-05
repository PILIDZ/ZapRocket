/**
 * XUN Accordion Field JavaScript
 * 
 * 现代化的手风琴字段交互逻辑
 * 支持流畅动画、键盘导航、无障碍访问等高级功能
 * 比CSF更好的用户体验
 * 
 * @since 1.0.0
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Accordion Field 类
     */
    var XunAccordionField = {
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initializeFields();
            this.setupKeyboardNavigation();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;

            $(document).on('click', '.pili-accordion-trigger', function(e) {
                e.preventDefault();
                self.toggleAccordion($(this));
            });

            $(document).on('keydown', '.pili-accordion-trigger', function(e) {
                self.handleKeyboardNavigation(e, $(this));
            });

            $(window).on('resize', function() {
                self.recalculateHeights();
            });

            $(document).on('visibilitychange', function() {
                if (!document.hidden) {
                    setTimeout(function() {
                        self.reinitializeFields();
                    }, 100);
                }
            });

            $(window).on('focus', function() {
                setTimeout(function() {
                    self.reinitializeFields();
                }, 100);
            });
        },

        /**
         * 初始化所有手风琴字段
         */
        initializeFields: function() {
            $('.pili-accordion-field').each(function() {
                var $field = $(this);
                XunAccordionField.initializeField($field);
            });
        },

        /**
         * 重新初始化所有字段 - 用于页面切换后的修复
         */
        reinitializeFields: function() {
            var self = this;

            $('.pili-accordion-field').each(function() {
                var $field = $(this);

                $field.find('.pili-accordion-item').each(function() {
                    var $item = $(this);
                    var $trigger = $item.find('.pili-accordion-trigger');
                    var $content = $item.find('.pili-accordion-content');

                    var shouldBeOpen = $trigger.data('default-open') === true ||
                                      $trigger.data('default-open') === 'true' ||
                                      $content.data('default-open') === true ||
                                      $content.data('default-open') === 'true' ||
                                      $content.hasClass('pili-accordion-open');

                    var isCurrentlyOpen = $trigger.attr('aria-expanded') === 'true';

                    if (shouldBeOpen && !isCurrentlyOpen) {
                        self.openAccordion($trigger);
                    } else if (!shouldBeOpen && isCurrentlyOpen) {
                        self.closeAccordion($trigger);
                    }
                });
            });
        },

        /**
         * 初始化单个手风琴字段 - 修复默认展开状态问题
         */
        initializeField: function($field) {
            var multiple = $field.data('multiple');
            var collapsible = $field.data('collapsible');

            $field.find('.pili-accordion-item').each(function(index) {
                var $item = $(this);
                var $trigger = $item.find('.pili-accordion-trigger');
                var $content = $item.find('.pili-accordion-content');
                var $icon = $trigger.find('.pili-accordion-icon');

                var isDefaultOpen = $trigger.data('default-open') === true ||
                                   $trigger.data('default-open') === 'true' ||
                                   $content.data('default-open') === true ||
                                   $content.data('default-open') === 'true' ||
                                   $content.hasClass('pili-accordion-open') ||
                                   !$content.hasClass('max-h-0');

                $trigger.attr('aria-expanded', isDefaultOpen);
                $content.attr('aria-hidden', !isDefaultOpen);

                if (isDefaultOpen) {
                    $icon.addClass('rotate-180');
                    $item.addClass('pili-accordion-open');
                    $content.removeClass('max-h-0');
                    $content.addClass('pili-accordion-open');

                    setTimeout(function() {
                        if ($content.is(':visible')) {
                            var contentHeight = $content[0].scrollHeight;
                            if (contentHeight > 0) {
                                $content.css({
                                    'max-height': 'none',
                                    'opacity': '1'
                                });
                            } else {
                                $content.css({
                                    'max-height': 'none',
                                    'opacity': '1',
                                    'display': 'block'
                                });
                            }
                        }
                    }, 50);
                } else {
                    $icon.removeClass('rotate-180');
                    $item.removeClass('pili-accordion-open');
                    $content.addClass('max-h-0');
                    $content.removeClass('pili-accordion-open');
                    $content.css({
                        'max-height': '0',
                        'opacity': '0'
                    });
                }
            });

            if (!multiple) {
                var $openItems = $field.find('.pili-accordion-content.pili-accordion-open');
                if ($openItems.length > 1) {
                    $openItems.slice(1).each(function() {
                        XunAccordionField.closeAccordion($(this).siblings('.pili-accordion-trigger'));
                    });
                }
            }
        },

        /**
         * 切换手风琴状态
         */
        toggleAccordion: function($trigger) {
            var $field = $trigger.closest('.pili-accordion-field');
            var $item = $trigger.closest('.pili-accordion-item');
            var $content = $item.find('.pili-accordion-content');
            var $icon = $trigger.find('.pili-accordion-icon');
            var isOpen = $trigger.attr('aria-expanded') === 'true';
            var multiple = $field.data('multiple');
            var collapsible = $field.data('collapsible');

            if (!multiple && !isOpen) {
                $field.find('.pili-accordion-trigger').each(function() {
                    var $otherTrigger = $(this);
                    if ($otherTrigger[0] !== $trigger[0] && $otherTrigger.attr('aria-expanded') === 'true') {
                        XunAccordionField.closeAccordion($otherTrigger);
                    }
                });
            }

            if (isOpen) {
                if (collapsible) {
                    this.closeAccordion($trigger);
                }
            } else {
                this.openAccordion($trigger);
            }

            this.triggerEvent($field, 'accordion:toggled', {
                trigger: $trigger,
                item: $item,
                isOpen: !isOpen
            });
        },

        /**
         * 打开手风琴项目 - 改进的展开逻辑
         */
        openAccordion: function($trigger) {
            var $item = $trigger.closest('.pili-accordion-item');
            var $content = $item.find('.pili-accordion-content');
            var $icon = $trigger.find('.pili-accordion-icon');

            $trigger.attr('aria-expanded', 'true');
            $content.attr('aria-hidden', 'false');

            $content.removeClass('max-h-0');
            $content.addClass('pili-accordion-open');

            $content.css({
                'max-height': 'none',
                'opacity': '1'
            });

            $icon.addClass('rotate-180');

            $item.addClass('pili-accordion-open');

            this.triggerEvent($trigger.closest('.pili-accordion-field'), 'accordion:opened', {
                trigger: $trigger,
                item: $item
            });
        },

        /**
         * 关闭手风琴项目 - 改进的折叠逻辑
         */
        closeAccordion: function($trigger) {
            var $item = $trigger.closest('.pili-accordion-item');
            var $content = $item.find('.pili-accordion-content');
            var $icon = $trigger.find('.pili-accordion-icon');

            $trigger.attr('aria-expanded', 'false');
            $content.attr('aria-hidden', 'true');

            $content.addClass('max-h-0');
            $content.removeClass('pili-accordion-open');

            $content.css({
                'max-height': '0',
                'opacity': '0'
            });

            $icon.removeClass('rotate-180');

            $item.removeClass('pili-accordion-open');

            this.triggerEvent($trigger.closest('.pili-accordion-field'), 'accordion:closed', {
                trigger: $trigger,
                item: $item
            });
        },

        /**
         * 设置内容高度
         */
        setContentHeight: function($content) {
            var hadMaxHeight = $content.hasClass('max-h-0');

            if (hadMaxHeight) {
                $content.removeClass('max-h-0');
                var contentHeight = $content[0].scrollHeight;
                $content.css('max-height', contentHeight + 'px');
                $content.addClass('max-h-0');
            } else {
                var currentMaxHeight = $content.css('max-height');
                if (currentMaxHeight === 'none' || currentMaxHeight === 'auto') {
                    return;
                } else {
                    var contentHeight = $content[0].scrollHeight;
                    $content.css('max-height', contentHeight + 'px');
                }
            }
        },

        /**
         * 重新计算所有打开项目的高度
         */
        recalculateHeights: function() {
            $('.pili-accordion-content:not(.max-h-0)').each(function() {
                XunAccordionField.setContentHeight($(this));
            });
        },

        /**
         * 设置键盘导航
         */
        setupKeyboardNavigation: function() {
            $('.pili-accordion-field').each(function() {
                var $field = $(this);
                var $triggers = $field.find('.pili-accordion-trigger');
                
                $triggers.each(function(index) {
                    $(this).attr('tabindex', '0');
                });
            });
        },

        /**
         * 处理键盘导航
         */
        handleKeyboardNavigation: function(e, $trigger) {
            var $field = $trigger.closest('.pili-accordion-field');
            var $triggers = $field.find('.pili-accordion-trigger');
            var currentIndex = $triggers.index($trigger);

            switch (e.which) {
                case 13:
                case 32:
                    e.preventDefault();
                    this.toggleAccordion($trigger);
                    break;

                case 38:
                    e.preventDefault();
                    var prevIndex = currentIndex > 0 ? currentIndex - 1 : $triggers.length - 1;
                    $triggers.eq(prevIndex).focus();
                    break;

                case 40:
                    e.preventDefault();
                    var nextIndex = currentIndex < $triggers.length - 1 ? currentIndex + 1 : 0;
                    $triggers.eq(nextIndex).focus();
                    break;

                case 36:
                    e.preventDefault();
                    $triggers.first().focus();
                    break;

                case 35:
                    e.preventDefault();
                    $triggers.last().focus();
                    break;
            }
        },

        /**
         * 展开所有项目
         */
        expandAll: function($field) {
            var multiple = $field.data('multiple');
            
            if (multiple) {
                $field.find('.pili-accordion-trigger').each(function() {
                    var $trigger = $(this);
                    if ($trigger.attr('aria-expanded') !== 'true') {
                        XunAccordionField.openAccordion($trigger);
                    }
                });

                this.triggerEvent($field, 'accordion:expandedAll');
            }
        },

        /**
         * 折叠所有项目
         */
        collapseAll: function($field) {
            var collapsible = $field.data('collapsible');
            
            if (collapsible) {
                $field.find('.pili-accordion-trigger').each(function() {
                    var $trigger = $(this);
                    if ($trigger.attr('aria-expanded') === 'true') {
                        XunAccordionField.closeAccordion($trigger);
                    }
                });

                this.triggerEvent($field, 'accordion:collapsedAll');
            }
        },

        /**
         * 触发自定义事件
         */
        triggerEvent: function($field, eventName, data) {
            var fieldId = $field.data('field-id');
            $field.trigger('xun:accordion:' + eventName, $.extend({
                fieldId: fieldId
            }, data || {}));
        },

        /**
         * 获取字段状态
         */
        getFieldState: function(fieldId) {
            var $field = $('.pili-accordion-field[data-field-id="' + fieldId + '"]');
            var state = {
                fieldId: fieldId,
                items: []
            };

            $field.find('.pili-accordion-item').each(function(index) {
                var $item = $(this);
                var $trigger = $item.find('.pili-accordion-trigger');
                var isOpen = $trigger.attr('aria-expanded') === 'true';

                state.items.push({
                    index: index,
                    isOpen: isOpen,
                    title: $trigger.find('span').text().trim()
                });
            });

            return state;
        },

        /**
         * 设置字段状态
         */
        setFieldState: function(fieldId, state) {
            var $field = $('.pili-accordion-field[data-field-id="' + fieldId + '"]');
            
            if (state.items && Array.isArray(state.items)) {
                state.items.forEach(function(itemState, index) {
                    var $trigger = $field.find('.pili-accordion-trigger').eq(index);
                    if ($trigger.length) {
                        var currentlyOpen = $trigger.attr('aria-expanded') === 'true';
                        if (itemState.isOpen && !currentlyOpen) {
                            XunAccordionField.openAccordion($trigger);
                        } else if (!itemState.isOpen && currentlyOpen) {
                            XunAccordionField.closeAccordion($trigger);
                        }
                    }
                });
            }
        },

        /**
         * 重新初始化字段（用于动态添加的字段）
         */
        reinit: function() {
            this.initializeFields();
            this.setupKeyboardNavigation();
        }
    };

    PILI.registerBoot('accordion', function($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-accordion-field').each(function() {
            XunAccordionField.initializeField($(this));
        });
    });

    $(document).ready(function() {
        setTimeout(function() {
            XunAccordionField.init();
        }, 100);
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        PILI.boot('accordion', $container && $container.length ? $container : undefined);
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        PILI.boot('accordion');
    });

    PILI.AccordionField = XunAccordionField;

})(jQuery);
