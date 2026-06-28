<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('type')->default('info');
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->text('message')->nullable();
                $table->string('link')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->boolean('is_hidden')->default(false)->index();
                $table->timestamp('hidden_at')->nullable()->index();
                $table->timestamps();
            });
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('link');
            }
            if (!Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->index()->after('read_at');
            }
            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->index()->after('is_hidden');
            }
        });
    }

    public function down(): void
    {
        // لا نحذف الأعمدة حفاظاً على بيانات الإشعارات.
    }
};