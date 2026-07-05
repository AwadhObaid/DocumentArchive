(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-lite-notifications]');
        if (!root) return;

        const pollUrl = root.getAttribute('data-poll-url');
        const intervalSeconds = Math.max(10, parseInt(root.getAttribute('data-poll-seconds') || '30', 10));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const countEls = document.querySelectorAll('[data-lite-unread-count]');
        const toast = document.getElementById('liteNotificationToast');
        const toastTitle = toast ? toast.querySelector('[data-lite-toast-title]') : null;
        const toastBody = toast ? toast.querySelector('[data-lite-toast-body]') : null;
        let lastUnread = parseInt(root.getAttribute('data-initial-unread') || '0', 10);
        let firstPoll = true;
        let toastTimer = null;

        function updateCounts(count) {
            countEls.forEach(function (el) {
                el.textContent = String(count);
                el.style.display = count > 0 ? '' : 'none';
            });
        }

        function beep() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = new AudioContext();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 740;
                gain.gain.value = 0.035;
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start();
                setTimeout(function () {
                    oscillator.stop();
                    ctx.close();
                }, 130);
            } catch (e) {}
        }

        function showToast(item) {
            if (!toast || !item) return;
            if (toastTitle) toastTitle.textContent = item.title || 'إشعار جديد';
            if (toastBody) toastBody.textContent = item.body || 'وصل إشعار جديد إلى النظام.';
            toast.classList.add('is-visible');
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, 6500);
        }

        function poll() {
            if (!pollUrl) return;
            fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (response.status === 401 || response.status === 419) {
                    window.location.reload();
                    return null;
                }
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok) return;
                const unread = parseInt(payload.unread_count || 0, 10);
                updateCounts(unread);
                if (!firstPoll && unread > lastUnread) {
                    showToast(payload.latest && payload.latest.length ? payload.latest[0] : null);
                    beep();
                }
                firstPoll = false;
                lastUnread = unread;
            }).catch(function () {});
        }

        updateCounts(lastUnread);
        setInterval(poll, intervalSeconds * 1000);
    });
})();
