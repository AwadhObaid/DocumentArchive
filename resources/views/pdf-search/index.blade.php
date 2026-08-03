@extends('layouts.app')

@section('title', 'بحث داخل ملفات PDF')
@section('page_title', 'بحث داخل ملفات PDF')
@section('page_subtitle', 'البحث داخل النص المفهرس من مرفقات الكتب والمذكرات والتعاميم والكتب المتفرقة')

@section('content')
@php
    $statusOptions = [
        'all' => 'كل الحالات',
        'unindexed' => 'غير مفهرس',
        'indexed' => 'مفهرس بنجاح',
        'needs_ocr' => 'يحتاج تعرفًا ضوئيًا',
        'failed' => 'فشلت الفهرسة',
        'missing' => 'الملف غير موجود',
        'pending' => 'بانتظار الفهرسة',
        'skipped' => 'تم تجاوزه',
        'unsupported' => 'نوع غير قابل للفهرسة',
    ];
    $sourceOptions = $sourceOptions ?? [
        'all' => 'جميع المصادر المتاحة',
        'documents' => 'الكتب فقط',
        'memos' => 'المذكرات فقط',
        'circulars' => 'التعاميم فقط',
        'misc_books' => 'الكتب المتفرقة فقط',
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
                هذه الصفحة تبحث داخل النص المستخرج من مرفقات الكتب والمذكرات والتعاميم والكتب المتفرقة.
                ملفات PDF النصية تُفهرس مباشرة، أما ملفات السكانر والصور فتحتاج إلى <strong>التعرف الضوئي على النصوص</strong>.
            </p>
            <div class="pdf-search-stats" style="margin-top:14px;">
                <div class="pdf-search-stat"><span>إجمالي المرفقات</span><strong>{{ number_format($stats['total']) }}</strong></div>
                <div class="pdf-search-stat"><span>القابلة للفهرسة</span><strong>{{ number_format($stats['eligible']) }}</strong></div>
                <div class="pdf-search-stat"><span>مفهرسة بنجاح</span><strong>{{ number_format($stats['indexed']) }}</strong></div>
                <div class="pdf-search-stat"><span>غير مفهرسة</span><strong>{{ number_format($stats['unindexed']) }}</strong></div>
                <div class="pdf-search-stat"><span>تحتاج تعرفًا ضوئيًا</span><strong>{{ number_format($stats['needs_ocr']) }}</strong></div>
                <div class="pdf-search-stat"><span>فاشلة أو مفقودة</span><strong>{{ number_format($stats['failed'] + $stats['missing']) }}</strong></div>
                <div class="pdf-search-stat"><span>غير مدعومة</span><strong>{{ number_format($stats['unsupported']) }}</strong></div>
                <div class="pdf-search-stat"><span>اكتمال الفهرسة</span><strong>{{ number_format($stats['coverage_percent'], 1) }}%</strong></div>
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
                        @foreach($sourceOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
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
                ، ويمكن تحديد المصدر مثل:
                <span dir="ltr">--source=circulars</span>
                أو
                <span dir="ltr">--source=misc_books</span>
            </div>
        </div>
    @endif

    <div class="pdf-search-card pdf-search-results">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
            <h2>نتائج البحث والفهرسة</h2>
            <span class="pdf-status-pill pdf-status-info">{{ $indexes->total() }} نتيجة</span>
        </div>
        {{-- V95.1.2: raw PHP control flow avoids Blade directive ambiguity in the bulk results table. --}}
        <?php if ($canIndex && $indexes->count() > 0): ?>
            <section
                class="pdf-bulk-index-panel no-print"
                data-pdf-bulk-indexing
                data-selection-url="{{ route('pdf-search.selection') }}"
                data-selection-limit="500"
                data-filter-q="{{ $filters['q'] }}"
                data-filter-source="{{ $filters['source'] }}"
                data-filter-status="{{ $filters['status'] }}"
            >
                <div class="pdf-bulk-index-intro">
                    <div>
                        <strong>فهرسة المرفقات المحددة</strong>
                        <span>حدّد المرفقات غير المفهرسة من الصفحة الحالية، أو اجلب جميع النتائج المطابقة للفلتر الحالي.</span>
                    </div>
                    <span class="pdf-bulk-selected-badge">
                        المحدد: <strong data-pdf-selected-count>0</strong>
                    </span>
                </div>

                <div class="pdf-bulk-index-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-pdf-select-page>
                        تحديد غير المفهرس في الصفحة
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-pdf-select-filtered>
                        تحديد جميع القابلة للمعالجة من النتائج
                    </button>
                    <button type="button" class="btn btn-light btn-sm" data-pdf-clear-selection disabled>
                        إلغاء التحديد
                    </button>

                    <label class="pdf-bulk-ocr-option">
                        <input type="checkbox" data-pdf-enable-ocr>
                        تشغيل التعرف الضوئي للمحدد
                    </label>

                    <button type="button" class="btn btn-success btn-sm" data-pdf-run-selected disabled>
                        فهرسة المحدد
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-pdf-cancel-run hidden>
                        إيقاف بعد الملف الحالي
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-pdf-reload-results hidden>
                        تحديث النتائج
                    </button>
                </div>

                <div class="pdf-bulk-progress" hidden data-pdf-progress-wrap>
                    <div class="pdf-bulk-progress-head">
                        <span data-pdf-progress-text>بانتظار بدء الفهرسة...</span>
                        <strong data-pdf-progress-percent>0%</strong>
                    </div>
                    <div class="pdf-bulk-progress-track" aria-hidden="true">
                        <span data-pdf-progress-bar style="width:0%"></span>
                    </div>
                </div>

                <div class="pdf-bulk-message" data-pdf-bulk-message aria-live="polite"></div>
            </section>
        <?php endif; ?>

        <?php if ($indexes->count() > 0): ?>
            <div class="pdf-search-table-wrap">
                <table class="pdf-search-table">
                    <thead>
                        <tr>
                            <?php if ($canIndex): ?>
                                <th class="no-print pdf-bulk-checkbox-column">
                                    <label class="pdf-bulk-master-label" title="تحديد جميع المرفقات القابلة للمعالجة في الصفحة الحالية">
                                        <input type="checkbox" data-pdf-select-page-checkbox>
                                        <span>تحديد</span>
                                    </label>
                                </th>
                            <?php endif; ?>
                            <th>نوع السجل</th>
                            <th>اسم الملف</th>
                            <th>حالة الفهرسة</th>
                            <th>النص المستخرج</th>
                            <th>آخر فهرسة</th>
                            <?php if ($canIndex): ?>
                                <th class="no-print">إجراء</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indexes as $index): ?>
                            <?php
                                $route = $index->recordRoute();
                                $bulkSelectable = (int) $index->is_indexable === 1
                                    && $index->resolved_status !== 'indexed';
                                $indexUrl = route('pdf-search.index-attachment', [
                                    $index->source_type,
                                    $index->attachment_id,
                                ]);
                            ?>
                            <tr data-pdf-index-row="{{ $index->source_type }}:{{ $index->attachment_id }}">
                                <?php if ($canIndex): ?>
                                    <td class="no-print pdf-bulk-checkbox-column">
                                        <?php if ($bulkSelectable): ?>
                                            <input
                                                type="checkbox"
                                                class="pdf-bulk-item-checkbox"
                                                value="{{ $index->source_type }}:{{ $index->attachment_id }}"
                                                data-pdf-bulk-item
                                                data-source-type="{{ $index->source_type }}"
                                                data-attachment-id="{{ $index->attachment_id }}"
                                                data-index-url="{{ $indexUrl }}"
                                                data-status="{{ $index->resolved_status }}"
                                                data-label="{{ $index->original_name ?: $index->file_name ?: $index->record_number }}"
                                                aria-label="تحديد المرفق {{ $index->original_name ?: $index->file_name ?: $index->attachment_id }}"
                                            >
                                        <?php else: ?>
                                            <span class="pdf-bulk-checkbox-placeholder">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <strong>{{ $index->sourceLabel() }}</strong><br>
                                    <?php if ($route): ?>
                                        <a href="{{ $route }}" class="pdf-source-number">{{ $index->record_number ?: $index->source_id }}</a>
                                    <?php else: ?>
                                        <span class="pdf-source-number">{{ $index->record_number ?: $index->source_id }}</span>
                                    <?php endif; ?>
                                    <div class="pdf-record-title">{{ $index->record_title ?: '-' }}</div>
                                </td>

                                <td class="pdf-file-name">
                                    {{ $index->original_name ?: $index->file_name ?: '-' }}<br>
                                    <small>{{ strtoupper($index->extension ?: '-') }} / {{ number_format((int) $index->index_text_length) }} حرف مستخرج</small>
                                </td>

                                <td>
                                    <span class="pdf-status-pill pdf-status-{{ $index->statusClass() }}">{{ $index->statusName() }}</span>
                                    <?php if ($index->extractor): ?>
                                        <div class="pdf-processing-method">طريقة المعالجة: {{ $index->extractorName() }}</div>
                                    <?php endif; ?>
                                    <?php if ($index->error_message): ?>
                                        <div class="pdf-error-box">{{ $index->friendlyErrorMessage() }}</div>
                                    <?php endif; ?>
                                    <div class="pdf-bulk-row-state" data-pdf-row-state></div>
                                </td>

                                <td class="pdf-snippet">{{ $index->snippet($filters['q']) ?: 'لا يوجد نص مستخرج بعد.' }}</td>
                                <td>{{ $index->lastIndexedAt()?->format('Y-m-d H:i') ?: '-' }}</td>

                                <?php if ($canIndex): ?>
                                    <td class="no-print">
                                        <?php if ((int) $index->is_indexable === 1): ?>
                                            <form method="POST" action="{{ $indexUrl }}" class="pdf-inline-form">
                                                @csrf
                                                <?php if ($index->resolved_status !== 'unindexed'): ?>
                                                    <input type="hidden" name="force" value="1">
                                                <?php endif; ?>
                                                <label class="pdf-inline-ocr-option">
                                                    <input type="checkbox" name="enable_ocr" value="1">
                                                    تشغيل التعرف الضوئي
                                                </label>
                                                <button type="submit" class="btn btn-sm {{ $index->resolved_status === 'unindexed' ? 'btn-primary' : 'btn-secondary' }}">
                                                    {{ $index->resolved_status === 'unindexed' ? 'فهرسة المرفق' : 'إعادة الفهرسة' }}
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="pdf-status-pill pdf-status-muted">غير مدعوم</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pdf-pagination-wrap">{{ $indexes->links() }}</div>
        <?php else: ?>
            <p class="empty-state">لا توجد مرفقات مطابقة لشروط البحث الحالية.</p>
        <?php endif; ?>
    </div>
</div>

<script src="{{ asset('js/pdf-search-bulk-indexing-v95-1.js') }}?v={{ filemtime(public_path('js/pdf-search-bulk-indexing-v95-1.js')) }}" defer></script>
@endsection
