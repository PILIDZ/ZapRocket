/**
 * Border Field JavaScript
 * 
 * 处理边框字段的交互功能，包括实时预览和颜色选择器
 * 
 * @package Xun Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    var borderStrings = (PILI.bag('border') && PILI.bag('border').strings) || {};
    var defaultStyleNames = borderStrings.styles || {};

    function str(key, fallback) {
        return borderStrings[key] || fallback;
    }

    /**
     * Border Field 类
     */
    var XunBorderField = {

        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.updateAllPreviews();

            if (PILI.ColorPicker) {
                PILI.ColorPicker.init();
                this.initBorderColorPickers();
            }
        },

        /**
         * 初始化边框字段中的颜色选择器
         *
         * @param {jQuery} [$root]
         */
        initBorderColorPickers: function($root) {
            var $scope = $root && $root.length ? $root : $(document);
            $scope.find('.pili-border-field .pili-color-config').each(function() {
                try {
                    var config = JSON.parse($(this).text());
                    if (config && config.fieldId && PILI.ColorPicker) {
                        PILI.ColorPicker.createColorPicker(config);
                    }
                } catch (e) {
                }
            });
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;

            $(document).on('input change', '.pili-border-field input[type="number"]', function() {
                self.updatePreview($(this).closest('.pili-border-field'));
            });

            $(document).on('change', '.pili-border-field select', function() {
                self.updatePreview($(this).closest('.pili-border-field'));
            });

            $(document).on('input change', '.pili-border-field .pili-color-input', function() {
                self.updatePreview($(this).closest('.pili-border-field'));
            });

            $(document).on('click', '.pili-border-field .pili-color-field .w-12.h-12, .pili-border-field .pili-color-field .w-16.h-16', function() {
                var $preview = $(this);
                var previewId = $preview.attr('id');

                if (PILI.ColorPicker && previewId) {
                    var fieldId = previewId.replace('-preview', '');
                    PILI.ColorPicker.openColorPicker(fieldId);
                }
            });
        },

        /**
         * 更新所有边框字段的预览
         *
         * @param {jQuery} [$root]
         */
        updateAllPreviews: function($root) {
            var self = this;
            var $scope = $root && $root.length ? $root : $(document);
            $scope.find('.pili-border-field').each(function() {
                self.updatePreview($(this));
            });
        },

        /**
         * 更新边框预览
         * 
         * @param {jQuery} $field 字段容器
         */
        updatePreview: function($field) {
            var $preview = $field.find('.pili-border-preview');
            if ($preview.length === 0) return;

            var borderData = this.getBorderData($field);
            var styles = this.buildBorderStyles(borderData);
            $preview.attr('style', styles);
            var previewText = this.generatePreviewText(borderData);
            $preview.text(previewText);
        },

        /**
         * 获取边框数据
         * 
         * @param {jQuery} $field 字段容器
         * @return {Object} 边框数据对象
         */
        getBorderData: function($field) {
            var data = {
                top: '',
                right: '',
                bottom: '',
                left: '',
                all: '',
                style: 'solid',
                color: '',
                unit: 'px'
            };

            $field.find('input[type="number"]').each(function() {
                var $input = $(this);
                var name = $input.attr('name');
                var value = $input.val();
                
                if (name && value) {
                    if (name.includes('[top]')) {
                        data.top = value;
                    } else if (name.includes('[right]')) {
                        data.right = value;
                    } else if (name.includes('[bottom]')) {
                        data.bottom = value;
                    } else if (name.includes('[left]')) {
                        data.left = value;
                    } else if (name.includes('[all]')) {
                        data.all = value;
                    }
                }
            });

            var $styleSelect = $field.find('select');
            if ($styleSelect.length > 0) {
                data.style = $styleSelect.val() || 'solid';
            }

            var $colorInput = $field.find('.pili-color-input');
            if ($colorInput.length > 0) {
                data.color = $colorInput.val() || '';
            }

            return data;
        },

        /**
         * 构建边框CSS样式
         * 
         * @param {Object} data 边框数据
         * @return {String} CSS样式字符串
         */
        buildBorderStyles: function(data) {
            var styles = [];
            var unit = data.unit || 'px';
            var color = data.color || '#d1d5db';
            var style = data.style || 'solid';

            if (data.all && data.all !== '') {
                styles.push('border: ' + data.all + unit + ' ' + style + ' ' + color);
            } else {
                if (data.top && data.top !== '') {
                    styles.push('border-top: ' + data.top + unit + ' ' + style + ' ' + color);
                }
                if (data.right && data.right !== '') {
                    styles.push('border-right: ' + data.right + unit + ' ' + style + ' ' + color);
                }
                if (data.bottom && data.bottom !== '') {
                    styles.push('border-bottom: ' + data.bottom + unit + ' ' + style + ' ' + color);
                }
                if (data.left && data.left !== '') {
                    styles.push('border-left: ' + data.left + unit + ' ' + style + ' ' + color);
                }
            }

            return styles.join('; ');
        },

        /**
         * 生成预览文本
         * 
         * @param {Object} data 边框数据
         * @return {String} 预览文本
         */
        generatePreviewText: function(data) {
            var parts = [];
            var unit = data.unit || 'px';

            var styleNames = defaultStyleNames;

            if (data.all && data.all !== '') {
                var styleName = styleNames[data.style] || data.style || styleNames.solid || '';
                parts.push(data.all + unit + ' ' + styleName);
            } else {
                var widths = [];
                if (data.top && data.top !== '') widths.push(str('widthTop', '上') + ':' + data.top);
                if (data.right && data.right !== '') widths.push(str('widthRight', '右') + ':' + data.right);
                if (data.bottom && data.bottom !== '') widths.push(str('widthBottom', '下') + ':' + data.bottom);
                if (data.left && data.left !== '') widths.push(str('widthLeft', '左') + ':' + data.left);

                if (widths.length > 0) {
                    parts.push(widths.join(' ') + unit);
                    if (data.style && data.style !== 'solid') {
                        var styleName = styleNames[data.style] || data.style;
                        parts.push(styleName);
                    }
                }
            }

            if (data.color) {
                parts.push(data.color);
            }

            return parts.length > 0 ? parts.join(' | ') : str('previewDefault', '边框预览效果');
        },

        /**
         * 重新初始化字段（用于动态添加的字段）
         *
         * @param {jQuery} [$root]
         */
        reinit: function($root) {
            if (PILI.bootFn('color')) {
                PILI.boot('color', $root);
            }
            this.initBorderColorPickers($root);
            this.updateAllPreviews($root);
        }
    };

    PILI.registerBoot('border', function($root) {
        XunBorderField.reinit($root);
    });

    /**
     * 文档就绪时初始化
     */
    $(document).ready(function() {
        XunBorderField.init();
    });

    $(document).on('xun:field:added', function(e, $container) {
        XunBorderField.reinit($container && $container.length ? $container : undefined);
    });

    $(document).on('xun:field:loaded', function() {
        XunBorderField.reinit();
    });

    /**
     * 暴露到全局，供其他脚本调用
     */
    PILI.BorderField = XunBorderField;

})(jQuery);
