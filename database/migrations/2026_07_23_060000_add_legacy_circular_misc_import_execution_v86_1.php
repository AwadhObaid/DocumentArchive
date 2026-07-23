<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legacy_circular_misc_import_runs')) {
            $missing = [
                'import_status' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'import_status'
                ),
                'import_total' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'import_total'
                ),
                'imported_files' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'imported_files'
                ),
                'import_failed_files' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'import_failed_files'
                ),
                'import_skipped_files' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'import_skipped_files'
                ),
                'imported_by' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'imported_by'
                ),
                'import_started_at' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'import_started_at'
                ),
                'import_finished_at' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_runs',
                    'import_finished_at'
                ),
            ];

            Schema::table(
                'legacy_circular_misc_import_runs',
                function (Blueprint $table) use ($missing): void {
                    if ($missing['import_status']) {
                        $table->string('import_status', 40)
                            ->default('not_started');
                    }
                    if ($missing['import_total']) {
                        $table->unsignedInteger('import_total')->default(0);
                    }
                    if ($missing['imported_files']) {
                        $table->unsignedInteger('imported_files')->default(0);
                    }
                    if ($missing['import_failed_files']) {
                        $table->unsignedInteger('import_failed_files')->default(0);
                    }
                    if ($missing['import_skipped_files']) {
                        $table->unsignedInteger('import_skipped_files')->default(0);
                    }
                    if ($missing['imported_by']) {
                        $table->unsignedBigInteger('imported_by')->nullable();
                    }
                    if ($missing['import_started_at']) {
                        $table->timestamp('import_started_at')->nullable();
                    }
                    if ($missing['import_finished_at']) {
                        $table->timestamp('import_finished_at')->nullable();
                    }
                }
            );
        }

        if (Schema::hasTable('legacy_circular_misc_import_items')) {
            $missing = [
                'import_attempts' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'import_attempts'
                ),
                'import_error' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'import_error'
                ),
                'copied_file_size' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'copied_file_size'
                ),
                'copied_sha256' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'copied_sha256'
                ),
                'imported_by' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'imported_by'
                ),
                'source_verified_at' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'source_verified_at'
                ),
                'circular_attachment_id' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'circular_attachment_id'
                ),
                'misc_book_attachment_id' => ! Schema::hasColumn(
                    'legacy_circular_misc_import_items',
                    'misc_book_attachment_id'
                ),
            ];

            Schema::table(
                'legacy_circular_misc_import_items',
                function (Blueprint $table) use ($missing): void {
                    if ($missing['import_attempts']) {
                        $table->unsignedInteger('import_attempts')->default(0);
                    }
                    if ($missing['import_error']) {
                        $table->text('import_error')->nullable();
                    }
                    if ($missing['copied_file_size']) {
                        $table->unsignedBigInteger('copied_file_size')->nullable();
                    }
                    if ($missing['copied_sha256']) {
                        $table->char('copied_sha256', 64)->nullable();
                    }
                    if ($missing['imported_by']) {
                        $table->unsignedBigInteger('imported_by')->nullable();
                    }
                    if ($missing['source_verified_at']) {
                        $table->timestamp('source_verified_at')->nullable();
                    }
                    if ($missing['circular_attachment_id']) {
                        $table->unsignedBigInteger('circular_attachment_id')->nullable();
                    }
                    if ($missing['misc_book_attachment_id']) {
                        $table->unsignedBigInteger('misc_book_attachment_id')->nullable();
                    }
                }
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legacy_circular_misc_import_items')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('legacy_circular_misc_import_items', 'import_attempts')
                    ? 'import_attempts' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'import_error')
                    ? 'import_error' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'copied_file_size')
                    ? 'copied_file_size' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'copied_sha256')
                    ? 'copied_sha256' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'imported_by')
                    ? 'imported_by' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'source_verified_at')
                    ? 'source_verified_at' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'circular_attachment_id')
                    ? 'circular_attachment_id' : null,
                Schema::hasColumn('legacy_circular_misc_import_items', 'misc_book_attachment_id')
                    ? 'misc_book_attachment_id' : null,
            ]));

            if ($columns) {
                Schema::table(
                    'legacy_circular_misc_import_items',
                    fn (Blueprint $table) => $table->dropColumn($columns)
                );
            }
        }

        if (Schema::hasTable('legacy_circular_misc_import_runs')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('legacy_circular_misc_import_runs', 'import_status')
                    ? 'import_status' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'import_total')
                    ? 'import_total' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'imported_files')
                    ? 'imported_files' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'import_failed_files')
                    ? 'import_failed_files' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'import_skipped_files')
                    ? 'import_skipped_files' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'imported_by')
                    ? 'imported_by' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'import_started_at')
                    ? 'import_started_at' : null,
                Schema::hasColumn('legacy_circular_misc_import_runs', 'import_finished_at')
                    ? 'import_finished_at' : null,
            ]));

            if ($columns) {
                Schema::table(
                    'legacy_circular_misc_import_runs',
                    fn (Blueprint $table) => $table->dropColumn($columns)
                );
            }
        }
    }
};
