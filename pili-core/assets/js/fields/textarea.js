/**
 * Textarea Field JavaScript - 基础字段增强
 * 
 * 提供textarea字段的增强功能：自动调整高度、字符计数、键盘快捷键等
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.1.0
 */

(function($) {
    'use strict';

    function textareaBag() {
        return (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('textarea')) || {};
    }

    /**
     * Textarea Field 类
     */
    var XunTextareaField = {

        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initAutoResize();
            this.initCharacterCount();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;

            $(document).on('input', '.pili-textarea-input', function() {
                var $textarea = $(this);
                var $container = $textarea.closest('.pili-textarea-field');

                if ($textarea.data('auto-resize')) {
                    self.autoResize($textarea);
                }

                if ($textarea.data('show-count')) {
                    self.updateCharacterCount($textarea, $container);
                }

                self.validateLength($textarea);
            });

            $(document).on('keydown', '.pili-textarea-input', function(e) {
                self.handleKeyboardShortcuts(e, $(this));
            });

            $(document).on('paste', '.pili-textarea-input', function(e) {
                var $textarea = $(this);
                setTimeout(function() {
                    if ($textarea.data('auto-resize')) {
                        self.autoResize($textarea);
                    }
                    if ($textarea.data('show-count')) {
                        self.updateCharacterCount($textarea, $textarea.closest('.pili-textarea-field'));
                    }
                    self.validateLength($textarea);
                }, 10);
            });

            if (window.ResizeObserver) {
                var resizeObserver = new ResizeObserver(function(entries) {
                    entries.forEach(function(entry) {
                        var $textarea = $(entry.target);
                        if ($textarea.hasClass('pili-textarea-input')) {
                            if ($textarea.data('show-count')) {
                                self.updateCharacterCount($textarea, $textarea.closest('.pili-textarea-field'));
                            }
                            self.validateLength($textarea);
                        }
                    });
                });

                var mutationObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    var $node = $(node);
                                    $node.find('.pili-textarea-input').each(function() {
                                        resizeObserver.observe(this);
                                    });
                                    if ($node.hasClass('pili-textarea-input')) {
                                        resizeObserver.observe(node);
                                    }
                                }
                            });
                        }
                    });
                });

                mutationObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });

                $('.pili-textarea-input').each(function() {
                    resizeObserver.observe(this);
                });
            } else {
                $(document).on('mouseup', '.pili-textarea-input', function() {
                    var $textarea = $(this);
                    setTimeout(function() {
                        if ($textarea.data('show-count')) {
                            self.updateCharacterCount($textarea, $textarea.closest('.pili-textarea-field'));
                        }
                        self.validateLength($textarea);
                    }, 100);
                });
            }
        },

        /**
         * 初始化自动调整高度
         *
         * @param {jQuery} [$root]
         */
        initAutoResize: function($root) {
            var self = this;
            var $scope = $root && $root.length ? $root : $(document);
            $scope.find('.pili-textarea-input[data-auto-resize="true"]').each(function() {
                self.autoResize($(this));
            });
        },

        /**
         * 自动调整高度
         */
        autoResize: function($textarea) {
            $textarea.css('height', 'auto');
            
            var scrollHeight = $textarea[0].scrollHeight;
            var minHeight = parseInt($textarea.css('min-height')) || 80;
            var maxHeight = parseInt($textarea.css('max-height')) || 400;
            
            var newHeight = Math.max(minHeight, Math.min(scrollHeight, maxHeight));
            
            $textarea.css('height', newHeight + 'px');
        },

        /**
         * 初始化字符计数
         *
         * @param {jQuery} [$root]
         */
        initCharacterCount: function($root) {
            var self = this;
            var $scope = $root && $root.length ? $root : $(document);
            $scope.find('.pili-textarea-input[data-show-count="true"]').each(function() {
                var $textarea = $(this);
                var $container = $textarea.closest('.pili-textarea-field');
                self.updateCharacterCount($textarea, $container);
            });
        },

        /**
         * 更新字符计数
         */
        updateCharacterCount: function($textarea, $container) {
            var currentLength = $textarea.val().length;
            var maxLength = parseInt($textarea.attr('maxlength')) || 0;

            var $counter = $container.find('.pili-textarea-counter');
            var $currentCount = $counter.find('.current-count');
            var $maxCount = $counter.find('.max-count');

            $currentCount.text(currentLength);

            if (maxLength > 0) {
                var percentage = (currentLength / maxLength) * 100;

                $counter.removeClass('text-gray-500 text-yellow-600 text-red-600 bg-white bg-yellow-50 bg-red-50 border-gray-200 border-yellow-300 border-red-300');

                if (percentage >= 100) {
                    $counter.addClass('text-red-600 bg-red-50 border-red-300');
                } else if (percentage >= 80) {
                    $counter.addClass('text-yellow-600 bg-yellow-50 border-yellow-300');
                } else {
                    $counter.addClass('text-gray-500 bg-white border-gray-200');
                }
            } else {
                $counter.removeClass('text-yellow-600 text-red-600 bg-yellow-50 bg-red-50 border-yellow-300 border-red-300');
                $counter.addClass('text-gray-500 bg-white border-gray-200');
            }
        },

        /**
         * 验证长度
         */
        validateLength: function($textarea) {
            var value = $textarea.val();
            var currentLength = value.length;
            var maxLength = parseInt($textarea.attr('maxlength')) || 0;
            var minLength = parseInt($textarea.attr('minlength')) || 0;
            
            var isValid = true;
            var errorMessage = '';
            
            if (maxLength > 0 && currentLength > maxLength) {
                isValid = false;
                errorMessage = textareaBag().maxLengthExceeded || '超出最大长度';
            }
            
            if (minLength > 0 && currentLength < minLength) {
                isValid = false;
                errorMessage = textareaBag().minLengthRequired || '未达到最小长度';
            }
            
            if (isValid) {
                $textarea.removeClass('outline-red-300 focus:outline-red-600')
                        .addClass('outline-gray-300 focus:outline-indigo-600');
            } else {
                $textarea.removeClass('outline-gray-300 focus:outline-indigo-600')
                        .addClass('outline-red-300 focus:outline-red-600');
            }
            
            return isValid;
        },

        /**
         * 处理键盘快捷键
         */
        handleKeyboardShortcuts: function(e, $textarea) {
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                var $form = $textarea.closest('form');
                if ($form.length) {
                    e.preventDefault();
                    $form.submit();
                }
            }
            
            if (e.keyCode === 9 && !e.shiftKey) {
                e.preventDefault();
                var start = $textarea[0].selectionStart;
                var end = $textarea[0].selectionEnd;
                var value = $textarea.val();
                
                $textarea.val(value.substring(0, start) + '\t' + value.substring(end));
                $textarea[0].selectionStart = $textarea[0].selectionEnd = start + 1;
                
                $textarea.trigger('input');
            }
        }
    };

    PILI.registerBoot('textarea', function($root) {
        XunTextareaField.initAutoResize($root);
        XunTextareaField.initCharacterCount($root);
    });

    $(document).ready(function() {
        XunTextareaField.init();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        PILI.boot('textarea', $container && $container.length ? $container : undefined);
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        PILI.boot('textarea');
    });

    PILI.TextareaField = XunTextareaField;

})(jQuery);
