<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'legacy_memo_import_runs',
            function (Blueprint $table) {
                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'import_status'
                )) {
                    $table->string('import_status')
                        ->default('not_started')
                        ->after('notes')
                        ->index();
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'import_total'
                )) {
                    $table->unsignedInteger('import_total')
                        ->default(0)
                        ->after('import_status');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'imported_files'
                )) {
                    $table->unsignedInteger('imported_files')
                        ->default(0)
                        ->after('import_total');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'import_failed_files'
                )) {
                    $table->unsignedInteger('import_failed_files')
                        ->default(0)
                        ->after('imported_files');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'import_skipped_files'
                )) {
                    $table->unsignedInteger('import_skipped_files')
                        ->default(0)
                        ->after('import_failed_files');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'imported_by'
                )) {
                    $table->foreignId('imported_by')
                        ->nullable()
                        ->after('import_skipped_files')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'import_started_at'
                )) {
                    $table->timestamp('import_started_at')
                        ->nullable()
                        ->after('imported_by');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'import_finished_at'
                )) {
                    $table->timestamp('import_finished_at')
                        ->nullable()
                        ->after('import_started_at');
                }
            }
        );

        Schema::table(
            'legacy_memo_import_items',
            function (Blueprint $table) {
                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'imported_attachment_id'
                )) {
                    $table->foreignId('imported_attachment_id')
                        ->nullable()
                        ->after('memo_id')
                        ->constrained('memo_attachments')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'import_attempts'
                )) {
                    $table->unsignedInteger('import_attempts')
                        ->default(0)
                        ->after('payload');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'imported_by'
                )) {
                    $table->foreignId('imported_by')
                        ->nullable()
                        ->after('import_attempts')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'imported_at'
                )) {
                    $table->timestamp('imported_at')
                        ->nullable()
                        ->after('imported_by');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'source_verified_at'
                )) {
                    $table->timestamp('source_verified_at')
                        ->nullable()
                        ->after('imported_at');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'copied_file_size'
                )) {
                    $table->unsignedBigInteger('copied_file_size')
                        ->nullable()
                        ->after('source_verified_at');
                }

                if (! Schema::hasColumn(
                    'legacy_memo_import_items',
                    'copied_sha256'
                )) {
                    $table->char('copied_sha256', 64)
                        ->nullable()
                        ->after('copied_file_size');
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'legacy_memo_import_items',
            function (Blueprint $table) {
                if (Schema::hasColumn(
                    'legacy_memo_import_items',
                    'imported_attachment_id'
                )) {
                    $table->dropConstrainedForeignId(
                        'imported_attachment_id'
                    );
                }

                if (Schema::hasColumn(
                    'legacy_memo_import_items',
                    'imported_by'
                )) {
                    $table->dropConstrainedForeignId('imported_by');
                }

                foreach (
                    [
                        'import_attempts',
                        'imported_at',
                        'source_verified_at',
                        'copied_file_size',
                        'copied_sha256',
                    ] as $column
                ) {
                    if (Schema::hasColumn(
                        'legacy_memo_import_items',
                        $column
                    )) {
                        $table->dropColumn($column);
                    }
                }
            }
        );

        Schema::table(
            'legacy_memo_import_runs',
            function (Blueprint $table) {
                if (Schema::hasColumn(
                    'legacy_memo_import_runs',
                    'imported_by'
                )) {
                    $table->dropConstrainedForeignId('imported_by');
                }

                foreach (
                    [
                        'import_status',
                        'import_total',
                        'imported_files',
                        'import_failed_files',
                        'import_skipped_files',
                        'import_started_at',
                        'import_finished_at',
                    ] as $column
                ) {
                    if (Schema::hasColumn(
                        'legacy_memo_import_runs',
                        $column
                    )) {
                        $table->dropColumn($column);
                    }
                }
            }
        );
    }
};
