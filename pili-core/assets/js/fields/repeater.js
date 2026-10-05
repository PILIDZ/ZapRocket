/**
 * XUN Repeater Field JavaScript
 * 
 * 现代化的重复器字段交互逻辑
 * 支持拖拽排序、折叠展开、批量操作、键盘导航等高级功能
 * 
 * @since 1.0.0
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Repeater Field 类
     */
    var XunRepeaterField = {

        /**
         * 当前 repeater 字段的直接列表容器（不含嵌套 repeater 内的 .pili-repeater-items）
         */
        getItemsContainer: function($field) {
            return $field.children('.pili-repeater-items').first();
        },

        /**
         * 当前 repeater 字段的模板节点（不含嵌套 repeater 内的模板）
         */
        getTemplateRoot: function($field) {
            return $field.children('.pili-repeater-template').first();
        },

        /**
         * 当前 repeater 字段的直接子行（不含嵌套 repeater 内的行）
         */
        getDirectItems: function($field) {
            return this.getItemsContainer($field).children('.pili-repeater-item');
        },

        /**
         * 单行 repeater 的直接内容区（不含嵌套 repeater 子行的 content）
         */
        getItemContent: function($item) {
            return $item.children('.pili-repeater-item-content').first();
        },

        /**
         * 单行 repeater 的直接头部
         */
        getItemHeader: function($item) {
            return $item.children('.pili-repeater-item-header').first();
        },

        /**
         * 行的直接所属 repeater（不含嵌套 repeater 误判）。
         */
        getOwningRepeaterField: function($item) {
            if (!$item || !$item.length) {
                return $();
            }
            return $item.parent('.pili-repeater-items').parent('.pili-repeater-field').first();
        },

        /**
         * 读取行头配置（data-title-config；兼容 preview_field）。
         */
        getTitleConfig: function($field) {
            var i18n = PILI.bag('repeater') || {};
            var defaults = {
                fields: [],
                prefix: i18n.item || '项目',
                showIndex: true,
                showDisabled: false,
                separator: ' · ',
                fallback: i18n.item || '项目',
                maxLength: 100
            };
            if (!$field || !$field.length) {
                return defaults;
            }
            var raw = $field.attr('data-title-config');
            if (raw) {
                try {
                    var parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        return $.extend({}, defaults, parsed);
                    }
                } catch (err) {
                }
            }
            var previewField = $field.attr('data-preview-field') || $field.data('preview-field') || '';
            if (previewField) {
                return $.extend({}, defaults, {
                    fields: [previewField],
                    showIndex: false,
                    showDisabled: false
                });
            }
            return $.extend({}, defaults, {
                fields: this.autoDetectTitleFieldIds($field),
                showDisabled: this.itemHasEnabledSwitch($field)
            });
        },

        /**
         * DOM 自动探测适合做行头的直接子字段 id。
         */
        autoDetectTitleFieldIds: function($field) {
            var eligible = { text: 1, textarea: 1, select: 1, number: 1, radio: 1, button: 1 };
            var skip = { switch: 1, repeater: 1, accordion: 1, subheading: 1, content: 1, callback: 1, notice: 1, fieldset: 1, tabbed: 1, group: 1, media: 1, gallery: 1, icon: 1, color: 1 };
            var ids = [];
            var $sample = this.getDirectItems($field).first();
            if (!$sample.length) {
                $sample = this.getTemplateRoot($field).find('> .pili-repeater-item').first();
            }
            if (!$sample.length) {
                return ids;
            }
            this.getDirectSubfieldScopes($sample).each(function() {
                if (ids.length >= 2) {
                    return false;
                }
                var type = ($(this).attr('data-field-type') || '').toLowerCase();
                var fid = $(this).attr('data-field-id') || '';
                if (!fid || skip[type]) {
                    return;
                }
                if (eligible[type]) {
                    ids.push(fid);
                }
            });
            return ids;
        },

        /**
         * 行内直接子字段容器（不含嵌套 repeater 内字段）。
         */
        getDirectSubfieldScopes: function($item) {
            return this.getItemContent($item).children('.space-y-2[data-field-id]');
        },

        itemHasEnabledSwitch: function($field) {
            var found = false;
            var $sample = this.getDirectItems($field).first();
            if (!$sample.length) {
                return false;
            }
            this.getDirectSubfieldScopes($sample).each(function() {
                if (($(this).attr('data-field-id') || '') === 'enabled' &&
                    ($(this).attr('data-field-type') || '') === 'switch') {
                    found = true;
                    return false;
                }
            });
            return found;
        },

        getSubfieldScope: function($item, fieldId) {
            return this.getDirectSubfieldScopes($item).filter('[data-field-id="' + fieldId + '"]').first();
        },

        resolveSubfieldDisplayValue: function($item, fieldId) {
            var $scope = this.getSubfieldScope($item, fieldId);
            if (!$scope.length) {
                var $fallback = $item.find('[name*="[' + fieldId + ']"]').first();
                return $fallback.length ? String($fallback.val() || '').trim() : '';
            }
            var type = ($scope.attr('data-field-type') || '').toLowerCase();
            if (type === 'select' || type === 'radio' || type === 'button') {
                var $sel = $scope.find('select.pili-select-native, select').first();
                if ($sel.length) {
                    var $opt = $sel.find('option:selected');
                    var label = ($opt.text() || '').trim();
                    if (label) {
                        return label;
                    }
                    return String($sel.val() || '').trim();
                }
            }
            var $input = $scope.find('input[type="text"], input[type="search"], input[type="number"], textarea').first();
            if (!$input.length) {
                $input = $scope.find('[name*="[' + fieldId + ']"]').first();
            }
            return $input.length ? String($input.val() || '').trim() : '';
        },

        isItemEnabled: function($item) {
            var $scope = this.getSubfieldScope($item, 'enabled');
            var $sw = $scope.length ? $scope.find('[name*="[enabled]"]').first() : $item.find('[name*="[enabled]"]').first();
            if (!$sw.length) {
                return true;
            }
            if ($sw.is(':checkbox')) {
                return $sw.prop('checked');
            }
            var v = String($sw.val() || '').toLowerCase();
            return v === '1' || v === 'true' || v === 'on' || v === 'yes';
        },

        quotaBusy: false,

        getMaxEnabled: function($field) {
            var n = parseInt($field.attr('data-max-enabled'), 10);
            return isNaN(n) ? 0 : Math.max(0, n);
        },

        getEnabledFieldId: function($field) {
            return $field.attr('data-max-enabled-field') || 'enabled';
        },

        getMaxEnabledSkip: function($field) {
            var raw = $field.attr('data-max-enabled-skip') || '';
            if (!raw) {
                return null;
            }
            try {
                var parsed = JSON.parse(raw);
                if (parsed && parsed.field && parsed.values && parsed.values.length) {
                    return parsed;
                }
            } catch (err) {
            }
            return null;
        },

        getSubfieldRawValue: function($item, fieldId) {
            var $scope = this.getSubfieldScope($item, fieldId);
            if (!$scope.length) {
                var $fallback = $item.find('[name*="[' + fieldId + ']"]').first();
                return $fallback.length ? String($fallback.val() || '') : '';
            }
            var $sel = $scope.find('select').first();
            if ($sel.length) {
                return String($sel.val() || '');
            }
            var $input = $scope.find('input, textarea').first();
            return $input.length ? String($input.val() || '') : '';
        },

        isMaxEnabledSkipItem: function($item, $field) {
            var skip = this.getMaxEnabledSkip($field);
            if (!skip) {
                return false;
            }
            var val = this.getSubfieldRawValue($item, skip.field);
            return skip.values.indexOf(val) !== -1;
        },

        getEnabledSwitchEl: function($item, $field) {
            var fieldId = this.getEnabledFieldId($field || this.getOwningRepeaterField($item));
            var $scope = this.getSubfieldScope($item, fieldId);
            if (!$scope.length) {
                return $();
            }
            return $scope.find('[data-pili-switch]').first();
        },

        countEnabledItems: function($field) {
            var self = this;
            var count = 0;
            this.getDirectItems($field).each(function() {
                var $item = $(this);
                if (self.isMaxEnabledSkipItem($item, $field)) {
                    return;
                }
                if (self.isItemEnabled($item)) {
                    count += 1;
                }
            });
            return count;
        },

        setItemEnabled: function($item, on, $field) {
            $field = $field && $field.length ? $field : this.getOwningRepeaterField($item);
            var $el = this.getEnabledSwitchEl($item, $field);
            var inst = $el.length ? $el.data('xunSwitch') : null;
            if (inst) {
                var wasDisabled = !!inst.isDisabled;
                if (wasDisabled) {
                    inst.setDisabled(false);
                }
                inst.setChecked(!!on);
                return;
            }
            var fieldId = this.getEnabledFieldId($field);
            var $scope = this.getSubfieldScope($item, fieldId);
            var $input = $scope.length ? $scope.find('input[type="hidden"]').first() : $();
            if (!$input.length) {
                $input = $item.find('[name*="[' + fieldId + ']"]').first();
            }
            if ($input.length) {
                $input.val(on ? '1' : '');
            }
            if ($el.length) {
                $el.find('[role="switch"]').attr('data-checked', on ? 'true' : 'false');
            }
        },

        setEnabledSwitchLocked: function($item, locked, $field) {
            var $el = this.getEnabledSwitchEl($item, $field);
            var inst = $el.length ? $el.data('xunSwitch') : null;
            var max = $field && $field.length ? this.getMaxEnabled($field) : 0;
            var tpl = (PILI.bag('repeater') && PILI.bag('repeater').maxEnabled) || '已达到最多启用数量限制（%d 个），多出来的项目可以保留但不能启用。';
            var tip = String(tpl).replace('%d', String(max || 1));
            if (inst) {
                inst.setDisabled(!!locked);
            } else if ($el.length) {
                $el.find('[role="switch"]').toggleClass('opacity-50 cursor-not-allowed', !!locked);
            }
            if ($el.length) {
                $el.attr('title', locked ? tip : '');
            }
        },

        syncEnabledSwitchLocks: function($field) {
            var max = this.getMaxEnabled($field);
            if (max <= 0) {
                return;
            }
            var self = this;
            var full = this.countEnabledItems($field) >= max;
            this.getDirectItems($field).each(function() {
                var $item = $(this);
                if (self.isMaxEnabledSkipItem($item, $field)) {
                    self.setEnabledSwitchLocked($item, false, $field);
                    return;
                }
                var on = self.isItemEnabled($item);
                self.setEnabledSwitchLocked($item, full && !on, $field);
            });
        },

        enforceEnabledQuota: function($field) {
            if (!$field || !$field.length) {
                return;
            }
            var max = this.getMaxEnabled($field);
            if (max <= 0) {
                return;
            }
            var self = this;
            var kept = 0;
            this.quotaBusy = true;
            this.getDirectItems($field).each(function() {
                var $item = $(this);
                if (self.isMaxEnabledSkipItem($item, $field)) {
                    return;
                }
                if (!self.isItemEnabled($item)) {
                    return;
                }
                kept += 1;
                if (kept > max) {
                    self.setItemEnabled($item, false, $field);
                    self.updateItemTitleFromItem($item, $field);
                }
            });
            this.quotaBusy = false;
            this.syncEnabledSwitchLocks($field);
        },

        scheduleEnabledQuota: function($field) {
            var self = this;
            var run = function() {
                if ($field && $field.length) {
                    self.enforceEnabledQuota($field);
                    return;
                }
                $('.pili-repeater-field').each(function() {
                    if ($(this).closest('.pili-repeater-template').length) {
                        return;
                    }
                    self.enforceEnabledQuota($(this));
                });
            };
            run();
            [50, 150, 400].forEach(function(ms) {
                setTimeout(run, ms);
            });
        },

        onEnabledSwitchChange: function($field, $item, payload) {
            if (this.quotaBusy) {
                return;
            }
            var max = this.getMaxEnabled($field);
            if (max <= 0) {
                return;
            }
            if (this.isMaxEnabledSkipItem($item, $field)) {
                this.syncEnabledSwitchLocks($field);
                return;
            }
            var checked = payload && typeof payload.checked !== 'undefined'
                ? !!payload.checked
                : this.isItemEnabled($item);
            if (checked && this.countEnabledItems($field) > max) {
                this.quotaBusy = true;
                this.setItemEnabled($item, false, $field);
                this.quotaBusy = false;
                this.notifyMaxEnabled($field);
            }
            this.syncEnabledSwitchLocks($field);
        },

        shouldBlockEnable: function($field, $item) {
            var max = this.getMaxEnabled($field);
            if (max <= 0) {
                return false;
            }
            if (this.isMaxEnabledSkipItem($item, $field)) {
                return false;
            }
            if (this.isItemEnabled($item)) {
                return false;
            }
            return this.countEnabledItems($field) >= max;
        },

        toastWarning: function(msg, throttleKey) {
            var now = Date.now();
            var stampKey = '_toastAt_' + (throttleKey || 'default');
            if (this[stampKey] && now - this[stampKey] < 1500) {
                return;
            }
            this[stampKey] = now;
            msg = String(msg || '');
            if (!msg) {
                return;
            }
            if (window.PiliXunToast && typeof window.PiliXunToast.warning === 'function') {
                window.PiliXunToast.warning(msg);
                return;
            }
            if (window.PiliXunToast && typeof window.PiliXunToast.show === 'function') {
                window.PiliXunToast.show(msg, { type: 'warning' });
                return;
            }
            if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
                window.PilipostUi.toast(msg, 'warning');
            }
        },

        notifyMaxEnabled: function($field) {
            var max = this.getMaxEnabled($field);
            var tpl = (PILI.bag('repeater') && PILI.bag('repeater').maxEnabled) || '已达到最多启用数量限制（%d 个），多出来的项目可以保留但不能启用。';
            this.toastWarning(String(tpl).replace('%d', String(max || 1)), 'maxEnabled');
        },

        notifyMaxItems: function($field) {
            var max = parseInt($field.data('max'), 10) || 0;
            var tpl = (PILI.bag('repeater') && PILI.bag('repeater').maxItems) || '已达到最大项目数量限制（%d 个）';
            this.toastWarning(String(tpl).replace('%d', String(max || 1)), 'maxItems');
        },

        buildItemTitleText: function($item, $field) {
            var config = this.getTitleConfig($field);
            var parts = [];
            var idx = this.getDirectItems($field).index($item) + 1;
            var hasFieldValue = false;

            if (config.showIndex) {
                parts.push(String(config.prefix || (PILI.bag('repeater') && PILI.bag('repeater').item) || '项目').trim() + ' ' + idx);
            }
            (config.fields || []).forEach(function(fieldId) {
                var val = XunRepeaterField.resolveSubfieldDisplayValue($item, fieldId);
                if (val) {
                    parts.push(val);
                    hasFieldValue = true;
                }
            });
            if (config.showDisabled && !this.isItemEnabled($item)) {
                parts.push((PILI.bag('repeater') && PILI.bag('repeater').disabled) || '已禁用');
            }
            if (!parts.length || (!hasFieldValue && !config.showIndex)) {
                return (config.fallback || (PILI.bag('repeater') && PILI.bag('repeater').item) || '项目') + ' #' + idx;
            }
            var text = parts.join(config.separator || ' · ');
            var max = parseInt(config.maxLength, 10) || 100;
            if (text.length > max) {
                text = text.substring(0, max) + '...';
            }
            return text;
        },

        updateItemTitleFromItem: function($item, $field) {
            if (!$item || !$item.length) {
                return;
            }
            if (!$field || !$field.length) {
                $field = this.getOwningRepeaterField($item);
            }
            if (!$field.length) {
                return;
            }
            var $titleSpan = this.getItemHeader($item).find('.pili-repeater-item-title').first();
            if (!$titleSpan.length) {
                return;
            }
            var idx = this.getDirectItems($field).index($item) + 1;
            var fallback = (this.getTitleConfig($field).fallback || (PILI.bag('repeater') && PILI.bag('repeater').item) || '项目') + ' #' + idx;
            $titleSpan.attr('data-default-title', fallback);
            $titleSpan.text(this.buildItemTitleText($item, $field));
        },

        /**
         * 是否默认收起（data-default-collapsed）
         */
        isDefaultCollapsed: function($field) {
            if (!$field || !$field.length) {
                return false;
            }
            if ($field.data('collapsible') === false || $field.attr('data-collapsible') === 'false') {
                return false;
            }
            return $field.attr('data-default-collapsed') === 'true';
        },

        /**
         * 无动画收起单行（初始化 / 新增项兜底）
         */
        collapseItemInstant: function($item) {
            var $content = this.getItemContent($item);
            if (!$content.length || $content.is(':hidden')) {
                return;
            }
            var $header = this.getItemHeader($item);
            var $toggleBtn = $header.find('.pili-repeater-toggle').first();
            var $icon = $toggleBtn.find('svg');
            $content.hide();
            $icon.addClass('rotate-180');
            $item.addClass('collapsed');
            $toggleBtn.attr('aria-expanded', 'false');
            $header.attr('aria-expanded', 'false');
            $content.attr('aria-hidden', 'true');
        },

        /**
         * 按字段配置应用默认收起（已有行 + 动态新增行）
         */
        applyDefaultCollapsedState: function($field) {
            if (!this.isDefaultCollapsed($field)) {
                return;
            }
            var self = this;
            this.getDirectItems($field).each(function() {
                self.collapseItemInstant($(this));
            });
        },
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initializeFields();
            this.initSortable();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;

            $(document).on('click', '.pili-repeater-add', function(e) {
                e.preventDefault();
                self.addItem($(this));
            });

            $(document).on('click', '.pili-repeater-remove', function(e) {
                e.preventDefault();
                self.removeItem($(this));
            });

            $(document).on('click', '.pili-repeater-clone', function(e) {
                e.preventDefault();
                self.cloneItem($(this));
            });

            $(document).on('click', '.pili-repeater-toggle', function(e) {
                e.preventDefault();
                self.toggleItem($(this));
            });

            $(document).on('click', '.pili-repeater-item-header', function(e) {
                if ($(e.target).is('button, input, select, textarea, a, svg, path') ||
                    $(e.target).closest('button').length > 0 ||
                    $(e.target).closest('.pili-repeater-toggle, .pili-repeater-remove, .pili-repeater-clone').length > 0) {
                    return;
                }
                e.preventDefault();
                var $toggleBtn = $(this).find('.pili-repeater-toggle');
                if ($toggleBtn.length) {
                    self.toggleItem($toggleBtn);
                }
            });

            $(document).on('keydown', '.pili-repeater-item-header', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var $toggleBtn = $(this).find('.pili-repeater-toggle');
                    if ($toggleBtn.length) {
                        self.toggleItem($toggleBtn);
                    }
                }
            });

            $(document).on('click', '.pili-repeater-collapse-all', function(e) {
                e.preventDefault();
                self.collapseAll($(this));
            });

            $(document).on('click', '.pili-repeater-expand-all', function(e) {
                e.preventDefault();
                self.expandAll($(this));
            });

            $(document).on('click', '.pili-repeater-clear-all', function(e) {
                e.preventDefault();
                self.clearAll($(this));
            });

            $(document).on('keydown', '.pili-repeater-field', function(e) {
                self.handleKeyboardShortcuts(e, $(this));
            });

            $(document).on('input change', '.pili-repeater-item input, .pili-repeater-item textarea, .pili-repeater-item select', function() {
                var $item = $(this).closest('.pili-repeater-item');
                self.updateItemTitleFromItem($item);
            });

            $(document).on('xun:select:changed', '.pili-repeater-item .pili-select-field', function() {
                var $item = $(this).closest('.pili-repeater-item');
                setTimeout(function() {
                    self.updateItemTitleFromItem($item);
                }, 0);
            });

            $(document).on('click', '.pili-repeater-item [data-pili-switch]', function() {
                var $sw = $(this);
                var $item = $sw.closest('.pili-repeater-item');
                var $field = self.getOwningRepeaterField($item);
                if (!$field.length) {
                    return;
                }
                var $scope = $sw.closest('.space-y-2[data-field-id]');
                if (($scope.attr('data-field-id') || '') !== self.getEnabledFieldId($field)) {
                    return;
                }
                if (self.shouldBlockEnable($field, $item)) {
                    self.notifyMaxEnabled($field);
                }
            });

            $(document).on('xun:switch:change', '.pili-repeater-item [data-pili-switch]', function(e, payload) {
                var $sw = $(this);
                var $item = $sw.closest('.pili-repeater-item');
                var $field = self.getOwningRepeaterField($item);
                var $scope = $sw.closest('.space-y-2[data-field-id]');
                var enabledId = $field.length ? self.getEnabledFieldId($field) : 'enabled';
                if ($field.length && ($scope.attr('data-field-id') || '') === enabledId) {
                    self.onEnabledSwitchChange($field, $item, payload || {});
                }
                setTimeout(function() {
                    self.updateItemTitleFromItem($item);
                }, 0);
            });

            $(document).on((window.PILI&&PILI.ev?PILI.ev('field:loaded'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:loaded'), function() {
                self.scheduleRefreshItemTitles();
            });

            $(document).on('sortstart', '.pili-repeater-items', function(e, ui) {
                self.onSortStart(ui);
            });

            $(document).on('sortstop', '.pili-repeater-items', function(e, ui) {
                self.onSortStop(ui);
            });

            $(document).on('sortupdate', '.pili-repeater-items', function(e, ui) {
                self.onSortUpdate($(this));
            });

            $(document).on('pili:field:added pili-field-added', function(e, $container) {
                if (!$container || !$container.length) {
                    return;
                }
                var $fields = $container.hasClass('pili-repeater-field')
                    ? $container
                    : $container.find('.pili-repeater-field');
                $fields.each(function() {
                    var $rep = $(this);
                    if ($rep.closest('.pili-repeater-template').length) {
                        return;
                    }
                    self.applyDefaultCollapsedState($rep);
                    self.updateFieldState($rep);
                    self.updateItemNumbers($rep);
                    self.refreshItemTitles($rep);
                    self.initializeAccessibility($rep);
                    self.initSortableForField($rep);
                    self.scheduleEnabledQuota($rep);
                });
            });
        },

        scheduleRefreshItemTitles: function($root) {
            var self = this;
            var run = function() {
                var $scope = ($root && $root.length) ? $root : $(document);
                $scope.find('.pili-repeater-field').each(function() {
                    if ($(this).closest('.pili-repeater-template').length) {
                        return;
                    }
                    self.refreshItemTitles($(this));
                });
            };
            run();
            [50, 150, 400].forEach(function(ms) {
                setTimeout(run, ms);
            });
        },

        /**
         * 初始化所有字段
         */
        initializeFields: function() {
            $('.pili-repeater-field').each(function() {
                var $field = $(this);
                XunRepeaterField.applyDefaultCollapsedState($field);
                XunRepeaterField.updateFieldState($field);
                XunRepeaterField.updateItemNumbers($field);
                XunRepeaterField.initializeAccessibility($field);

                XunRepeaterField.refreshItemTitles($field);
            });
            this.scheduleRefreshItemTitles();
            this.scheduleEnabledQuota();
        },

        /**
         * 刷新 repeater 所有行的行头标题。
         */
        refreshItemTitles: function($field) {
            if (!$field || !$field.length) {
                return;
            }
            var self = this;
            this.getDirectItems($field).each(function() {
                self.updateItemTitleFromItem($(this), $field);
            });
        },

        /**
         * 初始化无障碍访问属性
         */
        initializeAccessibility: function($field) {
            this.getDirectItems($field).each(function() {
                var $item = $(this);
                var $header = XunRepeaterField.getItemHeader($item);
                var $content = XunRepeaterField.getItemContent($item);
                var $toggleBtn = $item.find('> .pili-repeater-item-header .pili-repeater-toggle, .pili-repeater-item-header > .pili-repeater-item-actions .pili-repeater-toggle').first();
                if (!$toggleBtn.length) {
                    $toggleBtn = $header.find('.pili-repeater-toggle').first();
                }
                var isCollapsed = $content.is(':hidden');

                $header.attr({
                    'role': 'button',
                    'tabindex': '0',
                    'aria-expanded': isCollapsed ? 'false' : 'true',
                    'aria-label': (PILI.bag('repeater') && PILI.bag('repeater').toggleItem) || '展开或折叠此项目'
                });

                $toggleBtn.attr({
                    'aria-expanded': isCollapsed ? 'false' : 'true',
                    'aria-label': (PILI.bag('repeater') && PILI.bag('repeater').toggle) || '展开或折叠'
                });

                $content.attr({
                    'aria-hidden': isCollapsed ? 'true' : 'false'
                });
            });
        },

        /**
         * 为单个 repeater 容器启用拖拽（已初始化则跳过）
         */
        initSortableForField: function($field) {
            var $container = this.getItemsContainer($field);
            if (!$container.length || $container.hasClass('ui-sortable')) {
                return;
            }
            var sortable = $field.data('sortable');
            if (!sortable && $field.attr('data-sortable') !== 'true') {
                return;
            }
            $container.sortable({
                handle: '.pili-repeater-sort-handle',
                placeholder: 'pili-repeater-placeholder bg-blue-50 border-2 border-dashed border-blue-300 rounded-lg',
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
                helper: 'clone'
            });
        },

        /**
         * 初始化拖拽排序
         */
        initSortable: function() {
            var self = this;
            $('.pili-repeater-field').each(function() {
                self.initSortableForField($(this));
            });
        },

        /**
         * 新增/克隆行后重置动态字段 boot 标记，并触发子字段 boot。
         */
        resetDynamicFieldBootFlags: function($item) {
            if (!$item || !$item.length) {
                return;
            }
            $item.find('.pili-icon-field').each(function() {
                $(this).removeData('xunIconInitialized');
            });
            $item.find('.pili-color-field').each(function() {
                $(this).removeData('xunColorInitialized');
            });
            $item.find('.pili-media-field').each(function() {
                $(this).removeData('pili-media-initialized');
            });
            $item.find('.pili-gallery-field').each(function() {
                $(this).removeData('pili-gallery-initialized');
            });
            $item.find('.pili-select-field').each(function() {
                $(this).removeData('pili-select-initialized');
                $(this).removeData('pili-select-instance');
            });
            $item.find('[data-pili-switch]').each(function() {
                $(this).removeData('xunSwitch');
            });
            $item.find('.pili-slider-field').each(function() {
                $(this).removeData('pili-slider-initialized');
            });
        },

        bootDynamicFields: function($root) {
            if (!$root || !$root.length) {
                return;
            }
            if (PILI.Framework && typeof PILI.Framework.bootLazySectionFields === 'function') {
                PILI.Framework.bootLazySectionFields($root, { skip: ['chart'] });
            } else {
                if (typeof PILI.bootFn('icon') === 'function') {
                    PILI.boot('icon', $root);
                }
                if (typeof PILI.bootFn('color') === 'function') {
                    PILI.boot('color', $root);
                }
                if (typeof PILI.bootFn('media') === 'function') {
                    PILI.boot('media', $root);
                }
                if (typeof PILI.bootFn('slider') === 'function') {
                    PILI.boot('slider', $root);
                }
                if (typeof window.piliSelectFieldBoot === 'function') {
                    window.piliSelectFieldBoot($root);
                }
                if (typeof PILI.bootFn('switch') === 'function') {
                    PILI.boot('switch', $root);
                }
            }
            // 外层增/克隆行后：内层 nested repeater 需刷新结构与 sortable。
            if (typeof PILI.bootFn('repeater') === 'function') {
                PILI.boot('repeater', $root);
            }
        },

        /**
         * 计算 repeater 下一行 index（取 max(data-index)+1，避免删行后 duplicate index）。
         */
        nextRepeaterItemIndex: function($field) {
            var next = 0;
            this.getDirectItems($field).each(function() {
                var idx = parseInt($(this).attr('data-index'), 10);
                if (!isNaN(idx) && idx >= next) {
                    next = idx + 1;
                }
            });
            return next;
        },

        /**
         * 添加项目
         */
        addItem: function($button) {
            var self = this;
            var $field = $button.closest('.pili-repeater-field');
            var $container = this.getItemsContainer($field);
            var $template = this.getTemplateRoot($field);
            var max = parseInt($field.data('max')) || 0;
            var currentCount = this.getDirectItems($field).length;

            if (max > 0 && currentCount >= max) {
                this.notifyMaxItems($field);
                return;
            }

            var $newItem = $template.children('.pili-repeater-item').first().clone();
            var newIndex = this.nextRepeaterItemIndex($field);

            $newItem = this.replaceTemplateVars($newItem, newIndex, $field);
            this.resetDynamicFieldBootFlags($newItem);

            $newItem.appendTo($container);

            if (this.isDefaultCollapsed($field)) {
                this.collapseItemInstant($newItem);
            }

            $newItem.find('.pili-repeater-field').each(function() {
                XunRepeaterField.initField($(this));
            });

            // 通知其他字段脚本：repeater 内新增了动态字段，需要重新初始化（如 select/date/icon 等）
            $(document).trigger((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), [$newItem]);
            $(document).trigger('pili-field-added', [$newItem]);

            setTimeout(function() {
                self.bootDynamicFields($newItem);
                self.scheduleEnabledQuota($field);
            }, 0);

            this.initializeAccessibility($newItem.closest('.pili-repeater-field'));

            var $firstInput = $newItem.find('input, textarea, select').first();
            $firstInput.focus();

            setTimeout(function() {
                self.updateItemTitleFromItem($newItem, $field);
            }, 100);

            this.updateFieldState($field);

            this.updateItemNumbers($field);

            this.triggerEvent($field, 'item:added', { item: $newItem, index: newIndex });
        },

        /**
         * 删除项目
         */
        removeItem: function($button) {
            var self = this;
            var $item = $button.closest('.pili-repeater-item');
            var $field = $item.closest('.pili-repeater-field');
            var min = parseInt($field.data('min')) || 0;
            var currentCount = this.getDirectItems($field).length;

            if (min > 0 && currentCount <= min) {
                this.showAlert($field, 'min');
                return;
            }

            var doRemove = function() {
                var index = $item.data('index');
                $item.remove();
                self.updateFieldState($field);
                self.updateItemNumbers($field);
                self.triggerEvent($field, 'item:removed', { index: index });
            };

            var msg =
                (PILI.bag('repeater') && PILI.bag('repeater').confirmDelete) ||
                '';
            if (!msg) {
                doRemove();
                return;
            }

            var title =
                (PILI.bag('repeater') && PILI.bag('repeater').confirmDeleteTitle) ||
                (PILI.bag('repeater') && PILI.bag('repeater').removeItem) ||
                '确认删除';
            var confirmText =
                (PILI.bag('repeater') && PILI.bag('repeater').removeItem) || '删除';
            var cancelText =
                (PILI.bag('repeater') && PILI.bag('repeater').cancel) || '取消';

            // 优先 XUN / PilipostUi；仅组件不可用时才允许浏览器 confirm 兜底。
            if (window.PilipostUi && typeof window.PilipostUi.confirm === 'function') {
                window.PilipostUi.confirm(msg, {
                    title: title,
                    type: 'warning',
                    confirmText: confirmText,
                    cancelText: cancelText
                }).then(function(ok) {
                    if (ok) {
                        doRemove();
                    }
                });
                return;
            }
            if (typeof PILI.confirm === 'function') {
                window
                    .PILI.confirm({
                        title: title,
                        message: msg,
                        type: 'warning',
                        confirmText: confirmText,
                        cancelText: cancelText
                    })
                    .then(function(ok) {
                        if (ok) {
                            doRemove();
                        }
                    });
                return;
            }
            if (window.confirm(msg)) {
                doRemove();
            }
        },

        /**
         * 复制项目
         */
        cloneItem: function($button) {
            var self = this;
            var $item = $button.closest('.pili-repeater-item');
            var $field = $item.closest('.pili-repeater-field');
            var max = parseInt($field.data('max')) || 0;
            var currentCount = this.getDirectItems($field).length;

            if (max > 0 && currentCount >= max) {
                this.notifyMaxItems($field);
                return;
            }

            var $clonedItem = $item.clone();
            var newIndex = this.nextRepeaterItemIndex($field);

            this.resetDynamicFieldBootFlags($clonedItem);
            this.updateItemIndex($clonedItem, newIndex);

            $clonedItem.find('input[type="file"]').val('');
            $clonedItem.find('.media-preview').empty();

            $clonedItem.hide().insertAfter($item).slideDown(300);

            // 通知其他字段脚本：repeater 内复制项后，内部字段需要重新初始化
            $(document).trigger((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), [$clonedItem]);
            $(document).trigger('pili-field-added', [$clonedItem]);

            setTimeout(function() {
                self.bootDynamicFields($clonedItem);
                self.scheduleEnabledQuota($field);
            }, 0);

            this.initializeAccessibility($field);

            this.updateFieldState($field);
            this.updateItemNumbers($field);

            this.triggerEvent($field, 'item:cloned', { 
                original: $item, 
                clone: $clonedItem, 
                index: newIndex 
            });
        },

        /**
         * 折叠/展开项目
         */
        toggleItem: function($button) {
            var $item = $button.closest('.pili-repeater-item');
            var $content = this.getItemContent($item);
            var $icon = $button.find('svg');
            var $header = this.getItemHeader($item);
            var isCollapsed = $content.is(':hidden');

            if (isCollapsed) {
                $content.slideDown(200);
                $icon.removeClass('rotate-180');
                $item.removeClass('collapsed');

                $button.attr('aria-expanded', 'true');
                $header.attr('aria-expanded', 'true');
                $content.attr('aria-hidden', 'false');
            } else {
                $content.slideUp(200);
                $icon.addClass('rotate-180');
                $item.addClass('collapsed');

                $button.attr('aria-expanded', 'false');
                $header.attr('aria-expanded', 'false');
                $content.attr('aria-hidden', 'true');
            }

            this.triggerEvent($item.closest('.pili-repeater-field'), 'item:toggled', {
                item: $item,
                collapsed: !isCollapsed
            });
        },

        /**
         * 全部折叠
         */
        collapseAll: function($button) {
            var $field = $button.closest('.pili-repeater-field');
            var $items = this.getDirectItems($field);

            $items.each(function() {
                var $item = $(this);
                var $content = XunRepeaterField.getItemContent($item);
                var $icon = $item.find('> .pili-repeater-item-header .pili-repeater-toggle svg').first();

                if ($content.is(':visible')) {
                    $content.slideUp(200);
                    $icon.addClass('rotate-180');
                    $item.addClass('collapsed');
                }
            });

            this.triggerEvent($field, 'items:collapsed');
        },

        /**
         * 全部展开
         */
        expandAll: function($button) {
            var $field = $button.closest('.pili-repeater-field');
            var $items = this.getDirectItems($field);

            $items.each(function() {
                var $item = $(this);
                var $content = XunRepeaterField.getItemContent($item);
                var $icon = $item.find('> .pili-repeater-item-header .pili-repeater-toggle svg').first();

                if ($content.is(':hidden')) {
                    $content.slideDown(200);
                    $icon.removeClass('rotate-180');
                    $item.removeClass('collapsed');
                }
            });

            this.triggerEvent($field, 'items:expanded');
        },

        /**
         * 清空全部
         */
        clearAll: function($button) {
            var $field = $button.closest('.pili-repeater-field');
            var min = parseInt($field.data('min')) || 0;
            var i18n = PILI.bag('repeater') || {};
            var msg = i18n.confirmClear || '';
            var title = i18n.confirmClearTitle || '确认清空';
            var confirmText = i18n.clear || '清空';
            var cancelText = i18n.cancel || '取消';

            var askConfirm = function(done) {
                if (!msg) {
                    done(true);
                    return;
                }
                if (window.PilipostUi && typeof window.PilipostUi.confirm === 'function') {
                    window.PilipostUi.confirm(msg, {
                        title: title,
                        type: 'warning',
                        confirmText: confirmText,
                        cancelText: cancelText
                    }).then(function(ok) {
                        done(!!ok);
                    });
                    return;
                }
                if (typeof PILI.confirm === 'function') {
                    window
                        .PILI.confirm({
                            title: title,
                            message: msg,
                            type: 'warning',
                            confirmText: confirmText,
                            cancelText: cancelText
                        })
                        .then(function(ok) {
                            done(!!ok);
                        });
                    return;
                }
                // 组件不可用时才允许浏览器 confirm 兜底。
                done(window.confirm(msg));
            };

            var selfRef = this;
            askConfirm(function(ok){
                if (!ok) return;
                var $items = selfRef.getDirectItems($field);
                var itemsToRemove = $items.length - min;
                if (itemsToRemove <= 0) {
                    selfRef.showAlert($field, 'min');
                    return;
                }
                $items.slice(min).each(function(index) {
                    var $item = $(this);
                    setTimeout(function() {
                        $item.slideUp(300, function() {
                            $item.remove();
                            if (index === itemsToRemove - 1) {
                                XunRepeaterField.updateFieldState($field);
                                XunRepeaterField.updateItemNumbers($field);
                            }
                        });
                    }, index * 100);
                });
                selfRef.triggerEvent($field, 'items:cleared');
            });
        },

        /**
         * 处理键盘快捷键
         */
        handleKeyboardShortcuts: function(e, $field) {
            if ((e.ctrlKey || e.metaKey) && e.which === 13) {
                e.preventDefault();
                $field.find('.pili-repeater-add').click();
            }

            if ((e.ctrlKey || e.metaKey) && e.which === 68) {
                var $focusedItem = $(e.target).closest('.pili-repeater-item');
                if ($focusedItem.length) {
                    e.preventDefault();
                    $focusedItem.find('.pili-repeater-clone').click();
                }
            }

            if (e.which === 46 && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                var $focusedItem = $(e.target).closest('.pili-repeater-item');
                if ($focusedItem.length) {
                    e.preventDefault();
                    $focusedItem.find('.pili-repeater-remove').click();
                }
            }
        },

        /**
         * 拖拽开始
         */
        onSortStart: function(ui) {
            ui.item.addClass('pili-repeater-dragging');
            ui.placeholder.height(ui.item.outerHeight());
        },

        /**
         * 拖拽结束
         */
        onSortStop: function(ui) {
            ui.item.removeClass('pili-repeater-dragging');
        },

        /**
         * 拖拽更新
         */
        onSortUpdate: function($container) {
            var $field = $container.closest('.pili-repeater-field');
            
            $container.children('.pili-repeater-item').each(function(index) {
                XunRepeaterField.updateItemIndex($(this), index);
            });

            this.updateItemNumbers($field);

            this.triggerEvent($field, 'items:sorted');
        },

        /**
         * 转义正则特殊字符。
         */
        escapeRegExp: function(str) {
            return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        /**
         * 替换模板变量（对齐主题：按当前 fieldId 作用域替换，保护嵌套模板 {{INDEX}}）。
         *
         * @param {jQuery} $element 新行根节点
         * @param {number} index    新行索引
         * @param {jQuery} [$field] 所属 .pili-repeater-field
         * @return {jQuery}
         */
        replaceTemplateVars: function($element, index, $field) {
            if (!$element || !$element.length) {
                return $element;
            }

            index = parseInt(index, 10);
            if (isNaN(index)) {
                index = 0;
            }
            var number = index + 1;
            var $rep = ($field && $field.length)
                ? $field
                : this.getOwningRepeaterField($element);
            if (!$rep || !$rep.length) {
                $rep = $element.closest('.pili-repeater-field');
            }
            var repId = $rep.length ? String($rep.data('field-id') || $rep.attr('data-field-id') || '') : '';

            $element.attr('data-index', index);

            var $title = this.getItemHeader($element).find('.pili-repeater-item-title').first();
            if ($title.length) {
                var defTitle = $title.attr('data-default-title') || '';
                if (defTitle) {
                    defTitle = defTitle
                        .replace(/\{\{NUMBER\}\}/g, String(number))
                        .replace(/\{\{ROW_NUMBER\}\}/g, String(number))
                        .replace(/\{\{INDEX\}\}/g, String(index))
                        .replace(/\{\{ROW_INDEX\}\}/g, String(index));
                    $title.attr('data-default-title', defTitle);
                }
                var shown = $title.text();
                if (shown && shown.indexOf('{{') !== -1) {
                    $title.text(
                        shown
                            .replace(/\{\{NUMBER\}\}/g, String(number))
                            .replace(/\{\{ROW_NUMBER\}\}/g, String(number))
                            .replace(/\{\{INDEX\}\}/g, String(index))
                            .replace(/\{\{ROW_INDEX\}\}/g, String(index))
                    );
                }
            }

            var segRe = repId
                ? new RegExp('(\\[' + this.escapeRegExp(repId) + '\\])\\[\\{\\{(?:INDEX|ROW_INDEX)\\}\\}\\]', 'g')
                : null;
            var segReLegacy = repId
                ? new RegExp('(\\[' + this.escapeRegExp(repId) + '\\])\\{\\{(?:INDEX|ROW_INDEX)\\}\\}', 'g')
                : null;

            var rewrite = function(val) {
                if (typeof val !== 'string' || !val) {
                    return val;
                }
                var next = val.split('___').join('');
                if (segRe) {
                    next = next.replace(segRe, '$1[' + index + ']');
                }
                if (segReLegacy) {
                    next = next.replace(segReLegacy, '$1[' + index + ']');
                }
                return next;
            };

            $element.find('*').addBack().each(function() {
                var el = this;
                if (!el || !el.attributes) {
                    return;
                }
                var inNestedTemplate = false;
                var node = el;
                while (node && node !== $element[0]) {
                    if (node.classList && node.classList.contains('pili-repeater-template')) {
                        inNestedTemplate = true;
                        break;
                    }
                    node = node.parentNode;
                }

                for (var i = 0; i < el.attributes.length; i++) {
                    var attr = el.attributes[i];
                    if (!attr || !attr.name) {
                        continue;
                    }
                    var raw = attr.value;
                    if (typeof raw !== 'string' || (raw.indexOf('{{') === -1 && raw.indexOf('___') === -1)) {
                        continue;
                    }
                    if (inNestedTemplate && (attr.name === 'data-index' || attr.name === 'data-default-title')) {
                        continue;
                    }
                    var next = rewrite(raw);
                    if (!inNestedTemplate) {
                        next = next
                            .replace(/\{\{NUMBER\}\}/g, String(number))
                            .replace(/\{\{ROW_NUMBER\}\}/g, String(number));
                        if (el === $element[0] || attr.name === 'data-index') {
                            next = next
                                .replace(/\{\{INDEX\}\}/g, String(index))
                                .replace(/\{\{ROW_INDEX\}\}/g, String(index));
                        }
                        if ((attr.name === 'name' || attr.name === 'id' || attr.name === 'for')
                            && next.indexOf('{{') !== -1 && repId) {
                            next = next.replace(
                                new RegExp('(\\[' + XunRepeaterField.escapeRegExp(repId) + '\\])\\[\\{\\{(?:INDEX|ROW_INDEX)\\}\\}\\]', 'g'),
                                '$1[' + index + ']'
                            );
                        }
                    }
                    if (next !== raw) {
                        el.setAttribute(attr.name, next);
                    }
                }
            });

            return $element;
        },

        /**
         * @deprecated 请使用 updateItemTitleFromItem；保留兼容旧调用。
         */
        updateItemTitle: function($input) {
            var $item = $input.closest('.pili-repeater-item');
            this.updateItemTitleFromItem($item);
        },

        /**
         * 更新项目索引
         */
        updateItemIndex: function($item, newIndex) {
            var oldIndex = $item.attr('data-index');
            if (oldIndex === undefined || oldIndex === null) {
                oldIndex = $item.data('index');
            }
            oldIndex = String(oldIndex);
            newIndex = String(newIndex);
            $item.attr('data-index', newIndex);

            if (oldIndex === newIndex) {
                return;
            }

            var $repField = this.getOwningRepeaterField($item);
            var repId = $repField.data('field-id') || '';
            var oldSeg = repId ? ('[' + repId + '][' + oldIndex + ']') : ('[' + oldIndex + ']');
            var newSeg = repId ? ('[' + repId + '][' + newIndex + ']') : ('[' + newIndex + ']');

            $item.find('input, textarea, select').each(function() {
                var $input = $(this);
                var name = $input.attr('name');
                if (name && name.indexOf(oldSeg) !== -1) {
                    $input.attr('name', name.split(oldSeg).join(newSeg));
                } else if (name) {
                    var re = new RegExp('\\[' + oldIndex + '\\](?=\\[[^\\]]+\\]$)');
                    if (re.test(name)) {
                        $input.attr('name', name.replace(re, '[' + newIndex + ']'));
                    }
                }

                var id = $input.attr('id');
                if (id) {
                    id = id.replace(/_\d+_/, '_' + newIndex + '_');
                    $input.attr('id', id);
                }
            });

            $item.find('.pili-icon-field').each(function() {
                var $icon = $(this);
                var fid = $icon.attr('data-field-id') || '';
                // 只改 owning 段 [repId][old]，避免嵌套行下标被连带改写。
                if (repId && fid.indexOf(oldSeg) !== -1) {
                    $icon.attr('data-field-id', fid.split(oldSeg).join(newSeg));
                }
                $icon.find('script.pili-icon-config').each(function() {
                    try {
                        var cfg = JSON.parse($(this).text());
                        if (cfg.fieldId && repId && String(cfg.fieldId).indexOf(oldSeg) !== -1) {
                            cfg.fieldId = String(cfg.fieldId).split(oldSeg).join(newSeg);
                            $(this).text(JSON.stringify(cfg));
                        }
                    } catch (e) {
                    }
                });
            });

            $item.find('label').each(function() {
                var $label = $(this);
                var forAttr = $label.attr('for');
                if (forAttr) {
                    forAttr = forAttr.replace(/_\d+_/, '_' + newIndex + '_');
                    $label.attr('for', forAttr);
                }
            });
        },

        /**
         * 更新项目编号
         */
        updateItemNumbers: function($field) {
            var config = this.getTitleConfig($field);
            var fallback = config.fallback || (PILI.bag('repeater') && PILI.bag('repeater').item) || '项目';

            this.getDirectItems($field).each(function(index) {
                var $item = $(this);
                var $titleSpan = $item.find('.pili-repeater-item-title');
                var newDefaultTitle = fallback + ' #' + (index + 1);

                if ($titleSpan.length > 0) {
                    $titleSpan.attr('data-default-title', newDefaultTitle);
                    var currentText = $titleSpan.text();
                    if (currentText.indexOf('{{NUMBER}}') !== -1) {
                        $titleSpan.text(newDefaultTitle);
                    }
                    XunRepeaterField.updateItemTitleFromItem($item, $field);
                } else {
                    $item.find('.pili-repeater-item-header .text-sm').first().text(newDefaultTitle);
                }
            });
        },

        /**
         * 空状态哨兵：无行时启用，有行时禁用，避免与行内 array 字段名冲突。
         */
        syncEmptySentinel: function($field) {
            var name = $field.attr('data-field-name') || '';
            var $sentinel = $field.children('.pili-repeater-empty-sentinel').first();
            if (!$sentinel.length && name) {
                $sentinel = $('<input>', {
                    type: 'hidden',
                    'class': 'pili-repeater-empty-sentinel',
                    name: name,
                    value: ''
                });
                $field.prepend($sentinel);
            }
            if (!$sentinel.length) {
                return;
            }
            var empty = this.getDirectItems($field).length === 0;
            $sentinel.prop('disabled', !empty);
        },

        /**
         * 更新字段状态
         */
        updateFieldState: function($field) {
            var $container = this.getItemsContainer($field);
            var $emptyState = $field.children('.pili-repeater-empty-state').first();
            var $addButton = $field.children('.pili-repeater-add-container').find('.pili-repeater-add').first();
            var $count = $field.children('.pili-repeater-toolbar').find('.pili-repeater-count').first();
            
            var itemCount = this.getDirectItems($field).length;
            var max = parseInt($field.data('max')) || 0;
            var min = parseInt($field.data('min')) || 0;

            $count.text(itemCount);
            this.syncEmptySentinel($field);

            if (itemCount === 0) {
                $emptyState.removeClass('hidden');
                $container.addClass('hidden');
            } else {
                $emptyState.addClass('hidden');
                $container.removeClass('hidden');
            }

            if (max > 0 && itemCount >= max) {
                $addButton.addClass('opacity-50 cursor-not-allowed');
            } else {
                $addButton.removeClass('opacity-50 cursor-not-allowed');
            }

            this.getDirectItems($field).find('> .pili-repeater-item-header .pili-repeater-remove').each(function() {
                if (min > 0 && itemCount <= min) {
                    $(this).addClass('opacity-50 cursor-not-allowed').prop('disabled', true);
                } else {
                    $(this).removeClass('opacity-50 cursor-not-allowed').prop('disabled', false);
                }
            });

            this.triggerEvent($field, 'state:updated', { count: itemCount });
            this.enforceEnabledQuota($field);
        },

        /**
         * 显示提示
         */
        showAlert: function($field, type) {
            $field.children('.pili-repeater-alert.pili-repeater-' + type + '-alert').first().each(function() {
                var $alert = $(this);
                if ($alert.length && $alert.hasClass('hidden')) {
                    $alert.removeClass('hidden').hide().slideDown(200);
                }
            });
        },

        /**
         * 隐藏提示
         */
        hideAlert: function($field, type) {
            var $alert = $field.children('.pili-repeater-alert.pili-repeater-' + type + '-alert').first();
            if ($alert.length && !$alert.hasClass('hidden')) {
                $alert.slideUp(200, function() {
                    $alert.addClass('hidden');
                });
            }
        },

        /**
         * 触发自定义事件
         */
        triggerEvent: function($field, eventName, data) {
            var fieldId = $field.data('field-id');
            $field.trigger('pili:repeater:' + eventName, $.extend({
                fieldId: fieldId
            }, data || {}));
        },

        /**
         * 重新初始化字段（用于动态添加的字段）
         */
        reinit: function() {
            this.initializeFields();
            this.initSortable();
        },

        /**
         * 动态插入的 repeater 字段（如 AJAX 加载区块）可手动调用
         */
        initField: function($field) {
            if (!$field || !$field.length) {
                return;
            }
            this.applyDefaultCollapsedState($field);
            this.updateFieldState($field);
            this.updateItemNumbers($field);
            this.refreshItemTitles($field);
            this.initializeAccessibility($field);
            this.initSortableForField($field);
            this.scheduleEnabledQuota($field);
        },

        /**
         * 获取字段数据
         */
        getData: function(fieldId) {
            var $field = $('.pili-repeater-field[data-field-id="' + fieldId + '"]');
            var data = [];

            this.getDirectItems($field).each(function() {
                var itemData = {};
                $(this).find('input, textarea, select').each(function() {
                    var $input = $(this);
                    var name = $input.attr('name');
                    if (name) {
                        var fieldName = name.match(/\[([^\]]+)\]$/);
                        if (fieldName) {
                            itemData[fieldName[1]] = $input.val();
                        }
                    }
                });
                data.push(itemData);
            });

            return data;
        },

        /**
         * 设置字段数据
         */
        setData: function(fieldId, data) {
            var $field = $('.pili-repeater-field[data-field-id="' + fieldId + '"]');
            
            this.getDirectItems($field).remove();

            if (Array.isArray(data)) {
                data.forEach(function(itemData, index) {
                });
            }

            this.updateFieldState($field);
        }
    };

    PILI.registerBoot('repeater', function ($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-repeater-field').each(function () {
            var $field = $(this);
            if ($field.closest('.pili-repeater-template').length) {
                return;
            }
            XunRepeaterField.applyDefaultCollapsedState($field);
            XunRepeaterField.updateFieldState($field);
            XunRepeaterField.updateItemNumbers($field);
            XunRepeaterField.refreshItemTitles($field);
            XunRepeaterField.initializeAccessibility($field);
            XunRepeaterField.initSortableForField($field);
            XunRepeaterField.scheduleEnabledQuota($field);
        });
    });

    $(document).ready(function() {
        XunRepeaterField.init();
    });

    PILI.RepeaterField = XunRepeaterField;

})(jQuery);
