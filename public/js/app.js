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

    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }
});
