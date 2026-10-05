/**
 * Background Field JavaScript
 *
 * 处理背景字段的交互功能，包括媒体上传器和颜色选择器同步
 *
 * @package Xun Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    var eventsBound = false;
    var strings = (PILI.bag('background') && PILI.bag('background').strings) || {};

    function str(key, fallback) {
        return strings[key] || fallback;
    }

    /**
     * Background Field 类
     */
    var XunBackgroundField = {

        /**
         * 一次性绑定全局事件
         */
        init: function() {
            if (!eventsBound) {
                this.bindEvents();
                eventsBound = true;
            }
        },

        /**
         * 初始化背景字段中的颜色选择器
         *
         * @param {jQuery} [$root]
         */
        initBackgroundColorPickers: function($root) {
            var $scope = $root && $root.length ? $root : $(document);
            $scope.find('.pili-color-config').each(function() {
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
         * 分区 / repeater 注入后初始化可见 background 字段
         *
         * @param {jQuery} [$root]
         */
        bootFields: function($root) {
            var self = this;
            var $scope = $root && $root.length ? $root : $(document);

            $scope.find('input[name*="background-image"]').each(function() {
                if ($(this).hasClass('pili-media-url') || $(this).attr('name').indexOf('background-image]') !== -1) {
                    self.toggleBackgroundAttributes($(this));
                }
            });

            setTimeout(function() {
                if (PILI.bootFn('media')) {
                    PILI.boot('media', $scope);
                }
                if (PILI.bootFn('color')) {
                    PILI.boot('color', $scope);
                }
                if (PILI.bootFn('select')) {
                    PILI.boot('select', $scope);
                }
                if (PILI.ColorPicker) {
                    self.initBackgroundColorPickers($scope);
                }
            }, 50);
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;

            $(document).on('click', '.pili-background-media-button', function(e) {
                e.preventDefault();
                self.openMediaUploader($(this));
            });

            $(document).on('change', 'input[name*="background-image"]', function() {
                if ($(this).hasClass('pili-media-url') || $(this).attr('name').indexOf('[background-image]') !== -1) {
                    self.toggleBackgroundAttributes($(this));
                }
            });
        },

        /**
         * 打开WordPress媒体上传器
         */
        openMediaUploader: function($button) {
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert(str('mediaUnavailable', '媒体库不可用，请刷新页面重试'));
                return;
            }

            var $container = $button.closest('div');
            var $hiddenInput = $container.find('.pili-background-media-url');
            var $preview = $container.find('img');

            var mediaUploader = wp.media({
                title: str('selectImageTitle', '选择背景图片'),
                button: {
                    text: str('selectImageBtn', '选择图片')
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();

                if ($hiddenInput.length) {
                    $hiddenInput.val(attachment.url).trigger('change');
                }

                if ($preview.length === 0) {
                    $preview = $('<img class="h-16 w-16 object-cover rounded-md border border-gray-300 ml-3">');
                    $container.append($preview);
                }
                $preview.attr('src', attachment.url);

                $button.html(
                    '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>' +
                    '</svg>' + str('replaceImage', 'Change image')
                );

                if ($container.find('.pili-remove-media').length === 0) {
                    var $removeBtn = $('<button type="button" class="pili-remove-media ml-2 inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none">' + str('remove', '移除') + '</button>');
                    $container.append($removeBtn);
                }
            });

            mediaUploader.open();
        },

        /**
         * 移除背景图片
         */
        removeBackgroundImage: function($button) {
            var $container = $button.closest('.pili-media-field, .space-y-6');
            var $hiddenInput = $container.find('.pili-media-url, input[name*="[background-image]"]').first();
            var $preview = $container.find('img');
            var $mediaButton = $container.find('.pili-media-button');

            $hiddenInput.val('').trigger('change');

            $preview.remove();

            $button.remove();

            $mediaButton.html(
                '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>' +
                '</svg>' + str('selectImageBtn', '选择图片')
            );
        },

        /**
         * 切换背景属性显示/隐藏
         */
        toggleBackgroundAttributes: function($input) {
            var $container = $input.closest('.space-y-6');
            var $attributes = $container.find('.pili-bg-attributes');
            var hasImage = $input.val().trim() !== '';

            if (hasImage) {
                $attributes.show().removeClass('opacity-50');
            } else {
                $attributes.hide().addClass('opacity-50');
            }
        }
    };

    $(document).on('click', '.pili-remove-media', function(e) {
        e.preventDefault();
        XunBackgroundField.removeBackgroundImage($(this));
    });

    PILI.registerBoot('background', function($root) {
        XunBackgroundField.init();
        XunBackgroundField.bootFields($root);
    });

    $(document).ready(function() {
        XunBackgroundField.init();
        XunBackgroundField.bootFields();
    });

    $(document).on('xun:field:added', function(e, $container) {
        XunBackgroundField.bootFields($container && $container.length ? $container : undefined);
    });

    $(document).on('xun:field:loaded', function() {
        XunBackgroundField.bootFields();
    });

    PILI.BackgroundField = XunBackgroundField;

})(jQuery);
