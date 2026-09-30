(function () {
    'use strict';

    let deferredPrompt = null;

    function setupInstallPrompt() {
        const installButton = document.querySelector('[data-lite-install]');
        if (!installButton) return;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;
            installButton.hidden = false;
        });

        installButton.addEventListener('click', async function () {
            if (!deferredPrompt) return;

            deferredPrompt.prompt();
            try {
                await deferredPrompt.userChoice;
            } catch (e) {
                // تجاهل إلغاء المستخدم.
            }

            deferredPrompt = null;
            installButton.hidden = true;
        });

        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            installButton.hidden = true;
        });
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;

        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw-lite.js', { scope: '/lite/' })
                .catch(function () {
                    // عدم توفر Service Worker لا يمنع تشغيل نسخة Lite.
                });
        });
    }

    document.addEventListener('DOMContentLoaded', setupInstallPrompt);
    registerServiceWorker();
})();
