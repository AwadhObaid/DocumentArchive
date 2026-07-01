document.addEventListener('DOMContentLoaded', function () {
    function createConfirmDialog() {
        let dialog = document.getElementById('app-confirm-dialog');
        if (dialog) {
            return dialog;
        }

        dialog = document.createElement('div');
        dialog.id = 'app-confirm-dialog';
        dialog.className = 'app-confirm-overlay';
        dialog.setAttribute('aria-hidden', 'true');
        dialog.innerHTML = `
            <div class="app-confirm-card" role="dialog" aria-modal="true" aria-labelledby="app-confirm-title" aria-describedby="app-confirm-message">
                <div class="app-confirm-icon" aria-hidden="true">✉️</div>
                <div class="app-confirm-content">
                    <h3 id="app-confirm-title">تأكيد العملية</h3>
                    <p id="app-confirm-message">هل أنت متأكد؟</p>
                    <div class="app-confirm-extra" id="app-confirm-extra"></div>
                    <div class="app-confirm-actions">
                        <button type="button" class="btn btn-light app-confirm-cancel">إلغاء</button>
                        <button type="button" class="btn btn-primary app-confirm-accept">تأكيد</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(dialog);
        return dialog;
    }

    function openConfirmDialog(options) {
        const dialog = createConfirmDialog();
        const titleEl = dialog.querySelector('#app-confirm-title');
        const messageEl = dialog.querySelector('#app-confirm-message');
        const extraEl = dialog.querySelector('#app-confirm-extra');
        const acceptBtn = dialog.querySelector('.app-confirm-accept');
        const cancelBtn = dialog.querySelector('.app-confirm-cancel');
        const card = dialog.querySelector('.app-confirm-card');

        titleEl.textContent = options.title || 'تأكيد العملية';
        messageEl.textContent = options.message || 'هل أنت متأكد؟';
        acceptBtn.textContent = options.confirmText || 'تأكيد';
        cancelBtn.textContent = options.cancelText || 'إلغاء';

        if (options.extra) {
            extraEl.textContent = options.extra;
            extraEl.style.display = '';
        } else {
            extraEl.textContent = '';
            extraEl.style.display = 'none';
        }

        dialog.classList.add('is-open');
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('has-open-confirm');

        return new Promise(function (resolve) {
            function close(result) {
                dialog.classList.remove('is-open');
                dialog.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('has-open-confirm');
                acceptBtn.removeEventListener('click', onAccept);
                cancelBtn.removeEventListener('click', onCancel);
                dialog.removeEventListener('click', onOverlay);
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            }

            function onAccept() { close(true); }
            function onCancel() { close(false); }
            function onOverlay(event) {
                if (!card.contains(event.target)) {
                    close(false);
                }
            }
            function onKeydown(event) {
                if (event.key === 'Escape') {
                    close(false);
                }
            }

            acceptBtn.addEventListener('click', onAccept);
            cancelBtn.addEventListener('click', onCancel);
            dialog.addEventListener('click', onOverlay);
            document.addEventListener('keydown', onKeydown);
            window.setTimeout(function () { acceptBtn.focus(); }, 60);
        });
    }

    const confirmForms = document.querySelectorAll('form[data-confirm]');

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmAccepted === '1') {
                delete form.dataset.confirmAccepted;
                return;
            }

            event.preventDefault();

            openConfirmDialog({
                title: form.getAttribute('data-confirm-title') || 'تأكيد العملية',
                message: form.getAttribute('data-confirm') || 'هل أنت متأكد؟',
                extra: form.getAttribute('data-confirm-extra') || '',
                confirmText: form.getAttribute('data-confirm-yes') || 'نعم، متابعة',
                cancelText: form.getAttribute('data-confirm-no') || 'إلغاء'
            }).then(function (confirmed) {
                if (confirmed) {
                    form.dataset.confirmAccepted = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            });
        });
    });

    const sidebar = document.getElementById('sidebar');
    const sidebarButton = document.querySelector('[data-toggle-sidebar]');

    if (sidebar && sidebarButton) {
        sidebarButton.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    const themeButton = document.querySelector('[data-toggle-theme]');
    const savedTheme = localStorage.getItem('archive_theme');

    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }

    if (themeButton) {
        themeButton.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'light' ? 'dark' : 'light';

            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('archive_theme', next);
        });
    }
});
