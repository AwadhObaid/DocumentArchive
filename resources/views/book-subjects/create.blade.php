@extends('layouts.app')

@section('title', 'إضافة موضوع كتاب')
@section('page_title', 'إضافة موضوع كتاب')
@section('page_subtitle', 'أضف موضوعاً جديداً لاستخدامه في صفحة إضافة الكتاب')

@section('content')
<div class="page-title">
    <h1>إضافة موضوع كتاب</h1>
    <a href="{{ route('book-subjects.index') }}" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('book-subjects.store') }}">
        @csrf
        @include('book-subjects._form')
        <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-success">حفظ الموضوع</button>
            <a href="{{ route('book-subjects.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection
