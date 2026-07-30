/* DocumentArchive Live Data Synchronization V93 - dashboard alert consistency */
(() => {
    'use strict';

    const body = document.body;
    const endpoint = body?.dataset.liveSyncUrl || '';
    const routeName = body?.dataset.liveSyncRoute || '';

    const resolveResource = (route) => {
        if (route === 'dashboard') return 'dashboard';
        if (route.startsWith('documents.')) return 'documents';
        if (route.startsWith('memos.')) return 'memos';
        if (route.startsWith('circulars.')) return 'circulars';
        if (route.startsWith('misc-books.')) return 'misc_books';
        return null;
    };

    const currentResource = resolveResource(routeName);

    if (!endpoint || !currentResource) {
        return;
    }

    const labels = {
        documents: 'الكتب',
        memos: 'المذكرات',
        circulars: 'التعاميم',
        misc_books: 'المتفرقات',
    };

    const state = {
        polling: false,
        stopped: false,
        timer: null,
        interval: 15000,
        signatures: {},
        initialized: false,
        dirty: false,
        lastNoticeKey: '',
    };

    let notice = null;

    const formatNumber = (value) => {
        const number = Number(value);
        return Number.isFinite(number) ? new Intl.NumberFormat('en-US').format(number) : '0';
    };

    const ensureNotice = () => {
        if (notice) return notice;

        const element = document.createElement('section');
        element.className = 'da-live-sync-notice';
        element.setAttribute('role', 'status');
        element.setAttribute('aria-live', 'polite');
        element.setAttribute('aria-hidden', 'true');
        element.innerHTML = `
            <div class="da-live-sync-notice__icon" aria-hidden="true">🔄</div>
            <div class="da-live-sync-notice__content">
                <strong data-live-sync-title>تم تحديث البيانات</strong>
                <p data-live-sync-message>توجد بيانات أحدث متاحة من جهاز آخر.</p>
            </div>
            <div class="da-live-sync-notice__actions">
                <button type="button" class="da-live-sync-notice__refresh" data-live-sync-refresh>تحديث الآن</button>
                <button type="button" class="da-live-sync-notice__dismiss" data-live-sync-dismiss>لاحقًا</button>
            </div>
        `;

        element.querySelector('[data-live-sync-refresh]')?.addEventListener('click', () => {
            element.classList.add('is-refreshing');
            window.location.reload();
        });

        element.querySelector('[data-live-sync-dismiss]')?.addEventListener('click', () => {
            element.classList.remove('is-visible');
            element.setAttribute('aria-hidden', 'true');
        });

        document.body.appendChild(element);
        notice = element;
        return element;
    };

    const markDirtyForms = () => {
        const setDirty = (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            const form = target.closest('form');
            if (!(form instanceof HTMLFormElement)) return;
            if ((form.method || 'GET').toUpperCase() === 'GET') return;
            if (form.matches('[data-live-sync-ignore], [data-no-live-sync]')) return;

            state.dirty = true;
        };

        document.addEventListener('input', setDirty, true);
        document.addEventListener('change', setDirty, true);
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form instanceof HTMLFormElement && (form.method || 'GET').toUpperCase() !== 'GET') {
                state.dirty = false;
            }
        }, true);
    };

    const showNotice = (changedResources, dashboardUpdated = false) => {
        const key = changedResources
            .map((resource) => `${resource}:${state.signatures[resource] || ''}`)
            .sort()
            .join('|');

        if (key && key === state.lastNoticeKey) {
            return;
        }

        state.lastNoticeKey = key;
        const element = ensureNotice();
        const title = element.querySelector('[data-live-sync-title]');
        const message = element.querySelector('[data-live-sync-message]');

        const names = changedResources.map((resource) => labels[resource] || resource);
        const joinedNames = names.join('، ');

        if (title) {
            title.textContent = dashboardUpdated
                ? 'تم تحديث عدادات لوحة التحكم'
                : 'توجد بيانات أحدث';
        }

        if (message) {
            if (state.dirty) {
                message.textContent = `تم تغيير بيانات ${joinedNames} من جهاز آخر أثناء عملك. لن تُحدَّث الصفحة تلقائيًا حتى لا تفقد ما كتبته.`;
            } else if (dashboardUpdated) {
                message.textContent = `تم تحديث الأعداد تلقائيًا. حدّث الصفحة لعرض أحدث الرسوم والأنشطة في ${joinedNames}.`;
            } else {
                message.textContent = `تم تحديث بيانات ${joinedNames} من جهاز آخر. اضغط «تحديث الآن» لعرض أحدث نسخة.`;
            }
        }

        element.classList.add('is-visible');
        element.setAttribute('aria-hidden', 'false');
    };

    const syncAdministrativeAlertEmptyState = () => {
        const alertsContainer = document.querySelector('[data-live-sync-alerts]');
        const emptyState = document.querySelector('[data-live-sync-alert-empty]');

        if (!alertsContainer || !emptyState) return;

        const visibleAlerts = alertsContainer.querySelectorAll(
            '.da-admin-alert-item:not([hidden])'
        ).length;

        emptyState.hidden = visibleAlerts > 0;
    };

    const updateDashboardCounts = (counts) => {
        if (!counts || typeof counts !== 'object') return false;

        let changed = false;
        Object.entries(counts).forEach(([key, value]) => {
            const next = formatNumber(value);
            const numericValue = Number(value);

            document.querySelectorAll(`[data-live-sync-count="${CSS.escape(key)}"]`).forEach((node) => {
                if (node.textContent.trim() !== next) {
                    node.textContent = next;
                    node.classList.remove('da-live-sync-count-updated');
                    void node.offsetWidth;
                    node.classList.add('da-live-sync-count-updated');
                    changed = true;
                }
            });

            document.querySelectorAll(`[data-live-sync-alert-count="${CSS.escape(key)}"]`).forEach((node) => {
                if (node.textContent.trim() !== next) {
                    node.textContent = next;
                    changed = true;
                }

                const alert = node.closest('[data-live-sync-alert]');
                if (alert) {
                    alert.hidden = !Number.isFinite(numericValue) || numericValue <= 0;
                }
            });
        });

        syncAdministrativeAlertEmptyState();

        return changed;
    };

    const changedResourcesFromPayload = (resources) => {
        const changed = [];

        Object.entries(resources || {}).forEach(([resource, details]) => {
            const signature = typeof details?.signature === 'string' ? details.signature : '';
            if (!signature) return;

            if (state.initialized && state.signatures[resource] && state.signatures[resource] !== signature) {
                changed.push(resource);
            }

            state.signatures[resource] = signature;
        });

        return changed;
    };

    const relevantChanges = (changed) => {
        if (currentResource === 'dashboard') {
            return changed;
        }

        return changed.includes(currentResource) ? [currentResource] : [];
    };

    const schedule = () => {
        if (state.stopped) return;
        window.clearTimeout(state.timer);
        state.timer = window.setTimeout(poll, state.interval);
    };

    const poll = async () => {
        if (state.stopped || state.polling) {
            schedule();
            return;
        }

        if (document.hidden) {
            schedule();
            return;
        }

        state.polling = true;

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-DocumentArchive-Background': 'live-sync-v91',
                },
            });

            if (response.status === 401 || response.status === 419) {
                state.stopped = true;
                return;
            }

            if (!response.ok) {
                throw new Error(`Live sync HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (!payload?.ok) {
                throw new Error('Live sync response is unavailable');
            }

            const requestedInterval = Number(payload.poll_interval_ms);
            if (Number.isFinite(requestedInterval)) {
                state.interval = Math.max(10000, Math.min(60000, requestedInterval));
            }

            const changed = changedResourcesFromPayload(payload.resources || {});
            const relevant = relevantChanges(changed);

            if (currentResource === 'dashboard') {
                const dashboardUpdated = updateDashboardCounts(payload.dashboard_counts || {});
                if (state.initialized && relevant.length > 0) {
                    showNotice(relevant, dashboardUpdated);
                }
            } else if (state.initialized && relevant.length > 0) {
                showNotice(relevant, false);
            }

            state.initialized = true;
        } catch (_) {
            // مزامنة الخلفية صامتة: لا نزعج المستخدم عند انقطاع مؤقت في الشبكة.
        } finally {
            state.polling = false;
            schedule();
        }
    };

    markDirtyForms();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !state.stopped) {
            window.clearTimeout(state.timer);
            poll();
        }
    });

    window.addEventListener('online', () => {
        if (!state.stopped) {
            window.clearTimeout(state.timer);
            poll();
        }
    });

    window.addEventListener('beforeunload', () => {
        state.stopped = true;
        window.clearTimeout(state.timer);
    });

    poll();
})();
