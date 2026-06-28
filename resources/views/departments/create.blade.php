@extends('layouts.app')

@section('title', 'إضافة إدارة')

@section('content')
    <div class="page-title">
        <h1>إضافة إدارة</h1>
        @if(auth()->user()?->hasPermission('departments.manage'))
        <a href="{{ route('departments.index') }}" class="btn btn-secondary">رجوع</a>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('departments.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>اسمالإدارة</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label>الكود</label>
                    <input type="text" name="code" value="{{ old('code') }}" placeholder="مثال: SHIPPING_INSURANCE">
                </div>
                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description') }}</textarea>
                </div>
                <div class="form-group full">
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        نشطة
                    </label>
                </div>
            </div>
            <div style="margin-top:20px;"><button type="submit" class="btn btn-success">حفظ</button></div>
        </form>
    </div>
@endsection
