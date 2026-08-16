/* DocumentArchive Unified Non-Blocking Operation Feedback V95.5 */
(() => {
    'use strict';

    const root = document.getElementById('daGlobalLoading');
    const pageBar = document.querySelector('[data-da-page-progress]');

    if (!root || !pageBar) {
        return;
    }

    const messageNode = root.querySelector('[data-da-loading-message]');
    const hintNode = root.querySelector('.da-global-loading__hint');
    const progressNode = root.querySelector('[data-da-loading-progress]');
    const percentNode = root.querySelector('[data-da-loading-percent]');

    const state = {
        active: false,
        progress: 0,
        noticeTimer: null,
        progressTimer: null,
        finishTimer: null,
        token: 0,
        explicitProgress: false,
    };

    const DEFAULT_MESSAGE = 'جارٍ تنفيذ العملية...';
    const DEFAULT_HINT = 'يمكنك متابعة مشاهدة الصفحة أثناء تنفيذ العملية.';

    const clamp = (value) => Math.max(0, Math.min(100, Number(value) || 0));

    const setVisualProgress = (value, showPercent = false) => {
        state.progress = clamp(value);
        const scale = state.progress / 100;

        pageBar.style.transform = `scaleX(${scale})`;

        if (progressNode) {
            progressNode.style.transform = `scaleX(${scale})`;
        }

        if (percentNode) {
            if (showPercent) {
                percentNode.hidden = false;
                percentNode.textContent = `${Math.round(state.progress)}%`;
            } else {
                percentNode.hidden = true;
            }
        }
    };

    const clearTimers = () => {
        if (state.noticeTimer) {
            window.clearTimeout(state.noticeTimer);
            state.noticeTimer = null;
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

    const restoreBusyControls = () => {
        document.querySelectorAll('[data-da-operation-busy="1"]').forEach((control) => {
            control.classList.remove('da-action-busy');
            control.removeAttribute('aria-busy');
            control.removeAttribute('aria-disabled');

            if (control instanceof HTMLButtonElement && control.dataset.daOriginalHtml !== undefined) {
                control.innerHTML = control.dataset.daOriginalHtml;
                delete control.dataset.daOriginalHtml;
            }

            if (control instanceof HTMLInputElement && control.dataset.daOriginalValue !== undefined) {
                control.value = control.dataset.daOriginalValue;
                delete control.dataset.daOriginalValue;
            }

            control.removeAttribute('data-da-operation-busy');
        });

        document.querySelectorAll('form[data-da-submitting="1"]').forEach((form) => {
            delete form.dataset.daSubmitting;
            form.removeAttribute('aria-busy');
        });
    };

    const markControlBusy = (control, message = null) => {
        if (!(control instanceof HTMLElement)) {
            return;
        }

        if (control.dataset.daOperationBusy === '1') {
            return;
        }

        control.dataset.daOperationBusy = '1';
        control.classList.add('da-action-busy');
        control.setAttribute('aria-busy', 'true');
        control.setAttribute('aria-disabled', 'true');

        if (control instanceof HTMLButtonElement) {
            control.dataset.daOriginalHtml = control.innerHTML;

            if (message) {
                control.textContent = message;
            }
        } else if (control instanceof HTMLInputElement) {
            control.dataset.daOriginalValue = control.value;

            if (message) {
                control.value = message;
            }
        }
    };

    const hideNotice = () => {
        root.classList.remove('is-visible', 'is-error', 'is-success');
        root.setAttribute('aria-hidden', 'true');
    };

    const showNotice = () => {
        root.classList.add('is-visible');
        root.setAttribute('aria-hidden', 'false');
    };

    const hideImmediately = () => {
        clearTimers();
        state.active = false;
        state.explicitProgress = false;
        state.progress = 0;

        hideNotice();
        document.documentElement.classList.remove('da-loading-running');
        setVisualProgress(0, false);
        restoreBusyControls();
    };

    const runSimulatedProgress = (token) => {
        state.progressTimer = window.setInterval(() => {
            if (!state.active || state.token !== token || state.explicitProgress) {
                return;
            }

            let increment = 0.35;

            if (state.progress < 30) increment = 3.2;
            else if (state.progress < 60) increment = 1.5;
            else if (state.progress < 80) increment = 0.7;

            setVisualProgress(Math.min(91, state.progress + increment), false);
        }, 280);
    };

    const start = (message = DEFAULT_MESSAGE, options = {}) => {
        clearTimers();

        state.token += 1;
        const token = state.token;
        state.active = true;
        state.explicitProgress = false;

        root.classList.remove('is-error', 'is-success');
        messageNode.textContent = String(message || DEFAULT_MESSAGE);

        if (hintNode) {
            hintNode.textContent = String(options.hint || DEFAULT_HINT);
        }

        document.documentElement.classList.add('da-loading-running');
        setVisualProgress(options.initialProgress ?? 8, false);

        // Backward compatibility: old callers used overlay:false. In V95.5
        // this means "top progress only", because the full-screen overlay no
        // longer exists.
        const noticeEnabled = options.notice !== false && options.overlay !== false;
        const noticeDelay = Number.isFinite(Number(options.noticeDelay ?? options.overlayDelay))
            ? Math.max(0, Number(options.noticeDelay ?? options.overlayDelay))
            : 140;

        if (noticeEnabled) {
            state.noticeTimer = window.setTimeout(() => {
                if (state.active && state.token === token) {
                    showNotice();
                }
            }, noticeDelay);
        } else {
            hideNotice();
        }

        runSimulatedProgress(token);
        return token;
    };

    const setProgress = (value, message = null) => {
        if (!state.active) {
            start(message || DEFAULT_MESSAGE, { noticeDelay: 0 });
        }

        state.explicitProgress = true;

        if (message) {
            messageNode.textContent = String(message);
        }

        showNotice();
        setVisualProgress(value, true);
    };

    const finish = (message = null) => {
        if (!state.active) {
            hideImmediately();
            return;
        }

        if (state.progressTimer) {
            window.clearInterval(state.progressTimer);
            state.progressTimer = null;
        }

        setVisualProgress(100, state.explicitProgress);

        if (message) {
            messageNode.textContent = String(message);
            root.classList.remove('is-error');
            root.classList.add('is-success');
            showNotice();
        }

        state.finishTimer = window.setTimeout(
            hideImmediately,
            message ? 1800 : 220
        );
    };

    const fail = (message = 'تعذر إكمال العملية.') => {
        clearTimers();
        state.active = true;
        root.classList.remove('is-success');
        root.classList.add('is-error');
        messageNode.textContent = String(message);

        if (hintNode) {
            hintNode.textContent = 'راجع الرسالة الظاهرة في الصفحة أو أعد المحاولة.';
        }

        document.documentElement.classList.add('da-loading-running');
        setVisualProgress(100, false);
        showNotice();

        state.finishTimer = window.setTimeout(hideImmediately, 4200);
    };

    const resolveAnchorUrl = (anchor) => {
        if (!anchor) return null;

        try {
            return new URL(anchor.href, window.location.href);
        } catch (_) {
            return null;
        }
    };

    const isDownloadLikeLink = (anchor, event = null) => {
        if (!anchor) return false;
        if (event && (event.defaultPrevented || event.button !== 0)) return false;
        if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) return false;

        if (anchor.matches('[download], [data-da-download], [data-file-download]')) {
            return true;
        }

        const url = resolveAnchorUrl(anchor);

        if (!url || !/^https?:$/.test(url.protocol) || url.origin !== window.location.origin) {
            return false;
        }

        const pathname = decodeURIComponent(url.pathname || '').replace(/\/+$/, '');

        return /(?:^|\/)(?:download|downloads)(?:\/|$)/i.test(pathname)
            || /\/(?:export|download)[-_](?:pdf|word|docx|excel|xlsx|csv)(?:\/|$)/i.test(pathname)
            || /\/(?:pdf|word|docx|excel|xlsx|csv)$/i.test(pathname);
    };

    const releaseDownloadNavigationGuard = () => {
        window.setTimeout(() => {
            window.__daSkipNextBeforeUnloadLoading = false;
            hideImmediately();
        }, 1400);
    };

    const isSafeNavigationLink = (anchor, event) => {
        if (!anchor || event.defaultPrevented || event.button !== 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (anchor.matches('[download], [data-da-download], [data-file-download], [data-da-loading="off"], [data-no-loading]')) return false;
        if (anchor.closest('[data-no-global-loading]')) return false;
        if (anchor.target && anchor.target.toLowerCase() !== '_self') return false;

        const rawHref = anchor.getAttribute('href');

        if (!rawHref || rawHref.startsWith('#')) return false;
        if (/^(javascript:|mailto:|tel:)/i.test(rawHref)) return false;

        const url = resolveAnchorUrl(anchor);

        if (!url) return false;
        if (!/^https?:$/.test(url.protocol) || url.origin !== window.location.origin) return false;
        if (url.href === window.location.href) return false;
        if (isDownloadLikeLink(anchor)) return false;

        return true;
    };

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest?.('a[href]');

        if (isDownloadLikeLink(anchor, event)) {
            window.__daSkipNextBeforeUnloadLoading = true;
            hideImmediately();
            releaseDownloadNavigationGuard();
            return;
        }

        if (!isSafeNavigationLink(anchor, event)) {
            return;
        }

        // Navigation gets only the thin top progress bar. There is no toast
        // and no screen blocking for ordinary page changes.
        start(anchor.dataset.loadingText || 'جارٍ تحميل الصفحة...', {
            notice: false,
            initialProgress: 14,
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;
        if (form.matches('[data-da-loading="off"], [data-no-loading]')) return;
        if (form.closest('[data-no-global-loading]')) return;

        // Keep the asynchronous confirmation dialog above all operation
        // feedback. The real submit will be emitted again after confirmation.
        if (form.matches('[data-confirm]') && form.dataset.confirmAccepted !== '1') {
            return;
        }

        // Prevent a second submit after the first accepted submit has entered
        // the busy state.
        if (form.dataset.daSubmitting === '1') {
            event.preventDefault();
            return;
        }

        const submitter = event.submitter instanceof HTMLElement
            ? event.submitter
            : form.querySelector('button[type="submit"], input[type="submit"]');

        queueMicrotask(() => {
            if (event.defaultPrevented || !form.checkValidity()) {
                return;
            }

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

            form.dataset.daSubmitting = '1';
            form.setAttribute('aria-busy', 'true');

            // Only the initiating control becomes visually busy. The rest of
            // the page remains readable and usable.
            markControlBusy(submitter, message);

            start(message, {
                notice: true,
                noticeDelay: 90,
                initialProgress: 10,
                hint: method === 'GET'
                    ? 'يتم تنفيذ الطلب دون حجب الصفحة.'
                    : 'يمكنك متابعة مشاهدة الصفحة أثناء تنفيذ العملية.',
            });
        });
    }, true);

    window.addEventListener('beforeunload', () => {
        if (window.__daSkipNextBeforeUnloadLoading === true) {
            window.__daSkipNextBeforeUnloadLoading = false;
            return;
        }

        if (!state.active) {
            // Do not create any modal/overlay on unload. A thin progress bar
            // is enough feedback until the next page is displayed.
            start('جارٍ تحميل الصفحة...', {
                notice: false,
                initialProgress: 35,
            });
        }
    });

    window.addEventListener('pageshow', hideImmediately);

    window.addEventListener('focus', () => {
        if (window.__daSkipNextBeforeUnloadLoading === true) {
            hideImmediately();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && window.__daSkipNextBeforeUnloadLoading === true) {
            hideImmediately();
        }
    });

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

    // Backward-compatible API: existing File Bridge / OCR / indexing code can
    // continue to call the same methods, but feedback is now non-blocking.
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
