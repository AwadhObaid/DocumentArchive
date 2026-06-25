<?php

use Illuminate\Support\Facades\Schema;
use App\Models\User;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!Schema::hasColumn('users', 'permissions')) {
    echo "ERROR: users.permissions column is missing." . PHP_EOL;
    exit(1);
}

echo "OK: users.permissions column exists." . PHP_EOL;

$admin = User::where('username', 'admin')->first();
if (!$admin) {
    echo "WARNING: admin user not found." . PHP_EOL;
    exit(0);
}

echo "Admin role: {$admin->role}" . PHP_EOL;
echo "Admin has users.manage: " . ($admin->hasPermission('users.manage') ? 'YES' : 'NO') . PHP_EOL;
echo "Admin has backups.restore: " . ($admin->hasPermission('backups.restore') ? 'YES' : 'NO') . PHP_EOL;
