/**
 * PILI Framework 图标选择字段脚本
 *
 * Repeater 多行须按「容器 / input name」区分实例，勿仅用 fieldId 去重（见 fields/icon/icon.php 注释）。
 *
 * @package PILI Framework
 * @since   1.0
 */

(function($) {
    'use strict';

    var XunIconPicker = {

        instances: {},

        init: function() {
            var self = this;

            $(document).ready(function() {
                self.initIconPickers();
            });

            $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
                if ($container && $container.length) {
                    self.initIconPickers($container);
                } else {
                    self.initIconPickers();
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
                            if ($node.hasClass('pili-icon-field') || $node.find('.pili-icon-field').length > 0) {
                                self.initIconPickers($node);
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
                if (e.keyCode === 27) {
                    self.closeIconPicker();
                }
            });

            // Repeater 增行后若 init 竞态漏绑，点击时兜底初始化再打开弹层。
            $(document).on('click', '.pili-icon-field .pili-icon-select-btn, .pili-icon-field .pili-icon-preview, .pili-icon-field .pili-icon-placeholder', function(e) {
                var $container = $(this).closest('.pili-icon-field');
                if (!$container.length || $container.closest('.pili-repeater-template').length) {
                    return;
                }
                var wasReady = !!$container.data('xunIconInitialized');
                var key = self.ensureIconFieldReady($container);
                if (!key || !self.instances[key]) {
                    return;
                }
                if (!wasReady) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.openIconPicker(key);
                }
            });
        },

        /**
         * 释放实例（Repeater 克隆/改 index 时避免 instances 键占用导致新行无法绑定）。
         */
        releaseInstance: function(instanceKey, $container) {
            if (!instanceKey) {
                return;
            }
            var existing = this.instances[instanceKey];
            if (existing && existing.field && existing.field.length) {
                existing.field.off('.xunIconPicker');
            }
            if ($container && $container.length) {
                $container.removeData('xunIconInitialized');
            }
            delete this.instances[instanceKey];
        },

        /**
         * 确保单个 icon 容器已绑定；返回 instanceKey。
         */
        ensureIconFieldReady: function($container) {
            if (!$container || !$container.length) {
                return null;
            }
            if ($container.closest('.pili-repeater-template').length) {
                return null;
            }
            this.initIconPickers($container);
            var $input = $container.find('.pili-icon-input').first();
            var $config = $container.find('script.pili-icon-config, .pili-icon-config').first();
            if (!$input.length || !$config.length) {
                return null;
            }
            try {
                var config = JSON.parse($config.first().text());
                return this.getInstanceKey(config, $input);
            } catch (err) {
                return null;
            }
        },

        /**
         * 实例键：优先 input name（Repeater 每行唯一），回退 fieldId。
         */
        getInstanceKey: function(config, $input, $container) {
            var name = $input && $input.length ? $input.attr('name') : '';
            if (name) {
                return 'name:' + name;
            }
            var fid = config && config.fieldId ? config.fieldId : '';
            var domId = $container && $container.length ? ($container.attr('data-field-id') || '') : '';
            if (fid && domId) {
                return 'fid:' + fid + '@' + domId;
            }
            return fid ? 'fid:' + fid : '';
        },

        /**
         * @param {jQuery} [$root] 限定扫描根节点
         */
        initIconPickers: function($root) {
            var self = this;
            var $scope = ($root && $root.length) ? $root : $(document);

            $scope.find('.pili-icon-field').each(function() {
                var $container = $(this);

                if ($container.closest('.pili-repeater-template').length) {
                    return;
                }

                var $config = $container.find('script.pili-icon-config, .pili-icon-config');
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

                    var $input = $container.find('.pili-icon-input').first();
                    if ($input.length === 0) {
                        return;
                    }

                    var instanceKey = self.getInstanceKey(config, $input, $container);
                    if (!instanceKey) {
                        return;
                    }

                    var existing = self.instances[instanceKey];
                    if (existing && existing.field && existing.field.length) {
                        if (existing.field[0] === $container[0] && $container.data('xunIconInitialized')) {
                            return;
                        }
                        if (existing.field[0] !== $container[0]) {
                            self.releaseInstance(instanceKey, existing.field);
                        }
                    }

                    if ($container.data('xunIconInitialized')) {
                        $container.removeData('xunIconInitialized');
                    }

                    self.createIconPicker(config, $container, instanceKey);
                    $container.data('xunIconInitialized', true);
                } catch (error) {
                    if (window.console && console.warn) {
                        console.warn('pili-field-icon: JSON parse failed', error);
                    }
                }
            });
        },

        createIconPicker: function(config, $container, instanceKey) {
            var self = this;
            var $field = ($container && $container.length) ? $container : $();
            var $input = $field.find('.pili-icon-input').first();

            if ($field.length === 0 || $input.length === 0 || !instanceKey) {
                return;
            }

            if (!Array.isArray(config.availableIcons) || config.availableIcons.length === 0) {
                if (window.pilidocMenuIconPicker && Array.isArray(window.pilidocMenuIconPicker.availableIcons) && window.pilidocMenuIconPicker.availableIcons.length > 0) {
                    config.availableIcons = window.pilidocMenuIconPicker.availableIcons;
                } else {
                    config.availableIcons = [];
                }
            }
            if ((!config.messages || typeof config.messages !== 'object' || !config.messages.selectIcon) && window.pilidocMenuIconPicker && window.pilidocMenuIconPicker.messages) {
                config.messages = $.extend({}, window.pilidocMenuIconPicker.messages, config.messages || {});
            }
            if (!config.messages || typeof config.messages !== 'object') {
                config.messages = {};
            }

            self.instances[instanceKey] = {
                key: instanceKey,
                field: $field,
                input: $input,
                config: config,
                currentIcon: config.currentValue
            };

            self.bindBasicEvents($field, instanceKey);

            if (config.currentValue) {
                self.updateLivePreview(instanceKey, config.currentValue);
            }
        },

        bindBasicEvents: function($field, instanceKey) {
            var self = this;

            $field.on('click.xunIconPicker', '.pili-icon-select-btn', function(e) {
                e.preventDefault();
                self.openIconPicker(instanceKey);
            });

            $field.on('click.xunIconPicker', '.pili-icon-clear-btn', function(e) {
                e.preventDefault();
                self.clearIcon(instanceKey);
            });

            $field.on('click.xunIconPicker', '.pili-icon-preview, .pili-icon-placeholder', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openIconPicker(instanceKey);
            });
        },

        openIconPicker: function(instanceKey) {
            var self = this;
            var instance = this.instances[instanceKey];
            if (!instance) {
                return;
            }

            if ($('.pili-icon-picker-modal').length > 0) {
                $('.pili-icon-picker-modal').remove();
            }

            var currentIcon = instance.input.val() || '';
            var modalHtml = this.createIconPickerModal(instanceKey, currentIcon);
            var $modal = $(modalHtml);

            $modal.css({
                opacity: 0,
                transition: 'opacity 0.3s ease-in-out'
            });
            $('body').append($modal);

            this.bindModalEvents(instanceKey);

            setTimeout(function() {
                $modal.css({ opacity: 1 });
                $modal.find('.pili-icon-picker-dialog').removeClass('opacity-0 translate-y-4 scale-95')
                    .addClass('opacity-100 translate-y-0 scale-100');
            }, 10);
        },

        closeIconPicker: function() {
            var $modal = $('.pili-icon-picker-modal');
            var $dialog = $modal.find('.pili-icon-picker-dialog');

            $modal.css({ opacity: 0 });
            $dialog.removeClass('opacity-100 translate-y-0 scale-100')
                .addClass('opacity-0 translate-y-4 scale-95');

            setTimeout(function() {
                $modal.remove();
            }, 200);
        },

        createIconPickerModal: function(instanceKey, currentIcon) {
            var instance = this.instances[instanceKey];
            var config = instance.config;
            var globalMsg = PILI.bag('icon') || {};
            var msg = $.extend({}, globalMsg, config.messages || {});
            var icons = Array.isArray(config.availableIcons) ? config.availableIcons : [];

            var escAttr = function(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/'/g, '&#39;');
            };

            var html = '<div class="pili-icon-picker-modal fixed inset-0 flex items-center justify-center z-50 p-4" style="z-index: 9999; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); background: rgba(255, 255, 255, 0.1); opacity: 0; transition: opacity 0.3s ease-out;">';
            html += '<div class="pili-icon-picker-dialog bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl w-full max-w-lg border border-white/20 transform transition-all duration-300 ease-out opacity-0 translate-y-4 scale-95 max-h-[90vh] overflow-y-auto" style="backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);">';

            html += '<div class="flex items-center justify-between p-3 sm:p-4 border-b border-white/20">';
            html += '<h3 class="text-base sm:text-lg font-semibold text-gray-800">' + escAttr(msg.selectIcon || '选择图标') + '</h3>';
            html += '<button type="button" class="pili-icon-picker-close text-gray-500 hover:text-gray-700 hover:bg-white/50 rounded-full p-2 transition-all duration-200">';
            html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
            html += '</svg>';
            html += '</button>';
            html += '</div>';

            html += '<div class="p-3 sm:p-4">';

            if (config.showSearch) {
                html += '<div class="mb-4">';
                html += '<label class="block text-xs font-medium text-gray-600 mb-1">' + escAttr(msg.searchIconsLabel || '搜索图标') + '</label>';
                html += '<input type="text" class="pili-icon-search-input w-full px-3 py-2 border border-white/30 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400/50 text-sm bg-white/50 backdrop-blur-sm" placeholder="' + escAttr(msg.searchPlaceholder || '') + '">';
                html += '</div>';
            }

            html += '<div class="mb-4">';
            html += '<label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1 sm:mb-2">' + escAttr(msg.selectIcon || '选择图标') + '</label>';
            html += '<div class="pili-icon-grid grid grid-cols-6 sm:grid-cols-8 gap-2 max-h-64 overflow-y-auto border border-white/20 rounded-lg p-2 bg-white/30">';

            icons.forEach(function(iconName) {
                var selectedClass = (currentIcon === iconName) ? ' selected' : '';
                var safeCls = escAttr(iconName);
                html += '<button type="button" class="pili-icon-item flex flex-col items-center justify-center p-2 rounded-lg border border-white/30 hover:bg-white/50 hover:scale-105 transition-all duration-200 shadow-sm' + selectedClass + '" data-icon="' + safeCls + '" title="' + safeCls + '">';
                html += '<i class="' + iconName + ' pili-ri-icon text-xl text-gray-700" aria-hidden="true"></i>';
                html += '<span class="text-xs text-gray-600 mt-1 truncate w-full text-center">' + escAttr(iconName) + '</span>';
                html += '</button>';
            });

            html += '</div>';
            html += '</div>';
            html += '</div>';

            html += '<div class="flex items-center justify-end gap-2 sm:gap-3 p-3 sm:p-4 border-t border-white/20">';
            html += '<button type="button" class="pili-icon-picker-cancel px-3 py-2 sm:px-4 text-xs sm:text-sm font-medium text-gray-700 bg-white/50 backdrop-blur-sm border border-white/30 rounded-lg hover:bg-white/70 focus:outline-none focus:ring-2 focus:ring-blue-400/50 transition-all duration-200">' + escAttr(msg.cancel || '取消') + '</button>';
            html += '<button type="button" class="pili-icon-picker-confirm px-3 py-2 sm:px-4 text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 border border-transparent rounded-lg hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400/50 shadow-lg transition-all duration-200">' + escAttr(msg.confirm || '确认') + '</button>';
            html += '</div>';

            html += '</div>';
            html += '</div>';

            return html;
        },

        bindModalEvents: function(instanceKey) {
            var self = this;
            var $modal = $('.pili-icon-picker-modal');

            $modal.on('click', '.pili-icon-picker-close, .pili-icon-picker-cancel', function() {
                self.closeIconPicker();
            });

            $modal.on('click', function(e) {
                if (e.target === this) {
                    self.closeIconPicker();
                }
            });

            $modal.on('click', '.pili-icon-picker-confirm', function() {
                var $sel = $modal.find('.pili-icon-item.selected');
                var selectedIcon = $sel.attr('data-icon') || $sel.data('icon');

                if (selectedIcon) {
                    self.setIconValue(instanceKey, selectedIcon);
                }
                self.closeIconPicker();
            });

            $modal.on('click', '.pili-icon-item', function() {
                $(this).siblings().removeClass('selected');
                $(this).addClass('selected');
            });

            $modal.on('input', '.pili-icon-search-input', function() {
                self.filterIcons($modal, $(this).val().toLowerCase());
            });

            setTimeout(function() {
                $modal.find('.pili-icon-search-input').focus();
            }, 100);
        },

        setIconValue: function(instanceKey, iconName) {
            var instance = this.instances[instanceKey];
            if (!instance) {
                return;
            }

            instance.input.val(iconName).trigger('change').trigger('xun:icon:change');
            this.updateIconDisplay(instanceKey, iconName);
            instance.currentIcon = iconName;
        },

        updateIconDisplay: function(instanceKey, iconName) {
            var instance = this.instances[instanceKey];
            if (!instance) {
                return;
            }

            var $current = instance.field.find('.pili-icon-current');
            var globalMsg = PILI.bag('icon') || {};
            var msg = $.extend({}, globalMsg, instance.config.messages || {});
            var placeholder = instance.config.placeholder || msg.searchPlaceholder || '选择图标…';
            var selectLabel = msg.selectIcon || '选择图标';
            var clearLabel = msg.clear || msg.clearIcon || '清空';

            if (iconName) {
                var iconHtml = '<div class="pili-icon-preview">';
                iconHtml += '<i class="' + iconName + ' pili-ri-icon w-6 h-6" style="font-size: ' + (instance.config.size || 24) + 'px;" aria-hidden="true"></i>';
                iconHtml += '<span class="pili-icon-name">' + iconName + '</span>';
                iconHtml += '</div>';
                iconHtml += '<button type="button" class="pili-icon-select-btn">' + selectLabel + '</button>';
                iconHtml += '<button type="button" class="pili-icon-clear-btn">' + clearLabel + '</button>';

                $current.html(iconHtml);
                this.updateLivePreview(instanceKey, iconName);
            } else {
                var placeholderHtml = '<div class="pili-icon-placeholder">' + placeholder + '</div>';
                placeholderHtml += '<button type="button" class="pili-icon-select-btn">' + selectLabel + '</button>';

                $current.html(placeholderHtml);
                this.updateLivePreview(instanceKey, '');
            }
        },

        updateLivePreview: function(instanceKey, iconName) {
            var instance = this.instances[instanceKey];
            if (!instance || !instance.config.showPreview) {
                return;
            }

            var $field = instance.field;
            var $preview = $field.find('.pili-icon-live-preview');

            if (iconName) {
                if ($preview.length === 0) {
                    var previewHtml = '<div class="pili-icon-live-preview">';
                    previewHtml += '<h4>' + ((PILI.bag('icon') && PILI.bag('icon').previewEffect) || '预览效果：') + '</h4>';
                    previewHtml += '<div class="pili-icon-preview-sizes"></div>';
                    previewHtml += '</div>';
                    $field.append(previewHtml);
                    $preview = $field.find('.pili-icon-live-preview');
                }

                var $previewSizes = $preview.find('.pili-icon-preview-sizes');
                var previewSizes = ['16', '20', '24'];
                var sizesHtml = '';

                previewSizes.forEach(function(size) {
                    sizesHtml += '<div class="pili-icon-preview-item">';
                    sizesHtml += '<span class="pili-icon-preview-label">' + size + 'px:</span>';
                    sizesHtml += '<i class="' + iconName + ' pili-ri-icon inline-block" style="font-size: ' + size + 'px;" aria-hidden="true"></i>';
                    sizesHtml += '</div>';
                });

                $previewSizes.html(sizesHtml);
                $preview.show();
            } else if ($preview.length) {
                $preview.hide();
            }
        },

        clearIcon: function(instanceKey) {
            this.setIconValue(instanceKey, '');
        },

        filterIcons: function($modal, keyword) {
            var $items = $modal.find('.pili-icon-item');
            var hasResults = false;

            if (keyword === '') {
                $items.show();
                hasResults = true;
            } else {
                $items.each(function() {
                    var $item = $(this);
                    var iconName = String($item.attr('data-icon') || '').toLowerCase();

                    if (iconName.indexOf(keyword) !== -1) {
                        $item.show();
                        hasResults = true;
                    } else {
                        $item.hide();
                    }
                });
            }

            var $noResults = $modal.find('.pili-icon-no-results');
            if (!hasResults) {
                if ($noResults.length === 0) {
                    $modal.find('.pili-icon-grid').append('<div class="pili-icon-no-results col-span-full text-center text-gray-500 py-4">' + (msg.noIconsFound || msg.noResults || '未找到匹配的图标') + '</div>');
                }
            } else {
                $noResults.remove();
            }
        }
    };

    XunIconPicker.init();

    PILI.IconPicker = XunIconPicker;
    PILI.registerBoot('icon', function ($root) {
        XunIconPicker.initIconPickers($root);
    });

})(jQuery);
