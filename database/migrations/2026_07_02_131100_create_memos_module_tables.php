<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('memo_counters')) {
            Schema::create('memo_counters', function (Blueprint $table) {
                $table->id();
                $table->string('counter_key')->default('default')->unique();
                $table->unsignedBigInteger('start_number')->default(2600001);
                $table->integer('last_sequence')->default(-1);
                $table->string('last_memo_number')->nullable();
                $table->timestamps();
            });
        }

        DB::table('memo_counters')->insertOrIgnore([
            'counter_key' => 'default',
            'start_number' => 2600001,
            'last_sequence' => -1,
            'last_memo_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! Schema::hasTable('memos')) {
            Schema::create('memos', function (Blueprint $table) {
                $table->id();
                $table->string('memo_number')->unique();
                $table->unsignedInteger('memo_sequence')->default(0);
                $table->date('memo_date');
                $table->text('subject');
                $table->text('description')->nullable();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('sender')->nullable();
                $table->string('receiver')->nullable();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->longText('search_text')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();

                $table->index('memo_number');
                $table->index('memo_date');
                $table->index('status');
                $table->index('department_id');
            });
        }

        if (! Schema::hasTable('memo_attachments')) {
            Schema::create('memo_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
                $table->unsignedInteger('version_no')->default(1);
                $table->boolean('is_main')->default(true);
                $table->string('original_name');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('disk')->default('local');
                $table->string('extension', 20)->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('memo_id');
                $table->index('is_main');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memo_attachments');
        Schema::dropIfExists('memos');
        Schema::dropIfExists('memo_counters');
    }
};
