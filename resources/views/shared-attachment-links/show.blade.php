@extends('layouts.app')

@section('title', 'تفاصيل رابط المشاركة')
@section('page_title', 'تفاصيل رابط المشاركة')
@section('page_subtitle', 'عرض الرابط الآمن ومرفقاته وإحصائيات استخدامه.')

@section('content')
<div class="share-page">
    <div class="share-page-header">
        <div>
            <h1>تفاصيل رابط المشاركة</h1>
            <p>رابط مخصص لمرفقات {{ $link->memo ? 'المذكرة رقم ' . $link->memo->memo_number : 'الكتاب رقم ' . ($link->document?->reference_number ?: '-') }}.</p>
        </div>
        <div class="share-actions">
            <a href="{{ route('shared-attachment-links.index') }}" class="btn btn-light">سجل الروابط</a>
            @if($link->document)
                <a href="{{ route('documents.show', $link->document) }}" class="btn btn-secondary">عرض الكتاب</a>
            @endif
            @if($link->memo)
                <a href="{{ route('memos.show', $link->memo) }}" class="btn btn-secondary">عرض المذكرة</a>
            @endif
        </div>
    </div>

    <div class="share-detail-grid">
        <div class="share-card">
            <h2>الرابط العام</h2>
            <div class="share-url-box">
                <input type="text" readonly value="{{ $link->public_url }}" id="sharePublicUrl">
                <button type="button" class="btn btn-primary" data-copy-target="#sharePublicUrl">نسخ</button>
                <a class="btn btn-light" target="_blank" href="{{ $link->public_url }}">فتح</a>
            </div>
            <div class="share-info-list">
                <div><span>الحالة</span><strong><span class="share-badge {{ $link->status_class }}">{{ $link->status_name }}</span></strong></div>
                <div><span>انتهاء الصلاحية</span><strong>{{ $link->expires_at ? $link->expires_at->format('Y-m-d H:i') : 'غير محدد' }}</strong></div>
                <div><span>محمي بكلمة مرور</span><strong>{{ $link->requires_password ? 'نعم' : 'لا' }}</strong></div>
                <div><span>حد التحميل</span><strong>{{ $link->max_downloads ?: 'غير محدد' }}</strong></div>
                <div><span>المشاهدات</span><strong>{{ $link->view_count }}</strong></div>
                <div><span>التحميلات</span><strong>{{ $link->download_count }}</strong></div>
            </div>

            @if(auth()->user()?->hasPermission('attachment_shares.revoke'))
                <div class="share-actions-row">
                    @if($link->is_active)
                        <form method="POST" action="{{ route('shared-attachment-links.revoke', $link) }}" data-confirm="هل تريد تعطيل هذا الرابط؟">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-warning" type="submit">تعطيل الرابط</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('shared-attachment-links.destroy', $link) }}" data-confirm="هل تريد حذف هذا الرابط من السجل؟">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit">حذف الرابط</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="share-card">
            <h2>{{ $link->memo ? 'بيانات المذكرة' : 'بيانات الكتاب' }}</h2>
            <div class="share-info-list">
                <div><span>{{ $link->memo ? 'رقم المذكرة' : 'رقم الكتاب' }}</span><strong>{{ $link->document?->reference_number ?: ($link->memo?->memo_number ?: '-') }}</strong></div>
                <div><span>التاريخ</span><strong>{{ $link->memo ? optional($link->memo?->memo_date)->format('d/m/Y') : (optional($link->document?->reference_date)->format('d/m/Y') ?: '-') }}</strong></div>
                <div><span>الموضوع</span><strong>{{ $link->document?->subject ?: $link->document?->title ?: $link->memo?->subject ?: '-' }}</strong></div>
                <div><span>الإدارة</span><strong>{{ $link->document?->department?->name ?: $link->memo?->department?->name ?: '-' }}</strong></div>
                <div><span>أنشأه</span><strong>{{ $link->creator?->name ?: '-' }}</strong></div>
                <div><span>تاريخ الإنشاء</span><strong>{{ $link->created_at?->format('Y-m-d H:i') }}</strong></div>
            </div>
        </div>
    </div>

    <div class="share-card">
        <h2>المرفقات المشاركة</h2>
        <div class="table-responsive">
            <table class="table share-table">
                <thead>
                    <tr>
                        <th>اسم الملف</th>
                        <th>الحجم</th>
                        <th>مرات التحميل</th>
                        <th>آخر تحميل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($link->items as $item)
                        @php $attachment = $item->attachment ?: $item->memoAttachment; @endphp
                        <tr>
                            <td>{{ $attachment?->original_name ?: $attachment?->file_name ?: 'مرفق' }}</td>
                            <td>{{ $attachment?->file_size_for_humans ?: '-' }}</td>
                            <td>{{ $item->download_count }}</td>
                            <td>{{ $item->last_downloaded_at ? $item->last_downloaded_at->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
