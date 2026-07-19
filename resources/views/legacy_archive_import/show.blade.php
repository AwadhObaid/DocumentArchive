@extends('layouts.app')

@section('page_title', 'تقرير استيراد الأرشيف القديم')
@section('page_subtitle', $run->mode_label . ' — العملية رقم ' . $run->id)

@section('content')
<div class="legacy-import-page">
    <section class="legacy-import-hero legacy-import-report-hero">
        <div>
            <span class="legacy-import-kicker">{{ $run->mode_label }}</span>
            <h2>{{ $run->status_label }}</h2>
            <p>
                المصدر: {{ $run->original_filename }}
                @if($run->source_root_override)
                    — الجذر البديل: {{ $run->source_root_override }}
                @endif
            </p>
        </div>
        <a href="{{ route('legacy-archive-import.index') }}" class="btn btn-secondary">العودة للأداة</a>
    </section>

    <section class="legacy-import-stats">
        <div><span>الإجمالي</span><strong>{{ number_format($run->total_rows) }}</strong></div>
        <div><span>جاهزة</span><strong>{{ number_format($run->ready_rows) }}</strong></div>
        <div><span>مستوردة</span><strong>{{ number_format($run->imported_rows) }}</strong></div>
        <div><span>مكررة</span><strong>{{ number_format($run->duplicate_rows) }}</strong></div>
        <div><span>مرفقات مفقودة</span><strong>{{ number_format($run->missing_file_rows) }}</strong></div>
        <div><span>أخطاء</span><strong>{{ number_format($run->failed_rows) }}</strong></div>
        <div><span>ملفات منسوخة</span><strong>{{ number_format($run->copied_files) }}</strong></div>
        <div><span>حجم النسخ</span><strong>{{ number_format($run->copied_bytes / 1048576, 2) }} MB</strong></div>
    </section>

    <section class="legacy-import-card">
        <div class="legacy-import-section-head">
            <div>
                <h3>تفاصيل السجلات</h3>
                <p>يعرض كل سجل قديم وحالة العثور على الملف والاستيراد.</p>
            </div>
        </div>

        <div class="legacy-import-table-wrap">
            <table class="legacy-import-table legacy-import-details-table">
                <thead>
                    <tr>
                        <th>ID القديم</th>
                        <th>رقم الكتاب</th>
                        <th>الحالة</th>
                        <th>الملف</th>
                        <th>الرسالة</th>
                        <th>الكتاب الجديد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->source_record_id ?: '-' }}</td>
                            <td>{{ $item->reference_number ?: '-' }}</td>
                            <td>
                                <span class="legacy-import-badge is-{{ $item->status_tone }}">
                                    {{ $item->status_label }}
                                </span>
                            </td>
                            <td>
                                @if($item->file_exists)
                                    <span class="legacy-import-file-ok">موجود</span>
                                @else
                                    <span class="legacy-import-file-missing">مفقود</span>
                                @endif
                                @if($item->resolved_source_path)
                                    <small title="{{ $item->resolved_source_path }}">{{ $item->resolved_source_path }}</small>
                                @elseif($item->source_path)
                                    <small title="{{ $item->source_path }}">{{ $item->source_path }}</small>
                                @endif
                            </td>
                            <td>{{ $item->message }}</td>
                            <td>
                                @if($item->document)
                                    <a href="{{ route('documents.show', $item->document) }}">
                                        فتح الكتاب #{{ $item->document->id }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="legacy-import-empty">لا توجد تفاصيل.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="legacy-import-pagination">
            {{ $items->links() }}
        </div>
    </section>
</div>
@endsection
