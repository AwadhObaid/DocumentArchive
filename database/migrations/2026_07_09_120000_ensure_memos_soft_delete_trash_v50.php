<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('memos')) {
            return;
        }

        Schema::table('memos', function (Blueprint $table) {
            if (! Schema::hasColumn('memos', 'deleted_at')) {
                $table->softDeletes()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('memos') || ! Schema::hasColumn('memos', 'deleted_at')) {
            return;
        }

        Schema::table('memos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
