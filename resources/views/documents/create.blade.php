@extends('layouts.app')

@section('title', 'إضافة كتاب')

@section('content')
    <div class="page-title">
        <h1>إضافة كتاب جديد</h1>

        @if(auth()->user()?->hasPermission('documents.view'))
        <a href="{{ route('documents.index') }}" class="btn btn-secondary">
            رجوع
        </a>
        @endif
    </div>

    <div class="card">
        @if(auth()->user()?->hasPermission('documents.create'))
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label>تاريخ الكتاب</label>
                    <input type="date" name="reference_date" value="{{ old('reference_date', date('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label>عنوان الكتاب</label>
                    <input type="text" name="title" value="{{ old('title') }}" required>
                </div>

                <div class="form-group">
                    <label>البوليصة الرئيسية</label>
                    <input type="text" name="main_policy_number" value="{{ old('main_policy_number') }}">
                </div>

                <div class="form-group">
                    <label>البوليصة الفرعية</label>
                    <input type="text" name="sub_policy_number" value="{{ old('sub_policy_number') }}">
                </div>

                <div class="form-group full">
                    <label>موضوع الكتاب</label>
                    <textarea name="subject" required>{{ old('subject') }}</textarea>
                </div>

                <div class="form-group">
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">-- اختر الإدارة --</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>نوع الكتاب</label>
                    <select name="document_type_id">
                        <option value="">-- اختر النوع --</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('document_type_id') == $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>المرسل</label>
                    <input type="text" name="sender" value="{{ old('sender') }}">
                </div>

                <div class="form-group">
                    <label>المستلم
</label>
                    <input type="text" name="receiver" value="{{ old('receiver') }}">
                </div>

                <div class="form-group">
                    <label>درجة السرية</label>
                    <select name="confidentiality" required>
                        <option value="normal" @selected(old('confidentiality') === 'normal')>عادي</option>
                        <option value="confidential" @selected(old('confidentiality') === 'confidential')>سري</option>
                        <option value="very_confidential" @selected(old('confidentiality') === 'very_confidential')>سري للغاية</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="priority" required>
                        <option value="normal" @selected(old('priority') === 'normal')>عادي</option>
                        <option value="high" @selected(old('priority') === 'high')>هام
</option>
                        <option value="urgent" @selected(old('priority') === 'urgent')>عاجل</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description') }}</textarea>
                </div>

                <div class="form-group full">
                    <label>نسخة الكتاب الممسوحة ضوئياً / مرفق اختياري</label>
                    <input type="file" name="attachment">
                    <small>يمكن إنشاء الكتاب أولاً وطباعة رقمه، ثمرفع النسخة الممسوحة لاحقاً بعد رجوع المندوب.</small>
                </div>

                <div class="form-group full">
                    <label>ملاحظات</label>
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">
                    حفظ وتوليد رقمالكتاب
                </button>
            </div>
        </form>
        @endif
    </div>





<!-- DA_POLICY_DUPLICATE_WARNING_V4_START -->
<style>
    .da-policy-note-v4 { display: block; margin-top: 7px; font-size: 12px; font-weight: 850; line-height: 1.7; }
    .da-policy-note-v4.warning { color: #fbbf24; }
    .da-policy-note-v4.ok { color: #34d399; }
    .da-policy-modal-backdrop-v4 {
        position: fixed; inset: 0; z-index: 999999; display: none; align-items: center; justify-content: center;
        background: rgba(2, 6, 23, .72); backdrop-filter: blur(8px); padding: 18px; direction: rtl;
    }
    .da-policy-modal-v4 { width: min(570px, 100%); background: #0f172a; border: 1px solid rgba(245, 158, 11, .60); border-radius: 22px; box-shadow: 0 24px 80px rgba(0,0,0,.45); color: #f8fafc; overflow: hidden; text-align: right; }
    .da-policy-modal-head-v4 { padding: 18px 20px; background: rgba(245, 158, 11, .16); border-bottom: 1px solid rgba(245, 158, 11, .28); }
    .da-policy-modal-head-v4 strong { display: block; font-size: 20px; font-weight: 950; }
    .da-policy-modal-body-v4 { padding: 18px 20px; line-height: 1.9; color: #e5e7eb; font-weight: 780; }
    .da-policy-modal-info-v4 { margin-top: 12px; padding: 12px; background: rgba(15, 23, 42, .84); border: 1px solid rgba(148, 163, 184, .22); border-radius: 14px; color: #cbd5e1; font-size: 13px; }
    .da-policy-modal-actions-v4 { display: flex; gap: 10px; justify-content: flex-start; padding: 0 20px 18px; flex-wrap: wrap; }
    .da-policy-modal-actions-v4 button { border: 0; border-radius: 12px; padding: 10px 16px; cursor: pointer; font-weight: 950; color: #fff; }
    .da-policy-yes-v4 { background: #2563eb; }
    .da-policy-no-v4 { background: #dc2626; }
</style>
<script>
(function () {
    if (window.__DA_POLICY_DUPLICATE_WARNING_V4_ACTIVE__) return;
    window.__DA_POLICY_DUPLICATE_WARNING_V4_ACTIVE__ = true;

    const checkUrl = @json(route('documents.check-policy-duplicate'));
    const currentDocumentId = @json(isset($document) ? ($document->id ?? null) : null);
    const fieldConfig = {
        main_policy_number: 'البوليصة الرئيسية',
        sub_policy_number: 'البوليصة الفرعية'
    };
    const fieldStates = new WeakMap();
    let globalModalPromise = null;

    function stateFor(input) {
        if (!fieldStates.has(input)) {
            fieldStates.set(input, { timer: null, pendingValue: null, pendingPromise: null });
        }
        return fieldStates.get(input);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function inputFor(field) {
        return document.querySelector('[name="' + field + '"], #' + field + ', [data-policy-field="' + field + '"]');
    }

    function setNote(input, message, type) {
        if (!input) return;
        let note = input.parentElement.querySelector('.da-policy-note-v4[data-field="' + input.name + '"]');
        if (!note) {
            note = document.createElement('small');
            note.className = 'da-policy-note-v4';
            note.dataset.field = input.name;
            input.insertAdjacentElement('afterend', note);
        }
        note.className = 'da-policy-note-v4 ' + (type || '');
        note.textContent = message || '';
        note.style.display = message ? 'block' : 'none';
    }

    function getModal() {
        let backdrop = document.getElementById('daPolicyDuplicateModalV4');
        if (backdrop) return backdrop;

        backdrop = document.createElement('div');
        backdrop.id = 'daPolicyDuplicateModalV4';
        backdrop.className = 'da-policy-modal-backdrop-v4';
        backdrop.innerHTML = `
            <div class="da-policy-modal-v4" role="dialog" aria-modal="true">
                <div class="da-policy-modal-head-v4"><strong>تنبيه: رقمالبوليصة موجود مسبقاً</strong></div>
                <div class="da-policy-modal-body-v4">
                    <div id="daPolicyMsgV4"></div>
                    <div id="daPolicyInfoV4" class="da-policy-modal-info-v4"></div>
                </div>
                <div class="da-policy-modal-actions-v4">
                    <button type="button" class="da-policy-yes-v4" id="daPolicyYesV4">نعم، مواصلة الإدراج</button>
                    <button type="button" class="da-policy-no-v4" id="daPolicyNoV4">لا، منع الإدراج</button>
                </div>
            </div>`;
        document.body.appendChild(backdrop);
        return backdrop;
    }

    async function askUser(label, value, data) {
        // يمنع فتح نافذتين في نفس اللحظة.
        while (globalModalPromise) {
            try { await globalModalPromise; } catch (e) {}
        }

        globalModalPromise = new Promise((resolve) => {
            const m = getModal();
            const doc = data.document || {};
            const msg = m.querySelector('#daPolicyMsgV4');
            const info = m.querySelector('#daPolicyInfoV4');
            const yes = m.querySelector('#daPolicyYesV4');
            const no = m.querySelector('#daPolicyNoV4');

            msg.innerHTML = `
                الرقمالمدخل في <strong>${escapeHtml(label)}</strong> موجود مسبقاً:<br>
                <strong style="direction:ltr;display:inline-block;font-size:18px">${escapeHtml(value)}</strong><br>
                هل تريد المواصلة وإدراج نفس رقمالبوليصة؟
            `;
            info.innerHTML = `
                <div><strong>رقمالكتاب السابق:</strong> ${escapeHtml(doc.reference_number || '-')}</div>
                <div><strong>تاريخ الكتاب:</strong> ${escapeHtml(doc.reference_date || '-')}</div>
                <div><strong>الموضوع:</strong> ${escapeHtml(doc.subject || doc.title || '-')}</div>
                <div><strong>البوليصة الرئيسية:</strong> ${escapeHtml(doc.main_policy_number || '-')}</div>
                <div><strong>البوليصة الفرعية:</strong> ${escapeHtml(doc.sub_policy_number || '-')}</div>
            `;

            m.style.display = 'flex';

            const cleanup = (answer) => {
                m.style.display = 'none';
                yes.removeEventListener('click', yesHandler);
                no.removeEventListener('click', noHandler);
                const resolved = resolve(answer);
                setTimeout(() => { globalModalPromise = null; }, 0);
                return resolved;
            };
            const yesHandler = () => cleanup(true);
            const noHandler = () => cleanup(false);
            yes.addEventListener('click', yesHandler, { once: true });
            no.addEventListener('click', noHandler, { once: true });
        });

        return await globalModalPromise;
    }

    async function checkField(input, field, options = {}) {
        if (!input) return true;

        const s = stateFor(input);
        const value = (input.value || '').trim();

        if (!value) {
            setNote(input, '', '');
            input.dataset.policyAllowedValue = '';
            s.pendingValue = null;
            s.pendingPromise = null;
            return true;
        }

        if (input.dataset.policyAllowedValue === value) return true;
        if (s.pendingPromise && s.pendingValue === value) return await s.pendingPromise;

        const run = (async () => {
            const params = new URLSearchParams({ field: field, value: value });
            if (currentDocumentId) params.set('document_id', currentDocumentId);

            try {
                const response = await fetch(checkUrl + '?' + params.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    setNote(input, 'تعذر فحص التكرار: مسار الفحص لميرجع JSON. نفّذ route:clear ثمأعد التجربة.', 'warning');
                    return true;
                }

                const data = await response.json();
                if ((input.value || '').trim() !== value) return true;

                if (!data.exists) {
                    setNote(input, '', '');
                    return true;
                }

                setNote(input, 'هذا الرقمموجود مسبقاً، الرجاء اختيار المواصلة أو المنع.', 'warning');
                const allow = await askUser(fieldConfig[field] || field, value, data);

                if (allow) {
                    input.dataset.policyAllowedValue = value;
                    setNote(input, 'تمالسماح بتكرار هذا الرقمبناءً على موافقتك.', 'ok');
                    return true;
                }

                input.dataset.policyAllowedValue = '';
                input.value = '';
                setNote(input, 'تممنع إدراج الرقمالمكرر.', 'warning');
                if (!options.noFocus) setTimeout(() => input.focus(), 40);
                return false;
            } catch (error) {
                console.warn('تعذر فحص تكرار البوليصة:', error);
                setNote(input, 'تعذر فحص تكرار البوليصة حالياً.', 'warning');
                return true;
            } finally {
                if (s.pendingValue === value) {
                    s.pendingValue = null;
                    s.pendingPromise = null;
                }
            }
        })();

        s.pendingValue = value;
        s.pendingPromise = run;
        return await run;
    }

    function attach(input, field) {
        if (!input || input.dataset.policyDuplicateV4Attached === '1') return;
        input.dataset.policyDuplicateV4Attached = '1';

        const s = stateFor(input);
        const schedule = () => {
            clearTimeout(s.timer);
            if ((input.value || '').trim() !== input.dataset.policyAllowedValue) {
                input.dataset.policyAllowedValue = '';
            }
            s.timer = setTimeout(() => checkField(input, field), 700);
        };

        input.addEventListener('input', schedule);
        input.addEventListener('change', schedule);
        input.addEventListener('paste', () => setTimeout(schedule, 80));
    }

    function boot() {
        const main = inputFor('main_policy_number');
        const sub = inputFor('sub_policy_number');
        attach(main, 'main_policy_number');
        attach(sub, 'sub_policy_number');

        const form = (main || sub)?.closest('form');
        if (form && form.dataset.policyDuplicateV4SubmitAttached !== '1') {
            form.dataset.policyDuplicateV4SubmitAttached = '1';
            form.addEventListener('submit', async function (event) {
                if (form.dataset.policySubmitting === '1') return;
                event.preventDefault();

                if (main) {
                    const okMain = await checkField(main, 'main_policy_number');
                    if (!okMain) return;
                }
                if (sub) {
                    const okSub = await checkField(sub, 'sub_policy_number');
                    if (!okSub) return;
                }

                form.dataset.policySubmitting = '1';
                HTMLFormElement.prototype.submit.call(form);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
</script>
<!-- DA_POLICY_DUPLICATE_WARNING_V4_END -->
@endsection


