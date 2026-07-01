<?php

$root = dirname(__DIR__);
$failures = [];

$requiredFiles = [
    'app/Http/Controllers/EmailController.php',
    'app/Models/EmailMessage.php',
    'database/migrations/2026_07_01_102500_create_email_messages_table.php',
    'resources/views/emails/index.blade.php',
    'resources/views/emails/compose.blade.php',
    'resources/views/emails/show.blade.php',
    'resources/views/emails/mail/document.blade.php',
    'public/css/email-module.css',
    'README_EMAIL_MODULE_UPDATE.txt',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $failures[] = "Missing file: {$file}";
    }
}

$checks = [
    'routes/web.php' => [
        'EmailController::class',
        "name('emails.index')",
        "name('emails.compose')",
        "name('emails.send')",
        "name('documents.email.compose')",
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "'emails.index' => 'emails.view'",
        "'emails.send' => 'emails.send'",
        "'documents.email.compose' => 'emails.send'",
    ],
    'app/Support/PermissionRegistry.php' => [
        "'key' => 'emails'",
        "'emails.view' => 'عرض البريد الإلكتروني وسجل الإرسال'",
        "'emails.send' => 'إرسال الكتب بالبريد الإلكتروني'",
    ],
    'resources/views/layouts/app.blade.php' => [
        "route('emails.index')",
        "css/email-module.css",
    ],
    'resources/views/documents/index.blade.php' => [
        "route('documents.email.compose', \$document)",
        'إرسال بالبريد',
    ],
    'resources/views/documents/show.blade.php' => [
        "route('documents.email.compose', \$document)",
        'إرسال بالبريد',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        $failures[] = "Missing file for content check: {$file}";
        continue;
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $failures[] = "Missing expected text in {$file}: {$needle}";
        }
    }
}

$phpFiles = [
    'app/Http/Controllers/EmailController.php',
    'app/Models/EmailMessage.php',
    'database/migrations/2026_07_01_102500_create_email_messages_table.php',
];

foreach ($phpFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        continue;
    }

    $command = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    $code = 0;
    exec($command, $output, $code);

    if ($code !== 0) {
        $failures[] = "PHP syntax check failed for {$file}: " . implode(' ', $output);
    }
}

if ($failures !== []) {
    echo "Email module update check failed:\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

echo "Email module update check passed.\n";
exit(0);
