@extends('layouts.app')

@section('title', 'تعديل موضوع كتاب')
@section('page_title', 'تعديل موضوع كتاب')
@section('page_subtitle', 'تعديل بيانات موضوع الكتاب وحالة ظهوره في القوائم')

@section('content')
<div class="page-title">
    <h1>تعديل موضوع كتاب</h1>
    <a href="{{ route('book-subjects.index') }}" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('book-subjects.update', $bookSubject) }}">
        @csrf
        @method('PUT')
        @include('book-subjects._form', ['bookSubject' => $bookSubject])
        <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-success">حفظ التعديلات</button>
            <a href="{{ route('book-subjects.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection
