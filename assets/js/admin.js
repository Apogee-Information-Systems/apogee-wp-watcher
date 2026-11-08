(function () {
	'use strict';

	if (typeof apogeeWpWatcher === 'undefined') {
		return;
	}

	var flash = document.getElementById('apogee-wp-watcher-flash');
	var pairing = document.getElementById('apogee-wp-watcher-pairing');
	var userCodeEl = document.getElementById('apogee-wp-watcher-user-code');
	var verifyLink = document.getElementById('apogee-wp-watcher-verify-link');
	var pairingStatus = document.getElementById('apogee-wp-watcher-pairing-status');
	var connectBtn = document.getElementById('apogee-wp-watcher-connect');
	var disconnectBtn = document.getElementById('apogee-wp-watcher-disconnect');
	var pollTimer = null;

	function setFlash(message, ok) {
		if (!flash) {
			return;
		}
		flash.textContent = message || '';
		flash.classList.toggle('is-ok', !!ok);
		flash.classList.toggle('is-error', !ok && !!message);
	}

	function post(action, extra) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', apogeeWpWatcher.nonce);
		if (extra) {
			Object.keys(extra).forEach(function (k) {
				body.append(k, extra[k]);
			});
		}
		return fetch(apogeeWpWatcher.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (r) {
			return r.json();
		});
	}

	function stopPoll() {
		if (pollTimer) {
			clearTimeout(pollTimer);
			pollTimer = null;
		}
	}

	function schedulePoll(intervalSec) {
		stopPoll();
		var ms = Math.max(3, intervalSec || 5) * 1000;
		pollTimer = setTimeout(pollToken, ms);
	}

	function pollToken() {
		if (pairingStatus) {
			pairingStatus.textContent = apogeeWpWatcher.i18n.waiting;
		}
		post('apogee_wp_watcher_poll_token').then(function (json) {
			if (!json) {
				setFlash(apogeeWpWatcher.i18n.error, false);
				schedulePoll(5);
				return;
			}
			if (json.success && json.data && json.data.status === 'connected') {
				stopPoll();
				setFlash(json.data.message || apogeeWpWatcher.i18n.connected, true);
				window.location.reload();
				return;
			}
			if (json.success && json.data && (json.data.status === 'authorization_pending' || json.data.status === 'slow_down')) {
				schedulePoll(json.data.status === 'slow_down' ? 8 : 5);
				return;
			}
			var msg = (json.data && json.data.message) || apogeeWpWatcher.i18n.error;
			setFlash(msg, false);
			if (json.data && json.data.error === 'expired_token') {
				stopPoll();
				if (pairing) {
					pairing.hidden = true;
				}
				return;
			}
			schedulePoll(5);
		}).catch(function () {
			setFlash(apogeeWpWatcher.i18n.error, false);
			schedulePoll(5);
		});
	}

	if (connectBtn) {
		connectBtn.addEventListener('click', function () {
			setFlash(apogeeWpWatcher.i18n.connecting, true);
			connectBtn.disabled = true;
			post('apogee_wp_watcher_start_connect').then(function (json) {
				connectBtn.disabled = false;
				if (!json || !json.success) {
					setFlash((json && json.data && json.data.message) || apogeeWpWatcher.i18n.error, false);
					return;
				}
				var d = json.data;
				if (pairing) {
					pairing.hidden = false;
				}
				if (userCodeEl) {
					userCodeEl.textContent = d.user_code || '';
				}
				if (verifyLink) {
					verifyLink.href = d.verification_uri_complete || d.verification_uri || '#';
					window.open(verifyLink.href, '_blank', 'noopener');
				}
				setFlash('', true);
				schedulePoll(d.interval || 5);
			}).catch(function () {
				connectBtn.disabled = false;
				setFlash(apogeeWpWatcher.i18n.error, false);
			});
		});
	}

	if (disconnectBtn) {
		disconnectBtn.addEventListener('click', function () {
			if (!window.confirm(apogeeWpWatcher.i18n.confirmDisc)) {
				return;
			}
			setFlash(apogeeWpWatcher.i18n.disconnecting, true);
			disconnectBtn.disabled = true;
			post('apogee_wp_watcher_disconnect').then(function (json) {
				if (json && json.success) {
					window.location.reload();
					return;
				}
				disconnectBtn.disabled = false;
				setFlash((json && json.data && json.data.message) || apogeeWpWatcher.i18n.error, false);
			}).catch(function () {
				disconnectBtn.disabled = false;
				setFlash(apogeeWpWatcher.i18n.error, false);
			});
		});
	}
})();
