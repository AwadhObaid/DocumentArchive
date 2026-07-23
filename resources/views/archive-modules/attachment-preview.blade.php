@extends('layouts.app')

@section('title', $pageTitle)
@section('page_title', $pageTitle)
@section('page_subtitle', $attachment->original_name ?: $attachment->file_name)

@section('content')
@php
    $extension = strtolower(
        $attachment->extension
            ?: pathinfo(
                $attachment->original_name ?: $attachment->file_name,
                PATHINFO_EXTENSION
            )
    );
    $isImage = in_array(
        $extension,
        ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
        true
    );
    $isPdf = $extension === 'pdf';
@endphp

<div class="archive-preview-shell">
    <div class="archive-module-header">
        <div>
            <h1>{{ $pageTitle }}</h1>
            <p>
                السجل:
                <strong class="archive-module-number">{{ $recordNumber }}</strong>
                — {{ $attachment->original_name ?: $attachment->file_name }}
            </p>
        </div>

        <div class="archive-module-actions">
            <a class="btn btn-light" href="{{ $recordUrl }}">العودة إلى السجل</a>
            <a class="btn btn-primary" href="{{ $downloadUrl }}">تنزيل</a>
        </div>
    </div>

    <div class="card">
        @if($isImage)
            <img class="archive-preview-image" src="{{ $inlineUrl }}" alt="{{ $attachment->original_name }}">
        @elseif($isPdf)
            <iframe class="archive-preview-frame" src="{{ $inlineUrl }}" title="{{ $attachment->original_name }}"></iframe>
        @else
            <div class="empty-state">
                <p>هذا النوع لا يدعم المعاينة داخل المتصفح.</p>
                <a class="btn btn-primary" href="{{ $downloadUrl }}">تنزيل الملف</a>
            </div>
        @endif
    </div>
</div>
@endsection
