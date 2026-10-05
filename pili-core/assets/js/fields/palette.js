/**
 * Xun Framework Palette 字段 JavaScript
 * 
 * 这个文件包含了调色板字段的所有交互逻辑，包括颜色选择、
 * 搜索过滤、自定义颜色、多选支持等功能。
 * 
 * @package Xun Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    /**
     * Palette 字段类
     */
    class XunPalette {
        
        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$paletteContainer = $container.find('.pili-palette-container');
            this.$searchInput = $container.find('.pili-palette-search');
            this.$addCustomBtn = $container.find('.pili-palette-add-custom');
            this.$inputs = $container.find('.pili-palette-input');
            this.$preview = $container.find('.pili-palette-selected-colors');
            this.fieldId = $container.data('field-id');

            if (this.$addCustomBtn.length === 0) {
                setTimeout(() => {
                    this.$addCustomBtn = $container.find('.pili-palette-add-custom');
                    if (this.$addCustomBtn.length > 0) {
                        this.bindCustomButtonEvent();
                    }
                }, 100);
            }
            
            this.config = this.$paletteContainer.data('palette-config') || {};
            this.isMultiple = this.config.multiple || false;
            this.allowEmpty = this.config.allowEmpty !== false;
            
            this.selectedColors = this.getInitialValues();
            
            this.init();
        }
        
        /**
         * 初始化调色板
         */
        init() {
            this.initEvents();
            this.initSearch();
            this.updatePreview();
            this.addDynamicStyles();
        }
        
        /**
         * 获取初始值
         */
        getInitialValues() {
            const values = [];
            this.$inputs.each(function() {
                const value = $(this).val();
                if (value) {
                    values.push(value);
                }
            });
            return values;
        }
        
        /**
         * 初始化事件监听
         */
        initEvents() {
            const self = this;

            this.$container.on('click', '.pili-palette-item', function(e) {
                e.preventDefault();
                self.handleColorSelect($(this));
            });

            this.bindCustomButtonEvent();

            this.$container.on('click', '.pili-remove-color', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const color = $(this).closest('.pili-palette-item').data('color');
                self.removeColor(color);
            });
        }

        /**
         * 绑定自定义按钮事件
         */
        bindCustomButtonEvent() {
            const self = this;

            this.$container.off('click.custom-color').on('click.custom-color', '.pili-palette-add-custom', function(e) {
                e.preventDefault();
                self.showCustomColorPicker();
            });
        }
        
        /**
         * 初始化搜索功能
         */
        initSearch() {
            if (this.$searchInput.length === 0) return;

            const self = this;
            let searchTimeout;

            this.$searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    self.filterColors($(this).val());
                }, 300);
            });
        }


        
        /**
         * 处理颜色选择
         * 
         * @param {jQuery} $item 颜色项目元素
         */
        handleColorSelect($item) {
            const color = $item.data('color');
            const isSelected = $item.hasClass('pili-selected');
            
            if (this.isMultiple) {
                if (isSelected) {
                    this.removeColor(color);
                } else {
                    this.addColor(color);
                }
            } else {
                if (isSelected && this.allowEmpty) {
                    this.removeColor(color);
                } else {
                    this.setColor(color);
                }
            }
        }
        
        /**
         * 添加颜色
         * 
         * @param {string} color 颜色值
         */
        addColor(color) {
            if (this.isMultiple) {
                if (!this.selectedColors.includes(color)) {
                    this.selectedColors.push(color);
                    this.updateUI();
                    this.updateInputs();
                    this.updatePreview();
                    this.triggerChange();
                }
            } else {
                this.setColor(color);
            }
        }
        
        /**
         * 移除颜色
         * 
         * @param {string} color 颜色值
         */
        removeColor(color) {
            if (this.isMultiple) {
                const index = this.selectedColors.indexOf(color);
                if (index > -1) {
                    this.selectedColors.splice(index, 1);
                    this.updateUI();
                    this.updateInputs();
                    this.updatePreview();
                    this.triggerChange();
                }
            } else {
                this.selectedColors = [];
                this.updateUI();
                this.updateInputs();
                this.updatePreview();
                this.triggerChange();
            }
        }
        
        /**
         * 设置颜色（单选模式）
         * 
         * @param {string} color 颜色值
         */
        setColor(color) {
            this.selectedColors = [color];
            this.updateUI();
            this.updateInputs();
            this.updatePreview();
            this.triggerChange();
        }
        
        /**
         * 显示自定义颜色选择器
         */
        showCustomColorPicker() {
            if (typeof PILI.ColorPicker !== 'undefined') {
                this.showXunColorPicker();
            }
        }

        /**
         * 使用Xun Color字段的颜色选择器
         */
        showXunColorPicker() {
            const self = this;

            const tempId = 'temp-color-picker-' + Date.now();
            const inputId = tempId + '-input';

            const $tempContainer = $(`
                <div id="${tempId}" style="position: absolute; left: -9999px; opacity: 0;">
                    <div class="pili-color-field bg-white rounded-lg border border-gray-200 p-4 w-full" data-field-id="${tempId}" data-field-path="${tempId}">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-12 h-12 rounded-lg border-2 border-gray-300 shadow-sm cursor-pointer transition-transform duration-200 hover:scale-105" style="background: #ffffff;" id="${tempId}-preview"></div>
                            </div>
                            <div class="flex-1">
                                <input type="text" id="${inputId}" class="pili-color-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="#ffffff" name="temp_color" />
                            </div>
                        </div>
                        <script type="application/json" class="pili-color-config">
                            {
                                "fieldId": "${tempId}",
                                "inputId": "${inputId}",
                                "actualFieldId": "${tempId}",
                                "fieldPath": "${tempId}",
                                "value": "#ffffff",
                                "format": "hex",
                                "alpha": false,
                                "palette": [],
                                "messages": {
                                    "formatLabel": (PILI.bag('palette') && PILI.bag('palette').strings && PILI.bag('palette').strings.formatLabel) || "格式",
                                    "invalidColor": (PILI.bag('palette') && PILI.bag('palette').strings && PILI.bag('palette').strings.invalidColor) || "无效的颜色值",
                                    "colorCopied": (PILI.bag('palette') && PILI.bag('palette').strings && PILI.bag('palette').strings.colorCopied) || "颜色已复制"
                                }
                            }
                        </script>
                    </div>
                </div>
            `);

            $('body').append($tempContainer);

            if (PILI.ColorPicker && PILI.ColorPicker.initColorPickers) {
                PILI.ColorPicker.initColorPickers();

                $tempContainer.on('change input', '.pili-color-input', function() {
                    const color = $(this).val();
                    if (color && color !== '#ffffff') {
                        self.addCustomColor(color);
                        $tempContainer.remove();
                        $(document).off('click.temp-color-picker');
                    }
                });

                $tempContainer.on('change input', 'input[type="text"]', function() {
                    const color = $(this).val();
                    if (color && color !== '#ffffff') {
                        self.addCustomColor(color);
                        $tempContainer.remove();
                        $(document).off('click.temp-color-picker');
                    }
                });

                $(document).on('click.temp-color-confirm', '.pili-color-picker-confirm', function() {
                    let color = null;

                    const $modal = $('.pili-color-picker-modal');
                    if ($modal.length > 0) {
                        color = $modal.find('[data-current-color-value]').val() ||
                               $modal.find('.pili-color-input').val() ||
                               $modal.find('input[type="text"]').val();
                    }

                    if (!color) {
                        color = $tempContainer.find('input').val();
                    }

                    if (color && color !== '#ffffff') {
                        self.addCustomColor(color);
                        $tempContainer.remove();
                        $(document).off('click.temp-color-picker click.temp-color-confirm');
                    }
                });

                $(document).on('click.temp-color-picker', function(e) {
                    if (!$(e.target).closest('.pili-color-picker-modal, #' + tempId).length) {
                        setTimeout(() => {
                            if ($tempContainer.length) {
                                $tempContainer.remove();
                                $(document).off('click.temp-color-picker');
                            }
                        }, 100);
                    }
                });

                setTimeout(() => {
                    if (PILI.ColorPicker.openColorPicker) {
                        PILI.ColorPicker.openColorPicker(tempId);
                    } else {
                        $tempContainer.find('#' + tempId + '-preview').click();
                    }
                }, 200);
            } else {
                $tempContainer.remove();
            }
        }


        
        /**
         * 添加自定义颜色
         *
         * @param {string} color 颜色值
         */
        addCustomColor(color) {
            if (!color) return;

            const existingItem = this.$container.find(`.pili-palette-item[data-color="${color}"]`);
            if (existingItem.length > 0) {
                this.handleColorSelect(existingItem);
                return;
            }

            const $newItem = this.createCustomColorItem(color);

            const $firstGroup = this.$container.find('.pili-palette-group').first();
            if ($firstGroup.length > 0) {
                const $grid = $firstGroup.find('.grid');
                $grid.append($newItem);
            }

            this.addColor(color);
        }
        
        /**
         * 创建自定义颜色项目
         *
         * @param {string} color 颜色值
         * @returns {jQuery} 颜色项目元素
         */
        createCustomColorItem(color) {
            const colorSize = this.config.colorSize || 'medium';
            const sizeClasses = {
                'small': 'w-8 h-8',
                'medium': 'w-12 h-12',
                'large': 'w-16 h-16'
            };

            const $item = $(`
                <div class="pili-palette-item relative cursor-pointer group transition-all duration-200" data-color="${color}" data-label="${color}">
                    <div class="relative">
                        <div class="${sizeClasses[colorSize]} rounded-lg shadow-sm border border-gray-200 group-hover:border-gray-400 group-hover:shadow-md transition-all duration-200" style="background-color: ${color};"></div>
                        <button type="button" class="pili-remove-color absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-10">
                            ${color}
                        </div>
                    </div>
                </div>
            `);

            return $item;
        }
        
        /**
         * 过滤颜色
         * 
         * @param {string} searchTerm 搜索词
         */
        filterColors(searchTerm) {
            const term = searchTerm.toLowerCase().trim();
            
            this.$container.find('.pili-palette-item').each(function() {
                const $item = $(this);
                const color = $item.data('color').toLowerCase();
                const label = $item.data('label').toLowerCase();
                
                const matches = !term || color.includes(term) || label.includes(term);
                $item.toggle(matches);
            });
            
            this.updateEmptyState(term);
        }
        
        /**
         * 更新空状态显示
         * 
         * @param {string} searchTerm 搜索词
         */
        updateEmptyState(searchTerm) {
            const hasVisibleItems = this.$container.find('.pili-palette-item:visible').length > 0;
            
            if (!hasVisibleItems && searchTerm) {
                if (this.$container.find('.pili-search-empty').length === 0) {
                    const $emptyState = $(`
                        <div class="pili-search-empty text-center py-8 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <p class="mt-2 text-sm">${(PILI.bag('palette') && PILI.bag('palette').strings && PILI.bag('palette').strings.noResults) || '未找到匹配的颜色'}</p>
                        </div>
                    `);
                    this.$paletteContainer.append($emptyState);
                }
            } else {
                this.$container.find('.pili-search-empty').remove();
            }
        }
        
        /**
         * 更新UI显示
         */
        updateUI() {
            const self = this;

            this.$container.find('.pili-palette-item').each(function() {
                const $item = $(this);
                const color = $item.data('color');
                const isSelected = self.selectedColors.includes(color);

                $item.toggleClass('pili-selected', isSelected);

                const $colorBlock = $item.find('.rounded-lg').first();

                if (isSelected) {
                    $colorBlock.removeClass('border border-gray-200 group-hover:border-gray-400')
                              .addClass('border-2 border-blue-500 shadow-md');
                } else {
                    $colorBlock.removeClass('border-2 border-blue-500 shadow-md')
                              .addClass('border border-gray-200 group-hover:border-gray-400');
                }

                const $listContainer = $item.find('.flex.items-center');
                if ($listContainer.length > 0) {
                    if (isSelected) {
                        $listContainer.removeClass('border-gray-200 hover:border-gray-300 border')
                                    .addClass('border-blue-500 bg-blue-50 border-2');

                        const $listColorBlock = $listContainer.find('.rounded-lg').first();
                        $listColorBlock.removeClass('border border-gray-200')
                                      .addClass('border-2 border-blue-500');
                    } else {
                        $listContainer.removeClass('border-blue-500 bg-blue-50 border-2')
                                    .addClass('border-gray-200 hover:border-gray-300 border');

                        const $listColorBlock = $listContainer.find('.rounded-lg').first();
                        $listColorBlock.removeClass('border-2 border-blue-500')
                                      .addClass('border border-gray-200');
                    }
                }
            });
        }
        
        /**
         * 更新隐藏输入字段
         */
        updateInputs() {
            this.$inputs.remove();
            
            const fieldName = this.$inputs.first().attr('name');
            let inputsHtml = '';
            
            if (this.isMultiple) {
                const baseName = fieldName.replace('[]', '');
                if (this.selectedColors.length > 0) {
                    this.selectedColors.forEach(color => {
                        inputsHtml += `<input type="hidden" name="${baseName}[]" value="${color}" class="pili-palette-input" />`;
                    });
                } else {
                    inputsHtml += `<input type="hidden" name="${baseName}[]" value="" class="pili-palette-input" />`;
                }
            } else {
                const value = this.selectedColors.length > 0 ? this.selectedColors[0] : '';
                inputsHtml += `<input type="hidden" name="${fieldName}" value="${value}" class="pili-palette-input" />`;
            }
            
            this.$container.append(inputsHtml);
            this.$inputs = this.$container.find('.pili-palette-input');
        }
        
        /**
         * 更新预览显示
         */
        updatePreview() {
            if (this.$preview.length === 0) return;
            
            this.$preview.empty();
            
            if (this.selectedColors.length === 0) {
                this.$preview.append('<span class="text-gray-500 text-sm">' + ((PILI.bag('palette') && PILI.bag('palette').strings && PILI.bag('palette').strings.noColorSelected) || '未选择任何颜色') + '</span>');
                return;
            }
            
            this.selectedColors.forEach(color => {
                const $previewItem = $(`
                    <div class="flex items-center space-x-2 bg-white rounded-lg border border-gray-200 px-3 py-2">
                        <div class="w-4 h-4 rounded border border-gray-300" style="background-color: ${color};"></div>
                        <span class="text-sm font-mono text-gray-700">${color}</span>
                    </div>
                `);
                this.$preview.append($previewItem);
            });
        }
        
        /**
         * 触发变更事件
         */
        triggerChange() {
            this.$container.trigger('xun:palette:change', {
                colors: [...this.selectedColors],
                isMultiple: this.isMultiple
            });
        }
        
        /**
         * 获取选中的颜色
         * 
         * @returns {Array|string} 选中的颜色
         */
        getSelectedColors() {
            return this.isMultiple ? [...this.selectedColors] : (this.selectedColors[0] || '');
        }
        
        /**
         * 设置选中的颜色
         * 
         * @param {Array|string} colors 要设置的颜色
         */
        setSelectedColors(colors) {
            if (this.isMultiple) {
                this.selectedColors = Array.isArray(colors) ? [...colors] : [colors];
            } else {
                this.selectedColors = Array.isArray(colors) ? [colors[0]] : [colors];
            }
            
            this.updateUI();
            this.updateInputs();
            this.updatePreview();
            this.triggerChange();
        }
        
        /**
         * 添加动态样式
         */
        addDynamicStyles() {
            if ($('#pili-palette-styles').length > 0) return;

            const styles = `
                <style id="pili-palette-styles">
                    .pili-palette-field {
                        user-select: none;
                    }

                    /* 构建产物可能无 pl-10：给搜索框留出左侧放大镜空间 */
                    .pili-palette-search {
                        padding-left: 2.5rem !important;
                    }

                    .pili-palette-item {
                        position: relative;
                    }

                    .pili-palette-animate .pili-palette-item {
                        transition: all 0.2s ease;
                    }

                    .pili-palette-item:hover {
                        transform: translateY(-1px);
                    }

                    .pili-palette-item.pili-selected {
                        transform: translateY(-2px);
                    }

                    .pili-palette-compact .pili-palette-item {
                        margin: 1px;
                    }

                    .pili-remove-color {
                        z-index: 10;
                    }

                    .grid-cols-3 {
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                    }

                    .grid-cols-4 {
                        grid-template-columns: repeat(4, minmax(0, 1fr));
                    }

                    .grid-cols-5 {
                        grid-template-columns: repeat(5, minmax(0, 1fr));
                    }

                    .grid-cols-6 {
                        grid-template-columns: repeat(6, minmax(0, 1fr));
                    }

                    .grid-cols-7 {
                        grid-template-columns: repeat(7, minmax(0, 1fr));
                    }

                    .grid-cols-8 {
                        grid-template-columns: repeat(8, minmax(0, 1fr));
                    }

                    .grid-cols-10 {
                        grid-template-columns: repeat(10, minmax(0, 1fr));
                    }

                    .grid-cols-12 {
                        grid-template-columns: repeat(12, minmax(0, 1fr));
                    }

                    @media (max-width: 768px) {
                        .pili-palette-toolbar {
                            flex-direction: column;
                            align-items: stretch;
                        }

                        .pili-palette-search {
                            margin-bottom: 0.75rem;
                        }

                        .grid-cols-5,
                        .grid-cols-6,
                        .grid-cols-7,
                        .grid-cols-8 {
                            grid-template-columns: repeat(4, minmax(0, 1fr));
                        }

                        .grid-cols-10,
                        .grid-cols-12 {
                            grid-template-columns: repeat(6, minmax(0, 1fr));
                        }
                    }

                    @media (max-width: 480px) {
                        .grid-cols-3,
                        .grid-cols-4,
                        .grid-cols-5,
                        .grid-cols-6,
                        .grid-cols-7,
                        .grid-cols-8 {
                            grid-template-columns: repeat(3, minmax(0, 1fr));
                        }

                        .grid-cols-10,
                        .grid-cols-12 {
                            grid-template-columns: repeat(4, minmax(0, 1fr));
                        }
                    }

                    @media (hover: none) and (pointer: coarse) {
                        .pili-palette-item:hover {
                            transform: none;
                        }

                        .pili-remove-color {
                            opacity: 1;
                        }
                    }
                </style>
            `;

            $('head').append(styles);
        }
    }

    /**
     * 初始化所有调色板字段
     */
    function initPaletteFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-palette-field').each(function() {
            const $container = $(this);
            if ($container.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$container.data('pili-palette-initialized')) {
                new XunPalette($container);
                $container.data('pili-palette-initialized', true);
            }
        });
    }

    PILI.registerBoot('palette', initPaletteFields);

    $(document).ready(function() {
        initPaletteFields();
    });

    $(document).on('xun:field:loaded', function() {
        initPaletteFields();
    });

    $(document).on('xun:field:added', function(e, $container) {
        initPaletteFields($container && $container.length ? $container : undefined);
    });

})(jQuery);
