<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>الرابط غير متاح</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/shared-attachments.css') }}?v={{ filemtime(public_path('css/shared-attachments.css')) }}">
</head>
<body class="share-public-body">
    <main class="share-public-shell">
        <section class="share-public-card share-password-card">
            <div class="share-public-brand">📁 DocumentArchive</div>
            <h1>الرابط غير متاح</h1>
            <p>
                قد يكون الرابط منتهي الصلاحية، أو تم تعطيله، أو وصل إلى حد التحميلات المسموح.
            </p>
            @if($link)
                <div class="share-public-meta one">
                    <div><span>الحالة</span><strong>{{ $link->status_name }}</strong></div>
                    <div><span>تنتهي الصلاحية</span><strong>{{ $link->expires_at ? $link->expires_at->format('Y-m-d H:i') : 'غير محدد' }}</strong></div>
                </div>
            @endif
        </section>
    </main>
</body>
</html>
