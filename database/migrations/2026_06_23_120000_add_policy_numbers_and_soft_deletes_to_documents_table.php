<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'main_policy_number')) {
                $table->string('main_policy_number')->nullable()->after('reference_date');
            }

            if (!Schema::hasColumn('documents', 'sub_policy_number')) {
                $table->string('sub_policy_number')->nullable()->after('main_policy_number');
            }

            if (!Schema::hasColumn('documents', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'main_policy_number')) {
                $table->dropColumn('main_policy_number');
            }

            if (Schema::hasColumn('documents', 'sub_policy_number')) {
                $table->dropColumn('sub_policy_number');
            }

            if (Schema::hasColumn('documents', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
