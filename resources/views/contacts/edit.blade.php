@extends('layouts.app')

@section('title', 'تعديل جهة اتصال')
@section('page_title', 'تعديل جهة اتصال')
@section('page_subtitle', 'تحديث بيانات الجهة المستخدمة في البريد وواتساب.')

@section('content')
<div class="ct-page">
    <div class="ct-page-header"><div><h1>تعديل جهة اتصال</h1><p>{{ $contact->display_name }}</p></div></div>
    <form method="POST" action="{{ route('contacts.update', $contact) }}" class="ct-card ct-form-card">
        @csrf
        @method('PUT')
        @include('contacts._form')
        <div class="ct-actions-row">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="{{ route('contacts.index') }}" class="btn btn-light">إلغاء</a>
        </div>
    </form>
</div>
@endsection
