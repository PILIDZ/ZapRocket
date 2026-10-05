/**
 * 现代化颜色选择器字段 JavaScript
 * 
 * 支持多种颜色格式、透明度、调色板、历史记录等高级功能
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    function colorBag() {
        return (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('color')) || {};
    }

    function colorMsg(key, fallback) {
        var bag = colorBag();
        if (bag.messages && bag.messages[key]) {
            return bag.messages[key];
        }
        return fallback || key;
    }

    /**
     * 颜色选择器类
     */
    var XunColorPicker = {
        instances: {},
        colorHistory: [],
        maxHistoryItems: 20,
        /**
         * 初始化
         */
        init: function() {
            var self = this;
            $(document).ready(function() {
                self.initColorPickers();
            });
            $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
                if ($container && $container.length) {
                    self.initColorPickers($container);
                } else {
                    self.initColorPickers();
                }
            });
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type !== 'childList') {
                            return;
                        }
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType !== 1) {
                                return;
                            }
                            var $node = $(node);
                            if ($node.hasClass('pili-color-field') || $node.find('.pili-color-field').length > 0) {
                                self.initColorPickers($node);
                            }
                        });
                    });
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            $(document).on('keydown', function(e) {
                self.handleKeyboardShortcuts(e);
            });
        },
        /**
         * 实例键：优先 input name（Repeater 每行唯一），回退 fieldId。
         */
        getInstanceKey: function(config, $input) {
            var name = $input && $input.length ? $input.attr('name') : '';
            if (name) {
                return 'name:' + name;
            }
            return 'fid:' + (config && config.fieldId ? config.fieldId : '');
        },
        /**
         * @param {jQuery} [$root] 限定扫描根节点
         */
        initColorPickers: function($root) {
            var self = this;
            var $scope = ($root && $root.length) ? $root : $(document);

            $scope.find('.pili-color-field').each(function() {
                var $container = $(this);

                if ($container.closest('.pili-repeater-template').length) {
                    return;
                }

                if ($container.data('xunColorInitialized')) {
                    return;
                }

                var $config = $container.find('script.pili-color-config, .pili-color-config');
                if ($config.length === 0) {
                    return;
                }

                try {
                    var raw = $config.first().text();
                    if (!raw || !raw.trim()) {
                        return;
                    }
                    var config = JSON.parse(raw);
                    if (!config || !config.fieldId) {
                        return;
                    }

                    var $input = $container.find('.pili-color-input').first();
                    if ($input.length === 0) {
                        return;
                    }

                    var instanceKey = self.getInstanceKey(config, $input);
                    if (!instanceKey || self.instances[instanceKey]) {
                        return;
                    }

                    self.createColorPicker(config, $container, instanceKey);
                    $container.data('xunColorInitialized', true);
                } catch (error) {
                }
            });
        },
        /**
         * 创建颜色选择器实例
         */
        createColorPicker: function(config, $container, instanceKey) {
            var self = this;
            var $field = ($container && $container.length) ? $container : $();
            var $input = $field.find('.pili-color-input').first();
            var $preview = $field.find('[id$="-preview"]').first();
            if ($preview.length === 0) {
                $preview = $field.find('.w-12.h-12').first();
            }
            if ($field.length === 0 || $input.length === 0) {
                return;
            }
            self.instances[instanceKey] = {
                key: instanceKey,
                field: $field,
                input: $input,
                preview: $preview,
                config: config,
                currentColor: self.parseColor(config.value)
            };
            self.bindBasicEvents($field, instanceKey);
            if (config.value) {
                self.updateColorPreview(instanceKey, config.value);
            }
        },
        /**
         * 绑定基本事件
         */
        bindBasicEvents: function($field, instanceKey) {
            var self = this;
            var config = self.instances[instanceKey] ? self.instances[instanceKey].config : {};

            $field.on('click.xunColorPicker', '.w-12.h-12, .w-16.h-16', function() {
                self.openColorPicker(instanceKey);
            });
            $field.on('input change', '.pili-color-input', function() {
                var value = $(this).val();
                self.updateColorPreview(instanceKey, value);
            });
            $field.on('click.xunColorPicker', '.pili-palette-color', function() {
                var color = $(this).data('color');
                if (color) {
                    self.setColorValue(instanceKey, color);
                }
            });
            $field.on('change', '.pili-color-format-select', function() {
                var format = $(this).val();
                var instance = self.instances[instanceKey];
                if (!instance) {
                    return;
                }
                var currentValue = instance.input.val();
                var convertedValue = self.convertColorFormat(currentValue, format);
                self.setColorValue(instanceKey, convertedValue);
            });
        },
        /**
         * 设置颜色值
         */
        setColorValue: function(instanceKey, color) {
            var instance = this.instances[instanceKey];
            if (!instance) return;
            instance.input.val(color).trigger('change').trigger('xun:color:change');
            this.updateColorPreview(instanceKey, color);
            instance.currentColor = this.parseColor(color);
        },
        /**
         * 更新颜色预览
         */
        updateColorPreview: function(instanceKey, color) {
            var instance = this.instances[instanceKey];
            if (!instance) return;
            var $preview = instance.preview;
            if (!$preview || $preview.length === 0) {
                if (instance.field && instance.field.length > 0) {
                    $preview = instance.field.find('[id$="-preview"]').first();
                    if ($preview.length === 0) {
                        $preview = instance.field.find('.w-12.h-12').first();
                    }
                    if ($preview.length > 0) {
                        instance.preview = $preview;
                    }
                } else {
                    return;
                }
            }
            if ($preview && $preview.length > 0 && this.isValidColor(color)) {
                var $colorDiv = $preview.find('div');
                if ($colorDiv.length > 0) {
                    $colorDiv.css('background', color);
                } else {
                    $preview.css('background', color);
                }
            }
        },
        /**
         * 转换颜色格式
         */
        convertColorFormat: function(color, targetFormat) {
            var parsedColor = this.parseColor(color);
            return this.formatColor(parsedColor, targetFormat);
        },
        /**
         * 打开颜色选择器
         */
        openColorPicker: function(instanceKey) {
            var self = this;
            var instance = this.instances[instanceKey];
            if (!instance) return;
            if ($('.pili-color-picker-modal').length > 0) {
                $('.pili-color-picker-modal').remove();
            }
            var currentColor = instance.input.val() || '#3B82F6';
            var modalHtml = this.createColorPickerModal(instanceKey, currentColor);
            var $modal = $(modalHtml);
            $modal.css({
                opacity: 0,
                transition: 'opacity 0.3s ease-in-out'
            });
            $('body').append($modal);
            $modal.attr('data-instance-key', instanceKey);
            this.bindModalEvents(instanceKey);
            setTimeout(function() {
                $modal.css({
                    opacity: 1
                });
                $modal.find('.pili-color-picker-dialog').removeClass('opacity-0 translate-y-4 scale-95')
                                                       .addClass('opacity-100 translate-y-0 scale-100');
            }, 10);
        },
        /**
         * 创建颜色选择器模态框
         */
        createColorPickerModal: function(instanceKey, currentColor) {
            var msg = (PILI.bag('color') && PILI.bag('color').messages) ? PILI.bag('color').messages : {};
            var t = function(key, fallback) { return msg[key] || fallback; };
            var html = '<div class="pili-color-picker-modal fixed inset-0 flex items-center justify-center z-50 p-4" style="z-index: 9999; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); background: rgba(255, 255, 255, 0.1); opacity: 0; transition: opacity 0.3s ease-out;">';
            html += '<div class="pili-color-picker-dialog bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl w-full max-w-sm sm:max-w-md border border-white/20 transform transition-all duration-300 ease-out opacity-0 translate-y-4 scale-95 max-h-[90vh] overflow-y-auto" style="backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);">';
            html += '<div class="flex items-center justify-between p-3 sm:p-4 border-b border-white/20">';
            html += '<h3 class="text-base sm:text-lg font-semibold text-gray-800">' + t('selectColor', '选择颜色') + '</h3>';
            html += '<button type="button" class="pili-color-picker-close text-gray-500 hover:text-gray-700 hover:bg-white/50 rounded-full p-2 transition-all duration-200">';
            html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
            html += '</svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="p-3 sm:p-4">';
            html += '<div class="mb-3 sm:mb-4">';
            html += '<div class="w-full h-16 sm:h-20 rounded-xl border-2 border-white/30 mb-2 sm:mb-3 shadow-inner overflow-hidden" style="box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);" data-preview>';
            html += '<div class="w-full h-full rounded-lg" style="background: ' + currentColor + ';"></div>';
            html += '</div>';
            html += '<div class="mb-4">';
            html += '<label class="block text-xs font-medium text-gray-600 mb-1">' + t('outputFormat', '输出格式') + '</label>';
            html += '<div class="flex gap-2">';
            html += '<select class="flex-1 px-3 py-2 border border-white/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400/50 text-sm bg-white/50 backdrop-blur-sm" data-output-format>';
            html += '<option value="hex">HEX</option>';
            html += '<option value="rgb">RGB</option>';
            html += '<option value="hsl">HSL</option>';
            html += '<option value="hsv">HSV</option>';
            html += '</select>';
            html += '<input type="text" class="flex-2 px-3 py-2 border border-white/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400/50 font-mono text-sm bg-white/50 backdrop-blur-sm" data-current-color-value readonly>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="mb-3 sm:mb-4">';
            html += '<div class="relative h-40 sm:h-48 mb-2 sm:mb-3 rounded-xl overflow-hidden cursor-crosshair shadow-inner border border-white/20" data-color-canvas>';
            html += '<div class="absolute inset-0 rounded-xl" data-hue-background style="background: hsl(0, 100%, 50%);"></div>';
            html += '<div class="absolute inset-0 rounded-xl" style="background: linear-gradient(to right, rgba(255,255,255,1), rgba(255,255,255,0)), linear-gradient(to bottom, rgba(0,0,0,0), rgba(0,0,0,1));"></div>';
            html += '<div class="absolute w-4 h-4 border-3 border-white rounded-full shadow-xl pointer-events-none transform -translate-x-2 -translate-y-2 z-10" data-color-cursor style="left: 50%; top: 50%; box-shadow: 0 0 0 1px rgba(0,0,0,0.3), 0 4px 12px rgba(0,0,0,0.2);"></div>';
            html += '</div>';
            html += '<input type="range" class="w-full h-4 rounded-xl appearance-none cursor-pointer shadow-inner border border-white/20" style="background: linear-gradient(to right, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff, #ff00ff, #ff0000);" min="0" max="360" value="0" data-hue-slider>';
            html += '</div>';
            html += '<div class="mb-3 sm:mb-4">';
            html += '<label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1 sm:mb-2">' + t('presetColors', '预设颜色') + '</label>';
            html += '<div class="grid grid-cols-8 gap-1.5 sm:gap-2">';
            var presetColors = ['#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16', '#22C55E', '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9', '#3B82F6', '#6366F1', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899'];
            presetColors.forEach(function(color) {
                html += '<button type="button" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg border border-white/30 hover:scale-110 hover:shadow-lg transition-all duration-200 shadow-sm" style="background: ' + color + '; box-shadow: inset 0 1px 2px rgba(255,255,255,0.2), 0 2px 4px rgba(0,0,0,0.1);" data-preset-color="' + color + '"></button>';
            });
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="flex items-center justify-end gap-2 sm:gap-3 p-3 sm:p-4 border-t border-white/20">';
            html += '<button type="button" class="pili-color-picker-cancel px-3 py-2 sm:px-4 text-xs sm:text-sm font-medium text-gray-700 bg-white/50 backdrop-blur-sm border border-white/30 rounded-lg hover:bg-white/70 focus:outline-none focus:ring-2 focus:ring-blue-400/50 transition-all duration-200">' + t('cancel', '取消') + '</button>';
            html += '<button type="button" class="pili-color-picker-confirm px-3 py-2 sm:px-4 text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 border border-transparent rounded-lg hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400/50 shadow-lg transition-all duration-200">' + t('confirm', '确定') + '</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            return html;
        },
        /**
         * 绑定模态框事件
         */
        bindModalEvents: function(instanceKey) {
            var self = this;
            var $modal = $('.pili-color-picker-modal');
            $modal.on('click', '.pili-color-picker-close, .pili-color-picker-cancel', function() {
                self.closeColorPicker();
            });
            $modal.on('click', function(e) {
                if (e.target === this) {
                    self.closeColorPicker();
                }
            });
            $modal.on('click', '.pili-color-picker-confirm', function() {
                var selectedColor = $modal.find('[data-current-color-value]').val();
                if (selectedColor) {
                    self.setColorValue(instanceKey, selectedColor);
                }
                self.closeColorPicker();
            });
            $modal.on('change', '[data-output-format]', function() {
                var format = $(this).val();
                var colorValues = $modal.data('color-values');
                if (colorValues && colorValues[format]) {
                    $modal.find('[data-current-color-value]').val(colorValues[format]);
                }
            });
            $modal.on('click', '[data-preset-color]', function() {
                var color = $(this).data('preset-color');
                self.parseAndUpdateColor($modal, color);
                var $preview = $modal.find('[data-preview]');
                var $colorDiv = $preview.find('div');
                if ($colorDiv.length > 0) {
                    $colorDiv.css('background', color);
                } else {
                    $preview.css('background', color);
                }
            });
            $modal.on('mousedown touchstart', '[data-color-canvas]', function(e) {
                self.handleCanvasInteraction(e, $modal);
            });
            $modal.on('input change', '[data-hue-slider]', function() {
                var hue = $(this).val();
                self.updateCanvasHue($modal, hue);
            });
            $modal.on('mousedown touchstart', '[data-hue-slider]', function(e) {
                var $slider = $(this);
                var updateHue = function(e) {
                    var clientX = e.clientX || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0].clientX);
                    if (!clientX) return;
                    var rect = $slider[0].getBoundingClientRect();
                    var x = Math.max(0, Math.min(rect.width, clientX - rect.left));
                    var hue = Math.round((x / rect.width) * 360);
                    $slider.val(hue);
                    $slider.trigger('input');
                };
                updateHue(e);
                $(document).on('mousemove.hueslider touchmove.hueslider', function(e) {
                    e.preventDefault();
                    updateHue(e);
                });
                $(document).on('mouseup.hueslider touchend.hueslider', function() {
                    $(document).off('.hueslider');
                });
                e.preventDefault();
            });
        },
        /**
         * 关闭颜色选择器
         */
        closeColorPicker: function() {
            var $modal = $('.pili-color-picker-modal');
            var $dialog = $modal.find('.pili-color-picker-dialog');
            $modal.css({
                opacity: 0
            });
            $dialog.removeClass('opacity-100 translate-y-0 scale-100')
                   .addClass('opacity-0 translate-y-4 scale-95');
            setTimeout(function() {
                $modal.remove();
            }, 200);
        },
        /**
         * 处理画布交互
         */
        handleCanvasInteraction: function(e, $modal) {
            var self = this;
            var $canvas = $modal.find('[data-color-canvas]');
            var $cursor = $modal.find('[data-color-cursor]');
            var updateColor = function(e) {
                var clientX = e.clientX || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0].clientX);
                var clientY = e.clientY || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0].clientY);
                if (!clientX || !clientY) return;
                var rect = $canvas[0].getBoundingClientRect();
                var x = Math.max(0, Math.min(rect.width, clientX - rect.left));
                var y = Math.max(0, Math.min(rect.height, clientY - rect.top));
                var hue = parseInt($modal.find('[data-hue-slider]').val());
                var saturation = Math.round((x / rect.width) * 100);
                var value = Math.round(100 - (y / rect.height) * 100);
                $cursor.css({
                    left: x + 'px',
                    top: y + 'px'
                });
                var color = self.updateAllColorFormats($modal, hue, saturation, value);
                var $preview = $modal.find('[data-preview]');
                var $colorDiv = $preview.find('div');
                if ($colorDiv.length > 0) {
                    $colorDiv.css('background', color);
                } else {
                    $preview.css('background', color);
                }
            };
            e.preventDefault();
            updateColor(e);
            $(document).on('mousemove.colorpicker touchmove.colorpicker', function(e) {
                e.preventDefault();
                updateColor(e);
            });
            $(document).on('mouseup.colorpicker touchend.colorpicker', function() {
                $(document).off('.colorpicker');
            });
        },
        /**
         * HSV转HSL
         */
        hsvToHsl: function(h, s, v) {
            s = s / 100;
            v = v / 100;
            var l = v * (2 - s) / 2;
            var sl = l !== 0 && l !== 1 ? (v - l) / Math.min(l, 1 - l) : 0;
            return {
                h: h,
                s: Math.round(sl * 100),
                l: Math.round(l * 100)
            };
        },
        /**
         * HSL转RGB
         */
        hslToRgb: function(h, s, l) {
            h = h / 360;
            s = s / 100;
            l = l / 100;
            var r, g, b;
            if (s === 0) {
                r = g = b = l;
            } else {
                var hue2rgb = function(p, q, t) {
                    if (t < 0) t += 1;
                    if (t > 1) t -= 1;
                    if (t < 1/6) return p + (q - p) * 6 * t;
                    if (t < 1/2) return q;
                    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                    return p;
                };
                var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                var p = 2 * l - q;
                r = hue2rgb(p, q, h + 1/3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1/3);
            }
            return {
                r: Math.round(r * 255),
                g: Math.round(g * 255),
                b: Math.round(b * 255)
            };
        },
        /**
         * RGB转HEX
         */
        rgbToHex: function(r, g, b) {
            return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        },
        /**
         * 更新所有颜色格式显示
         */
        updateAllColorFormats: function($modal, hue, saturation, value) {
            var hsvString = 'hsv(' + hue + ', ' + saturation + '%, ' + value + '%)';
            var hsl = this.hsvToHsl(hue, saturation, value);
            var hslString = 'hsl(' + hsl.h + ', ' + hsl.s + '%, ' + hsl.l + '%)';
            var rgb = this.hslToRgb(hsl.h, hsl.s, hsl.l);
            var rgbString = 'rgb(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ')';
            var hexString = this.rgbToHex(rgb.r, rgb.g, rgb.b);
            var outputFormat = $modal.find('[data-output-format]').val() || 'hex';
            var currentValue = '';
            switch (outputFormat) {
                case 'hex':
                    currentValue = hexString;
                    break;
                case 'rgb':
                    currentValue = rgbString;
                    break;
                case 'hsl':
                    currentValue = hslString;
                    break;
                case 'hsv':
                    currentValue = hsvString;
                    break;
                default:
                    currentValue = hexString;
            }
            $modal.find('[data-current-color-value]').val(currentValue);
            $modal.data('color-values', {
                hex: hexString,
                rgb: rgbString,
                hsl: hslString,
                hsv: hsvString
            });
            // 拖拽/调色过程中：直接写入字段并广播，预览可立即跟上
            var instanceKey = $modal.attr('data-instance-key');
            if (instanceKey && this.instances[instanceKey]) {
                this.setColorValue(instanceKey, hexString);
            }
            return hexString;
        },
        /**
         * 解析颜色值并更新UI
         */
        parseAndUpdateColor: function($modal, colorString) {
            var hsv = this.parseColorToHsv(colorString);
            if (!hsv) return;
            $modal.find('[data-hue-slider]').val(hsv.h);
            this.updateCanvasHue($modal, hsv.h);
            var $canvas = $modal.find('[data-color-canvas]');
            var $cursor = $modal.find('[data-color-cursor]');
            var rect = $canvas[0].getBoundingClientRect();
            var x = (hsv.s / 100) * rect.width;
            var y = ((100 - hsv.v) / 100) * rect.height;
            $cursor.css({
                left: x + 'px',
                top: y + 'px'
            });
            this.updateAllColorFormats($modal, hsv.h, hsv.s, hsv.v);
        },
        /**
         * 解析颜色字符串为HSV
         */
        parseColorToHsv: function(colorString) {
            if (colorString.startsWith('#')) {
                return this.hexToHsv(colorString);
            } else if (colorString.startsWith('hsl')) {
                var match = colorString.match(/hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)/);
                if (match) {
                    var h = parseInt(match[1]);
                    var s = parseInt(match[2]);
                    var l = parseInt(match[3]);
                    return this.hslToHsv(h, s, l);
                }
            }
            return null;
        },
        /**
         * HEX转HSV
         */
        hexToHsv: function(hex) {
            var r = parseInt(hex.slice(1, 3), 16) / 255;
            var g = parseInt(hex.slice(3, 5), 16) / 255;
            var b = parseInt(hex.slice(5, 7), 16) / 255;
            return this.rgbToHsv(r * 255, g * 255, b * 255);
        },
        /**
         * RGB转HSV
         */
        rgbToHsv: function(r, g, b) {
            r /= 255;
            g /= 255;
            b /= 255;
            var max = Math.max(r, g, b);
            var min = Math.min(r, g, b);
            var h, s, v = max;
            var d = max - min;
            s = max === 0 ? 0 : d / max;
            if (max === min) {
                h = 0;
            } else {
                switch (max) {
                    case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                    case g: h = (b - r) / d + 2; break;
                    case b: h = (r - g) / d + 4; break;
                }
                h /= 6;
            }
            return {
                h: Math.round(h * 360),
                s: Math.round(s * 100),
                v: Math.round(v * 100)
            };
        },
        /**
         * HSL转HSV
         */
        hslToHsv: function(h, s, l) {
            s = s / 100;
            l = l / 100;
            var v = l + s * Math.min(l, 1 - l);
            var sNew = v === 0 ? 0 : 2 * (1 - l / v);
            return {
                h: h,
                s: Math.round(sNew * 100),
                v: Math.round(v * 100)
            };
        },
        /**
         * 更新画布色相
         */
        updateCanvasHue: function($modal, hue) {
            var $canvas = $modal.find('[data-color-canvas]');
            var $hueBackground = $canvas.find('[data-hue-background]');
            var hueColor = 'hsl(' + hue + ', 100%, 50%)';
            if ($hueBackground.length > 0) {
                $hueBackground.css('background', hueColor);
            } else {
                var $firstDiv = $canvas.find('div').first();
                if ($firstDiv.length > 0) {
                    $firstDiv.css('background', hueColor);
                }
            }
            var $cursor = $modal.find('[data-color-cursor]');
            var $canvas = $modal.find('[data-color-canvas]');
            var rect = $canvas[0].getBoundingClientRect();
            var cursorLeft = parseFloat($cursor.css('left')) || (rect.width / 2);
            var cursorTop = parseFloat($cursor.css('top')) || (rect.height / 2);
            var saturation = Math.round((cursorLeft / rect.width) * 100);
            var value = Math.round(100 - (cursorTop / rect.height) * 100);
            var color = this.updateAllColorFormats($modal, hue, saturation, value);
            var $preview = $modal.find('[data-preview]');
            var $colorDiv = $preview.find('div');
            if ($colorDiv.length > 0) {
                $colorDiv.css('background', color);
            } else {
                $preview.css('background', color);
            }
        },
        /**
         * 构建颜色选择器
         */
        buildColorPickerUI: function($container, config) {
            $container.empty();
            var html = '<div class="h-full flex flex-col bg-white">';
            html += '<div class="flex-shrink-0 p-4 border-b border-gray-200">';
            html += '<div class="flex items-center space-x-4">';
            html += '<div class="relative">';
            html += '<div class="pili-color-preview w-16 h-16 rounded-lg border-2 border-gray-300 shadow-sm cursor-pointer transition-transform duration-200 hover:scale-105" style="background: ' + config.value + ';"></div>';
            if (config.alpha) {
                html += '<div class="absolute inset-0 rounded-lg opacity-50 -z-10" style="background-image: repeating-conic-gradient(#ccc 0% 25%, transparent 0% 50%); background-size: 8px 8px;"></div>';
            }
            html += '</div>';
            html += '<div class="flex-1 space-y-3">';
            html += '<div class="flex items-center space-x-2">';
            html += '<input type="text" class="pili-color-value-input flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono transition-colors" value="' + config.value + '" placeholder="' + config.messages.formatLabel + '">';
            html += '<select class="pili-color-format-select px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">';
            var formats = ['hex', 'rgb', 'rgba', 'hsl', 'hsla'];
            formats.forEach(function(format) {
                var selected = format === config.format ? ' selected' : '';
                html += '<option value="' + format + '"' + selected + '>' + format.toUpperCase() + '</option>';
            });
            html += '</select>';
            html += '</div>';
            if (config.alpha) {
                html += '<div>';
                html += '<label class="block text-xs font-medium text-gray-700 mb-2">' + config.messages.alphaLabel + '</label>';
                html += '<div class="relative">';
                html += '<input type="range" class="pili-alpha-slider w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer" min="0" max="100" value="100">';
                html += '<div class="absolute inset-0 rounded-lg pointer-events-none opacity-30" style="background-image: repeating-linear-gradient(90deg, #ccc 0%, #ccc 25%, transparent 25%, transparent 50%); background-size: 8px 8px;"></div>';
                html += '</div>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="flex-1 p-4">';
            html += '<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 h-full">';
            html += '<div class="lg:col-span-3">';
            html += '<div class="pili-color-canvas-container relative h-full min-h-48 rounded-lg overflow-hidden cursor-crosshair border border-gray-200" style="background: linear-gradient(to bottom, transparent, black), linear-gradient(to right, white, red);">';
            html += '<div class="pili-color-cursor absolute w-4 h-4 border-2 border-white rounded-full shadow-lg pointer-events-none transform -translate-x-2 -translate-y-2"></div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="flex flex-col">';
            html += '<label class="block text-xs font-medium text-gray-700 mb-2">' + (config.messages.hue || (PILI.bag('color') && PILI.bag('color').messages && PILI.bag('color').messages.hue) || '色相') + '</label>';
            html += '<div class="relative h-32 lg:h-full min-h-32">';
            html += '<input type="range" class="pili-hue-slider w-full h-full rounded-lg appearance-none cursor-pointer" min="0" max="360" value="0" orient="vertical" style="background: linear-gradient(to bottom, #ff0000 0%, #ffff00 16.66%, #00ff00 33.33%, #00ffff 50%, #0000ff 66.66%, #ff00ff 83.33%, #ff0000 100%); writing-mode: bt-lr;">';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="flex-shrink-0 p-4 border-t border-gray-200 space-y-4">';
            if (config.palette && config.palette.length > 0) {
                html += '<div>';
                html += '<label class="block text-xs font-medium text-gray-700 mb-2">' + config.messages.paletteLabel + '</label>';
                html += '<div class="pili-color-palette grid grid-cols-10 gap-1.5">';
                config.palette.forEach(function(color) {
                    html += '<button type="button" class="pili-palette-color pili-palette-' + config.fieldId + ' w-6 h-6 rounded border border-gray-300 hover:scale-110 hover:shadow-md transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" style="background: ' + color + ';" data-color="' + color + '" data-field-id="' + config.fieldId + '" title="' + color + '"></button>';
                });
                html += '</div>';
                html += '</div>';
            }
            if (config.history) {
                html += '<div>';
                html += '<label class="block text-xs font-medium text-gray-700 mb-2">' + config.messages.historyLabel + '</label>';
                html += '<div class="pili-color-history grid grid-cols-10 gap-1.5 min-h-6">';
                html += '<div class="text-xs text-gray-500 col-span-10 text-center py-2">' + (config.messages.noHistory || (PILI.bag('color') && PILI.bag('color').messages && PILI.bag('color').messages.noHistory) || '暂无历史记录') + '</div>';
                html += '</div>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
            $container.html(html);
        },
        /**
         * 绑定事件
         */
        bindEvents: function($container, config) {
            var self = this;
            var fieldId = config.fieldId;
            $container.on('click', '.pili-color-preview', function() {
                self.toggleColorPicker(fieldId);
            });
            $container.on('input change', '.pili-color-value-input', function() {
                var value = $(this).val();
                self.setColor(fieldId, value);
            });
            $container.on('change', '.pili-color-format-select', function() {
                var format = $(this).val();
                self.changeFormat(fieldId, format);
            });
            if (config.alpha) {
                $container.on('input', '.pili-alpha-slider', function() {
                    var alpha = parseFloat($(this).val()) / 100;
                    self.setAlpha(fieldId, alpha);
                });
            }
            $container.on('input', '.pili-hue-slider', function() {
                var hue = parseInt($(this).val());
                self.setHue(fieldId, hue);
            });
            $container.on('mousedown', '.pili-color-canvas-container', function(e) {
                self.startColorSelection(fieldId, e);
            });
            $container.on('click', '.pili-palette-color', function() {
                var color = $(this).data('color');
                var targetFieldId = $(this).data('field-id');
                if (!targetFieldId || targetFieldId === fieldId) {
                    self.setColor(fieldId, color);
                }
            });
            $container.on('click', '.pili-history-color', function() {
                var color = $(this).data('color');
                self.setColor(fieldId, color);
            });
            self.bindToolbarEvents($container, config);
        },
        /**
         * 绑定工具栏事件
         */
        bindToolbarEvents: function($container, config) {
            var self = this;
            var fieldId = config.fieldId;
            var $field = $container.closest('.pili-color-field');
            $field.on('click', '.pili-color-eyedropper-btn', function() {
                self.openEyedropper(fieldId);
            });
            $field.on('click', '.pili-color-copy-btn', function() {
                self.copyColor(fieldId);
            });
            $field.on('click', '.pili-color-reset-btn', function() {
                self.resetColor(fieldId);
            });
        },
        /**
         * 显示加载状态
         */
        showLoadingState: function($container) {
            var loadingHtml = '<div class="pili-color-loading h-full flex items-center justify-center">' +
                '<div class="text-center">' +
                '<div class="animate-spin rounded-full h-8 w-8 border-2 border-blue-500 border-t-transparent mx-auto mb-2"></div>' +
                '<div class="text-sm text-gray-600">' + colorMsg('loading', '加载中…') + '</div>' +
                '</div>' +
                '</div>';
            $container.html(loadingHtml);
        },
        /**
         * 显示错误状态
         */
        showErrorState: function($container) {
            var errorHtml = '<div class="pili-color-error h-full flex items-center justify-center">' +
                '<div class="text-center text-red-600">' +
                '<svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
                '</svg>' +
                '<div class="text-sm">' + colorMsg('error', '错误') + '</div>' +
                '</div>' +
                '</div>';
            $container.html(errorHtml);
        },


        /**
         * 更新颜色输入框
         */
        updateColorInputs: function(fieldId, color) {
            var instance = this.instances[fieldId];
            if (!instance) return;

            var $input = instance.container.find('.pili-color-value-input');
            var $formatSelect = instance.container.find('.pili-color-format-select');
            var format = $formatSelect.val() || instance.config.format;

            var formattedColor = this.formatColor(color, format);
            $input.val(formattedColor);

            if (instance.config.alpha) {
                var $alphaSlider = instance.container.find('.pili-alpha-slider');
                $alphaSlider.val(Math.round(color.a * 100));
            }

            var $hueSlider = instance.container.find('.pili-hue-slider');
            $hueSlider.val(color.h);
        },

        /**
         * 更新颜色画布
         */
        updateColorCanvas: function(fieldId, color) {
            var instance = this.instances[fieldId];
            if (!instance) return;

            var $canvas = instance.container.find('.pili-color-canvas-container');
            var $cursor = instance.container.find('.pili-color-cursor');

            var hueColor = 'hsl(' + color.h + ', 100%, 50%)';
            $canvas.css('background', 'linear-gradient(to bottom, transparent, black), linear-gradient(to right, white, ' + hueColor + ')');

            var x = color.s;
            var y = 100 - color.l;
            $cursor.css({
                left: x + '%',
                top: y + '%'
            });
        },

        /**
         * 格式化颜色值
         */
        formatColor: function(color, format) {
            switch (format) {
                case 'hex':
                    return this.rgbToHex(color.r, color.g, color.b);
                case 'rgb':
                    return 'rgb(' + color.r + ', ' + color.g + ', ' + color.b + ')';
                case 'rgba':
                    return 'rgba(' + color.r + ', ' + color.g + ', ' + color.b + ', ' + color.a + ')';
                case 'hsl':
                    return 'hsl(' + color.h + ', ' + color.s + '%, ' + color.l + '%)';
                case 'hsla':
                    return 'hsla(' + color.h + ', ' + color.s + '%, ' + color.l + '%, ' + color.a + ')';
                default:
                    return this.rgbToHex(color.r, color.g, color.b);
            }
        },

        /**
         * RGB转HEX
         */
        rgbToHex: function(r, g, b) {
            return '#' + [r, g, b].map(function(x) {
                var hex = Math.round(x).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        },

        /**
         * 解析颜色值
         */
        parseColor: function(colorString) {
            if (!colorString) {
                return { r: 59, g: 130, b: 246, a: 1, h: 217, s: 91, l: 60 };
            }

            colorString = colorString.trim();

            if (colorString.startsWith('#')) {
                return this.parseHex(colorString);
            }

            if (colorString.startsWith('rgb')) {
                return this.parseRgb(colorString);
            }

            if (colorString.startsWith('hsl')) {
                return this.parseHsl(colorString);
            }

            return { r: 59, g: 130, b: 246, a: 1, h: 217, s: 91, l: 60 };
        },

        /**
         * 解析HEX颜色
         */
        parseHex: function(hex) {
            hex = hex.slice(1);

            if (hex.length === 3) {
                hex = hex.split('').map(function(char) { return char + char; }).join('');
            }

            var r = parseInt(hex.slice(0, 2), 16);
            var g = parseInt(hex.slice(2, 4), 16);
            var b = parseInt(hex.slice(4, 6), 16);
            var a = hex.length === 8 ? parseInt(hex.slice(6, 8), 16) / 255 : 1;

            var hsl = this.rgbToHsl(r, g, b);

            return {
                r: r, g: g, b: b, a: a,
                h: hsl.h, s: hsl.s, l: hsl.l
            };
        },

        /**
         * 解析RGB颜色
         */
        parseRgb: function(rgb) {
            var match = rgb.match(/rgba?\(([^)]+)\)/);
            if (!match) return this.parseColor('#3B82F6');

            var values = match[1].split(',').map(function(v) { return parseFloat(v.trim()); });
            var r = values[0] || 0;
            var g = values[1] || 0;
            var b = values[2] || 0;
            var a = values[3] !== undefined ? values[3] : 1;

            var hsl = this.rgbToHsl(r, g, b);

            return {
                r: r, g: g, b: b, a: a,
                h: hsl.h, s: hsl.s, l: hsl.l
            };
        },

        /**
         * RGB转HSL
         */
        rgbToHsl: function(r, g, b) {
            r /= 255;
            g /= 255;
            b /= 255;

            var max = Math.max(r, g, b);
            var min = Math.min(r, g, b);
            var h, s, l = (max + min) / 2;

            if (max === min) {
                h = s = 0;
            } else {
                var d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

                switch (max) {
                    case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                    case g: h = (b - r) / d + 2; break;
                    case b: h = (r - g) / d + 4; break;
                }
                h /= 6;
            }

            return {
                h: Math.round(h * 360),
                s: Math.round(s * 100),
                l: Math.round(l * 100)
            };
        },

        /**
         * 解析HSL颜色
         */
        parseHsl: function(hsl) {
            var match = hsl.match(/hsla?\(([^)]+)\)/);
            if (!match) return this.parseColor('#3B82F6');

            var values = match[1].split(',').map(function(v) { return parseFloat(v.trim().replace('%', '')); });
            var h = values[0] || 0;
            var s = values[1] || 0;
            var l = values[2] || 0;
            var a = values[3] !== undefined ? values[3] : 1;

            var rgb = this.hslToRgb(h, s, l);

            return {
                r: rgb.r, g: rgb.g, b: rgb.b, a: a,
                h: h, s: s, l: l
            };
        },

        /**
         * HSL转RGB
         */
        hslToRgb: function(h, s, l) {
            h /= 360;
            s /= 100;
            l /= 100;

            var r, g, b;

            if (s === 0) {
                r = g = b = l;
            } else {
                var hue2rgb = function(p, q, t) {
                    if (t < 0) t += 1;
                    if (t > 1) t -= 1;
                    if (t < 1/6) return p + (q - p) * 6 * t;
                    if (t < 1/2) return q;
                    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                    return p;
                };

                var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                var p = 2 * l - q;
                r = hue2rgb(p, q, h + 1/3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1/3);
            }

            return {
                r: Math.round(r * 255),
                g: Math.round(g * 255),
                b: Math.round(b * 255)
            };
        },

        /**
         * 添加到历史记录
         */
        addToHistory: function(color) {
            if (!color || this.colorHistory.indexOf(color) !== -1) return;

            this.colorHistory.unshift(color);
            if (this.colorHistory.length > this.maxHistoryItems) {
                this.colorHistory.pop();
            }

            this.updateHistoryUI();
        },

        /**
         * 更新历史记录UI
         */
        updateHistoryUI: function() {
            var self = this;

            $('.pili-color-history').each(function() {
                var $history = $(this);
                $history.empty();

                if (self.colorHistory.length === 0) {
                    $history.html('<div class="text-xs text-gray-500 col-span-10 text-center py-2">' + ((PILI.bag('color') && PILI.bag('color').messages && PILI.bag('color').messages.noHistory) || '暂无历史记录') + '</div>');
                    return;
                }

                self.colorHistory.forEach(function(color) {
                    var $colorBtn = $('<button type="button" class="pili-history-color w-6 h-6 rounded border border-gray-300 hover:scale-110 transition-transform duration-150" style="background: ' + color + ';" data-color="' + color + '" title="' + color + '"></button>');
                    $history.append($colorBtn);
                });
            });
        },

        /**
         * 开启取色器
         */
        openEyedropper: function(fieldId) {
            var self = this;

            if (!window.EyeDropper) {
                self.showMessage(colorMsg('eyedropperError', '取色失败'), 'error');
                return;
            }

            try {
                var eyeDropper = new window.EyeDropper();

                eyeDropper.open().then(function(result) {
                    self.setColor(fieldId, result.sRGBHex);
                    self.showMessage(colorMsg('colorCopied', '颜色已复制'), 'success');
                }).catch(function(error) {
                    if (error.name !== 'AbortError') {
                        self.showMessage(colorMsg('eyedropperError', '取色失败'), 'error');
                    }
                });
            } catch (error) {
                self.showMessage(colorMsg('eyedropperError', '取色失败'), 'error');
            }
        },

        /**
         * 复制颜色
         */
        copyColor: function(fieldId) {
            var self = this;
            var instance = this.instances[fieldId];
            if (!instance) return;

            var color = this.formatColor(instance.currentColor, instance.config.format);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(color).then(function() {
                    self.showMessage(colorMsg('colorCopied', '颜色已复制'), 'success');
                }).catch(function() {
                    self.fallbackCopy(color);
                });
            } else {
                self.fallbackCopy(color);
            }
        },

        /**
         * 降级复制方案
         */
        fallbackCopy: function(text) {
            try {
                var $temp = $('<textarea>');
                $temp.val(text).css({
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: '2em',
                    height: '2em',
                    padding: 0,
                    border: 'none',
                    outline: 'none',
                    boxShadow: 'none',
                    background: 'transparent'
                });
                $('body').append($temp);
                $temp[0].select();
                $temp[0].setSelectionRange(0, 99999);

                var successful = document.execCommand('copy');
                $temp.remove();

                if (successful) {
                    this.showMessage(colorMsg('colorCopied', '颜色已复制'), 'success');
                } else {
                    this.showMessage((PILI.bag('color') && PILI.bag('color').messages && PILI.bag('color').messages.copyFailed) || '复制失败，请手动复制', 'error');
                }
            } catch (err) {
                this.showMessage((PILI.bag('color') && PILI.bag('color').messages && PILI.bag('color').messages.copyUnavailable) || '复制功能不可用', 'error');
            }
        },

        /**
         * 重置颜色
         */
        resetColor: function(fieldId) {
            var instance = this.instances[fieldId];
            if (!instance) return;

            var defaultColor = instance.config.value || '#3B82F6';
            this.setColor(fieldId, defaultColor);
        },

        /**
         * 显示消息
         */
        showMessage: function(message, type) {
            var $message = $('<div class="pili-color-message fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white text-sm transition-all duration-300 transform translate-x-full">')
                .addClass(type === 'error' ? 'bg-red-500' : 'bg-green-500')
                .text(message);

            $('body').append($message);

            setTimeout(function() {
                $message.removeClass('translate-x-full');
            }, 10);

            setTimeout(function() {
                $message.addClass('translate-x-full');
                setTimeout(function() {
                    $message.remove();
                }, 300);
            }, 3000);
        },

        /**
         * 键盘快捷键处理
         */
        handleKeyboardShortcuts: function(e) {
            if (e.ctrlKey && e.key === 'c') {
                var activeField = $('.pili-color-field.active').data('field-id');
                if (activeField) {
                    e.preventDefault();
                    this.copyColor(activeField);
                }
            }

            if (e.ctrlKey && e.key === 'v') {
                var activeField = $('.pili-color-field.active').data('field-id');
                if (activeField && navigator.clipboard) {
                    e.preventDefault();
                    navigator.clipboard.readText().then(function(text) {
                        if (XunColorPicker.isValidColor(text)) {
                            XunColorPicker.setColor(activeField, text);
                        }
                    });
                }
            }
        },

        /**
         * 验证颜色值
         */
        isValidColor: function(color) {
            if (!color) return false;

            if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3}|[A-Fa-f0-9]{8})$/.test(color)) {
                return true;
            }
            if (/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[0-1]?(?:\.\d+)?)?\s*\)$/.test(color)) {
                return true;
            }

            if (/^hsla?\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%\s*(?:,\s*[0-1]?(?:\.\d+)?)?\s*\)$/.test(color)) {
                return true;
            }

            return false;
        }
    };

    XunColorPicker.init();

    PILI.ColorPicker = XunColorPicker;
    PILI.registerBoot('color', function ($root) {
        XunColorPicker.initColorPickers($root);
    });

})(jQuery);
