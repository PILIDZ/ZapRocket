/**
 * PILI Framework Dialog Component
 * 弹窗组件（icon 区默认闪团团 / PiliBot，按 type 切换表情；引擎未加载时回退 SVG）
 *
 * @version 1.1
 * @author PILI Framework
 */

window.PILI = window.PILI || {};
window.PILI.instances = window.PILI.instances || {};
window.PILI.boots = window.PILI.boots || {};
window.PILI.mounts = window.PILI.mounts || {};
window.PILI.bagsStore = window.PILI.bagsStore || {};
window.PILI.ajaxById = window.PILI.ajaxById || {};
window.PILI.runtimeById = window.PILI.runtimeById || {};
window.PILI.lazyById = window.PILI.lazyById || {};

/**
 * Resolve optionId from form / root / aliases.
 *
 * @param {Element|jQuery|string|null} ctx Context element or optionId string.
 * @return {string}
 */
window.PILI.resolveOptionId = function (ctx) {
	if (typeof ctx === 'string' && ctx) {
		return ctx;
	}
	var el = null;
	if (ctx) {
		if (ctx.jquery && ctx.length) {
			el = ctx[0];
		} else if (ctx.nodeType) {
			el = ctx;
		}
	}
	if (el && el.getAttribute) {
		var direct = el.getAttribute('data-option-id');
		if (direct) {
			return String(direct);
		}
		if (el.closest) {
			var host = el.closest('[data-option-id], form.pili-form, .pili-framework-page');
			if (host && host.getAttribute) {
				var oid = host.getAttribute('data-option-id');
				if (oid) {
					return String(oid);
				}
			}
		}
	}
	var form = document.getElementById('pili-options-form') || document.querySelector('form.pili-form[data-option-id]');
	if (form && form.getAttribute) {
		var fid = form.getAttribute('data-option-id');
		if (fid) {
			return String(fid);
		}
	}
	var page = document.querySelector('.pili-framework-page[data-option-id]');
	if (page && page.getAttribute) {
		var pid = page.getAttribute('data-option-id');
		if (pid) {
			return String(pid);
		}
	}
	if (window.piliRuntime && window.piliRuntime.optionId) {
		return String(window.piliRuntime.optionId);
	}
	if (window.piliAjax && window.piliAjax.optionId) {
		return String(window.piliAjax.optionId);
	}
	return '';
};

window.PILI.ajaxCfg = function (ctx) {
	var id = window.PILI.resolveOptionId(ctx);
	if (id && window.PILI.ajaxById && window.PILI.ajaxById[id]) {
		return window.PILI.ajaxById[id];
	}
	return window.piliAjax || {};
};

window.PILI.runtimeCfg = function (ctx) {
	var id = window.PILI.resolveOptionId(ctx);
	if (id && window.PILI.runtimeById && window.PILI.runtimeById[id]) {
		return window.PILI.runtimeById[id];
	}
	return window.piliRuntime || {};
};

window.PILI.lazyCfg = function (ctx) {
	var id = window.PILI.resolveOptionId(ctx);
	if (id && window.PILI.lazyById && window.PILI.lazyById[id] && window.PILI.lazyById[id].sections) {
		return window.PILI.lazyById[id].sections;
	}
	return window.piliLazySections || null;
};

/**
 * @param {Element|jQuery|string|null} ctx Element, optionId, or instance_id (from setBag).
 * @return {string}
 */
window.PILI.instanceId = function (ctx) {
	if (typeof ctx === 'string' && ctx !== '') {
		if (window.PILI.runtimeById && window.PILI.runtimeById[ctx] && window.PILI.runtimeById[ctx].instanceId) {
			return String(window.PILI.runtimeById[ctx].instanceId);
		}
		// pili_localize_bag passes Config instance_id as third arg.
		return String(ctx);
	}
	var rt = window.PILI.runtimeCfg(ctx);
	if (rt && rt.instanceId) {
		return String(rt.instanceId);
	}
	var el = null;
	if (ctx && ctx.nodeType) {
		el = ctx;
	} else if (ctx && ctx.jquery && ctx.length) {
		el = ctx[0];
	}
	if (el && el.closest) {
		var root = el.closest('[data-pili-instance]');
		if (root && root.getAttribute) {
			var di = root.getAttribute('data-pili-instance');
			if (di) {
				return String(di);
			}
		}
	}
	if (window.piliRuntime && window.piliRuntime.instanceId) {
		return String(window.piliRuntime.instanceId);
	}
	return 'default';
};

window.PILI.setBag = function (key, data, ctx) {
	var id = window.PILI.instanceId(ctx);
	if (!window.PILI.instances[id]) {
		window.PILI.instances[id] = { bags: {} };
	}
	if (!window.PILI.instances[id].bags) {
		window.PILI.instances[id].bags = {};
	}
	window.PILI.instances[id].bags[key] = data || {};
	// Soft legacy fallback only when a single runtime bucket exists (no dual-instance overwrite).
	var bucketCount = window.PILI.runtimeById ? Object.keys(window.PILI.runtimeById).length : 0;
	if (bucketCount <= 1) {
		window.PILI.bagsStore[key] = data || {};
	}
};

window.PILI.bag = function (key, ctx) {
	var id = window.PILI.instanceId(ctx);
	var inst = window.PILI.instances[id];
	if (inst && inst.bags && inst.bags[key]) {
		return inst.bags[key];
	}
	return window.PILI.bagsStore[key] || {};
};
window.PILI.registerBoot = function (name, fn) {
	window.PILI.boots[name] = fn;
	return fn;
};
window.PILI.bootFn = function (name) {
	return window.PILI.boots[name];
};
window.PILI.boot = function (name, $root) {
	var fn = window.PILI.boots[name];
	if (typeof fn === 'function') {
		return fn($root);
	}
};
window.PILI.registerMount = function (name, fn) {
	window.PILI.mounts[name] = fn;
	return fn;
};
window.PILI.mountFn = function (name) {
	return window.PILI.mounts[name];
};
window.PILI.mount = function (name, host) {
	var fn = window.PILI.mounts[name];
	if (typeof fn === 'function') {
		return fn(host);
	}
};

class PiliDialog {
	constructor() {
		this.currentDialog = null;
		this.botInstance = null;
		this.init();
	}

	/**
	 * 初始化弹窗组件
	 */
	init() {
		this.createDialogContainer();
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && this.currentDialog) {
				const pending = this.currentDialog;
				this.close(() => {
					if (pending && typeof pending.resolve === 'function') {
						pending.resolve(false);
					}
				});
			}
		});
	}

	/**
	 * 创建弹窗HTML容器
	 */
	createDialogContainer() {
		if (document.getElementById('pili-dialog')) {
			this.dialogElement = document.getElementById('pili-dialog');
			this.backdropElement = document.getElementById('dialog-backdrop');
			this.panelElement = document.getElementById('dialog-panel');
			this.scrollElement = this.dialogElement
				? this.dialogElement.querySelector('[data-pili-dialog-scroll]')
				: null;
			this.ensureMessageElement();
			this.ensureDialogClosed();
			return;
		}
		const closeLabel = (PILI.bag('dialogDefaults').closeLabel)
			|| (typeof window.pilipost__ === 'function' ? window.pilipost__('关闭弹窗') : '关闭弹窗');
		const dialogHTML = `
			<div id="pili-dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title" class="relative hidden" hidden style="display:none;pointer-events:none;z-index:999999;">
				<div aria-hidden="true" class="fixed inset-0 bg-white/20 backdrop-blur-sm transition-all duration-300 opacity-0" id="dialog-backdrop" style="z-index:999998;pointer-events:none;"></div>
				<div class="fixed inset-0 w-screen overflow-y-auto" style="z-index:999999;pointer-events:none;" data-pili-dialog-scroll>
					<div class="flex min-h-full w-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<div id="dialog-panel" class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all duration-300 opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 sm:my-8 w-full sm:w-full sm:max-w-lg sm:p-6">
							<button type="button" id="dialog-close" aria-label="${String(closeLabel).replace(/"/g, '&quot;')}" class="absolute right-3 top-3 inline-flex items-center justify-center rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
									<path d="M6 6l12 12M18 6 6 18" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</button>
							<div>
								<div id="dialog-icon" class="mx-auto flex size-12 items-center justify-center rounded-full" aria-hidden="true"></div>
								<div class="mt-3 text-center sm:mt-5">
									<h3 id="dialog-title" class="text-base font-semibold text-gray-900"></h3>
									<div class="mt-2">
										<div id="dialog-message" class="pili-dialog-message text-sm text-gray-500"></div>
									</div>
								</div>
							</div>
							<div id="dialog-buttons" class="mt-5 sm:mt-6"></div>
						</div>
					</div>
				</div>
			</div>
		`;
		document.body.insertAdjacentHTML('beforeend', dialogHTML);
		this.dialogElement = document.getElementById('pili-dialog');
		this.backdropElement = document.getElementById('dialog-backdrop');
		this.panelElement = document.getElementById('dialog-panel');
		this.scrollElement = this.dialogElement
			? this.dialogElement.querySelector('[data-pili-dialog-scroll]')
			: null;
		this.ensureMessageElement();
		this.ensureDialogClosed();
	}

	/**
	 * 消息区须为 div：块级 HTML（任务队列进度详情等）塞进 p 会被浏览器拆标签，导致按钮区布局异常。
	 *
	 * @returns {HTMLElement|null}
	 */
	ensureMessageElement() {
		let el = document.getElementById('dialog-message');
		if (!el) {
			return null;
		}
		if (el.tagName && el.tagName.toLowerCase() === 'p') {
			const div = document.createElement('div');
			div.id = 'dialog-message';
			div.className = String(el.className || '') + ' pili-dialog-message';
			el.parentNode.replaceChild(div, el);
			el = div;
		} else if (!el.classList.contains('pili-dialog-message')) {
			el.classList.add('pili-dialog-message');
		}
		return el;
	}

	/**
	 * 强制关闭态：避免全屏层在无 CSS / 半开状态下挡住整页点击。
	 */
	ensureDialogClosed() {
		if (!this.dialogElement) {
			return;
		}
		this.dialogElement.classList.add('hidden');
		this.dialogElement.setAttribute('hidden', '');
		this.dialogElement.style.display = 'none';
		this.dialogElement.style.pointerEvents = 'none';
		if (this.backdropElement) {
			this.backdropElement.style.pointerEvents = 'none';
			this.backdropElement.onclick = null;
			this.backdropElement.classList.add('opacity-0');
			this.backdropElement.classList.remove('opacity-100');
		}
		if (this.scrollElement) {
			this.scrollElement.style.pointerEvents = 'none';
		}
		if (this.panelElement) {
			this.panelElement.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
			this.panelElement.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
		}
		document.body.style.overflow = '';
	}

	/**
	 * 显示确认弹窗
	 * @param {Object} options 配置选项
	 * @returns {Promise} 返回用户选择的Promise
	 */
	confirm(options = {}) {
		const globalDefaults = PILI.bag('dialogDefaults');
		const t = (msgid, fallbackKey) => {
			if (fallbackKey && globalDefaults[fallbackKey]) {
				return String(globalDefaults[fallbackKey]);
			}
			return (typeof window.pilipost__ === 'function' ? window.pilipost__(msgid) : msgid);
		};
		const config = {
			title: t('确认', 'confirmTitle'),
			message: t('您确定要执行此操作吗？', 'confirmMessage'),
			confirmText: t('确定', 'confirmText'),
			cancelText: t('取消', 'cancelText'),
			type: 'warning',
			bot: globalDefaults.bot !== false,
			botSize: globalDefaults.botSize || 72,
			...options
		};

		return new Promise((resolve) => {
			this.show(config, resolve, true);
		});
	}

	/**
	 * 显示提示弹窗
	 * @param {Object} options 配置选项
	 * @returns {Promise} 返回Promise
	 */
	alert(options = {}) {
		const globalDefaults = PILI.bag('dialogDefaults');
		const t = (msgid, fallbackKey) => {
			if (fallbackKey && globalDefaults[fallbackKey]) {
				return String(globalDefaults[fallbackKey]);
			}
			return (typeof window.pilipost__ === 'function' ? window.pilipost__(msgid) : msgid);
		};
		const config = {
			title: t('提示', 'alertTitle'),
			message: t('操作完成', 'alertMessage'),
			confirmText: t('确定', 'confirmText'),
			type: 'info',
			bot: globalDefaults.bot !== false,
			botSize: globalDefaults.botSize || 72,
			...options
		};

		return new Promise((resolve) => {
			this.show(config, resolve, false);
		});
	}

	/**
	 * 显示带表单输入的弹窗
	 * @param {Object} options 配置
	 * @returns {Promise<{confirmed:boolean, values:Object}>}
	 */
	prompt(options = {}) {
		const globalDefaults = PILI.bag('dialogDefaults');
		const t = (msgid, fallbackKey) => {
			if (fallbackKey && globalDefaults[fallbackKey]) {
				return String(globalDefaults[fallbackKey]);
			}
			return (typeof window.pilipost__ === 'function' ? window.pilipost__(msgid) : msgid);
		};
		const config = {
			title: t('Enter…', 'promptTitle'),
			message: '',
			confirmText: t('确定', 'confirmText'),
			cancelText: t('取消', 'cancelText'),
			type: 'info',
			bot: globalDefaults.bot !== false,
			botSize: globalDefaults.botSize || 72,
			fields: [],
			onReady: null,
			validate: null,
			...options
		};

		return new Promise((resolve) => {
			this.setPromptContent(config);
			this.setPromptButtons(config, resolve);
			this.open();
			this.currentDialog = { resolve, isConfirm: true, isPrompt: true };
			if (typeof config.onReady === 'function') {
				try {
					config.onReady(this.panelElement);
				} catch (e) {
					// ignore hook errors
				}
			}
		});
	}

	/**
	 * 显示弹窗
	 * @param {Object} config 配置
	 * @param {Function} resolve Promise resolve函数
	 * @param {Boolean} isConfirm 是否为确认弹窗
	 */
	show(config, resolve, isConfirm = false) {
		const closeBtn = document.getElementById('dialog-close');
		if (closeBtn) {
			const closeLabel = (PILI.bag('dialogDefaults') && PILI.bag('dialogDefaults').closeLabel)
				|| (typeof window.pilipost__ === 'function' ? window.pilipost__('关闭弹窗') : '关闭弹窗');
			closeBtn.setAttribute('aria-label', String(closeLabel));
		}
		this.setContent(config);
		this.setButtons(config, resolve, isConfirm);
		this.open();
		this.currentDialog = { resolve, isConfirm };
	}

	canUseBot() {
		return !!(
			window.PiliBotCore &&
			typeof window.PiliBotCore.createInstance === 'function' &&
			window.PiliBotCore.STATES
		);
	}

	resolveBotSize(size) {
		if (typeof size === 'number' && size > 0) {
			return Math.round(size);
		}
		const n = parseInt(size, 10);
		return n > 0 ? n : 72;
	}

	/**
	 * Dialog type → 闪团团表情态（与 Toast 对齐，warning 用 warn 警示脸）
	 */
	mapBotState(type, explicit) {
		const core = window.PiliBotCore;
		if (explicit && core && core.STATES && core.STATES[explicit]) {
			return explicit;
		}
		const alias = {
			ok: 'success',
			done: 'success',
			danger: 'error',
			fail: 'error',
			warn: 'warning',
			caution: 'warning',
			default: 'info',
			neutral: 'info',
			primary: 'info'
		};
		let key = String(type || 'info').toLowerCase();
		if (alias[key]) {
			key = alias[key];
		}
		const map = {
			success: 'success',
			error: 'error',
			warning: 'warn',
			info: 'streaming',
			approval: 'approval',
			thinking: 'thinking',
			idle: 'idle'
		};
		if (map[key]) {
			return map[key];
		}
		if (core && core.STATES && core.STATES[key]) {
			return key;
		}
		return 'streaming';
	}

	destroyBot() {
		this.stopBotCycle();
		if (this.botInstance && typeof this.botInstance.destroy === 'function') {
			try {
				this.botInstance.destroy();
			} catch (e) {
				/* noop */
			}
		}
		this.botInstance = null;
	}

	/**
	 * 停止表情轮播。
	 */
	stopBotCycle() {
		if (this._botCycleTimer) {
			clearInterval(this._botCycleTimer);
			this._botCycleTimer = null;
		}
	}

	/**
	 * 弹窗打开期间随机切换吉祥物表情（避免一直停在警示脸）。
	 *
	 * @param {string[]} states State keys.
	 * @param {number}   ms     Interval.
	 */
	startBotCycle(states, ms) {
		this.stopBotCycle();
		const pool = Array.isArray(states)
			? states.filter((s) => typeof s === 'string' && s)
			: [];
		if (!this.botInstance || typeof this.botInstance.setState !== 'function' || pool.length < 2) {
			return;
		}
		const interval = typeof ms === 'number' && ms >= 800 ? ms : 2600;
		let last = '';
		this._botCycleTimer = setInterval(() => {
			if (!this.botInstance || typeof this.botInstance.setState !== 'function') {
				this.stopBotCycle();
				return;
			}
			let next = pool[Math.floor(Math.random() * pool.length)];
			if (pool.length > 1 && next === last) {
				next = pool[(pool.indexOf(next) + 1 + Math.floor(Math.random() * (pool.length - 1))) % pool.length];
			}
			last = next;
			try {
				this.botInstance.setState(next);
			} catch (e) {
				/* noop */
			}
		}, interval);
	}

	mountBot(iconContainer, state, botSize) {
		if (!this.canUseBot() || !iconContainer) {
			return null;
		}
		const px = this.resolveBotSize(botSize);
		iconContainer.className =
			'mx-auto flex items-center justify-center pili-dialog-icon pili-dialog-icon--pilibot';
		iconContainer.style.width = px + 'px';
		iconContainer.style.height = px + 'px';
		iconContainer.innerHTML =
			'<canvas class="pili-dialog-icon__canvas" width="' +
			px +
			'" height="' +
			px +
			'" aria-hidden="true"></canvas>';
		const canvas = iconContainer.querySelector('canvas.pili-dialog-icon__canvas');
		if (!canvas) {
			return null;
		}
		const bot = window.PiliBotCore.createInstance(canvas, {
			id: 'dialog-bot-' + Date.now(),
			state: 'idle',
			followPointer: false,
			transparentBg: true
		});
		if (bot && state && state !== 'idle') {
			window.requestAnimationFrame(() => {
				try {
					bot.setState(state);
				} catch (e) {
					/* noop */
				}
			});
		}
		return bot;
	}

	/**
	 * 设置弹窗内容
	 * @param {Object} config 配置
	 */
	setContent(config) {
		this.destroyBot();
		const iconContainer = document.getElementById('dialog-icon');
		const htmlBody = config.html || config.messageHtml || '';
		const hasHtmlBody = !!htmlBody;
		// 与顶栏「重置当前页」同款：默认展示 PiliBot；仅显式 bot:false 时隐藏（HTML 正文也保留图标）。
		const wantBot = config.bot !== false;
		const botState = this.mapBotState(config.type, config.state);
		const botSize = this.resolveBotSize(config.botSize);

		if (this.panelElement) {
			this.panelElement.classList.toggle('pili-dialog-panel--html', hasHtmlBody);
		}

		if (iconContainer) {
			if (wantBot) {
				iconContainer.hidden = false;
				iconContainer.style.display = '';
			} else {
				iconContainer.innerHTML = '';
				iconContainer.hidden = true;
				iconContainer.style.display = 'none';
			}
		}

		if (wantBot && this.canUseBot()) {
			this.botInstance = this.mountBot(iconContainer, botState, botSize);
			if (!this.botInstance) {
				this.renderFallbackIcon(iconContainer, config.type);
			} else if (config.botCycle || (Array.isArray(config.botCycleStates) && config.botCycleStates.length)) {
				const core = window.PiliBotCore;
				const fallbackPool = [
					'happy',
					'winkSoft',
					'winkHi',
					'peek',
					'lookLeft',
					'lookRight',
					'listening',
					'focused',
					'squint',
					'streaming',
					'approval',
					'idle'
				];
				let pool = Array.isArray(config.botCycleStates) && config.botCycleStates.length
					? config.botCycleStates.slice()
					: fallbackPool;
				if (core && core.STATES) {
					pool = pool.filter((s) => !!core.STATES[s]);
				}
				if (pool.length < 2) {
					pool = fallbackPool.filter((s) => !core || !core.STATES || !!core.STATES[s]);
				}
				this.startBotCycle(pool, config.botCycleMs);
			}
		} else if (wantBot && iconContainer) {
			this.renderFallbackIcon(iconContainer, config.type);
		}

		document.getElementById('dialog-title').textContent = config.title;
		const messageEl = this.ensureMessageElement();
		if (messageEl && htmlBody) {
			messageEl.innerHTML = String(htmlBody);
			messageEl.classList.add('text-left', 'pili-dialog-message--html');
		} else if (messageEl) {
			messageEl.textContent = config.message;
			messageEl.classList.remove('text-left', 'pili-dialog-message--html');
		}
		this.clearPromptFields();
	}

	renderFallbackIcon(iconContainer, type) {
		const iconConfig = this.getIconConfig(type);
		iconContainer.className = `mx-auto flex size-12 items-center justify-center rounded-full pili-dialog-icon ${iconConfig.bgClass}`;
		iconContainer.style.width = '';
		iconContainer.style.height = '';
		iconContainer.innerHTML = iconConfig.icon;
	}

	/**
	 * 设置带输入项的弹窗内容
	 * @param {Object} config 配置
	 */
	setPromptContent(config) {
		this.setContent(config);
		document.getElementById('dialog-message').textContent = config.message || '';

		let formWrap = this.panelElement.querySelector('#pili-dialog-prompt-fields');
		if (!formWrap) {
			const messageWrap = this.panelElement.querySelector('#dialog-message')?.parentElement;
			if (messageWrap) {
				formWrap = document.createElement('div');
				formWrap.id = 'pili-dialog-prompt-fields';
				formWrap.className = 'mt-4 space-y-3 text-left';
				messageWrap.appendChild(formWrap);
			}
		}
		if (!formWrap) return;
		formWrap.style.display = '';
		formWrap.innerHTML = this.renderPromptFields(config.fields || []);
	}

	/**
	 * 清理 prompt 专用字段区域，防止与 confirm/alert 串场
	 */
	clearPromptFields() {
		const formWrap = this.panelElement.querySelector('#pili-dialog-prompt-fields');
		if (!formWrap) return;
		formWrap.innerHTML = '';
		formWrap.style.display = 'none';
	}

	renderPromptFields(fields) {
		if (!Array.isArray(fields) || fields.length === 0) return '';
		const inputBaseClass = 'pili-input pili-focusable block w-full !min-h-10 !h-10 rounded-md bg-white !px-3 !py-2.5 appearance-none !text-base/6 !leading-6 box-border text-gray-900 border border-gray-300 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none sm:text-sm/6';
		const textareaClass = 'pili-input pili-focusable block w-full rounded-md bg-white !px-3 !py-2.5 box-border text-gray-900 border border-gray-300 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none sm:text-sm/6';
		const controlStyle = 'width:100%;box-sizing:border-box;';
		return fields.map((field) => {
			const f = {
				name: '',
				label: '',
				type: 'text',
				value: '',
				placeholder: '',
				help: '',
				min: '',
				max: '',
				step: '',
				options: [],
				required: false,
				rows: 3,
				...field
			};
			const required = f.required ? 'required' : '';
			const label = f.label ? `<label class="block text-sm font-medium text-gray-700 mb-1" for="pili-prompt-${f.name}">${f.label}</label>` : '';
			const help = f.help ? `<p class="mt-1 text-xs text-gray-500">${f.help}</p>` : '';
			let control = '';
			if (f.type === 'textarea') {
				control = `<textarea id="pili-prompt-${f.name}" data-prompt-name="${f.name}" class="${textareaClass}" style="${controlStyle}" rows="${Number(f.rows) || 3}" placeholder="${this.escapeHtml(String(f.placeholder || ''))}" ${required}>${this.escapeHtml(String(f.value || ''))}</textarea>`;
			} else if (f.type === 'select') {
				const opts = Array.isArray(f.options) ? f.options : [];
				const optionsHtml = opts.map((opt) => {
					const value = typeof opt === 'object' ? String(opt.value ?? '') : String(opt);
					const text = typeof opt === 'object' ? String(opt.label ?? value) : value;
					const selected = String(f.value) === value ? 'selected' : '';
					return `<option value="${this.escapeHtml(value)}" ${selected}>${this.escapeHtml(text)}</option>`;
				}).join('');
				control = `<select id="pili-prompt-${f.name}" data-prompt-name="${f.name}" class="${inputBaseClass}" style="${controlStyle}" ${required}>${optionsHtml}</select>`;
			} else {
				const min = f.min !== '' ? `min="${this.escapeHtml(String(f.min))}"` : '';
				const max = f.max !== '' ? `max="${this.escapeHtml(String(f.max))}"` : '';
				const step = f.step !== '' ? `step="${this.escapeHtml(String(f.step))}"` : '';
				control = `<input id="pili-prompt-${f.name}" data-prompt-name="${f.name}" type="${this.escapeHtml(String(f.type || 'text'))}" value="${this.escapeHtml(String(f.value || ''))}" placeholder="${this.escapeHtml(String(f.placeholder || ''))}" class="${inputBaseClass}" style="${controlStyle}" ${min} ${max} ${step} ${required} />`;
			}
			return `<div data-prompt-field="${this.escapeHtml(String(f.name))}">${label}${control}${help}</div>`;
		}).join('');
	}

	collectPromptValues() {
		const values = {};
		this.panelElement.querySelectorAll('[data-prompt-name]').forEach((el) => {
			const key = el.getAttribute('data-prompt-name');
			if (!key) return;
			values[key] = (el.value || '').toString();
		});
		return values;
	}

	escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	/**
	 * 获取图标配置（无表情引擎时的回退）
	 * @param {String} type 类型
	 * @returns {Object} 图标配置
	 */
	getIconConfig(type) {
		const configs = {
			success: {
				bgClass: 'bg-green-100',
				icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6 text-green-600"><path d="m4.5 12.75 6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round" /></svg>'
			},
			error: {
				bgClass: 'bg-red-100',
				icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6 text-red-600"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" /></svg>'
			},
			danger: {
				bgClass: 'bg-red-100',
				icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6 text-red-600"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" /></svg>'
			},
			warning: {
				bgClass: 'bg-yellow-100',
				icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6 text-yellow-600"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linecap="round" stroke-linejoin="round" /></svg>'
			},
			info: {
				bgClass: 'bg-blue-100',
				icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6 text-blue-600"><path d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z" stroke-linecap="round" stroke-linejoin="round" /></svg>'
			}
		};

		return configs[type] || configs.info;
	}

	/**
	 * 设置按钮
	 * @param {Object} config 配置
	 * @param {Function} resolve Promise resolve函数
	 * @param {Boolean} isConfirm 是否为确认弹窗
	 */
	setButtons(config, resolve, isConfirm) {
		const buttonsContainer = document.getElementById('dialog-buttons');
		const closeBtn = document.getElementById('dialog-close');
		if (isConfirm) {
			buttonsContainer.className = 'mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3';
			buttonsContainer.innerHTML = `
				<button type="button" id="dialog-confirm" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:col-start-2">
					${config.confirmText}
				</button>
				<button type="button" id="dialog-cancel" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0">
					${config.cancelText}
				</button>
			`;
			document.getElementById('dialog-confirm').addEventListener('click', () => {
				this.close(() => resolve(true));
			});
			document.getElementById('dialog-cancel').addEventListener('click', () => {
				this.close(() => resolve(false));
			});
		} else if (config.showCancel) {
			const cancelText =
				config.cancelText ||
				(typeof window.pilipost__ === 'function' ? window.pilipost__('取消') : '取消');
			buttonsContainer.className = 'mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3';
			buttonsContainer.innerHTML = `
				<button type="button" id="dialog-confirm" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:col-start-2">
					${config.confirmText}
				</button>
				<button type="button" id="dialog-cancel" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0">
					${cancelText}
				</button>
			`;
			document.getElementById('dialog-confirm').addEventListener('click', () => {
				this.close(() => resolve(true));
			});
			document.getElementById('dialog-cancel').addEventListener('click', () => {
				this.close(() => resolve(false));
			});
		} else {
			buttonsContainer.className = 'mt-5 sm:mt-6';
			buttonsContainer.innerHTML = `
				<button type="button" id="dialog-ok" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
					${config.confirmText}
				</button>
			`;
			document.getElementById('dialog-ok').addEventListener('click', () => {
				this.close(() => resolve(true));
			});
		}
		if (closeBtn) {
			closeBtn.onclick = () => {
				this.close(() => resolve(false));
			};
		}
		if (this.backdropElement) {
			this.backdropElement.onclick = () => {
				this.close(() => resolve(false));
			};
		}
	}

	setPromptButtons(config, resolve) {
		const buttonsContainer = document.getElementById('dialog-buttons');
		const closeBtn = document.getElementById('dialog-close');
		const cancelText = (config && config.cancelText) ? config.cancelText : (typeof window.pilipost__ === 'function' ? window.pilipost__('取消') : '取消');
		buttonsContainer.className = 'mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3';
		buttonsContainer.innerHTML = `
			<button type="button" id="dialog-confirm" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:col-start-2">
				${config.confirmText}
			</button>
			<button type="button" id="dialog-cancel" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0">
				${cancelText}
			</button>
		`;

		const confirmBtn = document.getElementById('dialog-confirm');
		const cancelBtn = document.getElementById('dialog-cancel');

		confirmBtn.addEventListener('click', () => {
			const values = this.collectPromptValues();
			if (typeof config.validate === 'function') {
				const result = config.validate(values, this.panelElement);
				if (result === false) return;
				if (typeof result === 'string' && result) {
					if (PILI.alert) {
						PILI.alert({ title: (typeof window.pilipost__ === 'function' ? window.pilipost__('提示') : '提示'), message: result, type: 'warning' });
					} else {
						alert(result);
					}
					return;
				}
			}
			this.close(() => resolve({ confirmed: true, values }));
		});
		cancelBtn.addEventListener('click', () => {
			this.close(() => resolve({ confirmed: false, values: {} }));
		});
		if (closeBtn) {
			closeBtn.onclick = () => {
				this.close(() => resolve({ confirmed: false, values: {} }));
			};
		}
		if (this.backdropElement) {
			this.backdropElement.onclick = () => {
				this.close(() => resolve({ confirmed: false, values: {} }));
			};
		}
	}

	/**
	 * 打开弹窗
	 */
	open() {
		if (!this.dialogElement) {
			return;
		}
		this.dialogElement.classList.remove('hidden');
		this.dialogElement.removeAttribute('hidden');
		this.dialogElement.style.display = '';
		// 块编辑器骨架/弹出层常 ≥100000；低于此会「点了确认却看不见弹窗」。
		this.dialogElement.style.zIndex = '1000100';
		this.dialogElement.style.pointerEvents = 'auto';
		if (this.backdropElement) {
			this.backdropElement.style.pointerEvents = 'auto';
			this.backdropElement.style.zIndex = '1000099';
		}
		if (this.scrollElement) {
			this.scrollElement.style.pointerEvents = 'auto';
			this.scrollElement.style.zIndex = '1000100';
		}
		if (this.panelElement) {
			this.panelElement.style.pointerEvents = 'auto';
		}
		requestAnimationFrame(() => {
			requestAnimationFrame(() => {
				if (this.backdropElement) {
					this.backdropElement.classList.remove('opacity-0');
					this.backdropElement.classList.add('opacity-100');
				}
				if (this.panelElement) {
					this.panelElement.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
					this.panelElement.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
				}
			});
		});
		document.body.style.overflow = 'hidden';
	}

	/**
	 * 关闭弹窗
	 */
	close(done) {
		if (!this.currentDialog) return;
		if (this.backdropElement) {
			this.backdropElement.classList.remove('opacity-100');
			this.backdropElement.classList.add('opacity-0');
		}
		if (this.panelElement) {
			this.panelElement.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
			this.panelElement.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
		}
		setTimeout(() => {
			this.destroyBot();
			this.ensureDialogClosed();
			document.body.style.overflow = '';
			if (typeof done === 'function') {
				done();
			}
		}, 300);
		this.currentDialog = null;
	}
}

function initXunDialog() {
	if (!PILI.Dialog) {
		PILI.Dialog = new PiliDialog();
		PILI.confirm = (options) => {
			return PILI.Dialog.confirm(options);
		};
		PILI.alert = (options) => {
			return PILI.Dialog.alert(options);
		};
		PILI.prompt = (options) => {
			return PILI.Dialog.prompt(options);
		};
	}
}

initXunDialog();

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initXunDialog);
} else {
	initXunDialog();
}
