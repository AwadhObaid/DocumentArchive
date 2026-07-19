@extends('layouts.app')

@section('title', 'ترتيب المرفقات القديمة')
@section('page_title', 'ترتيب المرفقات القديمة')
@section('page_subtitle', 'فحص ونقل مرفقات الكتب القديمة إلى مسارات الشركة ونوع العملية والسنة')

@section('content')
<div class="v75v80-page">
    <div class="v75v80-hero">
        <div>
            <h2>أداة آمنة لترتيب المرفقات القديمة</h2>
            <p>ابدأ دائماً بفحص فقط. التنفيذ الفعلي ينسخ الملفات إلى المسار الجديد ويحدث قاعدة البيانات، مع إبقاء النسخة القديمة افتراضياً.</p>
        </div>
        <div class="v75v80-path-box">
            <strong>تنبيه أمان</strong>
            <span>لا يتم حذف النسخ القديمة إلا إذا فعّلت خيار الحذف صراحةً.</span>
        </div>
    </div>

    <div class="v75v80-grid v75v80-stats-grid">
        <div class="v75v80-stat"><span>إجمالي مفحوص</span><strong>{{ $summary['total'] }}</strong></div>
        <div class="v75v80-stat"><span>جاهز للنقل</span><strong>{{ $summary['ready'] }}</strong></div>
        <div class="v75v80-stat"><span>مرتب مسبقًا</span><strong>{{ $summary['already_sorted'] }}</strong></div>
        <div class="v75v80-stat"><span>ناقص تصنيف</span><strong>{{ $summary['missing_classification'] }}</strong></div>
        <div class="v75v80-stat"><span>ملفات مفقودة</span><strong>{{ $summary['missing'] }}</strong></div>
    </div>

    <div class="v75v80-card">
        <form method="GET" action="{{ route('attachments-migration.index') }}" class="v75v80-inline-form">
            <label>حد الفحص</label>
            <input type="number" name="limit" value="{{ $limit }}" min="1" max="5000">
            <button class="btn btn-secondary" type="submit">تحديث الملخص</button>
        </form>
    </div>

    <div class="v75v80-grid v75v80-two">
        <div class="v75v80-card">
            <h3>فحص فقط Dry Run</h3>
            <p>ينشئ سجل فحص بدون نقل أي ملف وبدون تعديل قاعدة البيانات.</p>
            <form method="POST" action="{{ route('attachments-migration.dry-run') }}">
                @csrf
                <input type="hidden" name="limit" value="{{ $limit }}">
                <button class="btn btn-primary" type="submit">إنشاء تقرير فحص</button>
            </form>
        </div>

        <div class="v75v80-card v75v80-danger-card">
            <h3>تنفيذ النقل</h3>
            <p>اكتب عبارة التأكيد التالية بالضبط: <strong>تنفيذ النقل</strong></p>
            <form method="POST" action="{{ route('attachments-migration.execute') }}">
                @csrf
                <input type="hidden" name="limit" value="{{ $limit }}">
                <div class="form-group">
                    <label>عبارة التأكيد</label>
                    <input type="text" name="confirm_phrase" placeholder="تنفيذ النقل">
                </div>
                <label class="v75v80-check">
                    <input type="checkbox" name="delete_original" value="1">
                    حذف النسخة القديمة بعد نجاح النسخ والتحديث
                </label>
                <button class="btn btn-danger" type="submit">تنفيذ ترتيب المرفقات</button>
            </form>
        </div>
    </div>

    <div class="v75v80-card">
        <h3>عينة من نتيجة الفحص الحالية</h3>
        <div class="table-responsive">
            <table class="v75v80-table">
                <thead>
                    <tr>
                        <th>الكتاب</th>
                        <th>الحالة</th>
                        <th>المسار الحالي</th>
                        <th>المسار الجديد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary['items'] as $item)
                        <tr>
                            <td>{{ $item['reference_number'] ?: '-' }}</td>
                            <td><span class="v75v80-badge status-{{ $item['status'] }}">{{ $item['message'] }}</span></td>
                            <td class="v75v80-ltr">{{ $item['source_path'] ?: '-' }}</td>
                            <td class="v75v80-ltr">{{ $item['target_path'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">لا توجد مرفقات للفحص.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="v75v80-card">
        <h3>آخر عمليات الفحص والتنفيذ</h3>
        <div class="table-responsive">
            <table class="v75v80-table">
                <thead>
                    <tr>
                        <th>الوقت</th>
                        <th>النوع</th>
                        <th>الحالة</th>
                        <th>الإجمالي</th>
                        <th>تم</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $run->mode_name }}</td>
                            <td>{{ $run->status_name }}</td>
                            <td>{{ $run->total_items }}</td>
                            <td>{{ $run->moved_items + $run->copied_items }}</td>
                            <td><a class="btn btn-secondary btn-sm" href="{{ route('attachments-migration.show', $run) }}">عرض</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">لا توجد عمليات محفوظة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
