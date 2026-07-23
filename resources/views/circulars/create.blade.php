@extends('layouts.app')
@section('title', 'إضافة تعميم')
@section('page_title', 'إضافة تعميم')
@section('page_subtitle', 'الرقم المتوقع: ' . ($nextNumber['circular_number'] ?? '2610000'))
@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>إضافة تعميم</h1>
            <p>يُحجز الرقم الداخلي عند حفظ التعميم.</p>
        </div>
        <a class="btn btn-light" href="{{ route('circulars.index') }}">العودة</a>
    </div>
    <form method="POST" action="{{ route('circulars.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="archive-module-form-note">الرقم المتوقع حاليًا: <strong class="archive-module-number">{{ $nextNumber['circular_number'] ?? '2610000' }}</strong></div>
        @include('circulars._form')
        <div class="archive-module-actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">حفظ التعميم</button>
            <a class="btn btn-light" href="{{ route('circulars.index') }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
