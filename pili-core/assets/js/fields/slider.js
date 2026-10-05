/**
 * PILI Framework Slider 字段 JavaScript
 * 
 * 这个文件包含了滑块字段的所有交互逻辑，包括拖拽滑动、
 * 键盘快捷键、实时预览、数值同步等功能。
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    /**
     * Slider 字段类
     */
    class XunSlider {
        
        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$track = $container.find('.pili-slider-track');
            this.$progress = $container.find('.pili-slider-progress');
            this.$handles = $container.find('.pili-slider-handle');
            this.$inputs = $container.find('.pili-slider-input');
            this.$tooltips = $container.find('.pili-slider-tooltip');
            this.$preview = $container.find('.pili-slider-preview');
            this.fieldId = $container.data('field-id');
            
            this.config = this.$track.data('slider-config') || {};
            this.isRange = this.config.range || false;
            this.isDragging = false;
            this.activeHandle = null;
            
            this.values = this.isRange ? 
                { min: this.config.min, max: this.config.max } : 
                this.config.min;
            
            this.init();
        }
        
        /**
         * 初始化滑块
         */
        init() {
            this.initValues();
            this.initEvents();
            this.initKeyboard();
            this.addDynamicStyles();
            this.updateUI();
            this.updateTooltips();
            this.updatePreview();
        }
        
        /**
         * 初始化数值
         */
        initValues() {
            if (this.isRange) {
                const minInput = this.$inputs.filter('.pili-slider-input-min');
                const maxInput = this.$inputs.filter('.pili-slider-input-max');
                
                this.values.min = parseFloat(minInput.val()) || this.config.min;
                this.values.max = parseFloat(maxInput.val()) || this.config.max;
            } else {
                this.values = parseFloat(this.$inputs.val()) || this.config.min;
            }
        }
        
        /**
         * 初始化事件监听
         */
        initEvents() {
            const self = this;
            
            this.$track.on('mousedown touchstart', function(e) {
                if ($(e.target).hasClass('pili-slider-handle')) return;
                self.handleTrackClick(e);
            });
            
            this.$handles.on('mousedown touchstart', function(e) {
                e.preventDefault();
                self.startDrag(e, $(this));
            });
            
            this.$inputs.on('input change', function() {
                self.handleInputChange($(this));
            });
            
            $(document).on('mousemove touchmove', function(e) {
                if (self.isDragging) {
                    self.handleDrag(e);
                }
            });
            
            $(document).on('mouseup touchend', function() {
                if (self.isDragging) {
                    self.stopDrag();
                }
            });
            
            this.$handles.on('mouseenter', function() {
                self.showTooltip($(this));
            });
            
            this.$handles.on('mouseleave', function() {
                if (!self.isDragging) {
                    self.hideTooltip($(this));
                }
            });
        }
        
        /**
         * 初始化键盘支持
         */
        initKeyboard() {
            if (!this.config.keyboard) return;
            
            const self = this;
            
            this.$handles.on('keydown', function(e) {
                const $handle = $(this);
                const isMin = $handle.hasClass('pili-slider-handle-min');

                let step = self.config.step;
                let currentValue;

                if (self.isRange) {
                    currentValue = isMin ? self.values.min : self.values.max;
                } else {
                    currentValue = self.values;
                }
                
                switch (e.key) {
                    case 'ArrowLeft':
                    case 'ArrowDown':
                        e.preventDefault();
                        self.adjustValue($handle, currentValue - step);
                        break;
                    case 'ArrowRight':
                    case 'ArrowUp':
                        e.preventDefault();
                        self.adjustValue($handle, currentValue + step);
                        break;
                    case 'PageDown':
                        e.preventDefault();
                        self.adjustValue($handle, currentValue - step * 10);
                        break;
                    case 'PageUp':
                        e.preventDefault();
                        self.adjustValue($handle, currentValue + step * 10);
                        break;
                    case 'Home':
                        e.preventDefault();
                        self.adjustValue($handle, self.config.min);
                        break;
                    case 'End':
                        e.preventDefault();
                        self.adjustValue($handle, self.config.max);
                        break;
                }
            });
        }
        
        /**
         * 处理轨道点击
         *
         * @param {Event} e 事件对象
         */
        handleTrackClick(e) {
            e.preventDefault();

            const rect = this.$track[0].getBoundingClientRect();
            if (!rect || rect.width <= 0) {
                return;
            }
            const clientX = e.clientX || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0] ? e.originalEvent.touches[0].clientX : 0);
            const clickX = clientX - rect.left;
            const percentage = Math.max(0, Math.min(1, clickX / rect.width));
            const value = this.config.min + (percentage * (this.config.max - this.config.min));

            if (this.isRange) {
                const minDistance = Math.abs(value - this.values.min);
                const maxDistance = Math.abs(value - this.values.max);

                if (minDistance < maxDistance) {
                    this.adjustValue(this.$handles.filter('.pili-slider-handle-min'), value);
                } else {
                    this.adjustValue(this.$handles.filter('.pili-slider-handle-max'), value);
                }
            } else {
                this.adjustValue(this.$handles, value);
            }
        }
        
        /**
         * 开始拖拽
         *
         * @param {Event} e 事件对象
         * @param {jQuery} $handle 手柄元素
         */
        startDrag(e, $handle) {
            e.preventDefault();

            this.isDragging = true;
            this.activeHandle = $handle;
            
            $handle.addClass('pili-dragging');
            $('body').addClass('pili-slider-dragging');
            
            this.showTooltip($handle);
            
            this.$container.trigger('xun:slider:dragstart', {
                handle: $handle,
                value: this.getCurrentValue($handle)
            });
        }
        
        /**
         * 处理拖拽
         *
         * @param {Event} e 事件对象
         */
        handleDrag(e) {
            if (!this.isDragging || !this.activeHandle) return;

            e.preventDefault();

            const rect = this.$track[0].getBoundingClientRect();
            if (!rect || rect.width <= 0) {
                return;
            }
            const clientX = e.clientX || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0] ? e.originalEvent.touches[0].clientX : 0);
            const dragX = clientX - rect.left;
            const percentage = Math.max(0, Math.min(1, dragX / rect.width));
            const value = this.config.min + (percentage * (this.config.max - this.config.min));

            this.adjustValue(this.activeHandle, value);
        }
        
        /**
         * 停止拖拽
         */
        stopDrag() {
            if (!this.isDragging) return;
            
            this.isDragging = false;
            
            if (this.activeHandle) {
                this.activeHandle.removeClass('pili-dragging');
                this.hideTooltip(this.activeHandle);
                this.activeHandle = null;
            }
            
            $('body').removeClass('pili-slider-dragging');
            
            this.$container.trigger('xun:slider:dragend', {
                value: this.isRange ? this.values : this.values
            });
        }
        
        /**
         * 调整数值
         * 
         * @param {jQuery} $handle 手柄元素
         * @param {number} value 新数值
         */
        adjustValue($handle, value) {
            value = Math.max(this.config.min, Math.min(this.config.max, value));
            
            if (this.config.step > 0) {
                value = Math.round(value / this.config.step) * this.config.step;
            }
            
            if (this.config.precision > 0) {
                value = parseFloat(value.toFixed(this.config.precision));
            }
            
            if (this.isRange) {
                const isMin = $handle.hasClass('pili-slider-handle-min');
                
                if (isMin) {
                    value = Math.min(value, this.values.max);
                    this.values.min = value;
                } else {
                    value = Math.max(value, this.values.min);
                    this.values.max = value;
                }
            } else {
                this.values = value;
            }
            
            this.updateUI();
            this.updateInputs();
            this.updateTooltips();
            this.updatePreview();
            
            this.$container.trigger('xun:slider:change', {
                value: this.isRange ? this.values : this.values,
                handle: $handle
            });
        }
        
        /**
         * 处理输入框变化
         * 
         * @param {jQuery} $input 输入框元素
         */
        handleInputChange($input) {
            const value = parseFloat($input.val());
            
            if (isNaN(value)) return;
            
            if (this.isRange) {
                if ($input.hasClass('pili-slider-input-min')) {
                    this.adjustValue(this.$handles.filter('.pili-slider-handle-min'), value);
                } else if ($input.hasClass('pili-slider-input-max')) {
                    this.adjustValue(this.$handles.filter('.pili-slider-handle-max'), value);
                }
            } else {
                this.adjustValue(this.$handles, value);
            }
        }
        
        /**
         * 更新UI显示
         */
        updateUI() {
            const range = this.config.max - this.config.min;
            
            if (this.isRange) {
                const minPercent = ((this.values.min - this.config.min) / range) * 100;
                const maxPercent = ((this.values.max - this.config.min) / range) * 100;
                
                this.$progress.css({
                    left: minPercent + '%',
                    width: (maxPercent - minPercent) + '%'
                });
                
                this.$handles.filter('.pili-slider-handle-min').css('left', minPercent + '%');
                this.$handles.filter('.pili-slider-handle-max').css('left', maxPercent + '%');
                
                this.$handles.filter('.pili-slider-handle-min').attr('aria-valuenow', this.values.min);
                this.$handles.filter('.pili-slider-handle-max').attr('aria-valuenow', this.values.max);

                this.updateTooltipPositions(minPercent, maxPercent);
            } else {
                const percent = ((this.values - this.config.min) / range) * 100;
                
                this.$progress.css('width', percent + '%');
                
                this.$handles.css('left', percent + '%');
                
                this.$handles.attr('aria-valuenow', this.values);

                this.updateTooltipPositions(percent);
            }
        }

        /**
         * 根据当前百分比更新工具提示位置
         * @param {number} percent 单值百分比
         * @param {number} maxPercent 范围的最大百分比（可选）
         */
        updateTooltipPositions(percent, maxPercent = null) {
            if (!this.config.tooltip) return;

            if (this.isRange) {
                const $minTip = this.$tooltips.filter('.pili-slider-tooltip-min');
                const $maxTip = this.$tooltips.filter('.pili-slider-tooltip-max');

                if ($minTip.length) {
                    $minTip.css({ left: percent + '%', right: 'auto' });
                }
                if ($maxTip.length && maxPercent !== null) {
                    $maxTip.css({ left: maxPercent + '%', right: 'auto' });
                }
            } else {
                if (this.$tooltips.length) {
                    this.$tooltips.css({ left: percent + '%', right: 'auto' });
                }
            }
        }
        
        /**
         * 更新输入框数值
         */
        updateInputs() {
            if (this.isRange) {
                this.$inputs.filter('.pili-slider-input-min').val(this.values.min);
                this.$inputs.filter('.pili-slider-input-max').val(this.values.max);
            } else {
                this.$inputs.val(this.values);
            }
        }
        
        /**
         * 更新工具提示
         */
        updateTooltips() {
            if (!this.config.tooltip) return;
            
            if (this.isRange) {
                this.$tooltips.filter('.pili-slider-tooltip-min').text(this.formatValue(this.values.min));
                this.$tooltips.filter('.pili-slider-tooltip-max').text(this.formatValue(this.values.max));
            } else {
                this.$tooltips.text(this.formatValue(this.values));
            }
        }
        
        /**
         * 显示工具提示
         * 
         * @param {jQuery} $handle 手柄元素
         */
        showTooltip($handle) {
            if (!this.config.tooltip) return;
            
            let $tooltip;
            if (this.isRange) {
                if ($handle.hasClass('pili-slider-handle-min')) {
                    $tooltip = this.$tooltips.filter('.pili-slider-tooltip-min');
                } else {
                    $tooltip = this.$tooltips.filter('.pili-slider-tooltip-max');
                }
            } else {
                $tooltip = this.$tooltips;
            }
            
            $tooltip.addClass('opacity-100').removeClass('opacity-0');
        }
        
        /**
         * 隐藏工具提示
         * 
         * @param {jQuery} $handle 手柄元素
         */
        hideTooltip($handle) {
            if (!this.config.tooltip) return;
            
            let $tooltip;
            if (this.isRange) {
                if ($handle.hasClass('pili-slider-handle-min')) {
                    $tooltip = this.$tooltips.filter('.pili-slider-tooltip-min');
                } else {
                    $tooltip = this.$tooltips.filter('.pili-slider-tooltip-max');
                }
            } else {
                $tooltip = this.$tooltips;
            }
            
            $tooltip.addClass('opacity-0').removeClass('opacity-100');
        }
        
        /**
         * 更新实时预览
         */
        updatePreview() {
            if (this.$preview.length === 0) return;
            
            let previewText;
            if (this.isRange) {
                previewText = `${this.formatValue(this.values.min)} - ${this.formatValue(this.values.max)}`;
            } else {
                previewText = this.formatValue(this.values);
            }
            
            this.$preview.text(previewText);
        }
        
        /**
         * 格式化数值显示
         * 
         * @param {number} value 数值
         * @returns {string} 格式化后的字符串
         */
        formatValue(value) {
            if (typeof this.config.formatValue === 'function') {
                return this.config.formatValue(value);
            }
            
            let formatted = value.toString();
            
            if (this.config.precision > 0) {
                formatted = value.toFixed(this.config.precision);
            }
            
            return formatted;
        }
        
        /**
         * 获取当前手柄的值
         * 
         * @param {jQuery} $handle 手柄元素
         * @returns {number} 当前值
         */
        getCurrentValue($handle) {
            if (this.isRange) {
                return $handle.hasClass('pili-slider-handle-min') ? this.values.min : this.values.max;
            }
            return this.values;
        }
        
        /**
         * 获取当前所有值
         * 
         * @returns {number|Object} 当前值
         */
        getValue() {
            return this.isRange ? { ...this.values } : this.values;
        }
        
        /**
         * 设置值
         *
         * @param {number|Object} value 新值
         */
        setValue(value) {
            if (this.isRange) {
                if (typeof value === 'object' && value !== null) {
                    if (value.min !== undefined) {
                        this.adjustValue(this.$handles.filter('.pili-slider-handle-min'), value.min);
                    }
                    if (value.max !== undefined) {
                        this.adjustValue(this.$handles.filter('.pili-slider-handle-max'), value.max);
                    }
                }
            } else {
                if (typeof value === 'number') {
                    this.adjustValue(this.$handles, value);
                }
            }
        }

        /**
         * 添加动态样式
         */
        addDynamicStyles() {
            if ($('#pili-slider-styles').length > 0) return;

            const styles = `
                <style id="pili-slider-styles">
                    .pili-slider-field {
                        user-select: none;
                    }

                    .pili-slider-track {
                        position: relative;
                    }

                    .pili-slider-handle {
                        transform: translateX(-50%);
                        z-index: 10;
                    }

                    .pili-slider-handle:hover {
                        transform: translateX(-50%) scale(1.1);
                    }

                    .pili-slider-handle:focus {
                        outline: none;
                        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
                    }

                    .pili-slider-handle.pili-dragging {
                        transform: translateX(-50%) scale(1.2);
                        cursor: grabbing !important;
                    }

                    .pili-slider-tooltip {
                        transform: translateX(-50%);
                        white-space: nowrap;
                        z-index: 20;
                    }

                    .pili-slider-progress {
                        pointer-events: none;
                    }

                    .pili-slider-animate .pili-slider-handle {
                        transition: transform 0.2s ease;
                    }

                    .pili-slider-animate .pili-slider-progress {
                        transition: all 0.2s ease;
                    }

                    .pili-slider-dragging {
                        cursor: grabbing !important;
                    }

                    .pili-slider-dragging .pili-slider-progress {
                        transition: none !important;
                    }
                    .pili-slider-dragging .pili-slider-handle {
                        transition: none !important;
                    }

                    @media (max-width: 768px) {
                        .pili-slider-handle {
                            width: 24px;
                            height: 24px;
                        }

                        .pili-slider-track {
                            height: 8px;
                        }
                    }

                    @media (hover: none) and (pointer: coarse) {
                        .pili-slider-handle:hover {
                            transform: translateX(-50%);
                        }
                    }
                </style>
            `;

            $('head').append(styles);
        }
    }

    /**
     * 初始化滑块字段（与 repeater 动态项对齐：必须响应 pili:field:added）
     *
     * @param {jQuery|undefined} $root 可选；传入时只初始化该根节点内的 .pili-slider-field（如 repeater 新行）
     */
    function initSliderFields($root) {
        const $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-slider-field').each(function() {
            const $container = $(this);
            if (!$root && $container.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$container.data('pili-slider-initialized')) {
                new XunSlider($container);
                $container.data('pili-slider-initialized', true);
            }
        });
    }

    PILI.registerBoot('slider', initSliderFields);

    $(document).ready(function() {
        initSliderFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        initSliderFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        if ($container && $container.length) {
            initSliderFields($container);
        } else {
            initSliderFields();
        }
    });

})(jQuery);
