(function () {
    if (window.__DocumentArchiveNotificationsReady) {
        return;
    }
    window.__DocumentArchiveNotificationsReady = true;

    const defaults = {
        success: { title: 'تمت العملية بنجاح', icon: '✅', duration: 4200 },
        error: { title: 'حدث خطأ', icon: '⛔', duration: 6500 },
        warning: { title: 'تنبيه', icon: '⚠️', duration: 5600 },
        info: { title: 'معلومة', icon: 'ℹ️', duration: 4800 }
    };

    function ensureContainer() {
        let container = document.getElementById('app-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'app-toast-container';
            container.className = 'app-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }
        return container;
    }

    function normalize(input, type, title) {
        if (typeof input === 'string') {
            return { message: input, type: type || 'info', title: title || '' };
        }
        return input || {};
    }

    window.showAppNotification = function (input, type, title, duration) {
        const item = normalize(input, type, title);
        const toastType = ['success', 'error', 'warning', 'info'].includes(item.type) ? item.type : 'info';
        const config = defaults[toastType];
        const message = (item.message || '').toString().trim();

        if (!message) return null;

        const container = ensureContainer();
        const toast = document.createElement('div');
        const life = Number(item.duration || duration || config.duration || 4500);

        toast.className = 'app-toast ' + toastType;
        toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');
        toast.innerHTML = `
            <div class="app-toast-icon">${item.icon || config.icon}</div>
            <div class="app-toast-body">
                <div class="app-toast-title">${escapeHtml(item.title || title || config.title)}</div>
                <div class="app-toast-message">${escapeHtml(message).replace(/\n/g, '<br>')}</div>
            </div>
            <button type="button" class="app-toast-close" aria-label="إغلاق">×</button>
            <div class="app-toast-progress" style="animation-duration:${life}ms"></div>
        `;

        const remove = function () {
            if (toast.classList.contains('removing')) return;
            toast.classList.add('removing');
            window.setTimeout(() => toast.remove(), 220);
        };

        toast.querySelector('.app-toast-close').addEventListener('click', remove);
        container.appendChild(toast);

        const timer = window.setTimeout(remove, life);
        toast.addEventListener('mouseenter', () => window.clearTimeout(timer), { once: true });
        return toast;
    };

    window.addEventListener('app:notify', function (event) {
        window.showAppNotification(event.detail || {});
    });

    document.addEventListener('DOMContentLoaded', function () {
        const payload = Array.isArray(window.AppFlashNotifications) ? window.AppFlashNotifications : [];
        payload.forEach(function (item) {
            window.showAppNotification(item);
        });
    });

    function escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
/* notifications-center-arabic-spacing-v2-fix */
(function () {
    const pairs = [
        [['تعليم', 'الكل', ' كمقروء'].join(''), 'تعليم الكل كمقروء'],
        [['تعليم', 'الكل', 'كمقروء'].join(''), 'تعليم الكل كمقروء'],
        [['تعليم الكل', 'كمقروء'].join(''), 'تعليم الكل كمقروء'],
        [['تعليم', 'الكل'].join(''), 'تعليم الكل'],
        [['عرض', 'الكل'].join(''), 'عرض الكل'],
        [['إخفاء', 'المقروء'].join(''), 'إخفاء المقروء'],
        [['اخفاء', 'المقروء'].join(''), 'إخفاء المقروء'],
        [['حذف', 'المخفية'].join(''), 'حذف المخفية'],
        [['مركز', 'الإشعارات'].join(''), 'مركز الإشعارات'],
        [['نجاح', 'العملية'].join(''), 'نجاح العملية'],
        [['تم', 'حفظ'].join(''), 'تم حفظ'],
        [['تم', 'إنشاء'].join(''), 'تم إنشاء'],
        [['تم', 'توليد'].join(''), 'تم توليد'],
        [['رقم', 'الكتاب'].join(''), 'رقم الكتاب']
    ];

    function fixValue(value) {
        let next = value;
        pairs.forEach(([bad, good]) => {
            next = next.split(bad).join(good);
        });
        return next;
    }

    function fixTextNode(node) {
        const next = fixValue(node.nodeValue || '');
        if (next !== node.nodeValue) {
            node.nodeValue = next;
        }
    }

    function fixElement(element) {
        if (!element) return;
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(fixTextNode);
    }

    function fixNotificationsArabicSpacing(root) {
        const scope = root || document;
        if (scope.nodeType === Node.TEXT_NODE) {
            fixTextNode(scope);
            return;
        }
        if (scope.nodeType === Node.ELEMENT_NODE) {
            fixElement(scope);
        }
        const targets = scope.querySelectorAll
            ? scope.querySelectorAll('.notification-center, #notification-center, #notificationCenter, .notification-center-panel, .notification-dropdown, .notification-widget, [data-notification-center]')
            : [];
        targets.forEach(fixElement);
    }

    function run() {
        fixNotificationsArabicSpacing(document);
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach(fixNotificationsArabicSpacing);
                if (mutation.type === 'characterData') {
                    fixNotificationsArabicSpacing(mutation.target);
                }
            });
            fixNotificationsArabicSpacing(document);
        });
        observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
