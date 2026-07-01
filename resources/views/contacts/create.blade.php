@extends('layouts.app')

@section('title', 'إضافة جهة اتصال')
@section('page_title', 'إضافة جهة اتصال')
@section('page_subtitle', 'أضف بريدًا أو رقم واتساب لاستخدامه لاحقًا في الإرسال.')

@section('content')
<div class="ct-page">
    <div class="ct-page-header"><div><h1>إضافة جهة اتصال</h1><p>يفضل إدخال بريد أو رقم واتساب واحد على الأقل.</p></div></div>
    <form method="POST" action="{{ route('contacts.store') }}" class="ct-card ct-form-card">
        @csrf
        @include('contacts._form')
        <div class="ct-actions-row">
            <button type="submit" class="btn btn-primary">حفظ جهة الاتصال</button>
            <a href="{{ route('contacts.index') }}" class="btn btn-light">إلغاء</a>
        </div>
    </form>
</div>
@endsection
