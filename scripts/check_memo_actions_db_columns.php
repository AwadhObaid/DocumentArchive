<?php

use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);

require $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$app = require $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$checks = [
    'email_messages' => ['memo_id'],
    'whatsapp_messages' => ['memo_id'],
    'shared_attachment_links' => ['memo_id'],
    'shared_attachment_link_items' => ['memo_attachment_id'],
];

$ok = true;

echo "------------------------------------------------------------\n";
echo "Memo actions database columns check\n";
echo "------------------------------------------------------------\n";

foreach ($checks as $table => $columns) {
    if (!Schema::hasTable($table)) {
        echo "MISSING TABLE: {$table}\n";
        $ok = false;
        continue;
    }

    foreach ($columns as $column) {
        if (Schema::hasColumn($table, $column)) {
            echo "OK: {$table}.{$column}\n";
        } else {
            echo "MISSING COLUMN: {$table}.{$column}\n";
            $ok = false;
        }
    }
}

echo "------------------------------------------------------------\n";
echo $ok ? "RESULT: OK\n" : "RESULT: FAILED\n";

exit($ok ? 0 : 1);
