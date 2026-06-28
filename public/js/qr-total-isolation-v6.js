(function () {
    function isAllowedPage() {
        var p = window.location.pathname || '';
        return /\/documents\/\d+\/print-reference\/?$/.test(p) || p.indexOf('/settings/qr-print-position') === 0;
    }
    if (isAllowedPage()) return;

    var selectors = [
        '.document-qr-card',
        '.document-qr-wrapper',
        '.da-document-qr',
        '.da-document-qr-card',
        '.da-print-qr',
        '.da-print-qr-only',
        '.da-qr-print',
        '.da-qr-print-box',
        '.qr-print-position-card',
        '[data-da-qr]',
        '[data-da-print-qr]',
        'img[src*="/qr.svg"]',
        'img[src*="qr.svg"]'
    ];

    function removeLeakedQr() {
        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (node) {
                var box = node.closest('.document-qr-card, .document-qr-wrapper, .da-document-qr, .da-document-qr-card, .da-print-qr, .da-print-qr-only, .da-qr-print, .da-qr-print-box, .qr-print-position-card, [data-da-qr], [data-da-print-qr]');
                (box || node).remove();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeLeakedQr);
    } else {
        removeLeakedQr();
    }
    setTimeout(removeLeakedQr, 100);
    setTimeout(removeLeakedQr, 500);
})();