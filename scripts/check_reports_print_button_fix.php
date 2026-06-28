<?php
/**
 * Verifies that the professional report button is present in reports/index.blade.php.
 * Run from Laravel project root:
 *   php scripts/check_reports_print_button_fix.php
 */

$root = realpath(__DIR__ . '/..');
if (!$root) {
    fwrite(STDERR, "ERROR: Cannot resolve project root.\n");
    exit(1);
}

$view = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'index.blade.php';
$routes = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$errors = [];
$warnings = [];

if (!is_file($view)) {
    $errors[] = 'ملف صفحة التقارير غير موجود: resources/views/reports/index.blade.php';
} else {
    $content = file_get_contents($view);
    if (strpos($content, 'REPORTS_PRINT_BUTTON_FIX_START') === false) {
        $errors[] = 'بلوك زر التقرير الرسمي غير موجود داخل صفحة التقارير.';
    }
    if (strpos($content, '/reports/print') === false) {
        $errors[] = 'رابط /reports/print غير موجود داخل زر صفحة التقارير.';
    }
    if (strpos($content, 'تقرير رسمي منسق') === false) {
        $errors[] = 'نص الزر "تقرير رسمي منسق" غير موجود.';
    }
}

if (!is_file($routes)) {
    $warnings[] = 'ملف routes/web.php غير موجود للفحص.';
} else {
    $routeContent = file_get_contents($routes);
    if (strpos($routeContent, '/reports/print') === false && strpos($routeContent, "reports/print") === false) {
        $warnings[] = 'لم يتم العثور على مسار /reports/print داخل routes/web.php. إذا كان الرابط لا يفتح، أعد تركيب تحديث التقرير الرسمي.';
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح زر التقرير الرسمي.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    if ($warnings) {
        echo "WARNINGS:\n";
        foreach ($warnings as $warning) {
            echo "- {$warning}\n";
        }
    }
    exit(1);
}

echo "OK: زر التقرير الرسمي المنسق موجود داخل صفحة التقارير.\n";
foreach ($warnings as $warning) {
    echo "WARNING: {$warning}\n";
}
