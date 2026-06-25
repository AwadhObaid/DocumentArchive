<?php
/**
 * حذف مجلدات النسخ الاحتياطية التي أنشأتها إصلاحات تنبيه تكرار البوليصة.
 * هذه المجلدات ليست جزءاً من واجهة Laravel الفعلية، لكنها قد تجعل سكربت الفحص يظن أن البلوكات القديمة ما زالت موجودة.
 * التشغيل من جذر المشروع:
 * php scripts/cleanup_policy_duplicate_v3_backups.php
 */

$projectRoot = realpath(__DIR__ . '/..');
if (!$projectRoot) {
    fwrite(STDERR, "ERROR: تعذر تحديد جذر المشروع.\n");
    exit(1);
}

$viewsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
if (!is_dir($viewsRoot)) {
    fwrite(STDERR, "ERROR: لم يتم العثور على resources/views. تأكد أنك تشغل السكربت من داخل مشروع Laravel.\n");
    exit(1);
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
}

$deleted = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if (!$item->isDir()) {
        continue;
    }

    $baseName = $item->getBasename();
    if (str_starts_with($baseName, '_backup_policy_duplicate')) {
        $path = $item->getPathname();
        $deleted[] = $path;
        rrmdir($path);
    }
}

if (empty($deleted)) {
    echo "OK: لا توجد مجلدات backup قديمة لتنبيه تكرار البوليصة.\n";
    exit(0);
}

echo "تم حذف مجلدات backup القديمة التالية:\n";
foreach ($deleted as $path) {
    echo "- " . str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $path) . "\n";
}

echo "\nالآن شغّل:\n";
echo "php artisan view:clear\n";
echo "php artisan optimize:clear\n";
echo "php scripts/check_policy_duplicate_warning_v3.php\n";
