<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QrPrintSettingsController extends Controller
{
    private array $defaults = [
        'qr_enabled' => '1',
        'qr_x_mm' => '70',
        'qr_y_mm' => '28',
        'qr_size_mm' => '16',
        'qr_label_enabled' => '1',
        'qr_label_text' => 'رمز الوصول الإلكتروني',
        'qr_label_font_size_mm' => '2.1',
        'qr_card_padding_mm' => '1',
        'qr_background_enabled' => '1',
        'qr_show_border' => '1',
        'print_block_x_mm' => '106',
        'print_block_y_mm' => '28',
        'print_font_size_pt' => '10.5',
        'print_department_font_size_pt' => '11',
        'print_line_height' => '1.35',
        'print_label_width_mm' => '27',
        'print_colon_width_mm' => '4',
        'print_value_width_mm' => '36',
    ];

    public function edit()
    {
        return view('settings.qr-print-position', [
            'settings' => $this->settings(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'qr_enabled' => ['nullable', 'boolean'],
            'qr_x_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'qr_y_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'qr_size_mm' => ['required', 'numeric', 'min:8', 'max:45'],
            'qr_label_enabled' => ['nullable', 'boolean'],
            'qr_label_text' => ['nullable', 'string', 'max:100'],
            'qr_label_font_size_mm' => ['required', 'numeric', 'min:1.5', 'max:6'],
            'qr_card_padding_mm' => ['required', 'numeric', 'min:0', 'max:8'],
            'qr_background_enabled' => ['nullable', 'boolean'],
            'qr_show_border' => ['nullable', 'boolean'],
            'print_block_x_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'print_block_y_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'print_font_size_pt' => ['required', 'numeric', 'min:7', 'max:24'],
            'print_department_font_size_pt' => ['required', 'numeric', 'min:7', 'max:24'],
            'print_line_height' => ['required', 'numeric', 'min:1', 'max:2.5'],
            'print_label_width_mm' => ['required', 'numeric', 'min:15', 'max:60'],
            'print_colon_width_mm' => ['required', 'numeric', 'min:2', 'max:12'],
            'print_value_width_mm' => ['required', 'numeric', 'min:20', 'max:80'],
        ], [], [
            'qr_x_mm' => 'موضع QR من يسار الورقة',
            'qr_y_mm' => 'موضع QR من أعلى الورقة',
            'qr_size_mm' => 'حجم QR',
            'print_block_x_mm' => 'موضع بيانات رقم الكتاب من يسار الورقة',
            'print_block_y_mm' => 'موضع بيانات رقم الكتاب من أعلى الورقة',
            'print_font_size_pt' => 'حجم خط رقم الكتاب والتاريخ',
        ]);

        $data['qr_enabled'] = $request->boolean('qr_enabled') ? '1' : '0';
        $data['qr_label_enabled'] = $request->boolean('qr_label_enabled') ? '1' : '0';
        $data['qr_background_enabled'] = $request->boolean('qr_background_enabled') ? '1' : '0';
        $data['qr_show_border'] = $request->boolean('qr_show_border') ? '1' : '0';
        $data['qr_label_text'] = trim((string) ($data['qr_label_text'] ?? '')) ?: 'رمز الوصول الإلكتروني';

        $this->saveMany($data);

        return redirect()
            ->route('settings.qr-print-position')
            ->with('success', 'تم حفظ إعدادات الباركود والطباعة بنجاح.');
    }

    private function settings(): array
    {
        $settings = $this->defaults;

        if (!Schema::hasTable('qr_print_settings')) {
            return $settings;
        }

        try {
            $rows = DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
            foreach ($rows as $key => $value) {
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = $value;
                }
            }
        } catch (\Throwable $e) {
            return $settings;
        }

        return $settings;
    }

    private function saveMany(array $data): void
    {
        if (!Schema::hasTable('qr_print_settings')) {
            return;
        }

        foreach (array_merge($this->defaults, $data) as $key => $value) {
            DB::table('qr_print_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}