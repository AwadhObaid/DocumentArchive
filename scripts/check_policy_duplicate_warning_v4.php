<?php
$root = dirname(__DIR__);

function pjoin_check_v4(string ...$parts): string { return implode(DIRECTORY_SEPARATOR, $parts); }
function rel_check_v4(string $root, string $path): string { return str_replace($root . DIRECTORY_SEPARATOR, '', $path); }
function is_policy_backup_check_v4(string $path): bool
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return str_contains($normalized, DIRECTORY_SEPARATOR . '_backup_policy_duplicate');
}
function scan_active_blades_check_v4(string $viewsDir): array
{
    $files = [];
    $walk = function (string $dir) use (&$walk, &$files) {
        $items = @scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                if (str_starts_with($item, '_backup_policy_duplicate')) continue;
                $walk($path);
            } elseif (is_file($path) && str_ends_with($item, '.blade.php')) {
                $files[] = $path;
            }
        }
    };
    if (is_dir($viewsDir)) $walk($viewsDir);
    return $files;
}

$webPath = pjoin_check_v4($root, 'routes', 'web.php');
$controllerPath = pjoin_check_v4($root, 'app', 'Http', 'Controllers', 'DocumentController.php');
$createViewPath = pjoin_check_v4($root, 'resources', 'views', 'documents', 'create.blade.php');
$editViewPath = pjoin_check_v4($root, 'resources', 'views', 'documents', 'edit.blade.php');
$viewsDir = pjoin_check_v4($root, 'resources', 'views');

$errors = [];
foreach ([$webPath, $controllerPath, $createViewPath, $editViewPath] as $path) {
    if (!file_exists($path)) {
        $errors[] = 'الملف غير موجود: ' . rel_check_v4($root, $path);
    }
}

if (file_exists($webPath)) {
    $web = file_get_contents($webPath);
    $routeCount = substr_count($web, '/documents/check-policy-duplicate');
    if ($routeCount !== 1) {
        $errors[] = "يجب وجود مسار فحص التكرار مرة واحدة فقط. الموجود: {$routeCount}";
    }
    if (strpos($web, 'documents.check-policy-duplicate') === false) {
        $errors[] = 'اسم مسار فحص التكرار غير موجود.';
    }

    $routePos = strpos($web, '/documents/check-policy-duplicate');
    $resourcePos = strpos($web, "Route::resource('documents'");
    if ($resourcePos === false) $resourcePos = strpos($web, 'Route::resource("documents"');
    if ($routePos !== false && $resourcePos !== false && $routePos > $resourcePos) {
        $errors[] = 'مسار فحص التكرار موجود بعد Route::resource ويجب أن يكون قبله.';
    }
}

if (file_exists($controllerPath)) {
    $controller = file_get_contents($controllerPath);
    foreach (['function checkPolicyDuplicate', 'main_policy_number', 'sub_policy_number', 'response()->json'] as $needle) {
        if (strpos($controller, $needle) === false) {
            $errors[] = 'عنصر ناقص في DocumentController.php: ' . $needle;
        }
    }
}

foreach ([$createViewPath, $editViewPath] as $viewPath) {
    if (!file_exists($viewPath)) continue;
    $view = file_get_contents($viewPath);
    foreach (['DA_POLICY_DUPLICATE_WARNING_V4_START', '__DA_POLICY_DUPLICATE_WARNING_V4_ACTIVE__', 'تنبيه: رقم البوليصة موجود مسبقاً', 'نعم، مواصلة الإدراج', 'لا، منع الإدراج'] as $needle) {
        if (strpos($view, $needle) === false) {
            $errors[] = 'عنصر V4 ناقص في ' . rel_check_v4($root, $viewPath) . ': ' . $needle;
        }
    }
    if (strpos($view, 'confirm(') !== false && strpos($view, 'policy') !== false) {
        $errors[] = 'ما زال يوجد confirm متعلق بالبوليصة في ' . rel_check_v4($root, $viewPath);
    }
}

$oldFiles = [];
$v4Count = 0;
foreach (scan_active_blades_check_v4($viewsDir) as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'DA_POLICY_DUPLICATE_WARNING_START') !== false ||
        strpos($content, 'DA_POLICY_DUPLICATE_WARNING_V2_START') !== false ||
        strpos($content, 'DA_POLICY_DUPLICATE_WARNING_V3_START') !== false) {
        $oldFiles[] = rel_check_v4($root, $file);
    }
    $v4Count += substr_count($content, 'DA_POLICY_DUPLICATE_WARNING_V4_START');
}

if ($oldFiles) {
    $errors[] = 'ما زالت توجد بلوكات قديمة في ملفات نشطة: ' . implode(', ', $oldFiles);
}
if ($v4Count !== 2) {
    $errors[] = "يجب أن يكون بلوك V4 موجوداً مرتين فقط: create/edit. الموجود: {$v4Count}";
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح V4.\n";
    foreach ($errors as $error) echo "- {$error}\n";
    exit(1);
}

echo "OK: تنبيه تكرار البوليصة V4 يعمل بنافذة عربية مخصصة وبدون بلوكات قديمة في الملفات النشطة.\n";
