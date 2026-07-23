@extends('layouts.app')
@section('title', 'إضافة كتاب متفرق')
@section('page_title', 'إضافة كتاب متفرق')
@section('page_subtitle', 'الرقم المتوقع: ' . ($nextNumber['misc_number'] ?? '2620000'))
@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>إضافة كتاب متفرق</h1>
            <p>للموظفين والهيئات والمخاطبات العسكرية والمدنية.</p>
        </div>
        <a class="btn btn-light" href="{{ route('misc-books.index') }}">العودة</a>
    </div>
    <form method="POST" action="{{ route('misc-books.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="archive-module-form-note">الرقم المتوقع حاليًا: <strong class="archive-module-number">{{ $nextNumber['misc_number'] ?? '2620000' }}</strong></div>
        @include('misc-books._form')
        <div class="archive-module-actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">حفظ الكتاب</button>
            <a class="btn btn-light" href="{{ route('misc-books.index') }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
