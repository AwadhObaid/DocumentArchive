/* DocumentArchive File Bridge V94.2.1 - protocol loading compatibility */
(() => {
    'use strict';

    const fields = Array.from(document.querySelectorAll('[data-smart-attachment-field]'));
    if (!fields.length) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const pollers = new WeakMap();

    const resetGlobalLoading = () => {
        try {
            window.DocumentArchiveLoading?.reset?.();
        } catch (_) {
            // Global loading is optional and must never block File Bridge recovery.
        }
    };

    const releaseProtocolLaunchGuard = () => {
        window.__daSkipNextBeforeUnloadLoading = false;
        resetGlobalLoading();
    };

    const clearPoller = (field) => {
        const state = pollers.get(field);
        if (!state) {
            releaseProtocolLaunchGuard();
            return;
        }

        window.clearTimeout(state.timer);
        if (state.recoveryTimer) window.clearTimeout(state.recoveryTimer);
        if (state.focusHandler) window.removeEventListener('focus', state.focusHandler);
        if (state.visibilityHandler) document.removeEventListener('visibilitychange', state.visibilityHandler);
        pollers.delete(field);
        releaseProtocolLaunchGuard();
    };

    const postJson = async (url, body = {}) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
            throw new Error(validation || data.message || 'تعذر تنفيذ طلب File Bridge.');
        }

        return data;
    };

    const setSelectedMessage = (field, message, error = false) => {
        const selectedNode = field.querySelector('[data-smart-attachment-selected]');
        const clearButton = field.querySelector('[data-smart-attachment-clear]');

        if (!selectedNode) return;
        selectedNode.textContent = message;
        selectedNode.hidden = false;
        selectedNode.classList.toggle('is-error', error);
        if (clearButton) clearButton.hidden = false;
    };

    const clearOtherSelections = (field) => {
        const nativeInput = field.querySelector('[data-smart-native-file]');
        const smartToken = field.querySelector('[data-smart-attachment-token]');

        if (nativeInput) nativeInput.value = '';
        if (smartToken) smartToken.value = '';
    };

    const currentReference = (field) => {
        const selector = field.dataset.referenceElement;
        const node = selector ? document.querySelector(selector) : null;
        const value = node?.textContent?.trim() || field.dataset.referenceNumber || '';

        return value === '...' ? '' : value;
    };

    const currentYear = (field) => {
        const selector = field.dataset.yearElement;
        const node = selector ? document.querySelector(selector) : null;
        const value = Number(node?.textContent?.trim() || field.dataset.referenceYear || new Date().getFullYear());

        return Number.isFinite(value) ? value : new Date().getFullYear();
    };

    const cancelActiveRequest = async (field) => {
        const state = pollers.get(field);
        clearPoller(field);

        if (!state?.cancelUrl) return;
        try {
            await postJson(state.cancelUrl);
        } catch (_) {
            // Cancellation is best-effort. Expired requests are cleaned automatically.
        }
    };

    const pollStatus = async (field, state) => {
        if (pollers.get(field) !== state) return;

        resetGlobalLoading();

        try {
            const response = await fetch(state.statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'تعذر متابعة حالة File Bridge.');
            }

            if (data.status === 'ready') {
                const bridgeToken = field.querySelector('[data-file-bridge-token]');
                if (!bridgeToken || !data.selection_token) {
                    throw new Error('لم يصل رمز اختيار الملف من File Bridge.');
                }

                clearOtherSelections(field);
                bridgeToken.value = data.selection_token;
                bridgeToken.dispatchEvent(new Event('change', { bubbles: true }));

                const file = data.file || {};
                setSelectedMessage(
                    field,
                    `تم اختيار عبر File Bridge: ${file.name || 'ملف محلي'}${file.size_human ? ` — ${file.size_human}` : ''}`
                );
                clearPoller(field);
                return;
            }

            if (['failed', 'cancelled', 'expired', 'consumed'].includes(data.status)) {
                setSelectedMessage(field, data.message || 'لم تكتمل عملية File Bridge.', true);
                clearPoller(field);
                return;
            }

            setSelectedMessage(field, data.message || 'بانتظار اختيار الملف في برنامج File Bridge...');
        } catch (error) {
            state.failures += 1;
            if (state.failures >= 5) {
                setSelectedMessage(field, error.message || 'تعذر متابعة File Bridge.', true);
                clearPoller(field);
                return;
            }
        }

        state.timer = window.setTimeout(() => pollStatus(field, state), 1500);
    };

    const openBridge = async (field, button) => {
        clearPoller(field);
        button.disabled = true;
        setSelectedMessage(field, 'جارٍ إنشاء طلب File Bridge...');

        try {
            const data = await postJson(field.dataset.fileBridgeCreateUrl, {
                query: currentReference(field),
                year: currentYear(field),
                document_id: field.dataset.fileBridgeDocumentId || null,
            });

            const bridgeToken = field.querySelector('[data-file-bridge-token]');
            if (bridgeToken) bridgeToken.value = '';

            const state = {
                statusUrl: data.status_url,
                cancelUrl: data.cancel_url,
                timer: null,
                recoveryTimer: null,
                failures: 0,
                focusHandler: null,
                visibilityHandler: null,
            };

            state.focusHandler = () => resetGlobalLoading();
            state.visibilityHandler = () => {
                if (!document.hidden) resetGlobalLoading();
            };
            window.addEventListener('focus', state.focusHandler);
            document.addEventListener('visibilitychange', state.visibilityHandler);
            pollers.set(field, state);

            setSelectedMessage(field, 'تم استدعاء File Bridge. اختر الملف من النافذة المحلية ثم انتظر اكتمال الرفع.');

            window.__daSkipNextBeforeUnloadLoading = true;
            window.location.href = data.deep_link;
            state.recoveryTimer = window.setTimeout(releaseProtocolLaunchGuard, 700);
            state.timer = window.setTimeout(() => pollStatus(field, state), 1200);

            window.setTimeout(() => {
                if (pollers.get(field) !== state) return;
                const downloadUrl = field.dataset.fileBridgeDownloadUrl;
                const selectedNode = field.querySelector('[data-smart-attachment-selected]');
                if (!selectedNode || !downloadUrl) return;

                const installLink = document.createElement('a');
                installLink.href = downloadUrl;
                installLink.textContent = 'تحميل أداة File Bridge';
                installLink.className = 'da-file-bridge-install-link';
                selectedNode.append(' — ');
                selectedNode.appendChild(installLink);
            }, 3500);
        } catch (error) {
            releaseProtocolLaunchGuard();
            setSelectedMessage(field, error.message || 'تعذر تشغيل File Bridge.', true);
        } finally {
            button.disabled = false;
        }
    };

    fields.forEach((field) => {
        const button = field.querySelector('[data-file-bridge-open]');
        const bridgeToken = field.querySelector('[data-file-bridge-token]');
        const clearButton = field.querySelector('[data-smart-attachment-clear]');
        const nativeInput = field.querySelector('[data-smart-native-file]');
        const smartToken = field.querySelector('[data-smart-attachment-token]');

        button?.addEventListener('click', () => openBridge(field, button));

        clearButton?.addEventListener('click', () => {
            cancelActiveRequest(field);
            if (bridgeToken) bridgeToken.value = '';
        });

        nativeInput?.addEventListener('change', () => {
            if (!nativeInput.files?.length) return;
            cancelActiveRequest(field);
            if (bridgeToken) bridgeToken.value = '';
        });

        smartToken?.addEventListener('change', () => {
            if (!smartToken.value) return;
            cancelActiveRequest(field);
            if (bridgeToken) bridgeToken.value = '';
        });

        if (bridgeToken?.value) {
            setSelectedMessage(field, 'تم الاحتفاظ باختيار سابق من File Bridge.');
        }
    });
})();
