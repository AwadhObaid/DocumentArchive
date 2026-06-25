<?php
/**
 * DocumentArchive - Policy Duplicate Warning Update
 * يضيف فحص تكرار رقم البوليصة الرئيسية/الفرعية أثناء الكتابة مع خيار نعم/لا للمواصلة.
 */

$root = dirname(__DIR__);

function backup_file_if_exists(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $backupDir = dirname($path) . DIRECTORY_SEPARATOR . '_backup_policy_duplicate_warning_' . date('Ymd_His');
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    copy($path, $backupDir . DIRECTORY_SEPARATOR . basename($path));
}

function insert_before_last_class_brace(string $content, string $insert): string
{
    $pos = strrpos($content, "\n}");
    if ($pos === false) {
        return rtrim($content) . "\n\n" . $insert . "\n";
    }

    return substr($content, 0, $pos) . "\n" . $insert . "\n" . substr($content, $pos);
}

function insert_before_first_private_method_or_class_end(string $content, string $insert): string
{
    $privatePos = strpos($content, "\n    private function ");
    if ($privatePos !== false) {
        return substr($content, 0, $privatePos) . "\n" . $insert . "\n" . substr($content, $privatePos);
    }

    return insert_before_last_class_brace($content, $insert);
}

$webPath = $root . '/routes/web.php';
$controllerPath = $root . '/app/Http/Controllers/DocumentController.php';
$layoutPath = $root . '/resources/views/layouts/app.blade.php';

foreach ([$webPath, $controllerPath, $layoutPath] as $requiredPath) {
    if (!file_exists($requiredPath)) {
        echo "ERROR: الملف غير موجود: {$requiredPath}\n";
        exit(1);
    }
}

backup_file_if_exists($webPath);
backup_file_if_exists($controllerPath);
backup_file_if_exists($layoutPath);

// 1) Route: يجب أن يكون قبل Route::resource('documents') حتى لا يلتقطه مسار show.
$web = file_get_contents($webPath);
$routeBlock = <<<'PHP_ROUTE'
    Route::get('/documents/check-policy-duplicate', [DocumentController::class, 'checkPolicyDuplicate'])
        ->name('documents.check-policy-duplicate');

PHP_ROUTE;

if (strpos($web, "documents.check-policy-duplicate") === false) {
    $needle = "    Route::resource('documents', DocumentController::class);";
    if (strpos($web, $needle) !== false) {
        $web = str_replace($needle, $routeBlock . $needle, $web);
    } else {
        // احتياطي: إدراج قبل نهاية أول مجموعة auth إن أمكن.
        $web = rtrim($web) . "\n\n" . trim($routeBlock) . "\n";
    }
    file_put_contents($webPath, $web);
}

// 2) Controller method.
$controller = file_get_contents($controllerPath);
$method = <<<'PHP_METHOD'
    /**
     * فحص تكرار رقم البوليصة الرئيسية أو الفرعية أثناء إدخال بيانات الكتاب.
     * لا يمنع التكرار من قاعدة البيانات؛ فقط يعيد نتيجة واضحة للواجهة لتطلب موافقة المستخدم.
     */
    public function checkPolicyDuplicate(Request $request)
    {
        $validated = $request->validate([
            'field' => ['required', 'in:main_policy_number,sub_policy_number'],
            'value' => ['required', 'string', 'max:255'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $field = $validated['field'];
        $value = trim((string) $validated['value']);

        if ($value === '') {
            return response()->json([
                'exists' => false,
                'message' => null,
                'document' => null,
            ]);
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('documents') || !\Illuminate\Support\Facades\Schema::hasColumn('documents', $field)) {
                return response()->json([
                    'exists' => false,
                    'message' => null,
                    'document' => null,
                ]);
            }

            $query = Document::query()->where($field, $value);

            if (!empty($validated['document_id'])) {
                $query->where('id', '<>', (int) $validated['document_id']);
            }

            $document = $query->orderByDesc('id')->first();

            if (!$document) {
                return response()->json([
                    'exists' => false,
                    'message' => null,
                    'document' => null,
                ]);
            }

            $referenceDate = null;
            if (!empty($document->reference_date)) {
                try {
                    $referenceDate = \Illuminate\Support\Carbon::parse($document->reference_date)->format('d/m/Y');
                } catch (\Throwable $dateException) {
                    $referenceDate = (string) $document->reference_date;
                }
            }

            $label = $field === 'main_policy_number' ? 'البوليصة الرئيسية' : 'البوليصة الفرعية';

            return response()->json([
                'exists' => true,
                'message' => "رقم {$label} موجود مسبقاً.",
                'document' => [
                    'id' => $document->id,
                    'reference_number' => $document->reference_number ?? null,
                    'reference_date' => $referenceDate,
                    'title' => $document->title ?? null,
                    'subject' => $document->subject ?? null,
                    'main_policy_number' => $document->main_policy_number ?? null,
                    'sub_policy_number' => $document->sub_policy_number ?? null,
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'exists' => false,
                'message' => 'تعذر فحص تكرار البوليصة حالياً.',
                'document' => null,
            ], 200);
        }
    }
PHP_METHOD;

if (strpos($controller, 'function checkPolicyDuplicate') === false) {
    $controller = insert_before_first_private_method_or_class_end($controller, $method);
    file_put_contents($controllerPath, $controller);
}

// 3) Global UI script in app layout.
$layout = file_get_contents($layoutPath);
$markerStart = '<!-- DA_POLICY_DUPLICATE_WARNING_START -->';
$markerEnd = '<!-- DA_POLICY_DUPLICATE_WARNING_END -->';

$uiBlock = <<<'BLADE'
<!-- DA_POLICY_DUPLICATE_WARNING_START -->
<style>
    .da-policy-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(2, 6, 23, .72);
        backdrop-filter: blur(8px);
        padding: 18px;
        direction: rtl;
    }
    .da-policy-modal {
        width: min(520px, 100%);
        background: #0f172a;
        border: 1px solid rgba(245, 158, 11, .45);
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(0,0,0,.38);
        color: #f8fafc;
        overflow: hidden;
    }
    .da-policy-modal-header {
        padding: 18px 20px;
        background: rgba(245, 158, 11, .13);
        border-bottom: 1px solid rgba(245, 158, 11, .25);
    }
    .da-policy-modal-header strong {
        display: block;
        font-size: 20px;
        font-weight: 950;
    }
    .da-policy-modal-body {
        padding: 18px 20px;
        line-height: 1.9;
        color: #e5e7eb;
        font-weight: 750;
    }
    .da-policy-modal-info {
        margin-top: 12px;
        padding: 12px;
        background: rgba(15, 23, 42, .82);
        border: 1px solid rgba(148, 163, 184, .20);
        border-radius: 14px;
        color: #cbd5e1;
        font-size: 13px;
    }
    .da-policy-modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-start;
        padding: 0 20px 18px;
        flex-wrap: wrap;
    }
    .da-policy-modal-actions button {
        border: 0;
        border-radius: 12px;
        padding: 10px 16px;
        cursor: pointer;
        font-weight: 950;
        color: #fff;
    }
    .da-policy-yes { background: #2563eb; }
    .da-policy-no { background: #dc2626; }
    .da-policy-inline-note {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        font-weight: 850;
        line-height: 1.6;
    }
    .da-policy-inline-note.warning { color: #fbbf24; }
    .da-policy-inline-note.ok { color: #34d399; }
</style>
<script>
(function () {
    const checkUrl = @json(route('documents.check-policy-duplicate'));
    const editMatch = window.location.pathname.match(/\/documents\/(\d+)\/edit/);
    const currentDocumentId = editMatch ? editMatch[1] : '';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function ensureModal() {
        let backdrop = document.getElementById('daPolicyDuplicateModal');
        if (backdrop) {
            return backdrop;
        }

        backdrop = document.createElement('div');
        backdrop.id = 'daPolicyDuplicateModal';
        backdrop.className = 'da-policy-modal-backdrop';
        backdrop.innerHTML = `
            <div class="da-policy-modal" role="dialog" aria-modal="true" aria-labelledby="daPolicyModalTitle">
                <div class="da-policy-modal-header">
                    <strong id="daPolicyModalTitle">تنبيه تكرار البوليصة</strong>
                </div>
                <div class="da-policy-modal-body">
                    <div id="daPolicyModalMessage"></div>
                    <div id="daPolicyModalInfo" class="da-policy-modal-info"></div>
                </div>
                <div class="da-policy-modal-actions">
                    <button type="button" class="da-policy-yes" id="daPolicyModalYes">نعم، مواصلة</button>
                    <button type="button" class="da-policy-no" id="daPolicyModalNo">لا، منع</button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);
        return backdrop;
    }

    function showPolicyModal(label, value, duplicateDocument) {
        return new Promise((resolve) => {
            const modal = ensureModal();
            const message = modal.querySelector('#daPolicyModalMessage');
            const info = modal.querySelector('#daPolicyModalInfo');
            const yes = modal.querySelector('#daPolicyModalYes');
            const no = modal.querySelector('#daPolicyModalNo');

            const referenceNumber = duplicateDocument?.reference_number || '-';
            const referenceDate = duplicateDocument?.reference_date || '-';
            const subject = duplicateDocument?.subject || duplicateDocument?.title || '-';

            message.innerHTML = `
                رقم <strong>${label}</strong> التالي موجود مسبقاً:<br>
                <strong style="direction:ltr;display:inline-block">${escapeHtml(value)}</strong><br>
                هل تريد المواصلة وإدراج نفس رقم البوليصة؟
            `;
            info.innerHTML = `
                <div><strong>رقم الكتاب السابق:</strong> ${escapeHtml(referenceNumber)}</div>
                <div><strong>تاريخ الكتاب:</strong> ${escapeHtml(referenceDate)}</div>
                <div><strong>الموضوع:</strong> ${escapeHtml(subject)}</div>
            `;

            modal.style.display = 'flex';

            const cleanup = (answer) => {
                modal.style.display = 'none';
                yes.removeEventListener('click', onYes);
                no.removeEventListener('click', onNo);
                resolve(answer);
            };

            const onYes = () => cleanup(true);
            const onNo = () => cleanup(false);

            yes.addEventListener('click', onYes);
            no.addEventListener('click', onNo);
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function setNote(input, message, type) {
        if (!input) return;
        let note = input.parentElement?.querySelector('.da-policy-inline-note[data-policy-note="' + input.name + '"]');
        if (!note) {
            note = document.createElement('small');
            note.className = 'da-policy-inline-note';
            note.dataset.policyNote = input.name;
            input.insertAdjacentElement('afterend', note);
        }
        note.className = 'da-policy-inline-note ' + (type || '');
        note.textContent = message || '';
        note.style.display = message ? 'block' : 'none';
    }

    async function checkDuplicate(input, field, label) {
        const value = (input.value || '').trim();
        if (value.length < 2) {
            setNote(input, '', '');
            input.dataset.policyAllowedValue = '';
            return;
        }

        if (input.dataset.policyAllowedValue === value || input.dataset.policyCheckingValue === value) {
            return;
        }

        input.dataset.policyCheckingValue = value;

        try {
            const params = new URLSearchParams({ field, value });
            if (currentDocumentId) {
                params.set('document_id', currentDocumentId);
            }

            const response = await fetch(checkUrl + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            input.dataset.policyCheckingValue = '';

            // إذا تغيّرت القيمة أثناء الفحص، تجاهل النتيجة القديمة.
            if ((input.value || '').trim() !== value) {
                return;
            }

            if (!data.exists) {
                setNote(input, '', '');
                return;
            }

            setNote(input, 'تنبيه: هذا الرقم موجود مسبقاً، يرجى تحديد المتابعة أو المنع.', 'warning');
            const allow = await showPolicyModal(label, value, data.document || {});

            if (allow) {
                input.dataset.policyAllowedValue = value;
                setNote(input, 'تم السماح بتكرار هذه البوليصة بناءً على موافقتك.', 'ok');
            } else {
                input.dataset.policyAllowedValue = '';
                input.value = '';
                setNote(input, 'تم منع إدراج الرقم المكرر.', 'warning');
                setTimeout(() => input.focus(), 50);
            }
        } catch (error) {
            input.dataset.policyCheckingValue = '';
            console.warn('Policy duplicate check failed:', error);
        }
    }

    function attachPolicyWatcher(name, field, label) {
        const input = document.querySelector('[name="' + name + '"]');
        if (!input) return;

        let timer = null;
        const run = () => {
            clearTimeout(timer);
            timer = setTimeout(() => checkDuplicate(input, field, label), 650);
        };

        input.addEventListener('input', function () {
            if ((input.value || '').trim() !== input.dataset.policyAllowedValue) {
                input.dataset.policyAllowedValue = '';
            }
            run();
        });

        input.addEventListener('blur', function () {
            clearTimeout(timer);
            checkDuplicate(input, field, label);
        });
    }

    ready(function () {
        attachPolicyWatcher('main_policy_number', 'main_policy_number', 'البوليصة الرئيسية');
        attachPolicyWatcher('sub_policy_number', 'sub_policy_number', 'البوليصة الفرعية');
    });
})();
</script>
<!-- DA_POLICY_DUPLICATE_WARNING_END -->
BLADE;

if (strpos($layout, $markerStart) === false) {
    if (stripos($layout, '</body>') !== false) {
        $layout = preg_replace('/<\/body>/i', $uiBlock . "\n</body>", $layout, 1);
    } else {
        $layout = rtrim($layout) . "\n" . $uiBlock . "\n";
    }
    file_put_contents($layoutPath, $layout);
}

echo "OK: تم تركيب فحص تكرار البوليصة الرئيسية والفرعية أثناء الكتابة.\n";
echo "ملاحظة: يتم تجاهل نفس الكتاب في صفحة التعديل حتى لا يظهر تنبيه على بياناته الحالية.\n";
