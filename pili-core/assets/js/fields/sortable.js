/**
 * PILI Framework Sortable 字段 JavaScript
 * 
 * 这个文件包含了可排序字段的所有交互逻辑，包括拖拽排序、
 * 项目管理、键盘导航、动画效果等功能。
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    var sortableStrings = (PILI.bag('sortable') && PILI.bag('sortable').strings) || {};

    function sortableStr(key, fallback) {
        return sortableStrings[key] || fallback;
    }

    /**
     * Sortable 字段类
     */
    class XunSortable {
        
        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$sortableContainer = $container.find('.pili-sortable-container');
            this.$itemsContainer = $container.find('.pili-sortable-items');
            this.$addButton = $container.find('.pili-sortable-add-item');
            this.$togglePreview = $container.find('.pili-sortable-toggle-preview');
            this.$preview = $container.find('.pili-sortable-preview');
            this.$template = $container.find('.pili-sortable-item-template');
            this.fieldId = $container.data('field-id');
            
            this.config = this.$sortableContainer.data('sortable-config') || {};
            
            this.isPreviewMode = false;
            this.draggedItem = null;
            this.itemCounter = this.getItemCount();
            
            this.init();
        }
        
        /**
         * 初始化可排序字段
         */
        init() {
            this.initSortable();
            this.initEvents();
            this.initKeyboardNavigation();
            this.updateItemNumbers();
        }
        
        /**
         * 初始化拖拽排序
         */
        initSortable() {
            if (!this.config.sortable || !this.$itemsContainer.length) return;

            const self = this;

            this.$itemsContainer.sortable({
                handle: this.config.showHandles ? '.pili-sortable-handle' : '.pili-sortable-item',
                placeholder: 'pili-sortable-placeholder bg-blue-50 border-2 border-dashed border-blue-300 rounded-lg',
                tolerance: 'pointer',
                cursor: 'move',
                opacity: 0.9,
                distance: 5,
                scroll: true,
                scrollSensitivity: 100,
                scrollSpeed: 20,
                cursorAt: {
                    left: 10,
                    top: 10
                },
                helper: 'clone',
                start: function(event, ui) {
                    self.handleSortStart(event, ui);
                },
                update: function(event, ui) {
                    self.handleSortUpdate(event, ui);
                },
                stop: function(event, ui) {
                    self.handleSortStop(event, ui);
                }
            });
        }
        
        /**
         * 初始化事件监听
         */
        initEvents() {
            const self = this;
            
            this.$addButton.on('click', function(e) {
                e.preventDefault();
                self.addItem();
            });
            
            this.$container.on('click', '.pili-sortable-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeItem($(this).closest('.pili-sortable-item'));
            });
            
            this.$container.on('click', '.pili-sortable-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggleItem($(this).closest('.pili-sortable-item'));
            });
            
            this.$togglePreview.on('click', function(e) {
                e.preventDefault();
                self.togglePreview();
            });
            
            this.$container.on('change input', '.pili-sortable-item-content input, .pili-sortable-item-content select, .pili-sortable-item-content textarea', function() {
                self.updatePreview();
            });
        }
        
        /**
         * 初始化键盘导航
         */
        initKeyboardNavigation() {
            if (!this.config.keyboardNav) return;
            
            const self = this;
            
            this.$container.on('keydown', '.pili-sortable-item', function(e) {
                const $item = $(this);
                const $items = self.$itemsContainer.find('.pili-sortable-item');
                const currentIndex = $items.index($item);
                
                switch (e.key) {
                    case 'ArrowUp':
                        if (e.ctrlKey && currentIndex > 0) {
                            e.preventDefault();
                            self.moveItem($item, 'up');
                        }
                        break;
                        
                    case 'ArrowDown':
                        if (e.ctrlKey && currentIndex < $items.length - 1) {
                            e.preventDefault();
                            self.moveItem($item, 'down');
                        }
                        break;
                        
                    case '删除':
                        if (e.ctrlKey && self.config.removable) {
                            e.preventDefault();
                            self.removeItem($item);
                        }
                        break;
                        
                    case 'Enter':
                        if (e.ctrlKey && self.config.collapsible) {
                            e.preventDefault();
                            self.toggleItem($item);
                        }
                        break;
                }
            });
        }
        
        /**
         * 处理排序开始
         */
        handleSortStart(_, ui) {
            ui.item.addClass('pili-sortable-dragging');
            ui.placeholder.height(ui.item.outerHeight());
        }

        /**
         * 处理排序更新
         */
        handleSortUpdate() {
            this.updateItemNumbers();
            this.updatePreview();
        }

        /**
         * 处理排序停止
         */
        handleSortStop(_, ui) {
            ui.item.removeClass('pili-sortable-dragging');
        }
        
        /**
         * 添加项目
         */
        addItem() {
            if (this.config.maxItems > 0 && this.getItemCount() >= this.config.maxItems) {
                alert(PILI.bag('sortable').strings.maxItemsReached);
                return;
            }
            
            const newItemHtml = this.createNewItemHtml();
            const $newItem = $(newItemHtml);
            
            this.$itemsContainer.append($newItem);
            this.itemCounter++;
            
            this.updateItemNumbers();
            
            this.initializeNewItemFields($newItem);
            
            $newItem[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            const $firstInput = $newItem.find('input, select, textarea').first();
            if ($firstInput.length > 0) {
                $firstInput[0].focus();
            }
            
            this.$container.trigger('xun:sortable:add', {
                item: $newItem,
                index: $newItem.index()
            });
        }
        
        /**
         * 删除项目
         */
        removeItem($item) {
            if (this.config.minItems > 0 && this.getItemCount() <= this.config.minItems) {
                alert(PILI.bag('sortable').strings.minItemsRequired);
                return;
            }
            
            if (!confirm(PILI.bag('sortable').strings.confirmRemove)) {
                return;
            }
            
            const itemIndex = $item.index();
            
            if (this.config.animation) {
                $item.addClass('animate-pulse');
                setTimeout(() => {
                    $item.slideUp(300, () => {
                        $item.remove();
                        this.updateItemNumbers();
                        this.updatePreview();
                    });
                }, 150);
            } else {
                $item.remove();
                this.updateItemNumbers();
                this.updatePreview();
            }
            
            this.$container.trigger('xun:sortable:remove', {
                index: itemIndex
            });
        }
        
        /**
         * 切换项目折叠状态
         */
        toggleItem($item) {
            const $content = $item.find('.pili-sortable-item-content');
            const $toggle = $item.find('.pili-sortable-toggle svg');
            const isCollapsed = $content.is(':hidden');
            
            if (isCollapsed) {
                $content.slideDown(200);
                $toggle.removeClass('rotate-180');
                $item.removeClass('pili-collapsed');
            } else {
                $content.slideUp(200);
                $toggle.addClass('rotate-180');
                $item.addClass('pili-collapsed');
            }
            
            this.$container.trigger('xun:sortable:toggle', {
                item: $item,
                collapsed: !isCollapsed
            });
        }
        
        /**
         * 移动项目
         */
        moveItem($item, direction) {
            const $items = this.$itemsContainer.find('.pili-sortable-item');
            const currentIndex = $items.index($item);
            
            if (direction === 'up' && currentIndex > 0) {
                $item.insertBefore($items.eq(currentIndex - 1));
            } else if (direction === 'down' && currentIndex < $items.length - 1) {
                $item.insertAfter($items.eq(currentIndex + 1));
            }
            
            this.updateItemNumbers();
            this.updatePreview();
            
            this.$container.trigger('xun:sortable:move', {
                item: $item,
                direction: direction,
                newIndex: $item.index()
            });
        }
        
        /**
         * 切换预览模式
         */
        togglePreview() {
            this.isPreviewMode = !this.isPreviewMode;
            
            if (this.isPreviewMode) {
                this.$preview.removeClass('hidden');
                this.updatePreview();
            } else {
                this.$preview.addClass('hidden');
            }
            
            this.$togglePreview.toggleClass('bg-blue-100 text-blue-700', this.isPreviewMode);
        }
        
        /**
         * 更新预览内容
         */
        updatePreview() {
            if (!this.isPreviewMode || !this.$preview.length) return;
            
            const items = [];
            this.$itemsContainer.find('.pili-sortable-item').each(function(index) {
                const $item = $(this);
                const title = $item.find('.pili-sortable-item-header h4').text() || sortableStr('itemNumber', '项目 %d').replace('%d', String(index + 1));
                items.push(`${index + 1}. ${title}`);
            });
            
            const previewHtml = items.length > 0 ? items.join('<br>') : sortableStr('noItems', '暂无项目');
            this.$preview.find('.pili-sortable-preview-content').html(previewHtml);
        }
        
        /**
         * 更新项目编号
         */
        updateItemNumbers() {
            if (!this.config.showNumbers) return;
            
            this.$itemsContainer.find('.pili-sortable-item').each(function(index) {
                $(this).find('.pili-sortable-number').text(index + 1);
                $(this).attr('data-item-index', index);
            });
        }
        
        /**
         * 获取项目数量
         */
        getItemCount() {
            return this.$itemsContainer.find('.pili-sortable-item').length;
        }
        
        /**
         * 创建新项目HTML
         */
        createNewItemHtml() {
            return `
                <div class="pili-sortable-item relative bg-white border border-gray-200 rounded-lg shadow-sm transition-all duration-200" data-item-key="new-${this.itemCounter}" data-item-index="${this.getItemCount()}">
                    <div class="pili-sortable-item-header flex items-center justify-between p-3 border-b border-gray-200">
                        <div class="flex items-center space-x-3">
                            ${this.config.showHandles ? '<div class="pili-sortable-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg></div>' : ''}
                            ${this.config.showNumbers ? `<span class="pili-sortable-number inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-gray-500 bg-gray-100 rounded-full">${this.getItemCount() + 1}</span>` : ''}
                            <h4 class="text-sm font-medium text-gray-900">${sortableStr('newItem', '新项目')}</h4>
                        </div>
                        <div class="flex items-center space-x-2">
                            ${this.config.collapsible ? '<button type="button" class="pili-sortable-toggle text-gray-400 hover:text-gray-600 focus:outline-none"><svg class="w-4 h-4 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg></button>' : ''}
                            ${this.config.removable ? '<button type="button" class="pili-sortable-remove text-red-400 hover:text-red-600 focus:outline-none"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>' : ''}
                        </div>
                    </div>
                    <div class="pili-sortable-item-content p-4">
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="${sortableStr('inputPlaceholder', '请输入内容…')}" />
                    </div>
                </div>
            `;
        }
        
        /**
         * 初始化新项目的字段
         */
        initializeNewItemFields($item) {
            if (PILI && PILI.init) {
                PILI.init($item);
            }
        }
        
        /**
         * 获取排序后的数据
         */
        getSortedData() {
            const data = {};
            
            this.$itemsContainer.find('.pili-sortable-item').each(function() {
                const $item = $(this);
                const key = $item.data('item-key');
                const itemData = {};

                $item.find('input, select, textarea').each(function() {
                    const $field = $(this);
                    const name = $field.attr('name');
                    if (name) {
                        itemData[name] = $field.val();
                    }
                });

                data[key] = itemData;
            });
            
            return data;
        }
        

    }

    /**
     * 初始化所有可排序字段
     */
    function initSortableFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-sortable-field').each(function() {
            const $container = $(this);
            if (!$container.data('pili-sortable-initialized')) {
                new XunSortable($container);
                $container.data('pili-sortable-initialized', true);
            }
        });
    }

    PILI.registerBoot('sortable', initSortableFields);

    $(document).ready(function() {
        initSortableFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        initSortableFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        initSortableFields($container && $container.length ? $container : undefined);
    });

})(jQuery);
