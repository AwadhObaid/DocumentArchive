<?php

$controllerPath = __DIR__ . '/../app/Http/Controllers/BackupController.php';

if (!file_exists($controllerPath)) {
    fwrite(STDERR, "BackupController.php not found: {$controllerPath}\n");
    exit(1);
}

$content = file_get_contents($controllerPath);
if ($content === false) {
    fwrite(STDERR, "Unable to read BackupController.php\n");
    exit(1);
}

$method = <<<'PHP_METHOD'

    /**
     * يمنع قفل النظام بعد استعادة قاعدة البيانات.
     * بعد أي استعادة لقاعدة البيانات يتم التأكد من وجود حساب المدير الافتراضي
     * بكلمة مرور معروفة، مع عدم التأثير على باقي المستخدمين.
     */
    private function ensureDefaultAdminAfterRestore(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('users')) {
            return;
        }

        $now = now();

        DB::table('users')->updateOrInsert(
            ['username' => 'admin'],
            [
                'name' => 'مدير النظام',
                'email' => null,
                'phone' => null,
                'role' => 'مدير النظام',
                'is_active' => 1,
                'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
PHP_METHOD;

if (!str_contains($content, 'ensureDefaultAdminAfterRestore')) {
    $needle = "\n    private function formatBytes";
    if (str_contains($content, $needle)) {
        $content = str_replace($needle, $method . $needle, $content);
    } else {
        $pos = strrpos($content, "\n}");
        if ($pos === false) {
            fwrite(STDERR, "Could not find class closing brace.\n");
            exit(1);
        }
        $content = substr($content, 0, $pos) . $method . substr($content, $pos);
    }
}

// Add call after database restore. Keep idempotent.
$content = str_replace(
    "            $" . "this->restoreDatabaseFromZip($" . "fileName);\n\n            return redirect()",
    "            $" . "this->restoreDatabaseFromZip($" . "fileName);\n            $" . "this->ensureDefaultAdminAfterRestore();\n\n            return redirect()",
    $content
);

$content = str_replace(
    "            $" . "this->restoreDatabaseFromZip($" . "fileName);\n            $" . "this->restoreDocumentsFromZip($" . "fileName);",
    "            $" . "this->restoreDatabaseFromZip($" . "fileName);\n            $" . "this->ensureDefaultAdminAfterRestore();\n            $" . "this->restoreDocumentsFromZip($" . "fileName);",
    $content
);

// Avoid duplicate call if script was run more than once.
$content = preg_replace('/(ensureDefaultAdminAfterRestore\(\);\s*){2,}/', "ensureDefaultAdminAfterRestore();\n            ", $content);

file_put_contents($controllerPath, $content);

echo "OK: BackupController.php updated.\n";
echo "After any database/full restore, admin login will be reset to:\n";
echo "Username: admin\n";
echo "Password: 12345678\n";
