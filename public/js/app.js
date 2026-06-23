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
});