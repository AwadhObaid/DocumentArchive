<?php

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    // When executed from project root: php scripts/...
    $root = getcwd();
}
if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تعذر تحديد جذر مشروع Laravel. شغّل السكربت من داخل جذر المشروع.\n");
    exit(1);
}

$view = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'qr-print-position.blade.php';
if (!file_exists($view)) {
    fwrite(STDERR, "ERROR: الملف غير موجود: resources/views/settings/qr-print-position.blade.php\n");
    exit(1);
}

$content = file_get_contents($view);
if ($content === false) {
    fwrite(STDERR, "ERROR: تعذر قراءة ملف إعدادات موضع QR.\n");
    exit(1);
}

$safeBackExpression = "\\Illuminate\\Support\\Facades\\Route::has('settings.index') ? route('settings.index') : url('/settings')";
$original = $content;

// Fix the common Blade patterns first.
$content = preg_replace(
    "/\{\{\s*route\(\s*['\"]settings\.index['\"]\s*\)\s*\}\}/",
    "{{ {$safeBackExpression} }}",
    $content
);

// Fix href="{{route('settings.index')}}" variations already handled above; this catches {!! route(...) !!} if present.
$content = preg_replace(
    "/\{!!\s*route\(\s*['\"]settings\.index['\"]\s*\)\s*!!\}/",
    "{!! {$safeBackExpression} !!}",
    $content
);

// If a raw route('settings.index') remains inside a direct href or JS snippet, replace with safe expression.
// Avoid changing the route call inside the safe fallback we just inserted.
if (strpos($content, "Route::has('settings.index')") === false) {
    $content = str_replace(["route('settings.index')", 'route("settings.index")'], $safeBackExpression, $content);
}

if ($content === $original) {
    echo "INFO: لم يتم العثور على route('settings.index') داخل صفحة QR، ربما تم إصلاحها مسبقاً.\n";
} else {
    if (file_put_contents($view, $content) === false) {
        fwrite(STDERR, "ERROR: تعذر حفظ ملف إعدادات موضع QR.\n");
        exit(1);
    }
    echo "DONE: تم إصلاح رابط الرجوع في صفحة إعدادات موضع QR بدون الاعتماد الإجباري على route settings.index.\n";
}

// Optional: ensure settings directory route name is not required by this view.
echo "NEXT: php artisan route:clear && php artisan view:clear && php artisan optimize:clear\n";
