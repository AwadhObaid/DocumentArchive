@extends('layouts.app')

@section('title', 'الكتب')
@section('page_title', 'الكتب')
@section('page_subtitle', 'البحث في الكتب، البوالص، المرفقات، وطباعة رقم الكتاب')

@section('content')
    <div class="page-title">
        <h1>الكتب</h1>

        @if(auth()->user()?->hasPermission('documents.create'))
        <a href="{{ route('documents.create') }}" class="btn btn-primary">
            + إضافة كتاب جديد
        </a>
        @endif
    </div>

    <div class="card">
        @if(auth()->user()?->hasPermission('documents.view'))
        <form method="GET" action="{{ route('documents.index') }}">
            <div class="form-grid">
                <div class="form-group">
                    <label>بحث عام</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="رقم الكتاب / البوليصة / موضوع الكتاب / المرسل / المستلم">
                </div>

                <div class="form-group">
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">كل الإدارات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>نوع الكتاب</label>
                    <select name="document_type_id">
                        <option value="">كل الأنواع</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('document_type_id') == $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </div>

                <div class="form-group">
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </div>

                <div class="form-group" style="justify-content: flex-end;">
                    <label>&nbsp;</label>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">بحث</button>
                        <a href="{{ route('documents.index') }}" class="btn btn-secondary">إلغاء الفلترة</a>
                    </div>
                </div>
            </div>
        </form>
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
                <th>البوليصة الفرعية</th>
                <th>الإدارة</th>
                <th>الحالة</th>
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
                    <td>{{ $document->sub_policy_number ?? '-' }}</td>
                    <td>{{ $document->department?->name ?? '-' }}</td>
                    <td><span class="badge">{{ $document->status_name }}</span></td>
                    <td>
                        <div class="actions">
                            @if(auth()->user()?->hasPermission('documents.view'))
                            <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                            @endif
                            @if(auth()->user()?->hasPermission('documents.update'))
                            <a class="btn btn-primary" href="{{ route('documents.edit', $document) }}">تعديل</a>
                            @endif
                            @if(auth()->user()?->hasPermission('documents.print_reference'))
                            <a class="btn btn-warning" target="_blank" href="{{ route('documents.print-reference', $document) }}">طباعة رقم الكتاب</a>
                            @endif

                            @if($document->mainAttachment)
                                @if(auth()->user()?->hasPermission('attachments.preview'))
                                <a href="{{ route('attachments.preview', $document->mainAttachment) }}" class="btn btn-info">استعراض</a>
                                @endif
                                @if(auth()->user()?->hasPermission('attachments.download'))
                                <a href="{{ route('attachments.download', $document->mainAttachment) }}" class="btn btn-secondary">تنزيل</a>
                                @endif
                            @endif

                            @if(auth()->user()?->hasPermission('documents.delete'))
                            <form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm="هل أنت متأكد من حذف هذا الكتاب؟ سيتم نقله إلى سلة المحذوفات.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">حذف</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">لا توجد كتب حتى الآن.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $documents->links() }}
        </div>
    </div>
@endsection
