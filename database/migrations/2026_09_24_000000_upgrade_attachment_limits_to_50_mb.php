<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Upgrade only the previous default (20 MB). Existing custom values
        // such as 30, 40, 75 or 100 MB are preserved.
        DB::table('settings')
            ->whereIn('key', [
                'smart_attachment_max_file_mb',
                'file_bridge_max_file_mb',
            ])
            ->where('value', '20')
            ->update([
                'value' => '50',
            ]);
    }

    public function down(): void
    {
        // Intentionally left empty: a rollback must not overwrite an
        // administrator's intentional 50 MB/custom setting.
    }
};
