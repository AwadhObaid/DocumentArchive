<?php

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تفك الضغط داخل جذر مشروع Laravel.\n");
    exit(1);
}

function rrmdir_safe($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir_safe($path); else @unlink($path);
    }
    @rmdir($dir);
}

function ensure_dir($dir) {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'dashboard-array-object-compat-' . date('Ymd_His');
ensure_dir($backupDir);

// Clean accidental _backup folders from active Laravel autoload/view paths.
foreach (['app', 'resources', 'routes'] as $base) {
    $basePath = $root . DIRECTORY_SEPARATOR . $base;
    if (!is_dir($basePath)) continue;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir() && str_starts_with($fileInfo->getFilename(), '_backup')) {
            rrmdir_safe($fileInfo->getPathname());
        }
    }
}

$touched = [];

// 1) Make DashboardController convert array rows to objects before returning view.
$controllerPath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DashboardController.php';
if (!file_exists($controllerPath)) {
    fwrite(STDERR, "ERROR: الملف غير موجود: app/Http/Controllers/DashboardController.php\n");
    exit(1);
}
$controller = file_get_contents($controllerPath);
copy($controllerPath, $backupDir . DIRECTORY_SEPARATOR . 'DashboardController.php');

$markerStart = '// [DA-DASHBOARD-OBJECT-COMPAT-START]';
if (!str_contains($controller, $markerStart)) {
    $block = <<<'PHPBLOCK'
        // [DA-DASHBOARD-OBJECT-COMPAT-START]
        $daObjectify = function ($items) {
            if ($items instanceof \Illuminate\Support\Collection) {
                return $items->map(function ($item) {
                    return is_array($item) ? (object) $item : $item;
                });
            }

            if (is_array($items)) {
                return collect($items)->map(function ($item) {
                    return is_array($item) ? (object) $item : $item;
                });
            }

            return $items;
        };

        foreach ([
            'latestDocuments',
            'recentDocuments',
            'lastDocuments',
            'latestDocs',
            'recentDocs',
            'documents',
            'latestActivityLogs',
            'recentActivityLogs',
            'activityLogs',
            'latestActivities',
            'recentActivities',
            'activities',
        ] as $daVarName) {
            if (isset($$daVarName)) {
                $$daVarName = $daObjectify($$daVarName);
            }
        }
        // [DA-DASHBOARD-OBJECT-COMPAT-END]

PHPBLOCK;

    $pos = strpos($controller, 'return view(');
    if ($pos === false) {
        fwrite(STDERR, "ERROR: تعذر إيجاد return view داخل DashboardController.php\n");
        exit(1);
    }
    $controller = substr($controller, 0, $pos) . $block . substr($controller, $pos);
    file_put_contents($controllerPath, $controller);
    $touched[] = 'app/Http/Controllers/DashboardController.php';
}

// 2) Make dashboard blade tolerate arrays and objects in common document/activity rows.
$viewPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.blade.php';
if (file_exists($viewPath)) {
    $view = file_get_contents($viewPath);
    copy($viewPath, $backupDir . DIRECTORY_SEPARATOR . 'dashboard-index.blade.php');
    $originalView = $view;

    $varNames = [
        'document', 'doc', 'latestDocument', 'recentDocument', 'latestDoc', 'recentDoc',
        'activity', 'log', 'activityLog', 'recentActivity', 'latestActivity', 'item', 'row'
    ];
    $fields = [
        'id', 'reference_number', 'reference_date', 'subject', 'title', 'main_policy_number',
        'sub_policy_number', 'created_at', 'action', 'description', 'model_type', 'model_id',
        'name', 'type', 'status', 'url', 'link'
    ];

    foreach ($varNames as $var) {
        foreach ($fields as $field) {
            $pattern = '/\$' . preg_quote($var, '/') . '->' . preg_quote($field, '/') . '\b/';
            $replacement = "data_get(\$$var, '$field')";
            $view = preg_replace($pattern, $replacement, $view);
        }
    }

    if ($view !== $originalView) {
        file_put_contents($viewPath, $view);
        $touched[] = 'resources/views/dashboard/index.blade.php';
    }
}

// 3) Add a tiny check script marker file is not needed; output summary.
echo "DONE: تم إصلاح توافق لوحة التحكم مع المصفوفات والكائنات.\n";
echo "Backup: {$backupDir}\n";
if ($touched) {
    echo "Files touched:\n- " . implode("\n- ", $touched) . "\n";
} else {
    echo "لم تكن هناك تغييرات جديدة؛ يبدو أن الإصلاح مطبق مسبقاً.\n";
}
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";
