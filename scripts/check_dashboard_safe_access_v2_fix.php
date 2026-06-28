<?php
$root = dirname(__DIR__);
$dashboard = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.blade.php';
$errors = [];

if (!file_exists($dashboard)) {
    $errors[] = 'الملف غير موجود: resources/views/dashboard/index.blade.php';
} else {
    $c = file_get_contents($dashboard);
    if (strpos($c, 'function da_dashboard_value') === false) {
        $errors[] = 'دالة da_dashboard_value غير موجودة داخل لوحة التحكم.';
    }

    // Detect remaining direct access for fields known to cause recent crashes.
    $dangerPatterns = [
        '/\$[A-Za-z_][A-Za-z0-9_]*\s*\[\s*[\'\"](?:id|reference_number|reference_date|subject|created_at|action|description|model_id)[\'\"]\s*\]/u' => 'ما زال يوجد وصول array مباشر لحقول لوحة التحكم.',
        '/\$[A-Za-z_][A-Za-z0-9_]*\s*->\s*(?:id|reference_number|reference_date|subject|created_at|action|description|model_id)\b/u' => 'ما زال يوجد وصول object مباشر لحقول لوحة التحكم.'
    ];
    foreach ($dangerPatterns as $pattern => $message) {
        if (preg_match($pattern, $c)) {
            $errors[] = $message;
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح لوحة التحكم.\n";
    foreach (array_unique($errors) as $e) {
        echo "- {$e}\n";
    }
    exit(1);
}

echo "OK: إصلاح لوحة التحكم جاهز.\n";
