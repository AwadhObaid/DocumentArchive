@extends('layouts.app')

@section('title', 'عرض الكتاب')

@section('content')
@php
    $value = function ($row, string $key, $default = '-') {
        $v = data_get($row, $key);
        return ($v === null || $v === '') ? $default : $v;
    };
    $docId = $value($document ?? null, 'id', null);
    $attachmentsList = collect(data_get($document ?? null, 'attachments', $attachments ?? []));
@endphp

<div class="page-header">
    <div>
        <h1>عرض الكتاب</h1>
        <p>بيانات الكتاب، البوالص، الم
رفقات، وسجل الحركة.</p>
    </div>
    <div class="page-actions no-print" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a href="{{ url('/documents') }}" class="btn btn-light">رجوع</a>
        @if($docId)
            <a href="{{ url('/documents/'.$docId.'/edit') }}" class="btn btn-primary">تعديل</a>
            <a href="{{ url('/documents/'.$docId.'/activity') }}" class="btn btn-info">سجل الحركة</a>
            <a href="{{ url('/documents/'.$docId.'/print-reference') }}" class="btn btn-warning">طباعة رقم
 الكتاب</a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>رقم
 الكتاب: {{ $value($document ?? null, 'reference_number') }}</h2>
    </div>
    <div class="table-responsive">
        <table class="table details-table">
            <tbody>
                <tr><th>تاريخ الكتاب</th><td>{{ optional($value($document ?? null, 'reference_date', null))->format('Y-m-d') ?? $value($document ?? null, 'reference_date') }}</td></tr>
                <tr><th>م
وضوع الكتاب</th><td>{{ $value($document ?? null, 'subject') }}</td></tr>
                <tr><th>عنوان الكتاب</th><td>{{ $value($document ?? null, 'title') }}</td></tr>
                <tr><th>البوليصة الرئيسية</th><td>{{ $value($document ?? null, 'main_policy_number') }}</td></tr>
                <tr><th>البوليصة الفرعية</th><td>{{ $value($document ?? null, 'sub_policy_number') }}</td></tr>
                <tr><th>الإدارة</th><td>{{ $value($document ?? null, 'department.name', $value($document ?? null, 'department_name')) }}</td></tr>
                <tr><th>نوع الكتاب</th><td>{{ $value($document ?? null, 'documentType.name', $value($document ?? null, 'document_type_name')) }}</td></tr>
                <tr><th>الم
رسل</th><td>{{ $value($document ?? null, 'sender') }}</td></tr>
                <tr><th>الم
ستلم
</th><td>{{ $value($document ?? null, 'recipient') }}</td></tr>
                <tr><th>الحالة</th><td>{{ $value($document ?? null, 'status') }}</td></tr>
                <tr><th>درجة السرية</th><td>{{ $value($document ?? null, 'confidentiality') }}</td></tr>
                <tr><th>الأولوية</th><td>{{ $value($document ?? null, 'priority') }}</td></tr>
                <tr><th>الوصف</th><td>{{ $value($document ?? null, 'description') }}</td></tr>
                <tr><th>الم
لاحظات</th><td>{{ $value($document ?? null, 'notes') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h2>الم
رفقات</h2></div>
    @if($attachmentsList->count())
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>اسم
 الم
لف</th>
                        <th>النوع</th>
                        <th>الحجم
</th>
                        <th class="no-print">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attachmentsList as $attachment)
                        @php $attId = data_get($attachment, 'id'); @endphp
                        <tr>
                            <td>{{ data_get($attachment, 'original_name', data_get($attachment, 'file_name', 'م
رفق')) }}</td>
                            <td>{{ data_get($attachment, 'mime_type', '-') }}</td>
                            <td>{{ data_get($attachment, 'size_human', data_get($attachment, 'file_size', '-')) }}</td>
                            <td class="no-print">
                                @if($attId)
                                    <a class="btn btn-sm btn-light" href="{{ url('/attachments/'.$attId.'/preview') }}">م
عاينة</a>
                                    <a class="btn btn-sm btn-primary" href="{{ url('/attachments/'.$attId.'/download') }}">تنزيل</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="empty-state">لا توجد م
رفقات.</p>
    @endif
</div>
@endsection