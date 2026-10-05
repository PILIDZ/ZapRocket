/**
 * PILI Framework - Number Field JavaScript
 * 
 * 为数字输入字段提供现代化的交互功能，包括：
 * - 实时验证和错误提示
 * - 键盘快捷键支持
 * - 增减按钮功能
 * - 数字格式化
 * - 范围指示器
 * - 无障碍访问支持
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    function numberBag() {
        return (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('number')) || {};
    }

    function numberI18n(key, fallback) {
        var bag = numberBag();
        if (bag.i18n && bag.i18n[key]) {
            return bag.i18n[key];
        }
        return fallback || key;
    }

    function numberSettings() {
        var bag = numberBag();
        return bag.settings || {
            thousand_separator: ',',
            decimal_separator: '.',
            currency_symbol: '$',
            percentage_symbol: '%'
        };
    }

    /**
     * 数字字段类
     */
    class XunNumberField {

        constructor($container) {
            this.$container = $container;
            this.$input = $container.find('.pili-number-input');
            this.$errorMessage = $container.find('.pili-number-error-message');
            this.$rangeProgress = $container.find('.pili-number-range-progress');
            this.$incrementBtn = $container.find('.pili-number-increment');
            this.$decrementBtn = $container.find('.pili-number-decrement');
            this.$sliderBridge = $container.find('.pili-number-slider-bridge');
            this.$slider = this.$sliderBridge.find('.pili-slider-field');
            this.$sliderTrack = this.$slider.find('.pili-slider-track');
            this.$sliderFill = this.$slider.find('.pili-slider-progress');
            this.$sliderHandle = this.$slider.find('.pili-slider-handle');
            this.$sliderValue = this.$slider.find('.pili-slider-tooltip');
            this.config = {
                fieldId: $container.data('field-id'),
                mode: this.$input.data('mode') || 'basic',
                precision: parseInt(this.$input.data('precision')) || 0,
                thousandSep: this.$input.data('thousand-sep') === 'true',
                formatDisplay: this.$input.data('format-display') === 'true',
                validation: this.$input.data('validation') === 'true',
                keyboardNav: this.$input.data('keyboard-nav') === 'true',
                autoSelect: this.$input.data('auto-select') === 'true',
                min: this.$input.data('min'),
                max: this.$input.data('max'),
                step: parseFloat(this.$input.data('step')) || 1
            };
            this.lastValidValue = this.$input.val();
            this.isFormatted = false;
            if (this.$input.length === 0) return;
            this.init();
        }

        init() {
            this.bindEvents();
            this.updateRangeIndicator();
            this.validateValue();
        }

        bindEvents() {
            this.$input
                .on('input', this.handleInput.bind(this))
                .on('change', this.handleChange.bind(this))
                .on('blur', this.handleBlur.bind(this))
                .on('focus', this.handleFocus.bind(this))
                .on('keydown', this.handleKeydown.bind(this))
                .on('wheel', this.handleWheel.bind(this));
            this.$incrementBtn.on('click', this.increment.bind(this));
            this.$decrementBtn.on('click', this.decrement.bind(this));
            this.$incrementBtn.add(this.$decrementBtn).on('mousedown', function(e) {
                e.preventDefault();
            });
            if (this.$slider.length > 0) {
                this.bindSliderEvents();
            }
        }

        handleInput(e) {
            if (this.config.validation) {
                this.validateValue();
            }
            this.updateRangeIndicator();
        }

        handleChange(e) {
            this.formatValue();
            this.validateValue();
            this.updateRangeIndicator();
        }

        handleBlur(e) {
            if (this.config.formatDisplay && !this.isFormatted) {
                this.formatValue();
            }
            this.validateValue();
        }

        handleFocus(e) {
            if (this.config.autoSelect) {
                setTimeout(() => {
                    this.$input[0].select();
                }, 10);
            }
            if (this.isFormatted) {
                this.unformatValue();
            }
        }

        handleKeydown(e) {
            if (!this.config.keyboardNav) return;
            const currentValue = parseFloat(this.$input.val()) || 0;
            let newValue = currentValue;
            switch (e.key) {
                case 'ArrowUp':
                    e.preventDefault();
                    newValue = this.adjustValue(currentValue, this.config.step);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    newValue = this.adjustValue(currentValue, -this.config.step);
                    break;
                case 'PageUp':
                    e.preventDefault();
                    newValue = this.adjustValue(currentValue, this.config.step * 10);
                    break;
                case 'PageDown':
                    e.preventDefault();
                    newValue = this.adjustValue(currentValue, -this.config.step * 10);
                    break;
                case 'Home':
                    if (this.config.min !== undefined && this.config.min !== '') {
                        e.preventDefault();
                        newValue = parseFloat(this.config.min);
                    }
                    break;
                case 'End':
                    if (this.config.max !== undefined && this.config.max !== '') {
                        e.preventDefault();
                        newValue = parseFloat(this.config.max);
                    }
                    break;
                default:
                    return;
            }
            if (newValue !== currentValue) {
                this.$input.val(newValue).trigger('input');
            }
        }

        handleWheel(e) {
            if (!this.$input.is(':focus')) return;
            e.preventDefault();
            const currentValue = parseFloat(this.$input.val()) || 0;
            const delta = e.originalEvent.deltaY > 0 ? -this.config.step : this.config.step;
            const newValue = this.adjustValue(currentValue, delta);
            this.$input.val(newValue).trigger('input');
        }

        increment() {
            const currentValue = parseFloat(this.$input.val()) || 0;
            const newValue = this.adjustValue(currentValue, this.config.step);
            this.$input.val(newValue).trigger('input').focus();
        }

        decrement() {
            const currentValue = parseFloat(this.$input.val()) || 0;
            const newValue = this.adjustValue(currentValue, -this.config.step);
            this.$input.val(newValue).trigger('input').focus();
        }

        adjustValue(currentValue, delta) {
            let newValue = currentValue + delta;
            if (this.config.min !== undefined && this.config.min !== '') {
                newValue = Math.max(newValue, parseFloat(this.config.min));
            }
            if (this.config.max !== undefined && this.config.max !== '') {
                newValue = Math.min(newValue, parseFloat(this.config.max));
            }
            if (this.config.precision > 0) {
                newValue = parseFloat(newValue.toFixed(this.config.precision));
            } else {
                newValue = Math.round(newValue);
            }
            return newValue;
        }

        validateValue() {
            const value = this.$input.val();
            const errors = [];
            this.clearError();
            if (value === '') {
                if (this.$input.prop('required')) {
                    errors.push(numberI18n('required_field', '此字段为必填项'));
                }
            } else {
                const numericValue = this.parseNumber(value);
                if (isNaN(numericValue)) {
                    errors.push(numberI18n('invalid_number', '数字无效'));
                } else {
                    if (this.config.min !== undefined && this.config.min !== '' && numericValue < parseFloat(this.config.min)) {
                        errors.push(String(numberI18n('below_minimum', '不能小于 %s')).replace('%s', this.config.min));
                    }
                    if (this.config.max !== undefined && this.config.max !== '' && numericValue > parseFloat(this.config.max)) {
                        errors.push(String(numberI18n('above_maximum', '不能大于 %s')).replace('%s', this.config.max));
                    }
                    if (this.config.step && this.config.step !== 'any') {
                        const min = this.config.min !== undefined && this.config.min !== '' ? parseFloat(this.config.min) : 0;
                        const remainder = (numericValue - min) % this.config.step;
                        if (Math.abs(remainder) > 0.0001) {
                            errors.push(String(numberI18n('invalid_step', '步进必须为 %s')).replace('%s', this.config.step));
                        }
                    }
                    if (this.config.precision >= 0) {
                        const decimalPlaces = (value.split('.')[1] || '').length;
                        if (decimalPlaces > this.config.precision) {
                            errors.push(String(numberI18n('decimal_places', '最多 %d 位小数')).replace('%d', this.config.precision));
                        }
                    }
                }
            }
            if (errors.length > 0) {
                this.showError(errors[0]);
                return false;
            }
            this.lastValidValue = value;
            return true;
        }

        formatValue() {
            if (!this.config.formatDisplay) return;
            const value = this.$input.val();
            const numericValue = this.parseNumber(value);
            if (!isNaN(numericValue)) {
                let formatted = numericValue.toFixed(this.config.precision);
                if (this.config.thousandSep) {
                    formatted = this.addThousandSeparator(formatted);
                }
                switch (this.config.mode) {
                    case 'currency':
                        formatted = numberSettings().currency_symbol + formatted;
                        break;
                    case 'percentage':
                        formatted = formatted + numberSettings().percentage_symbol;
                        break;
                }
                this.$input.val(formatted);
                this.isFormatted = true;
            }
        }

        unformatValue() {
            if (!this.isFormatted) return;
            const value = this.$input.val();
            let unformatted = value;
            unformatted = unformatted.replace(/[$%,]/g, '');
            this.$input.val(unformatted);
            this.isFormatted = false;
        }

        parseNumber(value) {
            if (value === undefined || value === null) {
                return NaN;
            }
            if (typeof value === 'number') return value;
            const str = String(value);
            const cleaned = str.replace(/[$%,]/g, '');
            return parseFloat(cleaned);
        }

        addThousandSeparator(value) {
            const parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, numberSettings().thousand_separator);
            return parts.join(numberSettings().decimal_separator);
        }

        updateRangeIndicator() {
            if (this.$rangeProgress.length > 0) {
                const value = this.parseNumber(this.$input.val());
                if (isNaN(value)) {
                    this.$rangeProgress.css('width', '0%');
                    return;
                }
                const min = parseFloat(this.config.min) || 0;
                const max = parseFloat(this.config.max) || 100;
                const percentage = Math.max(0, Math.min(100, ((value - min) / (max - min)) * 100));
                this.$rangeProgress.css('width', percentage + '%');
            }
            if (this.$slider.length > 0) {
                this.updateSliderPosition();
                this.updateSliderValue();
            }
        }

        showError(message) {
            this.$errorMessage.text(message).removeClass('hidden');
            const $inputContainer = this.$container.find('.pili-number-input-container');
            $inputContainer.addClass('outline-red-500 focus-within:outline-red-500');
            $inputContainer.removeClass('outline-gray-300 focus-within:outline-indigo-600');
        }

        clearError() {
            this.$errorMessage.addClass('hidden');
            const $inputContainer = this.$container.find('.pili-number-input-container');
            $inputContainer.removeClass('outline-red-500 focus-within:outline-red-500');
            $inputContainer.addClass('outline-gray-300 focus-within:outline-indigo-600');
        }

        /**
         * 绑定滑块事件
         */
        bindSliderEvents() {
            let isDragging = false;
            this.$sliderHandle.on('mousedown touchstart', (e) => {
                e.preventDefault();
                isDragging = true;
                this.$slider.addClass('dragging');
                this.handleSliderDrag(e);
                $(document).on('mousemove.slider touchmove.slider', (e) => {
                    if (!isDragging) return;
                    this.handleSliderDrag(e);
                });
                $(document).on('mouseup.slider touchend.slider', () => {
                    isDragging = false;
                    this.$slider.removeClass('dragging');
                    $(document).off('.slider');
                });
            });
            this.$sliderTrack.on('click', (e) => {
                if (e.target === this.$sliderHandle[0]) return;
                this.handleSliderClick(e);
            });
            this.$sliderHandle.on('keydown', (e) => {
                this.handleSliderKeydown(e);
            });
        }

        /**
         * 处理滑块拖动
         */
        handleSliderDrag(e) {
            e.preventDefault();
            const currentX = e.type === 'mousedown' || e.type === 'mousemove' ? e.clientX : e.originalEvent.touches[0].clientX;
            const rect = this.$sliderTrack[0].getBoundingClientRect();
            const relativeX = currentX - rect.left;
            const percent = Math.max(0, Math.min(1, relativeX / rect.width));
            const range = this.config.max - this.config.min;
            const newValue = this.config.min + (percent * range);
            this.updateValueFromSlider(this.constrainValue(newValue));
        }

        /**
         * 处理滑块点击
         */
        handleSliderClick(e) {
            const rect = this.$sliderTrack[0].getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const percent = clickX / rect.width;
            const range = this.config.max - this.config.min;
            const newValue = this.config.min + (percent * range);
            this.updateValueFromSlider(this.constrainValue(newValue));
        }

        /**
         * 处理滑块键盘导航
         */
        handleSliderKeydown(e) {
            let newValue = parseFloat(this.$input.val()) || 0;
            const step = this.config.step;
            switch (e.key) {
                case 'ArrowLeft':
                case 'ArrowDown':
                    e.preventDefault();
                    newValue -= step;
                    break;
                case 'ArrowRight':
                case 'ArrowUp':
                    e.preventDefault();
                    newValue += step;
                    break;
                case 'Home':
                    e.preventDefault();
                    newValue = this.config.min;
                    break;
                case 'End':
                    e.preventDefault();
                    newValue = this.config.max;
                    break;
                default:
                    return;
            }
            this.updateValueFromSlider(this.constrainValue(newValue));
        }

        /**
         * 约束数值在有效范围内
         */
        constrainValue(value) {
            let constrainedValue = value;
            if (this.config.min !== undefined && this.config.min !== '') {
                constrainedValue = Math.max(constrainedValue, parseFloat(this.config.min));
            }
            if (this.config.max !== undefined && this.config.max !== '') {
                constrainedValue = Math.min(constrainedValue, parseFloat(this.config.max));
            }
            if (this.config.step && this.config.step !== 'any') {
                const min = this.config.min !== undefined && this.config.min !== '' ? parseFloat(this.config.min) : 0;
                const steps = Math.round((constrainedValue - min) / this.config.step);
                constrainedValue = min + (steps * this.config.step);
            }
            return constrainedValue;
        }

        /**
         * 从滑块更新数值
         */
        updateValueFromSlider(value) {
            this.$input.val(value).trigger('input');
            this.updateSliderPosition();
            this.updateSliderValue();
        }

        /**
         * 更新滑块位置
         */
        updateSliderPosition() {
            if (this.$slider.length === 0) return;
            const value = parseFloat(this.$input.val()) || 0;
            const min = parseFloat(this.config.min) || 0;
            const max = parseFloat(this.config.max) || 100;
            const percent = Math.max(0, Math.min(100, ((value - min) / (max - min)) * 100));
            this.$sliderFill.css('width', percent + '%');
            this.$sliderHandle.css('left', percent + '%');
            this.$sliderHandle.attr('aria-valuenow', value);
        }

        /**
         * 更新滑块显示值
         */
        updateSliderValue() {
            if (this.$sliderValue.length === 0) return;
            const value = parseFloat(this.$input.val()) || 0;
            this.$sliderValue.text(value);
        }
    }

    /**
     * 初始化所有数字字段
     */
    function initNumberFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-number-field-container').each(function() {
            const $container = $(this);
            if ($container.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$container.data('pili-number-initialized')) {
                new XunNumberField($container);
                $container.data('pili-number-initialized', true);
            }
        });
    }

    PILI.registerBoot('number', initNumberFields);

    $(document).ready(function() {
        initNumberFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        initNumberFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        initNumberFields($container && $container.length ? $container : undefined);
    });

})(jQuery);
