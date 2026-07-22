@extends('layouts.app')

@section('title', 'فحص المذكرات القديمة')
@section('page_title', 'فحص المذكرات القديمة')
@section('page_subtitle', 'فحص المذكرات القديمة ثم مراجعتها واستيرادها بأمان')

@section('content')
<style>
    .legacy-memo-page { display:grid; gap:18px; }
    .legacy-memo-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
    .legacy-memo-head h1 { margin:0; }
    .legacy-memo-head p { margin:7px 0 0; color:var(--muted, #94a3b8); line-height:1.75; }
    .legacy-memo-grid { display:grid; grid-template-columns:2fr 1fr 1fr; gap:12px; align-items:end; }
    .legacy-memo-grid .form-group { margin:0; }
    .legacy-memo-note { padding:14px 16px; border:1px solid rgba(59,130,246,.35); border-radius:14px; background:rgba(59,130,246,.08); line-height:1.85; }
    .legacy-memo-warning { border-color:rgba(245,158,11,.45); background:rgba(245,158,11,.09); }
    .legacy-memo-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .legacy-memo-table td { vertical-align:middle; }
    .legacy-memo-path { direction:ltr; unicode-bidi:plaintext; word-break:break-all; white-space:normal; }
    @media (max-width:900px) { .legacy-memo-grid { grid-template-columns:1fr; } }
</style>

<div class="legacy-memo-page">
    <div class="legacy-memo-head">
        <div>
            <h1>فحص المذكرات القديمة</h1>
            <p>
                المرحلة V84 تقوم بالجرد واكتشاف المكرر واقتراح البيانات،
                والمرحلة V84.1 تستورد الملفات المحددة بعد مراجعة الموضوع والتاريخ.
                الملفات الأصلية على Server-1 لا تُحذف ولا تُعدّل.
            </p>
        </div>

        <div class="legacy-memo-actions">
            <a class="btn btn-light" href="{{ route('memos.index') }}">العودة إلى المذكرات</a>
        </div>
    </div>

    <div class="legacy-memo-note">
        <strong>ترقيم المذكرات المعتمد:</strong>
        يبدأ من <b>2600000</b>.
        الرقم التالي المتوقع حاليًا:
        <b dir="ltr">{{ $nextMemo['memo_number'] ?? '2600000' }}</b>.
        أرقام الفحص مقترحة وغير محجوزة؛ يُحجز الرقم النهائي أثناء تنفيذ الاستيراد.
    </div>

    <div class="legacy-memo-note legacy-memo-warning">
        يجب أن يكون مسار المجلد قابلًا للقراءة بواسطة حساب Apache/Laragon على الجهاز الذي يشغّل النظام.
        ستُحفظ نتائج الجرد وبصمات SHA-256 في قاعدة البيانات، لكن الملفات الأصلية لن تتغير.
    </div>

    <div class="card">
        <div class="card-header">
            <h2>إنشاء فحص تجريبي</h2>
        </div>

        <form method="POST" action="{{ route('memo-legacy-import.scan') }}">
            @csrf

            <div class="legacy-memo-grid">
                <div class="form-group">
                    <label>مسار مجلد المذكرات على Server-1</label>
                    <input type="text"
                           name="source_root"
                           value="{{ old('source_root', $defaultSourceRoot) }}"
                           required
                           dir="ltr"
                           placeholder="\\Server-1\اسم المجلد\المذكرات">
                </div>

                <div class="form-group">
                    <label>الحد الأعلى للملفات</label>
                    <input type="number"
                           name="limit"
                           min="1"
                           max="50000"
                           value="{{ old('limit', 10000) }}">
                </div>

                <div class="form-group">
                    <label style="display:flex;gap:8px;align-items:center;min-height:42px;">
                        <input type="checkbox"
                               name="recursive"
                               value="1"
                               @checked(old('recursive', true))>
                        فحص المجلدات الفرعية
                    </label>
                </div>
            </div>

            <div class="legacy-memo-actions" style="margin-top:16px;">
                <button class="btn btn-primary" type="submit">
                    بدء الفحص التجريبي
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>عمليات الفحص السابقة</h2>
        </div>

        @if($runs->count())
            <div class="table-responsive">
                <table class="table legacy-memo-table">
                    <thead>
                        <tr>
                            <th>العملية</th>
                            <th>حالة الفحص</th>
                            <th>حالة الاستيراد</th>
                            <th>المسار</th>
                            <th>الملفات</th>
                            <th>جاهزة</th>
                            <th>تحتاج مراجعة</th>
                            <th>مكررة</th>
                            <th>التاريخ</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runs as $run)
                            <tr>
                                <td>#{{ $run->id }}</td>
                                <td>{{ $run->status_label }}</td>
                                <td>
                                    {{ $run->import_status_label }}
                                    @if($run->imported_files)
                                        <div class="text-muted" style="font-size:11px;">
                                            {{ number_format($run->imported_files) }} مستوردة
                                        </div>
                                    @endif
                                </td>
                                <td class="legacy-memo-path">{{ $run->source_root }}</td>
                                <td>{{ number_format($run->total_files) }}</td>
                                <td>{{ number_format($run->ready_files) }}</td>
                                <td>{{ number_format($run->needs_review_files) }}</td>
                                <td>{{ number_format($run->duplicate_system_files + $run->duplicate_scan_files) }}</td>
                                <td>{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a class="btn btn-sm btn-light"
                                       href="{{ route('memo-legacy-import.show', $run) }}">
                                        عرض التقرير
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="empty-state">لم تُنفذ أي عملية فحص حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
