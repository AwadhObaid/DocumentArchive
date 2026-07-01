<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_attachment_links')) {
            Schema::create('shared_attachment_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('token', 96)->unique();
                $table->string('title')->nullable();
                $table->text('notes')->nullable();
                $table->string('password_hash')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('max_downloads')->nullable();
                $table->unsignedInteger('download_count')->default(0);
                $table->unsignedInteger('view_count')->default(0);
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamp('last_downloaded_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('document_id');
                $table->index('created_by');
                $table->index('expires_at');
                $table->index('is_active');
            });
        }

        if (!Schema::hasTable('shared_attachment_link_items')) {
            Schema::create('shared_attachment_link_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shared_attachment_link_id')
                    ->constrained('shared_attachment_links')
                    ->cascadeOnDelete();
                $table->foreignId('document_attachment_id')
                    ->constrained('document_attachments')
                    ->cascadeOnDelete();
                $table->unsignedInteger('download_count')->default(0);
                $table->timestamp('last_downloaded_at')->nullable();
                $table->timestamps();

                $table->unique(['shared_attachment_link_id', 'document_attachment_id'], 'shared_link_item_unique');
                $table->index('document_attachment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_attachment_link_items');
        Schema::dropIfExists('shared_attachment_links');
    }
};
