<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>مرفقات مشتركة - {{ $link->document?->reference_number ?: '' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/shared-attachments.css') }}?v={{ filemtime(public_path('css/shared-attachments.css')) }}">
</head>
<body class="share-public-body">
    <main class="share-public-shell">
        <section class="share-public-card">
            <div class="share-public-header">
                <div>
                    <div class="share-public-brand">📁 DocumentArchive</div>
                    <h1>مرفقات الكتاب</h1>
                    <p>يمكنك تحميل المرفقات المتاحة قبل انتهاء صلاحية الرابط.</p>
                </div>
                <span class="share-badge {{ $link->status_class }}">{{ $link->status_name }}</span>
            </div>

            <div class="share-public-meta">
                <div><span>رقم الكتاب</span><strong>{{ $link->document?->reference_number ?: '-' }}</strong></div>
                <div><span>تاريخ الكتاب</span><strong>{{ optional($link->document?->reference_date)->format('d/m/Y') ?: '-' }}</strong></div>
                <div><span>الموضوع</span><strong>{{ $link->document?->subject ?: $link->document?->title ?: '-' }}</strong></div>
                <div><span>تنتهي الصلاحية</span><strong>{{ $link->expires_at ? $link->expires_at->format('Y-m-d H:i') : 'غير محدد' }}</strong></div>
            </div>

            <div class="share-public-files">
                @foreach($link->items as $item)
                    @php
                        $attachment = $item->attachment;
                        $exists = $attachment && method_exists($attachment, 'existsOnDisk') ? $attachment->existsOnDisk() : false;
                    @endphp
                    <div class="share-public-file">
                        <div>
                            <strong>{{ $attachment?->original_name ?: $attachment?->file_name ?: 'مرفق' }}</strong>
                            <small>{{ $attachment?->file_size_for_humans ?: '-' }} — {{ $exists ? 'جاهز للتحميل' : 'غير متاح حاليًا' }}</small>
                        </div>
                        @if($exists)
                            <a class="btn btn-primary" href="{{ route('shared-attachments.public.download', [$link->token, $item]) }}">تحميل</a>
                        @else
                            <span class="share-badge danger">مفقود</span>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="share-public-note">
                هذا الرابط مخصص للمستلم فقط. لا تشارك الرابط مع أطراف غير معنية.
            </div>
        </section>
    </main>
</body>
</html>
