document.addEventListener('DOMContentLoaded', function () {
    const confirmForms = document.querySelectorAll('[data-confirm]');

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm') || 'هل أنت متأكد؟';

            if (!confirm(message)) {
                event.preventDefault();
            }
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
