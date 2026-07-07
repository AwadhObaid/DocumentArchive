<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_text_indexes', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20)->comment('document|memo');
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('attachment_id');
            $table->string('original_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->string('disk', 80)->default('local');
            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->nullable();
            $table->string('index_status', 30)->default('pending')->index();
            $table->string('extractor', 30)->nullable()->comment('native|ocr|mixed|none');
            $table->boolean('needs_ocr')->default(false)->index();
            $table->unsignedInteger('pages_count')->nullable();
            $table->unsignedInteger('text_length')->default(0);
            $table->longText('indexed_text')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_indexed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['source_type', 'attachment_id'], 'attachment_text_source_attachment_unique');
            $table->index(['source_type', 'source_id'], 'attachment_text_source_record_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_text_indexes');
    }
};
