<?php
$root = dirname(__DIR__);

$webPath = $root . '/routes/web.php';
$controllerPath = $root . '/app/Http/Controllers/DocumentController.php';
$createViewPath = $root . '/resources/views/documents/create.blade.php';
$editViewPath = $root . '/resources/views/documents/edit.blade.php';

$errors = [];

foreach ([$webPath, $controllerPath] as $path) {
    if (!file_exists($path)) {
        $errors[] = 'الملف غير موجود: ' . str_replace($root . '/', '', $path);
    }
}

if (file_exists($webPath)) {
    $web = file_get_contents($webPath);
    if (strpos($web, '/documents/check-policy-duplicate') === false || strpos($web, 'documents.check-policy-duplicate') === false) {
        $errors[] = 'مسار فحص تكرار البوليصة غير موجود في routes/web.php';
    }

    $routePos = strpos($web, '/documents/check-policy-duplicate');
    $resourcePos = strpos($web, "Route::resource('documents'");
    if ($resourcePos === false) {
        $resourcePos = strpos($web, 'Route::resource("documents"');
    }
    if ($routePos !== false && $resourcePos !== false && $routePos > $resourcePos) {
        $errors[] = 'مسار فحص التكرار موجود بعد Route::resource، وهذا قد يمنع ظهوره. يجب أن يكون قبله.';
    }
}

if (file_exists($controllerPath)) {
    $controller = file_get_contents($controllerPath);
    foreach (['function checkPolicyDuplicate', 'matched_field', 'TRIM(COALESCE(main_policy_number', 'TRIM(COALESCE(sub_policy_number'] as $needle) {
        if (strpos($controller, $needle) === false) {
            $errors[] = 'عنصر ناقص في DocumentController.php: ' . $needle;
        }
    }
}

foreach ([$createViewPath, $editViewPath] as $viewPath) {
    if (!file_exists($viewPath)) {
        $errors[] = 'ملف الواجهة غير موجود: ' . str_replace($root . '/', '', $viewPath);
        continue;
    }
    $view = file_get_contents($viewPath);
    foreach (['DA_POLICY_DUPLICATE_WARNING_V2_START', 'تنبيه تكرار البوليصة', 'نعم، مواصلة', 'لا، منع', 'documents.check-policy-duplicate'] as $needle) {
        if (strpos($view, $needle) === false) {
            $errors[] = 'عنصر ناقص في ' . str_replace($root . '/', '', $viewPath) . ': ' . $needle;
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل تركيب إصلاح تنبيه تكرار البوليصة V2.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: Policy duplicate warning V2 is installed.\n";
echo "تم التحقق من المسار، الترتيب قبل Route::resource، دالة الفحص، وسكربت create/edit.\n";
