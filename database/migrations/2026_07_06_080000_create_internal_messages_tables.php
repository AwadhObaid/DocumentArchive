<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_messages')) {
            Schema::create('internal_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
                $table->string('subject');
                $table->text('body')->nullable();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->foreignId('memo_id')->nullable()->constrained('memos')->nullOnDelete();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('sender_archived_at')->nullable();
                $table->timestamp('receiver_archived_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['receiver_id', 'read_at']);
                $table->index(['sender_id', 'created_at']);
                $table->index(['document_id']);
                $table->index(['memo_id']);
            });
        }

        if (! Schema::hasTable('internal_message_attachments')) {
            Schema::create('internal_message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('internal_message_id')->constrained('internal_messages')->cascadeOnDelete();
                $table->string('original_name');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('disk')->default('local');
                $table->string('extension', 20)->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['internal_message_id']);
                $table->index(['uploaded_by']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_message_attachments');
        Schema::dropIfExists('internal_messages');
    }
};
