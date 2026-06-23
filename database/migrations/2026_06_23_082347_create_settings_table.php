<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('key')->unique();
            $table->longText('value')->nullable();

            $table->string('group')->default('general');
            $table->string('type')->default('text');
            $table->text('description')->nullable();

            $table->timestamps();
        });

        DB::table('settings')->insert([
            [
                'key' => 'reference_start_number',
                'value' => '251230000',
                'group' => 'references',
                'type' => 'number',
                'description' => 'رقم بداية الإشارة في بداية كل سنة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'print_department_title',
                'value' => 'الشحن والتأمين',
                'group' => 'printing',
                'type' => 'text',
                'description' => 'العنوان الثابت الذي يظهر أعلى الإشارة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'print_top_mm',
                'value' => '53.30',
                'group' => 'printing',
                'type' => 'decimal',
                'description' => 'موضع كتلة الطباعة من أعلى ورقة A4 بالملليمتر',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'print_left_mm',
                'value' => '30.80',
                'group' => 'printing',
                'type' => 'decimal',
                'description' => 'موضع كتلة الطباعة من يسار ورقة A4 بالملليمتر',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'print_font_size_pt',
                'value' => '12',
                'group' => 'printing',
                'type' => 'number',
                'description' => 'حجم خط الإشارة والتاريخ عند الطباعة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};