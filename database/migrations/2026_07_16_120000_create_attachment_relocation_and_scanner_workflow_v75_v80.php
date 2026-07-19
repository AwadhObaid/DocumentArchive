<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attachment_relocation_runs')) {
            Schema::create('attachment_relocation_runs', function (Blueprint $table) {
                $table->id();
                $table->string('mode', 30)->default('dry_run');
                $table->string('status', 30)->default('new');
                $table->boolean('delete_original')->default(false);
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('ready_items')->default(0);
                $table->unsignedInteger('moved_items')->default(0);
                $table->unsignedInteger('copied_items')->default(0);
                $table->unsignedInteger('skipped_items')->default(0);
                $table->unsignedInteger('missing_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->longText('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attachment_relocation_items')) {
            Schema::create('attachment_relocation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->constrained('attachment_relocation_runs')->cascadeOnDelete();
                $table->foreignId('document_attachment_id')->nullable()->constrained('document_attachments')->nullOnDelete();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->string('reference_number')->nullable();
                $table->longText('original_path')->nullable();
                $table->string('original_disk', 80)->nullable();
                $table->longText('original_root_path')->nullable();
                $table->longText('target_path')->nullable();
                $table->string('target_disk', 80)->nullable();
                $table->longText('target_root_path')->nullable();
                $table->string('status', 50)->default('ready');
                $table->longText('message')->nullable();
                $table->boolean('source_exists')->default(false);
                $table->boolean('target_exists')->default(false);
                $table->unsignedBigInteger('file_size')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['run_id', 'status']);
                $table->index(['document_id']);
            });
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'scanner_inbox_path'],
                [
                    'value' => storage_path('app/scanner-inbox'),
                    'group' => 'scanner',
                    'type' => 'text',
                    'description' => 'مجلد صندوق الماسح الضوئي الذي يحفظ فيه برنامج المسح الملفات قبل ربطها بالكتب.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_relocation_items');
        Schema::dropIfExists('attachment_relocation_runs');

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'scanner_inbox_path')->delete();
        }
    }
};
