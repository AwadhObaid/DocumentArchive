<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_subjects')) {
            Schema::create('book_subjects', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort_order');
            });
        }

        if (Schema::hasTable('documents') && ! Schema::hasColumn('documents', 'book_subject_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignId('book_subject_id')
                    ->nullable()
                    ->after('subject')
                    ->constrained('book_subjects')
                    ->nullOnDelete();

                $table->index('book_subject_id');
            });
        }

        if (Schema::hasTable('book_subjects') && DB::table('book_subjects')->count() === 0) {
            $now = now();
            DB::table('book_subjects')->insert([
                ['name' => 'مطالبة تأمين', 'code' => 'INSURANCE_CLAIM', 'description' => 'موضوع افتراضي قابل للتعديل أو الحذف.', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'شحنة واردة', 'code' => 'INBOUND_SHIPMENT', 'description' => 'موضوع افتراضي قابل للتعديل أو الحذف.', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'شحنة صادرة', 'code' => 'OUTBOUND_SHIPMENT', 'description' => 'موضوع افتراضي قابل للتعديل أو الحذف.', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'إفادة / رد رسمي', 'code' => 'OFFICIAL_REPLY', 'description' => 'موضوع افتراضي قابل للتعديل أو الحذف.', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'book_subject_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropConstrainedForeignId('book_subject_id');
            });
        }

        Schema::dropIfExists('book_subjects');
    }
};
