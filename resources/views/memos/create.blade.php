@extends('layouts.app')

@section('title', 'إضافة مذكرة')
@section('page_title', 'إضافة مذكرة')
@section('page_subtitle', 'حفظ وأرشفة مذكرة واردة برقم مستقل يبدأ من 2600001')

@section('content')
<style>
    .memo-number-card { border:1px solid rgba(59,130,246,.28); background:linear-gradient(135deg, rgba(37,99,235,.13), rgba(15,23,42,.10)); }
    .memo-number-box { direction:ltr; display:inline-flex; align-items:center; justify-content:center; min-width:180px; padding:12px 18px; border-radius:16px; background:rgba(15,23,42,.75); color:#f8fafc; border:1px solid rgba(148,163,184,.28); font-size:28px; font-weight:950; letter-spacing:.6px; }
</style>

<div class="page-title">
    <h1>إضافة مذكرة</h1>
    <a href="{{ route('memos.index') }}" class="btn btn-secondary">رجوع</a>
</div>

<div class="card memo-number-card">
    <div style="display:flex; gap:18px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
        <div>
            <div style="font-size:13px; color:#94a3b8; font-weight:850; margin-bottom:6px;">رقم المذكرة المتوقع</div>
            <strong class="memo-number-box">{{ $nextMemo['memo_number'] ?? '2600001' }}</strong>
        </div>
        <div style="line-height:1.9; color:#cbd5e1; font-weight:750;">
            هذا الرقم مبدئي للعرض فقط، ويتم حجز الرقم النهائي عند الحفظ.<br>
            بداية ترقيم المذكرات: <strong style="direction:ltr;display:inline-block;">{{ $nextMemo['start_number'] ?? 2600001 }}</strong>
        </div>
    </div>
</div>

<div class="card">
    <form method="POST" action="{{ route('memos.store') }}" enctype="multipart/form-data">
        @csrf
        @include('memos._form')
        <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-success">حفظ وأرشفة المذكرة</button>
            <a href="{{ route('memos.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection
