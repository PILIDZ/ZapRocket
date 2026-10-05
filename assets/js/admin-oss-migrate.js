/**
 * ZapRocket OSS migrate console — PILI fields + toast/modal/table APIs.
 */
(function ($) {
	'use strict';

	function cfg() {
		return window.zaprocketOssMig || {};
	}

	function onPage() {
		return $('[data-field-id="zr_oss_mig_actions"], #zr-oss-mig, [data-zr-oss-mig]').length > 0;
	}

	function toast(type, message) {
		var api = window.PiliXunToast;
		if (!api) {
			return;
		}
		if (type === 'success' && typeof api.success === 'function') {
			api.success(message);
			return;
		}
		if (type === 'error' && typeof api.error === 'function') {
			api.error(message);
		}
	}

	function isAckOn() {
		var $wrap = $('[data-field-id="zr_oss_mig_ack"]').first();
		var $hid = $wrap.find('input[type="hidden"], input[type="checkbox"]').first();
		if ($hid.length) {
			var v = String($hid.val() || '');
			if (v === '1' || v === 'true' || $hid.prop('checked') === true) {
				return true;
			}
		}
		var $sw = $wrap.find('[role="switch"]').first();
		if ($sw.length) {
			return String($sw.attr('aria-checked') || '') === 'true' || String($sw.attr('data-checked') || '') === 'true';
		}
		return false;
	}

	function types() {
		var $sel = $('[data-field-id="zr_oss_mig_types"] select.pili-select-native').first();
		if ($sel.length) {
			var raw = $sel.val();
			if ($.isArray(raw)) {
				return raw.filter(function (v) {
					return !!v;
				});
			}
			if (raw) {
				return [String(raw)];
			}
			return [];
		}
		var out = [];
		$('[data-field-id="zr_oss_mig_types"] .pili-select-option[aria-selected="true"]').each(function () {
			var v = $(this).attr('data-value');
			if (v) {
				out.push(v);
			}
		});
		return out;
	}

	function progressWrap() {
		return $('[data-field-id="zr_oss_mig_progress"]').closest('.space-y-2');
	}

	function isProgressVisible(task, running) {
		var status = task && task.status ? String(task.status) : '';
		if (running) {
			return true;
		}
		return ['queued', 'scanning', 'running', 'paused', 'rolling_back'].indexOf(status) !== -1;
	}

	function toggleProgress(show) {
		var $wrap = progressWrap();
		if (!$wrap.length) {
			return;
		}
		$wrap.toggleClass('is-zr-oss-mig-progress-on', !!show);
	}

	function post(action, extra) {
		return $.post(
			cfg().ajax,
			$.extend(
				{
					action: action,
					nonce: cfg().nonce
				},
				extra || {}
			)
		);
	}

	function runTableAction(fieldId, actionKey, worker) {
		var Table = window.PILI && PILI.TableField;
		if (Table && typeof Table.runAction === 'function') {
			return Table.runAction(fieldId, actionKey, worker);
		}
		return $.when(worker({}));
	}

	function refreshTable(fieldId) {
		var Table = window.PILI && PILI.TableField;
		if (Table && typeof Table.refresh === 'function') {
			Table.refresh(fieldId);
		}
	}

	function setProgress(pct) {
		var $input = $('[data-field-id="zr_oss_mig_progress"] .pili-progress-input').first();
		if ($input.length) {
			$input.val(pct).trigger('change');
		}
	}

	function setStats(task, historical) {
		var vals = [
			task.scanned || 0,
			task.uploaded || 0,
			task.backup_count || task.replaced || 0,
			task.failed || 0
		];
		var bag = (cfg().i18n) || {};
		var labels = historical
			? [bag.scanLast || '上次已扫描', bag.uploadLast || '上次已上传', bag.rollbackLast || '上次可回滚', bag.failLast || '上次失败']
			: [bag.scan || '已扫描', bag.upload || '已上传', bag.rollbackBody || '可回滚正文', bag.failLabel || '失败'];
		var $cards = $('[data-field-id="zr_oss_mig_stats"] .pili-stat-card');
		if (!$cards.length) {
			$cards = $('[data-field-id="zr_oss_mig_stats"] .pili-stat-feature-card');
		}
		$cards.each(function (i) {
			if (i < vals.length) {
				var $el = $(this);
				$el.find('.pili-stat-card__value, .pili-stat-feature-card__value').first().text(String(vals[i]));
				$el.find('.pili-stat-card__label, .pili-stat-feature-card__label').first().text(labels[i]);
			}
		});
	}

	function fieldWrap(fieldId) {
		var $w = $('[data-field-id="' + fieldId + '"][data-field-type]').first();
		if ($w.length) {
			return $w;
		}
		return $('[data-field-id="' + fieldId + '"]').first();
	}

	function ensureLimitNotes() {
		var i18n = (cfg().i18n) || {};
		var failMsg = i18n.failCap || '失败表最多列出最近 50 条。超过后更早的失败不会出现在本表翻页里，请到下方迁移日志查看。重试只作用于本表列出的条目。';
		var logMsg = i18n.logCap || '本页轮询大约展示最近 120 条迁移日志，首屏最多约 200 条；更早的记录不会出现在翻页里。';
		if (!document.querySelector('[data-zr-oss-fail-cap]') && failMsg) {
			var $fails = fieldWrap('zr_oss_mig_fails');
			if ($fails.length) {
				$fails.prepend(
					'<p class="mt-0 mb-2 text-sm text-gray-600" data-zr-oss-fail-cap="1"></p>'
				);
				$fails.find('[data-zr-oss-fail-cap]').text(failMsg);
			}
		}
		if (!document.querySelector('[data-zr-oss-log-cap]') && logMsg) {
			var $logs = fieldWrap('zr_oss_mig_logs');
			if ($logs.length) {
				$logs.prepend(
					'<p class="mt-0 mb-2 text-sm text-gray-600" data-zr-oss-log-cap="1"></p>'
				);
				$logs.find('[data-zr-oss-log-cap]').text(logMsg);
			}
		}
	}

	function setLogs(logs) {
		var entries = (logs || []).map(function (row, i) {
			var level = String(row.level || 'info');
			if (level === 'warn') {
				level = 'warning';
			}
			return {
				id: String(i + 1),
				time: row.created_at || row.time || '',
				level: level,
				channel: 'oss_migrate',
				message: row.message || ''
			};
		});
		if (window.PiliLogViewer && typeof window.PiliLogViewer.setEntries === 'function') {
			window.PiliLogViewer.setEntries(
				$('.pili-log-viewer-field[data-field-id="zr_oss_mig_logs"]'),
				entries
			);
		}
	}

	function onMigrateHash() {
		return String(window.location.hash || '').indexOf('zr_oss_migrate') !== -1;
	}

	function applyControls(controls) {
		var map = {
			start: 'oss-mig-start',
			pause: 'oss-mig-pause',
			resume: 'oss-mig-resume',
			stop: 'oss-mig-stop',
			rollback: 'oss-mig-rollback'
		};
		controls = controls || {};
		Object.keys(map).forEach(function (k) {
			var on = !!controls[k];
			$('[data-field-id="zr_oss_mig_actions"] [data-pili-table-action-key="' + map[k] + '"]').each(function () {
				$(this).prop('disabled', !on);
				if (on) {
					$(this).removeAttr('disabled').attr('aria-disabled', 'false');
				} else {
					$(this).attr('disabled', 'disabled').attr('aria-disabled', 'true');
				}
			});
		});
	}

	function setRollbackHint(hint, count) {
		var text = hint || '';
		$('[data-zr-oss-mig-rollback-hint]').text(text);
		var $modal = $('[data-zr-oss-mig-rollback-modal]');
		if (!$modal.length) {
			$modal = $('[data-field-id="zr_oss_mig_rollback_modal"] p').first();
		}
		if ($modal.length && text) {
			var extra = (cfg().i18n && cfg().i18n.rollback) || '';
			if (count > 0 && extra) {
				$modal.text(text + ' ' + extra);
			} else {
				$modal.text(text);
			}
		}
	}

	function setStatusDesc(task, label) {
		var status = label || (task && task.status ? String(task.status) : '');
		var $desc = $('[data-field-id="zr_oss_mig_progress"]').find('p.mt-2, .pili-text-desc, [class*="description"]').first();
		if ($desc.length && status) {
			$desc.text(status + ' · ' + (task.scanned || 0) + ' / ' + (task.uploaded || 0) + ' / ' + (task.replaced || 0));
		}
	}

	var firstPoll = true;
	var lastFailRefresh = '';

	function renderStatus(res) {
		if (!res) {
			return;
		}
		var data = res.data || res;
		var task = data.task || {};
		var showBar = isProgressVisible(task, !!data.running);
		toggleProgress(showBar);
		if (showBar) {
			setProgress(data.percent || 0);
			setStatusDesc(task, data.status_label || '');
		}
		setStats(task, !!data.stats_historical);
		ensureLimitNotes();
		applyControls(data.controls || {});
		setRollbackHint(data.rollback_hint || '', parseInt(data.backup_count || task.backup_count || 0, 10) || 0);
		if ($.isArray(data.logs)) {
			setLogs(data.logs);
		}
		if (firstPoll) {
			refreshTable('zr_oss_mig_checks');
			refreshTable('zr_oss_mig_fails');
			firstPoll = false;
			lastFailRefresh = String(task.status || '') + ':' + String(task.failed || 0);
		} else {
			var failKey = String(task.status || '') + ':' + String(task.failed || 0);
			var failNow = ['queued', 'scanning', 'running', 'paused', 'stopped', 'done', 'failed', 'failed_gate', 'rolling_back', 'rolled_back'].indexOf(String(task.status || '')) !== -1;
			if (failNow && failKey !== lastFailRefresh) {
				refreshTable('zr_oss_mig_fails');
				lastFailRefresh = failKey;
			}
		}
	}

	function poll() {
		if (!onPage() || !onMigrateHash() || typeof zaprocketOssMig === 'undefined') {
			return;
		}
		post('zaprocket_oss_mig_status').done(function (res) {
			if (res && res.success) {
				renderStatus(res);
			}
		});
	}

	function send(act, extra) {
		var map = {
			start: 'zaprocket_oss_mig_start',
			pause: 'zaprocket_oss_mig_pause',
			resume: 'zaprocket_oss_mig_resume',
			stop: 'zaprocket_oss_mig_stop',
			rollback: 'zaprocket_oss_mig_rollback'
		};
		if (!map[act]) {
			return $.Deferred().reject().promise();
		}
		return post(map[act], extra || {}).done(function (res) {
			if (res && res.success) {
				toast('success', (res.data && res.data.message) || '');
				poll();
			} else {
				toast('error', (res && res.data && res.data.message) || (cfg().i18n && cfg().i18n.fail) || '');
				refreshTable('zr_oss_mig_checks');
			}
		}).fail(function () {
			toast('error', (cfg().i18n && cfg().i18n.fail) || '');
		});
	}

	function openRollback() {
		var Modal = window.PiliXunModal;
		if (!Modal || typeof Modal.open !== 'function') {
			if (window.confirm((cfg().i18n && cfg().i18n.rollback) || '')) {
				send('rollback');
			}
			return;
		}
		Modal.open('zr_oss_mig_rollback_modal', {
			onAction: function (action, api) {
				if (action === 'confirm') {
					send('rollback').always(function () {
						if (api && typeof api.close === 'function') {
							api.close();
						}
					});
				}
			}
		});
	}

	$(document).on('click', '[data-pili-table-action-key^="oss-mig-"]', function (e) {
		e.preventDefault();
		if (!onPage()) {
			return;
		}
		var $btn = $(this);
		if ($btn.prop('disabled') || $btn.attr('aria-disabled') === 'true' || $btn.attr('disabled')) {
			return;
		}
		var key = String($btn.attr('data-pili-table-action-key') || '');
		var act = key.replace('oss-mig-', '');
		if (act === 'retry') {
			var id = $btn.attr('data-id') || '';
			runTableAction('zr_oss_mig_fails', 'oss-mig-retry', function () {
				return post('zaprocket_oss_mig_retry', { item_id: id }).done(function () {
					poll();
					refreshTable('zr_oss_mig_fails');
				});
			});
			return;
		}
		if (act === 'rollback') {
			openRollback();
			return;
		}
		if (act === 'start') {
			if (!isAckOn()) {
				toast('error', (cfg().i18n && cfg().i18n.needAck) || '');
				return;
			}
			if (types().length < 1) {
				toast('error', (cfg().i18n && cfg().i18n.needCpt) || '');
				return;
			}
			toggleProgress(true);
		}
		runTableAction('zr_oss_mig_actions', key, function () {
			var extra = {};
			if (act === 'start') {
				extra.ack = 1;
				extra.types = types();
			}
			return send(act, extra);
		});
	});

	$(function () {
		toggleProgress(false);
		if (onMigrateHash()) {
			poll();
			ensureLimitNotes();
		}
		window.setInterval(poll, 4000);
		$(window).on('hashchange', function () {
			firstPoll = true;
			if (onMigrateHash()) {
				poll();
				ensureLimitNotes();
			}
		});
		$(document).on('pili:section:loaded pili:field:loaded pili:field:added', function () {
			if (onMigrateHash() && onPage()) {
				ensureLimitNotes();
				poll();
			}
		});
	});
})(jQuery);
