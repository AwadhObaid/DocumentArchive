@extends('layouts.app')

@section('title', 'بحث داخل ملفات PDF')
@section('page_title', 'بحث داخل ملفات PDF')
@section('page_subtitle', 'البحث داخل النص المفهرس من مرفقات الكتب والمذكرات')

@section('content')
@php
    $statusOptions = [
        'all' => 'كل الحالات',
        'indexed' => 'مفهرس بنجاح',
        'needs_ocr' => 'يحتاج تعرفًا ضوئيًا',
        'failed' => 'فشلت الفهرسة',
        'missing' => 'الملف غير موجود',
        'pending' => 'بانتظار الفهرسة',
        'skipped' => 'تم تجاوزه',
    ];
    $sourceOptions = [
        'all' => 'الكتب والمذكرات',
        'documents' => 'الكتب فقط',
        'memos' => 'المذكرات فقط',
    ];
    $toolLabels = [
        'pdftotext' => [
            'title' => 'استخراج النص من PDF',
            'technical' => 'pdftotext',
            'description' => 'تقرأ النصوص الموجودة فعليًا داخل ملفات PDF.',
            'missing' => 'غير مثبتة أو المسار غير صحيح',
        ],
        'pdftoppm' => [
            'title' => 'تحويل PDF إلى صور',
            'technical' => 'pdftoppm',
            'description' => 'تحوّل صفحات PDF الممسوحة ضوئيًا إلى صور قبل التعرف الضوئي.',
            'missing' => 'غير مثبتة أو المسار غير صحيح',
        ],
        'tesseract' => [
            'title' => 'التعرف الضوئي على النصوص',
            'technical' => 'Tesseract',
            'description' => 'يستخرج النص من الصور والسكانر، ويدعم العربية عند تثبيت لغة ara.',
            'missing' => 'غير مثبت أو المسار غير صحيح',
        ],
    ];
    $toolMessage = function (array $tool, string $key) {
        if (($tool['available'] ?? false) === true) {
            return 'جاهزة للاستخدام';
        }

        return match ($key) {
            'pdftotext' => 'أداة Poppler لاستخراج النص غير متاحة. ثبّت Poppler أو حدّد مسار pdftotext.exe من الإعدادات.',
            'pdftoppm' => 'أداة Poppler لتحويل PDF إلى صور غير متاحة. ثبّت Poppler أو حدّد مسار pdftoppm.exe من الإعدادات.',
            'tesseract' => 'محرك التعرف الضوئي غير متاح. ثبّت Tesseract أو حدّد مسار tesseract.exe من الإعدادات.',
            default => 'الأداة غير متاحة أو مسارها غير صحيح.',
        };
    };
@endphp

<div class="pdf-search-page">
    <div class="pdf-search-hero">
        <div class="pdf-search-card">
            <h1>🔎 بحث داخل محتوى ملفات PDF</h1>
            <p>
                هذه الصفحة تبحث داخل النص المستخرج من مرفقات الكتب والمذكرات.
                ملفات PDF النصية تُفهرس مباشرة، أما ملفات السكانر والصور فتحتاج إلى <strong>التعرف الضوئي على النصوص</strong>.
            </p>
            <div class="pdf-search-stats" style="margin-top:14px;">
                <div class="pdf-search-stat"><span>إجمالي الفهارس</span><strong>{{ number_format($stats['total']) }}</strong></div>
                <div class="pdf-search-stat"><span>مفهرسة بنجاح</span><strong>{{ number_format($stats['indexed']) }}</strong></div>
                <div class="pdf-search-stat"><span>تحتاج تعرفًا ضوئيًا</span><strong>{{ number_format($stats['needs_ocr']) }}</strong></div>
                <div class="pdf-search-stat"><span>فاشلة أو مفقودة</span><strong>{{ number_format($stats['failed']) }}</strong></div>
            </div>
        </div>

        <div class="pdf-search-card">
            <h2>حالة أدوات الفهرسة</h2>
            <p style="color:#94a3b8; margin-top:4px;">
                هذه الأدوات تعمل في الخلفية لاستخراج النص من ملفات PDF. إذا ظهرت أداة غير متاحة، حدّد مسارها من صفحة الإعدادات.
            </p>
            <div class="pdf-search-tools">
                @foreach($toolLabels as $key => $label)
                    @php($tool = $tools[$key] ?? ['available' => false, 'path' => '-', 'message' => '-'])
                    <div class="pdf-tool-chip {{ $tool['available'] ? 'available' : 'missing' }}">
                        <strong>{{ $tool['available'] ? '✅' : '⚠️' }} {{ $label['title'] }}</strong>
                        <small>{{ $tool['available'] ? 'الحالة: جاهزة' : 'الحالة: ' . $label['missing'] }}</small>
                        <small>الاسم التقني: {{ $label['technical'] }}</small>
                        <small>{{ $label['description'] }}</small>
                        @if(! $tool['available'])
                            <small style="color:#fca5a5;">{{ $toolMessage($tool, $key) }}</small>
                        @endif
                    </div>
                @endforeach
                <div class="pdf-tool-chip {{ $tools['ocr_enabled'] ? 'available' : 'missing' }}">
                    <strong>{{ $tools['ocr_enabled'] ? '✅' : 'ℹ️' }} التعرف الضوئي على النصوص</strong>
                    <small>الحالة: {{ $tools['ocr_enabled'] ? 'مفعّل' : 'غير مفعّل من الإعدادات' }}</small>
                    <small>اللغات: {{ $tools['ocr_languages'] }} / حد الصفحات: {{ $tools['pages_limit'] }}</small>
                    <small>يُستخدم فقط لملفات السكانر أو الصور التي لا تحتوي نصًا قابلًا للنسخ.</small>
                </div>
            </div>
        </div>
    </div>

    @if(! $hasIndexTable)
        <div class="alert-error">جدول الفهرسة غير موجود. نفّذ أمر التحديث: <span dir="ltr">php artisan migrate</span></div>
    @endif

    <div class="pdf-search-card">
        <form method="GET" action="{{ route('pdf-search.index') }}" class="pdf-search-filter">
            <div class="form-group">
                <label>كلمة البحث داخل ملفات PDF</label>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="مثال: بدل نوبة، رقم بوليصة، اسم موظف...">
            </div>
            <div class="form-group">
                <label>المصدر</label>
                <select name="source">
                    @foreach($sourceOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>حالة الفهرسة</label>
                <select name="status">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">بحث</button>
                <a href="{{ route('pdf-search.index') }}" class="btn btn-light">تصفير</a>
            </div>
        </form>
    </div>

    @if($canIndex)
        <div class="pdf-search-card">
            <h2>تشغيل الفهرسة</h2>
            <p>
                ابدأ بفهرسة ملفات PDF النصية بدون تشغيل التعرف الضوئي. بعد ذلك فعّل التعرف الضوئي للملفات التي تظهر بحالة
                <strong>يحتاج تعرفًا ضوئيًا</strong>.
            </p>
            <form method="POST" action="{{ route('pdf-search.run') }}" class="pdf-search-run-grid" style="margin-top:12px;">
                @csrf
                <div class="form-group">
                    <label>المصدر</label>
                    <select name="source">
                        <option value="all">الكتب والمذكرات</option>
                        <option value="documents">الكتب فقط</option>
                        <option value="memos">المذكرات فقط</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>عدد الملفات في التشغيل الواحد</label>
                    <input type="number" name="limit" value="25" min="1" max="500" required>
                </div>
                <div class="form-group">
                    <label style="display:flex; gap:8px; align-items:center; margin-top:24px;">
                        <input type="checkbox" name="enable_ocr" value="1">
                        تشغيل التعرف الضوئي لهذه الدفعة
                    </label>
                    <label style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                        <input type="checkbox" name="force" value="1">
                        إعادة فهرسة الملفات المفهرسة سابقًا
                    </label>
                </div>
                <div>
                    <button type="submit" class="btn btn-success">تشغيل الفهرسة الآن</button>
                </div>
            </form>
            <div class="pdf-note" style="margin-top:12px;">
                يمكن تشغيل الفهرسة من PowerShell أيضًا:
                <span dir="ltr">php artisan archive:index-pdfs --limit=25</span>
                ، ولتشغيل التعرف الضوئي:
                <span dir="ltr">php artisan archive:index-pdfs --limit=5 --ocr</span>
            </div>
        </div>
    @endif

    <div class="pdf-search-card pdf-search-results">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
            <h2>نتائج البحث والفهرسة</h2>
            <span class="pdf-status-pill pdf-status-info">{{ $indexes->total() }} نتيجة</span>
        </div>

        @if($indexes->count())
            <div class="pdf-search-table-wrap">
                <table class="pdf-search-table">
                    <thead>
                        <tr>
                            <th>نوع السجل</th>
                            <th>اسم الملف</th>
                            <th>حالة الفهرسة</th>
                            <th>النص المستخرج</th>
                            <th>آخر فهرسة</th>
                            @if($canIndex)<th class="no-print">إجراء</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($indexes as $index)
                            @php($route = $index->recordRoute())
                            <tr>
                                <td>
                                    <strong>{{ $index->source_label }}</strong><br>
                                    @if($route)
                                        <a href="{{ $route }}" class="pdf-source-number">{{ $index->recordNumber() }}</a>
                                    @else
                                        <span class="pdf-source-number">{{ $index->recordNumber() }}</span>
                                    @endif
                                    <div style="color:#94a3b8; max-width:240px; word-break:break-word;">{{ $index->recordTitle() }}</div>
                                </td>
                                <td class="pdf-file-name">
                                    {{ $index->original_name ?: $index->file_name ?: '-' }}<br>
                                    <small style="color:#94a3b8;">{{ strtoupper($index->extension ?: 'PDF') }} / {{ number_format($index->text_length) }} حرف مستخرج</small>
                                </td>
                                <td>
                                    <span class="pdf-status-pill pdf-status-{{ $index->status_class }}">{{ $index->status_name }}</span>
                                    @if($index->extractor)
                                        <div style="color:#94a3b8;font-size:12px;margin-top:6px;">طريقة المعالجة: {{ $index->extractor_name }}</div>
                                    @endif
                                    @if($index->error_message)
                                        <div class="pdf-error-box">{{ $index->friendlyErrorMessage() }}</div>
                                    @endif
                                </td>
                                <td class="pdf-snippet">{{ $index->snippet($filters['q']) ?: 'لا يوجد نص مستخرج بعد.' }}</td>
                                <td>{{ $index->last_indexed_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                @if($canIndex)
                                    <td class="no-print">
                                        <form method="POST" action="{{ route('pdf-search.reindex', $index) }}" class="pdf-inline-form">
                                            @csrf
                                            <label style="display:flex; gap:5px; align-items:center; font-size:12px; color:#cbd5e1;">
                                                <input type="checkbox" name="enable_ocr" value="1">
                                                تشغيل التعرف الضوئي
                                            </label>
                                            <button type="submit" class="btn btn-sm btn-secondary">إعادة الفهرسة</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:14px;">{{ $indexes->links() }}</div>
        @else
            <p class="empty-state">لا توجد نتائج بعد. شغّل الفهرسة أولاً أو غيّر شروط البحث.</p>
        @endif
    </div>
</div>
@endsection
