<?php

$root = dirname(__DIR__);
$required = [
    'app/Http/Controllers/ContactController.php',
    'app/Http/Controllers/MessageTemplateController.php',
    'app/Models/Contact.php',
    'app/Models/MessageTemplate.php',
    'database/migrations/2026_07_01_113800_create_contacts_and_message_templates.php',
    'resources/views/contacts/index.blade.php',
    'resources/views/contacts/create.blade.php',
    'resources/views/contacts/edit.blade.php',
    'resources/views/message-templates/index.blade.php',
    'resources/views/message-templates/create.blade.php',
    'resources/views/message-templates/edit.blade.php',
    'resources/views/emails/compose.blade.php',
    'resources/views/whatsapp/compose.blade.php',
    'public/css/contacts-templates.css',
];

$missing = [];
foreach ($required as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $missing[] = $file;
    }
}

$checks = [
    'routes/web.php' => ['ContactController', 'MessageTemplateController', "Route::resource('contacts'", "Route::resource('message-templates'"],
    'app/Support/PermissionRegistry.php' => ['contacts.view', 'contacts.manage', 'message_templates.view', 'message_templates.manage'],
    'app/Http/Middleware/ApplyRoutePermissions.php' => ['contacts.index', 'message-templates.index'],
    'resources/views/layouts/app.blade.php' => ['contacts-templates.css', 'جهات الاتصال', 'قوالب الرسائل'],
];

foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $content = is_file($path) ? file_get_contents($path) : '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $missing[] = $file . ' missing marker: ' . $needle;
        }
    }
}

if ($missing !== []) {
    echo "Contacts/templates update check failed:\n";
    foreach ($missing as $item) {
        echo "- {$item}\n";
    }
    exit(1);
}

echo "Contacts/templates update check passed.\n";
