<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('qr_print_settings');
    }

    public function down(): void
    {
        // QR has been removed from the system intentionally.
        // The table is not recreated on rollback.
    }
};