<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تنبيه تسجيل الخروج - نظام أرشفة المستندات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Cairo, Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, .24), transparent 32%),
                radial-gradient(circle at bottom left, rgba(20, 184, 166, .18), transparent 28%),
                #0f172a;
            color: #e5e7eb;
        }
        .notice-shell { width: min(100%, 560px); }
        .notice-card {
            width: 100%;
            background: rgba(15, 23, 42, .94);
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 28px 90px rgba(0, 0, 0, .42);
            backdrop-filter: blur(16px);
            text-align: center;
        }
        .notice-icon {
            width: 78px;
            height: 78px;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            border-radius: 24px;
            background: linear-gradient(145deg, #f59e0b, #ef4444);
            box-shadow: 0 18px 42px rgba(239, 68, 68, .28);
            font-size: 36px;
        }
        h1 { margin: 0 0 12px; font-size: 24px; color: #fff; }
        p { margin: 0; color: #cbd5e1; line-height: 1.95; font-size: 15px; }
        .notice-alert {
            margin: 22px 0;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(251, 191, 36, .35);
            background: rgba(120, 53, 15, .28);
            color: #fde68a;
            line-height: 1.9;
            text-align: right;
        }
        .notice-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .notice-btn {
            appearance: none;
            border: 0;
            border-radius: 16px;
            padding: 12px 20px;
            color: #fff;
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            font-weight: 900;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            box-shadow: 0 14px 30px rgba(14, 165, 233, .22);
        }
        .notice-btn.secondary {
            background: rgba(30, 41, 59, .92);
            border: 1px solid rgba(148, 163, 184, .28);
            box-shadow: none;
        }
        @media (max-width: 560px) {
            body { padding: 14px; }
            .notice-card { padding: 24px; border-radius: 24px; }
            h1 { font-size: 20px; }
            .notice-actions { flex-direction: column; }
            .notice-btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="notice-shell">
    <div class="notice-card">
        <div class="notice-icon">🔒</div>
        <h1>لا يمكن تسجيل الخروج من شريط العنوان</h1>
        <p>
            لحماية جلسة المستخدم، لا يسمح النظام بتنفيذ تسجيل الخروج عند فتح الرابط مباشرة من المتصفح.
            يجب استخدام زر تسجيل الخروج داخل النظام حتى يتم إرسال الطلب بطريقة آمنة.
        </p>

        <div class="notice-alert">
            الرابط <strong>/logout</strong> مخصص لطلبات آمنة من نوع <strong>POST</strong> وليس للفتح المباشر.
        </div>

        <div class="notice-actions">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="notice-btn">تسجيل الخروج الآن</button>
                </form>
                <a class="notice-btn secondary" href="{{ route('dashboard') }}">العودة للنظام</a>
            @else
                <a class="notice-btn" href="{{ route('login') }}">الذهاب لتسجيل الدخول</a>
            @endauth
        </div>
    </div>
</div>
</body>
</html>