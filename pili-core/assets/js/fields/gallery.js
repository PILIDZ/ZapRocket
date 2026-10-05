/**
 * PILI Framework Gallery Field JavaScript
 * 
 * 现代化图片库字段的JavaScript功能实现。
 * 
 * 功能特性：
 * - WordPress媒体库集成
 * - 拖拽排序
 * - 批量操作
 * - 图片预览
 * - 响应式设计
 * - 键盘导航
 * - 无障碍访问
 * 
 * @package PILI Framework
 * @author  June
 * @since   1.0
 */

(function($) {
    'use strict';

    function galleryI18n(key, fallback) {
        return (PILI.bag('gallery') && PILI.bag('gallery').i18n && PILI.bag('gallery').i18n[key]) || fallback;
    }

    function galleryBag() {
        return (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('gallery')) || {};
    }

    function galleryFormat(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return String(template).replace(/%(\d+\$)?d/g, function() {
            var val = args[i];
            i += 1;
            return val !== undefined ? val : '';
        });
    }

    /**
     * XunGalleryField 类
     * 
     * 管理图片库字段的所有交互功能
     */
    class XunGalleryField {
        
        /**
         * 构造函数
         * 
         * @param {jQuery} $container 字段容器元素
         */
        constructor($container) {
            this.$container = $container;
            this.$input = $container.find('.pili-gallery-input');
            this.$galleryContainer = $container.find('.pili-gallery-container');
            this.$grid = $container.find('.pili-gallery-grid');
            this.$emptyState = $container.find('.pili-gallery-empty-state');
            this.$uploadArea = $container.find('.pili-gallery-upload-area');
            this.$loading = $container.find('.pili-gallery-loading');
            this.$toolbar = $container.find('.pili-gallery-toolbar');
            this.config = this.parseConfig();
            this.imageIds = this.parseImageIds();
            this.batchMode = false;
            this.mediaFrame = null;
            this.init();
        }
        
        /**
         * 初始化字段
         */
        init() {
            this.bindEvents();
            this.initSortable();
            this.updateUI();
            this.setupKeyboardNavigation();
        }
        
        /**
         * 解析字段配置
         * 
         * @returns {Object} 配置对象
         */
        parseConfig() {
            const $configScript = this.$container.find('.pili-gallery-config');
            if ($configScript.length > 0) {
                try {
                    return JSON.parse($configScript.text());
                } catch (e) {
                    console.error('Gallery field config parse error:', e);
                }
            }
            return {
                maxFiles: 0,
                minFiles: 0,
                allowedTypes: ['image'],
                maxFileSize: 0,
                enableSorting: true,
                enableBatch: true,
                enablePreview: true,
                previewSize: 'thumbnail',
                gridColumns: { sm: 2, md: 3, lg: 4, xl: 5 },
                messages: {
                    errorMaxFiles: galleryI18n('errorMaxFiles', '最多只能上传 {max} 张图片'),
                    errorMinFiles: galleryI18n('errorMinFiles', '至少需要上传 {min} 张图片'),
                    errorFileType: galleryI18n('errorFileType', '不支持的文件类型'),
                    errorFileSize: galleryI18n('errorFileSize', '文件大小超出限制'),
                    confirmClear: galleryI18n('confirmClear', '确定要清空图片库吗？此操作不可撤销。'),
                    confirmDelete: galleryI18n('confirmDelete', '确定要删除选中的图片吗？此操作不可撤销。'),
                    confirmRemove: galleryI18n('confirmRemove', '确定要移除这张图片吗？'),
                    batchToggle: galleryI18n('batchToggle', '批量操作'),
                    batchExit: galleryI18n('batchExit', '退出批量'),
                }
            };
        }
        
        /**
         * 解析图片ID数组
         * 
         * @returns {Array} 图片ID数组
         */
        parseImageIds() {
            const value = this.$input.val();
            if (!value) return [];
            return value.split(',').map(id => parseInt(id.trim())).filter(id => id > 0);
        }
        
        /**
         * 绑定事件
         */
        bindEvents() {
            this.$container.on('click', '.pili-gallery-add-btn', (e) => {
                e.preventDefault();
                this.openMediaLibrary();
            });
            this.$container.on('click', '.pili-gallery-edit-btn', (e) => {
                e.preventDefault();
                this.openMediaLibrary(true);
            });
            this.$container.on('click', '.pili-gallery-clear-btn', (e) => {
                e.preventDefault();
                this.clearGallery();
            });
            this.$container.on('click', '.pili-gallery-remove-btn', (e) => {
                e.preventDefault();
                const imageId = parseInt($(e.currentTarget).data('image-id'));
                this.removeImage(imageId);
            });
            this.$container.on('click', '.pili-gallery-preview-btn', (e) => {
                e.preventDefault();
                const imageUrl = $(e.currentTarget).data('image-url');
                const imageTitle = $(e.currentTarget).data('image-title');
                this.previewImage(imageUrl, imageTitle);
            });
            if (this.config.enableBatch) {
                this.bindBatchEvents();
            }
            this.bindDragDropEvents();
        }
        
        /**
         * 绑定批量操作事件
         */
        bindBatchEvents() {
            this.$container.on('click', '.pili-gallery-batch-toggle-btn', (e) => {
                e.preventDefault();
                this.toggleBatchMode();
            });
            this.$container.on('click', '.pili-gallery-select-all-btn', (e) => {
                e.preventDefault();
                this.selectAll();
            });
            this.$container.on('click', '.pili-gallery-deselect-all-btn', (e) => {
                e.preventDefault();
                this.deselectAll();
            });
            this.$container.on('click', '.pili-gallery-delete-selected-btn', (e) => {
                e.preventDefault();
                this.deleteSelected();
            });
            this.$container.on('change', '.pili-gallery-item-checkbox', () => {
                this.updateBatchControls();
            });
        }
        
        /**
         * 绑定拖拽上传事件
         */
        bindDragDropEvents() {
            const $dropZone = this.$uploadArea.length > 0 ? this.$uploadArea : this.$container;
            $dropZone.on('dragover dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.addClass('drag-over');
            });
            $dropZone.on('dragleave dragend', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.removeClass('drag-over');
            });
            $dropZone.on('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.removeClass('drag-over');
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFileUpload(files);
                }
            });
        }
        
        /**
         * 初始化拖拽排序
         */
        initSortable() {
            if (!this.config.enableSorting || !this.$grid.length) return;
            this.$grid.sortable({
                items: '.pili-gallery-item',
                handle: '.pili-gallery-drag-handle',
                placeholder: 'pili-gallery-placeholder',
                tolerance: 'pointer',
                cursor: 'grabbing',
                opacity: 0.8,
                start: (event, ui) => {
                    ui.placeholder.height(ui.item.height());
                    ui.placeholder.addClass('border-2 border-dashed border-indigo-300 bg-indigo-50 rounded-lg');
                },
                update: () => {
                    this.updateImageOrder();
                }
            });
        }
        
        /**
         * 设置键盘导航
         */
        setupKeyboardNavigation() {
            this.$container.on('keydown', '.pili-gallery-item', (e) => {
                const $item = $(e.currentTarget);
                const $items = this.$container.find('.pili-gallery-item');
                const currentIndex = $items.index($item);
                switch (e.key) {
                    case 'ArrowRight':
                    case 'ArrowDown':
                        e.preventDefault();
                        const nextIndex = (currentIndex + 1) % $items.length;
                        $items.eq(nextIndex).focus();
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        e.preventDefault();
                        const prevIndex = currentIndex === 0 ? $items.length - 1 : currentIndex - 1;
                        $items.eq(prevIndex).focus();
                        break;
                    case '删除':
                    case 'Backspace':
                        e.preventDefault();
                        const imageId = parseInt($item.data('image-id'));
                        this.removeImage(imageId);
                        break;
                    case 'Enter':
                    case ' ':
                        e.preventDefault();
                        $item.find('.pili-gallery-preview-btn').click();
                        break;
                }
            });
        }

        /**
         * 打开WordPress媒体库
         *
         * @param {boolean} editMode 是否为编辑模式
         */
        openMediaLibrary(editMode = false) {
            // 必须用实例标志：select 闭包若捕获首次 editMode，先「编辑」再「添加」会误走 setImages。
            this._galleryEditMode = editMode === true;
            if (!this.mediaFrame) {
                this.mediaFrame = wp.media({
                    title: galleryI18n('mediaTitle', '选择图片'),
                    button: {
                        text: galleryI18n('mediaButtonText', '使用这些图片')
                    },
                    multiple: true,
                    library: {
                        type: this.config.allowedTypes
                    }
                });
                this.mediaFrame.on('select', () => {
                    const selection = this.mediaFrame.state().get('selection');
                    const newImageIds = [];
                    selection.each((attachment) => {
                        newImageIds.push(parseInt(attachment.get('id'), 10) || 0);
                    });
                    const ids = newImageIds.filter((id) => id > 0);
                    if (this._galleryEditMode) {
                        this.setImages(ids);
                    } else {
                        this.addImages(ids);
                    }
                });
            }
            if (this._galleryEditMode && this.imageIds.length > 0) {
                const selection = this.mediaFrame.state().get('selection');
                selection.reset();
                this.imageIds.forEach(imageId => {
                    const attachment = wp.media.attachment(imageId);
                    attachment.fetch();
                    selection.add(attachment);
                });
            }
            this.mediaFrame.open();
        }

        /**
         * 添加图片到图片库
         *
         * @param {Array} newImageIds 新图片ID数组
         */
        normalizeImageIds(list) {
            const out = [];
            const seen = {};
            (Array.isArray(list) ? list : []).forEach((raw) => {
                const id = parseInt(raw, 10) || 0;
                if (id > 0 && !seen[id]) {
                    seen[id] = 1;
                    out.push(id);
                }
            });
            return out;
        }

        addImages(newImageIds) {
            if (!Array.isArray(newImageIds) || newImageIds.length === 0) return;
            this.imageIds = this.normalizeImageIds(this.imageIds);
            newImageIds = this.normalizeImageIds(newImageIds);
            if (this.config.maxFiles > 0) {
                const totalCount = this.imageIds.length + newImageIds.length;
                if (totalCount > this.config.maxFiles) {
                    const allowedCount = this.config.maxFiles - this.imageIds.length;
                    if (allowedCount <= 0) {
                        this.showError(this.config.messages.errorMaxFiles.replace('{max}', this.config.maxFiles));
                        return;
                    }
                    newImageIds = newImageIds.slice(0, allowedCount);
                    this.showWarning(galleryFormat(galleryI18n('onlyAddMore', '只能再添加 %1$d 张图片，已自动截取前 %2$d 张。'), allowedCount, allowedCount));
                }
            }
            const uniqueIds = newImageIds.filter(id => this.imageIds.indexOf(id) < 0);
            if (uniqueIds.length === 0) {
                this.showWarning(galleryI18n('imagesAlreadyExist', '所选图片已存在于图片库中。'));
                return;
            }
            this.imageIds = this.imageIds.concat(uniqueIds);
            this.updateValue();
            this.renderImages();
            this.updateUI();
        }

        /**
         * 设置图片库（替换所有图片）
         *
         * @param {Array} imageIds 图片ID数组
         */
        setImages(imageIds) {
            if (!Array.isArray(imageIds)) return;
            imageIds = this.normalizeImageIds(imageIds);
            if (this.config.maxFiles > 0 && imageIds.length > this.config.maxFiles) {
                imageIds = imageIds.slice(0, this.config.maxFiles);
                this.showWarning(galleryFormat(galleryI18n('maxFilesTrimmed', '最多只能选择 %1$d 张图片，已自动截取前 %2$d 张。'), this.config.maxFiles, this.config.maxFiles));
            }
            this.imageIds = imageIds;
            this.updateValue();
            this.renderImages();
            this.updateUI();
            this.showSuccess(galleryFormat(galleryI18n('galleryUpdated', '图片库已更新，共 %d 张图片。'), imageIds.length));
        }

        /**
         * 移除单张图片
         *
         * @param {number} imageId 图片ID
         */
        removeImage(imageId) {
            if (PILI.confirm) {
                PILI.confirm({
                    title: galleryI18n('confirmRemoveTitle', '确认移除'),
                    message: this.config.messages.confirmRemove || galleryI18n('confirmRemove', '确定要移除这张图片吗？'),
                    type: 'warning',
                    confirmText: galleryI18n('remove', '移除'),
                    cancelText: galleryI18n('cancel', '取消'),
                }).then((ok) => {
                    if (ok) {
                        this._doRemoveImage(imageId);
                    }
                });
            } else {
                if (!confirm(this.config.messages.confirmRemove)) return;
                this._doRemoveImage(imageId);
            }
        }

        /**
         * 清空图片库
         */
        clearGallery() {
            const proceed = () => {
                this.imageIds = [];
                this.updateValue();
                this.renderImages();
                this.updateUI();
                if (PILI.alert) {
                    PILI.alert({ title: galleryI18n('successTitle', '成功'), message: galleryI18n('galleryCleared', '图片库已清空。'), type: 'success' });
                } else {
                    alert(galleryI18n('galleryCleared', '图片库已清空。'));
                }
            };
            if (PILI.confirm) {
                PILI.confirm({
                    title: galleryI18n('confirmClearTitle', '确认清空'),
                    message: this.config.messages.confirmClear || galleryI18n('confirmClear', '确定要清空图片库吗？此操作不可撤销。'),
                    type: 'warning',
                    confirmText: galleryI18n('clear', '清空'),
                    cancelText: galleryI18n('cancel', '取消'),
                }).then((ok) => { if (ok) proceed(); });
            } else {
                if (!confirm(this.config.messages.confirmClear)) return;
                proceed();
            }
        }

        /**
         * 更新图片顺序（拖拽排序后调用）
         */
        updateImageOrder() {
            const newOrder = [];
            this.$grid.find('.pili-gallery-item').each(function() {
                const imageId = parseInt($(this).data('image-id'));
                if (imageId > 0) {
                    newOrder.push(imageId);
                }
            });
            this.imageIds = newOrder;
            this.updateValue();
        }

        /**
         * 切换批量模式
         */
        toggleBatchMode() {
            this.batchMode = !this.batchMode;
            if (this.batchMode) {
                this.$container.addClass('batch-mode');
                this.$container.find('.pili-gallery-batch-controls').removeClass('hidden').addClass('flex');
                this.$container.find('.pili-gallery-batch-toggle-btn').text(this.config.messages.batchExit || galleryI18n('batchExit', '退出批量'));
            } else {
                this.$container.removeClass('batch-mode');
                this.$container.find('.pili-gallery-batch-controls').addClass('hidden').removeClass('flex');
                this.$container.find('.pili-gallery-batch-toggle-btn').text(this.config.messages.batchToggle || galleryI18n('batchToggle', '批量操作'));
                this.deselectAll();
            }
        }

        /**
         * 全选图片
         */
        selectAll() {
            this.$container.find('.pili-gallery-item-checkbox').prop('checked', true);
            this.updateBatchControls();
        }

        /**
         * 取消全选
         */
        deselectAll() {
            this.$container.find('.pili-gallery-item-checkbox').prop('checked', false);
            this.updateBatchControls();
        }

        /**
         * 删除选中的图片
         */
        deleteSelected() {
            const selectedIds = [];
            this.$container.find('.pili-gallery-item-checkbox:checked').each(function() {
                selectedIds.push(parseInt($(this).val()));
            });
            if (selectedIds.length === 0) {
                if (PILI.alert) {
                    PILI.alert({ title: galleryI18n('noticeTitle', '提示'), message: galleryI18n('selectImagesFirst', '请先选择要删除的图片。'), type: 'warning' });
                } else {
                    alert(galleryI18n('selectImagesFirst', '请先选择要删除的图片。'));
                }
                return;
            }
            const proceed = () => {
                this.imageIds = this.imageIds.filter(id => !selectedIds.includes(id));
                this.updateValue();
                this.renderImages();
                this.updateUI();
                if (PILI.alert) {
                    PILI.alert({ title: galleryI18n('successTitle', '成功'), message: galleryFormat(galleryI18n('imagesDeleted', '成功删除 %d 张图片。'), selectedIds.length), type: 'success' });
                } else {
                    alert(galleryFormat(galleryI18n('imagesDeleted', '成功删除 %d 张图片。'), selectedIds.length));
                }
            };
            if (PILI.confirm) {
                PILI.confirm({
                    title: galleryI18n('confirmDeleteTitle', '确认删除'),
                    message: this.config.messages.confirmDelete || galleryI18n('confirmDelete', '确定要删除选中的图片吗？此操作不可撤销。'),
                    type: 'warning',
                    confirmText: galleryI18n('delete', '删除'),
                    cancelText: galleryI18n('cancel', '取消'),
                }).then((ok) => { if (ok) proceed(); });
            } else {
                if (!confirm(this.config.messages.confirmDelete)) return;
                proceed();
            }
        }

        _doRemoveImage(imageId) {
            const index = this.imageIds.indexOf(imageId);
            if (index > -1) {
                this.imageIds.splice(index, 1);
                this.updateValue();
                this.renderImages();
                this.updateUI();
                if (PILI.alert) {
                    PILI.alert({ title: galleryI18n('successTitle', '成功'), message: galleryI18n('imageRemoved', '图片已移除。'), type: 'success' });
                } else {
                    alert(galleryI18n('imageRemoved', '图片已移除。'));
                }
            }
        }

        /**
         * 更新批量操作控件状态
         */
        updateBatchControls() {
            const $checkboxes = this.$container.find('.pili-gallery-item-checkbox');
            const $checked = $checkboxes.filter(':checked');
            this.$container.find('.pili-gallery-delete-selected-btn').toggleClass('opacity-50 cursor-not-allowed', $checked.length === 0);
        }

        /**
         * 预览图片
         *
         * @param {string} imageUrl 图片URL
         * @param {string} imageTitle 图片标题
         */
        previewImage(imageUrl, imageTitle) {
            const modal = $(`
                <div class="pili-gallery-preview-modal fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="z-index: 999999;">
                    <div class="relative max-w-4xl max-h-full p-4">
                        <button type="button" class="absolute -top-12 right-0 p-2 bg-opacity-20 text-white rounded-full hover:bg-opacity-30 transition-all duration-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <img src="${imageUrl}" alt="${imageTitle}" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
                        ${imageTitle ? `<div class="absolute -bottom-12 left-0 right-0 p-2 text-white text-center"><p class="text-lg font-medium bg-black bg-opacity-50 rounded px-3 py-1">${imageTitle}</p></div>` : ''}
                    </div>
                </div>
            `);
            $('body').append(modal);
            modal.on('click', function(e) {
                if (e.target === this || $(e.target).closest('button').length > 0) {
                    modal.remove();
                }
            });
            $(document).on('keydown.gallery-preview', function(e) {
                if (e.key === 'Escape') {
                    modal.remove();
                    $(document).off('keydown.gallery-preview');
                }
            });
        }

        /**
         * 处理文件上传（拖拽上传）
         *
         * @param {FileList} files 文件列表
         */
        handleFileUpload(files) {
            this.openMediaLibrary();
        }

        /**
         * 重新渲染图片网格
         */
        renderImages() {
            if (this.imageIds.length === 0) {
                this.$grid.empty();
                return;
            }
            this.loadImagesData();
        }

        /**
         * 加载图片数据并渲染
         */
        loadImagesData() {
            if (this.imageIds.length === 0) return;
            this.imageIds = this.normalizeImageIds(this.imageIds);
            const $loading = this.$loading && this.$loading.length
                ? this.$loading
                : this.$container.find('.pili-gallery-loading');
            const hideLoading = () => {
                if ($loading && $loading.length) {
                    $loading.addClass('hidden');
                }
            };
            if ($loading && $loading.length) {
                $loading.removeClass('hidden');
            }
            const gBag = galleryBag();
            const action =
                gBag.actionGetImages ||
                'pili_get_gallery_images';
            const form = this.$container && this.$container.length
                ? this.$container.closest('form.pili-form, .pili-framework-page')[0]
                : null;
            const optionId =
                (gBag.optionId) ||
                (form && form.getAttribute && form.getAttribute('data-option-id')) ||
                (window.PILI && PILI.resolveOptionId && PILI.resolveOptionId(form || this.$container && this.$container[0])) ||
                '';
            let nonce = gBag.nonce || '';
            if (form) {
                const formNonce = form.querySelector && form.querySelector('input[name="PILI_Options_nonce"]');
                if (formNonce && formNonce.value) {
                    nonce = formNonce.value;
                }
            }
            const ajax = (window.PILI && PILI.ajaxCfg) ? PILI.ajaxCfg(form || null) : (window.piliAjax || {});
            const data = {
                action: action,
                image_ids: this.imageIds,
                preview_size: this.config.previewSize || 'thumbnail',
                nonce: nonce || (ajax && ajax.nonce) || ''
            };
            if (optionId) {
                data.option_id = optionId;
            }
            // 兜底：请求异常挂起时不永久转圈
            const safetyTimer = setTimeout(hideLoading, 20000);
            $.post(gBag.ajaxUrl || (ajax && ajax.ajaxurl) || window.ajaxurl || '', data)
                .done((response) => {
                    if (response && response.success && response.data) {
                        this.renderImageItems(response.data);
                    } else {
                        this.renderImageItemsSimple();
                    }
                })
                .fail(() => {
                    this.renderImageItemsSimple();
                })
                .always(() => {
                    clearTimeout(safetyTimer);
                    hideLoading();
                });
        }

        /**
         * 渲染图片项（使用AJAX数据）
         */
        renderImageItems(imagesData) {
            this.$grid.empty();
            imagesData.forEach(imageData => {
                const $item = this.createImageItem(imageData);
                this.$grid.append($item);
            });
            if (this.config.enableSorting) {
                this.initSortable();
            }
        }

        /**
         * 简单渲染图片项（备用方案）
         */
        renderImageItemsSimple() {
            this.$grid.empty();
            this.imageIds.forEach(imageId => {
                const $item = this.createImageItemSimple(imageId);
                this.$grid.append($item);
            });
            if (this.config.enableSorting) {
                this.initSortable();
            }
        }

        /**
         * 创建图片项元素（使用完整数据）
         */
        createImageItem(imageData) {
            const $item = $(`
                <div class="pili-gallery-item group relative bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200" data-image-id="${imageData.id}">
                    ${this.config.enableBatch ? `
                    <div class="pili-gallery-checkbox absolute top-2 left-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <input type="checkbox" class="pili-gallery-item-checkbox w-4 h-4 text-indigo-600 bg-white border-gray-300 rounded focus:ring-indigo-500 focus:ring-2" value="${imageData.id}">
                    </div>
                    ` : ''}

                    <div class="aspect-square relative overflow-hidden">
                        <img src="${imageData.thumbnail}" alt="${imageData.alt}" class="absolute inset-0 w-full h-full object-cover" loading="lazy">

                        <div class="pili-gallery-item-veil absolute inset-0 bg-transparent transition-all duration-200 flex items-center justify-center pointer-events-none">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-2 pointer-events-auto">
                                ${this.config.enablePreview ? `
                                <button type="button" class="pili-gallery-preview-btn p-2 bg-white bg-opacity-90 rounded-full text-gray-700 hover:bg-opacity-100 transition-all duration-200" data-image-url="${imageData.full}" data-image-title="${imageData.title}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                                ` : ''}

                                <button type="button" class="pili-gallery-remove-btn p-2 bg-red-500 bg-opacity-90 rounded-full text-white hover:bg-opacity-100 transition-all duration-200" data-image-id="${imageData.id}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-3">
                        <p class="text-sm font-medium text-gray-900 truncate" title="${imageData.title}">${imageData.title}</p>
                        <p class="text-xs text-gray-500 mt-1">ID: ${imageData.id}</p>
                    </div>

                    ${this.config.enableSorting ? `
                    <div class="pili-gallery-drag-handle absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-move p-1 bg-white bg-opacity-90 rounded">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                        </svg>
                    </div>
                    ` : ''}
                </div>
            `);

            return $item;
        }

        /**
         * 创建简单图片项元素（备用方案）
         */
        createImageItemSimple(imageId) {
            const $item = $(`
                <div class="pili-gallery-item group relative bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200" data-image-id="${imageId}">
                    <div class="aspect-square relative overflow-hidden bg-gray-100 flex items-center justify-center">
                        <div class="absolute inset-0 flex items-center justify-center text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>

                        <div class="pili-gallery-item-veil absolute inset-0 bg-transparent transition-all duration-200 flex items-center justify-center pointer-events-none">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-2 pointer-events-auto">
                                <button type="button" class="pili-gallery-remove-btn p-2 bg-red-500 bg-opacity-90 rounded-full text-white hover:bg-opacity-100 transition-all duration-200" data-image-id="${imageId}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-3">
                        <p class="text-sm font-medium text-gray-900">${galleryFormat(galleryI18n('imageIdLabel', '图片 ID: %d'), imageId)}</p>
                        <p class="text-xs text-gray-500 mt-1">${galleryI18n('loading', '加载中…')}</p>
                    </div>
                </div>
            `);

            return $item;
        }

        /**
         * 更新隐藏输入框的值
         */
        updateValue() {
            this.$input.val(this.imageIds.join(','));
            this.$input.trigger('change');
        }

        /**
         * 更新UI状态
         */
        updateUI() {
            const hasImages = this.imageIds.length > 0;
            this.$galleryContainer.toggleClass('pili-gallery-empty', !hasImages);
            this.$emptyState.toggle(!hasImages);
            this.$grid.toggle(hasImages);
            this.$toolbar.find('.pili-gallery-edit-btn').toggle(hasImages);
            this.$toolbar.find('.pili-gallery-clear-btn').toggle(hasImages);
            this.$toolbar.find('.pili-gallery-batch-toggle-btn').toggle(hasImages && this.config.enableBatch);
            if (this.config.enableBatch) {
                this.updateBatchControls();
            }
        }

        /**
         * 显示成功消息
         *
         * @param {string} message 消息内容
         */
        showSuccess(message) {
            this.showNotice(message, 'success');
        }

        /**
         * 显示警告消息
         *
         * @param {string} message 消息内容
         */
        showWarning(message) {
            this.showNotice(message, 'warning');
        }

        /**
         * 显示错误消息
         *
         * @param {string} message 消息内容
         */
        showError(message) {
            this.showNotice(message, 'error');
        }

        /**
         * 显示通知消息
         *
         * @param {string} message 消息内容
         * @param {string} type 消息类型：success, warning, error
         */
        showNotice(message, type = 'info') {
            const colors = {
                success: 'bg-green-100 border-green-400 text-green-700',
                warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
                error: 'bg-red-100 border-red-400 text-red-700',
                info: 'bg-blue-100 border-blue-400 text-blue-700'
            };
            const notice = $(`
                <div class="pili-gallery-notice ${colors[type]} border px-4 py-3 rounded mb-4 relative">
                    <span class="block sm:inline">${message}</span>
                    <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg class="fill-current h-6 w-6" role="button" viewBox="0 0 20 20">
                            <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                        </svg>
                    </button>
                </div>
            `);
            this.$container.prepend(notice);
            notice.find('button').on('click', function() { notice.remove(); });
            setTimeout(() => { notice.fadeOut(300, function() { $(this).remove(); }); }, 5000);
        }
    }

    /**
     * 初始化所有gallery字段
     */
    function initGalleryFields($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.pili-gallery-field').each(function() {
            const $container = $(this);
            if ($container.closest('.pili-repeater-template').length) {
                return;
            }
            if (!$container.data('pili-gallery-instance')) {
                const instance = new XunGalleryField($container);
                $container.data('pili-gallery-instance', instance);
            }
        });
    }

    PILI.registerBoot('gallery', function ($root) {
        if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
            initGalleryFields($root);
            return;
        }
        var tries = 0;
        var timer = setInterval(function () {
            tries += 1;
            if ((typeof wp !== 'undefined' && typeof wp.media !== 'undefined') || tries > 50) {
                clearInterval(timer);
                if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                    initGalleryFields($root);
                }
            }
        }, 100);
    });

    $(document).ready(function() {
        PILI.boot('gallery');
    });

    $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function(e, $container) {
        PILI.boot('gallery', $container && $container.length ? $container : undefined);
    });

    /**
     * 导出到全局作用域（用于调试）
     */
    PILI.GalleryField = XunGalleryField;

})(jQuery);
