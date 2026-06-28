@extends('layouts.app')

@section('title', 'تعديل نوع مستند')

@section('content')
    <div class="page-title">
        <h1>تعديل نوع مستند</h1>
        @if(auth()->user()?->hasPermission('document_types.manage'))
        <a href="{{ route('document-types.index') }}" class="btn btn-secondary">رجوع</a>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('document-types.update', $documentType) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>اسمالنوع</label>
                    <input type="text" name="name" value="{{ old('name', $documentType->name) }}" required>
                </div>
                <div class="form-group">
                    <label>الكود</label>
                    <input type="text" name="code" value="{{ old('code', $documentType->code) }}">
                </div>
                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description', $documentType->description) }}</textarea>
                </div>
                <div class="form-group full">
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $documentType->is_active))>
                        نشط
                    </label>
                </div>
            </div>
            <div style="margin-top:20px;"><button type="submit" class="btn btn-success">حفظ التعديلات</button></div>
        </form>
    </div>
@endsection
