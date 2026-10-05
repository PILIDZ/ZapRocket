/**
 * XUN Table Field JavaScript
 * 支持：搜索、排序、分页、CSV 导出、清空、批量删除、表头全选、Shift 连选
 * build: 2026-09-25-server-paged-framework-fetch
 *
 * 使用规范（后续开发必看）：
 * 1) 想要“删除已选/清空/勾选批量操作”真正落库，必须在 PHP 字段配置里显式传入：
 *    - delete_action / delete_nonce
 *    - clear_action  / clear_nonce
 *    - selection_actions（可选：批量审核等，结构见 table.php）
 *    否则本组件只会做前端本地删除（不改数据库）。
 *
 * 2) table 字段建议最小配置：
 *    - row_key（每行唯一主键，如 id / order_no）
 *    - data_callback（返回表格行数据）
 *    - delete_action / clear_action（需要真删库时）
 *
 * 3) 后端必须提供对应 AJAX：
 *    - wp_ajax_{delete_action}
 *    - wp_ajax_{clear_action}
 *    - wp_ajax_{selection_actions[].action}
 *    并完成 nonce + capability 校验，最终返回标准 JSON：
 *    { success: boolean, data: { message: string, ... } }
 *
 * 4) 参数约定：
 *    - 批量删除 / selection_actions：POST ids[]（值来自前端 row_key）
 *    - selection_actions 还可附带 body 里的额外字段（如 status）
 *    - 清空：无需 ids，仅 action + nonce
 *
 * 5) 常见误区：
 *    - 只配置了 row_key / data_callback，但没配置 delete_action/clear_action
 *      => 按钮看起来可点，但不会真正删除数据库记录。
 *    - 配置了 action，但后端未 add_action('wp_ajax_xxx', ...)
 *      => 会提示删除/清空失败。
 *
 * 6) 操作列异步按钮（加载态）：
 *    - PHP：PILI_Field_table::render_action_button() + render_action_buttons_wrap()
 *    - JS：XunTableField.runAction(fieldId, actionKey, worker, options)
 *    - worker 内可用 ctx.setLoadingLabel('…') 切换阶段文案
 *    - 更新单元格：XunTableField.updateCell(fieldId, rowKey, '列标题片段', value)
 *
 * 7) 单元格超长省略（组件默认规范，避免业务侧重复造轮子）：
 *    - 纯文本列：未写 truncate 时默认截断约 40 字（表级可用 default_truncate 覆盖/关闭）
 *    - allow_html 列：默认不截断（进度条/徽标等）；需要时显式 truncate
 *    - columns[].truncate => false|true|字数；可选 truncate_length
 *    - 渲染为「预览… [查看]」，点击走 xunAlert 看全文（HTML 列保留消毒后的内容）
 *
 * 8) 多选 / Shift（2026-09-14）：
 *    - 锚点与上次自动区间挂在 state（lastAnchorKey / lastAutoRangeKeys），可被外部重置
 *    - Shift 区间仅基于「当前页可见、可选」行；禁止跨页算选区
 *    - 业务换页请调 XunTableField.clearSelection($field)，勿只 clear Set
 *
 * 9) 服务端分页双模式（同站插件+主题隔离）：
 *    - PHP server_paged => data-server-paged + data-server-fetch：框架自拉 pili_load_field_data
 *    - 业务外挂（任务队列）仅 data-server-paged：框架只通知 pili:table:perpage，不抢 AJAX
 */
(function ($) {
  'use strict';

  function tableBag() {
    return (typeof PILI !== 'undefined' && typeof PILI.bag === 'function' && PILI.bag('table')) || {};
  }

  function tableI18n(key, fallback) {
    var bag = (tableBag().i18n) || {};
    if (bag[key]) {
      return bag[key];
    }
    if (typeof window.pilipost__ === 'function' && fallback) {
      return window.pilipost__(fallback);
    }
    return fallback || key;
  }

  function tableSprintf(template) {
    var args = Array.prototype.slice.call(arguments, 1);
    var i = 0;
    if (typeof window.pilipostSprintf === 'function') {
      return window.pilipostSprintf.apply(window, [template].concat(args));
    }
    return String(template).replace(/%(\d+)\$[sd]|%[sd]/g, function (m, n) {
      if (n) {
        var idx = parseInt(n, 10) - 1;
        return idx >= 0 && idx < args.length ? String(args[idx]) : m;
      }
      return i < args.length ? String(args[i++]) : m;
    });
  }

  function getAjaxConfig() {
    var ns =
      (window.piliAjax && window.piliAjax.ajaxNs) ||
      (window.piliRuntime && window.piliRuntime.ajaxNs) ||
      'pili';
    return {
      url: (window.piliAjax && window.piliAjax.ajaxurl) || window.ajaxurl || '',
      nonce: window.piliAjax && window.piliAjax.nonce ? window.piliAjax.nonce : '',
      ajaxNs: String(ns),
      loadFieldAction: String(ns) + '_load_field_data',
    };
  }

  function resolveFieldId($field) {
    return (
      $field.attr('data-lazy-field-id') ||
      $field.closest('[data-field-id]').attr('data-field-id') ||
      ''
    );
  }

  function isFrameworkServerFetch($field) {
    return $field && $field.length && $field.attr('data-server-fetch') === '1';
  }

  function isServerPagedAttr($field) {
    return $field && $field.length && $field.attr('data-server-paged') === '1';
  }

  function fetchLazyTableData($field, done, opts) {
    var cfg = getAjaxConfig();
    var fieldId = resolveFieldId($field);
    if (!fieldId || !cfg.url) {
      done(new Error('缺少 field_id 或 ajaxurl'));
      return;
    }
    opts = opts || {};
    var state = $field.data('xunTableState') || {};
    var page = opts.page != null ? opts.page : (state.page || 1);
    var perPage = opts.perPage != null ? opts.perPage : (state.pageSize || parseInt($field.attr('data-page-size') || '10', 10) || 10);
    var search = opts.search != null ? opts.search : (($field.find('.pili-table-search').val() || '').toString());
    var payload = {
      // 与 admin-options 注册的 wp_ajax_{ajaxNs}_load_field_data 对齐（宿主可为 pilidoc_pili）
      action: cfg.loadFieldAction || 'pili_load_field_data',
      nonce: cfg.nonce,
      field_id: fieldId,
      unique: $field.attr('data-unique') || (window.piliRuntime && window.piliRuntime.optionId) || '',
      option_id: $field.attr('data-unique') || (window.piliRuntime && window.piliRuntime.optionId) || '',
    };
    var $secWrap = $field.closest('[data-section]');
    if ($secWrap.length) {
      payload.section = $secWrap.attr('data-section') || $secWrap.attr('data-section-id') || '';
    } else if (location.hash && location.hash.length > 1) {
      payload.section = location.hash.replace(/^#/, '');
    }
    if (isFrameworkServerFetch($field)) {
      payload.page = page;
      payload.per_page = perPage;
      payload.search = search;
    }
    $.ajax({
      url: cfg.url,
      type: 'POST',
      dataType: 'json',
      data: payload,
    })
      .done(function (res) {
        if (!res || !res.success || !res.data || res.data.type !== 'table') {
          done(new Error(res && res.data && res.data.message ? res.data.message : (typeof window.pilipost__ === 'function' ? window.pilipost__('表格加载失败') : '表格加载失败')));
          return;
        }
        $field.find('tbody').html(res.data.tbody || '');
        $field.data('xunTableDataLoaded', true);
        $field.data('xunTableLastPayload', res.data);
        if (isFrameworkServerFetch($field)) {
          var st = $field.data('xunTableState');
          if (st) {
            st.serverTotal = typeof res.data.total === 'number' ? res.data.total : parseInt(res.data.total, 10) || 0;
            if (res.data.page) {
              st.page = Math.max(1, parseInt(res.data.page, 10) || 1);
            }
            if (res.data.per_page) {
              st.pageSize = Math.max(1, parseInt(res.data.per_page, 10) || st.pageSize);
            }
          }
        }
        mountCellEditors($field);
        done(null, res.data);
      })
      .fail(function () {
        done(new Error('表格数据请求失败'));
      });
  }

  /**
   * 无整页刷新：重新拉取 tbody 并重建表格状态。
   * @param {string|Element|jQuery} fieldRef 字段 id 或表格节点
   * @param {function(Error=)=} done 完成回调
   */
  function refresh(fieldRef, done) {
    var $field = resolveTableField(fieldRef);
    if (!$field.length) {
      if (typeof done === 'function') {
        done(new Error('未找到表格'));
      }
      return;
    }
    var state = $field.data('xunTableState') || {};
    var opts = isFrameworkServerFetch($field)
      ? { page: state.page || 1, perPage: state.pageSize || parseInt(readDataAttr($field, 'page-size', '10'), 10) || 10 }
      : undefined;
    fetchLazyTableData($field, function (err) {
      if (err) {
        if (typeof done === 'function') {
          done(err);
        }
        return;
      }
      var st = $field.data('xunTableState');
      if (st) {
        if (st.selectedRowKeys && typeof st.selectedRowKeys.clear === 'function') {
          st.selectedRowKeys.clear();
        }
        resetShiftRangeState(st);
        reloadTableRows(st, { force: true });
      } else {
        // 尚未 init 时走 boot
        $field.removeData('xunTableInited');
        initOne($field);
      }
      if (typeof done === 'function') {
        done(null);
      }
    }, opts);
  }

  function toRows($tbody) {
    return $tbody.find('tr').toArray();
  }

  function cellText($tr, colIdx, selectable) {
    var offset = selectable ? 1 : 0;
    var $td = $tr.children().eq(colIdx + offset);
    var $full = $td.find('.pili-table-cell-full').first();
    if ($full.length) {
      return ($full.text() || '').trim();
    }
    return ($td.text() || '').trim();
  }

  /**
   * 表格 [查看] 全文 / 进度详情：统一 XunDialog（xunAlert），与顶栏「重置当前页」同壳。
   * 禁止再走 PiliXunModal，避免多套弹窗样式。
   */
  function showCellFullContent($btn) {
    var title =
      String($btn.attr('data-expand-title') || '').trim() ||
      tableI18n('viewFull', '完整内容');
    var mode = String($btn.attr('data-expand-mode') || 'text');
    var $full = $btn.closest('.pili-table-cell-clip').find('.pili-table-cell-full').first();
    var plain = ($full.text() || '').trim();
    var html = $full.length ? String($full.html() || '') : '';
    if (!plain && !html) {
      plain = tableI18n('viewFullEmpty', '（无内容）');
    }

    var bodyStyleBase =
      'max-height:min(60vh,28rem);overflow:auto;text-align:left;word-break:break-word;font-size:13px;line-height:1.55;color:#1f2937;';
    var bodyStyleText = bodyStyleBase + 'white-space:pre-wrap;';
    var bodyStyleHtml = bodyStyleBase + 'white-space:normal;';

    // 对齐「重置当前页」壳层；吉祥物随机开场 + 弹窗期间表情轮换。
    var botPool = [
      'peek',
      'lookLeft',
      'lookRight',
      'happy',
      'winkSoft',
      'winkHi',
      'listening',
      'focused',
      'idle',
      'cute',
      'winkCute'
    ];
    var alertOpts = {
      title: title,
      type: 'info',
      state: botPool[Math.floor(Math.random() * botPool.length)],
      botCycle: true,
      botCycleStates: botPool,
      botCycleMs: 2800,
      confirmText: tableI18n('ok', '确定'),
    };

    if (typeof PILI.alert === 'function') {
      if (mode === 'html' && html) {
        alertOpts.html =
          '<div class="pili-table-expand-body pili-table-expand-body--html" style="' +
          bodyStyleHtml +
          '">' +
          html +
          '</div>';
        PILI.alert(alertOpts);
        return;
      }
      alertOpts.html =
        '<div class="pili-table-expand-body" style="' +
        bodyStyleText +
        '">' +
        escapeHtml(plain) +
        '</div>';
      PILI.alert(alertOpts);
      return;
    }
    if (window.PilipostUi && typeof window.PilipostUi.alert === 'function') {
      window.PilipostUi.alert(plain, { title: title, type: 'info' });
      return;
    }
    window.alert(title + '\n\n' + plain);
  }

  function compareText(a, b) {
    var na = Number(a);
    var nb = Number(b);
    if (!Number.isNaN(na) && !Number.isNaN(nb)) return na - nb;
    return a.localeCompare(b, 'zh-Hans-CN', { numeric: true, sensitivity: 'base' });
  }

  function csvEscape(v) {
    var s = String(v == null ? '' : v);
    if (s.includes('"') || s.includes(',') || s.includes('\n')) {
      return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
  }

  function readDataAttr($el, key, fallback) {
    if (!$el || !$el.length) {
      return fallback !== undefined ? fallback : '';
    }
    var attrVal = $el.attr('data-' + key);
    if (attrVal !== undefined && attrVal !== null && attrVal !== '') {
      return attrVal;
    }
    var camel = key.replace(/-([a-z])/g, function (_, c) {
      return c.toUpperCase();
    });
    var dataVal = $el.data(camel);
    if (dataVal !== undefined && dataVal !== null && dataVal !== '') {
      return dataVal;
    }
    return fallback !== undefined ? fallback : '';
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function parseEmptyCopy(emptyText) {
    var text = String(emptyText || '').trim() || tableI18n('empty', '暂无数据');
    var match = text.match(/^(.+?)[。．.!！？?](.+)$/);
    if (match) {
      return {
        title: String(match[1] || '').trim(),
        hint: String(match[2] || '').trim()
      };
    }
    return { title: text, hint: '' };
  }

  function buildEmptyStateHtml(emptyText) {
    var copy = parseEmptyCopy(emptyText);
    var html =
      '<div class="pili-table-empty" role="status">' +
      '<div class="pili-table-empty__icon" aria-hidden="true">' +
      '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 7.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.5"></path>' +
      '<path d="M3 7.5 5.4 4.2A2 2 0 0 1 7.1 3.5h9.8a2 2 0 0 1 1.7.7L21 7.5"></path>' +
      '<path d="M9.5 12.5h5"></path>' +
      '</svg>' +
      '</div>' +
      '<p class="pili-table-empty__title">' +
      escapeHtml(copy.title) +
      '</p>';
    if (copy.hint) {
      html += '<p class="pili-table-empty__hint">' + escapeHtml(copy.hint) + '</p>';
    }
    html += '</div>';
    return html;
  }

  function buildEmptyRowHtml(colspan, emptyText) {
    return (
      '<tr class="pili-table-empty-row"><td class="pili-table-empty-cell" colspan="' +
      colspan +
      '">' +
      buildEmptyStateHtml(emptyText) +
      '</td></tr>'
    );
  }

  function readRowKey($tr) {
    if (!$tr || !$tr.length) {
      return '';
    }
    var key = String(readDataAttr($tr, 'row-key', '') || '');
    if (key) {
      return key;
    }
    return String($tr.find('.pili-table-row-radio').val() || '');
  }

  function liveTbody($field) {
    return $field.find('.pili-table-grid tbody').first();
  }

  function isDataRow(tr) {
    var $tr = $(tr);
    if ($tr.hasClass('pili-table-empty-row') || $tr.hasClass('pili-table-lazy-loading')) {
      return false;
    }
    return $tr.hasClass('pili-table-row') || $tr.children('td').length > 1;
  }

  /** 当前页可参与勾选/Shift 的数据行（跳过空态、禁用、无复选框）。 */
  function isSelectableDataRow(tr) {
    if (!isDataRow(tr)) {
      return false;
    }
    var $tr = $(tr);
    if ($tr.attr('data-selectable') === 'false' || $tr.attr('aria-disabled') === 'true') {
      return false;
    }
    if ($tr.hasClass('is-disabled') || $tr.hasClass('pili-table-row-disabled')) {
      return false;
    }
    var $cb = $tr.find('.pili-table-row-radio').first();
    if (!$cb.length || $cb.prop('disabled')) {
      return false;
    }
    return true;
  }

  function resetShiftRangeState(state) {
    if (!state) {
      return;
    }
    state.lastAnchorKey = null;
    state.lastAutoRangeKeys = [];
  }

  /**
   * 从当前 tbody 重建行缓存。
   * 注意：draw() 会 empty tbody 只挂回当前页，其它行仍活在 state.allRows 里（已脱离 DOM）。
   * 全量替换 tbody（lazy / refresh）后再调本函数；禁止在「已分页」后无脑从 DOM 重扫。
   *
   * @param {object} state
   * @param {{force?: boolean}=} opts force=true：以 tbody 为准（软刷/懒加载后）
   */
  function reloadTableRows(state, opts) {
    if (!state || !state.$field || !state.$field.length) {
      return;
    }
    opts = opts || {};
    state.$tbody = liveTbody(state.$field);
    var live = toRows(state.$tbody).filter(isDataRow);
    var prev = Array.isArray(state.allRows) ? state.allRows : [];
    // 框架真分页：tbody 即当前页，force 后以 DOM 为准并用 serverTotal 画脚。
    if (isFrameworkServerFetch(state.$field)) {
      var pending = state.$field.data('xunTableLastPayload');
      if (pending && typeof pending.total !== 'undefined') {
        state.serverTotal = typeof pending.total === 'number' ? pending.total : parseInt(pending.total, 10) || 0;
        if (pending.page) {
          state.page = Math.max(1, parseInt(pending.page, 10) || state.page || 1);
        }
      }
      state.allRows = live;
      state.filteredRows = live.slice();
      state.sortCol = -1;
      state.sortDir = 'asc';
      if (typeof state.draw === 'function') {
        state.draw();
      }
      return;
    }
    // 已分页后 tbody 只有当前页；非 force 时保留内存行，避免二次 boot 丢页。
    if (!opts.force && prev.length > live.length && live.length > 0) {
      if (typeof state.applySearch === 'function') {
        state.applySearch();
      }
      return;
    }
    resetShiftRangeState(state);
    state.allRows = live;
    state.filteredRows = state.allRows.slice();
    state.page = 1;
    state.sortCol = -1;
    state.sortDir = 'asc';
    if (typeof state.applySearch === 'function') {
      state.applySearch();
    }
  }

  function parseSelectionActions($field) {
    var raw = $field.attr('data-selection-actions') || '';
    if (!raw) {
      return [];
    }
    try {
      var list = JSON.parse(raw);
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function mountCellEditors($root) {
    var $scope = $root && $root.length ? $root : $(document);
    $scope.find('.pili-table-cell-editor[data-editor]').each(function () {
      var el = this;
      if (el.getAttribute('data-editor-mounted') === '1') {
        return;
      }
      var type = String(el.getAttribute('data-editor') || '');
      try {
        if (type === 'date' && typeof PILI.mountFn('date') === 'function') {
          PILI.mount('date', el);
          el.setAttribute('data-editor-mounted', '1');
        } else if (type === 'select' && typeof PILI.mountFn('select') === 'function') {
          PILI.mount('select', el);
          el.setAttribute('data-editor-mounted', '1');
        } else if (type === 'text' && typeof PILI.mountFn('text') === 'function') {
          PILI.mount('text', el);
          el.setAttribute('data-editor-mounted', '1');
        }
      } catch (err) {
        // ignore single cell
      }
    });
  }

  /**
   * 表格 editor=text：注入与 XUN text 字段同款输入框（不经 PHP PILI::field）。
   *
   * @param {HTMLElement} host
   * @returns {HTMLInputElement|null}
   */
  PILI.registerMount('text', function (host) {
    if (!host || !host.getAttribute) {
      return null;
    }
    var existing = host.querySelector('input.pili-input');
    if (existing) {
      return existing;
    }
    var settings = {};
    try {
      settings = JSON.parse(host.getAttribute('data-editor-settings') || '{}') || {};
    } catch (err) {
      settings = {};
    }
    var value = String(host.getAttribute('data-editor-value') || '');
    var type = String(settings.type || 'text');
    if (['text', 'email', 'url', 'tel', 'number', 'password'].indexOf(type) === -1) {
      type = 'text';
    }
    var placeholder = String(settings.placeholder || '');
    var inputClass =
      'pili-input pili-focusable block w-full !min-h-10 !h-10 rounded-md bg-white !px-3 !py-2.5 appearance-none !text-base/6 !leading-6 box-border text-gray-900 border border-gray-300 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none sm:text-sm/6';
    host.innerHTML =
      '<div><div class="mt-0">' +
      '<input type="' +
      type +
      '" class="' +
      inputClass +
      '" value="" />' +
      '</div></div>';
    var input = host.querySelector('input.pili-input');
    if (input) {
      input.value = value;
      if (placeholder) {
        input.setAttribute('placeholder', placeholder);
      }
    }
    return input;
  });

  function initOne($field) {
    var needLazyFetch = $field.attr('data-lazy-data') === '1' && !$field.data('xunTableDataLoaded');
    var build = String($field.attr('data-pili-table-build') || tableBag().build || '');

    if (needLazyFetch && $field.data('xunTableLazyLoading')) {
      return;
    }

    if ($field.data('xunTableInited') && String($field.data('xunTableBuild') || '') === build) {
      // 已 init：draw() 后 DOM 仅当前页。再 reloadTableRows 会把 allRows 收成一页。
      // 分区 FieldBoot / 全局 boot 可能二次进入，这里只补挂编辑器。
      mountCellEditors($field);
      return;
    }

    if ($field.data('xunTableInited')) {
      $field.off('.xunTable');
      $field.find('tbody').off('.xunTable');
      $field.removeData('xunTableInited');
      $field.removeData('xunTableState');
      $field.removeData('xunTableSelectedKeys');
    }

    var $table = $field.find('.pili-table-grid').first();
    var $tbody = liveTbody($field);
    var $sortBtns = $field.find('.pili-table-sort');
    var $prev = $field.find('.pili-table-prev');
    var $next = $field.find('.pili-table-next');
    var $pageLabel = $field.find('.pili-table-page');
    var $pages = $field.find('.pili-table-pages');
    var $summary = $field.find('.pili-table-summary');
    var $totalCount = $field.find('.pili-table-total-count');
    var $pageSize = $field.find('.pili-table-page-size');
    var $pageSizeWrap = $field.find('.pili-table-page-size-wrap');
    var $pageSizeDisplay = $pageSizeWrap.find('.pili-select-display');
    var $deleteSelected = $field.find('.pili-table-delete-selected');
    var $selectionActionBtns = $field.find('.pili-table-selection-action');
    var selectionActions = parseSelectionActions($field);

    var selectable = String(readDataAttr($field, 'selectable', 'true')) === 'true';
    var showDelete = String(readDataAttr($field, 'show-delete', selectable ? 'true' : 'false')) === 'true';
    var rowKeyField = readDataAttr($field, 'row-key', 'id') || 'id';
    var deleteAction = readDataAttr($field, 'delete-action', '');
    var deleteNonce = readDataAttr($field, 'delete-nonce', '');
    var deleteConfirmTitle = readDataAttr($field, 'delete-confirm-title', '');
    var deleteConfirmText = readDataAttr($field, 'delete-confirm-text', '');
    var deleteConfirmOk = readDataAttr($field, 'delete-confirm-ok', '');
    var clearAction = readDataAttr($field, 'clear-action', '');
    var clearNonce = readDataAttr($field, 'clear-nonce', '');
    var ajaxUrl = (window.piliAjax && window.piliAjax.ajaxurl) || window.ajaxurl || (PILI.bag('legacyVars') && PILI.bag('legacyVars').ajax_url) || '';

    var selectedRowKeys = new Set();
    var emptyText = readDataAttr($field, 'empty-text', tableI18n('empty', '暂无数据')) || tableI18n('empty', '暂无数据');
    var pageSize = parseInt(readDataAttr($field, 'page-size', '10'), 10);
    if (!pageSize || pageSize < 1) pageSize = 10;
    var frameworkFetch = isFrameworkServerFetch($field);
    var searchTimer = null;
    /** Ctrl/Cmd 点选时禁止把锚点挪到该行（由 click 置位，change 消费）。 */
    var suppressAnchorMove = false;
    /** Shift 连选同步 UI 期间忽略 checkbox change，避免目标行被浏览器默认翻转删掉。 */
    var suppressCheckboxChange = 0;
    /** Shift 已在 mousedown 处理过（避免 click 阶段浏览器再翻目标框）。 */
    var shiftRangeHandledAt = 0;
    var state = {
      $field: $field,
      $tbody: $tbody,
      allRows: toRows($tbody).filter(isDataRow),
      filteredRows: [],
      page: 1,
      sortCol: -1,
      sortDir: 'asc',
      pageSize: pageSize,
      serverTotal: 0,
      applySearch: null,
      draw: null,
      fetchServerPage: null,
      setFilteredSelection: null,
      selectedRowKeys: selectedRowKeys,
      /** Shift 连选锚点（当前页可选行中的 row_key）。 */
      lastAnchorKey: null,
      /** 上次 Shift 自动写入的区间 key（下次 Shift 先撤销再重算）。 */
      lastAutoRangeKeys: [],
    };
    state.filteredRows = state.allRows.slice();
    if (frameworkFetch) {
      var bootPayload = $field.data('xunTableLastPayload');
      if (bootPayload && typeof bootPayload.total !== 'undefined') {
        state.serverTotal = typeof bootPayload.total === 'number' ? bootPayload.total : parseInt(bootPayload.total, 10) || 0;
        if (bootPayload.page) {
          state.page = Math.max(1, parseInt(bootPayload.page, 10) || 1);
        }
      }
    }

    function syncSelectionToolbar() {
      var empty = selectedRowKeys.size === 0;
      if ($deleteSelected.length && showDelete) {
        $deleteSelected.prop('disabled', empty);
      }
      if ($selectionActionBtns.length) {
        $selectionActionBtns.prop('disabled', empty);
      }
    }

    function resetShiftRangeStateLocal() {
      resetShiftRangeState(state);
    }

    /** 当前页可见且可选的有序行（Shift 区间唯一基准，禁止跨页）。 */
    function getPageSelectableRows() {
      return getVisiblePageRows().filter(isSelectableDataRow);
    }

    function pageIndexOfKey(key, rows) {
      if (!key || !rows || !rows.length) {
        return -1;
      }
      for (var i = 0; i < rows.length; i++) {
        if (readRowKey($(rows[i])) === key) {
          return i;
        }
      }
      return -1;
    }

    /** 用户手动点选后，该 key 不再归属「上次自动区间」。 */
    function releaseManualKeyOwnership(key) {
      if (!key || !Array.isArray(state.lastAutoRangeKeys) || !state.lastAutoRangeKeys.length) {
        return;
      }
      state.lastAutoRangeKeys = state.lastAutoRangeKeys.filter(function (k) {
        return k !== key;
      });
    }

    /** 只刷新当前可见行的勾选/高亮，避免 empty+append 导致滚动条跳动。 */
    function syncVisibleSelectionUI() {
      liveTbody($field)
        .children('tr')
        .filter(function () {
          return isDataRow(this);
        })
        .each(function () {
          var $tr = $(this);
          var key = readRowKey($tr);
          var isSelected = key ? selectedRowKeys.has(key) : false;
          $tr.find('.pili-table-row-radio').prop('checked', isSelected);
          applyRowVisualState($tr, isSelected);
        });
      syncSelectionToolbar();
      publishSelection();
      syncSelectAllCheckbox();
    }

    function clearSelectionLocal() {
      selectedRowKeys.clear();
      resetShiftRangeStateLocal();
      suppressAnchorMove = false;
      suppressCheckboxChange = 0;
      shiftRangeHandledAt = 0;
      syncVisibleSelectionUI();
    }

    /** 以 Set 为真源刷 DOM；短暂忽略 change，并在 click 默认翻转后再刷一次。 */
    function reconcileSelectionDomFromSet() {
      suppressCheckboxChange += 1;
      try {
        syncVisibleSelectionUI();
      } finally {
        window.setTimeout(function () {
          suppressCheckboxChange = Math.max(0, suppressCheckboxChange - 1);
        }, 0);
      }
    }

    /**
     * 按「当前页可选行」顺序，勾选锚点到目标之间的整段（含两端、方向无关）。
     * 先撤销上次 Shift 自动区间，再写入新区；手动勾选的其它行保留。
     */
    function selectRangeByKeys(fromKey, toKey) {
      var rows = getPageSelectableRows();
      if (!rows.length) {
        return false;
      }
      var a = pageIndexOfKey(fromKey, rows);
      var b = pageIndexOfKey(toKey, rows);
      if (a < 0 && b < 0) {
        return false;
      }
      if (a < 0) {
        a = b;
      }
      if (b < 0) {
        b = a;
      }
      var prevAuto = Array.isArray(state.lastAutoRangeKeys) ? state.lastAutoRangeKeys.slice() : [];
      prevAuto.forEach(function (k) {
        if (k) {
          selectedRowKeys.delete(k);
        }
      });
      var lo = Math.min(a, b);
      var hi = Math.max(a, b);
      var newAuto = [];
      for (var i = lo; i <= hi; i++) {
        var k = readRowKey($(rows[i]));
        if (!k) {
          continue;
        }
        selectedRowKeys.add(k);
        newAuto.push(k);
      }
      state.lastAutoRangeKeys = newAuto;
      state.lastAnchorKey = toKey || fromKey || null;
      reconcileSelectionDomFromSet();
      // 实测：click 结束后浏览器会把「Shift 点到的那一格」再翻回去（Set 仍对、DOM 少 1）。
      // 下一拍 / rAF 再按 Set 回刷，钉死 UI。
      window.setTimeout(function () {
        reconcileSelectionDomFromSet();
      }, 0);
      if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(function () {
          reconcileSelectionDomFromSet();
        });
      }
      return true;
    }

    var pagerBtnBaseStyle = 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:32px;padding:0 8px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;color:#4b5563;font-size:13px;font-weight:500;line-height:1;cursor:pointer;transition:all .15s ease;';

    function showMsg(msg, type) {
      if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
        window.PilipostUi.toast(msg, type || 'info');
        return;
      }
      if (typeof PILI.alert === 'function') {
        PILI.alert({ title: type === 'success' ? tableI18n('success', '成功') : tableI18n('notice', '提示'), message: msg, type: type || 'info' });
      }
    }

    function applyRowVisualState($tr, isSelected) {
      if (isSelected) {
        $tr.addClass('selected').css('background-color', '#eff6ff');
        $tr.children('td').css('background-color', '#eff6ff');
      } else {
        $tr.removeClass('selected').css('background-color', '#ffffff');
        $tr.children('td').css('background-color', '#ffffff');
      }
    }

    function sortIndicatorHtml(mode) {
      var svgOpen = '<svg class="pili-table-sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
      if (mode === 'asc') {
        return svgOpen + '<polyline points="18 15 12 9 6 15"></polyline></svg>';
      }
      if (mode === 'desc') {
        return svgOpen + '<polyline points="6 9 12 15 18 9"></polyline></svg>';
      }
      return svgOpen + '<polyline points="7 15 12 20 17 15"></polyline><polyline points="7 9 12 4 17 9"></polyline></svg>';
    }

    function setSortIndicator() {
      $sortBtns.each(function () {
        var $btn = $(this);
        var idx = parseInt($btn.data('col-index'), 10);
        var mode = 'none';
        if (idx === state.sortCol) {
          mode = state.sortDir === 'asc' ? 'asc' : 'desc';
        }
        var $ind = $btn.find('.pili-table-sort-flag, .pili-table-sort-indicator');
        $ind
          .removeClass('is-none is-asc is-desc')
          .addClass('is-' + mode)
          .attr('title', mode === 'asc' ? tableI18n('sortAsc', '升序') : (mode === 'desc' ? tableI18n('sortDesc', '降序') : tableI18n('sort', '排序')))
          .html(sortIndicatorHtml(mode));
      });
    }

    function renderPageButtons(totalPages) {
      $pages.empty();
      if (totalPages <= 1) {
        $pages.append('<button type="button" class="pili-table-page-btn" style="' + pagerBtnBaseStyle + 'border-color:#3b82f6;background:#eff6ff;color:#2563eb;font-weight:700;" data-page="1">1</button>');
        return;
      }
      var start = Math.max(1, state.page - 2);
      var end = Math.min(totalPages, start + 4);
      start = Math.max(1, end - 4);
      for (var i = start; i <= end; i += 1) {
        var activeStyle = i === state.page ? 'border-color:#3b82f6;background:#eff6ff;color:#2563eb;font-weight:700;' : 'border-color:#e5e7eb;background:#fff;color:#4b5563;';
        $pages.append('<button type="button" class="pili-table-page-btn" style="' + pagerBtnBaseStyle + activeStyle + '" data-page="' + i + '">' + i + '</button>');
      }
    }

    function publishSelection() {
      var keys = Array.from(selectedRowKeys);
      $field.data('xunTableSelectedKeys', keys);
      $field.attr('data-selected-count', String(keys.length));
      $field.trigger('pili:table:selection', [keys]);
    }

    /** 当前页可见行（表头全选只作用于这些行，以 DOM 为准避免与筛选缓存错位）。 */
    function getVisiblePageRows() {
      return liveTbody($field)
        .children('tr')
        .toArray()
        .filter(isDataRow);
    }

    function syncSelectAllCheckbox() {
      var $all = $field.find('.pili-table-select-all');
      if (!$all.length || !selectable) {
        return;
      }
      var keys = [];
      getPageSelectableRows().forEach(function (tr) {
        var key = readRowKey($(tr));
        if (key) {
          keys.push(key);
        }
      });
      if (keys.length === 0) {
        $all.prop('checked', false);
        $all.prop('indeterminate', false);
        $all.prop('disabled', true);
        return;
      }
      var selectedCount = 0;
      keys.forEach(function (key) {
        if (selectedRowKeys.has(key)) {
          selectedCount += 1;
        }
      });
      $all.prop('disabled', false);
      $all.prop('indeterminate', false);
      if (selectedCount === 0) {
        $all.prop('checked', false);
      } else if (selectedCount === keys.length) {
        $all.prop('checked', true);
      } else {
        $all.prop('checked', false);
        $all.prop('indeterminate', true);
      }
    }

    function draw() {
      $tbody = liveTbody($field);
      state.$tbody = $tbody;

      var total = frameworkFetch ? (state.serverTotal || 0) : state.filteredRows.length;
      var pages = Math.max(1, Math.ceil(total / state.pageSize) || 1);
      if (state.page > pages) state.page = pages;
      if (state.page < 1) state.page = 1;

      if (!frameworkFetch) {
        $tbody.empty();
        if (total === 0) {
          var colspan = Math.max(1, $table.find('thead th').length);
          $tbody.append(buildEmptyRowHtml(colspan, emptyText));
        } else {
          var start = (state.page - 1) * state.pageSize;
          var end = start + state.pageSize;
          state.filteredRows.slice(start, end).forEach(function (tr) {
            var $tr = $(tr);
            if (selectable) {
              var key = readRowKey($tr);
              var isSelected = key ? selectedRowKeys.has(key) : false;
              $tr.find('.pili-table-row-radio').prop('checked', isSelected);
              applyRowVisualState($tr, isSelected);
            }
            $tbody.append($tr);
          });
        }
      } else {
        $tbody.children('tr').filter(function () {
          return isDataRow(this);
        }).each(function () {
          var $tr = $(this);
          if (selectable) {
            var keySp = readRowKey($tr);
            var isSelectedSp = keySp ? selectedRowKeys.has(keySp) : false;
            $tr.find('.pili-table-row-radio').prop('checked', isSelectedSp);
            applyRowVisualState($tr, isSelectedSp);
          }
        });
      }

      var startIndex = total === 0 ? 0 : ((state.page - 1) * state.pageSize + 1);
      var endIndex = total === 0 ? 0 : Math.min(state.page * state.pageSize, total);
      $summary.attr('title', tableSprintf(tableI18n('rangeTitle', '当前显示区间：%1$d-%2$d'), startIndex, endIndex));
      $totalCount.text(total);
      $pageLabel.text(tableSprintf(tableI18n('pageLabel', '第 %1$d / %2$d 页'), state.page, pages));
      $prev.prop('disabled', state.page <= 1).toggleClass('cursor-not-allowed opacity-50', state.page <= 1);
      $next.prop('disabled', state.page >= pages).toggleClass('cursor-not-allowed opacity-50', state.page >= pages);
      syncSelectionToolbar();

      publishSelection();
      syncSelectAllCheckbox();
      renderPageButtons(pages);
      setSortIndicator();
    }

    function fetchServerPage(page, done) {
      if (!frameworkFetch) {
        if (typeof done === 'function') done(null);
        return;
      }
      if ($field.data('xunTableLazyLoading')) {
        return;
      }
      state.page = Math.max(1, page || 1);
      $field.data('xunTableLazyLoading', true);
      $field.data('xunTableDataLoaded', false);
      fetchLazyTableData($field, function (err) {
        $field.data('xunTableLazyLoading', false);
        $field.data('xunTableDataLoaded', true);
        if (err) {
          liveTbody($field).html(
            '<tr><td colspan="99" style="text-align:center;color:#dc2626;border:1px solid #e5e7eb;padding:1rem;">' +
              (err.message || (typeof window.pilipost__ === 'function' ? window.pilipost__('Load failed') : 'Load failed')) +
              '</td></tr>'
          );
          state.serverTotal = 0;
          state.allRows = [];
          state.filteredRows = [];
          draw();
          if (typeof done === 'function') done(err);
          return;
        }
        reloadTableRows(state, { force: true });
        if (typeof done === 'function') done(null);
      }, {
        page: state.page,
        perPage: state.pageSize,
      });
    }

    function ensureRowsSynced() {
      $tbody = liveTbody($field);
      state.$tbody = $tbody;
      var liveRows = toRows($tbody).filter(isDataRow);
      var byKey = {};
      var orphan = [];
      function remember(tr) {
        var key = readRowKey($(tr));
        if (key) {
          byKey[key] = tr;
        } else {
          orphan.push(tr);
        }
      }
      (state.allRows || []).forEach(remember);
      liveRows.forEach(remember);
      state.allRows = Object.keys(byKey)
        .map(function (k) {
          return byKey[k];
        })
        .concat(orphan)
        .filter(isDataRow);
      var q = ($field.find('.pili-table-search').val() || '').toString().trim().toLowerCase();
      state.filteredRows = !q
        ? state.allRows.slice()
        : state.allRows.filter(function (tr) {
            return ($(tr).text() || '').toLowerCase().indexOf(q) !== -1;
          });
    }

    function setFilteredSelection(checked) {
      // 表头全选：仅当前页可见可选行；其它页已勾选的保持不动。
      var pageRows = getPageSelectableRows();
      if (!pageRows.length) {
        syncSelectAllCheckbox();
        return;
      }
      pageRows.forEach(function (tr) {
        var $tr = $(tr);
        var key = readRowKey($tr);
        if (!key) {
          return;
        }
        if (checked) {
          selectedRowKeys.add(key);
        } else {
          selectedRowKeys.delete(key);
        }
        releaseManualKeyOwnership(key);
        $tr.find('.pili-table-row-radio').prop('checked', !!checked);
        applyRowVisualState($tr, !!checked);
      });
      // 全选后锚点落到当前页首行，避免随后 Shift 因无锚点退化。
      state.lastAnchorKey = readRowKey($(pageRows[0])) || null;
      state.lastAutoRangeKeys = [];
      syncSelectionToolbar();
      publishSelection();
      syncSelectAllCheckbox();
    }

    state.setFilteredSelection = setFilteredSelection;

    function applySearch() {
      if (frameworkFetch) {
        resetShiftRangeStateLocal();
        fetchServerPage(1);
        return;
      }
      ensureRowsSynced();
      var q = ($field.find('.pili-table-search').val() || '').toString().trim().toLowerCase();
      state.filteredRows = !q
        ? state.allRows.slice()
        : state.allRows.filter(function (tr) {
            return ($(tr).text() || '').toLowerCase().indexOf(q) !== -1;
          });
      if (state.sortCol >= 0) {
        state.filteredRows.sort(function (a, b) {
          var r = compareText(cellText($(a), state.sortCol, selectable), cellText($(b), state.sortCol, selectable));
          return state.sortDir === 'asc' ? r : -r;
        });
      }
      state.page = 1;
      // 筛选/排序后行序变化：清 Shift 自动区间与锚点，避免脏区间。
      resetShiftRangeStateLocal();
      draw();
    }

    function clearLocalRows() {
      state.allRows = [];
      state.filteredRows = [];
      state.serverTotal = 0;
      selectedRowKeys.clear();
      resetShiftRangeStateLocal();
      state.page = 1;
      state.sortCol = -1;
      state.sortDir = 'asc';
      $field.find('.pili-table-search').val('');
      draw();
      $field.trigger('pili:table:cleared');
    }

    state.applySearch = applySearch;
    state.draw = draw;
    state.fetchServerPage = fetchServerPage;
    state.ensureRowsSynced = ensureRowsSynced;
    state.syncVisibleSelectionUI = syncVisibleSelectionUI;
    state.clearSelectionLocal = clearSelectionLocal;
    state.resetShiftRangeState = resetShiftRangeStateLocal;

    $field.off('.xunTable');
    $field.on('input.xunTable', '.pili-table-search', function () {
      if (frameworkFetch) {
        if (searchTimer) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(function () {
          applySearch();
        }, 320);
        return;
      }
      applySearch();
    });

    $field.on('click.xunTable', '.pili-table-cell-expand', function (e) {
      e.preventDefault();
      e.stopPropagation();
      showCellFullContent($(this));
    });

    $field.on('click.xunTable', '.pili-table-clear-all', function () {
      if (state.allRows.length === 0) {
        showMsg(tableI18n('noClearData', '当前没有可清空的数据。'), 'info');
        return;
      }
      var $btn = $(this);
      var confirmClear = function (done) {
        done(true);
      };
      if (typeof PILI.confirm === 'function') {
        confirmClear = function (done) {
          window
            .PILI.confirm({
              title: tableI18n('confirmClear', '确认清空'),
              message: tableI18n('clearConfirm', '确定要清空当前表格中的所有数据吗？此操作不可撤销。'),
              type: 'warning',
              confirmText: tableI18n('clear', '清空'),
              cancelText: tableI18n('cancel', '取消'),
            })
            .then(function (ok) {
              done(!!ok);
            });
        };
      } else if (window.PilipostUi && typeof window.PilipostUi.confirm === 'function') {
        confirmClear = function (done) {
          window.PilipostUi.confirm(tableI18n('clearConfirm', '确定要清空当前表格中的所有数据吗？此操作不可撤销。'), {
            title: tableI18n('confirmClear', '确认清空'),
            type: 'warning',
            confirmText: tableI18n('clear', '清空'),
            cancelText: tableI18n('cancel', '取消')
          }).then(function (ok) {
            done(!!ok);
          });
        };
      } else {
        confirmClear = function (done) {
          done(false);
        };
      }
      confirmClear(function (ok) {
        if (!ok) return;
        var oldText = $btn.text();
        $btn.prop('disabled', true).text(tableI18n('clearing', '清理中…'));
        if (clearAction && ajaxUrl) {
          var params = new URLSearchParams();
          params.append('action', clearAction);
          if (clearNonce) params.append('nonce', clearNonce);
          fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString(),
          })
            .then(function (res) {
              return res.json();
            })
            .then(function (json) {
              if (!json || !json.success) throw new Error(json && json.data && json.data.message ? json.data.message : tableI18n('clearFail', '清空失败'));
              showMsg(String((json.data && json.data.message) || tableI18n('cleared', '数据已清空。')), 'success');
              window.location.reload();
            })
            .catch(function (err) {
              showMsg(err && err.message ? err.message : tableI18n('clearFail', '清空失败'), 'error');
            })
            .finally(function () {
              $btn.prop('disabled', false).text(oldText);
            });
          return;
        }
        clearLocalRows();
        showMsg(tableI18n('clearedLocal', '数据已清空（仅前端，未配置 clear_action 时不会写入数据库）。'), 'info');
        $btn.prop('disabled', false).text(oldText);
      });
    });

    $pageSize.on('change.xunTable', function () {
      var nextSize = parseInt($(this).val(), 10);
      if (!nextSize || nextSize < 1) return;
      state.pageSize = nextSize;
      state.page = 1;
      if ($pageSizeDisplay.length) {
        $pageSizeDisplay.text(String(nextSize));
      }
      // 可见页集合变了：清锚点与自动区间，避免跨页幽灵 Shift。
      resetShiftRangeStateLocal();
      if (frameworkFetch) {
        $field.trigger('pili:table:perpage', [nextSize]);
        fetchServerPage(1);
        return;
      }
      // 业务外挂分页（仅 data-server-paged）：只通知，不本地 slice / 不抢 AJAX。
      if (isServerPagedAttr($field)) {
        $field.trigger('pili:table:perpage', [nextSize]);
        return;
      }
      draw();
    });

    // 复用 XunSelect：body portal + 贴底时向上翻，避免页脚处选项溢出视口。
    // 勿再手写 toggle.hidden（会挡住 portal，贴底时无法点选）。
    // boot 用 $field：find() 不含自身，须从父级扫到 .pili-table-page-size-wrap。
    if ($pageSizeWrap.length && typeof window.piliSelectFieldBoot === 'function') {
      window.piliSelectFieldBoot($field);
    }

    // 表头全选：必须用 change（勿用 click+preventDefault）。
    // checkbox 在 click 前已翻转 checked；preventDefault 后再取反会导致“点了没反应”。
    $field.on('change.xunTable', '.pili-table-select-all', function () {
      if (!selectable || $(this).prop('disabled')) {
        return;
      }
      $(this).prop('indeterminate', false);
      setFilteredSelection($(this).is(':checked'));
    });

    $field.on('click.xunTable', 'th.pili-table-select-col', function (e) {
      if ($(e.target).closest('.pili-table-select-all').length) {
        return;
      }
      var $all = $field.find('.pili-table-select-all');
      if (!$all.length || $all.prop('disabled')) {
        return;
      }
      e.preventDefault();
      var next = $all.prop('indeterminate') ? true : !$all.prop('checked');
      $all.prop('indeterminate', false).prop('checked', next).trigger('change');
    });

    // Shift 按下时禁止拖出文字选区；点在复选框上时勿在 tr 上 preventDefault。
    $field.on('mousedown.xunTable', 'tbody tr.pili-table-row', function (e) {
      if (!e.shiftKey) {
        return;
      }
      if ($(e.target).is('input, textarea, select, button, a') || $(e.target).closest('input, button, a, label').length) {
        return;
      }
      e.preventDefault();
    });

    // Shift+mousedown 复选框：在浏览器改 checked 之前完成连选（日志证实 click 后 DOM 会被翻少 1 格）。
    $field.on('mousedown.xunTable', 'tbody .pili-table-row-radio', function (e) {
      if (!selectable || !e.shiftKey || !state.lastAnchorKey) {
        return;
      }
      var $cb = $(this);
      var $tr = $cb.closest('tr');
      if (!isSelectableDataRow($tr.get(0))) {
        return;
      }
      var rowKey = readRowKey($tr);
      if (!rowKey) {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      shiftRangeHandledAt = Date.now();
      selectRangeByKeys(state.lastAnchorKey, rowKey);
      $cb.prop('checked', selectedRowKeys.has(rowKey));
    });

    // Shift+点击复选框：若 mousedown 已处理则只拦默认并再对账；否则补做连选。Ctrl/Cmd：只翻转不挪锚点。
    $field.on('click.xunTable', 'tbody .pili-table-row-radio', function (e) {
      if (!selectable) {
        return;
      }
      var $cb = $(this);
      var $tr = $cb.closest('tr');
      if (!isSelectableDataRow($tr.get(0))) {
        return;
      }
      var rowKey = readRowKey($tr);
      if (!rowKey) {
        return;
      }
      if (e.shiftKey && state.lastAnchorKey) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!shiftRangeHandledAt || Date.now() - shiftRangeHandledAt > 800) {
          selectRangeByKeys(state.lastAnchorKey, rowKey);
        } else {
          window.setTimeout(function () {
            reconcileSelectionDomFromSet();
          }, 0);
        }
        $cb.prop('checked', selectedRowKeys.has(rowKey));
        return false;
      }
      if (e.ctrlKey || e.metaKey) {
        suppressAnchorMove = true;
      }
    });

    $field.on('change.xunTable', 'tbody .pili-table-row-radio', function () {
      var $cbCh = $(this);
      var $trCh = $cbCh.closest('tr');
      var keyCh = readRowKey($trCh);
      if (suppressCheckboxChange > 0) {
        if (keyCh) {
          $cbCh.prop('checked', selectedRowKeys.has(keyCh));
        }
        return;
      }
      if (!keyCh) return;
      if ($cbCh.is(':checked')) selectedRowKeys.add(keyCh);
      else selectedRowKeys.delete(keyCh);
      releaseManualKeyOwnership(keyCh);
      if (suppressAnchorMove) {
        suppressAnchorMove = false;
      } else {
        state.lastAnchorKey = keyCh;
      }
      publishSelection();
      syncSelectAllCheckbox();
      applyRowVisualState($trCh, $cbCh.is(':checked'));
      syncSelectionToolbar();
    });

    $field.on('click.xunTable', 'tbody tr.pili-table-row', function (e) {
      if ($(e.target).is('input, button, a, select, textarea, label') || $(e.target).closest('button, a, label').length) {
        return;
      }
      if (!selectable) return;
      var $tr = $(this);
      if (!isSelectableDataRow($tr.get(0))) return;
      var $checkbox = $tr.find('.pili-table-row-radio');
      if (!$checkbox.length) return;
      var rowKey = readRowKey($tr);
      if (!rowKey) return;

      if (e.shiftKey && state.lastAnchorKey) {
        e.preventDefault();
        selectRangeByKeys(state.lastAnchorKey, rowKey);
        return;
      }

      if (e.ctrlKey || e.metaKey) {
        suppressAnchorMove = true;
      }
      $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
    });

    $pages.on('click.xunTable', '.pili-table-page-btn', function () {
      var targetPage = parseInt($(this).data('page'), 10);
      if (!targetPage || targetPage === state.page) return;
      state.page = targetPage;
      resetShiftRangeStateLocal();
      if (frameworkFetch) {
        fetchServerPage(targetPage);
        return;
      }
      draw();
    });

    $sortBtns.on('click.xunTable', function () {
      if (frameworkFetch) {
        showMsg(tableI18n('serverSortHint', '服务端分页表格暂不支持跨页排序，请使用搜索筛选。'), 'info');
        return;
      }
      var idx = parseInt($(this).data('col-index'), 10);
      if (state.sortCol === idx) state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
      else {
        state.sortCol = idx;
        state.sortDir = 'asc';
      }
      applySearch();
    });
    $prev.on('click.xunTable', function () {
      if (state.page <= 1) return;
      state.page -= 1;
      resetShiftRangeStateLocal();
      if (frameworkFetch) {
        fetchServerPage(state.page);
        return;
      }
      draw();
    });
    $next.on('click.xunTable', function () {
      var totalN = frameworkFetch ? (state.serverTotal || 0) : state.filteredRows.length;
      var pagesN = Math.max(1, Math.ceil(totalN / state.pageSize) || 1);
      if (state.page >= pagesN) return;
      state.page += 1;
      resetShiftRangeStateLocal();
      if (frameworkFetch) {
        fetchServerPage(state.page);
        return;
      }
      draw();
    });

    $field.on('click.xunTable', '.pili-table-export', function () {
      var headers = $table.find('thead th.pili-table-sort').toArray().map(function (th) {
        var title = $(th).find('.pili-table-sort-label > span').first().text().trim();
        if (!title) {
          title = $(th).clone().children('.pili-table-sort-flag, .pili-table-sort-indicator').remove().end().text().trim();
        }
        return csvEscape(title);
      });
      var lines = [];
      if (headers.length) lines.push(headers.join(','));
      state.filteredRows.forEach(function (tr) {
        var $tr = $(tr);
        var cols = $tr
          .children('td')
          .toArray()
          .map(function (td, idx) {
            if (selectable && idx === 0) {
              return csvEscape($tr.find('.pili-table-row-radio').val() || '');
            }
            var $td = $(td);
            var $full = $td.find('.pili-table-cell-full').first();
            if ($full.length) {
              return csvEscape(($full.text() || '').trim());
            }
            return csvEscape($td.text().trim());
          });
        if (cols.length) lines.push(cols.join(','));
      });
      var csv = '\uFEFF' + lines.join('\n');
      var url = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
      var a = document.createElement('a');
      a.href = url;
      a.download = 'table-export-' + Date.now() + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });

    function askConfirm(opts, done) {
      opts = opts || {};
      if (typeof PILI.confirm === 'function') {
        window
          .PILI.confirm({
            title: opts.title || tableI18n('confirm', '确认'),
            message: opts.message || '',
            type: opts.type || 'warning',
            confirmText: opts.confirmText || tableI18n('ok', '确定'),
            cancelText: opts.cancelText || tableI18n('cancel', '取消'),
          })
          .then(function (ok) {
            done(!!ok);
          });
        return;
      }
      if (window.PilipostUi && typeof window.PilipostUi.confirm === 'function') {
        window.PilipostUi.confirm(opts.message || tableI18n('okQuestion', '确定？'), {
          title: opts.title || tableI18n('confirm', '确认'),
          type: opts.type || 'warning',
          confirmText: opts.confirmText || tableI18n('ok', '确定'),
          cancelText: opts.cancelText || tableI18n('cancel', '取消')
        }).then(function (ok) {
          done(!!ok);
        });
        return;
      }
      done(false);
    }

    if ($selectionActionBtns.length && selectionActions.length) {
      $field.on('click.xunTable', '.pili-table-selection-action', function () {
        if (selectedRowKeys.size === 0) return;
        var $btn = $(this);
        if ($btn.prop('disabled')) return;
        var saKey = String($btn.attr('data-sa-key') || '');
        var sa = null;
        for (var i = 0; i < selectionActions.length; i++) {
          var item = selectionActions[i] || {};
          var itemKey = item.key != null && String(item.key) !== '' ? String(item.key) : 'sa' + i;
          if (itemKey === saKey) {
            sa = item;
            break;
          }
        }
        if (!sa || !sa.action) return;

        var selectedKeys = Array.from(selectedRowKeys);
        var count = selectedKeys.length;
        var confirmMsg = String(sa.confirm_text || tableSprintf(tableI18n('selectionConfirm', '确定对已勾选的 %d 项执行「%s」？'), count, sa.label || '')).replace(
          /\{n\}/g,
          String(count)
        );
        askConfirm(
          {
            title: sa.confirm_title || sa.label || tableI18n('confirm', '确认'),
            message: confirmMsg,
            type: sa.variant === 'danger' ? 'warning' : 'info',
            confirmText: sa.confirm_ok || sa.label || tableI18n('ok', '确定'),
          },
          function (ok) {
            if (!ok) return;
            var oldText = $btn.text();
            var loadingLabel = sa.loading_label || tableI18n('processing', '处理中…');
            $selectionActionBtns.prop('disabled', true);
            $btn.text(loadingLabel);
            var body = new URLSearchParams();
            body.append('action', String(sa.action));
            if (sa.nonce) body.append('nonce', String(sa.nonce));
            body.append('row_key', rowKeyField);
            selectedKeys.forEach(function (k) {
              body.append('ids[]', k);
            });
            var extra = sa.body && typeof sa.body === 'object' ? sa.body : {};
            Object.keys(extra).forEach(function (k) {
              if (extra[k] == null) return;
              body.append(String(k), String(extra[k]));
            });
            if (!ajaxUrl) {
              showMsg(tableI18n('missingAjax', '缺少 ajaxurl'), 'error');
              $btn.text(oldText);
              syncSelectionToolbar();
              return;
            }
            fetch(ajaxUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
              body: body.toString(),
            })
              .then(function (res) {
                return res.json();
              })
              .then(function (json) {
                if (!json || !json.success) {
                  throw new Error((json && json.data && json.data.message) || tableI18n('actionFail', '操作失败'));
                }
                showMsg(String((json.data && json.data.message) || tableI18n('actionDone', '已完成。')), 'success');
                if (json.data) {
                  $field.trigger('pili:table:actionDone', [json.data]);
                }
                selectedRowKeys.clear();
                resetShiftRangeStateLocal();
                var finish = function () {
                  syncVisibleSelectionUI();
                  $btn.text(oldText);
                  syncSelectionToolbar();
                };
                if (sa.refresh !== false) {
                  refresh($field, function (err) {
                    if (err) {
                      showMsg(err.message || tableI18n('refreshFail', '表格刷新失败'), 'error');
                    }
                    finish();
                  });
                  return;
                }
                finish();
              })
              .catch(function (err) {
                showMsg(err && err.message ? err.message : tableI18n('actionFail', '操作失败'), 'error');
                $btn.text(oldText);
                syncSelectionToolbar();
              });
          }
        );
      });
    }

    if (showDelete) {
      $field.on('click.xunTable', '.pili-table-delete-selected', function () {
        if (selectedRowKeys.size === 0) return;
        var selectedKeys = Array.from(selectedRowKeys);
        var count = selectedKeys.length;
        var $deleteBtn = $(this);
        askConfirm(
          {
            title: deleteConfirmTitle || tableI18n('confirmDelete', '确认删除'),
            message: deleteConfirmText
              ? tableSprintf(deleteConfirmText, count)
              : tableSprintf(tableI18n('deleteConfirm', '确定要删除已勾选的 %d 项吗？此操作不可撤销。'), count),
            type: 'warning',
            confirmText: deleteConfirmOk || tableI18n('delete', '删除'),
          },
          function (ok) {
          if (!ok) return;
          var oldText = $deleteBtn.text();
          $deleteBtn.prop('disabled', true).text(tableI18n('deleting', '删除中…'));
          if (deleteAction && ajaxUrl) {
            var body = new URLSearchParams();
            body.append('action', deleteAction);
            if (deleteNonce) body.append('nonce', deleteNonce);
            body.append('row_key', rowKeyField);
            selectedKeys.forEach(function (k) {
              body.append('ids[]', k);
            });
            fetch(ajaxUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
              body: body.toString(),
            })
              .then(function (res) {
                return res.json();
              })
              .then(function (json) {
                if (!json || !json.success) throw new Error(json && json.data.message ? json.data.message : tableI18n('deleteFail', '删除失败'));
                showMsg(String((json.data && json.data.message) || tableI18n('deleteOk', '删除成功。')), 'success');
                if (json.data) {
                  $field.trigger('pili:table:actionDone', [json.data]);
                }
                selectedRowKeys.clear();
                resetShiftRangeStateLocal();
                if ($field.attr('data-lazy-data') === '1') {
                  $field.data('xunTableDataLoaded', false);
                  fetchLazyTableData($field, function (err) {
                    if (err) {
                      window.location.reload();
                      return;
                    }
                    reloadTableRows(state, { force: true });
                  }, frameworkFetch ? { page: state.page, perPage: state.pageSize } : undefined);
                  return;
                }
                state.allRows = state.allRows.filter(function (tr) {
                  return selectedKeys.indexOf(readRowKey($(tr))) === -1;
                });
                state.filteredRows = state.filteredRows.filter(function (tr) {
                  return selectedKeys.indexOf(readRowKey($(tr))) === -1;
                });
                draw();
              })
              .catch(function (err) {
                showMsg(err && err.message ? err.message : tableI18n('deleteFail', '删除失败'), 'error');
              })
              .finally(function () {
                $deleteBtn.prop('disabled', false).text(oldText);
              });
            return;
          }
          state.allRows = state.allRows.filter(function (tr) {
            return selectedKeys.indexOf(readRowKey($(tr))) === -1;
          });
          state.allRows.forEach(function (tr, idx) {
            $(tr).attr('data-row-index', idx);
          });
          selectedRowKeys.clear();
          resetShiftRangeStateLocal();
          applySearch();
          showMsg(tableI18n('deletedLocal', '已从前端列表移除（未配置 delete_action 时不会写入数据库）。'), 'info');
          $deleteBtn.prop('disabled', false).text(oldText);
        });
      });
    }

    $field.data('xunTableState', state);
    $field.data('xunTableBuild', build);
    $field.data('xunTableInited', true);

    if (needLazyFetch) {
      $field.data('xunTableLazyLoading', true);
      var bootPer = parseInt(readDataAttr($field, 'page-size', '10'), 10) || 10;
      fetchLazyTableData($field, function (err) {
        $field.data('xunTableLazyLoading', false);
        $field.data('xunTableDataLoaded', true);
        if (err) {
          liveTbody($field).html(
            '<tr><td colspan="99" style="text-align:center;color:#dc2626;border:1px solid #e5e7eb;padding:1rem;">' +
              (err.message || (typeof window.pilipost__ === 'function' ? window.pilipost__('Load failed') : 'Load failed')) +
              '</td></tr>'
          );
        }
        reloadTableRows(state, { force: true });
        mountCellEditors($field);
      }, frameworkFetch ? { page: 1, perPage: bootPer } : undefined);
      return;
    }

    draw();
    mountCellEditors($field);
  }

  var actionIdleHtmlMap = {};

  function actionStorageKey(fieldId, actionKey) {
    return String(fieldId || '') + '::' + String(actionKey || '');
  }

  function ensureActionSpinStyle() {
    if (document.getElementById('pili-table-action-btn-spin-style')) {
      return;
    }
    var style = document.createElement('style');
    style.id = 'pili-table-action-btn-spin-style';
    style.textContent =
      '@keyframes pili-table-action-btn-spin{to{transform:rotate(360deg)}}' +
      '.pili-table-action-btn.is-loading{opacity:.72;cursor:wait;pointer-events:none;}' +
      '.pili-table-action-btn .pili-table-action-btn-spin{display:inline-block;animation:pili-table-action-btn-spin .75s linear infinite;}';
    document.head.appendChild(style);
  }

  function actionSpinnerHtml() {
    return (
      '<svg class="pili-table-action-btn-spin" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="margin-right:5px;flex-shrink:0;">' +
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>' +
      '</svg>'
    );
  }

  function findActionButtons(fieldId, actionKey) {
    var root = fieldId ? document.querySelector('[data-field-id="' + fieldId + '"]') || document : document;
    return Array.prototype.slice.call(root.querySelectorAll('.pili-table-action-btn[data-pili-table-action-key="' + actionKey + '"]'));
  }

  function isActionLoading(fieldId, actionKey) {
    return findActionButtons(fieldId, actionKey).some(function (btn) {
      return btn.classList.contains('is-loading');
    });
  }

  function setActionLoading(fieldId, actionKey, loading, label) {
    ensureActionSpinStyle();
    var buttons = findActionButtons(fieldId, actionKey);
    var storeKey = actionStorageKey(fieldId, actionKey);
    buttons.forEach(function (btn) {
      if (loading) {
        if (!actionIdleHtmlMap[storeKey]) {
          actionIdleHtmlMap[storeKey] = btn.innerHTML;
        }
        btn.classList.add('is-loading');
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        var text = label || btn.getAttribute('data-pili-table-loading-label') || tableBag().actionLoading || tableI18n('processing', '处理中…');
        btn.innerHTML = actionSpinnerHtml() + '<span>' + text + '</span>';
      }
    });
  }

  function finishAction(fieldId, actionKey) {
    var storeKey = actionStorageKey(fieldId, actionKey);
    var idleHtml = actionIdleHtmlMap[storeKey];
    var buttons = findActionButtons(fieldId, actionKey);
    var fallback = buttons.length ? buttons[0].getAttribute('data-pili-table-action-label') || buttons[0].textContent || '' : '';
    var html = idleHtml || fallback;

    function apply() {
      findActionButtons(fieldId, actionKey).forEach(function (btn) {
        btn.classList.remove('is-loading');
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.innerHTML = html;
      });
    }

    apply();
    setTimeout(apply, 0);
    setTimeout(function () {
      apply();
      delete actionIdleHtmlMap[storeKey];
    }, 120);
  }

  function runAction(fieldId, actionKey, worker, options) {
    options = options || {};
    if (isActionLoading(fieldId, actionKey)) {
      return $.Deferred().reject(new Error('action_busy')).promise();
    }

    setActionLoading(fieldId, actionKey, true, options.loadingLabel);

    var ctx = {
      setLoadingLabel: function (label) {
        setActionLoading(fieldId, actionKey, true, label);
      },
    };

    var chain;
    try {
      chain = worker(ctx);
    } catch (err) {
      chain = $.Deferred().reject(err).promise();
    }

    return $.when(chain).always(function () {
      finishAction(fieldId, actionKey);
    });
  }

  function updateCell(fieldId, rowKey, headerMatch, value) {
    if (value == null || !fieldId || !rowKey || !headerMatch) {
      return;
    }
    var field = document.querySelector('[data-field-id="' + fieldId + '"]');
    if (!field) {
      return;
    }
    var table = field.querySelector('.pili-table-grid');
    if (!table) {
      return;
    }
    var row = table.querySelector('tr[data-row-key="' + rowKey + '"]');
    if (!row) {
      return;
    }
    var headers = table.querySelectorAll('thead th');
    var colIdx = -1;
    headers.forEach(function (th, i) {
      if ((th.textContent || '').indexOf(headerMatch) !== -1) {
        colIdx = i;
      }
    });
    if (colIdx < 0) {
      return;
    }
    var cell = row.children[colIdx];
    if (cell) {
      cell.textContent = String(value);
    }
  }

  function boot($root) {
    var $scope = $root && $root.length ? $root : $(document);
    $scope.find('.pili-table-field').each(function () {
      initOne($(this));
    });
  }

  function resolveTableField(fieldRef) {
    if (!fieldRef) {
      return $();
    }
    if (fieldRef.jquery) {
      return fieldRef.first();
    }
    if (fieldRef.nodeType === 1) {
      return $(fieldRef).closest('.pili-table-field').addBack('.pili-table-field').first();
    }
    var id = String(fieldRef);
    var $byField = $('.pili-table-field')
      .filter(function () {
        return $(this).closest('[data-field-id="' + id + '"]').length > 0 || $(this).attr('data-lazy-field-id') === id;
      })
      .first();
    if ($byField.length) {
      return $byField;
    }
    return $(id).closest('.pili-table-field').addBack('.pili-table-field').first();
  }

  function getSelectedKeys(fieldRef) {
    var $field = resolveTableField(fieldRef);
    if (!$field.length) {
      return [];
    }
    var state = $field.data('xunTableState');
    if (state && state.selectedRowKeys && typeof state.selectedRowKeys.forEach === 'function') {
      return Array.from(state.selectedRowKeys);
    }
    var keys = $field.data('xunTableSelectedKeys');
    if (Array.isArray(keys)) {
      return keys.slice();
    }
    var fallback = [];
    $field.find('tbody input.pili-table-row-radio:checked').each(function () {
      var v = $(this).val();
      if (v) {
        fallback.push(String(v));
      }
    });
    return fallback;
  }

  /**
   * 清空选中：Set + 锚点 + 上次自动区间 + 勾选 UI + 表头全选态 + 计数，并派发 pili:table:selection。
   * 业务换页 / 换筛请优先调用，勿只 clear selectedRowKeys。
   *
   * @param {string|Element|jQuery} fieldRef
   * @return {boolean}
   */
  function clearSelection(fieldRef) {
    var $field = resolveTableField(fieldRef);
    if (!$field.length) {
      return false;
    }
    var state = $field.data('xunTableState');
    if (state && typeof state.clearSelectionLocal === 'function') {
      state.clearSelectionLocal();
      return true;
    }
    if (state && state.selectedRowKeys && typeof state.selectedRowKeys.clear === 'function') {
      state.selectedRowKeys.clear();
    }
    resetShiftRangeState(state);
    $field.data('xunTableSelectedKeys', []);
    $field.attr('data-selected-count', '0');
    $field.find('tbody tr').each(function () {
      var $tr = $(this);
      $tr.find('.pili-table-row-radio').prop('checked', false);
      $tr.removeClass('selected').css('background-color', '#ffffff');
      $tr.children('td').css('background-color', '#ffffff');
    });
    $field.find('.pili-table-select-all').prop('checked', false).prop('indeterminate', false);
    $field.find('.pili-table-delete-selected, .pili-table-selection-action').prop('disabled', true);
    $field.trigger('pili:table:selection', [[]]);
    return true;
  }

  $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
    boot($container && $container.length ? $container : null);
  });

  $(function () {
    boot(null);
  });

  PILI.TableField = {
    boot: boot,
    setActionLoading: setActionLoading,
    finishAction: finishAction,
    runAction: runAction,
    isActionLoading: isActionLoading,
    updateCell: updateCell,
    getSelectedKeys: getSelectedKeys,
    clearSelection: clearSelection,
    refresh: refresh,
    mountEditors: mountCellEditors,
  };

  PILI.registerBoot('table', boot);
})(jQuery);
