@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page_title', 'لوحة التحكم')
@section('page_subtitle', 'ملخص سريع لحركة الأرشيف الإلكتروني')

@section('content')
    <div class="stats-grid">
        <div class="stat-card">
            <span>📄</span>
            <div>
                <strong>{{ $stats['documents_count'] }}</strong>
                <p>إجمالي الكتب</p>
            </div>
        </div>

        <div class="stat-card">
            <span>📅</span>
            <div>
                <strong>{{ $stats['today_documents_count'] }}</strong>
                <p>كتب اليوم</p>
            </div>
        </div>

        <div class="stat-card">
            <span>📎</span>
            <div>
                <strong>{{ $stats['attachments_count'] }}</strong>
                <p>المرفقات</p>
            </div>
        </div>

        <div class="stat-card">
            <span>🗑️</span>
            <div>
                <strong>{{ $stats['trashed_documents_count'] }}</strong>
                <p>في سلة المحذوفات</p>
            </div>
        </div>

        @if(auth()->user()?->hasPermission('users.manage'))
            <div class="stat-card">
                <span>👥</span>
                <div>
                    <strong>{{ $stats['users_count'] }}</strong>
                    <p>المستخدمون</p>
                </div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="page-title">
            <h2>آخر الكتب</h2>
            @if(auth()->user()?->hasPermission('documents.create'))
            <a href="{{ route('documents.create') }}" class="btn btn-primary">+ إضافة كتاب</a>
            @endif
        </div>

        <table>
            <thead>
            <tr>
                <th>رقم الكتاب</th>
                <th>تاريخ الكتاب</th>
                <th>موضوع الكتاب</th>
                <th>البوليصة الرئيسية</th>
                <th>الإدارة</th>
                <th>إجراء</th>
            </tr>
            </thead>

            <tbody>
            @forelse($latestDocuments as $document)
                <tr>
                    <td><strong>{{ $document->reference_number }}</strong></td>
                    <td>{{ $document->formatted_date }}</td>
                    <td>{{ $document->subject ?? $document->title }}</td>
                    <td>{{ $document->main_policy_number ?? '-' }}</td>
                    <td>{{ $document->department?->name ?? '-' }}</td>
                    <td>
                        @if(auth()->user()?->hasPermission('documents.view'))
                        <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">لا توجد كتب حتى الآن.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
