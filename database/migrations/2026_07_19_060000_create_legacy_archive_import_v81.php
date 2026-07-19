<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            $missing = [
                'legacy_source' => ! Schema::hasColumn('documents', 'legacy_source'),
                'legacy_record_id' => ! Schema::hasColumn('documents', 'legacy_record_id'),
                'legacy_user_name' => ! Schema::hasColumn('documents', 'legacy_user_name'),
                'legacy_archive_folder' => ! Schema::hasColumn('documents', 'legacy_archive_folder'),
                'legacy_sader_id' => ! Schema::hasColumn('documents', 'legacy_sader_id'),
                'legacy_original_sader_id' => ! Schema::hasColumn('documents', 'legacy_original_sader_id'),
            ];

            Schema::table('documents', function (Blueprint $table) use ($missing) {
                if ($missing['legacy_source']) {
                    $table->string('legacy_source', 100)->nullable()->after('notes');
                }
                if ($missing['legacy_record_id']) {
                    $table->unsignedBigInteger('legacy_record_id')->nullable()->after('legacy_source');
                }
                if ($missing['legacy_user_name']) {
                    $table->string('legacy_user_name')->nullable()->after('legacy_record_id');
                }
                if ($missing['legacy_archive_folder']) {
                    $table->string('legacy_archive_folder', 1000)->nullable()->after('legacy_user_name');
                }
                if ($missing['legacy_sader_id']) {
                    $table->string('legacy_sader_id')->nullable()->after('legacy_archive_folder');
                }
                if ($missing['legacy_original_sader_id']) {
                    $table->string('legacy_original_sader_id')->nullable()->after('legacy_sader_id');
                }
            });

            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->unique(
                        ['legacy_source', 'legacy_record_id'],
                        'documents_legacy_source_record_unique'
                    );
                });
            } catch (Throwable) {
                // The unique index may already exist on an updated copy.
            }
        }

        if (! Schema::hasTable('legacy_archive_import_runs')) {
            Schema::create('legacy_archive_import_runs', function (Blueprint $table) {
                $table->id();
                $table->string('source_name', 100)->default('ESIS_TbArchive');
                $table->longText('source_file_path');
                $table->string('original_filename')->nullable();
                $table->string('mode', 30)->default('dry_run');
                $table->string('status', 40)->default('new');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('ready_rows')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedInteger('duplicate_rows')->default(0);
                $table->unsignedInteger('missing_file_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->unsignedInteger('skipped_rows')->default(0);
                $table->unsignedInteger('copied_files')->default(0);
                $table->unsignedBigInteger('copied_bytes')->default(0);
                $table->longText('source_root_override')->nullable();
                $table->boolean('import_missing_without_file')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->json('notes')->nullable();
                $table->timestamps();

                $table->index(['mode', 'status']);
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('legacy_archive_import_items')) {
            Schema::create('legacy_archive_import_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->constrained('legacy_archive_import_runs')->cascadeOnDelete();
                $table->unsignedBigInteger('source_record_id')->nullable();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->string('reference_number')->nullable();
                $table->string('status', 50)->default('ready');
                $table->longText('message')->nullable();
                $table->longText('source_path')->nullable();
                $table->longText('resolved_source_path')->nullable();
                $table->longText('target_path')->nullable();
                $table->boolean('file_exists')->default(false);
                $table->boolean('file_copied')->default(false);
                $table->unsignedBigInteger('source_size')->nullable();
                $table->unsignedBigInteger('target_size')->nullable();
                $table->string('source_sha256', 64)->nullable();
                $table->string('target_sha256', 64)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['run_id', 'status']);
                $table->index('source_record_id');
                $table->index('reference_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_archive_import_items');
        Schema::dropIfExists('legacy_archive_import_runs');

        if (Schema::hasTable('documents')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->dropUnique('documents_legacy_source_record_unique');
                });
            } catch (Throwable) {
                // Ignore missing index.
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('documents', 'legacy_original_sader_id') ? 'legacy_original_sader_id' : null,
                Schema::hasColumn('documents', 'legacy_sader_id') ? 'legacy_sader_id' : null,
                Schema::hasColumn('documents', 'legacy_archive_folder') ? 'legacy_archive_folder' : null,
                Schema::hasColumn('documents', 'legacy_user_name') ? 'legacy_user_name' : null,
                Schema::hasColumn('documents', 'legacy_record_id') ? 'legacy_record_id' : null,
                Schema::hasColumn('documents', 'legacy_source') ? 'legacy_source' : null,
            ]));

            if ($columns !== []) {
                Schema::table('documents', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
