(function ($) {
  'use strict';

  function getCfg() {
    var bag = (typeof PILI !== 'undefined' && typeof PILI.bag === 'function') ? PILI.bag('chart') : null;
    // bag() 空对象 {} 仍为 truthy，懒加载若未跑 setBag 会挡住 _piliBag_chart / localize。
    if (bag && bag.echarts_src) {
      return bag;
    }
    if (window._piliBag_chart && window._piliBag_chart.echarts_src) {
      return window._piliBag_chart;
    }
    if (window.piliChartField && window.piliChartField.echarts_src) {
      return window.piliChartField;
    }
    return bag || window._piliBag_chart || window.piliChartField || {};
  }

  function prefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function isGrowthAnimationEnabled($field) {
    if (prefersReducedMotion()) return false;
    var cfg = getCfg();
    if (cfg.entry_animation === false) return false;
    if ($field && String($field.attr('data-animate-entry') || '1') === '0') return false;
    return true;
  }

  function getGrowthAnimationConfig() {
    var cfg = getCfg();
    return {
      duration: parseInt(cfg.entry_duration, 10) || 800,
      easing: cfg.entry_easing || 'cubicOut',
      fieldStagger: parseInt(cfg.entry_stagger, 10) || 0
    };
  }

  function countChartIndex($field) {
    var $scope = $field.closest('.pili-section-wrapper, .pili-content, .pili-main');
    if (!$scope.length) $scope = $(document);
    return $scope.find('.pili-chart-field').index($field);
  }

  function applyGrowthAnimationToOption(option, fieldIndex, $field) {
    if (!option || typeof option !== 'object') return option;
    if (!isGrowthAnimationEnabled($field)) return option;
    if (option.animation === false) return option;

    var anim = getGrowthAnimationConfig();
    var out = $.extend(true, {}, option);
    var fieldDelay = Math.max(0, fieldIndex) * anim.fieldStagger;

    out.animation = true;
    out.animationDuration = anim.duration;
    out.animationEasing = anim.easing;
    out.animationDurationUpdate = 350;
    out.animationEasingUpdate = 'cubicOut';

    if (Array.isArray(out.series)) {
      out.series.forEach(function (series, seriesIdx) {
        if (!series || typeof series !== 'object') return;
        if (series.animation === false) return;

        series.animationDuration = anim.duration;
        series.animationEasing = anim.easing;

        var base = fieldDelay + seriesIdx * 35;
        if (series.type === 'pie') {
          series.animationType = 'scale';
          series.animationDelay = function (dataIdx) {
            return base + (typeof dataIdx === 'number' ? dataIdx : 0) * 55;
          };
          return;
        }

        series.animationDelay = function (dataIdx) {
          var step = series.type === 'bar' ? 45 : series.type === 'line' ? 28 : 35;
          return base + (typeof dataIdx === 'number' ? dataIdx : 0) * step;
        };
      });
    }

    return out;
  }

  function renderChartWithGrowth($field, chart, option, isReplay) {
    $field.data('xunChartGrowthPlayed', Date.now());
    var fieldIndex = countChartIndex($field);
    var finalOption = isGrowthAnimationEnabled($field)
      ? applyGrowthAnimationToOption(option, fieldIndex, $field)
      : (option || {});

    function draw() {
      if (!$field.closest('body').length) return;
      var inst = $field.data('xunChartInstance');
      if (!inst || inst !== chart) return;
      if (isReplay && typeof chart.clear === 'function') {
        chart.clear();
      }
      chart.setOption(finalOption, true);
    }

    var fieldDelay = isGrowthAnimationEnabled($field)
      ? Math.max(0, fieldIndex) * getGrowthAnimationConfig().fieldStagger
      : 0;

    if (fieldDelay > 0) {
      window.setTimeout(draw, fieldDelay);
      return;
    }
    window.requestAnimationFrame(draw);
  }

  function parseOption(raw) {
    if (typeof raw !== 'string' || !raw.trim()) return {};
    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function showError(msg) {
    var cfg = getCfg();
    if (typeof PILI.alert === 'function') {
      PILI.alert({ title: cfg.hint || '提示', message: msg, type: 'error' });
      return;
    }
    if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
      window.PilipostUi.toast(msg, 'error');
      return;
    }
    if (window.PiliXunToast && typeof window.PiliXunToast.show === 'function') {
      window.PiliXunToast.show({ message: msg, type: 'error' });
    }
  }

  var loaderState = {
    loading: false,
    loaded: false,
    queue: []
  };

  function ensureEcharts(cb) {
    if (window.echarts) {
      cb(true);
      return;
    }
    loaderState.queue.push(cb);
    if (loaderState.loading) {
      return;
    }
    loaderState.loading = true;

    var cfg = getCfg();

    function finish(ok) {
      loaderState.loading = false;
      loaderState.loaded = !!ok;
      while (loaderState.queue.length) {
        loaderState.queue.shift()(loaderState.loaded);
      }
    }

    // 仅加载 PHP 注入的插件本地路径，不走 CDN / PilidocVendor。
    var src = String(cfg.echarts_src || '').trim();
    if (!src) {
      showError(cfg.loader_error || '本地 ECharts 加载失败');
      finish(false);
      return;
    }

    var s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = function () {
      finish(!!window.echarts);
    };
    s.onerror = function () {
      showError(cfg.loader_error || '本地 ECharts 加载失败');
      finish(false);
    };
    document.head.appendChild(s);
  }

  function getAjaxConfig($field) {
    var el = $field && $field.length ? $field[0] : null;
    var ajax = (window.PILI && PILI.ajaxCfg) ? PILI.ajaxCfg(el) : (window.piliAjax || {});
    var runtime = (window.PILI && PILI.runtimeCfg) ? PILI.runtimeCfg(el) : (window.piliRuntime || {});
    var ns = (ajax && ajax.ajaxNs) || (runtime && runtime.ajaxNs) || 'pili';
    return {
      url: (ajax && ajax.ajaxurl) || window.ajaxurl || '',
      nonce: (ajax && ajax.nonce) ? ajax.nonce : '',
      optionId: (runtime && runtime.optionId) || '',
      ajaxNs: String(ns),
      loadFieldAction: String(ns) + '_load_field_data',
    };
  }

  function fetchLazyChartData($field, done) {
    var cfg = getAjaxConfig($field);
    var fieldId = $field.attr('data-lazy-field-id') || '';
    if (!fieldId || !cfg.url) {
      done(new Error(getCfg().ajax_missing || '缺少 field_id 或 ajaxurl'));
      return;
    }
    var oid = $field.attr('data-unique') || cfg.optionId || '';
    $.ajax({
      url: cfg.url,
      type: 'POST',
      dataType: 'json',
      data: {
        action: cfg.loadFieldAction || 'pili_load_field_data',
        nonce: cfg.nonce,
        field_id: fieldId,
        unique: oid,
        option_id: oid,
      },
    })
      .done(function (res) {
        if (!res || !res.success || !res.data || res.data.type !== 'chart') {
          done(new Error(res && res.data && res.data.message ? res.data.message : (getCfg().load_fail || '图表加载失败')));
          return;
        }
        var optionJson = JSON.stringify(res.data.option || {});
        $field.attr('data-option', optionJson);
        var $optScript = $field.find('script.pili-chart-option').first();
        if ($optScript.length) {
          $optScript.text(optionJson);
        } else {
          $field.append($('<script type="application/json" class="pili-chart-option"></script>').text(optionJson));
        }
        $field.find('input[type="hidden"]').first().val(optionJson);
        $field.find('.pili-chart-lazy-placeholder').remove();
        $field.find('.pili-chart-canvas').show();
        $field.data('xunChartDataLoaded', true);
        done(null);
      })
      .fail(function () {
        done(new Error(getCfg().request_fail || '图表数据请求失败'));
      });
  }

  function isChartHostVisible($field) {
    if (!$field || !$field.length) return false;
    var $wrap = $field.closest('.pili-section-wrapper');
    if ($wrap.length && $wrap.hasClass('hidden')) return false;
    if ($field.closest('.pili-dep-hidden').length) return false;
    var el = $field.find('.pili-chart-canvas').get(0) || $field.get(0);
    if (!el) return false;
    if (el.offsetParent === null && getComputedStyle(el).position !== 'fixed') {
      var rects = el.getClientRects();
      if (!rects || !rects.length) return false;
    }
    var w = el.clientWidth || el.offsetWidth || 0;
    var h = el.clientHeight || el.offsetHeight || 0;
    return w > 0 && h > 0;
  }

  function disposeChart($field) {
    var old = $field.data('xunChartInstance');
    if (old && typeof old.dispose === 'function') {
      try { old.dispose(); } catch (e) { /* ignore */ }
    }
    var resize = $field.data('xunChartResize');
    if (resize) {
      $(window).off('resize', resize);
    }
    var ro = $field.data('xunChartResizeObserver');
    if (ro && typeof ro.disconnect === 'function') {
      try { ro.disconnect(); } catch (e2) { /* ignore */ }
    }
    var inview = $field.data('xunChartInviewObs');
    if (inview && typeof inview.disconnect === 'function') {
      try { inview.disconnect(); } catch (e3) { /* ignore */ }
    }
    $field.removeData('xunChartInstance');
    $field.removeData('xunChartResize');
    $field.removeData('xunChartResizeObserver');
    $field.removeData('xunChartInviewObs');
    $field.removeData('xunChartGrowthPending');
    $field.data('xunChartInited', false);
  }

  function isElementInView(el) {
    if (!el || typeof el.getBoundingClientRect !== 'function') return true;
    var rect = el.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;
    if (vh < 2 || vw < 2) return true;
    var visW = Math.min(rect.right, vw) - Math.max(rect.left, 0);
    var visH = Math.min(rect.bottom, vh) - Math.max(rect.top, 0);
    if (visW < 24) return false;
    var need = Math.min(72, Math.max(28, (rect.height || 0) * 0.22));
    return visH >= need;
  }

  function quietOption(option) {
    var quiet = $.extend(true, {}, option || {});
    quiet.animation = false;
    quiet.animationDuration = 0;
    var list = quiet.series ? (Array.isArray(quiet.series) ? quiet.series : [quiet.series]) : [];
    for (var i = 0; i < list.length; i++) {
      if (!list[i] || typeof list[i] !== 'object') continue;
      list[i].animation = false;
      list[i].animationDuration = 0;
    }
    return quiet;
  }

  function releaseGrowth($field, chart, option, replay) {
    renderChartWithGrowth($field, chart, option, !!replay);
    var wait = 40;
    if (isGrowthAnimationEnabled($field)) {
      wait += getGrowthAnimationConfig().duration || 800;
    }
    window.setTimeout(function () {
      if (!$field.closest('body').length) return;
      $field.data('xunChartGrowthPending', 0);
      $field.trigger('xunChartGrown');
    }, wait);
  }

  /**
   * 首屏里的图马上播。视口外的先静画，滑进来再播一次，避免动画在屏幕外放完。
   */
  function scheduleEntryAnimation($field, chart, option) {
    if (!isGrowthAnimationEnabled($field)) {
      renderChartWithGrowth($field, chart, option, false);
      $field.data('xunChartGrowthPending', 0);
      $field.trigger('xunChartGrown');
      return;
    }
    var host = $field.get(0);
    if (isElementInView(host)) {
      $field.data('xunChartGrowthPending', 1);
      releaseGrowth($field, chart, option, false);
      return;
    }
    $field.data('xunChartGrowthPending', 1);
    try {
      chart.setOption(quietOption(option), true);
    } catch (eQuiet) { /* ignore */ }

    function play() {
      if (!$field.data('xunChartGrowthPending')) return;
      var inst = $field.data('xunChartInstance');
      if (!inst || inst !== chart) return;
      releaseGrowth($field, inst, option, true);
    }

    if (typeof window.IntersectionObserver !== 'function') {
      play();
      return;
    }
    var obs = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (!entries[i].isIntersecting || entries[i].intersectionRatio < 0.22) continue;
        try { obs.disconnect(); } catch (eObs) { /* ignore */ }
        $field.removeData('xunChartInviewObs');
        play();
        return;
      }
    }, { threshold: [0, 0.22, 0.4, 0.6] });
    $field.data('xunChartInviewObs', obs);
    if (host) obs.observe(host);
  }

  function initChart($field) {
    if ($field.attr('data-lazy-data') === '1' && !$field.data('xunChartDataLoaded')) {
      if ($field.data('xunChartLazyLoading')) {
        return;
      }
      $field.data('xunChartLazyLoading', true);
      fetchLazyChartData($field, function (err) {
        $field.data('xunChartLazyLoading', false);
        if (err) {
          $field.data('xunChartInited', false);
          $field.find('.pili-chart-lazy-placeholder')
            .text(err.message || getCfg().load_fail || '图表加载失败')
            .addClass('text-red-500');
          showError(err.message || getCfg().load_fail || '图表加载失败');
          return;
        }
        $field.data('xunChartInited', false);
        initChart($field);
      });
      return;
    }

    // 隐藏容器禁止 init；可见后再由 bootVisibleSectionFields / MutationObserver 触发。
    if (!isChartHostVisible($field)) {
      return;
    }

    if ($field.data('xunChartInited') && $field.data('xunChartInstance')) {
      resizeChartIfNeeded($field);
      return;
    }

    disposeChart($field);
    $field.data('xunChartInited', true);

    var $canvas = $field.find('.pili-chart-canvas').first();
    if (!$canvas.length) return;

    var theme = String($field.data('theme') || '');
    var renderer = String($field.data('renderer') || 'canvas');
    // 优先读 JSON script，其次 hidden input，最后 data-option。
    var rawOption = String($field.find('script.pili-chart-option').first().text() || '').trim();
    if (!rawOption || rawOption === '{}' || rawOption === '[]') {
      rawOption = String($field.find('input[type="hidden"]').first().val() || '');
    }
    if (!rawOption || rawOption === '{}' || rawOption === '[]') {
      rawOption = String($field.attr('data-option') || '');
    }
    var option = parseOption(rawOption);
    if (!option || !Object.keys(option).length) {
      option = {
        title: {
          text: getCfg().no_data || '暂无图表数据',
          left: 'center',
          top: 'middle',
          textStyle: { color: '#6b7280', fontSize: 14 },
        },
      };
    }

    ensureEcharts(function (ok) {
      if (!ok || !window.echarts) {
        $field.data('xunChartInited', false);
        var msg = getCfg().loader_error || getCfg().echarts_fail || 'ECharts 加载失败';
        if (!$field.find('.pili-chart-lazy-placeholder').length) {
          $canvas.hide();
          $field.prepend(
            '<div class="pili-chart-lazy-placeholder flex items-center justify-center text-sm text-red-500" style="width:100%;height:' +
              ($field.data('height') || 360) +
              'px;border:0;border-radius:10px;background:#fff;">' +
              msg +
              '</div>'
          );
        } else {
          $field.find('.pili-chart-lazy-placeholder').text(msg).addClass('text-red-500').show();
        }
        showError(msg);
        return;
      }
      if (!isChartHostVisible($field)) {
        $field.data('xunChartInited', false);
        return;
      }
      try {
        $field.find('.pili-chart-lazy-placeholder').remove();
        $canvas.show();
        var chart = window.echarts.init($canvas[0], theme || null, { renderer: renderer || 'canvas' });
        $field.data('xunChartInstance', chart);
        scheduleEntryAnimation($field, chart, option || {});

        var resize = function () {
          resizeChartIfNeeded($field);
        };
        $field.data('xunChartResize', resize);
        $(window).on('resize', resize);

        setTimeout(resize, 0);
        setTimeout(resize, 120);
        setTimeout(resize, 360);

        if (typeof window.ResizeObserver === 'function') {
          var ro = new ResizeObserver(function () {
            resize();
          });
          ro.observe($canvas[0]);
          var hostEl = $field.closest('.pili-section-content, .pili-content, .pili-main, .pili-framework-wrap').get(0);
          if (hostEl) {
            ro.observe(hostEl);
          }
          $field.data('xunChartResizeObserver', ro);
        }
      } catch (err) {
        $field.data('xunChartInited', false);
        showError((getCfg().init_error || '图表初始化失败') + (err && err.message ? '：' + err.message : ''));
      }
    });
  }

  function initCharts($root) {
    $root.find('.pili-chart-field').each(function () {
      initChart($(this));
    });
  }

  function resetWrapperLayout($wrapper) {
    $wrapper.css({
      display: '',
      verticalAlign: '',
      width: '',
      marginRight: '',
      marginTop: ''
    });
  }

  function ensureGroupContainer($cards, groupKey) {
    if (!$cards || !$cards.length) return null;
    var $first = $cards.eq(0);
    var $parent = $first.parent();
    if (!$parent.length) return null;

    // 如果已经在同组容器内，直接复用。
    if ($parent.hasClass('pili-chart-group-layout') && String($parent.data('row-group')) === String(groupKey)) {
      return $parent;
    }

    // 创建同组容器并把字段卡片移动进去，避免被外层默认块级布局干扰。
    var $container = $('<div class="pili-chart-group-layout"></div>');
    $container.attr('data-row-group', groupKey);
    $first.before($container);
    $cards.each(function () {
      $container.append(this);
    });
    return $container;
  }

  function getActiveRoot() {
    var el = document.querySelector('.pili-section-wrapper:not(.hidden)');
    return el ? $(el) : $(document);
  }

  function applyRowLayouts($root) {
    $root = $root && $root.length ? $root : getActiveRoot();
    var groups = {};
    $root.find('.pili-chart-field').each(function () {
      var $field = $(this);
      var group = String($field.data('row-group') || '').trim();
      if (!group) return;
      if (!groups[group]) groups[group] = [];
      groups[group].push($field);
    });

    Object.keys(groups).forEach(function (groupKey) {
      var items = groups[groupKey];
      if (!items || !items.length) return;

      var first = items[0];
      var columns = parseInt(first.data('columns'), 10);
      var gap = parseInt(first.data('row-gap'), 10);
      if (!columns || columns < 1) columns = 1;
      if (!gap || gap < 0) gap = 12;

      var isMobile = window.matchMedia && window.matchMedia('(max-width: 980px)').matches;
      var $cards = $();
      items.forEach(function ($field) {
        var $card = $field.closest('.pili-field');
        if ($card.length) {
          $cards = $cards.add($card);
        }
      });

      if (!$cards.length) return;
      var $container = ensureGroupContainer($cards, groupKey);
      if (!$container || !$container.length) return;

      var nextColumns = (isMobile || columns <= 1) ? 1 : columns;
      $container.css({
        display: 'grid',
        gridTemplateColumns: 'repeat(' + nextColumns + ', minmax(0, 1fr))',
        gap: gap + 'px',
        alignItems: 'start',
        width: '100%',
        minWidth: 0
      });

      items.forEach(function ($field) {
        var span = parseInt($field.data('column-span'), 10);
        if (!span || span < 1) span = 1;
        if (nextColumns > 1 && span > nextColumns) span = nextColumns;
        var $card = $field.closest('.pili-field');
        if (!$card.length) return;
        if (nextColumns <= 1) {
          $card.css('gridColumn', '');
          return;
        }
        $card.css('gridColumn', 'span ' + span);
      });
    });
  }

  /**
   * 尺寸没变就不要 resize。折线入场动画进行中被 resize 会停在 0，看起来像没画线。
   */
  function resizeChartIfNeeded($field) {
    var chart = $field.data('xunChartInstance');
    if (!chart || typeof chart.resize !== 'function') return;
    var el = $field.find('.pili-chart-canvas').get(0);
    if (!el) return;
    var w = el.clientWidth || 0;
    var h = el.clientHeight || 0;
    if (w < 2 || h < 2) return;
    var cw = typeof chart.getWidth === 'function' ? chart.getWidth() : 0;
    var ch = typeof chart.getHeight === 'function' ? chart.getHeight() : 0;
    if (Math.abs(cw - w) <= 1 && Math.abs(ch - h) <= 1) return;
    chart.resize();
  }

  function resizeCharts($root) {
    $root = $root && $root.length ? $root : getActiveRoot();
    $root.find('.pili-chart-field').each(function () {
      resizeChartIfNeeded($(this));
    });
  }

  function onSectionActivated($root, replayEntries) {
    $root = $root && $root.length ? $root : getActiveRoot();
    // 先把 row_group 网格排好再 init。后挪 DOM 会清掉刚画上的折线；仪表盘 chart_panel 没有 row_group，所以不受影响。
    applyRowLayouts($root);
    initCharts($root);
    setTimeout(function () {
      resizeCharts($root);
      if (replayEntries) replayVisibleChartEntries($root);
    }, 0);
    setTimeout(function () { resizeCharts($root); }, 120);
    setTimeout(function () { resizeCharts($root); }, 360);
  }

  function replayVisibleChartEntries($root) {
    $root = $root && $root.length ? $root : getActiveRoot();
    var now = Date.now();
    $root.find('.pili-chart-field').each(function () {
      var $field = $(this);
      if (!$field.data('xunChartInited')) return;
      var playedAt = $field.data('xunChartGrowthPlayed') || 0;
      if (now - playedAt < 600) return;
      var chart = $field.data('xunChartInstance');
      if (!chart || typeof chart.setOption !== 'function') return;
      var raw = String($field.find('script.pili-chart-option').first().text() || '').trim();
      if (!raw) raw = String($field.find('input[type="hidden"]').first().val() || '');
      if (!raw) raw = String($field.attr('data-option') || '');
      var option = parseOption(raw);
      if (!option || !Object.keys(option).length) return;
      renderChartWithGrowth($field, chart, option, true);
    });
  }

  function bootCharts($root, replayEntries) {
    onSectionActivated($root && $root.length ? $root : getActiveRoot(), !!replayEntries);
  }

  $(document).on((window.PILI&&PILI.ev?PILI.ev('field:added'):((window.piliRuntime&&piliRuntime.eventNs)||'pili')+':field:added'), function (e, $container) {
    bootCharts($container && $container.length ? $container : null, false);
  });

  $(document).ready(function () {
    bootCharts(null, false);
  });

  window.piliChartFieldBoot = bootCharts;

  // 切换菜单/分组后兜底触发一次图表重算，避免图表缩在左侧。
  $(document).on('click', '.pili-nav a, .pili-sidebar a, .pili-section-tab, .pili-tab-nav a', function () {
    setTimeout(function () {
      onSectionActivated(getActiveRoot(), true);
    }, 80);
  });

  $(window).on('resize', function () {
    onSectionActivated(getActiveRoot(), false);
  });

  // 监听 section 显示/隐藏切换（不依赖刷新）
  if (typeof window.MutationObserver === 'function') {
    var mo = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i += 1) {
        var m = mutations[i];
        if (!m || m.type !== 'attributes') continue;
        var target = m.target;
        if (!target || !target.classList) continue;
        if (!target.classList.contains('pili-section-wrapper')) continue;
        if (target.classList.contains('hidden')) continue;
        onSectionActivated($(target), true);
        break;
      }
    });
    mo.observe(document.body, { subtree: true, attributes: true, attributeFilter: ['class'] });
  }
})(jQuery);
