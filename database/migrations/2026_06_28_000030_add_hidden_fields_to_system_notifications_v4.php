<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('read_at');
            }

            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('is_hidden');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->dropColumn('hidden_at');
            }

            if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->dropColumn('is_hidden');
            }
        });
    }
};