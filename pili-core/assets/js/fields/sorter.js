/**
 * PILI Framework Sorter 字段 JavaScript
 * 
 * 这个文件包含了排序器字段的所有交互逻辑，包括拖拽排序、
 * 启用/禁用切换、批量操作、实时预览等功能。
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    var sorterStrings = (PILI.bag('sorter') && PILI.bag('sorter').strings) || {};

    function str(key, fallback) {
        return sorterStrings[key] || fallback;
    }

    /**
     * Sorter 字段类
     */
    class XunSorter {
        
        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$enabled = $container.find('.pili-sorter-enabled');
            this.$disabled = $container.find('.pili-sorter-disabled');
            this.$enabledCount = $container.find('.pili-enabled-count');
            this.$disabledCount = $container.find('.pili-disabled-count');
            this.$preview = $container.find('.pili-sorter-preview');
            this.fieldId = $container.data('field-id');

            this.draggedElement = null;
            this.draggedData = null;
            this.placeholder = null;

            this.init();
        }
        
        /**
         * 初始化排序器
         */
        init() {
            this.initSortable();
            this.initBatchOperations();
            this.initPreview();
            this.updateCounts();
            this.updateEmptyStates();
        }
        
        /**
         * 初始化拖拽排序功能
         */
        initSortable() {
            this.initNativeDragDrop();
        }

        /**
         * 初始化原生拖拽功能
         */
        initNativeDragDrop() {
            const self = this;

            this.$container.find('.pili-sortable-item').each(function() {
                this.draggable = true;
            });

            this.$container.on('dragstart', '.pili-sortable-item', function(e) {
                self.draggedElement = this;
                self.draggedData = {
                    key: $(this).data('key'),
                    html: $(this)[0].outerHTML,
                    sourceContainer: $(this).parent()
                };

                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/html', self.draggedData.html);

                $(this).addClass('pili-dragging');

                self.placeholder = $('<div class="pili-drag-placeholder h-12 border-2 border-dashed border-blue-300 rounded-lg bg-blue-50 opacity-50 mb-2"></div>');

                self.$enabled.addClass('pili-drop-target');
                self.$disabled.addClass('pili-drop-target');

                setTimeout(() => {
                    $(self.draggedElement).css('opacity', '0.3');
                }, 0);
            });

            this.$container.on('dragenter', '.pili-sorter-enabled, .pili-sorter-disabled', function(e) {
                e.preventDefault();
                $(this).addClass('pili-drop-active');
            });

            this.$container.on('dragover', '.pili-sorter-enabled, .pili-sorter-disabled', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';

                const $container = $(this);
                const $items = $container.find('.pili-sortable-item:not(.pili-dragging)');
                const mouseY = e.originalEvent.clientY;
                let insertAfter = null;

                $items.each(function() {
                    const rect = this.getBoundingClientRect();
                    const itemMiddle = rect.top + rect.height / 2;

                    if (mouseY > itemMiddle) {
                        insertAfter = this;
                    }
                });

                $container.find('.pili-drag-placeholder').remove();

                if (insertAfter) {
                    $(insertAfter).after(self.placeholder);
                } else {
                    $container.prepend(self.placeholder);
                }
            });

            this.$container.on('dragleave', '.pili-sorter-enabled, .pili-sorter-disabled', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.originalEvent.clientX;
                const y = e.originalEvent.clientY;

                if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                    $(this).removeClass('pili-drop-active');
                }
            });

            this.$container.on('drop', '.pili-sorter-enabled, .pili-sorter-disabled', function(e) {
                e.preventDefault();

                if (!self.draggedElement) return;

                const $dropContainer = $(this);
                const $placeholder = $dropContainer.find('.pili-drag-placeholder');

                if ($placeholder.length > 0) {
                    $placeholder.replaceWith(self.draggedElement);
                } else {
                    $dropContainer.append(self.draggedElement);
                }

                self.handleItemMove($(self.draggedElement));

                self.cleanupDrag();
            });

            this.$container.on('dragend', '.pili-sortable-item', function() {
                self.cleanupDrag();
            });

            this.addDynamicStyles();
        }

        /**
         * 清理拖拽状态
         */
        cleanupDrag() {
            this.$container.find('.pili-dragging').removeClass('pili-dragging').css('opacity', '');
            this.$container.find('.pili-drop-active').removeClass('pili-drop-active');
            this.$container.find('.pili-drop-target').removeClass('pili-drop-target');
            this.$container.find('.pili-drag-placeholder').remove();

            this.draggedElement = null;
            this.draggedData = null;
            this.placeholder = null;
        }
        
        /**
         * 处理项目移动
         * 
         * @param {jQuery} $item 移动的项目元素
         */
        handleItemMove($item) {
            const $parent = $item.parent();
            const isEnabled = $parent.hasClass('pili-sorter-enabled');
            const key = $item.data('key');
            
            const $input = $item.find('input[type="hidden"]');
            const currentName = $input.attr('name');
            const newType = isEnabled ? 'enabled' : 'disabled';
            const newName = currentName.replace(/\[(enabled|disabled)\]/, `[${newType}]`);
            
            $input.attr('name', newName);
            
            this.updateItemStatus($item, isEnabled);
            
            this.updateCounts();
            this.updateEmptyStates();
            
            this.updatePreview();
            
            this.$container.trigger('xun:sorter:change', {
                item: key,
                type: newType,
                order: this.getOrder()
            });
        }
        
        /**
         * 更新项目状态指示器
         * 
         * @param {jQuery} $item 项目元素
         * @param {boolean} isEnabled 是否启用
         */
        updateItemStatus($item, isEnabled) {
            const $status = $item.find('.inline-flex.items-center.rounded-full');
            
            if (isEnabled) {
                $status.removeClass('bg-gray-100 text-gray-800')
                       .addClass('bg-green-100 text-green-800')
                       .html('<svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>' + str('enabled', '已启用'));
            } else {
                $status.removeClass('bg-green-100 text-green-800')
                       .addClass('bg-gray-100 text-gray-800')
                       .html('<svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>' + str('disabled', '已禁用'));
            }
        }
        
        /**
         * 初始化批量操作
         */
        initBatchOperations() {
            const self = this;
            
            this.$container.on('click', '.pili-sorter-enable-all', function(e) {
                e.preventDefault();
                self.enableAll();
            });
            
            this.$container.on('click', '.pili-sorter-disable-all', function(e) {
                e.preventDefault();
                self.disableAll();
            });
        }
        
        /**
         * 启用所有项目
         */
        enableAll() {
            const $items = this.$disabled.find('.pili-sortable-item');
            const self = this;

            $items.each(function() {
                const $item = $(this);
                self.$enabled.append($item);
                self.handleItemMove($item);
            });
        }
        
        /**
         * 禁用所有项目
         */
        disableAll() {
            const $items = this.$enabled.find('.pili-sortable-item');
            const self = this;

            $items.each(function() {
                const $item = $(this);
                self.$disabled.append($item);
                self.handleItemMove($item);
            });
        }
        
        /**
         * 更新计数显示
         */
        updateCounts() {
            const enabledCount = this.$enabled.find('.pili-sortable-item').length;
            const disabledCount = this.$disabled.find('.pili-sortable-item').length;
            
            this.$enabledCount.text(enabledCount);
            this.$disabledCount.text(disabledCount);
        }
        
        /**
         * 更新空状态显示
         */
        updateEmptyStates() {
            const $enabledItems = this.$enabled.find('.pili-sortable-item');
            const $enabledPlaceholder = this.$enabled.find('.pili-empty-placeholder');
            
            if ($enabledItems.length === 0) {
                if ($enabledPlaceholder.length === 0) {
                    this.$enabled.append(this.createEmptyPlaceholder('enabled'));
                } else {
                    $enabledPlaceholder.show();
                }
            } else {
                $enabledPlaceholder.hide();
            }
            
            const $disabledItems = this.$disabled.find('.pili-sortable-item');
            const $disabledPlaceholder = this.$disabled.find('.pili-empty-placeholder');
            
            if ($disabledItems.length === 0) {
                if ($disabledPlaceholder.length === 0) {
                    this.$disabled.append(this.createEmptyPlaceholder('disabled'));
                } else {
                    $disabledPlaceholder.show();
                }
            } else {
                $disabledPlaceholder.hide();
            }
        }
        
        /**
         * 创建空状态占位符
         * 
         * @param {string} type 类型（enabled/disabled）
         * @returns {string} HTML字符串
         */
        createEmptyPlaceholder(type) {
            const isEnabled = type === 'enabled';
            const icon = isEnabled 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />';
            const text = isEnabled ? str('dragHereEnable', '拖拽项目到此处启用') : str('dragHereDisable', '拖拽项目到此处禁用');
            
            return `<div class="pili-empty-placeholder text-center py-8 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    ${icon}
                </svg>
                <p class="mt-2 text-sm">${text}</p>
            </div>`;
        }
        
        /**
         * 初始化实时预览
         */
        initPreview() {
            if (this.$preview.length > 0) {
                this.updatePreview();
            }
        }
        
        /**
         * 更新实时预览
         */
        updatePreview() {
            if (this.$preview.length === 0) return;
            
            const order = this.getOrder();
            const previewHtml = this.generatePreviewHtml(order);
            this.$preview.html(previewHtml);
        }
        
        /**
         * 获取当前排序
         * 
         * @returns {Object} 排序数据
         */
        getOrder() {
            const enabled = [];
            const disabled = [];
            
            this.$enabled.find('.pili-sortable-item').each(function() {
                const $item = $(this);
                enabled.push({
                    key: $item.data('key'),
                    label: $item.find('span').text().trim()
                });
            });
            
            this.$disabled.find('.pili-sortable-item').each(function() {
                const $item = $(this);
                disabled.push({
                    key: $item.data('key'),
                    label: $item.find('span').text().trim()
                });
            });
            
            return { enabled, disabled };
        }
        
        /**
         * 生成预览HTML
         * 
         * @param {Object} order 排序数据
         * @returns {string} HTML字符串
         */
        generatePreviewHtml(order) {
            let html = '<div class="space-y-2">';
            
            if (order.enabled.length > 0) {
                html += '<div><strong>' + str('enabledItems', '已启用项目：') + '</strong></div>';
                html += '<ol class="list-decimal list-inside space-y-1 ml-4">';
                order.enabled.forEach(item => {
                    html += `<li class="text-green-600">${item.label}</li>`;
                });
                html += '</ol>';
            }
            
            if (order.disabled.length > 0) {
                html += '<div class="mt-3"><strong>' + str('disabledItems', '已禁用项目：') + '</strong></div>';
                html += '<ul class="list-disc list-inside space-y-1 ml-4">';
                order.disabled.forEach(item => {
                    html += `<li class="text-gray-500">${item.label}</li>`;
                });
                html += '</ul>';
            }
            
            if (order.enabled.length === 0 && order.disabled.length === 0) {
                html += '<p class="text-gray-500 italic">' + str('empty', '暂无项目') + '</p>';
            }
            
            html += '</div>';
            return html;
        }
        

        
        /**
         * 添加动态样式
         */
        addDynamicStyles() {
            if ($('#pili-sorter-styles').length > 0) return;
            
            const styles = `
                <style id="pili-sorter-styles">
                    .pili-sortable-item.pili-dragging {
                        opacity: 0.8;
                        cursor: grabbing !important;
                        z-index: 9999 !important;
                    }

                    .pili-placeholder-active {
                        opacity: 0.6;
                        transition: opacity 0.2s ease;
                    }
                    
                    .pili-sorter-enabled.pili-drop-target,
                    .pili-sorter-disabled.pili-drop-target {
                        border-color: #3b82f6;
                        background-color: rgba(59, 130, 246, 0.05);
                    }
                    
                    .pili-sorter-enabled.pili-drop-active {
                        border-color: #10b981;
                        background-color: rgba(16, 185, 129, 0.1);
                    }
                    
                    .pili-sorter-disabled.pili-drop-active {
                        border-color: #6b7280;
                        background-color: rgba(107, 114, 128, 0.1);
                    }
                    
                    .pili-sortable-placeholder {
                        margin-bottom: 0.5rem;
                        border-radius: 0.5rem;
                    }
                    
                    .pili-drag-handle {
                        cursor: grab;
                    }
                    
                    .pili-drag-handle:active {
                        cursor: grabbing;
                    }
                </style>
            `;
            
            $('head').append(styles);
        }
    }

    /**
     * 初始化所有排序器字段
     */
    function initSorterFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-sorter-field').each(function() {
            const $container = $(this);
            if (!$container.data('pili-sorter-initialized')) {
                new XunSorter($container);
                $container.data('pili-sorter-initialized', true);
            }
        });
    }

    PILI.registerBoot('sorter', initSorterFields);

    $(document).ready(function() {
        initSorterFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        initSorterFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        initSorterFields($container && $container.length ? $container : undefined);
    });

})(jQuery);
