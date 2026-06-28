<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('key')->index();
                $table->string('label')->nullable();
                $table->string('category')->nullable()->index();
                $table->boolean('enabled')->default(true)->index();
                $table->string('applies_to')->default('global');
                $table->timestamps();
                $table->index(['user_id', 'key']);
            });
            return;
        }

        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'key')) {
                $table->string('key')->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'label')) {
                $table->string('label')->nullable();
            }
            if (! Schema::hasColumn('notification_preferences', 'category')) {
                $table->string('category')->nullable()->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'enabled')) {
                $table->boolean('enabled')->default(true)->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'applies_to')) {
                $table->string('applies_to')->default('global');
            }
            if (! Schema::hasColumn('notification_preferences', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // لا نحذف تفضيلات الإشعارات عند الرجوع حتى لا تضيع إعدادات المدير.
    }
};