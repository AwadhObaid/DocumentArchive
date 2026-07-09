(function () {
    'use strict';

    function isLoginForm(form) {
        if (!form) return false;
        const action = String(form.getAttribute('action') || '').toLowerCase();
        return form.hasAttribute('data-da-login-form')
            || action.includes('/login')
            || (form.querySelector('input[name="username"], input[name="email"]') && form.querySelector('input[name="password"]'));
    }

    function clearInput(input) {
        if (!input) return;
        input.value = '';
        input.defaultValue = '';
        input.setAttribute('value', '');
    }

    function secureInput(input, kind) {
        if (!input) return;

        input.setAttribute('autocomplete', 'new-password');
        input.setAttribute('autocapitalize', 'none');
        input.setAttribute('spellcheck', 'false');
        input.setAttribute('data-lpignore', 'true');
        input.setAttribute('data-1p-ignore', 'true');
        input.setAttribute('data-da-secure-login-input', kind || 'login');

        clearInput(input);

        // Delayed readonly reduces aggressive autofill without blocking real typing.
        input.setAttribute('readonly', 'readonly');
        const unlock = function () {
            input.removeAttribute('readonly');
        };
        ['focus', 'pointerdown', 'touchstart', 'keydown'].forEach(function (eventName) {
            input.addEventListener(eventName, unlock, { once: true, passive: true });
        });
        window.setTimeout(unlock, 450);
    }

    function hardenLoginForm(form) {
        if (!isLoginForm(form)) return;

        form.setAttribute('autocomplete', 'off');
        form.setAttribute('data-da-login-form', '1');
        form.setAttribute('data-lpignore', 'true');
        form.setAttribute('data-1p-ignore', 'true');

        const username = form.querySelector('input[name="username"], input[name="email"]');
        const password = form.querySelector('input[name="password"]');
        const remember = form.querySelector('input[name="remember"], input[name="remember_me"]');

        secureInput(username, 'username');
        secureInput(password, 'password');
        if (remember) {
            remember.checked = false;
            remember.defaultChecked = false;
            remember.disabled = true;
            remember.setAttribute('data-da-remember-disabled-v44', '1');
        }
    }

    function run() {
        document.querySelectorAll('form').forEach(hardenLoginForm);
    }

    document.addEventListener('DOMContentLoaded', function () {
        run();
        window.setTimeout(run, 100);
        window.setTimeout(run, 600);
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            run();
        }
    });
})();
