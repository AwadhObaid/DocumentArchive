/* DocumentArchive Smart Attachment Browser V94.1 */
(() => {
    'use strict';

    const fields = Array.from(document.querySelectorAll('[data-smart-attachment-field]'));
    if (!fields.length) return;

    let activeField = null;
    let selectedItem = null;
    let dialog = null;
    let sourceSelect = null;
    let queryInput = null;
    let yearInput = null;
    let recursiveInput = null;
    let searchButton = null;
    let resultsNode = null;
    let statusNode = null;
    let chooseButton = null;
    let settingsLink = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const buildDialog = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'da-smart-browser-dialog';
        dialog.innerHTML = `
            <div class="da-smart-browser-shell" dir="rtl">
                <header class="da-smart-browser-head">
                    <div>
                        <h2>البحث الذكي عن ملف الكتاب</h2>
                        <p>ابحث في مسارات السيرفر أو الشبكة المحفوظة في الإعدادات، ثم عاين الملف واختره.</p>
                    </div>
                    <button type="button" class="btn btn-light da-smart-browser-close" data-smart-dialog-close>✕</button>
                </header>

                <div class="da-smart-browser-body">
                    <div class="da-smart-browser-filters">
                        <div class="da-smart-browser-field">
                            <label for="daSmartBrowserSource">مصدر البحث</label>
                            <select id="daSmartBrowserSource"></select>
                        </div>

                        <div class="da-smart-browser-field">
                            <label for="daSmartBrowserQuery">رقم الكتاب أو اسم الملف</label>
                            <input id="daSmartBrowserQuery" type="search" maxlength="255" autocomplete="off">
                        </div>

                        <div class="da-smart-browser-field">
                            <label for="daSmartBrowserYear">السنة</label>
                            <input id="daSmartBrowserYear" type="number" min="2000" max="2200">
                        </div>

                        <button type="button" class="btn btn-primary" id="daSmartBrowserSearch">بحث</button>
                    </div>

                    <label class="da-smart-browser-recursive">
                        <input type="checkbox" id="daSmartBrowserRecursive">
                        البحث داخل المجلدات الفرعية
                    </label>

                    <div class="da-smart-browser-status" id="daSmartBrowserStatus">
                        جارٍ تحميل مصادر البحث...
                    </div>

                    <div class="da-smart-browser-results" id="daSmartBrowserResults"></div>
                </div>

                <footer class="da-smart-browser-foot">
                    <div class="da-smart-browser-foot-note">
                        للملفات الموجودة محلياً على جهاز المستخدم أغلق هذه النافذة واستخدم «اختيار من الجهاز».
                        <a href="#" id="daSmartBrowserSettingsLink">إعدادات المسارات</a>
                    </div>

                    <div class="da-smart-browser-foot-actions">
                        <button type="button" class="btn btn-light" data-smart-dialog-close>إلغاء</button>
                        <button type="button" class="btn btn-success" id="daSmartBrowserChoose" disabled>
                            اختيار الملف
                        </button>
                    </div>
                </footer>
            </div>
        `;

        document.body.appendChild(dialog);

        sourceSelect = dialog.querySelector('#daSmartBrowserSource');
        queryInput = dialog.querySelector('#daSmartBrowserQuery');
        yearInput = dialog.querySelector('#daSmartBrowserYear');
        recursiveInput = dialog.querySelector('#daSmartBrowserRecursive');
        searchButton = dialog.querySelector('#daSmartBrowserSearch');
        resultsNode = dialog.querySelector('#daSmartBrowserResults');
        statusNode = dialog.querySelector('#daSmartBrowserStatus');
        chooseButton = dialog.querySelector('#daSmartBrowserChoose');
        settingsLink = dialog.querySelector('#daSmartBrowserSettingsLink');

        dialog.querySelectorAll('[data-smart-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });

        searchButton.addEventListener('click', runSearch);
        queryInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                runSearch();
            }
        });

        chooseButton.addEventListener('click', chooseSelectedItem);

        settingsLink.addEventListener('click', (event) => {
            event.preventDefault();
            const url = activeField?.dataset.settingsUrl;
            if (url) window.open(url, '_blank', 'noopener');
        });
    };

    const setStatus = (message, type = '') => {
        statusNode.textContent = message;
        statusNode.classList.toggle('is-error', type === 'error');
        statusNode.classList.toggle('is-success', type === 'success');
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

    const openBrowser = async (field) => {
        activeField = field;
        selectedItem = null;

        if (!dialog) buildDialog();

        queryInput.value = currentReference(field);
        yearInput.value = currentYear(field);
        resultsNode.innerHTML = '';
        chooseButton.disabled = true;
        setStatus('جارٍ تحميل مصادر البحث...');

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }

        await loadSources();

        if (sourceSelect.options.length && queryInput.value.trim().length >= 2) {
            await runSearch();
        }
    };

    const loadSources = async () => {
        const url = new URL(activeField.dataset.sourcesUrl, window.location.origin);
        url.searchParams.set('year', yearInput.value);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'تعذر تحميل مصادر البحث.');
            }

            sourceSelect.innerHTML = '';

            if (!data.enabled) {
                setStatus('البحث الذكي معطل من إعدادات النظام. استخدم اختيار من الجهاز أو فعّله من الإعدادات.', 'error');
                return;
            }

            recursiveInput.checked = Boolean(data.recursive_default);

            const preferred = activeField.dataset.defaultSource || 'outgoing';
            const sources = Array.isArray(data.sources) ? data.sources : [];

            sources.forEach((source) => {
                const option = document.createElement('option');
                option.value = source.key;
                option.textContent = `${source.label}${source.available ? '' : ' — غير متاح حالياً'}`;
                option.disabled = !source.available;
                option.dataset.path = source.resolved_path || '';
                sourceSelect.appendChild(option);
            });

            const preferredOption = Array.from(sourceSelect.options)
                .find((option) => option.value === preferred && !option.disabled);
            const firstAvailable = Array.from(sourceSelect.options)
                .find((option) => !option.disabled);

            if (preferredOption) {
                sourceSelect.value = preferredOption.value;
            } else if (firstAvailable) {
                sourceSelect.value = firstAvailable.value;
            }

            if (!firstAvailable) {
                setStatus(
                    sources.length
                        ? 'المسارات محفوظة لكنها غير متاحة للسيرفر حالياً. تحقق من الشبكة وصلاحيات المشاركة.'
                        : 'لم يتم حفظ أي مسار للبحث الذكي. افتح إعدادات المسارات أولاً.',
                    'error'
                );
                return;
            }

            setStatus(`المصدر الجاهز: ${sourceSelect.selectedOptions[0]?.dataset.path || '-'}`, 'success');
        } catch (error) {
            setStatus(error.message || 'تعذر تحميل مصادر البحث.', 'error');
        }
    };

    async function runSearch() {
        if (!activeField || !sourceSelect.value) {
            setStatus('لا يوجد مصدر بحث متاح.', 'error');
            return;
        }

        const query = queryInput.value.trim();
        if (query.length < 2) {
            setStatus('اكتب رقم الكتاب أو عبارتي بحث على الأقل.', 'error');
            queryInput.focus();
            return;
        }

        selectedItem = null;
        chooseButton.disabled = true;
        searchButton.disabled = true;
        resultsNode.innerHTML = '<div class="da-smart-browser-empty">جارٍ البحث في الأرشيف...</div>';
        setStatus('جارٍ فحص الملفات المطابقة...');

        const url = new URL(activeField.dataset.searchUrl, window.location.origin);
        url.searchParams.set('source', sourceSelect.value);
        url.searchParams.set('q', query);
        url.searchParams.set('year', yearInput.value);
        url.searchParams.set('recursive', recursiveInput.checked ? '1' : '0');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'تعذر تنفيذ البحث.');
            }

            renderResults(data.items || []);

            const suffix = data.timed_out ? ' انتهت مهلة البحث قبل فحص جميع المجلدات.' : '';
            setStatus(
                `تم العثور على ${Number(data.items?.length || 0).toLocaleString('ar')} ملف. المسار: ${data.root}.${suffix}`,
                data.items?.length ? 'success' : ''
            );
        } catch (error) {
            resultsNode.innerHTML = '<div class="da-smart-browser-empty">لم تكتمل عملية البحث.</div>';
            setStatus(error.message || 'تعذر تنفيذ البحث.', 'error');
        } finally {
            searchButton.disabled = false;
        }
    }

    const renderResults = (items) => {
        resultsNode.innerHTML = '';

        if (!items.length) {
            resultsNode.innerHTML = '<div class="da-smart-browser-empty">لا توجد ملفات مطابقة لعبارة البحث.</div>';
            return;
        }

        items.forEach((item, index) => {
            const row = document.createElement('label');
            row.className = 'da-smart-browser-result';
            row.innerHTML = `
                <input type="radio" name="da_smart_browser_result" value="${index}">
                <div>
                    <div class="da-smart-browser-result-name">${escapeHtml(item.name)}</div>
                    <div class="da-smart-browser-result-meta">
                        ${escapeHtml(item.size_human)} — ${escapeHtml(item.modified_at_human)} — ${escapeHtml(String(item.extension || '').toUpperCase())}
                    </div>
                    <div class="da-smart-browser-result-path" dir="ltr">${escapeHtml(item.relative_path)}</div>
                </div>
                <a class="btn btn-sm btn-light da-smart-browser-result-preview"
                   href="${escapeHtml(item.preview_url)}"
                   target="_blank"
                   rel="noopener">
                    معاينة
                </a>
            `;

            const radio = row.querySelector('input[type="radio"]');
            const preview = row.querySelector('.da-smart-browser-result-preview');

            preview.addEventListener('click', (event) => event.stopPropagation());

            radio.addEventListener('change', () => {
                dialog.querySelectorAll('.da-smart-browser-result').forEach((node) => {
                    node.classList.remove('is-selected');
                });

                row.classList.add('is-selected');
                selectedItem = item;
                chooseButton.disabled = false;
            });

            resultsNode.appendChild(row);
        });
    };

    const chooseSelectedItem = () => {
        if (!activeField || !selectedItem) return;

        const tokenInput = activeField.querySelector('[data-smart-attachment-token]');
        const bridgeInput = activeField.querySelector('[data-file-bridge-token]');
        const nativeInput = activeField.querySelector('[data-smart-native-file]');
        const selectedNode = activeField.querySelector('[data-smart-attachment-selected]');
        const clearButton = activeField.querySelector('[data-smart-attachment-clear]');

        tokenInput.value = selectedItem.token;
        tokenInput.dispatchEvent(new Event('change', { bubbles: true }));
        if (bridgeInput) bridgeInput.value = '';
        nativeInput.value = '';
        selectedNode.textContent = `تم اختيار: ${selectedItem.name} — ${selectedItem.size_human}`;
        selectedNode.hidden = false;
        clearButton.hidden = false;

        dialog.close();
    };

    const clearField = (field) => {
        const tokenInput = field.querySelector('[data-smart-attachment-token]');
        const bridgeInput = field.querySelector('[data-file-bridge-token]');
        const nativeInput = field.querySelector('[data-smart-native-file]');
        const selectedNode = field.querySelector('[data-smart-attachment-selected]');
        const clearButton = field.querySelector('[data-smart-attachment-clear]');

        tokenInput.value = '';
        if (bridgeInput) bridgeInput.value = '';
        nativeInput.value = '';
        selectedNode.textContent = '';
        selectedNode.hidden = true;
        clearButton.hidden = true;
    };

    fields.forEach((field) => {
        const openButton = field.querySelector('[data-smart-attachment-open]');
        const nativeButton = field.querySelector('[data-smart-attachment-native]');
        const nativeInput = field.querySelector('[data-smart-native-file]');
        const tokenInput = field.querySelector('[data-smart-attachment-token]');
        const bridgeInput = field.querySelector('[data-file-bridge-token]');
        const selectedNode = field.querySelector('[data-smart-attachment-selected]');
        const clearButton = field.querySelector('[data-smart-attachment-clear]');

        openButton?.addEventListener('click', () => openBrowser(field));
        nativeButton?.addEventListener('click', () => nativeInput?.click());
        clearButton?.addEventListener('click', () => clearField(field));

        nativeInput?.addEventListener('change', () => {
            tokenInput.value = '';
            if (bridgeInput) bridgeInput.value = '';

            const file = nativeInput.files?.[0];
            if (file) {
                selectedNode.textContent = `تم اختيار من الجهاز: ${file.name}`;
                selectedNode.hidden = false;
                clearButton.hidden = false;
            } else {
                selectedNode.textContent = '';
                selectedNode.hidden = true;
                clearButton.hidden = true;
            }
        });

        if (tokenInput?.value || bridgeInput?.value) {
            clearButton.hidden = false;
        }
    });
})();
