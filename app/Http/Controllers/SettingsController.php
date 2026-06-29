<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private const PRINT_FONT_OPTIONS = [
        'Cairo' => 'Cairo',
        'Tajawal' => 'Tajawal',
        'Arial' => 'Arial',
        'Tahoma' => 'Tahoma',
        'Traditional Arabic' => 'Traditional Arabic',
        'Amiri' => 'Amiri',
    ];

    public function edit()
    {
        $settings = [
            'reference_start_number' => Setting::getValue('reference_start_number', '251230000'),
            'print_department_title' => Setting::getValue('print_department_title', 'الشحن والتأمين'),
            'print_font_family' => Setting::getValue('print_font_family', 'Cairo'),
            'print_top_mm' => Setting::getValue('print_top_mm', '32'),
            'print_left_mm' => Setting::getValue('print_left_mm', '32'),
            'print_font_size_pt' => Setting::getValue('print_font_size_pt', '10.2'),
            'print_department_font_size_pt' => Setting::getValue('print_department_font_size_pt', '10.8'),
            'print_label_width_mm' => Setting::getValue('print_label_width_mm', '18'),
            'print_colon_width_mm' => Setting::getValue('print_colon_width_mm', '1'),
            'print_value_width_mm' => Setting::getValue('print_value_width_mm', '24'),
            'print_column_gap_mm' => Setting::getValue('print_column_gap_mm', '0'),
            'print_title_gap_mm' => Setting::getValue('print_title_gap_mm', '0.6'),
            'print_row_gap_mm' => Setting::getValue('print_row_gap_mm', '0.25'),
        ];

        if (! array_key_exists($settings['print_font_family'], self::PRINT_FONT_OPTIONS)) {
            $settings['print_font_family'] = 'Cairo';
        }

        $printFontOptions = self::PRINT_FONT_OPTIONS;

        return view('settings.edit', compact('settings', 'printFontOptions'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'reference_start_number' => ['required', 'integer', 'min:1'],
            'print_department_title' => ['required', 'string', 'max:255'],
            'print_font_family' => ['required', 'string', Rule::in(array_keys(self::PRINT_FONT_OPTIONS))],
            'print_top_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'print_left_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'print_font_size_pt' => ['required', 'numeric', 'min:6', 'max:30'],
            'print_department_font_size_pt' => ['required', 'numeric', 'min:6', 'max:30'],
            'print_label_width_mm' => ['required', 'numeric', 'min:8', 'max:60'],
            'print_colon_width_mm' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'print_value_width_mm' => ['required', 'numeric', 'min:10', 'max:80'],
            'print_column_gap_mm' => ['required', 'numeric', 'min:0', 'max:8'],
            'print_title_gap_mm' => ['required', 'numeric', 'min:0', 'max:15'],
            'print_row_gap_mm' => ['required', 'numeric', 'min:0', 'max:10'],
            'apply_to_existing_documents' => ['nullable', 'boolean'],
        ]);

        Setting::setValue('reference_start_number', $validated['reference_start_number'], 'references', 'number', 'رقم بداية الإشارة في بداية كل سنة');
        Setting::setValue('print_department_title', $validated['print_department_title'], 'printing', 'text', 'العنوان الثابت الذي يظهر أعلى رقم الكتاب');
        Setting::setValue('print_font_family', $validated['print_font_family'], 'printing', 'text', 'نوع خط صفحة طباعة رقم الكتاب');
        Setting::setValue('print_top_mm', $validated['print_top_mm'], 'printing', 'decimal', 'موضع كتلة الطباعة من أعلى ورقة A4 بالملليمتر');
        Setting::setValue('print_left_mm', $validated['print_left_mm'], 'printing', 'decimal', 'موضع كتلة الطباعة من يسار ورقة A4 بالملليمتر');
        Setting::setValue('print_font_size_pt', $validated['print_font_size_pt'], 'printing', 'decimal', 'حجم خط رقم الكتاب والتاريخ عند الطباعة');
        Setting::setValue('print_department_font_size_pt', $validated['print_department_font_size_pt'], 'printing', 'decimal', 'حجم خط عنوان الجهة في صفحة طباعة رقم الكتاب');
        Setting::setValue('print_label_width_mm', $validated['print_label_width_mm'], 'printing', 'decimal', 'عرض خانة العنوان مثل رقم الكتاب وتاريخ الكتاب');
        Setting::setValue('print_colon_width_mm', $validated['print_colon_width_mm'], 'printing', 'decimal', 'عرض خانة النقطتين بين العنوان والقيمة');
        Setting::setValue('print_value_width_mm', $validated['print_value_width_mm'], 'printing', 'decimal', 'عرض خانة القيمة مثل الرقم والتاريخ');
        Setting::setValue('print_column_gap_mm', $validated['print_column_gap_mm'], 'printing', 'decimal', 'المسافة الأفقية بين خانات الطباعة');
        Setting::setValue('print_title_gap_mm', $validated['print_title_gap_mm'], 'printing', 'decimal', 'المسافة بين عنوان الجهة وصفوف رقم الكتاب');
        Setting::setValue('print_row_gap_mm', $validated['print_row_gap_mm'], 'printing', 'decimal', 'المسافة بين صف رقم الكتاب وصف تاريخ الكتاب');

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