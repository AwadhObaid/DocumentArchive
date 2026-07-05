<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureMemoIdColumn('email_messages');
        $this->ensureMemoIdColumn('whatsapp_messages');
        $this->ensureSharedAttachmentLinksColumns();
        $this->ensureSharedAttachmentLinkItemsColumns();
    }

    public function down(): void
    {
        // This migration is an idempotent repair migration. Down intentionally does nothing
        // to avoid removing columns that may already be used by real memo records.
    }

    private function ensureMemoIdColumn(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        if (!Schema::hasColumn($table, 'memo_id')) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                if (Schema::hasColumn($table, 'document_id')) {
                    $tableBlueprint->unsignedBigInteger('memo_id')->nullable()->after('document_id');
                } else {
                    $tableBlueprint->unsignedBigInteger('memo_id')->nullable();
                }
            });
        }

        $this->addIndexIfPossible($table, 'memo_id', $table . '_memo_id_index');
        $this->addForeignKeyIfPossible($table, 'memo_id', 'memos', 'id', $table . '_memo_id_foreign', 'SET NULL');
    }

    private function ensureSharedAttachmentLinksColumns(): void
    {
        $table = 'shared_attachment_links';

        if (!Schema::hasTable($table)) {
            return;
        }

        if (!Schema::hasColumn($table, 'memo_id')) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                if (Schema::hasColumn($table, 'document_id')) {
                    $tableBlueprint->unsignedBigInteger('memo_id')->nullable()->after('document_id');
                } else {
                    $tableBlueprint->unsignedBigInteger('memo_id')->nullable();
                }
            });
        }

        $this->makeColumnNullableIfPossible($table, 'document_id', 'BIGINT UNSIGNED');
        $this->addIndexIfPossible($table, 'memo_id', 'shared_attachment_links_memo_id_index');
        $this->addForeignKeyIfPossible($table, 'memo_id', 'memos', 'id', 'shared_attachment_links_memo_id_foreign', 'CASCADE');
    }

    private function ensureSharedAttachmentLinkItemsColumns(): void
    {
        $table = 'shared_attachment_link_items';

        if (!Schema::hasTable($table)) {
            return;
        }

        if (!Schema::hasColumn($table, 'memo_attachment_id')) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                if (Schema::hasColumn($table, 'document_attachment_id')) {
                    $tableBlueprint->unsignedBigInteger('memo_attachment_id')->nullable()->after('document_attachment_id');
                } else {
                    $tableBlueprint->unsignedBigInteger('memo_attachment_id')->nullable();
                }
            });
        }

        $this->makeColumnNullableIfPossible($table, 'document_attachment_id', 'BIGINT UNSIGNED');
        $this->addIndexIfPossible($table, 'memo_attachment_id', 'shared_attachment_link_items_memo_attachment_id_index');
        $this->addForeignKeyIfPossible($table, 'memo_attachment_id', 'memo_attachments', 'id', 'shared_attachment_link_items_memo_attachment_id_foreign', 'CASCADE');
    }

    private function makeColumnNullableIfPossible(string $table, string $column, string $type): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NULL");
        } catch (\Throwable $e) {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_{$column}_foreign`");
            } catch (\Throwable $ignored) {
            }

            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NULL");
            } catch (\Throwable $ignored) {
            }
        }
    }

    private function addIndexIfPossible(string $table, string $column, string $indexName): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        } catch (\Throwable $e) {
        }
    }

    private function addForeignKeyIfPossible(string $table, string $column, string $refTable, string $refColumn, string $constraintName, string $onDelete): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasTable($refTable) || DB::getDriverName() !== 'mysql') {
            return;
        }

        $deleteSql = strtoupper($onDelete) === 'SET NULL' ? 'SET NULL' : 'CASCADE';

        try {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`) ON DELETE {$deleteSql}");
        } catch (\Throwable $e) {
        }
    }
};
