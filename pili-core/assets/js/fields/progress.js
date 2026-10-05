/**
 * PILI Framework Progress 字段（进度条）
 *
 * @package PILI Framework
 */
(function ($) {
    'use strict';

    function parseConfig($track) {
        var raw = $track.attr('data-progress-config');
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function XunProgressBar($container) {
        this.$container = $container;
        this.$track = $container.find('.pili-progress-track');
        this.$fill = $container.find('.pili-progress-fill');
        this.$thumb = $container.find('.pili-progress-thumb');
        this.$input = $container.find('.pili-progress-input');
        this.$display = $container.find('.pili-progress-value-display');
        this.config = parseConfig(this.$track);
        this.showThumb = !!(this.config.showThumb && this.$thumb.length);
        this.trackInteractive = !!this.config.trackInteractive;
        if (this.showThumb) {
            this.$track.attr('tabindex', -1);
        } else if (this.trackInteractive) {
            this.$track.attr('tabindex', 0);
        } else {
            this.$track.attr('tabindex', -1);
        }
        this.isDragging = false;
        this.value = this.parseInputValue();
        this.init();
    }

    XunProgressBar.prototype.parseInputValue = function () {
        var v = parseFloat(this.$input.val());
        if (isNaN(v)) {
            return this.config.min != null ? this.config.min : 0;
        }
        return v;
    };

    XunProgressBar.prototype.clampAndStep = function (value) {
        value = Number(value);
        var min = Number(this.config.min != null ? this.config.min : 0);
        var max = Number(this.config.max != null ? this.config.max : 100);
        var step = Number(this.config.step != null ? this.config.step : 1);
        if (!isFinite(min)) {
            min = 0;
        }
        if (!isFinite(max)) {
            max = 100;
        }
        if (!isFinite(step) || step <= 0) {
            step = 1;
        }
        if (!isFinite(value)) {
            value = min;
        }
        value = Math.max(min, Math.min(max, value));
        if (step > 0) {
            value = Math.round(value / step) * step;
            value = Math.max(min, Math.min(max, value));
        }
        var prec = this.config.precision != null ? this.config.precision : 0;
        if (prec > 0) {
            value = parseFloat(value.toFixed(prec));
        } else {
            value = parseFloat(String(value));
        }
        return value;
    };

    XunProgressBar.prototype.ratio = function () {
        var min = Number(this.config.min != null ? this.config.min : 0);
        var max = Number(this.config.max != null ? this.config.max : 100);
        var val = Number(this.value);
        if (!isFinite(min) || !isFinite(max) || !isFinite(val)) {
            return 0;
        }
        var span = max - min;
        if (!isFinite(span) || Math.abs(span) < 1e-9) {
            return 0;
        }
        return (val - min) / span;
    };

    XunProgressBar.prototype.updateUI = function () {
        var raw = this.ratio();
        if (!isFinite(raw)) {
            raw = 0;
        }
        var r = Math.max(0, Math.min(1, raw));
        var pct = r * 100;
        if (!isFinite(pct)) {
            pct = 0;
        }
        this.$fill.css({
            width: pct + '%',
            height: '100%',
        });
        if (this.showThumb) {
            this.$thumb.css('left', pct + '%');
            this.$thumb.attr('aria-valuenow', this.value);
        }
        this.$track.attr('aria-valuenow', this.value);
        this.updateDisplayText();
    };

    XunProgressBar.prototype.updateDisplayText = function () {
        if (!this.$display.length) {
            return;
        }
        var mode = this.$container.attr('data-progress-display');
        var min = this.config.min != null ? this.config.min : 0;
        var max = this.config.max != null ? this.config.max : 100;
        var span = max - min;
        var prec = this.config.precision != null ? this.config.precision : 0;
        if (mode === 'percentage') {
            var showPct = Math.abs(span) < 1e-9 ? 0 : ((this.value - min) / span) * 100;
            this.$display.text(Math.round(showPct * Math.pow(10, prec)) / Math.pow(10, prec) + '%');
        } else {
            var num = prec > 0 ? parseFloat(this.value.toFixed(prec)) : parseFloat(String(this.value));
            var pre = this.$container.attr('data-progress-prefix') || '';
            var suf = this.$container.attr('data-progress-suffix') || '';
            this.$display.text(pre + num + suf);
        }
    };

    XunProgressBar.prototype.setValue = function (next, skipInputEvent) {
        this.value = this.clampAndStep(next);
        this.$input.val(this.value);
        this.updateUI();
        if (!skipInputEvent) {
            this.$input.trigger('change');
        }
        this.$container.trigger('xun:progress:change', { value: this.value });
    };

    XunProgressBar.prototype.posFromEvent = function (e) {
        var rect = this.$track[0].getBoundingClientRect();
        if (!rect || rect.width <= 0) {
            return null;
        }
        var clientX = e.clientX;
        if (clientX == null && e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0]) {
            clientX = e.originalEvent.touches[0].clientX;
        }
        if (clientX == null) {
            return null;
        }
        var min = Number(this.config.min != null ? this.config.min : 0);
        var max = Number(this.config.max != null ? this.config.max : 100);
        if (!isFinite(min) || !isFinite(max)) {
            return null;
        }
        var clickX = clientX - rect.left;
        var percentage = Math.max(0, Math.min(1, clickX / rect.width));
        return min + percentage * (max - min);
    };

    XunProgressBar.prototype.init = function () {
        var self = this;

        if (this.trackInteractive) {
            this.$track.on('mousedown touchstart', function (e) {
                if (self.showThumb && $(e.target).closest('.pili-progress-thumb').length) {
                    return;
                }
                var v = self.posFromEvent(e);
                if (v != null) {
                    self.setValue(v);
                }
            });
        }

        if (this.showThumb) {
            this.$thumb.on('mousedown touchstart', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.isDragging = true;
                self.$thumb.addClass('pili-progress-dragging');
                $('body').addClass('pili-progress-dragging');
            });
        }

        $(document).on('mousemove touchmove', function (e) {
            if (!self.isDragging) {
                return;
            }
            e.preventDefault();
            var v = self.posFromEvent(e);
            if (v != null) {
                self.setValue(v, true);
            }
        });

        $(document).on('mouseup touchend', function () {
            if (!self.isDragging) {
                return;
            }
            self.isDragging = false;
            self.$thumb.removeClass('pili-progress-dragging');
            $('body').removeClass('pili-progress-dragging');
            self.$input.trigger('change');
        });

        this.$input.on('input change', function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) {
                self.value = self.clampAndStep(v);
                self.updateUI();
            }
        });

        if (this.config.keyboard) {
            var $kbdTarget = this.showThumb ? this.$thumb : this.trackInteractive ? this.$track : null;
            if ($kbdTarget && $kbdTarget.length) {
                $kbdTarget.on('keydown', function (e) {
                    var step = Number(self.config.step != null ? self.config.step : 1);
                    if (!isFinite(step) || step <= 0) {
                        step = 1;
                    }
                    switch (e.key) {
                        case 'ArrowLeft':
                        case 'ArrowDown':
                            e.preventDefault();
                            self.setValue(self.value - step);
                            break;
                        case 'ArrowRight':
                        case 'ArrowUp':
                            e.preventDefault();
                            self.setValue(self.value + step);
                            break;
                        case 'Home':
                            e.preventDefault();
                            self.setValue(self.config.min != null ? self.config.min : 0);
                            break;
                        case 'End':
                            e.preventDefault();
                            self.setValue(self.config.max != null ? self.config.max : 100);
                            break;
                        default:
                            break;
                    }
                });
            }
        }

        this.addDynamicStyles();
        this.setValue(this.value, true);
    };

    XunProgressBar.prototype.addDynamicStyles = function () {
        if ($('#pili-progress-field-dynamic-styles').length) {
            return;
        }
        var styles =
            '<style id="pili-progress-field-dynamic-styles">' +
            /* 布局兜底：不依赖 Tailwind 是否扫描到 progress.php 中的 h-* / absolute 等类 */
            '.pili-progress-track{position:relative;width:100%;min-height:8px;border-radius:9999px;overflow:hidden;background-color:#e5e7eb;cursor:default;}' +
            '.pili-progress-field:not(.pili-progress-readonly-track) .pili-progress-track{cursor:pointer;}' +
            '.pili-progress-readonly-track .pili-progress-track{pointer-events:none!important;}' +
            '.pili-progress-small .pili-progress-track{min-height:6px;}' +
            '.pili-progress-medium .pili-progress-track{min-height:10px;}' +
            '.pili-progress-large .pili-progress-track{min-height:14px;}' +
            '.pili-progress-fill{position:absolute;top:0;left:0;height:100%;pointer-events:none;border-radius:9999px;}' +
            '.pili-progress-thumb{transform:translate(-50%,-50%);z-index:10;}' +
            '.pili-progress-track:focus{outline:none;box-shadow:0 0 0 2px var(--pili-progress-accent,#3b82f6);border-radius:9999px;}' +
            '.pili-progress-thumb:focus{outline:none;box-shadow:0 0 0 2px var(--pili-progress-accent,#3b82f6);}' +
            '.pili-progress-field .pili-progress-input:not([type=hidden]):focus{outline:none;box-shadow:0 0 0 2px var(--pili-progress-accent,#3b82f6);}' +
            '.pili-progress-dragging{cursor:grabbing!important;}' +
            '.pili-progress-thumb.pili-progress-dragging{cursor:grabbing!important;}' +
            '.pili-progress-animate .pili-progress-fill,.pili-progress-animate .pili-progress-thumb{transition:all .2s ease;}' +
            '.pili-progress-dragging .pili-progress-fill,.pili-progress-dragging .pili-progress-thumb{transition:none!important;}' +
            /* 百分比默认贴在进度条右侧 */
            '.pili-progress-bar-row{display:flex;align-items:center;gap:12px;width:100%;}' +
            '.pili-progress-track-wrap{flex:1 1 auto;min-width:0;}' +
            '.pili-progress-label-right .pili-progress-value-display{flex:0 0 auto;min-width:2.75em;text-align:right;font-weight:600;font-size:13px;line-height:1;white-space:nowrap;color:var(--pili-progress-label,#2563eb);}' +
            '.pili-progress-scale-below{display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:13px;}' +
            '</style>';
        $('head').append(styles);
    };

    function initProgressFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-progress-field').each(function () {
            var $container = $(this);
            if (!$root && $container.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$container.data('pili-progress-initialized')) {
                new XunProgressBar($container);
                $container.data('pili-progress-initialized', true);
            }
        });
    }

    PILI.registerBoot('progress', initProgressFields);

    $(document).ready(function () {
        initProgressFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function () {
        initProgressFields();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
        if ($container && $container.length) {
            initProgressFields($container);
        } else {
            initProgressFields();
        }
    });
})(jQuery);
