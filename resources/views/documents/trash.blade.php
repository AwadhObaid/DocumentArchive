@extends('layouts.app')

@section('title', 'سلة المحذوفات')

@section('content')
    <div class="page-title">
        <h1>سلة المحذوفات</h1>

        @if(auth()->user()?->hasPermission('documents.view'))
        <a href="{{ route('documents.index') }}" class="btn btn-secondary">رجوع للكتب</a>
        @endif
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>رقم الكتاب</th>
                <th>تاريخ الكتاب</th>
                <th>موضوع الكتاب</th>
                <th>البوليصة الرئيسية</th>
                <th>تاريخ الحذف</th>
                <th>إجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($documents as $document)
                <tr>
                    <td><strong>{{ $document->reference_number }}</strong></td>
                    <td>{{ $document->formatted_date }}</td>
                    <td>{{ $document->subject ?: $document->title }}</td>
                    <td>{{ $document->main_policy_number ?? '-' }}</td>
                    <td>{{ $document->deleted_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <div class="actions">
                            @if(auth()->user()?->hasPermission('documents.restore'))
                            <form method="POST" action="{{ route('documents.restore', $document->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-success">استعادة</button>
                            </form>
                            @endif

                            @if(auth()->user()?->hasPermission('documents.force_delete'))
                            <form method="POST" action="{{ route('documents.force-delete', $document->id) }}" data-confirm="تحذير: سيتمحذف الكتاب ومرفقاته نهائياً. هل أنت متأكد؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">حذف نهائي</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">سلة المحذوفات فارغة.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $documents->links() }}
        </div>
    </div>
@endsection


