<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        DB::table('departments')->insert([
            [
                'name' => 'الشحن والتأمين',
                'code' => 'SHIPPING_INSURANCE',
                'description' => 'الإدارة الافتراضية لطباعة الإشارة والتاريخ',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};