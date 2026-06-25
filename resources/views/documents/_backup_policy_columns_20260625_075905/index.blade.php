@extends('layouts.app')

@section('title', 'الكتب')

@section('content')
@php
    $user = auth()->user();

    $can = function (string $permission) use ($user): bool {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (isset($user->role) && in_array($user->role, ['admin', 'مدير النظام'], true)) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($permission);
        }

        $permissions = $user->permissions ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        return is_array($permissions)
            ? (in_array($permission, $permissions, true) || !empty($permissions[$permission] ?? false))
            : true;
    };

    $statusLabel = fn ($value) => $statusOptions[$value] ?? ($value ?: '-');
    $priorityLabel = fn ($value) => $priorityOptions[$value] ?? ($value ?: '-');
@endphp

<style>
    .documents-page { display: flex; flex-direction: column; gap: 16px; }
    .documents-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .documents-head h1 { margin: 0; font-size: clamp(26px, 3vw, 38px); font-weight: 950; }
    .documents-head p { margin: 7px 0 0; color: #94a3b8; font-weight: 700; }
    .doc-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .doc-summary-card { background: rgba(15,23,42,.72); border: 1px solid rgba(148,163,184,.20); border-radius: 18px; padding: 16px; }
    .doc-summary-card span { display: block; color: #94a3b8; font-size: 12px; font-weight: 900; margin-bottom: 8px; }
    .doc-summary-card strong { display: block; color: #fff; font-size: 26px; font-weight: 950; }
    .advanced-filter-card { background: rgba(15,23,42,.72); border: 1px solid rgba(148,163,184,.20); border-radius: 18px; padding: 16px; }
    .advanced-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .filter-actions { display: flex; align-items: end; gap: 8px; flex-wrap: wrap; }
    .documents-table-wrap { overflow-x: auto; }
    .documents-table { width: 100%; border-collapse: collapse; min-width: 980px; }
    .documents-table th, .documents-table td { padding: 12px 10px; border-bottom: 1px solid rgba(148,163,184,.16); text-align: right; vertical-align: middle; white-space: nowrap; }
    .documents-table th { color: #94a3b8; font-size: 12px; font-weight: 950; }
    .documents-table td { color: #f8fafc; font-weight: 750; }
    .document-title-cell { min-width: 260px; white-space: normal !important; }
    .document-title-cell strong { display: block; margin-bottom: 5px; }
    .document-title-cell small { color: #94a3b8; font-weight: 700; }
    .pill { display: inline-flex; padding: 5px 10px; border-radius: 999px; background: rgba(37,99,235,.14); border: 1px solid rgba(37,99,235,.28); font-size: 12px; font-weight: 900; color: #dbeafe; }
    .pill-muted { background: rgba(148,163,184,.10); border-color: rgba(148,163,184,.22); color: #cbd5e1; }
    .pill-warning { background: rgba(245,158,11,.13); border-color: rgba(245,158,11,.28); color: #fde68a; }
    .pill-danger { background: rgba(239,68,68,.13); border-color: rgba(239,68,68,.28); color: #fecaca; }
    .empty-documents { text-align: center; padding: 34px; color: #94a3b8; font-weight: 900; }
    @media (max-width: 1100px) { .doc-summary-grid, .advanced-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .doc-summary-grid, .advanced-filter-grid { grid-template-columns: 1fr; } .filter-actions { align-items: stretch; } .filter-actions .btn { width: 100%; } }
</style>

<div class="documents-page">
    <div class="documents-head">
        <div>
            <h1>الكتب</h1>
            <p>بحث وفرز سريع في الكتب والمرفقات والبوالص.</p>
        </div>

        <div class="actions">
            @if($can('documents.create') || $can('documents.add'))
                <a href="{{ route('documents.create') }}" class="btn btn-primary">+ إضافة كتاب جديد</a>
            @endif
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">تحديث</a>
        </div>
    </div>

    <div class="doc-summary-grid">
        <div class="doc-summary-card"><span>إجمالي الكتب</span><strong>{{ number_format($summary['total'] ?? 0) }}</strong></div>
        <div class="doc-summary-card"><span>نتائج الفلترة</span><strong>{{ number_format($summary['filtered'] ?? 0) }}</strong></div>
        <div class="doc-summary-card"><span>كتب لديها مرفقات</span><strong>{{ number_format($summary['with_attachments'] ?? 0) }}</strong></div>
        <div class="doc-summary-card"><span>كتب بلا مرفقات</span><strong>{{ number_format($summary['without_attachments'] ?? 0) }}</strong></div>
    </div>

    <div class="advanced-filter-card">
        <form method="GET" action="{{ route('documents.index') }}">
            <div class="advanced-filter-grid">
                <div class="form-group">
                    <label>بحث عام</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="رقم الكتاب / العنوان / الموضوع / البوليصة / المرسل">
                </div>

                <div class="form-group">
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">كل الإدارات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>نوع الكتاب</label>
                    <select name="document_type_id">
                        <option value="">كل الأنواع</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('document_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status">
                        <option value="">كل الحالات</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>درجة السرية</label>
                    <select name="confidentiality">
                        <option value="">كل الدرجات</option>
                        @foreach($confidentialityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('confidentiality') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="priority">
                        <option value="">كل الأولويات</option>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>المرفقات</label>
                    <select name="has_attachment">
                        <option value="">الكل</option>
                        <option value="yes" @selected(request('has_attachment') === 'yes')>لديه مرفق</option>
                        <option value="no" @selected(request('has_attachment') === 'no')>بدون مرفق</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الترتيب</label>
                    <select name="sort">
                        <option value="created_at" @selected(request('sort', 'created_at') === 'created_at')>تاريخ الإضافة</option>
                        <option value="reference_date" @selected(request('sort') === 'reference_date')>تاريخ الكتاب</option>
                        <option value="reference_number" @selected(request('sort') === 'reference_number')>رقم الكتاب</option>
                        <option value="title" @selected(request('sort') === 'title')>العنوان</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </div>

                <div class="form-group">
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </div>

                <div class="form-group">
                    <label>الاتجاه</label>
                    <select name="direction">
                        <option value="desc" @selected(request('direction', 'desc') === 'desc')>الأحدث أولاً</option>
                        <option value="asc" @selected(request('direction') === 'asc')>الأقدم أولاً</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>عدد النتائج</label>
                    <select name="per_page">
                        @foreach([15, 25, 50, 100] as $count)
                            <option value="{{ $count }}" @selected((int) request('per_page', 15) === $count)>{{ $count }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="filter-actions" style="margin-top: 14px;">
                <button type="submit" class="btn btn-primary">بحث</button>
                <a href="{{ route('documents.index') }}" class="btn btn-secondary">إلغاء الفلترة</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="documents-table-wrap">
            <table class="documents-table">
                <thead>
                <tr>
                    <th>رقم الكتاب</th>
                    <th>التاريخ</th>
                    <th>العنوان والموضوع</th>
                    <th>الإدارة</th>
                    <th>النوع</th>
                    <th>الحالة</th>
                    <th>الأولوية</th>
                    <th>المرفقات</th>
                    <th>إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($documents as $document)
                    @php
                        $priorityClass = match($document->priority ?? '') {
                            'urgent' => 'pill-danger',
                            'high' => 'pill-warning',
                            default => 'pill-muted',
                        };
                        $attachmentsCount = $document->attachments_count ?? ($document->mainAttachment ? 1 : 0);
                    @endphp
                    <tr>
                        <td><strong>{{ $document->reference_number }}</strong></td>
                        <td>{{ $document->formatted_date ?? optional($document->reference_date)->format('Y-m-d') ?? '-' }}</td>
                        <td class="document-title-cell">
                            <strong>{{ $document->title ?: '-' }}</strong>
                            <small>{{ \Illuminate\Support\Str::limit($document->subject ?: $document->description ?: '-', 95) }}</small>
                        </td>
                        <td>{{ $document->department?->name ?? '-' }}</td>
                        <td>{{ $document->documentType?->name ?? '-' }}</td>
                        <td><span class="pill">{{ $document->status_name ?? $statusLabel($document->status ?? null) }}</span></td>
                        <td><span class="pill {{ $priorityClass }}">{{ $document->priority_name ?? $priorityLabel($document->priority ?? null) }}</span></td>
                        <td>
                            @if($attachmentsCount > 0)
                                <span class="pill">{{ $attachmentsCount }} مرفق</span>
                            @else
                                <span class="pill pill-muted">لا يوجد</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @if($can('documents.view'))
                                    <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                                @endif
                                @if($can('documents.edit'))
                                    <a class="btn btn-primary" href="{{ route('documents.edit', $document) }}">تعديل</a>
                                @endif
                                @if($can('documents.print'))
                                    <a class="btn btn-warning" target="_blank" href="{{ route('documents.print-reference', $document) }}">طباعة الرقم</a>
                                @endif
                                @if(($can('documents.delete') || $can('documents.destroy')))
                                    <form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm="هل أنت متأكد من حذف هذا الكتاب؟">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger">حذف</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="empty-documents">لا توجد كتب مطابقة لمعايير البحث الحالية.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $documents->links() }}
        </div>
    </div>
</div>
@endsection