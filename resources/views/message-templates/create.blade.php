@extends('layouts.app')

@section('title', 'إضافة قالب رسالة')
@section('page_title', 'إضافة قالب رسالة')
@section('page_subtitle', 'أنشئ نصًا جاهزًا للبريد أو واتساب باستخدام متغيرات الكتاب.')

@section('content')
<div class="ct-page">
    <div class="ct-page-header"><div><h1>إضافة قالب رسالة</h1><p>القالب يساعد المستخدم على تجهيز الرسالة بسرعة وبصيغة موحدة.</p></div></div>
    <form method="POST" action="{{ route('message-templates.store') }}" class="ct-card ct-form-card">
        @csrf
        @include('message-templates._form')
        <div class="ct-actions-row">
            <button type="submit" class="btn btn-primary">حفظ القالب</button>
            <a href="{{ route('message-templates.index') }}" class="btn btn-light">إلغاء</a>
        </div>
    </form>
</div>
@endsection
