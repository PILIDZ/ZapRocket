/**
 * ZapRocket JS gettext helper for pili-framework (pilipostT → window.pilipost__).
 * wp_localize_script: window.zaprocketL10n = { locale, messages: { msgid: msgstr } }
 */
(function (w) {
	'use strict';

	function getMessages() {
		var bag = w.zaprocketL10n || {};
		return bag.messages || {};
	}

	function translate(msgid) {
		if (msgid == null || msgid === '') {
			return msgid;
		}
		var key = String(msgid);
		var messages = getMessages();
		if (Object.prototype.hasOwnProperty.call(messages, key) && messages[key] !== '') {
			return messages[key];
		}
		return key;
	}

	function translateFormat(msgid) {
		var str = translate(msgid);
		var args = Array.prototype.slice.call(arguments, 1);
		if (!args.length) {
			return str;
		}
		str = str.replace(/%(\d+)\$[sd]/g, function (_, n) {
			var i = parseInt(n, 10) - 1;
			return i >= 0 && i < args.length ? String(args[i]) : _;
		});
		var i = 0;
		str = str.replace(/%[sd]/g, function () {
			return i < args.length ? String(args[i++]) : '';
		});
		return str;
	}

	w.pilipost__ = translate;
	w.pili__ = translate;
	w.pilipostSprintf = translateFormat;
})(window);
