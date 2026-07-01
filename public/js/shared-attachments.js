(function () {
    function copyText(value) {
        if (!value) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () {
                alert('تم نسخ الرابط بنجاح.');
            }).catch(function () {
                fallbackCopy(value);
            });
        } else {
            fallbackCopy(value);
        }
    }

    function fallbackCopy(value) {
        var input = document.createElement('textarea');
        input.value = value;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        try {
            document.execCommand('copy');
            alert('تم نسخ الرابط بنجاح.');
        } catch (e) {
            alert('تعذر نسخ الرابط تلقائيًا. انسخه يدويًا.');
        }
        document.body.removeChild(input);
    }

    document.addEventListener('click', function (event) {
        var copyTextButton = event.target.closest('[data-copy-text]');
        if (copyTextButton) {
            event.preventDefault();
            copyText(copyTextButton.getAttribute('data-copy-text'));
            return;
        }

        var copyTargetButton = event.target.closest('[data-copy-target]');
        if (copyTargetButton) {
            event.preventDefault();
            var selector = copyTargetButton.getAttribute('data-copy-target');
            var target = selector ? document.querySelector(selector) : null;
            if (target) {
                copyText(target.value || target.textContent || '');
            }
        }
    });
})();
