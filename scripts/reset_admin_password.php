<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!Schema::hasTable('users')) {
    echo "ERROR: users table does not exist. Run migrations first.\n";
    exit(1);
}

$now = now();

$values = [
    'name' => 'مدير النظام',
    'username' => 'admin',
    'email' => 'admin@documentarchive.local',
    'phone' => null,
    'role' => 'مدير النظام',
    'is_active' => 1,
    'password' => Hash::make('12345678'),
    'updated_at' => $now,
];

$existing = DB::table('users')->where('username', 'admin')->first();

if ($existing) {
    DB::table('users')->where('username', 'admin')->update($values);
    echo "OK: Admin user updated successfully.\n";
} else {
    $values['created_at'] = $now;
    DB::table('users')->insert($values);
    echo "OK: Admin user created successfully.\n";
}

echo "Username: admin\n";
echo "Password: 12345678\n";
echo "Role: مدير النظام\n";
