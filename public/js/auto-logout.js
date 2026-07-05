(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const body = document.body;
        if (!body || body.dataset.autoLogoutEnabled !== '1') {
            return;
        }

        const timeoutSeconds = Math.max(60, parseInt(body.dataset.autoLogoutTimeout || '1800', 10));
        const warningSeconds = Math.min(
            Math.max(10, parseInt(body.dataset.autoLogoutWarning || '60', 10)),
            Math.max(10, timeoutSeconds - 5)
        );
        const pingUrl = body.dataset.autoLogoutPingUrl || '/session/activity';
        const loginUrl = body.dataset.autoLogoutLoginUrl || '/login';
        const logoutUrl = body.dataset.autoLogoutLogoutUrl || '/logout';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const modal = document.getElementById('autoLogoutModal');
        const countdown = modal ? modal.querySelector('[data-auto-logout-countdown]') : null;
        const stayButton = modal ? modal.querySelector('[data-auto-logout-stay]') : null;
        const logoutNowButton = modal ? modal.querySelector('[data-auto-logout-now]') : null;

        let lastActivity = Date.now();
        let lastPing = 0;
        let warned = false;
        let loggingOut = false;
        let intervalId = null;

        function showWarning(remainingSeconds) {
            if (!modal) return;
            warned = true;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            if (countdown) {
                countdown.textContent = String(Math.max(0, Math.ceil(remainingSeconds)));
            }
        }

        function hideWarning() {
            if (!modal) return;
            warned = false;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function serverPing(force) {
            const now = Date.now();
            if (!force && (now - lastPing) < 30000) {
                return;
            }
            lastPing = now;

            fetch(pingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ active_at: new Date().toISOString() })
            }).then(function (response) {
                if (response.status === 401 || response.status === 419) {
                    window.location.href = loginUrl;
                }
            }).catch(function () {
                // لا نزعج المستخدم عند انقطاع الشبكة المؤقت.
            });
        }

        function submitLogout() {
            if (loggingOut) return;
            loggingOut = true;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = logoutUrl;
            form.style.display = 'none';

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;
            form.appendChild(tokenInput);

            document.body.appendChild(form);
            form.submit();
        }

        function markActivity() {
            if (loggingOut) return;
            lastActivity = Date.now();
            if (warned) {
                hideWarning();
            }
            serverPing(false);
        }

        function tick() {
            const idleSeconds = (Date.now() - lastActivity) / 1000;
            const remainingSeconds = timeoutSeconds - idleSeconds;

            if (remainingSeconds <= 0) {
                submitLogout();
                return;
            }

            if (remainingSeconds <= warningSeconds) {
                showWarning(remainingSeconds);
            }

            if (countdown && warned) {
                countdown.textContent = String(Math.max(0, Math.ceil(remainingSeconds)));
            }
        }

        ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (eventName) {
            window.addEventListener(eventName, markActivity, { passive: true });
        });

        if (stayButton) {
            stayButton.addEventListener('click', function () {
                markActivity();
                serverPing(true);
            });
        }

        if (logoutNowButton) {
            logoutNowButton.addEventListener('click', submitLogout);
        }

        serverPing(true);
        intervalId = window.setInterval(tick, 1000);

        window.addEventListener('beforeunload', function () {
            if (intervalId) {
                window.clearInterval(intervalId);
            }
        });
    });
})();
