<?php
/**
 * DocumentArchive - Policy Duplicate Warning V2
 * إصلاح جذري لتنبيه تكرار البوليصة الرئيسية/الفرعية.
 * - يثبت المسار قبل Route::resource('documents') حتى لا يلتقطه show.
 * - يستبدل/يضيف دالة checkPolicyDuplicate داخل DocumentController.
 * - يحقن سكربت الفحص مباشرة داخل create/edit حتى لا يعتمد على layout أو stack.
 */

$root = dirname(__DIR__);

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function backup_file_if_exists(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $backupDir = dirname($path) . DIRECTORY_SEPARATOR . '_backup_policy_duplicate_v2_' . date('Ymd_His');
    ensure_dir($backupDir);
    copy($path, $backupDir . DIRECTORY_SEPARATOR . basename($path));
}

function find_method_range(string $content, string $methodName): ?array
{
    $pattern = '/public\s+function\s+' . preg_quote($methodName, '/') . '\s*\(/';
    if (!preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $start = $matches[0][1];
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        return null;
    }

    $depth = 0;
    $length = strlen($content);
    for ($i = $brace; $i < $length; $i++) {
        $char = $content[$i];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return [$start, $i + 1];
            }
        }
    }

    return null;
}

function insert_before_first_private_method_or_class_end(string $content, string $insert): string
{
    $privatePos = strpos($content, "\n    private function ");
    if ($privatePos !== false) {
        return substr($content, 0, $privatePos) . "\n" . $insert . "\n" . substr($content, $privatePos);
    }

    $protectedPos = strpos($content, "\n    protected function ");
    if ($protectedPos !== false) {
        return substr($content, 0, $protectedPos) . "\n" . $insert . "\n" . substr($content, $protectedPos);
    }

    $pos = strrpos($content, "\n}");
    if ($pos === false) {
        return rtrim($content) . "\n\n" . $insert . "\n";
    }

    return substr($content, 0, $pos) . "\n" . $insert . "\n" . substr($content, $pos);
}

function remove_existing_duplicate_route(string $web): string
{
    $lines = preg_split('/\R/', $web);
    $out = [];
    $skip = false;

    foreach ($lines as $line) {
        if ($skip) {
            if (strpos($line, ';') !== false) {
                $skip = false;
            }
            continue;
        }

        if (strpos($line, '/documents/check-policy-duplicate') !== false || strpos($line, 'documents.check-policy-duplicate') !== false) {
            if (strpos($line, ';') === false) {
                $skip = true;
            }
            continue;
        }

        $out[] = $line;
    }

    return implode("\n", $out);
}

function insert_route_before_documents_resource(string $web, string $routeLine): string
{
    $lines = preg_split('/\R/', $web);
    $inserted = false;
    $out = [];

    foreach ($lines as $line) {
        if (!$inserted && preg_match('/Route::resource\s*\(\s*[\'\"]documents[\'\"]/', $line)) {
            $indent = preg_match('/^(\s*)/', $line, $m) ? $m[1] : '    ';
            $out[] = $indent . $routeLine;
            $inserted = true;
        }
        $out[] = $line;
    }

    if (!$inserted) {
        $out[] = '';
        $out[] = $routeLine;
    }

    return implode("\n", $out);
}

$webPath = $root . '/routes/web.php';
$controllerPath = $root . '/app/Http/Controllers/DocumentController.php';
$createViewPath = $root . '/resources/views/documents/create.blade.php';
$editViewPath = $root . '/resources/views/documents/edit.blade.php';

foreach ([$webPath, $controllerPath] as $requiredPath) {
    if (!file_exists($requiredPath)) {
        echo "ERROR: الملف غير موجود: {$requiredPath}\n";
        exit(1);
    }
}

backup_file_if_exists($webPath);
backup_file_if_exists($controllerPath);
backup_file_if_exists($createViewPath);
backup_file_if_exists($editViewPath);

// 1) Route before resource.
$web = file_get_contents($webPath);
$web = remove_existing_duplicate_route($web);
$routeLine = "Route::get('/documents/check-policy-duplicate', [DocumentController::class, 'checkPolicyDuplicate'])->name('documents.check-policy-duplicate');";
$web = insert_route_before_documents_resource($web, $routeLine);
file_put_contents($webPath, $web);

// 2) Controller method: replace or insert.
$method = <<<'PHP_METHOD'
    /**
     * فحص تكرار رقم البوليصة الرئيسية/الفرعية أثناء الإدخال.
     * ملاحظة: لا يمنع التكرار في قاعدة البيانات، بل يعطي الواجهة قرار نعم/لا للمستخدم.
     */
    public function checkPolicyDuplicate(Request $request)
    {
        $validated = $request->validate([
            'field' => ['nullable', 'in:main_policy_number,sub_policy_number'],
            'value' => ['required', 'string', 'max:255'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $field = $validated['field'] ?? null;
        $value = trim((string) $validated['value']);

        if ($value === '') {
            return response()->json([
                'exists' => false,
                'message' => null,
                'document' => null,
            ]);
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('documents')) {
                return response()->json([
                    'exists' => false,
                    'message' => null,
                    'document' => null,
                ]);
            }

            $hasMain = \Illuminate\Support\Facades\Schema::hasColumn('documents', 'main_policy_number');
            $hasSub = \Illuminate\Support\Facades\Schema::hasColumn('documents', 'sub_policy_number');

            if (!$hasMain && !$hasSub) {
                return response()->json([
                    'exists' => false,
                    'message' => null,
                    'document' => null,
                ]);
            }

            $query = Document::query();

            if (!empty($validated['document_id'])) {
                $query->where('id', '<>', (int) $validated['document_id']);
            }

            // نفحص الرقم في الحقلين معاً حتى لو أُدخل كرئيسية وكان موجوداً سابقاً كفرعية أو العكس.
            $query->where(function ($q) use ($value, $hasMain, $hasSub) {
                if ($hasMain) {
                    $q->orWhereRaw("TRIM(COALESCE(main_policy_number, '')) = ?", [$value]);
                }
                if ($hasSub) {
                    $q->orWhereRaw("TRIM(COALESCE(sub_policy_number, '')) = ?", [$value]);
                }
            });

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

            $matchedField = null;
            if ($hasMain && trim((string) ($document->main_policy_number ?? '')) === $value) {
                $matchedField = 'main_policy_number';
            } elseif ($hasSub && trim((string) ($document->sub_policy_number ?? '')) === $value) {
                $matchedField = 'sub_policy_number';
            }

            $inputLabel = $field === 'sub_policy_number' ? 'البوليصة الفرعية' : 'البوليصة الرئيسية';
            $matchedLabel = $matchedField === 'sub_policy_number' ? 'البوليصة الفرعية' : 'البوليصة الرئيسية';

            return response()->json([
                'exists' => true,
                'message' => "رقم {$inputLabel} موجود مسبقاً في {$matchedLabel}.",
                'matched_field' => $matchedField,
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

$controller = file_get_contents($controllerPath);
$range = find_method_range($controller, 'checkPolicyDuplicate');
if ($range !== null) {
    $controller = substr($controller, 0, $range[0]) . $method . substr($controller, $range[1]);
} else {
    $controller = insert_before_first_private_method_or_class_end($controller, $method);
}
file_put_contents($controllerPath, $controller);

// 3) Inline script into create/edit views, not layout.
$markerStart = '<!-- DA_POLICY_DUPLICATE_WARNING_V2_START -->';
$markerEnd = '<!-- DA_POLICY_DUPLICATE_WARNING_V2_END -->';

$script = <<<'BLADE'
<!-- DA_POLICY_DUPLICATE_WARNING_V2_START -->
<style>
    .da-policy-note-v2 { display: block; margin-top: 7px; font-size: 12px; font-weight: 850; line-height: 1.7; }
    .da-policy-note-v2.warning { color: #fbbf24; }
    .da-policy-note-v2.ok { color: #34d399; }
    .da-policy-modal-backdrop-v2 {
        position: fixed; inset: 0; z-index: 999999; display: none; align-items: center; justify-content: center;
        background: rgba(2, 6, 23, .72); backdrop-filter: blur(8px); padding: 18px; direction: rtl;
    }
    .da-policy-modal-v2 { width: min(560px, 100%); background: #0f172a; border: 1px solid rgba(245, 158, 11, .55); border-radius: 22px; box-shadow: 0 24px 80px rgba(0,0,0,.40); color: #f8fafc; overflow: hidden; }
    .da-policy-modal-head-v2 { padding: 18px 20px; background: rgba(245, 158, 11, .14); border-bottom: 1px solid rgba(245, 158, 11, .26); }
    .da-policy-modal-head-v2 strong { display: block; font-size: 20px; font-weight: 950; }
    .da-policy-modal-body-v2 { padding: 18px 20px; line-height: 1.9; color: #e5e7eb; font-weight: 780; }
    .da-policy-modal-info-v2 { margin-top: 12px; padding: 12px; background: rgba(15, 23, 42, .84); border: 1px solid rgba(148, 163, 184, .22); border-radius: 14px; color: #cbd5e1; font-size: 13px; }
    .da-policy-modal-actions-v2 { display: flex; gap: 10px; justify-content: flex-start; padding: 0 20px 18px; flex-wrap: wrap; }
    .da-policy-modal-actions-v2 button { border: 0; border-radius: 12px; padding: 10px 16px; cursor: pointer; font-weight: 950; color: #fff; }
    .da-policy-yes-v2 { background: #2563eb; }
    .da-policy-no-v2 { background: #dc2626; }
</style>
<script>
(function () {
    const checkUrl = @json(route('documents.check-policy-duplicate'));
    const currentDocumentId = @json(isset($document) ? ($document->id ?? null) : null);
    const fieldConfig = {
        main_policy_number: 'البوليصة الرئيسية',
        sub_policy_number: 'البوليصة الفرعية'
    };

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
        let note = input.parentElement.querySelector('.da-policy-note-v2[data-field="' + input.name + '"]');
        if (!note) {
            note = document.createElement('small');
            note.className = 'da-policy-note-v2';
            note.dataset.field = input.name;
            input.insertAdjacentElement('afterend', note);
        }
        note.className = 'da-policy-note-v2 ' + (type || '');
        note.textContent = message || '';
        note.style.display = message ? 'block' : 'none';
    }

    function modal() {
        let backdrop = document.getElementById('daPolicyDuplicateModalV2');
        if (backdrop) return backdrop;

        backdrop = document.createElement('div');
        backdrop.id = 'daPolicyDuplicateModalV2';
        backdrop.className = 'da-policy-modal-backdrop-v2';
        backdrop.innerHTML = `
            <div class="da-policy-modal-v2" role="dialog" aria-modal="true">
                <div class="da-policy-modal-head-v2"><strong>تنبيه تكرار البوليصة</strong></div>
                <div class="da-policy-modal-body-v2">
                    <div id="daPolicyMsgV2"></div>
                    <div id="daPolicyInfoV2" class="da-policy-modal-info-v2"></div>
                </div>
                <div class="da-policy-modal-actions-v2">
                    <button type="button" class="da-policy-yes-v2" id="daPolicyYesV2">نعم، مواصلة</button>
                    <button type="button" class="da-policy-no-v2" id="daPolicyNoV2">لا، منع</button>
                </div>
            </div>`;
        document.body.appendChild(backdrop);
        return backdrop;
    }

    function askUser(label, value, data) {
        return new Promise((resolve) => {
            const m = modal();
            const doc = data.document || {};
            const msg = m.querySelector('#daPolicyMsgV2');
            const info = m.querySelector('#daPolicyInfoV2');
            const yes = m.querySelector('#daPolicyYesV2');
            const no = m.querySelector('#daPolicyNoV2');

            msg.innerHTML = `
                رقم <strong>${escapeHtml(label)}</strong> التالي موجود مسبقاً:<br>
                <strong style="direction:ltr;display:inline-block">${escapeHtml(value)}</strong><br>
                هل تريد المواصلة وإدراج نفس رقم البوليصة؟
            `;
            info.innerHTML = `
                <div><strong>رقم الكتاب السابق:</strong> ${escapeHtml(doc.reference_number || '-')}</div>
                <div><strong>تاريخ الكتاب:</strong> ${escapeHtml(doc.reference_date || '-')}</div>
                <div><strong>الموضوع:</strong> ${escapeHtml(doc.subject || doc.title || '-')}</div>
                <div><strong>البوليصة الرئيسية:</strong> ${escapeHtml(doc.main_policy_number || '-')}</div>
                <div><strong>البوليصة الفرعية:</strong> ${escapeHtml(doc.sub_policy_number || '-')}</div>
            `;

            m.style.display = 'flex';

            const done = (answer) => {
                m.style.display = 'none';
                yes.removeEventListener('click', yesHandler);
                no.removeEventListener('click', noHandler);
                resolve(answer);
            };
            const yesHandler = () => done(true);
            const noHandler = () => done(false);
            yes.addEventListener('click', yesHandler);
            no.addEventListener('click', noHandler);
        });
    }

    async function checkField(input, field, options = {}) {
        const value = (input.value || '').trim();
        if (!value) {
            setNote(input, '', '');
            input.dataset.policyAllowedValue = '';
            return true;
        }

        if (input.dataset.policyAllowedValue === value) {
            return true;
        }

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
                setNote(input, 'تعذر فحص التكرار: المسار لا يرجع JSON. نفّذ route:clear ثم أعد التجربة.', 'warning');
                return true;
            }

            const data = await response.json();
            if ((input.value || '').trim() !== value) return true;

            if (!data.exists) {
                setNote(input, '', '');
                return true;
            }

            setNote(input, 'هذا الرقم موجود مسبقاً، يرجى اختيار المواصلة أو المنع.', 'warning');
            const allow = await askUser(fieldConfig[field] || field, value, data);

            if (allow) {
                input.dataset.policyAllowedValue = value;
                setNote(input, 'تم السماح بتكرار هذا الرقم بناءً على موافقتك.', 'ok');
                return true;
            }

            input.dataset.policyAllowedValue = '';
            input.value = '';
            setNote(input, 'تم منع إدراج الرقم المكرر.', 'warning');
            if (!options.noFocus) setTimeout(() => input.focus(), 40);
            return false;
        } catch (error) {
            console.warn('Policy duplicate check failed:', error);
            setNote(input, 'تعذر فحص تكرار البوليصة حالياً.', 'warning');
            return true;
        }
    }

    function attach(input, field) {
        if (!input) return;
        let timer = null;
        const schedule = () => {
            clearTimeout(timer);
            if ((input.value || '').trim() !== input.dataset.policyAllowedValue) input.dataset.policyAllowedValue = '';
            timer = setTimeout(() => checkField(input, field), 450);
        };
        input.addEventListener('input', schedule);
        input.addEventListener('change', schedule);
        input.addEventListener('paste', () => setTimeout(schedule, 50));
        input.addEventListener('blur', () => {
            clearTimeout(timer);
            checkField(input, field);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const main = inputFor('main_policy_number');
        const sub = inputFor('sub_policy_number');
        attach(main, 'main_policy_number');
        attach(sub, 'sub_policy_number');

        const form = (main || sub)?.closest('form');
        if (form) {
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
    });
})();
</script>
<!-- DA_POLICY_DUPLICATE_WARNING_V2_END -->
BLADE;

function inject_script_into_view(string $path, string $script, string $markerStart, string $markerEnd): void
{
    if (!file_exists($path)) {
        return;
    }

    $content = file_get_contents($path);

    // Remove old v1/v2 script blocks if present.
    $content = preg_replace('/<!--\s*DA_POLICY_DUPLICATE_WARNING_START\s*-->.*?<!--\s*DA_POLICY_DUPLICATE_WARNING_END\s*-->/s', '', $content);
    $content = preg_replace('/<!--\s*DA_POLICY_DUPLICATE_WARNING_V2_START\s*-->.*?<!--\s*DA_POLICY_DUPLICATE_WARNING_V2_END\s*-->/s', '', $content);

    $pos = strrpos($content, '@endsection');
    if ($pos !== false) {
        $content = substr($content, 0, $pos) . "\n" . $script . "\n" . substr($content, $pos);
    } else {
        $content = rtrim($content) . "\n" . $script . "\n";
    }

    file_put_contents($path, $content);
}

inject_script_into_view($createViewPath, $script, $markerStart, $markerEnd);
inject_script_into_view($editViewPath, $script, $markerStart, $markerEnd);

// 4) Syntax checks.
passthru('php -l ' . escapeshellarg($controllerPath), $controllerCode);
if ($controllerCode !== 0) {
    echo "ERROR: يوجد خطأ نحوي في DocumentController.php بعد التحديث. راجع النسخة الاحتياطية.\n";
    exit(1);
}

passthru('php -l ' . escapeshellarg($webPath), $routeCode);
if ($routeCode !== 0) {
    echo "ERROR: يوجد خطأ نحوي في routes/web.php بعد التحديث. راجع النسخة الاحتياطية.\n";
    exit(1);
}

echo "OK: تم تركيب إصلاح تنبيه تكرار البوليصة V2 بنجاح.\n";
echo "مهم: نفذ php artisan route:clear و php artisan view:clear ثم أعد التجربة.\n";
