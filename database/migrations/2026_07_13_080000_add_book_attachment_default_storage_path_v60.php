<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_attachments') && ! Schema::hasColumn('document_attachments', 'storage_root_path')) {
            Schema::table('document_attachments', function (Blueprint $table) {
                $table->text('storage_root_path')->nullable()->after('disk');
            });
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'book_attachment_storage_root'],
                [
                    'value' => '',
                    'group' => 'book_attachments',
                    'type' => 'text',
                    'description' => 'المسار الافتراضي الخارجي لحفظ مرفقات الكتب. إذا ترك فارغاً يستخدم النظام storage/app/private داخل المشروع.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('document_attachments') && Schema::hasColumn('document_attachments', 'storage_root_path')) {
            Schema::table('document_attachments', function (Blueprint $table) {
                $table->dropColumn('storage_root_path');
            });
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'book_attachment_storage_root')->delete();
        }
    }
};
