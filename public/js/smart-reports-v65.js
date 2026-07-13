document.addEventListener('DOMContentLoaded', function () {
    const copyButton = document.querySelector('[data-copy-smart-report]');
    const output = document.getElementById('smartReportOutput');

    if (!copyButton || !output) {
        return;
    }

    copyButton.addEventListener('click', async function () {
        const text = output.innerText || output.textContent || '';

        try {
            await navigator.clipboard.writeText(text);
            const original = copyButton.textContent;
            copyButton.textContent = 'تم النسخ';
            setTimeout(() => copyButton.textContent = original, 1600);
        } catch (error) {
            const range = document.createRange();
            range.selectNodeContents(output);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
    });
});
