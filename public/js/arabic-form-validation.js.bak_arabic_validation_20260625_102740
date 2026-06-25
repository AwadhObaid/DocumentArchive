(function () {
    'use strict';

    if (window.__documentArchiveArabicValidationLoaded) {
        return;
    }
    window.__documentArchiveArabicValidationLoaded = true;

    const DEFAULT_MESSAGES = {
        valueMissing: 'هذا الحقل مطلوب.',
        typeMismatch: 'القيمة المدخلة غير صحيحة.',
        patternMismatch: 'صيغة هذا الحقل غير صحيحة.',
        tooShort: 'القيمة المدخلة أقصر من المطلوب.',
        tooLong: 'القيمة المدخلة أطول من المسموح.',
        rangeUnderflow: 'القيمة أقل من الحد المسموح.',
        rangeOverflow: 'القيمة أكبر من الحد المسموح.',
        stepMismatch: 'القيمة غير متوافقة مع الخطوة المطلوبة.',
        badInput: 'المدخل غير صالح.',
        customError: 'يرجى تصحيح هذا الحقل.'
    };

    const FIELD_LABELS = {
        reference_date: 'تاريخ الكتاب',
        title: 'عنوان الكتاب',
        subject: 'موضوع الكتاب',
        main_policy_number: 'البوليصة الرئيسية',
        sub_policy_number: 'البوليصة الفرعية',
        department_id: 'الإدارة',
        document_type_id: 'نوع الكتاب',
        sender: 'المرسل',
        recipient: 'المستلم',
        confidentiality: 'درجة السرية',
        priority: 'الأولوية',
        description: 'الوصف',
        notes: 'الملاحظات',
        attachment: 'المرفق',
        file: 'الملف',
        username: 'اسم المستخدم',
        password: 'كلمة المرور',
        password_confirmation: 'تأكيد كلمة المرور',
        email: 'البريد الإلكتروني',
        name: 'الاسم'
    };

    function isElementVisible(el) {
        if (!el || el.type === 'hidden') return false;
        if (el.disabled) return false;
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        if (el.offsetParent === null && style.position !== 'fixed') return false;
        return true;
    }

    function getFieldKey(field) {
        return field.getAttribute('name') || field.getAttribute('id') || '';
    }

    function getFieldLabel(field) {
        const explicit = field.getAttribute('data-label') || field.getAttribute('aria-label');
        if (explicit) return explicit.trim();

        const key = getFieldKey(field).replace(/\[\]$/, '');
        if (FIELD_LABELS[key]) return FIELD_LABELS[key];

        const id = field.getAttribute('id');
        if (id) {
            const label = document.querySelector('label[for="' + CSS.escape(id) + '"]');
            if (label && label.textContent.trim()) return label.textContent.trim().replace('*', '').trim();
        }

        const nearestLabel = field.closest('label');
        if (nearestLabel && nearestLabel.textContent.trim()) {
            return nearestLabel.textContent.trim().replace('*', '').trim();
        }

        return 'هذا الحقل';
    }

    function getValidationMessage(field) {
        const label = getFieldLabel(field);
        const custom = field.getAttribute('data-arabic-validation-message') || field.getAttribute('data-validation-message');
        if (custom) return custom;

        const validity = field.validity;
        if (validity.valueMissing) {
            return label === 'هذا الحقل' ? DEFAULT_MESSAGES.valueMissing : 'حقل ' + label + ' مطلوب.';
        }
        if (validity.typeMismatch) return 'صيغة ' + label + ' غير صحيحة.';
        if (validity.patternMismatch) return 'صيغة ' + label + ' غير صحيحة.';
        if (validity.tooShort) return 'حقل ' + label + ' أقصر من المطلوب.';
        if (validity.tooLong) return 'حقل ' + label + ' أطول من المسموح.';
        if (validity.rangeUnderflow) return 'قيمة ' + label + ' أقل من الحد المسموح.';
        if (validity.rangeOverflow) return 'قيمة ' + label + ' أكبر من الحد المسموح.';
        if (validity.stepMismatch) return 'قيمة ' + label + ' غير متوافقة مع الخطوة المطلوبة.';
        if (validity.badInput) return 'القيمة المدخلة في ' + label + ' غير صالحة.';
        if (validity.customError) return field.validationMessage || DEFAULT_MESSAGES.customError;
        return DEFAULT_MESSAGES.customError;
    }

    function removeFieldError(field) {
        field.classList.remove('is-invalid', 'da-invalid-field');
        field.removeAttribute('aria-invalid');
        const wrapper = field.closest('.form-group, .mb-3, .field, .input-group, .form-field, .da-field') || field.parentElement;
        if (!wrapper) return;
        const old = wrapper.querySelector(':scope > .da-validation-message');
        if (old) old.remove();
    }

    function showFieldError(field, message) {
        removeFieldError(field);
        field.classList.add('is-invalid', 'da-invalid-field');
        field.setAttribute('aria-invalid', 'true');

        const wrapper = field.closest('.form-group, .mb-3, .field, .input-group, .form-field, .da-field') || field.parentElement;
        if (!wrapper) return;

        const div = document.createElement('div');
        div.className = 'da-validation-message';
        div.setAttribute('role', 'alert');
        div.textContent = message;
        wrapper.appendChild(div);
    }

    function findInvalidField(form) {
        const fields = Array.from(form.querySelectorAll('input, select, textarea'));
        for (const field of fields) {
            if (!isElementVisible(field)) continue;
            if (field.type === 'button' || field.type === 'submit' || field.type === 'reset') continue;
            removeFieldError(field);
            if (!field.checkValidity()) return field;
        }
        return null;
    }

    function validateForm(form) {
        const invalidField = findInvalidField(form);
        if (!invalidField) return true;

        const message = getValidationMessage(invalidField);
        showFieldError(invalidField, message);
        showToast(message);

        setTimeout(() => {
            try {
                invalidField.focus({ preventScroll: false });
                invalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                invalidField.focus();
            }
        }, 80);

        return false;
    }

    function showToast(message) {
        let toast = document.querySelector('.da-validation-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'da-validation-toast';
            toast.setAttribute('role', 'alert');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(window.__daValidationToastTimer);
        window.__daValidationToastTimer = setTimeout(() => {
            toast.classList.remove('show');
        }, 4500);
    }

    function prepareForms() {
        document.querySelectorAll('form').forEach((form) => {
            if (form.hasAttribute('data-native-validation')) return;
            form.setAttribute('novalidate', 'novalidate');
        });
    }

    document.addEventListener('invalid', function (event) {
        const field = event.target;
        if (!field || !field.closest || field.closest('form[data-native-validation]')) return;
        event.preventDefault();
        if (isElementVisible(field)) {
            const message = getValidationMessage(field);
            showFieldError(field, message);
            showToast(message);
        }
    }, true);

    document.addEventListener('input', function (event) {
        const field = event.target;
        if (!field || !field.matches || !field.matches('input, select, textarea')) return;
        if (field.checkValidity()) removeFieldError(field);
    }, true);

    document.addEventListener('change', function (event) {
        const field = event.target;
        if (!field || !field.matches || !field.matches('input, select, textarea')) return;
        if (field.checkValidity()) removeFieldError(field);
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form || !form.matches || !form.matches('form')) return;
        if (form.hasAttribute('data-native-validation')) return;
        if (form.hasAttribute('data-skip-arabic-validation')) return;

        if (!validateForm(form)) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepareForms);
    } else {
        prepareForms();
    }
})();
