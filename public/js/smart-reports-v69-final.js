(function () {
    'use strict';

    function cleanText(text) {
        return String(text || '')
            .replace(/```[\s\S]*?```/g, '')
            .replace(/^#{1,6}\s*/gm, '')
            .replace(/\*\*(.*?)\*\*/g, '$1')
            .replace(/__([^_]+)__/g, '$1')
            .replace(/`([^`]+)`/g, '$1')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function setupFinalCopyButton() {
        const button = document.querySelector('[data-copy-smart-report]');
        const output = document.getElementById('smartReportOutput');
        if (!button || !output || button.dataset.v69CopyReady === '1') return;

        button.dataset.v69CopyReady = '1';
        button.addEventListener('click', async function (event) {
            event.preventDefault();
            event.stopPropagation();

            const text = cleanText(output.innerText || output.textContent || '');
            try {
                await navigator.clipboard.writeText(text);
                const original = button.textContent;
                button.textContent = 'تم النسخ';
                window.setTimeout(function () { button.textContent = original; }, 1400);
            } catch (error) {
                const range = document.createRange();
                range.selectNodeContents(output);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }, true);
    }

    function prepareOfficialPrintClone() {
        const clone = document.getElementById('smartReportPrintClone');
        if (!clone) return;

        clone.classList.add('smart-official-report-v69');
        clone.querySelectorAll('.smart-screen-only, .smart-print-hide, canvas, script, button').forEach(function (node) {
            node.remove();
        });
        clone.querySelectorAll('[style]').forEach(function (node) {
            if (!node.classList.contains('smart-summary-bar') && !node.closest('.smart-summary-bar')) {
                node.removeAttribute('style');
            }
        });
    }

    window.addEventListener('beforeprint', function () {
        window.setTimeout(prepareOfficialPrintClone, 20);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-smart-print]')) return;
        window.setTimeout(prepareOfficialPrintClone, 80);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupFinalCopyButton);
    } else {
        setupFinalCopyButton();
    }
})();
