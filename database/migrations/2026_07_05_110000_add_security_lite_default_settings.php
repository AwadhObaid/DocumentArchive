<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        $defaults = [
            'auto_logout_enabled' => ['0', 'security', 'boolean', 'تفعيل تسجيل الخروج التلقائي عند عدم النشاط'],
            'auto_logout_minutes' => ['30', 'security', 'number', 'مدة الخمول بالدقائق قبل تسجيل الخروج التلقائي'],
            'auto_logout_warning_seconds' => ['60', 'security', 'number', 'مدة ظهور تنبيه الخروج قبل انتهاء الجلسة بالثواني'],
            'lite_notification_poll_seconds' => ['30', 'lite', 'number', 'مدة تحديث إشعارات نسخة الهاتف لايت بالثواني'],
        ];

        foreach ($defaults as $key => [$value, $group, $type, $description]) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'group' => $group,
                    'type' => $type,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $brandIcon = DB::table('settings')->where('key', 'system_brand_icon')->value('value');
        if (in_array($brandIcon, ['🏠', '🏡'], true)) {
            DB::table('settings')
                ->where('key', 'system_brand_icon')
                ->update([
                    'value' => '🗂️',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // لا نحذف الإعدادات عند الرجوع حتى لا نفقد اختيار المدير.
    }
};
