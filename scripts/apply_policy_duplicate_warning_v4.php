<?php
/**
 * DocumentArchive - Policy Duplicate Warning V4 Arabic Modal Fix
 *
 * هذا التحديث يعالج:
 * - حذف مجلدات النسخ الاحتياطية القديمة داخل resources/views بأمان.
 * - إزالة بلوكات V1/V2/V3 القديمة من ملفات Blade النشطة فقط.
 * - حقن نافذة عربية مخصصة بدل confirm/alert الافتراضية في المتصفح.
 * - منع ظهور تنبيه تكرار البوليصة أكثر من مرة لنفس القيمة.
 */

$root = dirname(__DIR__);

function path_join_v4(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function ensure_dir_v4(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function rrmdir_v4(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = @scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            rrmdir_v4($path);
        } else {
            @chmod($path, 0666);
            @unlink($path);
        }
    }

    @chmod($dir, 0777);
    @rmdir($dir);
}

function collect_policy_backup_dirs_v4(string $dir, array &$found): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = @scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($path) || is_link($path)) {
            continue;
        }

        if (str_starts_with($item, '_backup_policy_duplicate')) {
            $found[] = $path;
            // لا ندخل إلى داخله؛ حذف المجلد الأب يحذف ما بداخله.
            continue;
        }

        collect_policy_backup_dirs_v4($path, $found);
    }
}

function backup_file_v4(string $root, string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $backupBase = path_join_v4($root, 'storage', 'app', 'private', 'update_backups', 'policy_duplicate_v4_' . date('Ymd_His'));
    ensure_dir_v4($backupBase);

    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, str_replace($root . DIRECTORY_SEPARATOR, '', $path));
    $target = $backupBase . DIRECTORY_SEPARATOR . $relative;
    ensure_dir_v4(dirname($target));
    copy($path, $target);
}

function is_inside_policy_backup_v4(string $path): bool
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return str_contains($normalized, DIRECTORY_SEPARATOR . '_backup_policy_duplicate');
}

function scan_active_blade_files_v4(string $viewsDir): array
{
    $files = [];

    $walk = function (string $dir) use (&$walk, &$files) {
        if (!is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && !is_link($path)) {
                if (str_starts_with($item, '_backup_policy_duplicate')) {
                    continue;
                }
                $walk($path);
                continue;
            }

            if (is_file($path) && str_ends_with($item, '.blade.php')) {
                $files[] = $path;
            }
        }
    };

    $walk($viewsDir);

    return $files;
}

function remove_policy_blocks_v4(string $content): string
{
    $patterns = [
        '/<!--\s*DA_POLICY_DUPLICATE_WARNING_START\s*-->.*?<!--\s*DA_POLICY_DUPLICATE_WARNING_END\s*-->/s',
        '/<!--\s*DA_POLICY_DUPLICATE_WARNING_V2_START\s*-->.*?<!--\s*DA_POLICY_DUPLICATE_WARNING_V2_END\s*-->/s',
        '/<!--\s*DA_POLICY_DUPLICATE_WARNING_V3_START\s*-->.*?<!--\s*DA_POLICY_DUPLICATE_WARNING_V3_END\s*-->/s',
        '/<!--\s*DA_POLICY_DUPLICATE_WARNING_V4_START\s*-->.*?<!--\s*DA_POLICY_DUPLICATE_WARNING_V4_END\s*-->/s',
    ];

    return preg_replace($patterns, '', $content);
}

function find_method_range_v4(string $content, string $methodName): ?array
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

function insert_method_before_class_end_v4(string $content, string $insert): string
{
    $pos = strrpos($content, "\n}");
    if ($pos === false) {
        return rtrim($content) . "\n\n" . $insert . "\n";
    }

    return substr($content, 0, $pos) . "\n" . $insert . "\n" . substr($content, $pos);
}

function remove_duplicate_route_v4(string $web): string
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

function insert_route_before_resource_v4(string $web, string $routeLine): string
{
    $lines = preg_split('/\R/', $web);
    $out = [];
    $inserted = false;

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

$webPath = path_join_v4($root, 'routes', 'web.php');
$controllerPath = path_join_v4($root, 'app', 'Http', 'Controllers', 'DocumentController.php');
$viewsDir = path_join_v4($root, 'resources', 'views');
$createViewPath = path_join_v4($root, 'resources', 'views', 'documents', 'create.blade.php');
$editViewPath = path_join_v4($root, 'resources', 'views', 'documents', 'edit.blade.php');

foreach ([$webPath, $controllerPath, $createViewPath, $editViewPath] as $path) {
    if (!file_exists($path)) {
        echo "ERROR: الملف غير موجود: {$path}\n";
        exit(1);
    }
}

// 1) حذف مجلدات backup القديمة بأمان قبل أي فحص/تعديل.
$backupDirs = [];
collect_policy_backup_dirs_v4($viewsDir, $backupDirs);
$backupDirs = array_values(array_unique($backupDirs));
// احذف الأطول أولاً احتياطياً، مع أن الجمع لا يدخل داخل backup.
usort($backupDirs, fn ($a, $b) => strlen($b) <=> strlen($a));
foreach ($backupDirs as $dir) {
    rrmdir_v4($dir);
}

// 2) نسخة احتياطية خارج resources/views حتى لا يلتقطها فحص Blade.
foreach ([$webPath, $controllerPath, $createViewPath, $editViewPath] as $path) {
    backup_file_v4($root, $path);
}

// 3) تنظيف كل ملفات Blade النشطة من بلوكات V1/V2/V3/V4 السابقة.
$changedViews = 0;
foreach (scan_active_blade_files_v4($viewsDir) as $bladePath) {
    $content = file_get_contents($bladePath);
    $clean = remove_policy_blocks_v4($content);
    if ($clean !== $content) {
        backup_file_v4($root, $bladePath);
        file_put_contents($bladePath, trim($clean) . "\n");
        $changedViews++;
    }
}

// 4) Route قبل resource.
$web = file_get_contents($webPath);
$web = remove_duplicate_route_v4($web);
$routeLine = "Route::get('/documents/check-policy-duplicate', [DocumentController::class, 'checkPolicyDuplicate'])->name('documents.check-policy-duplicate');";
$web = insert_route_before_resource_v4($web, $routeLine);
file_put_contents($webPath, $web);

// 5) Controller method آمن وبـ FQCN حتى لا يعتمد على import.
$method = <<<'PHP_METHOD'
    /**
     * فحص تكرار رقم البوليصة الرئيسية/الفرعية أثناء الإدخال.
     * لا يمنع التكرار من قاعدة البيانات؛ الواجهة تسأل المستخدم: نعم/لا.
     */
    public function checkPolicyDuplicate(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'field' => ['nullable', 'in:main_policy_number,sub_policy_number'],
            'value' => ['required', 'string', 'max:255'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $field = $validated['field'] ?? null;
        $value = trim((string) $validated['value']);

        if ($value === '') {
            return response()->json(['exists' => false, 'message' => null, 'document' => null]);
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('documents')) {
                return response()->json(['exists' => false, 'message' => null, 'document' => null]);
            }

            $hasMain = \Illuminate\Support\Facades\Schema::hasColumn('documents', 'main_policy_number');
            $hasSub = \Illuminate\Support\Facades\Schema::hasColumn('documents', 'sub_policy_number');

            if (!$hasMain && !$hasSub) {
                return response()->json(['exists' => false, 'message' => null, 'document' => null]);
            }

            $query = \App\Models\Document::query();

            if (!empty($validated['document_id'])) {
                $query->where('id', '<>', (int) $validated['document_id']);
            }

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
                return response()->json(['exists' => false, 'message' => null, 'document' => null]);
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
$range = find_method_range_v4($controller, 'checkPolicyDuplicate');
if ($range !== null) {
    $controller = substr($controller, 0, $range[0]) . $method . substr($controller, $range[1]);
} else {
    $controller = insert_method_before_class_end_v4($controller, $method);
}
file_put_contents($controllerPath, $controller);

// 6) سكربت V4: نافذة عربية مخصصة، بدون confirm/alert، مع منع التكرار.
$script = <<<'BLADE'
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
                <div class="da-policy-modal-head-v4"><strong>تنبيه: رقم البوليصة موجود مسبقاً</strong></div>
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
                الرقم المدخل في <strong>${escapeHtml(label)}</strong> موجود مسبقاً:<br>
                <strong style="direction:ltr;display:inline-block;font-size:18px">${escapeHtml(value)}</strong><br>
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
                    setNote(input, 'تعذر فحص التكرار: مسار الفحص لم يرجع JSON. نفّذ route:clear ثم أعد التجربة.', 'warning');
                    return true;
                }

                const data = await response.json();
                if ((input.value || '').trim() !== value) return true;

                if (!data.exists) {
                    setNote(input, '', '');
                    return true;
                }

                setNote(input, 'هذا الرقم موجود مسبقاً، الرجاء اختيار المواصلة أو المنع.', 'warning');
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
BLADE;

function inject_v4_into_view(string $path, string $script): void
{
    $content = file_get_contents($path);
    $content = remove_policy_blocks_v4($content);

    $pos = strrpos($content, '@endsection');
    if ($pos !== false) {
        $content = substr($content, 0, $pos) . "\n" . $script . "\n" . substr($content, $pos);
    } else {
        $content = rtrim($content) . "\n" . $script . "\n";
    }

    file_put_contents($path, $content);
}

inject_v4_into_view($createViewPath, $script);
inject_v4_into_view($editViewPath, $script);

passthru('php -l ' . escapeshellarg($controllerPath), $controllerCode);
if ($controllerCode !== 0) {
    echo "ERROR: يوجد خطأ نحوي في DocumentController.php بعد التحديث.\n";
    exit(1);
}

passthru('php -l ' . escapeshellarg($webPath), $routeCode);
if ($routeCode !== 0) {
    echo "ERROR: يوجد خطأ نحوي في routes/web.php بعد التحديث.\n";
    exit(1);
}

echo "OK: تم تركيب إصلاح V4 لتنبيه تكرار البوليصة.\n";
echo "تم حذف مجلدات backup القديمة داخل resources/views: " . count($backupDirs) . "\n";
echo "تم تنظيف ملفات Blade النشطة من البلوكات القديمة: {$changedViews}\n";
echo "تم اعتماد نافذة عربية مخصصة بدلاً من رسائل المتصفح الإنجليزية.\n";
