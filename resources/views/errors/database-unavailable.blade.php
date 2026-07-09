<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>تعذر الاتصال بقاعدة البيانات</title>
    <link rel="stylesheet" href="{{ asset('css/friendly-database-error-v48.css') }}?v={{ file_exists(public_path('css/friendly-database-error-v48.css')) ? filemtime(public_path('css/friendly-database-error-v48.css')) : time() }}">
</head>
<body class="da-db-error-page">
    <main class="da-db-error-shell" role="main">
        <section class="da-db-error-card" aria-labelledby="dbErrorTitle">
            <div class="da-db-error-icon">🗄️</div>

            <div class="da-db-error-content">
                <p class="da-db-error-kicker">تنبيه اتصال النظام</p>
                <h1 id="dbErrorTitle">تعذر الاتصال بقاعدة البيانات</h1>
                <p class="da-db-error-message">
                    لا يمكن عرض الصفحة حاليًا لأن خدمة قاعدة البيانات غير متاحة. شغّل MySQL من Laragon ثم حدّث الصفحة.
                </p>

                <div class="da-db-error-details" aria-label="تفاصيل الاتصال">
                    <div>
                        <span>قاعدة البيانات</span>
                        <strong>{{ $database ?: 'غير محددة' }}</strong>
                    </div>
                    <div>
                        <span>الخادم</span>
                        <strong>{{ $host }}:{{ $port }}</strong>
                    </div>
                    <div>
                        <span>نوع الاتصال</span>
                        <strong>{{ $connection }}</strong>
                    </div>
                </div>

                <div class="da-db-error-steps">
                    <h2>الإجراء المطلوب</h2>
                    <ol>
                        <li>افتح Laragon واضغط <strong>Start All</strong>.</li>
                        <li>تأكد أن خدمة <strong>MySQL</strong> تعمل.</li>
                        <li>اضغط زر تحديث الصفحة بالأسفل.</li>
                    </ol>
                </div>

                <div class="da-db-error-actions">
                    <button type="button" onclick="window.location.reload()">تحديث الصفحة</button>
                    <a href="{{ url('/login') }}">العودة لتسجيل الدخول</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
