@extends('layouts.app')

@section('title', 'مشاركة المرفقات')
@section('page_title', 'مشاركة المرفقات')
@section('page_subtitle', 'إدارة روابط آمنة ومؤقتة لمشاركة مرفقات الكتب.')

@section('content')
<div class="share-page">
    <div class="share-page-header">
        <div>
            <h1>مشاركة المرفقات</h1>
            <p>أنشئ روابط مؤقتة للمرفقات لاستخدامها في واتساب أو البريد الإلكتروني مع سجل مشاهدة وتحميل.</p>
        </div>
        <div class="share-actions">
            @if(auth()->user()?->hasPermission('attachment_shares.create'))
                <a href="{{ route('shared-attachment-links.create') }}" class="btn btn-primary">إنشاء رابط مشاركة</a>
            @endif
        </div>
    </div>

    <div class="share-summary-grid">
        <div class="share-stat-card"><div><span>إجمالي الروابط</span><strong>{{ $summary['total'] }}</strong></div><b>🔗</b></div>
        <div class="share-stat-card success"><div><span>روابط نشطة</span><strong>{{ $summary['active'] }}</strong></div><b>✅</b></div>
        <div class="share-stat-card warning"><div><span>روابط منتهية</span><strong>{{ $summary['expired'] }}</strong></div><b>⏳</b></div>
        <div class="share-stat-card info"><div><span>مرات التحميل</span><strong>{{ $summary['downloads'] }}</strong></div><b>⬇️</b></div>
    </div>

    <form class="share-filter-card" method="GET" action="{{ route('shared-attachment-links.index') }}">
        <div class="share-filter-grid">
            <div>
                <label>حالة الرابط</label>
                <select name="status">
                    <option value="all" @selected($status === 'all')>كل الروابط</option>
                    <option value="active" @selected($status === 'active')>نشطة</option>
                    <option value="expired" @selected($status === 'expired')>منتهية</option>
                    <option value="disabled" @selected($status === 'disabled')>معطلة</option>
                </select>
            </div>
            <div class="share-filter-actions">
                <button class="btn btn-primary" type="submit">تصفية</button>
                <a class="btn btn-light" href="{{ route('shared-attachment-links.index') }}">إعادة ضبط</a>
            </div>
        </div>
    </form>

    <div class="share-card">
        <div class="table-responsive">
            <table class="table share-table">
                <thead>
                    <tr>
                        <th>الكتاب</th>
                        <th>الحالة</th>
                        <th>المرفقات</th>
                        <th>المشاهدات</th>
                        <th>التحميلات</th>
                        <th>انتهاء الصلاحية</th>
                        <th class="no-print">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($links as $link)
                        <tr>
                            <td>
                                <strong>{{ $link->document?->reference_number ?: '-' }}</strong>
                                <small>{{ \Illuminate\Support\Str::limit($link->document?->subject ?: $link->document?->title ?: $link->title ?: '-', 70) }}</small>
                            </td>
                            <td><span class="share-badge {{ $link->status_class }}">{{ $link->status_name }}</span></td>
                            <td>{{ $link->items_count }} مرفق</td>
                            <td>{{ $link->view_count }}</td>
                            <td>{{ $link->download_count }}</td>
                            <td>{{ $link->expires_at ? $link->expires_at->format('Y-m-d H:i') : 'غير محدد' }}</td>
                            <td class="no-print">
                                <div class="share-row-actions">
                                    <a class="btn btn-sm btn-secondary" href="{{ route('shared-attachment-links.show', $link) }}">عرض</a>
                                    <button type="button" class="btn btn-sm btn-light" data-copy-text="{{ $link->public_url }}">نسخ الرابط</button>
                                    @if(auth()->user()?->hasPermission('attachment_shares.revoke') && $link->is_active)
                                        <form method="POST" action="{{ route('shared-attachment-links.revoke', $link) }}" data-confirm="هل تريد تعطيل هذا الرابط؟">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-warning" type="submit">تعطيل</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">لا توجد روابط مشاركة حتى الآن.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $links->links() }}</div>
    </div>
</div>
@endsection
