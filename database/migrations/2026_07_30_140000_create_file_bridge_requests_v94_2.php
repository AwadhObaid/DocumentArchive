<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('file_bridge_requests')) {
            return;
        }

        Schema::create('file_bridge_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('query')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedSmallInteger('reference_year')->nullable();
            $table->string('original_name')->nullable();
            $table->string('temporary_path', 1000)->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_machine')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_bridge_requests');
    }
};
