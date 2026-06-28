<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('unique_key')->nullable();
                $table->string('type')->default('info');
                $table->string('title');
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->string('url')->nullable();
                $table->string('link')->nullable();
                $table->string('source')->nullable();
                $table->json('payload')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('dismissed_at')->nullable();
                $table->timestamp('hidden_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('system_notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'unique_key')) {
                $table->string('unique_key')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'type')) {
                $table->string('type')->default('info')->index();
            }
            if (! Schema::hasColumn('system_notifications', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'message')) {
                $table->text('message')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'body')) {
                $table->text('body')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'url')) {
                $table->string('url')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'link')) {
                $table->string('link')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'source')) {
                $table->string('source')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'payload')) {
                $table->json('payload')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'data')) {
                $table->json('data')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // لا نحذف الجدول حتى لا نفقد الإشعارات المحفوظة.
    }
};