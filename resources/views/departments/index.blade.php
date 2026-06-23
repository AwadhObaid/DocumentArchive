@extends('layouts.app')

@section('title', 'الإدارات')

@section('content')
    <div class="page-title">
        <h1>الإدارات</h1>
        <a href="{{ route('departments.create') }}" class="btn btn-primary">+ إضافة إدارة</a>
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
            @forelse($departments as $department)
                <tr>
                    <td><strong>{{ $department->name }}</strong></td>
                    <td>{{ $department->code ?? '-' }}</td>
                    <td>{{ $department->description ?? '-' }}</td>
                    <td>
                        @if($department->is_active)
                            <span class="badge">نشطة</span>
                        @else
                            <span class="badge">معطلة</span>
                        @endif
                    </td>
                    <td>{{ $department->documents_count }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('departments.edit', $department) }}" class="btn btn-primary">تعديل</a>
                            <form method="POST" action="{{ route('departments.destroy', $department) }}" data-confirm="هل أنت متأكد من حذف أو تعطيل هذه الإدارة؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">لا توجد إدارات حتى الآن.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="pagination">{{ $departments->links() }}</div>
    </div>
@endsection
