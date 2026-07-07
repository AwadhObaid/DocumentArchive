<?php

use App\Models\AttachmentTextIndex;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "PDF/OCR table name fix V29 check\n";
echo "==================================================\n";

$errors = [];
$modelFile = base_path('app/Models/AttachmentTextIndex.php');
$migrationFile = base_path('database/migrations/2026_07_07_110000_create_attachment_text_indexes_table.php');

if (! is_file($modelFile)) {
    $errors[] = 'app/Models/AttachmentTextIndex.php غير موجود.';
} else {
    $contents = file_get_contents($modelFile);
    if (! str_contains($contents, "protected \$table = 'attachment_text_indexes';")) {
        $errors[] = 'اسم جدول الموديل غير مثبت على attachment_text_indexes.';
    }
}

if (! is_file($migrationFile)) {
    $errors[] = 'ملف migration الخاص بجدول فهرسة PDF غير موجود.';
}

try {
    $modelTable = (new AttachmentTextIndex())->getTable();
    echo "Model table: {$modelTable}\n";
    if ($modelTable !== 'attachment_text_indexes') {
        $errors[] = "الموديل يستخدم جدول {$modelTable} بدل attachment_text_indexes.";
    }

    $hasTable = Schema::hasTable('attachment_text_indexes');
    echo 'Database table attachment_text_indexes: ' . ($hasTable ? 'YES' : 'NO') . "\n";

    if (! $hasTable) {
        $errors[] = 'جدول attachment_text_indexes غير موجود في قاعدة البيانات. نفّذ: php artisan migrate';
    } else {
        $count = DB::table('attachment_text_indexes')->count();
        echo "Rows in attachment_text_indexes: {$count}\n";
    }
} catch (Throwable $e) {
    $errors[] = 'فشل فحص قاعدة البيانات: ' . $e->getMessage();
}

if ($errors) {
    echo "\nERRORS:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    echo "\nCHECK FAILED.\n";
    exit(1);
}

echo "\nOK: PDF/OCR table name fix V29 is installed correctly.\n";
