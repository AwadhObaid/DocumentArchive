<?php
/**
 * Dashboard safe access V2 fix
 * Fixes Blade errors caused by mixing arrays/stdClass/Eloquent objects in dashboard/index.blade.php.
 */

$root = dirname(__DIR__);
if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: لم يتم العثور على ملف artisan. تأكد أنك تشغل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$dashboard = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.blade.php';
if (!file_exists($dashboard)) {
    fwrite(STDERR, "ERROR: الملف غير موجود: resources/views/dashboard/index.blade.php\n");
    exit(1);
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'dashboard-safe-access-v2-' . date('Ymd_His');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
copy($dashboard, $backupDir . DIRECTORY_SEPARATOR . 'index.blade.php');

$contents = file_get_contents($dashboard);
$original = $contents;

$helper = <<<'BLADE'
@php
    if (! function_exists('da_dashboard_value')) {
        function da_dashboard_value($item, string $key, $default = '') {
            if (is_array($item)) {
                return $item[$key] ?? $default;
            }

            if (is_object($item)) {
                return $item->{$key} ?? $default;
            }

            return $default;
        }
    }
@endphp

BLADE;

if (strpos($contents, 'function da_dashboard_value') === false) {
    if (preg_match('/(@extends\s*\([^\n]+\)\s*)/u', $contents, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        $contents = substr($contents, 0, $pos) . "\n" . $helper . substr($contents, $pos);
    } else {
        $contents = $helper . $contents;
    }
}

// Replace direct array access on common dashboard row variables with safe helper.
// Examples: $document['id'] => da_dashboard_value($document, 'id')
$contents = preg_replace_callback(
    '/(\$[A-Za-z_][A-Za-z0-9_]*)\s*\[\s*[\'\"]([A-Za-z0-9_]+)[\'\"]\s*\]/u',
    function ($m) {
        return "da_dashboard_value({$m[1]}, '{$m[2]}')";
    },
    $contents
);

// Replace direct object access for fields commonly returned as arrays in dashboard cards/lists.
// Examples: $document->id => da_dashboard_value($document, 'id')
$fields = [
    'id', 'reference_number', 'reference_date', 'reference_year', 'subject', 'title',
    'main_policy_number', 'sub_policy_number', 'created_at', 'updated_at', 'deleted_at',
    'action', 'description', 'model_type', 'model_id', 'name', 'count', 'total',
    'document_id', 'type', 'status', 'url', 'link'
];
$fieldPattern = implode('|', array_map('preg_quote', $fields));
$contents = preg_replace_callback(
    '/(\$[A-Za-z_][A-Za-z0-9_]*)\s*->\s*(' . $fieldPattern . ')\b/u',
    function ($m) {
        return "da_dashboard_value({$m[1]}, '{$m[2]}')";
    },
    $contents
);

if ($contents === null) {
    fwrite(STDERR, "ERROR: حدث خطأ أثناء معالجة ملف لوحة التحكم.\n");
    exit(1);
}

file_put_contents($dashboard, $contents);

echo "DONE: تم إصلاح توافق لوحة التحكم مع array/stdClass/object.\n";
echo "Backup: {$backupDir}\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";
