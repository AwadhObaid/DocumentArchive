<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'app/Services/EmailSettingsService.php',
    'app/Http/Controllers/EmailSettingsController.php',
    'app/Http/Controllers/EmailController.php',
    'config/mail.php',
    'public/js/global-operation-loading-v90.js',
    'resources/views/emails/compose.blade.php',
    'resources/views/settings/email.blade.php',
    'resources/views/settings/edit.blade.php',
    'routes/web.php',
];

foreach ($files as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        $errors[] = 'Missing required file: ' . $relative;
    }
}

$requiredMarkers = [
    'app/Services/EmailSettingsService.php' => [
        "class EmailSettingsService",
        "Crypt::encryptString",
        "mail.default' => 'smtp",
        "app('mail.manager')->purge('smtp')",
        "email_last_test_status",
    ],
    'app/Http/Controllers/EmailSettingsController.php' => [
        "class EmailSettingsController",
        "email.settings.updated",
        "email.settings.test_succeeded",
        "Mail::raw",
    ],
    'app/Http/Controllers/EmailController.php' => [
        "EmailSettingsService",
        "mailRuntimeSettings",
        "BookAttachmentSmartPathService",
        "absolutePathForAttachment",
        "default_socket_timeout",
    ],
    'resources/views/settings/email.blade.php' => [
        "إعدادات البريد الإلكتروني",
        "settings.email.update",
        "settings.email.test",
        "email_import_env_password",
        "data-email-preset",
    ],
    'resources/views/settings/edit.blade.php' => [
        "settings.email.edit",
        "إعدادات البريد الإلكتروني",
    ],
    'routes/web.php' => [
        "EmailSettingsController",
        "settings.email.edit",
        "settings.email.update",
        "settings.email.test",
        "email-settings-management-v95-3:start",
    ],
    'resources/views/emails/compose.blade.php' => [
        "جارٍ إرسال البريد الإلكتروني",
        "data-confirm-yes=\"نعم، إرسال الآن\"",
    ],
    'public/js/global-operation-loading-v90.js' => [
        "Confirmation dialog compatibility (V95.2.1)",
        "form.dataset.confirmAccepted !== '1'",
    ],
    'config/mail.php' => [
        "MAIL_TIMEOUT",
        "max(5, min(120",
    ],
];

foreach ($requiredMarkers as $relative => $markers) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $contents = is_file($path) ? file_get_contents($path) : false;

    if ($contents === false) {
        continue;
    }

    foreach ($markers as $marker) {
        if (! str_contains($contents, $marker)) {
            $errors[] = "Missing marker in {$relative}: {$marker}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "DocumentArchive Email Settings Management V95.3 verification FAILED.\n");

    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . PHP_EOL);
    }

    exit(1);
}

echo "DocumentArchive Email Settings Management V95.3 verification PASSED.\n";
