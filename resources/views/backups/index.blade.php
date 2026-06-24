@extends('layouts.app')

@section('title', 'النسخ الاحتياطي')

@section('content')
<div class="page-header">
    <div>
        <h1>النسخ الاحتياطي</h1>
        <p>إنشاء نسخ احتياطية من قاعدة البيانات وملفات المرفقات وحفظها داخل التخزين الخاص.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="grid-cards backup-actions">
    <div class="card">
        <h3>نسخة قاعدة البيانات</h3>
        <p>تصدير جداول النظام إلى ملف SQL داخل ZIP.</p>
        <form method="POST" action="{{ route('backups.database') }}">
            @csrf
            <button type="submit" class="btn btn-primary">إنشاء نسخة قاعدة البيانات</button>
        </form>
    </div>

    <div class="card">
        <h3>نسخة ملفات المرفقات</h3>
        <p>نسخ ملفات الكتب والمرفقات من التخزين الخاص.</p>
        <form method="POST" action="{{ route('backups.files') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">إنشاء نسخة الملفات</button>
        </form>
    </div>

    <div class="card">
        <h3>نسخة كاملة</h3>
        <p>قاعدة البيانات + ملفات المرفقات في ملف ZIP واحد.</p>
        <form method="POST" action="{{ route('backups.full') }}">
            @csrf
            <button type="submit" class="btn btn-success">إنشاء نسخة كاملة</button>
        </form>
    </div>
</div>

<div class="alert alert-info backup-note">
    <strong>ملاحظة:</strong>
    قبل استخدام أي نسخة للاستعادة مستقبلاً، استخدم زر <strong>فحص</strong> للتأكد من أن النسخة تحتوي على ملف قاعدة البيانات و/أو ملفات المرفقات حسب نوعها.
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3>ملفات النسخ الاحتياطية</h3>
        <small>المسار: {{ $backupPath }}</small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>اسم الملف</th>
                    <th>الحجم</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $backup)
                    <tr>
                        <td class="backup-file-name">{{ $backup['name'] }}</td>
                        <td>{{ $backup['size'] }}</td>
                        <td>{{ $backup['created_at'] }}</td>
                        <td class="table-actions">
                            <a href="{{ route('backups.inspect', $backup['name']) }}" class="btn btn-sm btn-info">فحص</a>
                            <a href="{{ route('backups.download', $backup['name']) }}" class="btn btn-sm btn-primary">تنزيل</a>
                            <form method="POST" action="{{ route('backups.destroy', $backup['name']) }}" class="d-inline" onsubmit="return confirm('هل تريد حذف ملف النسخة الاحتياطية؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">لا توجد نسخ احتياطية حالياً.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
.backup-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}
.backup-actions .card {
    padding: 18px;
}
.backup-actions h3 {
    margin-bottom: 8px;
}
.backup-actions p {
    color: #64748b;
    min-height: 48px;
}
.backup-note {
    margin-top: 16px;
}
.backup-file-name {
    direction: ltr;
    text-align: left;
    font-weight: 700;
}
.table-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
</style>
@endsection
