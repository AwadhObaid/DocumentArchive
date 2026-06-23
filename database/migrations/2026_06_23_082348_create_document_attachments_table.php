<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();

            $table->string('attachment_type')->default('main');
            $table->unsignedInteger('version_no')->default(1);
            $table->boolean('is_main')->default(true);

            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('local');

            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            $table->string('ocr_status')->default('pending');
            $table->longText('ocr_text')->nullable();
            $table->text('ocr_error')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('document_id');
            $table->index('is_main');
            $table->index('ocr_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};