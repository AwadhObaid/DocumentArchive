@extends('layouts.app')

@section('title', 'تعديل نوع كتاب')
@section('page_title', 'تعديل نوع كتاب')
@section('page_subtitle', 'تعديل بيانات نوع الكتاب مع الحفاظ على الكتب المرتبطة به')

@section('content')
    {{-- DEFINITIONS_POLISH_FORM_START --}}
    <style>
        .definition-form-page { display:grid; gap:18px; }
        .definition-help { border:1px solid rgba(245,158,11,.22); background:rgba(245,158,11,.10); border-radius:16px; padding:14px 16px; }
        .definition-form-actions { margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
    </style>

    <div class="definition-form-page">
        <div class="page-title">
            <h1>تعديل نوع كتاب</h1>
            <a href="{{ route('document-types.index') }}" class="btn btn-secondary">رجوع</a>
        </div>

        <div class="definition-help">
            هذا النوع مرتبط بعدد {{ number_format($documentType->all_documents_count ?? $documentType->documents_count ?? 0) }} من الكتب. عند وجود كتب مرتبطة به، سيتم تعطيله بدلاً من حذفه.
        </div>

        <div class="card">
            <form method="POST" action="{{ route('document-types.update', $documentType) }}">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم النوع</label>
                        <input type="text" name="name" value="{{ old('name', $documentType->name) }}" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>الكود</label>
                        <input type="text" name="code" value="{{ old('code', $documentType->code) }}" placeholder="مثال: OUTGOING">
                    </div>
                    <div class="form-group full">
                        <label>الوصف</label>
                        <textarea name="description" rows="4">{{ old('description', $documentType->description) }}</textarea>
                    </div>
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $documentType->is_active))>
                            نوع نشط ويظهر في القوائم
                        </label>
                    </div>
                </div>
                <div class="definition-form-actions">
                    <button type="submit" class="btn btn-success">حفظ التعديلات</button>
                    <a href="{{ route('documents.index', ['document_type_id' => $documentType->id]) }}" class="btn btn-secondary">عرض الكتب المرتبطة</a>
                    <a href="{{ route('document-types.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
    {{-- DEFINITIONS_POLISH_FORM_END --}}
@endsection