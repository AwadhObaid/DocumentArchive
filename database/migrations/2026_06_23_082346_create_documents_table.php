<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Reference Number / الإشارة
            |--------------------------------------------------------------------------
            */
            $table->string('reference_number');
            $table->year('reference_year');
            $table->unsignedInteger('reference_sequence')->default(0);
            $table->date('reference_date');

            /*
            |--------------------------------------------------------------------------
            | Main Document Info
            |--------------------------------------------------------------------------
            */
            $table->string('title');
            $table->text('subject')->nullable();
            $table->text('description')->nullable();

            $table->string('sender')->nullable();
            $table->string('receiver')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status / Classification
            |--------------------------------------------------------------------------
            */
            $table->string('status')->default('active');
            $table->string('confidentiality')->default('normal');
            $table->string('priority')->default('normal');

            /*
            |--------------------------------------------------------------------------
            | Printing Snapshot
            |--------------------------------------------------------------------------
            */
            $table->string('print_title')->default('الشحن والتأمين');
            $table->decimal('print_top_mm', 8, 2)->default(53.30);
            $table->decimal('print_left_mm', 8, 2)->default(30.80);

            /*
            |--------------------------------------------------------------------------
            | Search / Notes
            |--------------------------------------------------------------------------
            */
            $table->longText('search_text')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['reference_year', 'reference_number']);
            $table->unique(['reference_year', 'reference_sequence']);

            $table->index(['reference_year', 'reference_date']);
            $table->index('reference_number');
            $table->index('status');
            $table->index('confidentiality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};