<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_chat_messages')) {
            Schema::create('internal_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamp('sender_deleted_at')->nullable();
                $table->timestamp('receiver_deleted_at')->nullable();
                $table->timestamps();

                $table->index(['receiver_id', 'read_at'], 'internal_chat_receiver_unread_index');
                $table->index(['sender_id', 'created_at'], 'internal_chat_sender_created_index');
                $table->index(['sender_id', 'receiver_id', 'id'], 'internal_chat_pair_forward_index');
                $table->index(['receiver_id', 'sender_id', 'id'], 'internal_chat_pair_reverse_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_chat_messages');
    }
};
