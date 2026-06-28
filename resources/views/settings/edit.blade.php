@extends('layouts.app')

@section('title', 'الإعدادات')

@section('content')
    <div class="page-title">
        <h1>الإعدادات</h1>

        @if(auth()->user()?->hasPermission('documents.view'))
        <a href="{{ route('documents.index') }}" class="btn btn-secondary">
            رجوع للم
ستندات
        </a>
        @endif
    </div>

    <div class="card">
        @if(auth()->user()?->hasPermission('settings.manage'))
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf

            <h2>إعدادات الإشارة</h2>

            <div class="form-grid">
                <div class="form-group">
                    <label>رقم
 بداية الإشارة</label>
                    <input type="number" name="reference_start_number" value="{{ old('reference_start_number', $settings['reference_start_number']) }}" required>
                    <small>م
ثال: 251230000. يبدأ م
نه النظام
 أول كل سنة.</small>
                </div>
            </div>

            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e5e7eb;">

            <h2>إعدادات طباعة الإشارة على ورقة A4</h2>

            <div class="form-grid">
                <div class="form-group">
                    <label>عنوان الطباعة</label>
                    <input type="text" name="print_department_title" value="{{ old('print_department_title', $settings['print_department_title']) }}" required>
                </div>

                <div class="form-group">
                    <label>حجم
 الخط</label>
                    <input type="number" name="print_font_size_pt" value="{{ old('print_font_size_pt', $settings['print_font_size_pt']) }}" min="6" max="30" required>
                </div>

                <div class="form-group">
                    <label>الم
وضع م
ن أعلى الورقة بالم
لليم
تر</label>
                    <input type="number" step="0.01" name="print_top_mm" value="{{ old('print_top_mm', $settings['print_top_mm']) }}" required>
                    <small>زِد الرقم
 لتحريك الطباعة للأسفل، وقلله لتحريكها للأعلى.</small>
                </div>

                <div class="form-group">
                    <label>الم
وضع م
ن يسار الورقة بالم
لليم
تر</label>
                    <input type="number" step="0.01" name="print_left_mm" value="{{ old('print_left_mm', $settings['print_left_mm']) }}" required>
                    <small>زِد الرقم
 لتحريك الطباعة يساراً، وقلله لتحريكها يم
يناً.</small>
                </div>

                <div class="form-group full">
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="apply_to_existing_documents" value="1">
                        تطبيق م
وضع الطباعة الجديد على الم
ستندات السابقة أيضاً
                    </label>
                    <small>إذا لم
 تحدد هذا الخيار، سيتم
 تطبيق الإعدادات فقط على الم
ستندات الجديدة.</small>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">
                    حفظ الإعدادات
                </button>
            </div>
        </form>
        @endif
    </div>

    <div class="card">
        <h2>م
عاينة الم
وضع الحالي</h2>
        <p>هذه الم
عاينة تقريبية داخل الشاشة فقط. عند الطباعة الفعلية استخدم
: <strong>A4 + Scale 100%</strong></p>

        <div style="width: 210mm; height: 297mm; background: white; position: relative; border: 1px solid #d1d5db; transform: scale(.45); transform-origin: top right; margin-bottom: -155mm;">
            <div style="position: absolute; top: {{ $settings['print_top_mm'] }}mm; left: {{ $settings['print_left_mm'] }}mm; width: 50mm; font-size: {{ $settings['print_font_size_pt'] }}pt; font-weight: bold; color: #000; direction: rtl;">
                <div style="text-align:center; font-size:11pt; margin-bottom:3mm;">{{ $settings['print_department_title'] }}</div>
                <div style="display:grid; grid-template-columns:27mm 4mm 19mm; direction:ltr; margin-bottom:1.5mm;">
                    <div style="text-align:left; direction:ltr;">251230000</div>
                    <div style="text-align:center;">:</div>
                    <div style="text-align:right; direction:rtl;">الإشارة</div>
                </div>
                <div style="display:grid; grid-template-columns:27mm 4mm 19mm; direction:ltr;">
                    <div style="text-align:left; direction:ltr;">04/01/2026</div>
                    <div style="text-align:center;">:</div>
                    <div style="text-align:right; direction:rtl;">التاريخ</div>
                </div>
            </div>
        </div>
    </div>
@endsection
