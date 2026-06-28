<?php
$root = dirname(__DIR__);
chdir($root);

$errors = [];
function read_file_rel(string $rel): string {
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    return is_file($path) ? file_get_contents($path) : '';
}

// 1) No Arabic words split after meem in active PHP/Blade/JS/CSS files.
$dirs = ['resources/views', 'app/Http/Controllers', 'app/Models', 'app/Providers', 'routes', 'public/js', 'public/css'];
$badSplit = [];
foreach ($dirs as $dir) {
    $base = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $path = $file->getPathname();
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = basename($path);
        if (!str_ends_with($name, '.blade.php') && !in_array($ext, ['php', 'js', 'css'], true)) continue;
        $content = file_get_contents($path);
        if (preg_match('/م(?:\r\n|\r|\n)[ \t]*(?=[\x{0600}-\x{06FF}])/u', $content)) {
            $badSplit[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}
if ($badSplit) {
    $errors[] = "لا تزال هناك كلمات عربية مقطعة بعد حرف م:\n  - " . implode("\n  - ", array_slice($badSplit, 0, 30));
}

$preview = read_file_rel('resources/views/attachments/preview.blade.php');
if (!str_contains($preview, "route('attachments.inline'")) {
    $errors[] = 'صفحة معاينة المرفق لا تستخدم route attachments.inline.';
}
if (str_contains($preview, 'fetch(DATA_URL') || str_contains($preview, 'pdfjsLib.getDocument')) {
    $errors[] = 'صفحة معاينة المرفق ما زالت تعتمد على منطق JS/PDF.js القديم.';
}
if (!str_contains($preview, 'تنزيله بإرادته فقط') && !str_contains($preview, 'التنزيل يبقى اختيارياً')) {
    $errors[] = 'رسالة التنزيل الاختياري غير موجودة في صفحة المعاينة.';
}

$print = read_file_rel('resources/views/documents/print-reference.blade.php');
foreach (['qr_x_mm', 'print_block_x_mm', 'print_font_size_pt', 'qr_label_text'] as $needle) {
    if (!str_contains($print, $needle)) {
        $errors[] = "صفحة طباعة رقم الكتاب لا تقرأ الإعداد: {$needle}";
    }
}
if (str_contains($print, 'right: 42mm') || str_contains($print, 'font-size: 13pt')) {
    $errors[] = 'صفحة طباعة رقم الكتاب ما زالت تحتوي قيماً قديمة كبيرة/يمينية.';
}

$settings = read_file_rel('resources/views/settings/qr-print-position.blade.php');
if (!str_starts_with(ltrim($settings), "@extends('layouts.app')")) {
    $errors[] = 'صفحة إعدادات QR لا تبدأ بالـ layout الصحيح.';
}
foreach (['print_block_x_mm', 'print_font_size_pt', 'إعدادات بيانات رقم الكتاب'] as $needle) {
    if (!str_contains($settings, $needle)) {
        $errors[] = "صفحة إعدادات الباركود والطباعة ناقصة: {$needle}";
    }
}
if (str_contains($settings, 'QR_PRINT_SETTINGS_BIND_START') || str_contains($settings, 'DA_QR_SETTINGS_BIND_V2_START')) {
    $errors[] = 'صفحة إعدادات QR ما زالت تحتوي بقايا ربط QR القديمة.';
}

$controller = read_file_rel('app/Http/Controllers/QrPrintSettingsController.php');
foreach (['print_block_x_mm', 'settings(): array', 'saveMany'] as $needle) {
    if (!str_contains($controller, $needle)) {
        $errors[] = "QrPrintSettingsController ناقص: {$needle}";
    }
}

$appCss = read_file_rel('public/css/app.css');
if (!str_contains($appCss, 'reviewed-stability-fix:start')) {
    $errors[] = 'تنسيقات الوضع الليلي للمرفقات غير مضافة إلى app.css.';
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح المراجعة المستقر.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح المراجعة المستقر مكتمل. الكلمات غير مقطعة، والمعاينة اختيارية، والطباعة مرتبطة بالإعدادات.\n";
