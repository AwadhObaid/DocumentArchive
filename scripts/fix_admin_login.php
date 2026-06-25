<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $connection = config('database.default');
    $driver = DB::connection()->getDriverName();
    $database = DB::connection()->getDatabaseName();

    echo "Database connection: {$connection}\n";
    echo "Database driver: {$driver}\n";
    echo "Database name: {$database}\n";

    if (!Schema::hasTable('users')) {
        echo "ERROR: users table does not exist. Run migrations first.\n";
        exit(1);
    }

    $now = now();

    $existing = DB::table('users')->where('username', 'admin')->first();

    $data = [
        'name' => 'مدير النظام',
        'username' => 'admin',
        'email' => null,
        'phone' => null,
        'role' => 'مدير النظام',
        'is_active' => 1,
        'password' => Hash::make('12345678'),
        'updated_at' => $now,
    ];

    if ($existing) {
        DB::table('users')->where('id', $existing->id)->update($data);
        $userId = $existing->id;
        echo "Admin user updated successfully.\n";
    } else {
        $data['created_at'] = $now;
        $userId = DB::table('users')->insertGetId($data);
        echo "Admin user created successfully.\n";
    }

    if (Schema::hasTable('sessions')) {
        DB::table('sessions')->truncate();
        echo "Sessions table cleared.\n";
    }

    $admin = DB::table('users')->where('id', $userId)->first();

    echo "----------------------------------------\n";
    echo "Login credentials:\n";
    echo "Username: admin\n";
    echo "Password: 12345678\n";
    echo "User ID: {$admin->id}\n";
    echo "Role: {$admin->role}\n";
    echo "Active: {$admin->is_active}\n";
    echo "Password hash valid: " . (Hash::check('12345678', $admin->password) ? 'YES' : 'NO') . "\n";
    echo "----------------------------------------\n";
    echo "Done. Now run:\n";
    echo "php artisan config:clear\n";
    echo "php artisan cache:clear\n";
    echo "php artisan optimize:clear\n";

    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
