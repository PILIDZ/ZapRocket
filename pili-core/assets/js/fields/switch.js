/**
 * PILI Framework Switch 字段 JavaScript
 * 
 * 为开关字段提供现代化的交互功能，包括：
 * - 流畅的动画效果
 * - 键盘导航支持
 * - 无障碍访问优化
 * - 触摸设备支持
 * - 状态管理
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    /**
     * Switch 字段类
     * 
     * 管理开关字段的所有交互行为和状态。
     * 
     * @since 1.0
     */
    class XunSwitch {
        
        /**
         * 构造函数
         *
         * @since 1.0
         *
         * @param {jQuery} $element 开关元素
         */
        constructor($element) {
            this.$container = $element;
            this.$switch = $element.find('[role="switch"]');
            this.$input = $element.find('input[type="hidden"]');

            this.isChecked = this.$switch.attr('data-checked') === 'true';
            this.isDisabled = this.$switch.hasClass('cursor-not-allowed');
            this.isLoading = this.$switch.hasClass('opacity-75');
            this.size = this.$switch.attr('data-size') || 'medium';
            this.color = this.$switch.attr('data-color') || 'blue';
            this.style = this.$container.attr('data-style') || 'default';

            if (this.style === 'short') {
                this.$thumb = this.$switch.find('span').last();
                this.$track = this.$switch.find('span').first();
            } else {
                this.$thumb = this.$switch.find('span').first();
            }

            this.init();
        }
        
        /**
         * 初始化开关
         * 
         * @since 1.0
         */
        init() {
            if (this.isDisabled || this.isLoading) {
                return;
            }
            
            this.bindEvents();
            this.updateAriaAttributes();
        }
        
        /**
         * 绑定事件
         * 
         * @since 1.0
         */
        bindEvents() {
            this.$switch.on('click.xunSwitch', (e) => {
                e.preventDefault();
                this.toggle();
            });
            
            this.$switch.on('keydown.xunSwitch', (e) => {
                this.handleKeydown(e);
            });
            
            this.$switch.on('focus.xunSwitch', () => {
                this.$switch.addClass('pili-switch-focused');
            });
            
            this.$switch.on('blur.xunSwitch', () => {
                this.$switch.removeClass('pili-switch-focused');
            });
            
            if ('ontouchstart' in window) {
                this.bindTouchEvents();
            }
        }
        
        /**
         * 绑定触摸事件
         * 
         * @since 1.0
         */
        bindTouchEvents() {
            let startX = 0;
            let isDragging = false;
            
            this.$thumb.on('touchstart.xunSwitch', (e) => {
                if (this.isDisabled || this.isLoading) return;
                
                startX = e.originalEvent.touches[0].clientX;
                isDragging = true;
                this.$switch.addClass('pili-switch-dragging');
            });
            
            this.$thumb.on('touchmove.xunSwitch', (e) => {
                if (!isDragging || this.isDisabled || this.isLoading) return;
                
                e.preventDefault();
                const currentX = e.originalEvent.touches[0].clientX;
                const deltaX = currentX - startX;
                const threshold = 20;
                
                if (Math.abs(deltaX) > threshold) {
                    const shouldCheck = deltaX > 0;
                    if (shouldCheck !== this.isChecked) {
                        this.toggle();
                    }
                    isDragging = false;
                }
            });
            
            this.$thumb.on('touchend.xunSwitch', () => {
                isDragging = false;
                this.$switch.removeClass('pili-switch-dragging');
            });
        }
        
        /**
         * 处理键盘事件
         * 
         * @since 1.0
         * 
         * @param {Event} e 键盘事件
         */
        handleKeydown(e) {
            if (this.isDisabled || this.isLoading) return;
            
            switch (e.key) {
                case ' ':
                case 'Enter':
                    e.preventDefault();
                    this.toggle();
                    break;
                    
                case 'ArrowLeft':
                    e.preventDefault();
                    this.setChecked(false);
                    break;
                    
                case 'ArrowRight':
                    e.preventDefault();
                    this.setChecked(true);
                    break;
            }
        }
        
        /**
         * 切换开关状态
         * 
         * @since 1.0
         */
        toggle() {
            this.setChecked(!this.isChecked);
        }
        
        /**
         * 设置开关状态
         *
         * @since 1.0
         *
         * @param {boolean} checked 是否选中
         */
        setChecked(checked) {
            if (this.isDisabled || this.isLoading || this.isChecked === checked) {
                return;
            }

            this.isChecked = checked;

            this.$switch.attr('data-checked', checked ? 'true' : 'false');

            this.updateBackgroundColor(checked);

            this.updateThumbPosition(checked);

            this.updateIconDisplay(checked);

            this.$input.val(checked ? '1' : '').trigger('change');

            this.updateAriaAttributes();

            this.$container.trigger('xun:switch:change', {
                checked: checked,
                value: checked ? '1' : '',
                field: this
            });
        }

        /**
         * 更新背景颜色
         *
         * @since 1.0
         *
         * @param {boolean} checked 是否选中
         */
        updateBackgroundColor(checked) {
            if (this.style === 'short') {
                const colorClasses = [
                    'bg-gray-200', 'bg-blue-600', 'bg-green-600', 'bg-red-600',
                    'bg-yellow-600', 'bg-purple-600', 'bg-pink-600', 'bg-gray-600',
                    'bg-indigo-600'
                ];
                this.$track.removeClass(colorClasses.join(' '));

                if (checked) {
                    const colorMap = {
                        'blue': 'bg-blue-600',
                        'green': 'bg-green-600',
                        'red': 'bg-red-600',
                        'yellow': 'bg-yellow-600',
                        'purple': 'bg-purple-600',
                        'pink': 'bg-pink-600',
                        'gray': 'bg-gray-600'
                    };
                    this.$track.addClass(colorMap[this.color] || 'bg-blue-600');
                } else {
                    this.$track.addClass('bg-gray-200');
                }
            } else {
                const colorClasses = [
                    'bg-gray-200', 'bg-blue-600', 'bg-green-600', 'bg-red-600',
                    'bg-yellow-600', 'bg-purple-600', 'bg-pink-600', 'bg-gray-600',
                    'bg-indigo-600',
                    'outline-blue-600', 'outline-green-600', 'outline-red-600',
                    'outline-yellow-600', 'outline-purple-600', 'outline-pink-600', 'outline-gray-600',
                    'outline-indigo-600',
                    'outline-gray-600'
                ];
                this.$switch.removeClass(colorClasses.join(' '));

                if (checked) {
                    const colorMap = {
                        'blue': 'bg-blue-600 outline-blue-600',
                        'green': 'bg-green-600 outline-green-600',
                        'red': 'bg-red-600 outline-red-600',
                        'yellow': 'bg-yellow-600 outline-yellow-600',
                        'purple': 'bg-purple-600 outline-purple-600',
                        'pink': 'bg-pink-600 outline-pink-600',
                        'gray': 'bg-gray-600 outline-gray-600'
                    };
                    this.$switch.addClass(colorMap[this.color] || 'bg-blue-600 outline-blue-600');
                } else {
                    this.$switch.addClass('bg-gray-200 outline-gray-600');
                }
            }
        }

        /**
         * 更新滑块位置
         *
         * @since 1.0
         *
         * @param {boolean} checked 是否选中
         */
        updateThumbPosition(checked) {
            const transformClasses = ['translate-x-0', 'translate-x-4', 'translate-x-5', 'translate-x-6', 'translate-x-7', 'translate-x-8', 'translate-x-9'];
            this.$thumb.removeClass(transformClasses.join(' '));

            if (checked) {
                if (this.style === 'short') {
                    this.$thumb.addClass('translate-x-5');
                } else {
                    switch (this.size) {
                        case 'small':
                            this.$thumb.addClass('translate-x-4');
                            break;
                        case 'large':
                            this.$thumb.addClass('translate-x-7');
                            break;
                        default:
                            this.$thumb.addClass('translate-x-5');
                            break;
                    }
                }
            } else {
                this.$thumb.addClass('translate-x-0');
            }
        }

        /**
         * 更新图标显示
         *
         * @since 1.0
         *
         * @param {boolean} checked 是否选中
         */
        updateIconDisplay(checked) {
            const $iconOff = this.$switch.find('span[aria-hidden="true"]').first();

            const $iconOn = this.$switch.find('span[aria-hidden="true"]').last();

            if ($iconOff.length && $iconOn.length && $iconOff[0] !== $iconOn[0]) {
                if (checked) {
                    $iconOff.removeClass('opacity-100').addClass('opacity-0');
                    $iconOn.removeClass('opacity-0').addClass('opacity-100');
                } else {
                    $iconOff.removeClass('opacity-0').addClass('opacity-100');
                    $iconOn.removeClass('opacity-100').addClass('opacity-0');
                }
            }
        }

        /**
         * 更新ARIA属性
         * 
         * @since 1.0
         */
        updateAriaAttributes() {
            this.$switch.attr('aria-checked', this.isChecked ? 'true' : 'false');
        }
        
        /**
         * 设置禁用状态
         *
         * @since 1.0
         *
         * @param {boolean} disabled 是否禁用
         */
        setDisabled(disabled) {
            this.isDisabled = disabled;
            this.$switch.toggleClass('opacity-50 cursor-not-allowed', disabled);
            this.$switch.attr('tabindex', disabled ? '-1' : '0');
            this.$container.toggleClass('cursor-not-allowed', disabled);
            this.$container.toggleClass('cursor-pointer', !disabled);
        }

        /**
         * 设置加载状态
         *
         * @since 1.0
         *
         * @param {boolean} loading 是否加载中
         */
        setLoading(loading) {
            this.isLoading = loading;
            this.$switch.toggleClass('opacity-75', loading);
        }
        
        /**
         * 获取当前值
         * 
         * @since 1.0
         * 
         * @return {boolean} 当前值
         */
        getValue() {
            return this.isChecked;
        }
        
        /**
         * 销毁开关实例
         * 
         * @since 1.0
         */
        destroy() {
            this.$switch.off('.xunSwitch');
            this.$thumb.off('.xunSwitch');
            this.$container.removeData('xunSwitch');
        }
    }
    
    /**
     * jQuery 插件
     * 
     * @since 1.0
     */
    $.fn.xunSwitch = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('xunSwitch');
            
            if (!instance) {
                instance = new XunSwitch($this);
                $this.data('xunSwitch', instance);
            }
            
            if (typeof options === 'string') {
                if (typeof instance[options] === 'function') {
                    return instance[options].apply(instance, Array.prototype.slice.call(arguments, 1));
                }
            }
        });
    };
    
    /**
     * 自动初始化
     * 
     * @since 1.0
     */
    function initSwitches($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('[data-pili-switch]').addBack('[data-pili-switch]').each(function() {
            const $this = $(this);
            if ($this.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$this.data('xunSwitch')) {
                $this.xunSwitch();
            }
        });
    }
    
    $(document).ready(function () {
        initSwitches();
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
        initSwitches($container && $container.length ? $container : undefined);
    });

    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            let shouldInit = false;

            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const $node = $(node);
                            if ($node.is('[data-pili-switch]') || $node.find('[data-pili-switch]').length) {
                                shouldInit = true;
                            }
                        }
                    });
                }
            });

            if (shouldInit) {
                setTimeout(function () { initSwitches(); }, 10);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    } else {
        console.warn('MutationObserver not supported, dynamic switch initialization may not work');
    }

    PILI.registerBoot('switch', function ($root) {
        initSwitches($root && $root.length ? $root : undefined);
    });
    
    PILI.Switch = XunSwitch;
    
})(jQuery);
