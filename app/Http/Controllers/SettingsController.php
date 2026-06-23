<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $settings = [
            'reference_start_number' => Setting::getValue('reference_start_number', '251230000'),
            'print_department_title' => Setting::getValue('print_department_title', 'الشحن والتأمين'),
            'print_top_mm' => Setting::getValue('print_top_mm', '53.30'),
            'print_left_mm' => Setting::getValue('print_left_mm', '30.80'),
            'print_font_size_pt' => Setting::getValue('print_font_size_pt', '12'),
        ];

        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'reference_start_number' => ['required', 'integer', 'min:1'],
            'print_department_title' => ['required', 'string', 'max:255'],
            'print_top_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'print_left_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'print_font_size_pt' => ['required', 'integer', 'min:6', 'max:30'],
            'apply_to_existing_documents' => ['nullable', 'boolean'],
        ]);

        Setting::setValue(
            'reference_start_number',
            $validated['reference_start_number'],
            'references',
            'number',
            'رقم بداية الإشارة في بداية كل سنة'
        );

        Setting::setValue(
            'print_department_title',
            $validated['print_department_title'],
            'printing',
            'text',
            'العنوان الثابت الذي يظهر أعلى الإشارة'
        );

        Setting::setValue(
            'print_top_mm',
            $validated['print_top_mm'],
            'printing',
            'decimal',
            'موضع كتلة الطباعة من أعلى ورقة A4 بالملليمتر'
        );

        Setting::setValue(
            'print_left_mm',
            $validated['print_left_mm'],
            'printing',
            'decimal',
            'موضع كتلة الطباعة من يسار ورقة A4 بالملليمتر'
        );

        Setting::setValue(
            'print_font_size_pt',
            $validated['print_font_size_pt'],
            'printing',
            'number',
            'حجم خط الإشارة والتاريخ عند الطباعة'
        );

        if ($request->boolean('apply_to_existing_documents')) {
            Document::query()->update([
                'print_title' => $validated['print_department_title'],
                'print_top_mm' => $validated['print_top_mm'],
                'print_left_mm' => $validated['print_left_mm'],
            ]);
        }

        return redirect()
            ->route('settings.edit')
            ->with('success', 'تم حفظ الإعدادات بنجاح.');
    }
}