/**
 * Date Field JavaScript - 组合式架构增强
 *
 * 保留所有date字段特有功能：日历弹窗、日期验证、格式化等
 * 基础文本输入功能由text.js处理
 *
 * @package PILI Framework
 * @author  June
 * @since   1.1.0
 */

(function($) {
    'use strict';

    function t(msgid, fallback) {
        if (typeof window.pilipost__ === 'function') {
            return window.pilipost__(msgid);
        }
        return fallback != null ? fallback : msgid;
    }

    function dateL10nBag() {
        var bag = (typeof PILI !== 'undefined' && typeof PILI.bag === 'function') ? PILI.bag('dateL10n') : null;
        return bag && typeof bag === 'object' ? bag : {};
    }

    function dateMonthsShort() {
        var m = dateL10nBag().monthsShort;
        if (Array.isArray(m) && m.length >= 12) {
            return m;
        }
        return ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
    }

    /**
     * XUN 日期选择器类
     */
    class XUNDatePicker {
        
        /**
         * 构造函数
         * 
         * @param {HTMLElement} element 日期字段容器元素
         * @param {Object} options 配置选项
         */
        constructor(element, options = {}) {
            this.element = element;
            this.options = this.mergeOptions(options);
            this.currentDate = new Date();
            this.selectedDate = null;
            this.isOpen = false;
            this.isRange = element.hasAttribute('data-range-type');
            this.rangeType = element.getAttribute('data-range-type') || null;
            this.init();
        }
        
        /**
         * 合并配置选项
         *
         * @param {Object} userOptions 用户配置
         * @returns {Object} 合并后的配置
         */
        mergeOptions(userOptions) {
            const defaults = {
                date_format: 'Y年m月d日',
                display_format: (dateL10nBag().displayFormat) || t('Y年m月d日', 'Y年m月d日'),
                placeholder: (dateL10nBag().placeholder) || t('请选择日期', '请选择日期'),
                min_date: null,
                max_date: null,
                disabled_dates: [],
                disabled_days: [],
                first_day: 1,
                show_today: true,
                show_clear: true,
                auto_close: true,
                locale: (dateL10nBag().locale) || 'en-US'
            };
            const merged = Object.assign({}, defaults, userOptions);
            if (merged.time_only) {
                merged.enable_time = true;
            }
            merged.dateFormat = merged.date_format;
            merged.displayFormat = merged.display_format;
            merged.minDate = merged.min_date;
            merged.maxDate = merged.max_date;
            merged.disabledDates = merged.disabled_dates;
            merged.disabledDays = merged.disabled_days;
            merged.firstDay = merged.first_day;
            merged.showToday = merged.show_today;
            merged.showClear = merged.show_clear;
            merged.autoClose = merged.auto_close;
            return merged;
        }
        
        /**
         * 初始化日期选择器
         */
        init() {
            this.bindElements();
            this.bindShellEvents();
            if (this.grid || this.options.time_only) {
                this.bindPickerEvents();
            }
            this.parseInitialValue();
            this.updateDisplay();
        }
        
        /**
         * 绑定DOM元素
         */
        bindElements() {
            this.wrapper = this.element;
            this.hiddenInput = this.wrapper.querySelector('.pili-date-value');
            this.displayInput = this.wrapper.querySelector('.pili-date-input');
            this.trigger = this.wrapper.querySelector('.pili-date-trigger');
            this.picker = this.wrapper.querySelector('.pili-date-picker') || this.picker;
            if (this.picker && this.picker.parentElement !== this.wrapper && !this.picker.classList.contains('pili-date-picker--portal')) {
                // keep portaled picker reference
            }
            if (!this.picker) {
                this.picker = this.wrapper.querySelector('.pili-date-picker');
            }
            // 弹层挂到 body 后仍用实例上的 picker 引用。
            const pickerRoot = this.picker || this.wrapper.querySelector('.pili-date-picker');
            this.picker = pickerRoot;
            this.content = pickerRoot ? pickerRoot.querySelector('.pili-date-picker-content') : null;
            this.header = pickerRoot ? pickerRoot.querySelector('.pili-date-header') : null;
            this.prevBtn = pickerRoot ? pickerRoot.querySelector('.pili-date-prev-month') : null;
            this.nextBtn = pickerRoot ? pickerRoot.querySelector('.pili-date-next-month') : null;
            this.monthYearBtn = pickerRoot ? pickerRoot.querySelector('.pili-date-month-year') : null;
            this.monthYearSpan = pickerRoot ? pickerRoot.querySelector('.pili-current-month-year') : null;
            this.grid = pickerRoot ? pickerRoot.querySelector('.pili-date-grid') : null;
            this.todayBtn = pickerRoot ? pickerRoot.querySelector('.pili-date-today') : null;
            this.clearBtn = pickerRoot ? pickerRoot.querySelector('.pili-date-clear') : null;
            this.timeSection = pickerRoot ? pickerRoot.querySelector('.pili-time-section') : null;
            this.hourInput = pickerRoot ? pickerRoot.querySelector('.pili-time-hour') : null;
            this.minuteInput = pickerRoot ? pickerRoot.querySelector('.pili-time-minute') : null;
            this.secondInput = pickerRoot ? pickerRoot.querySelector('.pili-time-second') : null;
            this.ampmSelect = pickerRoot ? pickerRoot.querySelector('.pili-time-ampm') : null;
            this.timeNowBtn = pickerRoot ? pickerRoot.querySelector('.pili-time-now') : null;
            this.timePresetBtns = pickerRoot ? pickerRoot.querySelectorAll('.pili-time-preset') : [];
        }

        /**
         * defer_picker：首开时从页面原型克隆日历 DOM。
         */
        ensurePickerMounted() {
            // time_only 无日格也算已挂载，禁止再从全局原型克隆回整月历。
            if (this.content && (this.grid || this.options.time_only)) {
                return true;
            }
            if (!this.picker) {
                return false;
            }
            const proto =
                document.querySelector('#pili-date-picker-prototype .pili-date-picker-content') ||
                document.querySelector(
                    '.space-y-2[data-field-id="post_schedule_list_date_proto"] .pili-date-picker-content'
                ) ||
                document.querySelector(
                    '.pili-date-field-wrapper:not([data-defer-picker="1"]) .pili-date-picker-content'
                );
            if (!proto) {
                return false;
            }
            this.picker.innerHTML = '';
            this.picker.appendChild(proto.cloneNode(true));
            this._pickerEventsBound = false;
            this.bindElements();
            this.bindPickerEvents();
            if (this.selectedDate && typeof this.syncTimeInputsFromDate === 'function') {
                this.syncTimeInputsFromDate(this.selectedDate);
            }
            if (this.options.time_only) {
                this.picker.classList.add('pili-date-time-only');
                ['.pili-date-header', '.pili-date-weekdays', '.pili-date-grid'].forEach((sel) => {
                    const node = this.picker.querySelector(sel);
                    if (node) {
                        node.style.display = 'none';
                    }
                });
                return !!this.content;
            }
            // 原型里的 .pili-date-grid 是空的，必须立刻画日格，否则只剩时间区。
            this.paintCalendarChrome();
            return !!(this.grid && this.content);
        }

        /**
         * 刷新月份标题 + 日格（不写 hidden、不派发 change）。
         */
        paintCalendarChrome() {
            if (this.options.time_only) {
                return;
            }
            if (this.monthYearSpan && this.currentDate) {
                const monthNames = dateMonthsShort();
                this.monthYearSpan.textContent = `${this.currentDate.getFullYear()}年${monthNames[this.currentDate.getMonth()]}`;
            }
            this.renderCalendar();
        }
        
        /**
         * 输入框 / 触发器事件（弹层未挂载也可绑）。
         */
        bindShellEvents() {
            if (this._shellEventsBound) {
                return;
            }
            this._shellEventsBound = true;
            if (this.trigger) {
                this.trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggle();
                });
            }
            if (this.displayInput) {
                this.displayInput.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.open();
                });
                this.displayInput.addEventListener('keydown', (e) => {
                    this.handleKeydown(e);
                });
            }
            this._onDocClick = (e) => {
                if (!this.isOpen) {
                    return;
                }
                const t = e.target;
                if (this.wrapper.contains(t) || (this.picker && this.picker.contains(t))) {
                    return;
                }
                this.close();
            };
            document.addEventListener('click', this._onDocClick);
        }

        /**
         * 日历内部控件事件。
         */
        bindPickerEvents() {
            if (this._pickerEventsBound) {
                return;
            }
            // 仅时间无 prevBtn；缺月历导航时仍须绑时分/清除，不能整段 return。
            if (!this.options.time_only && !this.prevBtn) {
                return;
            }
            this._pickerEventsBound = true;
            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.previousMonth();
                });
            }
            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.nextMonth();
                });
            }
            if (this.todayBtn && (this.options.show_today !== false)) {
                this.todayBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.selectToday();
                });
            }
            if (this.clearBtn && (this.options.show_clear !== false)) {
                this.clearBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.clear();
                });
            }
            if (this.hourInput) {
                this.hourInput.addEventListener('input', () => this.validateAndUpdateTime());
                this.hourInput.addEventListener('blur', () => this.formatTimeInput(this.hourInput, 'hour'));
            }
            if (this.minuteInput) {
                this.minuteInput.addEventListener('input', () => this.validateAndUpdateTime());
                this.minuteInput.addEventListener('blur', () => this.formatTimeInput(this.minuteInput, 'minute'));
            }
            if (this.secondInput) {
                this.secondInput.addEventListener('input', () => this.validateAndUpdateTime());
                this.secondInput.addEventListener('blur', () => this.formatTimeInput(this.secondInput, 'second'));
            }
            if (this.ampmSelect) {
                this.ampmSelect.addEventListener('change', () => this.updateDateTime());
            }
            if (this.timeNowBtn) {
                this.timeNowBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.setCurrentTime();
                });
            }
            (this.timePresetBtns || []).forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const time = btn.dataset.time;
                    this.setPresetTime(time);
                });
            });
            if (this.picker) {
                this.picker.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }
        }

        /**
         * @deprecated 兼容旧调用
         */
        bindEvents() {
            this.bindShellEvents();
            this.bindPickerEvents();
        }
        
        /**
         * 解析初始值
         */
        parseInitialValue() {
            const value = this.hiddenInput.value;
            if (value) {
                this.selectedDate = this.parseDate(value);
                if (this.selectedDate) {
                    this.currentDate = new Date(this.selectedDate);
                    this.syncTimeInputsFromDate(this.selectedDate);
                }
            }
        }

        /**
         * 将已选日期的时分秒同步到时间输入（enable_time 初值必走）。
         *
         * @param {Date} date
         */
        syncTimeInputsFromDate(date) {
            if (!this.options.enable_time || !this.hourInput || !this.minuteInput || !date) {
                return;
            }
            let hour = date.getHours();
            const minute = date.getMinutes();
            const second = date.getSeconds();
            if (this.options.time_format === '12') {
                const ampm = hour >= 12 ? 'PM' : 'AM';
                hour = hour % 12;
                if (hour === 0) {
                    hour = 12;
                }
                this.hourInput.value = hour.toString().padStart(2, '0');
                if (this.ampmSelect) {
                    this.ampmSelect.value = ampm;
                }
            } else {
                this.hourInput.value = hour.toString().padStart(2, '0');
            }
            this.minuteInput.value = minute.toString().padStart(2, '0');
            if (this.secondInput && this.options.show_seconds) {
                this.secondInput.value = second.toString().padStart(2, '0');
            }
        }
        
        /**
         * 解析日期字符串
         * 
         * @param {string} dateString 日期字符串
         * @returns {Date|null} 解析后的日期对象
         */
        parseDate(dateString) {
            if (!dateString) return null;
            const raw = String(dateString).trim();
            // 仅时间：14:30 / 14:30:00
            const timeOnly = raw.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
            if (timeOnly) {
                const now = new Date();
                const hh = parseInt(timeOnly[1], 10);
                const mm = parseInt(timeOnly[2], 10);
                const ss = timeOnly[3] ? parseInt(timeOnly[3], 10) : 0;
                if (hh > 23 || mm > 59 || ss > 59) {
                    return null;
                }
                return new Date(now.getFullYear(), now.getMonth(), now.getDate(), hh, mm, ss);
            }
            const dtMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
            if (dtMatch) {
                const y = parseInt(dtMatch[1], 10);
                const m = parseInt(dtMatch[2], 10) - 1;
                const d = parseInt(dtMatch[3], 10);
                const hh = parseInt(dtMatch[4], 10);
                const mm = parseInt(dtMatch[5], 10);
                const ss = dtMatch[6] ? parseInt(dtMatch[6], 10) : 0;
                const date = new Date(y, m, d, hh, mm, ss);
                if (
                    date.getFullYear() === y &&
                    date.getMonth() === m &&
                    date.getDate() === d
                ) {
                    return date;
                }
            }
            const formats = [
                /^(\d{4})-(\d{2})-(\d{2})$/,
                /^(\d{4})\/(\d{2})\/(\d{2})$/,
                /^(\d{2})\/(\d{2})\/(\d{4})$/,
                /^(\d{2})-(\d{2})-(\d{4})$/
            ];
            for (let format of formats) {
                const match = raw.match(format);
                if (match) {
                    let year, month, day;
                    if (format === formats[2] || format === formats[3]) {
                        month = parseInt(match[1]) - 1;
                        day = parseInt(match[2]);
                        year = parseInt(match[3]);
                    } else {
                        year = parseInt(match[1]);
                        month = parseInt(match[2]) - 1;
                        day = parseInt(match[3]);
                    }
                    const date = new Date(year, month, day);
                    if (date.getFullYear() === year && 
                        date.getMonth() === month && 
                        date.getDate() === day) {
                        return date;
                    }
                }
            }
            const date = new Date(raw);
            return isNaN(date.getTime()) ? null : date;
        }
        
        /**
         * 格式化日期
         *
         * @param {Date} date 日期对象
         * @param {string} format 格式字符串
         * @returns {string} 格式化后的日期字符串
         */
        formatDate(date, format) {
            if (!date || isNaN(date.getTime())) return '';
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const day = date.getDate();
            const dayOfWeek = date.getDay();
            const weekdays = (Array.isArray(dateL10nBag().weekdays) && dateL10nBag().weekdays.length >= 7)
                ? dateL10nBag().weekdays
                : [t('星期日'), t('星期一'), t('星期二'), t('星期三'), t('星期四'), t('星期五'), t('星期六')];
            const weekdaysShort = (Array.isArray(dateL10nBag().weekdaysShort) && dateL10nBag().weekdaysShort.length >= 7)
                ? dateL10nBag().weekdaysShort
                : [t('日'), t('一'), t('二'), t('三'), t('四'), t('五'), t('六')];
            return format
                .replace(/Y/g, year)
                .replace(/m/g, month.toString().padStart(2, '0'))
                .replace(/d/g, day.toString().padStart(2, '0'))
                .replace(/l/g, weekdays[dayOfWeek])
                .replace(/D/g, weekdaysShort[dayOfWeek])
                .replace(/n/g, month)
                .replace(/j/g, day);
        }
        
        /**
         * 更新显示
         */
        updateDisplay() {
            if (this.selectedDate) {
                if (this.options.time_only) {
                    if (!this.hourInput || !this.minuteInput) {
                        this.syncTimeInputsFromDate(this.selectedDate);
                    }
                    const timeString = this.getTimeString();
                    this.displayInput.value = timeString;
                    this.hiddenInput.value = timeString;
                } else {
                    let displayValue = this.formatDate(this.selectedDate, this.options.display_format || this.options.displayFormat);
                    let hiddenValue = this.formatDate(this.selectedDate, this.options.date_format || this.options.dateFormat);
                    if (this.options.enable_time && this.timeSection) {
                        const timeString = this.getTimeString();
                        if (timeString) {
                            displayValue += ' ' + timeString;
                            hiddenValue += ' ' + timeString;
                        }
                    }
                    this.displayInput.value = displayValue;
                    this.hiddenInput.value = hiddenValue;
                }
            } else {
                this.displayInput.value = '';
                this.hiddenInput.value = '';
            }
            if (this.monthYearSpan && !this.options.time_only) {
                const monthNames = dateMonthsShort();
                this.monthYearSpan.textContent = `${this.currentDate.getFullYear()}年${monthNames[this.currentDate.getMonth()]}`;
            }
            if (!this.options.time_only) {
                this.renderCalendar();
            }
            this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        /**
         * 渲染日历
         */
        renderCalendar() {
            if (!this.grid) return;
            this.grid.innerHTML = '';
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const startDate = new Date(firstDay);
            const firstDayOfWeek = this.options.first_day || this.options.firstDay || 1;
            const dayOffset = (firstDay.getDay() - firstDayOfWeek + 7) % 7;
            startDate.setDate(startDate.getDate() - dayOffset);
            for (let week = 0; week < 6; week++) {
                for (let day = 0; day < 7; day++) {
                    const currentDate = new Date(startDate);
                    currentDate.setDate(startDate.getDate() + (week * 7) + day);
                    const dayElement = this.createDayElement(currentDate, month);
                    this.grid.appendChild(dayElement);
                }
            }
        }

        /**
         * 创建日期元素
         *
         * @param {Date} date 日期
         * @param {number} currentMonth 当前月份
         * @returns {HTMLElement} 日期元素
         */
        createDayElement(date, currentMonth) {
            const dayElement = document.createElement('button');
            dayElement.type = 'button';
            dayElement.textContent = date.getDate();
            let classes = ['w-8', 'h-8', 'text-sm', 'rounded-lg', 'transition-all', 'duration-200', 'focus:outline-none', 'focus:ring-2', 'focus:ring-indigo-500'];
            const isCurrentMonth = date.getMonth() === currentMonth;
            const isToday = this.isToday(date);
            const isSelected = this.isSelected(date);
            const isDisabled = this.isDateDisabled(date);
            if (!isCurrentMonth) {
                classes.push('text-gray-300');
            } else if (isDisabled) {
                classes.push('text-gray-300', 'cursor-not-allowed');
                dayElement.disabled = true;
            } else {
                classes.push('text-gray-700', 'hover:bg-indigo-50', 'hover:text-indigo-600');
            }
            if (isToday && !isSelected) {
                classes.push('bg-indigo-100', 'text-indigo-600', 'font-medium');
            }
            if (isSelected) {
                classes.push('bg-indigo-600', 'text-white', 'font-medium');
            }
            dayElement.className = classes.join(' ');
            dayElement.setAttribute('aria-label', this.formatDate(date, 'Y年m月d日'));
            if (isSelected) {
                dayElement.setAttribute('aria-selected', 'true');
            }
            if (isToday) {
                dayElement.setAttribute('aria-current', 'date');
            }
            if (!isDisabled) {
                dayElement.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.selectDate(date);
                });
            }
            return dayElement;
        }

        /**
         * 检查是否为今天
         *
         * @param {Date} date 要检查的日期
         * @returns {boolean} 是否为今天
         */
        isToday(date) {
            const today = new Date();
            return date.getFullYear() === today.getFullYear() &&
                   date.getMonth() === today.getMonth() &&
                   date.getDate() === today.getDate();
        }

        /**
         * 检查是否为选中日期
         *
         * @param {Date} date 要检查的日期
         * @returns {boolean} 是否为选中日期
         */
        isSelected(date) {
            if (!this.selectedDate) return false;
            return date.getFullYear() === this.selectedDate.getFullYear() &&
                   date.getMonth() === this.selectedDate.getMonth() &&
                   date.getDate() === this.selectedDate.getDate();
        }

        /**
         * 检查日期是否被禁用
         *
         * @param {Date} date 要检查的日期
         * @returns {boolean} 是否被禁用
         */
        isDateDisabled(date) {
            const minDate = this.options.min_date || this.options.minDate;
            if (minDate) {
                const minDateObj = this.parseDate(minDate);
                if (minDateObj && date < minDateObj) {
                    return true;
                }
            }
            const maxDate = this.options.max_date || this.options.maxDate;
            if (maxDate) {
                const maxDateObj = this.parseDate(maxDate);
                if (maxDateObj && date > maxDateObj) {
                    return true;
                }
            }
            const disabledDays = this.options.disabled_days || this.options.disabledDays || [];
            if (disabledDays.length > 0) {
                if (disabledDays.includes(date.getDay())) {
                    return true;
                }
            }
            const disabledDates = this.options.disabled_dates || this.options.disabledDates || [];
            if (disabledDates.length > 0) {
                const dateString = this.formatDate(date, this.options.date_format || this.options.dateFormat);
                if (disabledDates.includes(dateString)) {
                    return true;
                }
            }
            return false;
        }

        /**
         * 选择日期
         *
         * @param {Date} date 要选择的日期
         */
        selectDate(date) {
            this.selectedDate = new Date(date);
            this.updateDisplay();
            const autoClose = this.options.auto_close !== undefined ? this.options.auto_close : this.options.autoClose;
            if (autoClose !== false) {
                this.close();
            }
        }

        /**
         * 选择今天
         */
        selectToday() {
            const today = new Date();
            if (!this.isDateDisabled(today)) {
                this.selectDate(today);
                this.currentDate = new Date(today);
                if (this.options.enable_time && this.timeSection) {
                    this.setCurrentTime();
                }
                this.updateDisplay();
            }
        }

        /**
         * 清除选择
         */
        clear() {
            this.selectedDate = null;
            this.updateDisplay();
            const autoClose = this.options.auto_close !== undefined ? this.options.auto_close : this.options.autoClose;
            if (autoClose !== false) {
                this.close();
            }
        }

        /**
         * 上一个月
         */
        previousMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.updateDisplay();
        }

        /**
         * 下一个月
         */
        nextMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.updateDisplay();
        }

        /**
         * 打开选择器
         */
        open() {
            if (this.isOpen) return;
            if (!this.ensurePickerMounted()) {
                return;
            }
            this.bindPickerEvents();
            this.isOpen = true;
            if (this.options.time_only && !this.selectedDate) {
                this.selectedDate = new Date();
                this.syncTimeInputsFromDate(this.selectedDate);
            }
            if (this.options.time_only && this.picker) {
                this.picker.classList.add('pili-date-time-only');
                // CSS 未命中时仍强制藏日历（portal / 旧 inline 样式兜底）。
                ['.pili-date-header', '.pili-date-weekdays', '.pili-date-grid'].forEach((sel) => {
                    const node = this.picker.querySelector(sel);
                    if (node) {
                        node.style.display = 'none';
                    }
                });
                if (this.timeSection) {
                    this.timeSection.style.borderTop = '0';
                    this.timeSection.style.paddingTop = '0';
                }
            }
            // 表格内挂到 body，避免被 overflow 裁切。
            if (this.picker && this.picker.parentElement !== document.body) {
                document.body.appendChild(this.picker);
                this.picker.classList.add('pili-date-picker--portal');
                this._ported = true;
            }
            this.picker.classList.remove('hidden');
            // 每次打开都重画日格（defer 首开 / portal 后高度才准）。
            this.paintCalendarChrome();
            this.positionPicker();
            this.bindPositionListeners();
            requestAnimationFrame(() => {
                this.picker.classList.remove('opacity-0', 'scale-95');
                this.picker.classList.add('opacity-100', 'scale-100');
                this.positionPicker();
            });
            if (typeof this.picker.focus === 'function') {
                this.picker.focus();
            }
        }

        /**
         * 关闭选择器
         */
        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this.unbindPositionListeners();
            this.picker.classList.remove('opacity-100', 'scale-100');
            this.picker.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                this.picker.classList.add('hidden');
            }, 200);
            if (this.hiddenInput) {
                this.hiddenInput.dispatchEvent(new CustomEvent('xun:date:commit', { bubbles: true }));
            }
        }

        /**
         * 切换选择器显示状态
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        /**
         * 智能定位选择器
         */
        positionPicker() {
            if (!this.picker || !this.displayInput) {
                return;
            }
            const rect = this.displayInput.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            if (this._ported) {
                this.picker.style.position = 'fixed';
                this.picker.classList.remove('top-full', 'bottom-full', 'left-0', 'right-0', 'absolute');
                // 先显示以量尺寸
                const prevVis = this.picker.style.visibility;
                this.picker.style.visibility = 'hidden';
                this.picker.classList.remove('hidden');
                const pr = this.picker.getBoundingClientRect();
                let top = rect.bottom + 8;
                if (top + pr.height > viewportHeight - 8 && rect.top > pr.height + 8) {
                    top = rect.top - pr.height - 8;
                }
                let left = rect.left;
                if (left + pr.width > viewportWidth - 8) {
                    left = Math.max(8, viewportWidth - pr.width - 8);
                }
                this.picker.style.top = top + 'px';
                this.picker.style.left = left + 'px';
                this.picker.style.visibility = prevVis || '';
                return;
            }
            const pickerRect = this.picker.getBoundingClientRect();
            this.picker.classList.remove('top-full', 'bottom-full', 'left-0', 'right-0');
            const spaceBelow = viewportHeight - rect.bottom;
            const spaceAbove = rect.top;
            if (spaceBelow >= pickerRect.height + 10 || spaceBelow >= spaceAbove) {
                this.picker.classList.add('top-full');
            } else {
                this.picker.classList.add('bottom-full');
            }
            const spaceRight = viewportWidth - rect.left;
            if (spaceRight >= pickerRect.width) {
                this.picker.classList.add('left-0');
            } else {
                this.picker.classList.add('right-0');
            }
        }

        /**
         * portal fixed 弹层：滚动/缩放时贴输入框（对齐 select）。
         */
        bindPositionListeners() {
            if (this._onReposition) {
                return;
            }
            this._onReposition = () => {
                if (this.isOpen) {
                    this.positionPicker();
                }
            };
            this._onScroll = (e) => {
                if (!this.isOpen) {
                    return;
                }
                const t = e.target;
                if (t && this.picker && (this.picker === t || this.picker.contains(t))) {
                    return;
                }
                this.positionPicker();
            };
            window.addEventListener('resize', this._onReposition);
            document.addEventListener('scroll', this._onScroll, true);
        }

        /**
         * @return {void}
         */
        unbindPositionListeners() {
            if (this._onReposition) {
                window.removeEventListener('resize', this._onReposition);
                this._onReposition = null;
            }
            if (this._onScroll) {
                document.removeEventListener('scroll', this._onScroll, true);
                this._onScroll = null;
            }
        }

        /**
         * 处理键盘事件
         *
         * @param {KeyboardEvent} e 键盘事件
         */
        handleKeydown(e) {
            switch (e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    this.open();
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.open();
                    }
                    break;
                case 'Tab':
                    if (this.isOpen) {
                        this.close();
                    }
                    break;
            }
        }

        /**
         * 销毁日期选择器
         */
        destroy() {
            this.close();
            this.unbindPositionListeners();
            if (this._onDocClick) {
                document.removeEventListener('click', this._onDocClick);
                this._onDocClick = null;
            }
            if (this.trigger) {
                this.trigger.removeEventListener('click', this.toggle);
            }
            if (this.displayInput) {
                this.displayInput.removeEventListener('click', this.open);
                this.displayInput.removeEventListener('keydown', this.handleKeydown);
            }
            this.element = null;
            this.options = null;
            this.currentDate = null;
            this.selectedDate = null;
        }

        /**
         * 获取时间字符串
         *
         * @returns {string} 格式化的时间字符串
         */
        getTimeString() {
            if (!this.hourInput || !this.minuteInput) {
                return '';
            }
            const hour = parseInt(this.hourInput.value) || 0;
            const minute = parseInt(this.minuteInput.value) || 0;
            const second = this.secondInput ? (parseInt(this.secondInput.value) || 0) : 0;
            const ampm = this.ampmSelect ? this.ampmSelect.value : '';
            let timeString = '';
            if (this.options.time_format === '12') {
                timeString = `${hour}:${minute.toString().padStart(2, '0')}`;
                if (this.options.show_seconds && this.secondInput) {
                    timeString += `:${second.toString().padStart(2, '0')}`;
                }
                timeString += ` ${ampm}`;
            } else {
                let hour24 = hour;
                if (ampm === 'PM' && hour !== 12) {
                    hour24 += 12;
                } else if (ampm === 'AM' && hour === 12) {
                    hour24 = 0;
                }
                timeString = `${hour24.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                if (this.options.show_seconds && this.secondInput) {
                    timeString += `:${second.toString().padStart(2, '0')}`;
                }
            }
            return timeString;
        }

        /**
         * 设置当前时间
         */
        setCurrentTime() {
            if (!this.hourInput || !this.minuteInput) {
                return;
            }
            const now = new Date();
            let hour = now.getHours();
            const minute = now.getMinutes();
            const second = now.getSeconds();
            if (this.options.time_format === '12') {
                const ampm = hour >= 12 ? 'PM' : 'AM';
                hour = hour % 12;
                if (hour === 0) hour = 12;
                this.hourInput.value = hour.toString().padStart(2, '0');
                if (this.ampmSelect) {
                    this.ampmSelect.value = ampm;
                }
            } else {
                this.hourInput.value = hour.toString().padStart(2, '0');
            }
            this.minuteInput.value = minute.toString().padStart(2, '0');
            if (this.secondInput && this.options.show_seconds) {
                this.secondInput.value = second.toString().padStart(2, '0');
            }
            this.updateDateTime();
        }

        /**
         * 更新日期时间
         */
        updateDateTime() {
            if (!this.selectedDate) {
                this.selectedDate = new Date();
            }
            if (this.hourInput && this.minuteInput) {
                let hour = parseInt(this.hourInput.value, 10);
                if (isNaN(hour)) {
                    hour = 0;
                }
                const minute = parseInt(this.minuteInput.value, 10) || 0;
                const second = this.secondInput ? (parseInt(this.secondInput.value, 10) || 0) : 0;
                if (this.options.time_format === '12' && this.ampmSelect) {
                    const ampm = this.ampmSelect.value;
                    if (ampm === 'PM' && hour !== 12) {
                        hour += 12;
                    } else if (ampm === 'AM' && hour === 12) {
                        hour = 0;
                    }
                }
                this.selectedDate.setHours(hour, minute, second, 0);
            }
            this.updateDisplay();
        }

        /**
         * 验证并更新时间
         */
        validateAndUpdateTime() {
            if (this.hourInput) {
                const hour = parseInt(this.hourInput.value);
                const maxHour = this.options.time_format === '12' ? 12 : 23;
                const minHour = this.options.time_format === '12' ? 1 : 0;
                if (hour > maxHour) {
                    this.hourInput.value = maxHour;
                } else if (hour < minHour && this.hourInput.value !== '') {
                    this.hourInput.value = minHour;
                }
            }
            if (this.minuteInput) {
                const minute = parseInt(this.minuteInput.value);
                if (minute > 59) {
                    this.minuteInput.value = 59;
                } else if (minute < 0 && this.minuteInput.value !== '') {
                    this.minuteInput.value = 0;
                }
            }
            if (this.secondInput) {
                const second = parseInt(this.secondInput.value);
                if (second > 59) {
                    this.secondInput.value = 59;
                } else if (second < 0 && this.secondInput.value !== '') {
                    this.secondInput.value = 0;
                }
            }
            this.updateDateTime();
        }

        /**
         * 格式化时间输入框
         *
         * @param {HTMLElement} input 输入框元素
         * @param {string} type 类型：hour, minute, second
         */
        formatTimeInput(input, type) {
            const value = parseInt(input.value);
            if (isNaN(value)) {
                if (type === 'hour') {
                    input.value = this.options.time_format === '12' ? '12' : '00';
                } else {
                    input.value = '00';
                }
            } else {
                if (type === 'hour' && this.options.time_format === '24') {
                    input.value = value.toString().padStart(2, '0');
                } else if (type === 'hour' && this.options.time_format === '12') {
                    input.value = value.toString();
                } else {
                    input.value = value.toString().padStart(2, '0');
                }
            }
            this.updateDateTime();
        }

        /**
         * 设置预设时间
         *
         * @param {string} timeString 时间字符串，格式：HH:MM
         */
        setPresetTime(timeString) {
            const [hour, minute] = timeString.split(':').map(num => parseInt(num));
            if (this.options.time_format === '12') {
                let displayHour = hour;
                let ampm = 'AM';
                if (hour === 0) {
                    displayHour = 12;
                    ampm = 'AM';
                } else if (hour === 12) {
                    displayHour = 12;
                    ampm = 'PM';
                } else if (hour > 12) {
                    displayHour = hour - 12;
                    ampm = 'PM';
                }
                this.hourInput.value = displayHour.toString();
                if (this.ampmSelect) {
                    this.ampmSelect.value = ampm;
                }
            } else {
                this.hourInput.value = hour.toString().padStart(2, '0');
            }
            this.minuteInput.value = minute.toString().padStart(2, '0');
            if (this.secondInput) {
                this.secondInput.value = '00';
            }
            this.updateDateTime();
        }
    }

    /**
     * jQuery插件封装
     */
    $.fn.xunDatePicker = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('pili-date-picker');
            if (!instance) {
                const settings = $this.data('settings') || {};
                const mergedOptions = $.extend({}, settings, options);
                instance = new XUNDatePicker(this, mergedOptions);
                $this.data('pili-date-picker', instance);
            }
        });
    };

    /**
     * 自动初始化
     */
    function bootDateFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-date-field-wrapper').each(function() {
            var $wrap = $(this);
            if (!$wrap.data('pili-date-picker')) {
                $wrap.xunDatePicker();
            }
        });
    }

    PILI.registerBoot('date', bootDateFields);

    /**
     * 表格 editor=date 挂载：在空壳宿主上注入 date 控件（不经 PHP PILI::field）。
     *
     * @param {HTMLElement} host
     * @returns {Object|null}
     */
    PILI.registerMount('date', function (host) {
        if (!host || !host.getAttribute) {
            return null;
        }
        var settings = {};
        try {
            settings = JSON.parse(host.getAttribute('data-editor-settings') || '{}') || {};
        } catch (err) {
            settings = {};
        }
        settings.defer_picker = true;
        if (typeof settings.enable_time === 'undefined') {
            settings.enable_time = false;
        }

        var value = host.getAttribute('data-editor-value') || '';
        var placeholder =
            settings.placeholder ||
            (dateL10nBag().placeholder) ||
            t('请选择日期', '请选择日期');
        var openLabel =
            (dateL10nBag().selectTime) ||
            t('打开日期选择器', '打开日期选择器');

        var wrap = host.querySelector('.pili-date-field-wrapper');
        if (!wrap) {
            host.innerHTML =
                '<div class="pili-date-field-wrapper relative" data-defer-picker="1">' +
                '<input type="hidden" class="pili-date-value" value="" />' +
                '<div class="relative">' +
                '<input type="text" class="pili-date-input grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-10 pl-3 text-left text-gray-900 sm:text-sm/6 touch-manipulation focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 transition-colors duration-200" readonly />' +
                '<button type="button" class="pili-date-trigger absolute right-2 top-1/2 -translate-y-1/2 flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-200 focus:outline-none focus:text-indigo-600" aria-label=""></button>' +
                '</div>' +
                '<div class="pili-date-picker absolute top-full left-0 mt-2 z-50 hidden bg-white border border-gray-200 rounded-lg shadow-xl min-w-80 max-w-sm transform opacity-0 scale-95 transition-all duration-200 ease-out"></div>' +
                '</div>';
            wrap = host.querySelector('.pili-date-field-wrapper');
            var btn = host.querySelector('.pili-date-trigger');
            if (btn) {
                btn.setAttribute('aria-label', openLabel);
                btn.innerHTML =
                    '<svg class="w-5 h-5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                    '<rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke-width="2"></rect>' +
                    '<line x1="16" y1="2" x2="16" y2="6" stroke-width="2"></line>' +
                    '<line x1="8" y1="2" x2="8" y2="6" stroke-width="2"></line>' +
                    '<line x1="3" y1="10" x2="21" y2="10" stroke-width="2"></line>' +
                    '</svg>';
            }
        }

        var $wrap = $(wrap);
        $wrap.attr('data-settings', JSON.stringify(settings));
        $wrap.attr('data-defer-picker', '1');
        $wrap.removeData('settings');
        $wrap.find('.pili-date-value').val(value);
        $wrap.find('.pili-date-input').attr('placeholder', placeholder);

        if (!$wrap.data('pili-date-picker')) {
            $wrap.xunDatePicker(settings);
        }
        return $wrap.data('pili-date-picker') || null;
    });

    $(document).ready(function() {
        bootDateFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
        bootDateFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        bootDateFields($container && $container.length ? $container : undefined);
        if (PILI.bag('tableField') && typeof PILI.bag('tableField').mountEditors === 'function') {
            PILI.bag('tableField').mountEditors($container && $container.length ? $container : undefined);
        }
    });

    PILI.DatePicker = XUNDatePicker;

})(jQuery);
