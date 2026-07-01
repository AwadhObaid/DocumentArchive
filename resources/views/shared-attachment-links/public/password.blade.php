<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كلمة مرور رابط المرفقات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/shared-attachments.css') }}?v={{ filemtime(public_path('css/shared-attachments.css')) }}">
</head>
<body class="share-public-body">
    <main class="share-public-shell">
        <section class="share-public-card share-password-card">
            <div class="share-public-brand">📁 DocumentArchive</div>
            <h1>الرابط محمي بكلمة مرور</h1>
            <p>أدخل كلمة المرور التي زوّدك بها مرسل الرابط لعرض المرفقات.</p>

            <form method="POST" action="{{ route('shared-attachments.public.unlock', $link->token) }}">
                @csrf
                <label>كلمة المرور</label>
                <input type="password" name="password" autofocus required>
                @error('password')<small class="field-error">{{ $message }}</small>@enderror
                <button class="btn btn-primary" type="submit">دخول</button>
            </form>
        </section>
    </main>
</body>
</html>
