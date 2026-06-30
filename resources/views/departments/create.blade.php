@extends('layouts.app')

@section('title', 'إضافة إدارة')
@section('page_title', 'إضافة إدارة')
@section('page_subtitle', 'أضف إدارة جديدة لاستخدامها في تصنيف الكتب')

@section('content')
    {{-- DEFINITIONS_POLISH_FORM_START --}}
    <style>
        .definition-form-page { display:grid; gap:18px; }
        .definition-help { border:1px solid rgba(59,130,246,.18); background:rgba(59,130,246,.08); border-radius:16px; padding:14px 16px; color:var(--text, #0f172a); }
        .definition-form-actions { margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
    </style>

    <div class="definition-form-page">
        <div class="page-title">
            <h1>إضافة إدارة</h1>
            <a href="{{ route('departments.index') }}" class="btn btn-secondary">رجوع</a>
        </div>

        <div class="definition-help">
            اكتب اسم الإدارة كما تريد ظهوره في القوائم والتقارير. الكود اختياري ويفضل أن يكون مختصراً مثل SHIPPING_INSURANCE.
        </div>

        <div class="card">
            <form method="POST" action="{{ route('departments.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم الإدارة</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="مثال: الشحن والتأمين">
                    </div>
                    <div class="form-group">
                        <label>الكود</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="مثال: SHIPPING_INSURANCE">
                    </div>
                    <div class="form-group full">
                        <label>الوصف</label>
                        <textarea name="description" rows="4" placeholder="وصف مختصر لاستخدام الإدارة">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))>
                            إدارة نشطة وتظهر في القوائم
                        </label>
                    </div>
                </div>
                <div class="definition-form-actions">
                    <button type="submit" class="btn btn-success">حفظ الإدارة</button>
                    <a href="{{ route('departments.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
    {{-- DEFINITIONS_POLISH_FORM_END --}}
@endsection