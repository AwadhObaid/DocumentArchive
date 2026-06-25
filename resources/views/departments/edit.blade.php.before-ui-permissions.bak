@extends('layouts.app')

@section('title', 'تعديل إدارة')

@section('content')
    <div class="page-title">
        <h1>تعديل إدارة</h1>
        <a href="{{ route('departments.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('departments.update', $department) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>اسم الإدارة</label>
                    <input type="text" name="name" value="{{ old('name', $department->name) }}" required>
                </div>
                <div class="form-group">
                    <label>الكود</label>
                    <input type="text" name="code" value="{{ old('code', $department->code) }}">
                </div>
                <div class="form-group full">
                    <label>الوصف</label>
                    <textarea name="description">{{ old('description', $department->description) }}</textarea>
                </div>
                <div class="form-group full">
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department->is_active))>
                        نشطة
                    </label>
                </div>
            </div>
            <div style="margin-top:20px;"><button type="submit" class="btn btn-success">حفظ التعديلات</button></div>
        </form>
    </div>
@endsection
