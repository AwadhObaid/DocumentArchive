<?php

use App\Models\User;
use App\Support\PermissionRegistry;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = 0;

User::query()->orderBy('id')->chunk(100, function ($users) use (&$count) {
    foreach ($users as $user) {
        $role = $user->role;
        $permissions = $role === 'admin'
            ? ['*']
            : PermissionRegistry::defaultsForRole($role);

        $user->forceFill(['permissions' => $permissions])->save();
        $count++;
    }
});

echo "Permissions defaults applied to {$count} users." . PHP_EOL;
