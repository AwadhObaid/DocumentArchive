@extends('layouts.app')

@section('title', 'أنواع المستندات')

@section('content')
    <div class="page-title">
        <h1>أنواع المستندات</h1>
        <a href="{{ route('document-types.create') }}" class="btn btn-primary">+ إضافة نوع</a>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>الاسم</th>
                <th>الكود</th>
                <th>الوصف</th>
                <th>الحالة</th>
                <th>عدد المستندات</th>
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
                            <span class="badge">معطل</span>
                        @endif
                    </td>
                    <td>{{ $type->documents_count }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('document-types.edit', $type) }}" class="btn btn-primary">تعديل</a>
                            <form method="POST" action="{{ route('document-types.destroy', $type) }}" data-confirm="هل أنت متأكد من حذف أو تعطيل هذا النوع؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">لا توجد أنواع مستندات حتى الآن.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="pagination">{{ $documentTypes->links() }}</div>
    </div>
@endsection
