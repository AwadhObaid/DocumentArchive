<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (! Schema::hasColumn('documents', 'workflow_status')) {
                    $table->string('workflow_status')->default('draft')->after('status');
                }
                if (! Schema::hasColumn('documents', 'workflow_note')) {
                    $table->text('workflow_note')->nullable()->after('workflow_status');
                }
                if (! Schema::hasColumn('documents', 'workflow_submitted_by')) {
                    $table->foreignId('workflow_submitted_by')->nullable()->after('workflow_note')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('documents', 'workflow_submitted_at')) {
                    $table->timestamp('workflow_submitted_at')->nullable()->after('workflow_submitted_by');
                }
                if (! Schema::hasColumn('documents', 'workflow_reviewed_by')) {
                    $table->foreignId('workflow_reviewed_by')->nullable()->after('workflow_submitted_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('documents', 'workflow_reviewed_at')) {
                    $table->timestamp('workflow_reviewed_at')->nullable()->after('workflow_reviewed_by');
                }
                if (! Schema::hasColumn('documents', 'workflow_finalized_by')) {
                    $table->foreignId('workflow_finalized_by')->nullable()->after('workflow_reviewed_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('documents', 'workflow_finalized_at')) {
                    $table->timestamp('workflow_finalized_at')->nullable()->after('workflow_finalized_by');
                }
            });

            DB::table('documents')->whereNull('workflow_status')->update(['workflow_status' => 'draft']);
        }

        if (Schema::hasTable('memos')) {
            Schema::table('memos', function (Blueprint $table) {
                if (! Schema::hasColumn('memos', 'workflow_status')) {
                    $table->string('workflow_status')->default('draft')->after('status');
                }
                if (! Schema::hasColumn('memos', 'workflow_note')) {
                    $table->text('workflow_note')->nullable()->after('workflow_status');
                }
                if (! Schema::hasColumn('memos', 'workflow_submitted_by')) {
                    $table->foreignId('workflow_submitted_by')->nullable()->after('workflow_note')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('memos', 'workflow_submitted_at')) {
                    $table->timestamp('workflow_submitted_at')->nullable()->after('workflow_submitted_by');
                }
                if (! Schema::hasColumn('memos', 'workflow_reviewed_by')) {
                    $table->foreignId('workflow_reviewed_by')->nullable()->after('workflow_submitted_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('memos', 'workflow_reviewed_at')) {
                    $table->timestamp('workflow_reviewed_at')->nullable()->after('workflow_reviewed_by');
                }
                if (! Schema::hasColumn('memos', 'workflow_finalized_by')) {
                    $table->foreignId('workflow_finalized_by')->nullable()->after('workflow_reviewed_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('memos', 'workflow_finalized_at')) {
                    $table->timestamp('workflow_finalized_at')->nullable()->after('workflow_finalized_by');
                }
            });

            DB::table('memos')->whereNull('workflow_status')->update(['workflow_status' => 'draft']);
        }

        if (! Schema::hasTable('workflow_actions')) {
            Schema::create('workflow_actions', function (Blueprint $table) {
                $table->id();
                $table->string('workflowable_type');
                $table->unsignedBigInteger('workflowable_id');
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['workflowable_type', 'workflowable_id'], 'workflow_actions_workflowable_index');
                $table->index(['action', 'created_at'], 'workflow_actions_action_created_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workflow_actions')) {
            Schema::dropIfExists('workflow_actions');
        }

        if (Schema::hasTable('memos')) {
            Schema::table('memos', function (Blueprint $table) {
                foreach (['workflow_submitted_by', 'workflow_reviewed_by', 'workflow_finalized_by'] as $column) {
                    if (Schema::hasColumn('memos', $column)) {
                        try { $table->dropForeign([$column]); } catch (Throwable $e) {}
                    }
                }
            });

            Schema::table('memos', function (Blueprint $table) {
                foreach ([
                    'workflow_finalized_at', 'workflow_finalized_by', 'workflow_reviewed_at', 'workflow_reviewed_by',
                    'workflow_submitted_at', 'workflow_submitted_by', 'workflow_note', 'workflow_status',
                ] as $column) {
                    if (Schema::hasColumn('memos', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                foreach (['workflow_submitted_by', 'workflow_reviewed_by', 'workflow_finalized_by'] as $column) {
                    if (Schema::hasColumn('documents', $column)) {
                        try { $table->dropForeign([$column]); } catch (Throwable $e) {}
                    }
                }
            });

            Schema::table('documents', function (Blueprint $table) {
                foreach ([
                    'workflow_finalized_at', 'workflow_finalized_by', 'workflow_reviewed_at', 'workflow_reviewed_by',
                    'workflow_submitted_at', 'workflow_submitted_by', 'workflow_note', 'workflow_status',
                ] as $column) {
                    if (Schema::hasColumn('documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
