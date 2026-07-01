@extends('layouts.app')

@section('title', 'قوالب الرسائل')
@section('page_title', 'قوالب الرسائل')
@section('page_subtitle', 'إدارة نصوص جاهزة للبريد الإلكتروني وواتساب مع متغيرات بيانات الكتب.')

@section('content')
<div class="ct-page">
    <div class="ct-page-header">
        <div><h1>قوالب الرسائل</h1><p>استخدم المتغيرات مثل <code>{document_number}</code> و <code>{subject}</code> لتجهيز الرسائل تلقائيًا.</p></div>
        <div class="ct-actions">
            @if(auth()->user()?->hasPermission('message_templates.manage'))
                <a href="{{ route('message-templates.create') }}" class="btn btn-primary">إضافة قالب</a>
            @endif
        </div>
    </div>

    <div class="ct-summary-grid">
        <div class="ct-stat-card"><div><span>إجمالي القوالب</span><strong>{{ $summary['total'] }}</strong></div><b>💬</b></div>
        <div class="ct-stat-card"><div><span>قوالب نشطة</span><strong>{{ $summary['active'] }}</strong></div><b>✅</b></div>
        <div class="ct-stat-card"><div><span>للبريد</span><strong>{{ $summary['email'] }}</strong></div><b>📧</b></div>
        <div class="ct-stat-card"><div><span>لواتساب</span><strong>{{ $summary['whatsapp'] }}</strong></div><b>🟢</b></div>
    </div>

    <form class="ct-filter-card" method="GET" action="{{ route('message-templates.index') }}">
        <div class="ct-filter-grid">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="اسم القالب أو جزء من النص...">
            </div>
            <div class="form-group">
                <label>القناة</label>
                <select name="channel">
                    <option value="">كل القنوات</option>
                    <option value="email" @selected(request('channel') === 'email')>البريد الإلكتروني</option>
                    <option value="whatsapp" @selected(request('channel') === 'whatsapp')>واتساب</option>
                    <option value="both" @selected(request('channel') === 'both')>البريد وواتساب</option>
                </select>
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <select name="status">
                    <option value="">كل الحالات</option>
                    <option value="active" @selected(request('status') === 'active')>نشط</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
                </select>
            </div>
            <div class="ct-filter-actions">
                <button class="btn btn-secondary" type="submit">تصفية</button>
                <a class="btn btn-light" href="{{ route('message-templates.index') }}">إلغاء</a>
            </div>
        </div>
    </form>

    <div class="ct-card">
        <div class="table-responsive">
            <table class="ct-table">
                <thead><tr><th>القالب</th><th>القناة</th><th>افتراضي</th><th>الحالة</th><th>معاينة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td><strong>{{ $template->name }}</strong><small>{{ $template->subject_template ?: 'بدون موضوع' }}</small></td>
                            <td>{{ $template->channel_name }}</td>
                            <td>{{ $template->is_default ? 'نعم' : 'لا' }}</td>
                            <td><span class="ct-badge {{ $template->is_active ? 'active' : 'inactive' }}">{{ $template->is_active ? 'نشط' : 'غير نشط' }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($template->body_template, 90) }}</td>
                            <td>
                                <div class="ct-row-actions">
                                    @if(auth()->user()?->hasPermission('message_templates.manage'))
                                        <a href="{{ route('message-templates.edit', $template) }}" class="btn btn-sm btn-light">تعديل</a>
                                        <form method="POST" action="{{ route('message-templates.destroy', $template) }}" data-confirm="سيتم حذف قالب الرسالة. هل تريد المتابعة؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                        </form>
                                    @else
                                        —
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ct-empty">لا توجد قوالب رسائل مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ct-pagination">{{ $templates->links() }}</div>
    </div>
</div>
@endsection
