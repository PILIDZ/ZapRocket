/**
 * PILI Framework 媒体字段 JavaScript
 * 
 * 提供现代化的媒体选择和管理功能，包括：
 * - WordPress媒体库集成
 * - 拖拽上传支持
 * - 实时预览更新
 * - 用户体验优化
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function ($) {
    'use strict';

    function mediaI18n(key, fallback) {
        return (PILI.bag('media') && PILI.bag('media')[key]) || fallback;
    }

    /**
     * 媒体字段类
     * 
     * 管理单个媒体字段的所有交互功能
     */
    class XunMediaField {

        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$preview = $container.find('.pili-media-preview');
            this.$controls = $container.find('.pili-media-controls');
            this.$urlInput = $container.find('.pili-media-url');
            this.$hiddenFields = {};

            // 初始化隐藏字段引用
            const hiddenFields = ['id', 'filename', 'filesize', 'width', 'height', 'thumbnail', 'alt', 'title', 'description', 'mime_type'];
            hiddenFields.forEach(field => {
                this.$hiddenFields[field] = $container.find(`.pili-media-${field}`);
            });

            // 字段配置
            this.config = {
                fieldId: $container.data('field-id'),
                library: $container.data('library'),
                previewSize: $container.data('preview-size') || 'medium',
                multiple: $container.data('multiple') === true
            };

            // 媒体库实例
            this.mediaLibrary = null;

            this.init();
        }

        /**
         * 初始化字段
         */
        init() {
            this.bindEvents();
        }

        /**
         * 绑定事件
         */
        bindEvents() {
            // 选择媒体按钮点击事件
            this.$container.on('click', '.pili-media-button', (e) => {
                e.preventDefault();

                // 检查WordPress媒体库是否可用
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    console.error('WordPress media library is not available');
                    this.showError(mediaI18n('libraryLoadError', '媒体库未正确加载，请刷新页面重试'));
                    return;
                }

                this.openMediaLibrary();
            });

            // 移除媒体按钮点击事件
            this.$container.on('click', '.pili-media-remove', (e) => {
                e.preventDefault();
                this.removeMedia();
            });

            // URL输入框变化事件
            this.$urlInput.on('change', (e) => {
                this.handleUrlChange(e.target.value);
            });
        }

        /**
         * 显示错误信息
         * 
         * @param {string} message 错误信息
         */
        showError(message) {
            if (typeof PILI.alert === 'function') {
                PILI.alert({
                    title: mediaI18n('errorTitle', '错误'),
                    message: message,
                    type: 'error'
                });
            } else {
                alert(message);
            }
        }

        /**
         * 打开媒体库 - 使用最简单稳定的方法
         */
        openMediaLibrary() {
            try {
                // 懒加载分区：脚本可能已注入但模板未进 DOM。
                if ( ! document.getElementById( 'tmpl-media-modal' ) ) {
                    console.error( 'Error opening media library: Template not found: #tmpl-media-modal' );
                    this.showError( mediaI18n( 'libraryOpenError', '无法打开媒体库（请硬刷新页面后重试）' ) );
                    return;
                }

                // 创建一个全新的媒体库实例，避免状态冲突
                const frame = wp.media({
                    title: mediaI18n('title', '选择媒体文件'),
                    button: {
                        text: mediaI18n('button', '选择')
                    },
                    multiple: this.config.multiple,
                    library: this.getLibraryConfig()
                });

                // 当选择完成时的处理
                frame.on('select', () => {
                    try {
                        // 获取选择的附件集合
                        const selection = frame.state().get('selection');

                        // 获取第一个选择的附件
                        const attachment = selection.first();

                        if (attachment) {
                            // 获取附件的JSON数据
                            const attachmentData = attachment.toJSON();

                            // 设置媒体数据
                            this.setMedia(attachmentData);
                        }
                    } catch (error) {
                        console.error('Error in select handler:', error);

                        // 备用方法：直接从selection获取
                        try {
                            const selection = frame.state().get('selection');
                            if (selection && selection.models && selection.models.length > 0) {
                                const model = selection.models[0];
                                const data = model.attributes || model.toJSON();
                                this.setMedia(data);
                            } else {
                                this.showError(mediaI18n('selectionError', '无法获取选择的媒体文件'));
                            }
                        } catch (fallbackError) {
                            console.error('Fallback method also failed:', fallbackError);
                            this.showError(mediaI18n('processError', '处理媒体选择时出现错误'));
                        }
                    }
                });

                // 打开媒体库
                frame.open();

            } catch (error) {
                console.error('Error opening media library:', error);
                this.showError(mediaI18n('libraryOpenError', '无法打开媒体库'));
            }
        }

        /**
         * 获取媒体库配置
         * 
         * @return {object} 媒体库配置
         */
        getLibraryConfig() {
            const config = {};

            // 安全地设置 uploadedTo
            try {
                if (wp.media.view.settings && wp.media.view.settings.post && wp.media.view.settings.post.id) {
                    config.uploadedTo = wp.media.view.settings.post.id;
                }
            } catch (e) {
                // 忽略设置错误
            }

            // 设置媒体类型限制
            if (this.config.library) {
                const types = this.config.library.split(',').map(type => type.trim()).filter(type => type);
                if (types.length > 0) {
                    config.type = types;
                }
            }

            return config;
        }

        /**
         * 设置媒体文件
         * 
         * @param {object} attachment 附件对象
         */
        setMedia(attachment) {
            try {
                // 更新隐藏字段
                this.updateHiddenField('id', attachment.id || '');
                this.updateHiddenField('url', attachment.url || '');
                this.updateHiddenField('filename', attachment.filename || '');
                this.updateHiddenField('alt', attachment.alt || '');
                this.updateHiddenField('title', attachment.title || '');
                this.updateHiddenField('description', attachment.description || '');
                this.updateHiddenField('mime_type', attachment.mime || '');

                // 设置文件大小
                if (attachment.filesizeHumanReadable) {
                    this.updateHiddenField('filesize', attachment.filesizeHumanReadable);
                }

                // 设置图片尺寸
                if (attachment.width && attachment.height) {
                    this.updateHiddenField('width', attachment.width);
                    this.updateHiddenField('height', attachment.height);
                }

                // 设置缩略图
                let thumbnailUrl = '';
                if (attachment.sizes && attachment.sizes.thumbnail) {
                    thumbnailUrl = attachment.sizes.thumbnail.url;
                } else if (attachment.type === 'image') {
                    thumbnailUrl = attachment.url;
                }
                this.updateHiddenField('thumbnail', thumbnailUrl);

                // 更新URL输入框
                this.$urlInput.val(attachment.url || '');

                // 更新预览
                this.updatePreview(attachment);

                // 触发变更事件
                this.$container.trigger('xun:media:changed', [attachment]);

            } catch (error) {
                console.error('Error setting media:', error);
                this.showError(mediaI18n('setMediaError', '设置媒体文件时出现错误'));
            }
        }

        /**
         * 移除媒体文件
         */
        removeMedia() {
            const confirmRemoval = () => {
                try {
                    // 清空所有隐藏字段
                    Object.keys(this.$hiddenFields).forEach(field => {
                        this.updateHiddenField(field, '');
                    });

                    // 清空URL输入框
                    this.$urlInput.val('');

                    // 清空预览内容并隐藏，但不移除容器
                    if (this.$preview.length) {
                        this.$preview.empty().hide();
                    }

                    // 触发移除事件
                    this.$container.trigger('xun:media:removed');

                } catch (error) {
                    console.error('Error removing media:', error);
                    this.showError(mediaI18n('removeMediaError', '移除媒体文件时出现错误'));
                }
            };

            // 使用框架的对话框组件（如果可用），否则使用原生confirm
            if (typeof PILI.confirm === 'function') {
                PILI.confirm({
                    title: mediaI18n('confirmRemoveTitle', '确认移除'),
                    message: mediaI18n('confirmRemoveMedia', mediaI18n('remove_confirm', '确定要移除这个媒体文件吗？')),
                    confirmText: mediaI18n('remove', '移除'),
                    cancelText: mediaI18n('cancel', '取消'),
                    type: 'warning'
                }).then(result => {
                    if (result) {
                        confirmRemoval();
                    }
                });
            } else {
                if (confirm(mediaI18n('remove_confirm', '确定要移除这个文件吗？'))) {
                    confirmRemoval();
                }
            }
        }

        /**
         * 更新隐藏字段值
         * 
         * @param {string} field 字段名
         * @param {string} value 字段值
         */
        updateHiddenField(field, value) {
            if (this.$hiddenFields[field] && this.$hiddenFields[field].length) {
                this.$hiddenFields[field].val(value || '');
            }
        }

        /**
         * 更新预览
         * 
         * @param {object} attachment 附件对象
         */
        updatePreview(attachment) {
            try {
                // 重新查找预览容器，以防它被移除了
                this.$preview = this.$container.find('.pili-media-preview');
                
                // 如果预览容器不存在，创建它
                if (!this.$preview.length) {
                    this.createPreview();
                }

                const isImage = attachment.type === 'image';
                const previewUrl = isImage ?
                    (attachment.sizes && attachment.sizes[this.config.previewSize] ?
                        attachment.sizes[this.config.previewSize].url : attachment.url) :
                    attachment.url;

                if (isImage && previewUrl) {
                    // 图片预览 - 使用object-contain确保图片完整显示，不被裁剪
                    this.$preview.html(`
                        <div class="w-full h-full flex items-center justify-center p-2" style="min-height: 120px;">
                            <img src="${this.escapeHtml(previewUrl)}" alt="${this.escapeHtml(attachment.alt || '')}" class="max-w-full max-h-full object-contain rounded" />
                        </div>
                        <button type="button" class="pili-media-remove absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" title="${mediaI18n('remove', '移除')}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    `);
                } else {
                    // 非图片文件预览
                    const icon = this.getFileIcon(attachment.mime || '');
                    const filename = this.escapeHtml(attachment.filename || mediaI18n('unknownFile', '未知文件'));
                    const filesize = attachment.filesizeHumanReadable ? this.escapeHtml(attachment.filesizeHumanReadable) : '';

                    this.$preview.html(`
                        <div class="flex items-center justify-center p-6 min-h-[120px]">
                            <div class="text-center">
                                <div class="text-4xl text-gray-400 mb-2">${icon}</div>
                                <div class="text-sm font-medium text-gray-700 truncate max-w-[200px]">${filename}</div>
                                ${filesize ? `<div class="text-xs text-gray-500 mt-1">${filesize}</div>` : ''}
                            </div>
                        </div>
                        <button type="button" class="pili-media-remove absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" title="${mediaI18n('remove', '移除')}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    `);
                }

                // 确保预览容器可见
                this.$preview.show();

            } catch (error) {
                console.error('Error updating preview:', error);
            }
        }

        /**
         * 创建预览容器
         */
        createPreview() {
            // 检查是否已存在预览容器（可能被隐藏了）
            if (this.$preview.length) {
                // 如果已存在，直接返回，不重复创建
                return;
            }

            // 根据字段配置设置预览容器的尺寸
            let previewStyle = 'max-width: 300px; max-height: 200px;';
            
            // 如果字段配置中有自定义预览尺寸，使用自定义尺寸
            const customWidth = this.$container.data('preview-width');
            const customHeight = this.$container.data('preview-height');
            
            if (customWidth) {
                previewStyle = `max-width: ${customWidth}px;`;
            }
            if (customHeight) {
                previewStyle += ` max-height: ${customHeight}px;`;
            }
            
            this.$preview = $(`<div class="pili-media-preview relative group bg-gray-50 border-2 border-dashed border-gray-200 rounded-lg overflow-hidden transition-all duration-200 hover:border-gray-300 mb-4" style="${previewStyle}"></div>`);
            this.$controls.before(this.$preview);
        }

        /**
         * 获取文件类型图标
         * 
         * @param {string} mimeType MIME类型
         * @return {string} 图标HTML
         */
        getFileIcon(mimeType) {
            if (mimeType.startsWith('image/')) {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm16 12V8l-4 4-6-6-6 6v4h16zM9 11a1 1 0 100-2 1 1 0 000 2z"/></svg>';
            } else if (mimeType.startsWith('video/')) {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm6 4v8l6-4-6-4z"/></svg>';
            } else if (mimeType.startsWith('audio/')) {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>';
            } else {
                return '<svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>';
            }
        }

        /**
         * 转义HTML字符
         * 
         * @param {string} text 要转义的文本
         * @return {string} 转义后的文本
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * 处理URL变化
         * 
         * @param {string} url 新的URL
         */
        handleUrlChange(url) {
            if (!url) {
                this.removeMedia();
                return;
            }

            try {
                // 验证URL格式
                new URL(url);
                this.updateHiddenField('url', url);

                // 如果是图片URL，创建预览
                if (this.isImageUrl(url)) {
                    const fakeAttachment = {
                        id: '',
                        url: url,
                        type: 'image',
                        filename: url.split('/').pop() || mediaI18n('imageFile', '图片文件'),
                        alt: '',
                        title: '',
                        description: '',
                        mime: 'image/jpeg'
                    };
                    this.updatePreview(fakeAttachment);
                }
            } catch (e) {
                // URL格式无效，忽略
            }
        }

        /**
         * 检查是否为图片URL
         * 
         * @param {string} url URL地址
         * @return {boolean} 是否为图片
         */
        isImageUrl(url) {
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
            const extension = url.split('.').pop().toLowerCase();
            return imageExtensions.includes(extension);
        }
    }

    /**
     * 初始化所有媒体字段
     */
    function initMediaFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-media-field').each(function () {
            const $this = $(this);
            if ($this.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$this.data('pili-media-initialized')) {
                try {
                    new XunMediaField($this);
                    $this.data('pili-media-initialized', true);
                } catch (error) {
                    console.error('Error initializing media field:', error);
                }
            }
        });
    }

    PILI.registerBoot('media', function ($root) {
        if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
            initMediaFields($root);
            return;
        }
        var tries = 0;
        var timer = setInterval(function () {
            tries += 1;
            if ((typeof wp !== 'undefined' && typeof wp.media !== 'undefined') || tries > 50) {
                clearInterval(timer);
                if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                    initMediaFields($root);
                }
            }
        }, 100);
    });

    function safeInit($root) {
        PILI.boot('media', $root);
    }

    // 页面加载完成后初始化
    $(document).ready(function () {
        safeInit();
    });

    // 动态添加字段时重新初始化
    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
        safeInit($container && $container.length ? $container : undefined);
    });

})(jQuery);