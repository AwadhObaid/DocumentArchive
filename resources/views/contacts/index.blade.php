@extends('layouts.app')

@section('title', 'جهات الاتصال')
@section('page_title', 'جهات الاتصال')
@section('page_subtitle', 'إدارة عناوين البريد وأرقام واتساب المستخدمة في الإرسال.')

@section('content')
<div class="ct-page">
    <div class="ct-page-header">
        <div>
            <h1>جهات الاتصال</h1>
            <p>احفظ الجهات المتكررة لاستخدامها مباشرة في البريد الإلكتروني وواتساب.</p>
        </div>
        <div class="ct-actions">
            @if(auth()->user()?->hasPermission('contacts.manage'))
                <a href="{{ route('contacts.create') }}" class="btn btn-primary">إضافة جهة اتصال</a>
            @endif
        </div>
    </div>

    <div class="ct-summary-grid">
        <div class="ct-stat-card"><div><span>إجمالي الجهات</span><strong>{{ $summary['total'] }}</strong></div><b>📇</b></div>
        <div class="ct-stat-card"><div><span>جهات نشطة</span><strong>{{ $summary['active'] }}</strong></div><b>✅</b></div>
        <div class="ct-stat-card"><div><span>لديها بريد</span><strong>{{ $summary['email'] }}</strong></div><b>📧</b></div>
        <div class="ct-stat-card"><div><span>لديها واتساب</span><strong>{{ $summary['whatsapp'] }}</strong></div><b>🟢</b></div>
    </div>

    <form class="ct-filter-card" method="GET" action="{{ route('contacts.index') }}">
        <div class="ct-filter-grid">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="اسم، جهة، بريد، رقم...">
            </div>
            <div class="form-group">
                <label>النوع</label>
                <select name="type">
                    <option value="">كل الأنواع</option>
                    @foreach(['internal'=>'داخلية','external'=>'خارجية','government'=>'جهة حكومية','company'=>'شركة','department'=>'إدارة','other'=>'أخرى'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <select name="status">
                    <option value="">كل الحالات</option>
                    <option value="active" @selected(request('status') === 'active')>نشطة</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>غير نشطة</option>
                </select>
            </div>
            <div class="ct-filter-actions">
                <button class="btn btn-secondary" type="submit">تصفية</button>
                <a class="btn btn-light" href="{{ route('contacts.index') }}">إلغاء</a>
            </div>
        </div>
    </form>

    <div class="ct-card">
        <div class="table-responsive">
            <table class="ct-table">
                <thead>
                    <tr>
                        <th>الجهة</th>
                        <th>النوع</th>
                        <th>البريد</th>
                        <th>واتساب</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contacts as $contact)
                        <tr>
                            <td>
                                <strong>{{ $contact->name }}</strong>
                                <small>{{ $contact->organization ?: $contact->contact_person ?: '—' }}</small>
                            </td>
                            <td>{{ $contact->type_name }}</td>
                            <td dir="ltr">{{ $contact->email ?: '—' }}</td>
                            <td dir="ltr">{{ $contact->whatsapp_number ?: '—' }}</td>
                            <td><span class="ct-badge {{ $contact->is_active ? 'active' : 'inactive' }}">{{ $contact->is_active ? 'نشطة' : 'غير نشطة' }}</span></td>
                            <td>
                                <div class="ct-row-actions">
                                    @if(auth()->user()?->hasPermission('contacts.manage'))
                                        <a href="{{ route('contacts.edit', $contact) }}" class="btn btn-sm btn-light">تعديل</a>
                                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" data-confirm="سيتم حذف جهة الاتصال. هل تريد المتابعة؟">
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
                        <tr><td colspan="6" class="ct-empty">لا توجد جهات اتصال مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ct-pagination">{{ $contacts->links() }}</div>
    </div>
</div>
@endsection
