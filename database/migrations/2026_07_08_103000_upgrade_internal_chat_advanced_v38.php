<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_chat_conversations')) {
            Schema::create('internal_chat_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->default('direct')->index();
                $table->string('title')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internal_chat_participants')) {
            Schema::create('internal_chat_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('internal_chat_conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('member');
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id'], 'internal_chat_participants_unique');
                $table->index(['user_id', 'deleted_at', 'archived_at'], 'internal_chat_participants_visibility_index');
                $table->index(['conversation_id', 'last_read_message_id'], 'internal_chat_participants_read_index');
            });
        }

        if (Schema::hasTable('internal_chat_messages')) {
            Schema::table('internal_chat_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('internal_chat_messages', 'conversation_id')) {
                    $table->foreignId('conversation_id')->nullable()->after('id')->constrained('internal_chat_conversations')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('internal_chat_messages', 'document_id')) {
                    $table->foreignId('document_id')->nullable()->after('body')->constrained('documents')->nullOnDelete();
                }
                if (! Schema::hasColumn('internal_chat_messages', 'memo_id')) {
                    $table->foreignId('memo_id')->nullable()->after('document_id')->constrained('memos')->nullOnDelete();
                }
            });

            $this->addIndexIfMissing('internal_chat_messages', 'internal_chat_conversation_id_index', ['conversation_id', 'id']);
            $this->addIndexIfMissing('internal_chat_messages', 'internal_chat_document_id_index', ['document_id']);
            $this->addIndexIfMissing('internal_chat_messages', 'internal_chat_memo_id_index', ['memo_id']);
        }

        if (! Schema::hasTable('internal_chat_attachments')) {
            Schema::create('internal_chat_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('internal_chat_message_id')->constrained('internal_chat_messages')->cascadeOnDelete();
                $table->string('original_name');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('disk')->default('local');
                $table->string('extension', 20)->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['internal_chat_message_id'], 'internal_chat_attachments_message_index');
                $table->index(['uploaded_by'], 'internal_chat_attachments_uploader_index');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable()->after('last_login_at')->index();
            });
        }

        $this->backfillDirectConversations();
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_seen_at');
            });
        }

        Schema::dropIfExists('internal_chat_attachments');

        if (Schema::hasTable('internal_chat_messages')) {
            Schema::table('internal_chat_messages', function (Blueprint $table) {
                if (Schema::hasColumn('internal_chat_messages', 'conversation_id')) {
                    $table->dropConstrainedForeignId('conversation_id');
                }
                if (Schema::hasColumn('internal_chat_messages', 'document_id')) {
                    $table->dropConstrainedForeignId('document_id');
                }
                if (Schema::hasColumn('internal_chat_messages', 'memo_id')) {
                    $table->dropConstrainedForeignId('memo_id');
                }
            });
        }

        Schema::dropIfExists('internal_chat_participants');
        Schema::dropIfExists('internal_chat_conversations');
    }

    private function backfillDirectConversations(): void
    {
        if (! Schema::hasTable('internal_chat_messages') || ! Schema::hasColumn('internal_chat_messages', 'conversation_id')) {
            return;
        }

        $pairs = DB::table('internal_chat_messages')
            ->select('sender_id', 'receiver_id')
            ->whereNull('conversation_id')
            ->whereNotNull('sender_id')
            ->whereNotNull('receiver_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $first = (int) min($pair->sender_id, $pair->receiver_id);
            $second = (int) max($pair->sender_id, $pair->receiver_id);
            if ($first <= 0 || $second <= 0 || $first === $second) {
                continue;
            }

            $conversationId = $this->findDirectConversationId($first, $second);
            if (! $conversationId) {
                $latestAt = DB::table('internal_chat_messages')
                    ->where(function ($q) use ($first, $second) {
                        $q->where('sender_id', $first)->where('receiver_id', $second);
                    })
                    ->orWhere(function ($q) use ($first, $second) {
                        $q->where('sender_id', $second)->where('receiver_id', $first);
                    })
                    ->max('created_at');

                $conversationId = (int) DB::table('internal_chat_conversations')->insertGetId([
                    'type' => 'direct',
                    'title' => null,
                    'created_by' => $first,
                    'last_message_at' => $latestAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ([$first, $second] as $userId) {
                    DB::table('internal_chat_participants')->updateOrInsert(
                        ['conversation_id' => $conversationId, 'user_id' => $userId],
                        ['role' => 'member', 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            DB::table('internal_chat_messages')
                ->whereNull('conversation_id')
                ->where(function ($outer) use ($first, $second) {
                    $outer->where(function ($q) use ($first, $second) {
                        $q->where('sender_id', $first)->where('receiver_id', $second);
                    })->orWhere(function ($q) use ($first, $second) {
                        $q->where('sender_id', $second)->where('receiver_id', $first);
                    });
                })
                ->update(['conversation_id' => $conversationId]);
        }
    }

    private function findDirectConversationId(int $firstUserId, int $secondUserId): ?int
    {
        $candidateIds = DB::table('internal_chat_participants as p1')
            ->join('internal_chat_participants as p2', 'p1.conversation_id', '=', 'p2.conversation_id')
            ->join('internal_chat_conversations as c', 'c.id', '=', 'p1.conversation_id')
            ->where('c.type', 'direct')
            ->where('p1.user_id', $firstUserId)
            ->where('p2.user_id', $secondUserId)
            ->pluck('p1.conversation_id');

        foreach ($candidateIds as $conversationId) {
            $count = DB::table('internal_chat_participants')
                ->where('conversation_id', $conversationId)
                ->count();
            if ((int) $count === 2) {
                return (int) $conversationId;
            }
        }

        return null;
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName, $columns) {
                $tableBlueprint->index($columns, $indexName);
            });
        } catch (Throwable $exception) {
            // Index probably already exists on this database driver.
        }
    }
};
