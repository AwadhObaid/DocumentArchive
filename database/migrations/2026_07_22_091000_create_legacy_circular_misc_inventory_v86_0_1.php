<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_circular_misc_import_runs')) {
            Schema::create('legacy_circular_misc_import_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('status', 30)->default('scanning');
                $table->unsignedInteger('total_files')->default(0);
                $table->unsignedInteger('ready_count')->default(0);
                $table->unsignedInteger('needs_review_count')->default(0);
                $table->unsignedInteger('existing_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('unreadable_count')->default(0);
                $table->unsignedInteger('unsupported_count')->default(0);
                $table->unsignedBigInteger('total_size')->default(0);
                $table->text('error_message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('legacy_circular_misc_import_sources')) {
            Schema::create('legacy_circular_misc_import_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->constrained('legacy_circular_misc_import_runs')->cascadeOnDelete();
                $table->text('source_path');
                $table->string('target_module', 30);
                $table->foreignId('category_id')->nullable()->constrained('archive_categories')->nullOnDelete();
                $table->boolean('recursive')->default(true);
                $table->string('default_direction', 30)->nullable();
                $table->string('default_nature', 30)->nullable();
                $table->string('default_entity')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('legacy_circular_misc_import_items')) {
            Schema::create('legacy_circular_misc_import_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->constrained('legacy_circular_misc_import_runs')->cascadeOnDelete();
                $table->foreignId('source_id')->constrained('legacy_circular_misc_import_sources')->cascadeOnDelete();
                $table->string('target_module', 30);
                $table->foreignId('category_id')->nullable()->constrained('archive_categories')->nullOnDelete();
                $table->text('source_path');
                $table->text('relative_path')->nullable();
                $table->string('file_name');
                $table->string('extension', 30)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->timestamp('file_modified_at')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->date('proposed_date')->nullable();
                $table->text('proposed_subject')->nullable();
                $table->string('proposed_number')->nullable();
                $table->string('proposed_original_number')->nullable();
                $table->string('proposed_entity')->nullable();
                $table->string('proposed_direction', 30)->nullable();
                $table->string('proposed_nature', 30)->nullable();
                $table->string('status', 40)->default('ready');
                $table->foreignId('duplicate_of_item_id')->nullable()
                    ->constrained('legacy_circular_misc_import_items')->nullOnDelete();
                $table->string('existing_type', 30)->nullable();
                $table->unsignedBigInteger('existing_id')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('circular_id')->nullable()->constrained('circulars')->nullOnDelete();
                $table->foreignId('misc_book_id')->nullable()->constrained('misc_books')->nullOnDelete();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
            });
        }

        // MySQL limits identifier names to 64 characters. Use explicit short names.
        // These checks also repair a table left behind by a previously failed migration.
        $this->ensureIndex(
            'legacy_circular_misc_import_runs',
            ['status', 'created_at'],
            'lcm_runs_status_created_idx'
        );

        $this->ensureIndex(
            'legacy_circular_misc_import_sources',
            ['run_id', 'target_module'],
            'lcm_sources_run_module_idx'
        );

        $this->ensureIndex(
            'legacy_circular_misc_import_items',
            ['run_id', 'status'],
            'lcm_items_run_status_idx'
        );

        $this->ensureIndex(
            'legacy_circular_misc_import_items',
            ['target_module', 'category_id'],
            'lcm_items_module_category_idx'
        );

        $this->ensureIndex(
            'legacy_circular_misc_import_items',
            ['sha256'],
            'lcm_items_sha256_idx'
        );

        $this->ensureIndex(
            'legacy_circular_misc_import_items',
            ['existing_type', 'existing_id'],
            'lcm_items_existing_ref_idx'
        );

        if (
            Schema::hasTable('legacy_archive_import_sources')
            && Schema::hasColumn('legacy_archive_import_sources', 'target_module')
            && DB::table('legacy_archive_import_sources')->count() === 0
        ) {
            Schema::drop('legacy_archive_import_sources');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_circular_misc_import_items');
        Schema::dropIfExists('legacy_circular_misc_import_sources');
        Schema::dropIfExists('legacy_circular_misc_import_runs');
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureIndex(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || $this->hasIndexForColumns($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function hasIndexForColumns(string $table, array $columns): bool
    {
        $indexes = DB::select(
            <<<'SQL'
SELECT
    INDEX_NAME AS index_name,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS indexed_columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = ?
GROUP BY INDEX_NAME
SQL,
            [$table]
        );

        $expected = implode(',', $columns);

        foreach ($indexes as $index) {
            if ((string) ($index->indexed_columns ?? '') === $expected) {
                return true;
            }
        }

        return false;
    }
};
