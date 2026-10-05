/**
 * Xun Framework 代码字段 JavaScript
 *
 * 提供简洁的代码编辑字段交互功能，包括：
 * - 行号显示和更新
 * - Tab键缩进支持
 * - 代码格式化
 * - 复制功能
 * - 全屏编辑
 *
 * @package Xun Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    /**
     * 代码字段类
     */
    class XunCodeField {

        constructor($container) {
            this.$container = $container;
            this.$textarea = $container.find('.pili-code-textarea');
            this.$lineNumbers = $container.find('.pili-line-numbers-content');

            this.config = {
                fieldId: $container.data('field-id'),
                language: $container.data('language') || 'html',
                theme: $container.data('theme') || 'light',
                tabSize: parseInt(this.$textarea.data('tab-size')) || 2,
                showLineNumbers: $container.find('.pili-code-line-numbers').length > 0
            };

            this.isFullscreen = false;
            this.init();
        }

        init() {
            this.bindEvents();
            this.updateLineNumbers();
            this.setupTabSupport();
        }

        bindEvents() {
            this.$textarea.on('input', () => {
                this.updateLineNumbers();
            });

            this.$textarea.on('scroll', () => {
                this.syncScroll();
            });

            this.$container.find('.pili-code-copy').on('click', (e) => {
                e.preventDefault();
                this.copyCode();
            });

            this.$container.find('.pili-code-format').on('click', (e) => {
                e.preventDefault();
                this.formatCode();
            });

            this.$container.find('.pili-code-fullscreen').on('click', (e) => {
                e.preventDefault();
                this.toggleFullscreen();
            });

            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isFullscreen) {
                    this.exitFullscreen();
                }
            });

            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', () => {
                if (!document.fullscreenElement && !document.webkitFullscreenElement &&
                    !document.mozFullScreenElement && !document.msFullscreenElement) {
                    this.exitFullscreen();
                }
            });
        }

        setupTabSupport() {
            this.$textarea.on('keydown', (e) => {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    this.insertTab();
                }
            });
        }

        insertTab() {
            const textarea = this.$textarea[0];
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const spaces = ' '.repeat(this.config.tabSize);

            const value = textarea.value;
            textarea.value = value.substring(0, start) + spaces + value.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + spaces.length;

            this.$textarea.trigger('input');
        }

        updateLineNumbers() {
            if (!this.config.showLineNumbers) return;

            const lines = this.$textarea.val().split('\n');
            const lineCount = lines.length;

            let lineNumbersHtml = '';
            for (let i = 1; i <= lineCount; i++) {
                lineNumbersHtml += `<div class="leading-5">${i}</div>`;
            }

            this.$lineNumbers.html(lineNumbersHtml);
        }

        syncScroll() {
            if (!this.config.showLineNumbers) return;

            const scrollTop = this.$textarea.scrollTop();
            this.$container.find('.pili-code-line-numbers').scrollTop(scrollTop);
        }

        copyCode() {
            const code = this.$textarea.val();

            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).catch(() => {
                    this.fallbackCopy(code);
                });
            } else {
                this.fallbackCopy(code);
            }
        }

        fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
            } catch (err) {
            }

            document.body.removeChild(textarea);
        }

        formatCode() {
            const code = this.$textarea.val();
            let formattedCode = code;

            switch (this.config.language) {
                case 'html':
                case 'xml':
                    formattedCode = this.formatHTML(code);
                    break;
                case 'css':
                    formattedCode = this.formatCSS(code);
                    break;
                case 'javascript':
                case 'json':
                    formattedCode = this.formatJS(code);
                    break;
                default:
                    formattedCode = this.formatGeneric(code);
            }

            this.$textarea.val(formattedCode);
            this.updateLineNumbers();
        }

        formatHTML(html) {
            return html
                .replace(/></g, '>\n<')
                .replace(/^\s+|\s+$/g, '')
                .split('\n')
                .map(line => line.trim())
                .filter(line => line.length > 0)
                .map((line, index, array) => {
                    let indent = 0;
                    for (let i = 0; i < index; i++) {
                        if (array[i].match(/<[^\/][^>]*[^\/]>$/)) indent++;
                        if (array[i].match(/<\/[^>]+>$/)) indent--;
                    }
                    if (line.match(/^<\/[^>]+>$/)) indent--;
                    return ' '.repeat(Math.max(0, indent * this.config.tabSize)) + line;
                })
                .join('\n');
        }

        formatCSS(css) {
            return css
                .replace(/\s*{\s*/g, ' {\n')
                .replace(/;\s*/g, ';\n')
                .replace(/\s*}\s*/g, '\n}\n')
                .split('\n')
                .map(line => {
                    line = line.trim();
                    if (line.includes(':') && !line.includes('{') && !line.includes('}')) {
                        return ' '.repeat(this.config.tabSize) + line;
                    }
                    return line;
                })
                .filter(line => line.length > 0)
                .join('\n');
        }

        formatJS(js) {
            return js
                .replace(/\s*{\s*/g, ' {\n')
                .replace(/;\s*/g, ';\n')
                .replace(/\s*}\s*/g, '\n}\n')
                .split('\n')
                .map(line => line.trim())
                .filter(line => line.length > 0)
                .join('\n');
        }

        formatGeneric(code) {
            return code
                .split('\n')
                .map(line => line.trim())
                .filter(line => line.length > 0)
                .join('\n');
        }

        toggleFullscreen() {
            if (this.isFullscreen) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        }

        enterFullscreen() {
            const element = this.$container[0];

            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.mozRequestFullScreen) {
                element.mozRequestFullScreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            }

            this.isFullscreen = true;
            this.$container.addClass('pili-fullscreen-mode');

            setTimeout(() => {
                this.updateLineNumbers();
            }, 100);

            this.$container.find('.pili-code-fullscreen span').text((PILI.bag('code') && PILI.bag('code').fullscreen_exit) || '退出全屏');
        }

        exitFullscreen() {
            const isInFullscreen = document.fullscreenElement || document.webkitFullscreenElement ||
                                 document.mozFullScreenElement || document.msFullscreenElement;

            if (isInFullscreen) {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }

            this.isFullscreen = false;
            this.$container.removeClass('pili-fullscreen-mode');

            this.$container.find('.pili-code-fullscreen span').text((PILI.bag('code') && PILI.bag('code').fullscreen_enter) || '全屏');
        }
    }

    /**
     * 初始化所有代码字段
     */
    function initCodeFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-code-field').each(function() {
            const $this = $(this);
            if (!$this.data('pili-code-initialized')) {
                const instance = new XunCodeField($this);
                $this.data('pili-code-instance', instance);
                $this.data('pili-code-initialized', true);
            }
        });
    }

    PILI.registerBoot('code', initCodeFields);

    $(document).ready(function() {
        initCodeFields();
    });

    $(document).on('xun:field:loaded', function() {
        initCodeFields();
    });

    $(document).on('xun:field:added', function(e, $container) {
        initCodeFields($container && $container.length ? $container : undefined);
    });

})(jQuery);