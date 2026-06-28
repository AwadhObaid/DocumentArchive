<?php

namespace App\Http\Controllers;

use App\Services\QrPrintLayoutSettings;
use Illuminate\Http\Request;

class QrPrintSettingsController extends Controller
{
    public function edit()
    {
        return view('settings.qr-print-position', [
            'settings' => QrPrintLayoutSettings::all(),
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
        ], [], [
            'qr_x_mm' => 'المسافة من يسار الورقة',
            'qr_y_mm' => 'المسافة من أعلى الورقة',
            'qr_size_mm' => 'حجم رمز QR',
            'qr_label_text' => 'نص أسفل الرمز',
        ]);

        $data['qr_enabled'] = $request->boolean('qr_enabled') ? '1' : '0';
        $data['qr_label_enabled'] = $request->boolean('qr_label_enabled') ? '1' : '0';
        $data['qr_background_enabled'] = $request->boolean('qr_background_enabled') ? '1' : '0';
        $data['qr_show_border'] = $request->boolean('qr_show_border') ? '1' : '0';
        $data['qr_label_text'] = $data['qr_label_text'] ?: 'رمز الوصول الإلكتروني';

        QrPrintLayoutSettings::upsertMany($data);

        return redirect()
            ->route('settings.qr-print-position')
            ->with('success', 'تم حفظ موضع رمز QR بنجاح.');
    }
}