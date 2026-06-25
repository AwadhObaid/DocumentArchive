(function () {
    'use strict';

    function getPlaceholder(input) {
        return input.getAttribute('data-placeholder') || 'لم يتم اختيار ملف';
    }

    function getButtonText(input) {
        return input.getAttribute('data-button-text') || 'اختيار ملف';
    }

    function selectedFileText(input) {
        if (!input.files || input.files.length === 0) {
            return getPlaceholder(input);
        }

        if (input.files.length === 1) {
            return input.files[0].name;
        }

        return 'تم اختيار ' + input.files.length + ' ملفات';
    }

    function enhanceFileInput(input) {
        if (!input || input.dataset.daArabicFileReady === '1') {
            return;
        }

        input.dataset.daArabicFileReady = '1';
        input.classList.add('da-native-file-input');

        var wrapper = document.createElement('div');
        wrapper.className = 'da-file-control';
        wrapper.setAttribute('dir', 'rtl');

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'da-file-button';
        button.textContent = getButtonText(input);

        var fileName = document.createElement('span');
        fileName.className = 'da-file-name is-empty';
        fileName.textContent = selectedFileText(input);

        wrapper.appendChild(button);
        wrapper.appendChild(fileName);

        input.insertAdjacentElement('afterend', wrapper);

        button.addEventListener('click', function () {
            input.click();
        });

        wrapper.addEventListener('click', function (event) {
            if (event.target !== button) {
                input.click();
            }
        });

        input.addEventListener('change', function () {
            fileName.textContent = selectedFileText(input);
            if (input.files && input.files.length > 0) {
                fileName.classList.remove('is-empty');
                fileName.classList.add('has-file');
            } else {
                fileName.classList.remove('has-file');
                fileName.classList.add('is-empty');
            }
        });
    }

    function initArabicFileInputs(root) {
        var scope = root || document;
        var inputs = scope.querySelectorAll('input[type="file"]:not([data-no-arabic-file])');
        inputs.forEach(enhanceFileInput);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initArabicFileInputs(document);

        if ('MutationObserver' in window) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) {
                            return;
                        }

                        if (node.matches && node.matches('input[type="file"]:not([data-no-arabic-file])')) {
                            enhanceFileInput(node);
                            return;
                        }

                        if (node.querySelectorAll) {
                            initArabicFileInputs(node);
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
})();
