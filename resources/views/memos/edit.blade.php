@extends('layouts.app')

@section('title', 'تعديل مذكرة')
@section('page_title', 'تعديل مذكرة')
@section('page_subtitle', 'تعديل بيانات المذكرة وإضافة مرفقات جديدة')

@section('content')
<div class="page-title">
    <h1>تعديل مذكرة رقم {{ $memo->memo_number }}</h1>
    <a href="{{ route('memos.show', $memo) }}" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('memos.update', $memo) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('memos._form', ['memo' => $memo])
        <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-success">حفظ التعديلات</button>
            <a href="{{ route('memos.show', $memo) }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection
