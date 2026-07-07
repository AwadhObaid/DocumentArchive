<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Setting;
use App\Services\ActivityLogger;
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

    private const DEFAULTS = [
        'system_name' => 'الأرشيف الإلكتروني',
        'system_department_name' => 'الشحن والتأمين',
        'system_full_title' => 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين',
        'system_tagline' => 'إدارة الكتب، المرفقات، البوالص، والطباعة الرسمية',
        'system_brand_icon' => '🗂️',
        'auto_logout_enabled' => '0',
        'auto_logout_minutes' => '30',
        'auto_logout_warning_seconds' => '60',
        'lite_notification_poll_seconds' => '30',
        'internal_chat_enabled' => '1',
        'internal_chat_poll_seconds' => '5',
        'pdf_search_pdftotext_path' => 'pdftotext',
        'pdf_search_pdftoppm_path' => 'pdftoppm',
        'pdf_search_tesseract_path' => 'tesseract',
        'pdf_search_ocr_languages' => 'ara+eng',
        'pdf_search_enable_ocr' => '0',
        'pdf_search_pages_limit' => '20',
        'reference_start_number' => '251230000',
        'print_department_title' => 'الشحن والتأمين',
        'print_font_family' => 'Cairo',
        'print_top_mm' => '32',
        'print_left_mm' => '32',
        'print_font_size_pt' => '10.2',
        'print_department_font_size_pt' => '10.8',
        'print_label_width_mm' => '18',
        'print_colon_width_mm' => '1',
        'print_value_width_mm' => '24',
        'print_column_gap_mm' => '0',
        'print_title_gap_mm' => '0.6',
        'print_row_gap_mm' => '0.25',
    ];

    public function edit()
    {
        $settings = $this->settingsForView();
        $printFontOptions = self::PRINT_FONT_OPTIONS;
        $printSummary = [
            'position' => $settings['print_top_mm'] . ' مم من الأعلى / ' . $settings['print_left_mm'] . ' مم من اليسار',
            'font' => $settings['print_font_family'] . ' - ' . $settings['print_font_size_pt'] . ' pt',
            'reference_start' => $settings['reference_start_number'],
            'auto_logout' => ((string) $settings['auto_logout_enabled'] === '1') ? ($settings['auto_logout_minutes'] . ' دقيقة') : 'غير مفعل',
            'pdf_search' => ((string) $settings['pdf_search_enable_ocr'] === '1') ? ('OCR مفعل - ' . $settings['pdf_search_ocr_languages']) : 'PDF نصي فقط',
            'internal_chat' => ((string) $settings['internal_chat_enabled'] === '1') ? ('مفعلة - كل ' . $settings['internal_chat_poll_seconds'] . ' ثواني') : 'غير مفعلة',
        ];

        return view('settings.edit', compact('settings', 'printFontOptions', 'printSummary'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:80'],
            'system_department_name' => ['required', 'string', 'max:120'],
            'system_full_title' => ['required', 'string', 'max:180'],
            'system_tagline' => ['nullable', 'string', 'max:255'],
            'system_brand_icon' => ['required', 'string', 'max:16'],
            'auto_logout_enabled' => ['nullable', 'boolean'],
            'auto_logout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'auto_logout_warning_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'lite_notification_poll_seconds' => ['required', 'integer', 'min:10', 'max:300'],
            'internal_chat_enabled' => ['nullable', 'boolean'],
            'internal_chat_poll_seconds' => ['required', 'integer', 'min:3', 'max:120'],
            'pdf_search_pdftotext_path' => ['nullable', 'string', 'max:500'],
            'pdf_search_pdftoppm_path' => ['nullable', 'string', 'max:500'],
            'pdf_search_tesseract_path' => ['nullable', 'string', 'max:500'],
            'pdf_search_ocr_languages' => ['required', 'string', 'max:80'],
            'pdf_search_enable_ocr' => ['nullable', 'boolean'],
            'pdf_search_pages_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'reference_start_number' => ['required', 'integer', 'min:1', 'max:999999999999'],
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
        ], [
            'system_name.required' => 'اسم النظام مطلوب.',
            'system_department_name.required' => 'اسم القسم مطلوب.',
            'system_full_title.required' => 'العنوان الرئيسي مطلوب.',
            'system_brand_icon.required' => 'أيقونة النظام مطلوبة.',
            'reference_start_number.required' => 'رقم بداية الكتاب مطلوب.',
            'auto_logout_minutes.required' => 'مدة الخمول قبل تسجيل الخروج مطلوبة.',
            'auto_logout_minutes.min' => 'مدة الخمول يجب ألا تقل عن دقيقة واحدة.',
            'auto_logout_warning_seconds.required' => 'مدة التنبيه قبل الخروج مطلوبة.',
            'lite_notification_poll_seconds.required' => 'مدة تحديث إشعارات نسخة الهاتف مطلوبة.',
            'internal_chat_poll_seconds.required' => 'مدة تحديث الدردشة الداخلية مطلوبة.',
            'pdf_search_ocr_languages.required' => 'لغات OCR مطلوبة، مثال: ara+eng.',
            'pdf_search_pages_limit.required' => 'حد صفحات OCR مطلوب.',
            'reference_start_number.integer' => 'رقم بداية الكتاب يجب أن يكون رقماً صحيحاً.',
            'print_department_title.required' => 'عنوان الطباعة مطلوب.',
            'print_font_family.in' => 'نوع الخط المختار غير مدعوم.',
        ]);

        $validated['auto_logout_enabled'] = $request->boolean('auto_logout_enabled') ? '1' : '0';
        $validated['internal_chat_enabled'] = $request->boolean('internal_chat_enabled') ? '1' : '0';
        $validated['pdf_search_enable_ocr'] = $request->boolean('pdf_search_enable_ocr') ? '1' : '0';
        foreach (['pdf_search_pdftotext_path', 'pdf_search_pdftoppm_path', 'pdf_search_tesseract_path'] as $toolPathKey) {
            $validated[$toolPathKey] = trim((string) ($validated[$toolPathKey] ?? ''));
        }

        if (((int) $validated['auto_logout_warning_seconds']) >= (((int) $validated['auto_logout_minutes']) * 60)) {
            return back()
                ->withErrors(['auto_logout_warning_seconds' => 'مدة التنبيه يجب أن تكون أقل من مدة الخمول الكاملة.'])
                ->withInput();
        }

        $before = $this->settingsForView();

        $definitions = [
            'system_name' => ['general', 'text', 'اسم النظام المختصر الظاهر في القائمة الجانبية وعنوان الصفحة'],
            'system_department_name' => ['general', 'text', 'اسم القسم أو الإدارة الظاهر أسفل اسم النظام'],
            'system_full_title' => ['general', 'text', 'العنوان الرئيسي أعلى صفحات النظام'],
            'system_tagline' => ['general', 'text', 'الوصف المختصر أعلى صفحات النظام'],
            'system_brand_icon' => ['general', 'text', 'أيقونة النظام في القائمة الجانبية'],
            'auto_logout_enabled' => ['security', 'boolean', 'تفعيل تسجيل الخروج التلقائي عند عدم النشاط'],
            'auto_logout_minutes' => ['security', 'number', 'مدة الخمول بالدقائق قبل تسجيل الخروج التلقائي'],
            'auto_logout_warning_seconds' => ['security', 'number', 'مدة ظهور تنبيه الخروج قبل انتهاء الجلسة بالثواني'],
            'lite_notification_poll_seconds' => ['lite', 'number', 'مدة تحديث إشعارات نسخة الهاتف لايت بالثواني'],
            'internal_chat_enabled' => ['internal_chat', 'boolean', 'تفعيل نافذة الدردشة الداخلية العائمة'],
            'internal_chat_poll_seconds' => ['internal_chat', 'number', 'مدة تحديث الدردشة الداخلية بالثواني'],
            'pdf_search_pdftotext_path' => ['pdf_search', 'text', 'مسار أداة pdftotext لاستخراج نصوص PDF النصية'],
            'pdf_search_pdftoppm_path' => ['pdf_search', 'text', 'مسار أداة pdftoppm لتحويل PDF إلى صور قبل OCR'],
            'pdf_search_tesseract_path' => ['pdf_search', 'text', 'مسار أداة Tesseract OCR'],
            'pdf_search_ocr_languages' => ['pdf_search', 'text', 'لغات OCR المستخدمة مثل ara+eng'],
            'pdf_search_enable_ocr' => ['pdf_search', 'boolean', 'تفعيل OCR عند فهرسة ملفات PDF الممسوحة ضوئياً'],
            'pdf_search_pages_limit' => ['pdf_search', 'number', 'أقصى عدد صفحات تتم معالجتها OCR في الملف الواحد'],
            'reference_start_number' => ['references', 'number', 'رقم بداية الكتاب في بداية كل سنة'],
            'print_department_title' => ['printing', 'text', 'العنوان الثابت الذي يظهر أعلى رقم الكتاب'],
            'print_font_family' => ['printing', 'text', 'نوع خط صفحة طباعة رقم الكتاب'],
            'print_top_mm' => ['printing', 'decimal', 'موضع كتلة الطباعة من أعلى ورقة A4 بالملليمتر'],
            'print_left_mm' => ['printing', 'decimal', 'موضع كتلة الطباعة من يسار ورقة A4 بالملليمتر'],
            'print_font_size_pt' => ['printing', 'decimal', 'حجم خط رقم الكتاب والتاريخ عند الطباعة'],
            'print_department_font_size_pt' => ['printing', 'decimal', 'حجم خط عنوان الجهة في صفحة طباعة رقم الكتاب'],
            'print_label_width_mm' => ['printing', 'decimal', 'عرض خانة العنوان مثل رقم الكتاب وتاريخ الكتاب'],
            'print_colon_width_mm' => ['printing', 'decimal', 'عرض خانة النقطتين بين العنوان والقيمة'],
            'print_value_width_mm' => ['printing', 'decimal', 'عرض خانة القيمة مثل الرقم والتاريخ'],
            'print_column_gap_mm' => ['printing', 'decimal', 'المسافة الأفقية بين خانات الطباعة'],
            'print_title_gap_mm' => ['printing', 'decimal', 'المسافة بين عنوان الجهة وصفوف رقم الكتاب'],
            'print_row_gap_mm' => ['printing', 'decimal', 'المسافة بين صف رقم الكتاب وصف تاريخ الكتاب'],
        ];

        foreach ($definitions as $key => [$group, $type, $description]) {
            Setting::setValue($key, $validated[$key] ?? '', $group, $type, $description);
        }

        if ($request->boolean('apply_to_existing_documents')) {
            Document::query()->update([
                'print_title' => $validated['print_department_title'],
                'print_top_mm' => $validated['print_top_mm'],
                'print_left_mm' => $validated['print_left_mm'],
            ]);
        }

        ActivityLogger::log(
            'settings.updated',
            'تم تعديل إعدادات النظام العامة والطباعة.',
            null,
            [
                'changed_keys' => $this->changedKeys($before, $validated),
                'apply_to_existing_documents' => $request->boolean('apply_to_existing_documents'),
            ]
        );

        return redirect()
            ->route('settings.edit')
            ->with('success', 'تم حفظ إعدادات النظام بنجاح.');
    }

    private function settingsForView(): array
    {
        $settings = [];

        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = Setting::getValue($key, $default);
        }

        if (! array_key_exists($settings['print_font_family'], self::PRINT_FONT_OPTIONS)) {
            $settings['print_font_family'] = 'Cairo';
        }

        return $settings;
    }

    private function changedKeys(array $before, array $after): array
    {
        $changed = [];

        foreach ($before as $key => $oldValue) {
            if (! array_key_exists($key, $after)) {
                continue;
            }

            if ((string) $oldValue !== (string) $after[$key]) {
                $changed[$key] = [
                    'old' => (string) $oldValue,
                    'new' => (string) $after[$key],
                ];
            }
        }

        return $changed;
    }
}