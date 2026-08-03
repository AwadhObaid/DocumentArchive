(() => {
    'use strict';

    const SELECTORS = {
        panel: '[data-pdf-bulk-indexing]',
        item: '[data-pdf-bulk-item]',
        pageCheckbox: '[data-pdf-select-page-checkbox]',
        selectPage: '[data-pdf-select-page]',
        selectFiltered: '[data-pdf-select-filtered]',
        clearSelection: '[data-pdf-clear-selection]',
        runSelected: '[data-pdf-run-selected]',
        cancelRun: '[data-pdf-cancel-run]',
        reloadResults: '[data-pdf-reload-results]',
        enableOcr: '[data-pdf-enable-ocr]',
        selectedCount: '[data-pdf-selected-count]',
        progressWrap: '[data-pdf-progress-wrap]',
        progressText: '[data-pdf-progress-text]',
        progressPercent: '[data-pdf-progress-percent]',
        progressBar: '[data-pdf-progress-bar]',
        message: '[data-pdf-bulk-message]',
    };

    const makeKey = (sourceType, attachmentId) => `${sourceType}:${attachmentId}`;

    const readJsonSafely = async (response) => {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return response.json();
        }

        const text = await response.text();
        return text ? { message: text } : {};
    };

    const init = () => {
        const panel = document.querySelector(SELECTORS.panel);

        if (!panel) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const pageCheckbox = document.querySelector(SELECTORS.pageCheckbox);
        const itemCheckboxes = Array.from(document.querySelectorAll(SELECTORS.item));
        const selectPageButton = panel.querySelector(SELECTORS.selectPage);
        const selectFilteredButton = panel.querySelector(SELECTORS.selectFiltered);
        const clearButton = panel.querySelector(SELECTORS.clearSelection);
        const runButton = panel.querySelector(SELECTORS.runSelected);
        const cancelButton = panel.querySelector(SELECTORS.cancelRun);
        const reloadButton = panel.querySelector(SELECTORS.reloadResults);
        const ocrCheckbox = panel.querySelector(SELECTORS.enableOcr);
        const selectedCount = panel.querySelector(SELECTORS.selectedCount);
        const progressWrap = panel.querySelector(SELECTORS.progressWrap);
        const progressText = panel.querySelector(SELECTORS.progressText);
        const progressPercent = panel.querySelector(SELECTORS.progressPercent);
        const progressBar = panel.querySelector(SELECTORS.progressBar);
        const message = panel.querySelector(SELECTORS.message);

        const selected = new Map();
        let running = false;
        let cancelRequested = false;

        const itemFromCheckbox = (checkbox) => ({
            sourceType: checkbox.dataset.sourceType || '',
            attachmentId: Number.parseInt(checkbox.dataset.attachmentId || '0', 10),
            indexUrl: checkbox.dataset.indexUrl || '',
            status: checkbox.dataset.status || 'unindexed',
            label: checkbox.dataset.label || `مرفق ${checkbox.dataset.attachmentId || ''}`,
        });

        const setMessage = (text, tone = 'info') => {
            if (!message) {
                return;
            }

            message.textContent = text || '';
            message.dataset.tone = tone;
        };

        const setRowState = (item, text, tone = 'info') => {
            const row = document.querySelector(`[data-pdf-index-row="${CSS.escape(makeKey(item.sourceType, item.attachmentId))}"]`);
            const state = row?.querySelector('[data-pdf-row-state]');

            if (!state) {
                return;
            }

            state.textContent = text || '';
            state.dataset.tone = tone;
        };

        const syncVisibleCheckboxes = () => {
            itemCheckboxes.forEach((checkbox) => {
                const key = makeKey(checkbox.dataset.sourceType || '', checkbox.dataset.attachmentId || '');
                checkbox.checked = selected.has(key);
                checkbox.disabled = running;
            });
        };

        const updateMasterCheckbox = () => {
            if (!pageCheckbox) {
                return;
            }

            const selectable = itemCheckboxes.filter((checkbox) => !checkbox.disabled || running);
            const checkedCount = selectable.filter((checkbox) => checkbox.checked).length;

            pageCheckbox.checked = selectable.length > 0 && checkedCount === selectable.length;
            pageCheckbox.indeterminate = checkedCount > 0 && checkedCount < selectable.length;
            pageCheckbox.disabled = running || selectable.length === 0;
        };

        const updateControls = () => {
            const count = selected.size;

            if (selectedCount) {
                selectedCount.textContent = String(count);
            }

            if (runButton) {
                runButton.disabled = running || count === 0;
            }

            if (clearButton) {
                clearButton.disabled = running || count === 0;
            }

            if (selectPageButton) {
                selectPageButton.disabled = running || itemCheckboxes.length === 0;
            }

            if (selectFilteredButton) {
                selectFilteredButton.disabled = running;
            }

            if (ocrCheckbox) {
                ocrCheckbox.disabled = running;
            }

            syncVisibleCheckboxes();
            updateMasterCheckbox();
        };

        const addCheckboxItem = (checkbox) => {
            const item = itemFromCheckbox(checkbox);

            if (!item.sourceType || !item.attachmentId || !item.indexUrl) {
                return;
            }

            selected.set(makeKey(item.sourceType, item.attachmentId), item);
        };

        const removeCheckboxItem = (checkbox) => {
            selected.delete(makeKey(checkbox.dataset.sourceType || '', checkbox.dataset.attachmentId || ''));
        };

        const setProgress = (completed, total, label = '') => {
            const safeTotal = Math.max(1, total);
            const percent = Math.min(100, Math.round((completed / safeTotal) * 100));

            if (progressWrap) {
                progressWrap.hidden = false;
            }

            if (progressText) {
                progressText.textContent = label || `تمت معالجة ${completed} من ${total}`;
            }

            if (progressPercent) {
                progressPercent.textContent = `${percent}%`;
            }

            if (progressBar) {
                progressBar.style.width = `${percent}%`;
            }
        };

        const selectCurrentPage = () => {
            itemCheckboxes.forEach((checkbox) => {
                addCheckboxItem(checkbox);
            });

            updateControls();
            setMessage(`تم تحديد ${itemCheckboxes.length} مرفقًا قابلًا للمعالجة من الصفحة الحالية.`, 'success');
        };

        const clearSelection = () => {
            selected.clear();
            updateControls();
            setMessage('تم إلغاء تحديد المرفقات.', 'info');
        };

        const selectFiltered = async () => {
            const endpoint = panel.dataset.selectionUrl || '';

            if (!endpoint) {
                setMessage('تعذر تحديد مسار جلب النتائج المطابقة.', 'error');
                return;
            }

            selectFilteredButton.disabled = true;
            setMessage('جارٍ جمع المرفقات القابلة للمعالجة من النتائج المطابقة...', 'info');

            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('q', panel.dataset.filterQ || '');
            url.searchParams.set('source', panel.dataset.filterSource || 'all');
            url.searchParams.set('status', panel.dataset.filterStatus || 'all');
            url.searchParams.set('limit', panel.dataset.selectionLimit || '500');

            try {
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await readJsonSafely(response);

                if (!response.ok) {
                    throw new Error(payload.message || `تعذر جلب النتائج (${response.status}).`);
                }

                const items = Array.isArray(payload.items) ? payload.items : [];

                selected.clear();
                items.forEach((rawItem) => {
                    const sourceType = String(rawItem.source_type || '');
                    const attachmentId = Number.parseInt(String(rawItem.attachment_id || '0'), 10);
                    const indexUrl = String(rawItem.index_url || '');

                    if (!sourceType || !attachmentId || !indexUrl) {
                        return;
                    }

                    selected.set(makeKey(sourceType, attachmentId), {
                        sourceType,
                        attachmentId,
                        indexUrl,
                        status: String(rawItem.status || 'unindexed'),
                        label: String(rawItem.label || `مرفق ${attachmentId}`),
                    });
                });

                updateControls();

                if (payload.limited) {
                    setMessage(
                        `تم تحديد أول ${payload.returned} مرفق من أصل ${payload.total}. الحد الأعلى للعملية الواحدة هو ${payload.limit} مرفق.`,
                        'warning'
                    );
                } else {
                    setMessage(`تم تحديد ${selected.size} مرفقًا قابلًا للمعالجة من النتائج المطابقة.`, 'success');
                }
            } catch (error) {
                setMessage(error instanceof Error ? error.message : 'تعذر جلب المرفقات المطابقة.', 'error');
            } finally {
                if (!running) {
                    selectFilteredButton.disabled = false;
                }
            }
        };

        const classifyResult = (status, summary) => {
            switch (status) {
                case 'indexed':
                    summary.indexed += 1;
                    return ['تمت الفهرسة بنجاح', 'success'];
                case 'needs_ocr':
                    summary.needsOcr += 1;
                    return ['يحتاج إلى التعرف الضوئي', 'warning'];
                case 'missing':
                    summary.missing += 1;
                    return ['الملف غير موجود', 'error'];
                case 'skipped':
                    summary.skipped += 1;
                    return ['تم تجاوز الملف', 'warning'];
                case 'failed':
                default:
                    summary.failed += 1;
                    return ['فشلت الفهرسة', 'error'];
            }
        };

        const processOne = async (item, enableOcr) => {
            setRowState(item, 'جارٍ الفهرسة...', 'processing');

            const response = await fetch(item.indexUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    enable_ocr: enableOcr ? 1 : 0,
                    force: item.status === 'unindexed' ? 0 : 1,
                }),
            });

            const payload = await readJsonSafely(response);

            if (!response.ok || payload.ok === false) {
                const validationMessage = payload.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : '';
                throw new Error(validationMessage || payload.message || `فشل الطلب (${response.status}).`);
            }

            return payload;
        };

        const runSelected = async () => {
            if (running || selected.size === 0) {
                return;
            }

            const enableOcr = Boolean(ocrCheckbox?.checked);
            const count = selected.size;
            const confirmation = enableOcr
                ? `سيتم فهرسة ${count} مرفقًا مع تشغيل التعرف الضوئي، وقد تستغرق العملية وقتًا أطول. هل تريد المتابعة؟`
                : `سيتم فهرسة ${count} مرفقًا بالتتابع. هل تريد المتابعة؟`;

            if (!window.confirm(confirmation)) {
                return;
            }

            running = true;
            cancelRequested = false;
            const items = Array.from(selected.values());
            const summary = {
                processed: 0,
                indexed: 0,
                needsOcr: 0,
                failed: 0,
                missing: 0,
                skipped: 0,
                requestErrors: 0,
            };

            if (cancelButton) {
                cancelButton.hidden = false;
                cancelButton.disabled = false;
            }

            if (reloadButton) {
                reloadButton.hidden = true;
            }

            updateControls();
            setProgress(0, items.length, `بدء فهرسة ${items.length} مرفقًا...`);
            setMessage('بدأت الفهرسة الجماعية. لا تغلق الصفحة حتى انتهاء العملية أو طلب الإيقاف.', 'info');

            for (let index = 0; index < items.length; index += 1) {
                if (cancelRequested) {
                    break;
                }

                const item = items[index];
                setProgress(summary.processed, items.length, `جارٍ معالجة ${index + 1} من ${items.length}: ${item.label}`);

                try {
                    const payload = await processOne(item, enableOcr);
                    const [stateText, tone] = classifyResult(String(payload.status || 'failed'), summary);
                    setRowState(item, stateText, tone);
                } catch (error) {
                    summary.requestErrors += 1;
                    setRowState(item, error instanceof Error ? error.message : 'تعذر تنفيذ الطلب.', 'error');
                }

                summary.processed += 1;
                setProgress(summary.processed, items.length);
            }

            running = false;

            if (cancelButton) {
                cancelButton.hidden = true;
                cancelButton.disabled = false;
            }

            if (reloadButton) {
                reloadButton.hidden = false;
            }

            updateControls();

            const stoppedText = cancelRequested ? ' تم إيقاف العملية بعد الملف الحالي.' : '';
            const tone = summary.failed + summary.missing + summary.requestErrors > 0 ? 'warning' : 'success';
            setMessage(
                `انتهت المعالجة: ${summary.processed}؛ مفهرس: ${summary.indexed}؛ يحتاج OCR: ${summary.needsOcr}؛ مفقود: ${summary.missing}؛ فشل: ${summary.failed + summary.requestErrors}؛ متجاوز: ${summary.skipped}.${stoppedText}`,
                tone
            );
        };

        itemCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    addCheckboxItem(checkbox);
                } else {
                    removeCheckboxItem(checkbox);
                }

                updateControls();
            });
        });

        pageCheckbox?.addEventListener('change', () => {
            if (pageCheckbox.checked) {
                selectCurrentPage();
            } else {
                itemCheckboxes.forEach(removeCheckboxItem);
                updateControls();
                setMessage('تم إلغاء تحديد مرفقات الصفحة الحالية.', 'info');
            }
        });

        selectPageButton?.addEventListener('click', selectCurrentPage);
        selectFilteredButton?.addEventListener('click', selectFiltered);
        clearButton?.addEventListener('click', clearSelection);
        runButton?.addEventListener('click', runSelected);

        cancelButton?.addEventListener('click', () => {
            cancelRequested = true;
            cancelButton.disabled = true;
            setMessage('سيتم إيقاف الفهرسة بعد انتهاء معالجة الملف الحالي.', 'warning');
        });

        reloadButton?.addEventListener('click', () => {
            window.location.reload();
        });

        updateControls();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
