<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('qr_print_settings')) {
            Schema::create('qr_print_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            'qr_enabled' => '1',
            'qr_x_mm' => '82',
            'qr_y_mm' => '50',
            'qr_size_mm' => '20',
            'qr_label_enabled' => '1',
            'qr_label_text' => 'رمز الوصول الإلكتروني',
            'qr_label_font_size_mm' => '2.6',
            'qr_card_padding_mm' => '2',
            'qr_background_enabled' => '1',
            'qr_show_border' => '1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('qr_print_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Keep settings table by default to avoid losing print calibration.
        // Schema::dropIfExists('qr_print_settings');
    }
};