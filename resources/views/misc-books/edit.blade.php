@extends('layouts.app')
@section('title', 'تعديل كتاب متفرق')
@section('page_title', 'تعديل الكتاب ' . $miscBook->misc_number)
@section('page_subtitle', 'تحديث البيانات وإضافة مرفقات جديدة')
@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>تعديل الكتاب المتفرق</h1>
            <p class="archive-module-number">{{ $miscBook->misc_number }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('misc-books.show', $miscBook) }}">العودة للعرض</a>
    </div>
    <form method="POST" action="{{ route('misc-books.update', $miscBook) }}" enctype="multipart/form-data" class="card">
        @csrf
        @method('PUT')
        @include('misc-books._form')
        <div class="archive-module-actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">حفظ التعديلات</button>
            <a class="btn btn-light" href="{{ route('misc-books.show', $miscBook) }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
