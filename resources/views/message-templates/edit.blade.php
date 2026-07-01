@extends('layouts.app')

@section('title', 'تعديل قالب رسالة')
@section('page_title', 'تعديل قالب رسالة')
@section('page_subtitle', 'تحديث نص القالب ومتغيراته.')

@section('content')
<div class="ct-page">
    <div class="ct-page-header"><div><h1>تعديل قالب رسالة</h1><p>{{ $template->name }}</p></div></div>
    <form method="POST" action="{{ route('message-templates.update', $template) }}" class="ct-card ct-form-card">
        @csrf
        @method('PUT')
        @include('message-templates._form')
        <div class="ct-actions-row">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="{{ route('message-templates.index') }}" class="btn btn-light">إلغاء</a>
        </div>
    </form>
</div>
@endsection
