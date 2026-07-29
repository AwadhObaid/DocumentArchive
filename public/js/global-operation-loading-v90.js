/* DocumentArchive Global Operation Loading V90.0.0 */
(() => {
    'use strict';

    const root = document.getElementById('daGlobalLoading');
    const pageBar = document.querySelector('[data-da-page-progress]');

    if (!root || !pageBar) {
        return;
    }

    const messageNode = root.querySelector('[data-da-loading-message]');
    const progressNode = root.querySelector('[data-da-loading-progress]');
    const percentNode = root.querySelector('[data-da-loading-percent]');

    const state = {
        active: false,
        progress: 0,
        overlayTimer: null,
        progressTimer: null,
        finishTimer: null,
        token: 0,
        explicitProgress: false,
    };

    const clamp = (value) => Math.max(0, Math.min(100, Number(value) || 0));

    const setVisualProgress = (value, showPercent = false) => {
        state.progress = clamp(value);
        const scale = state.progress / 100;
        pageBar.style.transform = `scaleX(${scale})`;
        progressNode.style.transform = `scaleX(${scale})`;

        if (showPercent) {
            percentNode.hidden = false;
            percentNode.textContent = `${Math.round(state.progress)}%`;
        } else {
            percentNode.hidden = true;
        }
    };

    const clearTimers = () => {
        if (state.overlayTimer) {
            window.clearTimeout(state.overlayTimer);
            state.overlayTimer = null;
        }
        if (state.progressTimer) {
            window.clearInterval(state.progressTimer);
            state.progressTimer = null;
        }
        if (state.finishTimer) {
            window.clearTimeout(state.finishTimer);
            state.finishTimer = null;
        }
    };

    const hideImmediately = () => {
        clearTimers();
        state.active = false;
        state.explicitProgress = false;
        state.progress = 0;
        root.classList.remove('is-visible', 'is-error');
        root.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('da-loading-running');
        document.body?.classList.remove('da-loading-active');
        document.body?.removeAttribute('aria-busy');
        document.querySelectorAll('form[aria-busy="true"]').forEach((form) => {
            form.removeAttribute('aria-busy');
        });
        setVisualProgress(0, false);
    };

    const runSimulatedProgress = (token) => {
        state.progressTimer = window.setInterval(() => {
            if (!state.active || state.token !== token || state.explicitProgress) {
                return;
            }

            let increment = 0.45;
            if (state.progress < 35) increment = 3.4;
            else if (state.progress < 65) increment = 1.7;
            else if (state.progress < 82) increment = 0.8;

            setVisualProgress(Math.min(92, state.progress + increment), false);
        }, 260);
    };

    const start = (message = 'جارٍ تنفيذ العملية...', options = {}) => {
        clearTimers();
        state.token += 1;
        const token = state.token;
        state.active = true;
        state.explicitProgress = false;
        root.classList.remove('is-error');
        messageNode.textContent = String(message || 'جارٍ تنفيذ العملية...');
        root.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('da-loading-running');
        document.body?.classList.add('da-loading-active');
        document.body?.setAttribute('aria-busy', 'true');
        setVisualProgress(options.initialProgress ?? 8, false);

        const overlayDelay = Number.isFinite(Number(options.overlayDelay))
            ? Math.max(0, Number(options.overlayDelay))
            : 220;

        if (options.overlay !== false) {
            state.overlayTimer = window.setTimeout(() => {
                if (state.active && state.token === token) {
                    root.classList.add('is-visible');
                }
            }, overlayDelay);
        }

        runSimulatedProgress(token);
        return token;
    };

    const setProgress = (value, message = null) => {
        if (!state.active) {
            start(message || 'جارٍ تنفيذ العملية...', { overlayDelay: 0 });
        }
        state.explicitProgress = true;
        if (message) {
            messageNode.textContent = String(message);
        }
        root.classList.add('is-visible');
        setVisualProgress(value, true);
    };

    const finish = (message = null) => {
        if (!state.active) {
            hideImmediately();
            return;
        }

        if (message) {
            messageNode.textContent = String(message);
            root.classList.add('is-visible');
        }

        if (state.progressTimer) {
            window.clearInterval(state.progressTimer);
            state.progressTimer = null;
        }

        setVisualProgress(100, state.explicitProgress);
        state.finishTimer = window.setTimeout(hideImmediately, message ? 520 : 180);
    };

    const fail = (message = 'تعذر إكمال العملية.') => {
        clearTimers();
        state.active = true;
        root.classList.add('is-visible', 'is-error');
        root.setAttribute('aria-hidden', 'false');
        messageNode.textContent = String(message);
        document.documentElement.classList.add('da-loading-running');
        document.body?.classList.add('da-loading-active');
        setVisualProgress(100, false);
        state.finishTimer = window.setTimeout(hideImmediately, 1500);
    };

    const isSafeNavigationLink = (anchor, event) => {
        if (!anchor || event.defaultPrevented || event.button !== 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (anchor.matches('[download], [data-da-loading="off"], [data-no-loading]')) return false;
        if (anchor.closest('[data-no-global-loading]')) return false;
        if (anchor.target && anchor.target.toLowerCase() !== '_self') return false;

        const rawHref = anchor.getAttribute('href');
        if (!rawHref || rawHref.startsWith('#')) return false;
        if (/^(javascript:|mailto:|tel:)/i.test(rawHref)) return false;

        let url;
        try {
            url = new URL(anchor.href, window.location.href);
        } catch (_) {
            return false;
        }

        if (!/^https?:$/.test(url.protocol) || url.origin !== window.location.origin) return false;
        if (url.href === window.location.href) return false;
        return true;
    };

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest?.('a[href]');
        if (!isSafeNavigationLink(anchor, event)) return;

        const message = anchor.dataset.loadingText || 'جارٍ تحميل الصفحة...';
        start(message, { overlayDelay: 260 });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.matches('[data-da-loading="off"], [data-no-loading]')) return;
        if (form.closest('[data-no-global-loading]')) return;

        queueMicrotask(() => {
            if (event.defaultPrevented || !form.checkValidity()) return;

            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            const customMessage = submitter?.dataset.loadingText || form.dataset.loadingText;
            const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
            const hasFiles = fileInputs.some((input) => input.files && input.files.length > 0);
            const spoofedMethod = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
            const method = spoofedMethod || (form.method || 'GET').toUpperCase();

            let message = customMessage;
            if (!message && hasFiles) message = 'جارٍ رفع الملفات...';
            if (!message && method === 'DELETE') message = 'جارٍ حذف البيانات...';
            if (!message && method === 'GET') message = 'جارٍ تنفيذ البحث...';
            if (!message) message = 'جارٍ حفظ البيانات...';

            form.setAttribute('aria-busy', 'true');
            start(message, { overlayDelay: 120 });
        });
    }, true);

    window.addEventListener('beforeunload', () => {
        if (!state.active) {
            start('جارٍ تحميل الصفحة...', { overlayDelay: 0 });
        }
    });

    window.addEventListener('pageshow', hideImmediately);

    document.addEventListener('da:loading:start', (event) => {
        const detail = event.detail || {};
        start(detail.message, detail.options || {});
    });

    document.addEventListener('da:loading:progress', (event) => {
        const detail = event.detail || {};
        setProgress(detail.value, detail.message || null);
    });

    document.addEventListener('da:loading:finish', (event) => {
        finish(event.detail?.message || null);
    });

    document.addEventListener('da:loading:fail', (event) => {
        fail(event.detail?.message || undefined);
    });

    window.DocumentArchiveLoading = Object.freeze({
        start,
        setProgress,
        finish,
        fail,
        reset: hideImmediately,
        isActive: () => state.active,
    });

    hideImmediately();
})();
