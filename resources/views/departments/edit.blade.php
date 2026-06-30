@extends('layouts.app')

@section('title', 'تعديل إدارة')
@section('page_title', 'تعديل إدارة')
@section('page_subtitle', 'تعديل بيانات الإدارة مع الحفاظ على الكتب المرتبطة بها')

@section('content')
    {{-- DEFINITIONS_POLISH_FORM_START --}}
    <style>
        .definition-form-page { display:grid; gap:18px; }
        .definition-help { border:1px solid rgba(245,158,11,.22); background:rgba(245,158,11,.10); border-radius:16px; padding:14px 16px; }
        .definition-form-actions { margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
    </style>

    <div class="definition-form-page">
        <div class="page-title">
            <h1>تعديل إدارة</h1>
            <a href="{{ route('departments.index') }}" class="btn btn-secondary">رجوع</a>
        </div>

        <div class="definition-help">
            هذه الإدارة مرتبطة بعدد {{ number_format($department->all_documents_count ?? $department->documents_count ?? 0) }} من الكتب. عند وجود كتب مرتبطة بها، سيتم تعطيلها بدلاً من حذفها.
        </div>

        <div class="card">
            <form method="POST" action="{{ route('departments.update', $department) }}">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم الإدارة</label>
                        <input type="text" name="name" value="{{ old('name', $department->name) }}" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>الكود</label>
                        <input type="text" name="code" value="{{ old('code', $department->code) }}" placeholder="مثال: SHIPPING_INSURANCE">
                    </div>
                    <div class="form-group full">
                        <label>الوصف</label>
                        <textarea name="description" rows="4">{{ old('description', $department->description) }}</textarea>
                    </div>
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department->is_active))>
                            إدارة نشطة وتظهر في القوائم
                        </label>
                    </div>
                </div>
                <div class="definition-form-actions">
                    <button type="submit" class="btn btn-success">حفظ التعديلات</button>
                    <a href="{{ route('documents.index', ['department_id' => $department->id]) }}" class="btn btn-secondary">عرض الكتب المرتبطة</a>
                    <a href="{{ route('departments.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
    {{-- DEFINITIONS_POLISH_FORM_END --}}
@endsection