<?php
$root = getcwd();
$errors = [];
if (!is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'شغّل الفحص من جذر مشروع Laravel.';
}
if (!is_file($root . '/public/css/document-qr-precise-side-position.css')) {
    $errors[] = 'ملف CSS غير موجود: public/css/document-qr-precise-side-position.css';
}
if (!is_file($root . '/public/js/document-qr-precise-side-position.js')) {
    $errors[] = 'ملف JS غير موجود: public/js/document-qr-precise-side-position.js';
}
$viewsDir = $root . '/resources/views';
$foundLink = false;
if (is_dir($viewsDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') continue;
        $c = file_get_contents($file->getPathname());
        if (strpos($c, 'document-qr-precise-side-position.css') !== false && strpos($c, 'document-qr-precise-side-position.js') !== false) {
            $foundLink = true;
            break;
        }
    }
}
if (!$foundLink) {
    $errors[] = 'روابط CSS/JS غير موجودة داخل ملف Blade الخاص بالطباعة.';
}
if ($errors) {
    echo "ERROR: لم يكتمل ضبط موضع QR.\n- " . implode("\n- ", $errors) . "\n";
    exit(1);
}
echo "OK: تم ضبط موضع QR بدقة داخل صفحة الطباعة.\n";
