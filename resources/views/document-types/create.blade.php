@extends('layouts.app')

@section('title', 'إضافة نوع م
ستند')

@section('content')
    <div class="page-title">
        <h1>إضافة نوع م
ستند</h1>
        @if(auth()->user()?->hasPermission('document_types.manage'))
        <a href="{{ route('document-types.index') }}" class="btn btn-secondary">رجوع</a>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('document-types.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>اسم
 النوع</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label>الكود</label>
                    <input type="text" name="code" value="{{ old('code') }}" placeholder="م
ثال: OUTGOING">
                </div>
                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description') }}</textarea>
                </div>
                <div class="form-group full">
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        نشط
                    </label>
                </div>
            </div>
            <div style="margin-top:20px;"><button type="submit" class="btn btn-success">حفظ</button></div>
        </form>
    </div>
@endsection
