<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - غير مصرح</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f6fb;
            --card: #ffffff;
            --primary: #0f172a;
            --muted: #64748b;
            --danger: #dc2626;
            --danger-bg: #fee2e2;
            --border: #e2e8f0;
            --blue: #2563eb;
            --blue-soft: #dbeafe;
            --shadow: 0 24px 70px rgba(15, 23, 42, .12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Cairo", Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, .16), transparent 32%),
                radial-gradient(circle at bottom left, rgba(220, 38, 38, .10), transparent 30%),
                var(--bg);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
        }

        .error-card {
            width: min(760px, 100%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .top-bar {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #ffffff;
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .12);
            display: grid;
            place-items: center;
            font-size: 24px;
        }

        .brand-title {
            font-size: 17px;
            font-weight: 800;
            line-height: 1.35;
        }

        .brand-subtitle {
            margin-top: 2px;
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 500;
        }

        .code-badge {
            min-width: 76px;
            height: 46px;
            border-radius: 999px;
            background: rgba(220, 38, 38, .18);
            border: 1px solid rgba(248, 113, 113, .45);
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 800;
            color: #fecaca;
            direction: ltr;
        }

        .content {
            padding: 36px 34px 32px;
            text-align: center;
        }

        .lock-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 18px;
            border-radius: 28px;
            background: var(--danger-bg);
            color: var(--danger);
            display: grid;
            place-items: center;
            font-size: 38px;
        }

        h1 {
            margin: 0;
            font-size: clamp(24px, 4vw, 34px);
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .message {
            max-width: 570px;
            margin: 14px auto 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.9;
        }

        .notice {
            max-width: 610px;
            margin: 24px auto 0;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #991b1b;
            border-radius: 18px;
            padding: 14px 18px;
            font-size: 14px;
            line-height: 1.8;
        }

        .actions {
            margin-top: 28px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 14px;
            padding: 12px 20px;
            font-family: inherit;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 150px;
            transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--blue);
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, .22);
        }

        .btn-soft {
            background: var(--blue-soft);
            color: #1d4ed8;
        }

        .footer {
            border-top: 1px solid var(--border);
            padding: 14px 24px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
            background: #f8fafc;
        }

        @media (max-width: 540px) {
            body {
                padding: 14px;
            }

            .top-bar {
                padding: 18px;
                align-items: flex-start;
            }

            .brand-title {
                font-size: 14px;
            }

            .content {
                padding: 28px 18px 24px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="error-card" role="main">
        <header class="top-bar">
            <div class="brand">
                <div class="brand-icon">📁</div>
                <div>
                    <div class="brand-title">نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين</div>
                    <div class="brand-subtitle">إدارة الكتب، المرفقات، الصلاحيات والنسخ الاحتياطي</div>
                </div>
            </div>
            <div class="code-badge">403</div>
        </header>

        <section class="content">
            <div class="lock-icon">🔒</div>

            <h1>غير مصرح لك بالدخول</h1>

            <p class="message">
                هذه الصفحة محمية بصلاحيات خاصة. لا يمكن فتحها إلا من حساب يملك صلاحية مناسبة داخل النظام
.
            </p>

            <div class="notice">
                {{ $exception->getMessage() ?: 'هذه الصفحة متاحة لمدير النظامفقط.' }}
            </div>

            <div class="actions">
                @auth
                    <a class="btn btn-primary" href="{{ route('dashboard') }}">🏠 العودة إلى لوحة التحكم
</a>
                @else
                    <a class="btn btn-primary" href="{{ route('login') }}">🔐 تسجيل الدخول</a>
                @endauth

                <button class="btn btn-soft" type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'">
                    ↩️ رجوع للخلف
                </button>
            </div>
        </section>

        <footer class="footer">
            في حال كنت تحتاج هذه الصلاحية، يرجى مراجعة مدير النظام
.
        </footer>
    </main>
</body>
</html>
