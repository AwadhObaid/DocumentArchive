<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_chat_participants')) {
            return;
        }

        if (! Schema::hasColumn('internal_chat_participants', 'cleared_at')) {
            Schema::table('internal_chat_participants', function (Blueprint $table) {
                $table->timestamp('cleared_at')->nullable()->after('last_read_at')->index();
            });
        }

        $this->convertWrongVisualDeletes();
        $this->addIndexIfMissing('internal_chat_participants', 'internal_chat_participants_clear_index', ['conversation_id', 'user_id', 'cleared_at']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('internal_chat_participants') || ! Schema::hasColumn('internal_chat_participants', 'cleared_at')) {
            return;
        }

        Schema::table('internal_chat_participants', function (Blueprint $table) {
            $table->dropColumn('cleared_at');
        });
    }

    private function convertWrongVisualDeletes(): void
    {
        if (! Schema::hasColumn('internal_chat_participants', 'deleted_at')) {
            return;
        }

        DB::table('internal_chat_participants')
            ->whereNotNull('deleted_at')
            ->chunkById(200, function ($participants) {
                foreach ($participants as $participant) {
                    DB::table('internal_chat_participants')
                        ->where('id', $participant->id)
                        ->update([
                            // أي حذف ظاهري تم تنفيذه في V38 كان يخفي المحادثة/المستخدم من القائمة.
                            // في V39 نحوله إلى cleared_at: أي إخفاء الرسائل القديمة فقط من شاشة هذا المستخدم.
                            'cleared_at' => $participant->deleted_at ?: now(),
                            'deleted_at' => null,
                            'archived_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName, $columns) {
                $tableBlueprint->index($columns, $indexName);
            });
        } catch (Throwable $exception) {
            // الفهرس موجود مسبقًا أو قاعدة البيانات لا تسمح بتكراره.
        }
    }
};
