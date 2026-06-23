<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_counters', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('reference_year')->unique();

            $table->unsignedBigInteger('start_number')->default(251230000);

            /*
            |--------------------------------------------------------------------------
            | last_sequence
            |--------------------------------------------------------------------------
            | يبدأ من -1 حتى يكون أول رقم:
            | 251230000 + 0 = 251230000
            */
            $table->integer('last_sequence')->default(-1);

            $table->string('last_reference_number')->nullable();

            $table->timestamps();

            $table->index('reference_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_counters');
    }
};