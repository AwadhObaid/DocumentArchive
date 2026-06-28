<?php
/**
 * DocumentArchive - Popup Notifications UI Update
 * يضيف إشعارات منبثقة عامة للنظام بدون مكتبات خارجية.
 */

$base = dirname(__DIR__);

function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function put_file(string $path, string $content): void
{
    ensure_dir(dirname($path));
    file_put_contents($path, $content);
    echo "WRITE: {$path}\n";
}

function inject_before_once(string $path, string $needle, string $insert, string $marker): bool
{
    if (!file_exists($path)) {
        echo "SKIP: {$path} غير موجود.\n";
        return false;
    }

    $content = file_get_contents($path);
    if (strpos($content, $marker) !== false) {
        echo "OK: {$marker} موجود مسبقاً في {$path}\n";
        return true;
    }

    if (stripos($content, $needle) !== false) {
        $content = preg_replace('/' . preg_quote($needle, '/') . '/i', $insert . "\n" . $needle, $content, 1);
    } else {
        $content .= "\n" . $insert . "\n";
    }

    file_put_contents($path, $content);
    echo "PATCH: {$path}\n";
    return true;
}

$css = <<<'CSS'
/* DocumentArchive popup notifications */
.app-toast-container {
    position: fixed;
    inset-inline-start: 24px;
    bottom: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: min(420px, calc(100vw - 32px));
    pointer-events: none;
}

.app-toast {
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    direction: rtl;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 14px 12px;
    border-radius: 18px;
    color: #f8fafc;
    background: rgba(15, 23, 42, .96);
    border: 1px solid rgba(148, 163, 184, .26);
    box-shadow: 0 18px 45px rgba(0, 0, 0, .34);
    backdrop-filter: blur(10px);
    transform: translateY(12px);
    opacity: 0;
    animation: appToastIn .22s ease-out forwards;
}

.app-toast.removing {
    animation: appToastOut .18s ease-in forwards;
}

.app-toast-icon {
    width: 36px;
    height: 36px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex: 0 0 auto;
    background: rgba(255, 255, 255, .10);
}

.app-toast-body {
    min-width: 0;
}

.app-toast-title {
    font-weight: 800;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 2px;
}

.app-toast-message {
    font-size: 13px;
    line-height: 1.7;
    color: rgba(226, 232, 240, .92);
    word-break: break-word;
}

.app-toast-close {
    border: 0;
    outline: none;
    cursor: pointer;
    color: rgba(226, 232, 240, .82);
    background: rgba(255, 255, 255, .08);
    width: 28px;
    height: 28px;
    border-radius: 10px;
    font-size: 18px;
    line-height: 1;
}

.app-toast-close:hover {
    background: rgba(255, 255, 255, .16);
    color: #fff;
}

.app-toast-progress {
    position: absolute;
    inset-inline-start: 0;
    bottom: 0;
    height: 3px;
    width: 100%;
    transform-origin: right center;
    animation-name: appToastProgress;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}

.app-toast.success { border-color: rgba(34, 197, 94, .38); }
.app-toast.error { border-color: rgba(239, 68, 68, .42); }
.app-toast.warning { border-color: rgba(245, 158, 11, .42); }
.app-toast.info { border-color: rgba(59, 130, 246, .42); }

.app-toast.success .app-toast-icon,
.app-toast.success .app-toast-progress { background: linear-gradient(135deg, #16a34a, #22c55e); }

.app-toast.error .app-toast-icon,
.app-toast.error .app-toast-progress { background: linear-gradient(135deg, #dc2626, #ef4444); }

.app-toast.warning .app-toast-icon,
.app-toast.warning .app-toast-progress { background: linear-gradient(135deg, #d97706, #f59e0b); }

.app-toast.info .app-toast-icon,
.app-toast.info .app-toast-progress { background: linear-gradient(135deg, #2563eb, #60a5fa); }

@keyframes appToastIn {
    to { opacity: 1; transform: translateY(0); }
}

@keyframes appToastOut {
    to { opacity: 0; transform: translateY(10px); }
}

@keyframes appToastProgress {
    to { transform: scaleX(0); }
}

@media (max-width: 640px) {
    .app-toast-container {
        inset-inline-start: 12px;
        inset-inline-end: 12px;
        bottom: 14px;
        width: auto;
    }

    .app-toast {
        border-radius: 16px;
    }
}

@media print {
    .app-toast-container { display: none !important; }
}
CSS;

$js = <<<'JS'
(function () {
    if (window.__DocumentArchiveNotificationsReady) {
        return;
    }
    window.__DocumentArchiveNotificationsReady = true;

    const defaults = {
        success: { title: 'تمت العملية بنجاح', icon: '✅', duration: 4200 },
        error: { title: 'حدث خطأ', icon: '⛔', duration: 6500 },
        warning: { title: 'تنبيه', icon: '⚠️', duration: 5600 },
        info: { title: 'معلومة', icon: 'ℹ️', duration: 4800 }
    };

    function ensureContainer() {
        let container = document.getElementById('app-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'app-toast-container';
            container.className = 'app-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }
        return container;
    }

    function normalize(input, type, title) {
        if (typeof input === 'string') {
            return { message: input, type: type || 'info', title: title || '' };
        }
        return input || {};
    }

    window.showAppNotification = function (input, type, title, duration) {
        const item = normalize(input, type, title);
        const toastType = ['success', 'error', 'warning', 'info'].includes(item.type) ? item.type : 'info';
        const config = defaults[toastType];
        const message = (item.message || '').toString().trim();

        if (!message) return null;

        const container = ensureContainer();
        const toast = document.createElement('div');
        const life = Number(item.duration || duration || config.duration || 4500);

        toast.className = 'app-toast ' + toastType;
        toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');
        toast.innerHTML = `
            <div class="app-toast-icon">${item.icon || config.icon}</div>
            <div class="app-toast-body">
                <div class="app-toast-title">${escapeHtml(item.title || title || config.title)}</div>
                <div class="app-toast-message">${escapeHtml(message).replace(/\n/g, '<br>')}</div>
            </div>
            <button type="button" class="app-toast-close" aria-label="إغلاق">×</button>
            <div class="app-toast-progress" style="animation-duration:${life}ms"></div>
        `;

        const remove = function () {
            if (toast.classList.contains('removing')) return;
            toast.classList.add('removing');
            window.setTimeout(() => toast.remove(), 220);
        };

        toast.querySelector('.app-toast-close').addEventListener('click', remove);
        container.appendChild(toast);

        const timer = window.setTimeout(remove, life);
        toast.addEventListener('mouseenter', () => window.clearTimeout(timer), { once: true });
        return toast;
    };

    window.addEventListener('app:notify', function (event) {
        window.showAppNotification(event.detail || {});
    });

    document.addEventListener('DOMContentLoaded', function () {
        const payload = Array.isArray(window.AppFlashNotifications) ? window.AppFlashNotifications : [];
        payload.forEach(function (item) {
            window.showAppNotification(item);
        });
    });

    function escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
JS;

$partial = <<<'BLADE'
{{-- DocumentArchive popup notifications partial --}}
@php
    $flashNotifications = [];
    $flashMap = [
        'success' => ['type' => 'success', 'title' => 'تمت العملية بنجاح'],
        'status' => ['type' => 'success', 'title' => 'تمت العملية بنجاح'],
        'message' => ['type' => 'info', 'title' => 'معلومة'],
        'info' => ['type' => 'info', 'title' => 'معلومة'],
        'warning' => ['type' => 'warning', 'title' => 'تنبيه'],
        'error' => ['type' => 'error', 'title' => 'حدث خطأ'],
        'danger' => ['type' => 'error', 'title' => 'حدث خطأ'],
    ];

    foreach ($flashMap as $key => $meta) {
        if (session()->has($key)) {
            $value = session($key);
            if (is_array($value)) {
                $value = implode("\n", array_filter($value));
            }
            $flashNotifications[] = [
                'type' => $meta['type'],
                'title' => $meta['title'],
                'message' => (string) $value,
            ];
        }
    }

    if (isset($errors) && $errors->any()) {
        $messages = collect($errors->all())->take(5)->implode("\n");
        $remaining = max($errors->count() - 5, 0);
        if ($remaining > 0) {
            $messages .= "\n" . 'وتوجد ' . $remaining . ' ملاحظات إضافية.';
        }
        $flashNotifications[] = [
            'type' => 'error',
            'title' => 'يرجى مراجعة البيانات',
            'message' => $messages,
        ];
    }
@endphp

<div id="app-toast-container" class="app-toast-container" aria-live="polite" aria-atomic="true"></div>

@if (!empty($flashNotifications))
    <script>
        window.AppFlashNotifications = (window.AppFlashNotifications || []).concat(@json($flashNotifications, JSON_UNESCAPED_UNICODE));
    </script>
@endif
BLADE;

put_file($base . '/public/css/app-notifications.css', $css);
put_file($base . '/public/js/app-notifications.js', $js);
put_file($base . '/resources/views/partials/flash-notifications.blade.php', $partial);

$layoutCandidates = [
    $base . '/resources/views/layouts/app.blade.php',
    $base . '/resources/views/layouts/admin.blade.php',
];

$patched = false;
foreach ($layoutCandidates as $layout) {
    if (!file_exists($layout)) {
        continue;
    }

    $cssInsert = "    {{-- DocumentArchive popup notifications --}}\n    <link rel=\"stylesheet\" href=\"{{ asset('css/app-notifications.css') }}\">";
    $jsInsert = "    {{-- DocumentArchive popup notifications --}}\n    @include('partials.flash-notifications')\n    <script src=\"{{ asset('js/app-notifications.js') }}\"></script>";

    inject_before_once($layout, '</head>', $cssInsert, 'app-notifications.css');
    inject_before_once($layout, '</body>', $jsInsert, "partials.flash-notifications");
    if (strpos(file_get_contents($layout), 'app-notifications.js') === false) {
        inject_before_once($layout, '</body>', "    <script src=\"{{ asset('js/app-notifications.js') }}\"></script>", 'app-notifications.js');
    }
    $patched = true;
    // غالباً app.blade هو الأساسي، لا نحتاج تعديل كل layouts حتى لا تظهر مرتين.
    break;
}

if (!$patched) {
    echo "WARNING: لم يتم العثور على layout معروف. أضف يدوياً داخل layout:\n";
    echo "<link rel=\"stylesheet\" href=\"{{ asset('css/app-notifications.css') }}\">\n";
    echo "@include('partials.flash-notifications')\n";
    echo "<script src=\"{{ asset('js/app-notifications.js') }}\"></script>\n";
}

// إضافة مثال استخدام للمطور داخل scripts فقط.
$example = <<<'TXT'
أمثلة استخدام الإشعارات المنبثقة:

من Laravel Controller:
return redirect()->route('documents.index')->with('success', 'تم حفظ الكتاب بنجاح.');
return back()->with('error', 'تعذر تنفيذ العملية.');
return back()->with('warning', 'يوجد تنبيه يحتاج مراجعة.');
return back()->with('info', 'تم تحديث البيانات.');

من JavaScript:
window.showAppNotification({
    type: 'success',
    title: 'تم الحفظ',
    message: 'تم حفظ البيانات بنجاح.'
});
TXT;
put_file($base . '/scripts/popup_notifications_usage_examples.txt', $example);

echo "\nDONE: تم تركيب الإشعارات المنبثقة.\n";
echo "نفذ الآن: php artisan view:clear && php artisan optimize:clear\n";
