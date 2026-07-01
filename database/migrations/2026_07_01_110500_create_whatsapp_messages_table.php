<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('phone_number', 40);
            $table->string('normalized_phone', 20);
            $table->longText('message_body');
            $table->text('whatsapp_url');
            $table->string('status')->default('opened');
            $table->text('error_message')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->index('created_by');
            $table->index('normalized_phone');
            $table->index('status');
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
