<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        DB::table('document_types')->insert([
            [
                'name' => 'صادر',
                'code' => 'OUTGOING',
                'description' => 'مستند صادر',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'وارد',
                'code' => 'INCOMING',
                'description' => 'مستند وارد',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'مستند عام',
                'code' => 'GENERAL',
                'description' => 'مستند أرشفة عام',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};