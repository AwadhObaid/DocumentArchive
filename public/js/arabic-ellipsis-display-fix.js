(function () {
    'use strict';

    function markNoEllipsis() {
        var selectors = [
            'h1','h2','h3','h4','h5','h6','p','label','th','td','button',
            '.btn','.alert','.badge','.card','.page-title','.page-subtitle',
            '.brand-title','.brand-subtitle','.section-title','.stat-title','.stat-label',
            '.sidebar a','.sidebar span','.app-sidebar a','.app-sidebar span',
            'aside a','aside span','header a','header span','.nav-link','.menu-link'
        ];

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.classList.add('da-force-no-ellipsis');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markNoEllipsis);
    } else {
        markNoEllipsis();
    }
})();