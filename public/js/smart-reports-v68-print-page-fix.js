(function () {
    'use strict';

    function getReportSource() {
        return document.getElementById('smartPrintableReport');
    }

    function removeOldClone() {
        const oldClone = document.getElementById('smartReportPrintClone');
        if (oldClone) oldClone.remove();
    }

    function createPrintClone() {
        const source = getReportSource();
        if (!source) return null;

        removeOldClone();

        const clone = source.cloneNode(true);
        clone.id = 'smartReportPrintClone';
        clone.classList.add('smart-report-print-clone');

        clone.querySelectorAll('script, canvas, button, [data-smart-print], [data-copy-smart-report]').forEach(function (node) {
            node.remove();
        });

        clone.querySelectorAll('[id]').forEach(function (node) {
            if (node.id !== 'smartReportPrintClone') {
                node.removeAttribute('id');
            }
        });

        document.body.appendChild(clone);
        return clone;
    }

    function preparePrint() {
        if (!getReportSource()) return false;
        createPrintClone();
        document.body.classList.add('smart-report-printing');
        return true;
    }

    function cleanupPrint() {
        window.setTimeout(function () {
            document.body.classList.remove('smart-report-printing');
            removeOldClone();
        }, 250);
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-smart-print]');
        if (!button) return;

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        if (!preparePrint()) {
            window.print();
            return;
        }

        window.setTimeout(function () {
            window.print();
        }, 60);
    }, true);

    window.addEventListener('beforeprint', function () {
        if (!document.body.classList.contains('smart-report-printing') && getReportSource()) {
            preparePrint();
        }
    });

    window.addEventListener('afterprint', cleanupPrint);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && document.body.classList.contains('smart-report-printing')) {
            cleanupPrint();
        }
    });
})();
