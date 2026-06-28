@extends('layouts.app')

@section('title', 'أنواع الم
ستندات')

@section('content')
    <div class="page-title">
        <h1>أنواع الم
ستندات</h1>
        @if(auth()->user()?->hasPermission('document_types.manage'))
        <a href="{{ route('document-types.create') }}" class="btn btn-primary">+ إضافة نوع</a>
        @endif
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>الاسم
</th>
                <th>الكود</th>
                <th>الوصف</th>
                <th>الحالة</th>
                <th>عدد الم
ستندات</th>
                <th>إجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($documentTypes as $type)
                <tr>
                    <td><strong>{{ $type->name }}</strong></td>
                    <td>{{ $type->code ?? '-' }}</td>
                    <td>{{ $type->description ?? '-' }}</td>
                    <td>
                        @if($type->is_active)
                            <span class="badge">نشط</span>
                        @else
                            <span class="badge">م
عطل</span>
                        @endif
                    </td>
                    <td>{{ $type->documents_count }}</td>
                    <td>
                        <div class="actions">
                            @if(auth()->user()?->hasPermission('document_types.manage'))
                            <a href="{{ route('document-types.edit', $type) }}" class="btn btn-primary">تعديل</a>
                            @endif
                            <form method="POST" action="{{ route('document-types.destroy', $type) }}" data-confirm="هل أنت م
تأكد م
ن حذف أو تعطيل هذا النوع؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">لا توجد أنواع م
ستندات حتى الآن.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="pagination">{{ $documentTypes->links() }}</div>
    </div>
@endsection
