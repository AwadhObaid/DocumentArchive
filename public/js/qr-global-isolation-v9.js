(function () {
    var isPrintReference = /\/documents\/\d+\/print-reference(?:$|[?#\/])/.test(window.location.pathname);
    if (isPrintReference) return;

    function removeLeakedQr() {
        var selectors = [
            '.document-qr-card',
            '.da-document-qr',
            '.da-qr-print',
            '.da-print-qr',
            '.qr-print-position',
            '.qr-position-card',
            '[data-da-qr]'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function (node) {
            node.remove();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeLeakedQr);
    } else {
        removeLeakedQr();
    }
    setTimeout(removeLeakedQr, 300);
    setTimeout(removeLeakedQr, 1200);
})();