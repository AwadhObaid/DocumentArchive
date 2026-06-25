<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!Schema::hasTable('users')) {
    fwrite(STDERR, "ERROR: users table does not exist. Run migrations or restore a valid backup first.\n");
    exit(1);
}

$now = now();
$data = [
    'name' => 'Admin',
    'password' => Hash::make('12345678'),
    'role' => 'admin',
    'is_active' => 1,
];

if (Schema::hasColumn('users', 'email')) {
    $data['email'] = 'admin@documentarchive.local';
}
if (Schema::hasColumn('users', 'phone')) {
    $data['phone'] = null;
}
if (Schema::hasColumn('users', 'email_verified_at')) {
    $data['email_verified_at'] = null;
}
if (Schema::hasColumn('users', 'remember_token')) {
    $data['remember_token'] = null;
}
if (Schema::hasColumn('users', 'updated_at')) {
    $data['updated_at'] = $now;
}

if (DB::table('users')->where('username', 'admin')->exists()) {
    DB::table('users')->where('username', 'admin')->update($data);
} else {
    $insert = array_merge(['username' => 'admin'], $data);
    if (Schema::hasColumn('users', 'created_at')) {
        $insert['created_at'] = $now;
    }
    DB::table('users')->insert($insert);
}

$hash = (string) DB::table('users')->where('username', 'admin')->value('password');
$ok = Hash::check('12345678', $hash) ? 'YES' : 'NO';

echo "Admin user repaired successfully.\n";
echo "Username: admin\n";
echo "Password: 12345678\n";
echo "Role: admin\n";
echo "Password hash valid: {$ok}\n";
