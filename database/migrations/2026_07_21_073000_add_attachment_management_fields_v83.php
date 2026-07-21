<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('document_attachments', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->after('uploaded_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('document_attachments', 'deletion_reason')) {
                $table->text('deletion_reason')
                    ->nullable()
                    ->after('deleted_by');
            }

            if (! Schema::hasColumn('document_attachments', 'replaces_attachment_id')) {
                $table->foreignId('replaces_attachment_id')
                    ->nullable()
                    ->after('deletion_reason')
                    ->constrained('document_attachments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('document_attachments', 'replaced_by_attachment_id')) {
                $table->foreignId('replaced_by_attachment_id')
                    ->nullable()
                    ->after('replaces_attachment_id')
                    ->constrained('document_attachments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('document_attachments', 'replacement_reason')) {
                $table->text('replacement_reason')
                    ->nullable()
                    ->after('replaced_by_attachment_id');
            }

            if (! Schema::hasColumn('document_attachments', 'replaced_at')) {
                $table->timestamp('replaced_at')
                    ->nullable()
                    ->after('replacement_reason');
            }

            if (! Schema::hasColumn('document_attachments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('document_attachments', 'replaced_by_attachment_id')) {
                $table->dropConstrainedForeignId('replaced_by_attachment_id');
            }

            if (Schema::hasColumn('document_attachments', 'replaces_attachment_id')) {
                $table->dropConstrainedForeignId('replaces_attachment_id');
            }

            if (Schema::hasColumn('document_attachments', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }

            $columns = [
                'replacement_reason',
                'replaced_at',
                'deletion_reason',
                'deleted_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('document_attachments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
