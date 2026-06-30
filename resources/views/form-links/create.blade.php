@extends('layouts.app')

@section('title', 'إضافة نموذج')
@section('page_title', 'إضافة نموذج')
@section('page_subtitle', 'إضافة رابط نموذج داخلي أو خارجي إلى نظام الأرشفة')

@section('content')
    <div class="page-title">
        <h2>إضافة نموذج</h2>
        <a href="{{ route('form-links.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('form-links.store') }}">
            @csrf
            @include('form-links._form')

            <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-success">حفظ النموذج</button>
                <a href="{{ route('form-links.index') }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
