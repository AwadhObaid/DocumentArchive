<?php
$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $root = getcwd();
}
if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل الفحص من جذر مشروع Laravel.\n");
    exit(1);
}
function p(...$parts) { return preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $parts)); }
$errors = [];
$layout = p($root, 'resources', 'views', 'layouts', 'app.blade.php');
$show = p($root, 'resources', 'views', 'documents', 'show.blade.php');
$print = p($root, 'resources', 'views', 'documents', 'print-reference.blade.php');
$guard = p($root, 'public', 'css', 'qr-leak-guard.css');

foreach ([$layout, $show, $print, $guard] as $file) {
    if (!file_exists($file)) $errors[] = 'ملف غير موجود: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file);
}
if (file_exists($layout)) {
    $c = file_get_contents($layout);
    if (strpos($c, 'qr-leak-guard.css') === false) $errors[] = 'رابط qr-leak-guard.css غير موجود في layout.';
    if (preg_match('/document-qr-card|document-print-qr|da-qr-print-box/i', $c)) $errors[] = 'ما زالت توجد آثار QR عائمة في layout.';
}
if (file_exists($show)) {
    $c = file_get_contents($show);
    if (preg_match('/document-qr-card|document-print-qr|da-qr|\/qr\.svg|qr-print-position/i', $c)) $errors[] = 'ما زالت توجد آثار QR داخل صفحة عرض الكتاب.';
    if (strpos($c, 'طباعة رقم الكتاب') === false) $errors[] = 'صفحة عرض الكتاب لا تحتوي زر طباعة رقم الكتاب.';
}
if (file_exists($print)) {
    $c = file_get_contents($print);
    if (strpos($c, 'da-print-reference-page') === false) $errors[] = 'صفحة الطباعة غير معزولة بالـ body class.';
    if (strpos($c, 'qr_print_settings') === false) $errors[] = 'صفحة الطباعة لا تقرأ إعدادات QR.';
    if (strpos($c, '/qr.svg') === false) $errors[] = 'رابط QR داخل صفحة الطباعة غير موجود.';
}
foreach ([p($root,'app'), p($root,'resources'), p($root,'routes')] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if ($item->isDir() && strpos($item->getFilename(), '_backup') === 0) {
            $errors[] = 'ما زال يوجد مجلد backup داخل مسارات Laravel: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
        }
    }
}
if ($errors) {
    echo "ERROR: لم يكتمل إصلاح عزل QR.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}
echo "OK: تم عزل QR بنجاح. QR يظهر فقط في صفحة طباعة رقم الكتاب، وصفحة عرض الكتاب نظيفة.\n";
