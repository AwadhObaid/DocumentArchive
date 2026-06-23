@extends('layouts.app')

@section('title', 'المستندات')

@section('content')
    <div class="page-title">
        <h1>المستندات</h1>

        <a href="{{ route('documents.create') }}" class="btn btn-primary">
            + إضافة مستند جديد
        </a>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>الإشارة</th>
                <th>التاريخ</th>
                <th>العنوان</th>
                <th>الإدارة</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
            </thead>

            <tbody>
            @forelse($documents as $document)
                <tr>
                    <td><strong>{{ $document->reference_number }}</strong></td>
                    <td>{{ $document->formatted_date }}</td>
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->department?->name ?? '-' }}</td>
                    <td>{{ $document->documentType?->name ?? '-' }}</td>
                    <td><span class="badge">{{ $document->status_name }}</span></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                            <a class="btn btn-warning" target="_blank" href="{{ route('documents.print-reference', $document) }}">طباعة الإشارة</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">لا توجد مستندات حتى الآن.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 16px;">
            {{ $documents->links() }}
        </div>
    </div>
@endsection