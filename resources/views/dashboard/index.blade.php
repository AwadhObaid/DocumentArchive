@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page_title', 'لوحة التحكم')

@section('content')
    <div class="dashboard-hero">
        <div>
            <span class="hero-badge">قسم الشحن والتأمين</span>
            <h2>مرحباً بك في نظام الأرشيف الإلكتروني</h2>
            <p>إدارة الكتب، أرقام الكتب، البوالص، المرفقات، وسلة المحذوفات من مكان واحد.</p>
        </div>

        <div class="hero-actions">
            <a href="{{ route('documents.create') }}" class="btn btn-primary">+ إضافة كتاب جديد</a>
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">عرض الكتب</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📄</div>
            <div>
                <span>إجمالي الكتب</span>
                <strong>{{ number_format($stats['documents_total']) }}</strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">📅</div>
            <div>
                <span>كتب اليوم</span>
                <strong>{{ number_format($stats['documents_today']) }}</strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">🗓️</div>
            <div>
                <span>كتب هذا الشهر</span>
                <strong>{{ number_format($stats['documents_month']) }}</strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">📎</div>
            <div>
                <span>إجمالي المرفقات</span>
                <strong>{{ number_format($stats['attachments_total']) }}</strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red">⏳</div>
            <div>
                <span>بانتظار المسح الضوئي</span>
                <strong>{{ number_format($stats['documents_without_attachment']) }}</strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon slate">🗑️</div>
            <div>
                <span>سلة المحذوفات</span>
                <strong>{{ number_format($stats['trash_total']) }}</strong>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <h3>آخر الكتب المضافة</h3>
                <a href="{{ route('documents.index') }}" class="small-link">عرض الكل</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>رقم الكتاب</th>
                        <th>تاريخ الكتاب</th>
                        <th>موضوع الكتاب</th>
                        <th>البوليصة الرئيسية</th>
                        <th>الحالة</th>
                        <th>إجراء</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($latestDocuments as $document)
                        <tr>
                            <td><strong>{{ $document->reference_number }}</strong></td>
                            <td>{{ $document->formatted_date }}</td>
                            <td>{{ $document->subject ?: $document->title }}</td>
                            <td>{{ $document->main_policy_number ?? '-' }}</td>
                            <td>
                                @if($document->mainAttachment)
                                    <span class="badge badge-green">مؤرشف</span>
                                @else
                                    <span class="badge badge-orange">مسجل</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary btn-sm">عرض</a>
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
        </div>

        <div class="card">
            <div class="card-header">
                <h3>أنواع الكتب الأكثر استخداماً</h3>
                <a href="{{ route('document-types.index') }}" class="small-link">إدارة الأنواع</a>
            </div>

            <div class="type-list">
                @forelse($documentsByType as $type)
                    <div class="type-item">
                        <div>
                            <strong>{{ $type->name }}</strong>
                            <span>{{ $type->code ?? '-' }}</span>
                        </div>
                        <b>{{ number_format($type->documents_count) }}</b>
                    </div>
                @empty
                    <p>لا توجد أنواع كتب حتى الآن.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
