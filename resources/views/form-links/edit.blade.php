@extends('layouts.app')

@section('title', 'تعديل نموذج')
@section('page_title', 'تعديل نموذج')
@section('page_subtitle', 'تحديث بيانات رابط النموذج وطريقة ظهوره داخل النظام')

@section('content')
    <div class="page-title">
        <h2>تعديل نموذج</h2>
        <a href="{{ route('form-links.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('form-links.update', $formLink) }}">
            @csrf
            @method('PUT')
            @include('form-links._form')

            <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-success">حفظ التعديلات</button>
                <a href="{{ route('form-links.index') }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
