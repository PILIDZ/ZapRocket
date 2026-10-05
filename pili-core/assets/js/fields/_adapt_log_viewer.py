# one-shot adapter; delete after run
from pathlib import Path

src = Path(r"f:/phpstudy_pro/WWW/piliai.com/wp-content/plugins/pilipost/vendor-xun/assets/js/fields/log_viewer.js")
dst = Path(r"f:/phpstudy_pro/WWW/pilidz.com/wp-content/themes/pilidoc/pili-core/assets/js/fields/log_viewer.js")
t = src.read_text(encoding="utf-8")
repls = [
    ("XUN log_viewer", "PILI log_viewer"),
    ("window.PiliXunLogViewer", "window.PiliLogViewer"),
    (
        "var cfg = window.xunLogViewerField || {};",
        "var cfg = (window.PILI && PILI.bag ? PILI.bag('log_viewer') : null) || window.piliLogViewerField || window.xunLogViewerField || {};",
    ),
    ("$('.xun-log-dd.is-open')", "$('.pili-log-dd.is-open, .xun-log-dd.is-open')"),
    ("xunLogViewerInited", "piliLogViewerInited"),
    ("xun-log-viewer", "pili-log-viewer"),
    ("xun-log-dd", "pili-log-dd"),
    ("xun:log_viewer:", "pili:log_viewer:"),
    ("xunLogViewerToastTimer", "piliLogViewerToastTimer"),
    ("xunLogViewerSetEntries", "piliLogViewerSetEntries"),
    ("xunLogViewerClear", "piliLogViewerClear"),
    ("xunLogViewerGoToPage", "piliLogViewerGoToPage"),
    ("xunLogViewerGetFiltered", "piliLogViewerGetFiltered"),
    ("xunLogViewerExport", "piliLogViewerExport"),
    ("window.xunLogViewerFieldBoot", "window.piliLogViewerFieldBoot"),
    ("$field.hasClass('pili-log-viewer-field')", "$field.is('.pili-log-viewer-field, .xun-log-viewer-field')"),
    ("closest('.pili-log-viewer-field')", "closest('.pili-log-viewer-field, .xun-log-viewer-field')"),
    ("find('.pili-log-viewer-field')", "find('.pili-log-viewer-field, .xun-log-viewer-field')"),
    ("'#pilipost-logs-live-count'", "'#pilidoc-log-files-live-count, #pilipost-logs-live-count'"),
]
for a, b in repls:
    t = t.replace(a, b)

old = """			var ask =
				window.PilipostUi && typeof window.PilipostUi.confirm === 'function'
					? window.PilipostUi.confirm(str('clearConfirm', '确定清空全部运行日志？此操作不可恢复。'), {
							title: str('clearTitle', '清空日志'),
							type: 'warning',
							confirmText: str('clearOkBtn', '清空'),
							cancelText: str('cancel', '取消')
					  })
					: typeof window.xunConfirm === 'function'
						? window.xunConfirm({
								title: str('clearTitle', '清空日志'),
								message: str('clearConfirm', '确定清空全部运行日志？此操作不可恢复。'),
								type: 'warning',
								confirmText: str('clearOkBtn', '清空'),
								cancelText: str('cancel', '取消')
						  })
						: Promise.resolve(false);"""
new = """			var clearOpts = {
							title: str('clearTitle', '清空日志'),
							message: str('clearConfirm', '确定从列表隐藏近 7 天可见条目？JSONL 文件仍保留，可用下方文件表整文件删除。'),
							type: 'warning',
							confirmText: str('clearOkBtn', '清空'),
							cancelText: str('cancel', '取消')
					  };
			var ask =
				window.PILI && typeof window.PILI.confirm === 'function'
					? window.PILI.confirm(clearOpts)
					: window.PilipostUi && typeof window.PilipostUi.confirm === 'function'
					? window.PilipostUi.confirm(clearOpts.message, clearOpts)
					: typeof window.xunConfirm === 'function'
						? window.xunConfirm(clearOpts)
						: Promise.resolve(false);"""
if old not in t:
    raise SystemExit("confirm block not found")
t = t.replace(old, new)

t = t.replace(
    """			if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
				window.PilipostUi.toast(msg, tipType);
				return;
			}
			if (window.PiliXunToast && typeof window.PiliXunToast.show === 'function') {
				window.PiliXunToast.show(msg, { type: tipType });
				return;
			}""",
    """			if (window.PILI && typeof window.PILI.toast === 'function') {
				window.PILI.toast(msg, { type: tipType });
				return;
			}
			if (window.PilipostUi && typeof window.PilipostUi.toast === 'function') {
				window.PilipostUi.toast(msg, tipType);
				return;
			}
			if (window.PiliXunToast && typeof window.PiliXunToast.show === 'function') {
				window.PiliXunToast.show(msg, { type: tipType });
				return;
			}""",
)
t = t.replace("$(document).on('xun:field:added'", "$(document).on('pili:field:added xun:field:added'")
t = t.replace("window.PiliLogViewer =", "window.PiliXunLogViewer = window.PiliLogViewer =")
t += "\n\twindow.xunLogViewerFieldBoot = window.piliLogViewerFieldBoot;\n"
dst.write_text(t, encoding="utf-8")
print("ok", dst.stat().st_size)
