<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'نظام أرشفة المستندات')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .topbar {
            background: #1f2937;
            color: white;
            padding: 14px 24px;
            font-size: 18px;
            font-weight: bold;
        }

        .container {
            width: min(1200px, calc(100% - 32px));
            margin: 24px auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,.06);
            margin-bottom: 18px;
        }

        .page-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        h1, h2, h3 {
            margin-top: 0;
        }

        .btn {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        input, select, textarea {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            font-size: 15px;
            font-family: Tahoma, Arial, sans-serif;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 11px;
            border-bottom: 1px solid #e5e7eb;
            text-align: right;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e5e7eb;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 700px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                flex-direction: column;
                align-items: stretch;
            }

            table {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<div class="topbar" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
    <span>نظام أرشفة المستندات</span>

    <div style="display:flex; gap:10px; font-size:14px;">
        <a href="{{ route('documents.index') }}" style="color:white; text-decoration:none;">المستندات</a>
        <a href="{{ route('settings.edit') }}" style="color:white; text-decoration:none;">الإعدادات</a>
    </div>
</div>

<div class="container">
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            <strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</div>

</body>
</html>