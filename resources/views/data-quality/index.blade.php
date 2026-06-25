@extends('layouts.app')

@section('title', 'جودة البيانات')

@section('content')
    <div class="page-title">
        <div>
            <h1>جودة البيانات</h1>
            <p class="muted">مراجعة الكتب التي تحتاج انتباه: مرفقات ناقصة، بوالص مكررة، أو بيانات غير مكتملة.</p>
        </div>
        <div class="actions">
            <a href="{{ route('data-quality.index') }}" class="btn btn-primary">تحديث المراجعة</a>
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">رجوع</a>
        </div>
    </div>

    @unless($databaseReady)
        <div class="alert-error" style="margin-bottom: 18px;">
            لا يمكن تنفيذ مراجعة جودة البيانات لأن جدول الكتب غير موجود. افتح فحص النظام أولاً.
        </div>
    @endunless

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat-card">
            <div class="stat-label">كتب بلا مرفقات</div>
            <div class="stat-value">{{ $summary['without_attachments'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">بوالص رئيسية مكررة</div>
            <div class="stat-value">{{ $summary['duplicate_main_policies'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">بوالص فرعية مكررة</div>
            <div class="stat-value">{{ $summary['duplicate_sub_policies'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">كتب في سلة المحذوفات</div>
            <div class="stat-value">{{ $summary['deleted_documents'] }}</div>
        </div>
    </div>

    @php
        $hasIssues = array_sum($summary) > 0;
    @endphp

    @if($databaseReady && !$hasIssues)
        <div class="alert-success" style="margin-bottom: 18px;">
            لا توجد ملاحظات جودة بيانات حالياً. البيانات تبدو منظمة وسليمة.
        </div>
    @elseif($databaseReady)
        <div class="alert-warning" style="margin-bottom: 18px;">
            توجد ملاحظات يفضل مراجعتها. هذه الصفحة لا تمنع العمل، لكنها تساعد المدير على تنظيف البيانات.
        </div>
    @endif

    <div class="grid-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 18px;">
        <div class="card">
            <h3 style="margin-bottom: 14px;">كتب بلا مرفقات</h3>
            @if($withoutAttachments->isEmpty())
                <p class="muted">لا توجد كتب بلا مرفقات.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>رقم الكتاب</th>
                            <th>التاريخ</th>
                            <th>الموضوع</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withoutAttachments as $document)
                            <tr>
                                <td>{{ $document->reference_number }}</td>
                                <td>{{ $document->reference_date ? \Illuminate\Support\Carbon::parse($document->reference_date)->format('Y-m-d') : '-' }}</td>
                                <td>{{ $document->subject ?: $document->title }}</td>
                                <td><a class="btn btn-secondary btn-sm" href="{{ route('documents.show', $document) }}">عرض</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h3 style="margin-bottom: 14px;">كتب في سلة المحذوفات</h3>
            @if($deletedDocuments->isEmpty())
                <p class="muted">لا توجد كتب محذوفة حالياً.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>رقم الكتاب</th>
                            <th>الموضوع</th>
                            <th>تاريخ الحذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deletedDocuments as $document)
                            <tr>
                                <td>{{ $document->reference_number ?? '-' }}</td>
                                <td>{{ $document->subject ?? $document->title ?? '-' }}</td>
                                <td>{{ $document->deleted_at ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <h3 style="margin-bottom: 14px;">البوالص الرئيسية المكررة</h3>
        @include('data-quality.partials.duplicate-policy-table', [
            'groups' => $duplicateMainPolicies,
            'label' => 'البوليصة الرئيسية',
        ])
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <h3 style="margin-bottom: 14px;">البوالص الفرعية المكررة</h3>
        @include('data-quality.partials.duplicate-policy-table', [
            'groups' => $duplicateSubPolicies,
            'label' => 'البوليصة الفرعية',
        ])
    </div>

    <div class="grid-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
        <div class="card">
            <h3 style="margin-bottom: 14px;">كتب بدون بوليصة رئيسية</h3>
            @include('data-quality.partials.simple-document-table', ['documents' => $missingMainPolicies])
        </div>
        <div class="card">
            <h3 style="margin-bottom: 14px;">كتب بدون بوليصة فرعية</h3>
            @include('data-quality.partials.simple-document-table', ['documents' => $missingSubPolicies])
        </div>
    </div>

    <style>
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr !important; }
        }
    </style>
@endsection
