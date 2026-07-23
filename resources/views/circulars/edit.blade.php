@extends('layouts.app')
@section('title', 'تعديل تعميم')
@section('page_title', 'تعديل التعميم ' . $circular->circular_number)
@section('page_subtitle', 'تحديث بيانات التعميم وإضافة مرفقات جديدة')
@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>تعديل التعميم</h1>
            <p class="archive-module-number">{{ $circular->circular_number }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('circulars.show', $circular) }}">العودة للعرض</a>
    </div>
    <form method="POST" action="{{ route('circulars.update', $circular) }}" enctype="multipart/form-data" class="card">
        @csrf
        @method('PUT')
        @include('circulars._form')
        <div class="archive-module-actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">حفظ التعديلات</button>
            <a class="btn btn-light" href="{{ route('circulars.show', $circular) }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
