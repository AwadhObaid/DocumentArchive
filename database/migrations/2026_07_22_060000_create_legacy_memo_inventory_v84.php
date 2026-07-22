<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MEMO_START_NUMBER = 2600000;

    public function up(): void
    {
        Schema::table('memo_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('memo_attachments', 'sha256')) {
                $table->char('sha256', 64)
                    ->nullable()
                    ->after('file_size')
                    ->index();
            }

            if (! Schema::hasColumn('memo_attachments', 'source_path')) {
                $table->text('source_path')
                    ->nullable()
                    ->after('sha256');
            }
        });

        if (! Schema::hasTable('legacy_memo_import_runs')) {
            Schema::create(
                'legacy_memo_import_runs',
                function (Blueprint $table) {
                    $table->id();
                    $table->text('source_root');
                    $table->boolean('recursive')->default(true);
                    $table->string('status')->default('processing');
                    $table->unsignedInteger('total_files')->default(0);
                    $table->unsignedInteger('supported_files')->default(0);
                    $table->unsignedInteger('ready_files')->default(0);
                    $table->unsignedInteger('needs_review_files')->default(0);
                    $table->unsignedInteger('duplicate_system_files')->default(0);
                    $table->unsignedInteger('duplicate_scan_files')->default(0);
                    $table->unsignedInteger('unreadable_files')->default(0);
                    $table->unsignedInteger('unsupported_files')->default(0);
                    $table->unsignedInteger('hashed_files')->default(0);
                    $table->unsignedBigInteger('bytes_total')->default(0);
                    $table->foreignId('created_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                    $table->timestamp('started_at')->nullable();
                    $table->timestamp('finished_at')->nullable();
                    $table->json('notes')->nullable();
                    $table->timestamps();

                    $table->index('status');
                    $table->index('created_at');
                }
            );
        }

        if (! Schema::hasTable('legacy_memo_import_items')) {
            Schema::create(
                'legacy_memo_import_items',
                function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('run_id')
                        ->constrained('legacy_memo_import_runs')
                        ->cascadeOnDelete();
                    $table->foreignId('memo_id')
                        ->nullable()
                        ->constrained('memos')
                        ->nullOnDelete();
                    $table->string('status');
                    $table->text('message')->nullable();
                    $table->text('source_path');
                    $table->text('relative_path')->nullable();
                    $table->string('original_name');
                    $table->string('extension', 20)->nullable();
                    $table->string('mime_type')->nullable();
                    $table->unsignedBigInteger('file_size')->default(0);
                    $table->timestamp('modified_at')->nullable();
                    $table->char('sha256', 64)->nullable();
                    $table->string('proposed_memo_number')->nullable();
                    $table->unsignedInteger('proposed_memo_sequence')->nullable();
                    $table->date('proposed_memo_date')->nullable();
                    $table->text('proposed_subject')->nullable();
                    $table->string('proposed_sender')->nullable();
                    $table->string('proposed_receiver')->nullable();
                    $table->foreignId('proposed_department_id')
                        ->nullable()
                        ->constrained('departments')
                        ->nullOnDelete();
                    $table->json('payload')->nullable();
                    $table->timestamps();

                    $table->index(['run_id', 'status']);
                    $table->index('sha256');
                    $table->index('proposed_memo_number');
                }
            );
        }

        $this->normalizeMemoCounter();
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_memo_import_items');
        Schema::dropIfExists('legacy_memo_import_runs');

        Schema::table('memo_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('memo_attachments', 'sha256')) {
                $table->dropIndex(['sha256']);
                $table->dropColumn('sha256');
            }

            if (Schema::hasColumn('memo_attachments', 'source_path')) {
                $table->dropColumn('source_path');
            }
        });
    }

    private function normalizeMemoCounter(): void
    {
        if (! Schema::hasTable('memo_counters')
            || ! Schema::hasTable('memos')) {
            return;
        }

        DB::table('memo_counters')->insertOrIgnore([
            'counter_key' => 'default',
            'start_number' => self::MEMO_START_NUMBER,
            'last_sequence' => -1,
            'last_memo_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('memo_counters')
            ->where('counter_key', 'default')
            ->first();

        $maxSequence = -1;

        if ($counter
            && is_numeric($counter->last_memo_number ?? null)) {
            $lastNumber = (int) $counter->last_memo_number;

            if ($lastNumber >= self::MEMO_START_NUMBER) {
                $maxSequence = max(
                    $maxSequence,
                    $lastNumber - self::MEMO_START_NUMBER
                );
            }
        } elseif ($counter
            && (int) ($counter->last_sequence ?? -1) >= 0) {
            $oldStart = (int) (
                $counter->start_number
                    ?: self::MEMO_START_NUMBER
            );
            $oldLastNumber = $oldStart
                + (int) $counter->last_sequence;

            if ($oldLastNumber >= self::MEMO_START_NUMBER) {
                $maxSequence = max(
                    $maxSequence,
                    $oldLastNumber - self::MEMO_START_NUMBER
                );
            }
        }

        DB::table('memos')
            ->select(['id', 'memo_number'])
            ->orderBy('id')
            ->chunkById(500, function ($memos) use (&$maxSequence) {
                foreach ($memos as $memo) {
                    $number = trim((string) $memo->memo_number);

                    if ($number === '' || ! ctype_digit($number)) {
                        continue;
                    }

                    $numeric = (int) $number;

                    if ($numeric < self::MEMO_START_NUMBER) {
                        continue;
                    }

                    $sequence = $numeric - self::MEMO_START_NUMBER;
                    $maxSequence = max($maxSequence, $sequence);

                    DB::table('memos')
                        ->where('id', $memo->id)
                        ->update(['memo_sequence' => $sequence]);
                }
            });

        DB::table('memo_counters')
            ->where('counter_key', 'default')
            ->update([
                'start_number' => self::MEMO_START_NUMBER,
                'last_sequence' => $maxSequence,
                'last_memo_number' => $maxSequence >= 0
                    ? (string) (
                        self::MEMO_START_NUMBER + $maxSequence
                    )
                    : null,
                'updated_at' => now(),
            ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE memo_counters '
                . 'MODIFY start_number BIGINT UNSIGNED NOT NULL '
                . 'DEFAULT 2600000'
            );
        }
    }
};
