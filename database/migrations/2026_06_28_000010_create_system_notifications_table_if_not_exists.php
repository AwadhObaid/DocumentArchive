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
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type')->default('info')->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->string('url')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamp('hidden_at')->nullable()->index();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['user_id', 'hidden_at']);
            });

            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (!Schema::hasColumn('system_notifications', 'type')) {
                $table->string('type')->default('info')->index();
            }
            if (!Schema::hasColumn('system_notifications', 'title')) {
                $table->string('title')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'message')) {
                $table->text('message')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'body')) {
                $table->text('body')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'url')) {
                $table->string('url')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'data')) {
                $table->json('data')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->index();
            }
            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->index();
            }
            if (!Schema::hasColumn('system_notifications', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // لا نحذف الجدول عند الرجوع حتى لا نفقد الإشعارات المحفوظة.
    }
};