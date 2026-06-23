@extends('layouts.app')

@section('title', 'عرض المستند')

@section('content')
    <div class="page-title">
        <h1>عرض المستند</h1>

        <div class="actions">
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">رجوع</a>
            <a href="{{ route('documents.print-reference', $document) }}" target="_blank" class="btn btn-warning">طباعة الإشارة</a>
        </div>
    </div>

    <div class="card">
        <h2>الإشارة: {{ $document->reference_number }}</h2>

        <table>
            <tr>
                <th>التاريخ</th>
                <td>{{ $document->formatted_date }}</td>
            </tr>
            <tr>
                <th>العنوان</th>
                <td>{{ $document->title }}</td>
            </tr>
            <tr>
                <th>الإدارة</th>
                <td>{{ $document->department?->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>نوع المستند</th>
                <td>{{ $document->documentType?->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>المرسل</th>
                <td>{{ $document->sender ?? '-' }}</td>
            </tr>
            <tr>
                <th>المستلم</th>
                <td>{{ $document->receiver ?? '-' }}</td>
            </tr>
            <tr>
                <th>درجة السرية</th>
                <td>{{ $document->confidentiality_name }}</td>
            </tr>
            <tr>
                <th>الأولوية</th>
                <td>{{ $document->priority_name }}</td>
            </tr>
            <tr>
                <th>الموضوع</th>
                <td>{{ $document->subject ?? '-' }}</td>
            </tr>
            <tr>
                <th>الوصف</th>
                <td>{{ $document->description ?? '-' }}</td>
            </tr>
            <tr>
                <th>الملاحظات</th>
                <td>{{ $document->notes ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h3>المرفقات</h3>

        @forelse($document->attachments as $attachment)
            <p>
                <strong>{{ $attachment->original_name }}</strong>
                -
                {{ $attachment->file_size_for_humans }}
            </p>
        @empty
            <p>لا توجد مرفقات.</p>
        @endforelse
    </div>
@endsection