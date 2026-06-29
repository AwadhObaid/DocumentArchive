@extends('layouts.app')

@section('title', 'النسخ الاحتياطي')

@section('content')

<!-- DA_BACKUPS_TABLE_INNER_SCROLL_START -->
<style>
    /* Scoped to the backups page only: keep wide backup tables inside their card. */
    @media screen {
        body:has(#daBackupsPage) {
            overflow-x: hidden !important;
        }

        #daBackupsPage,
        #daBackupsPage * {
            box-sizing: border-box;
        }

        #daBackupsPage {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden !important;
        }

        #daBackupsPage .da-backup-table-scroll {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-x: auto !important;
            overflow-y: visible !important;
            -webkit-overflow-scrolling: touch;
            direction: ltr;
            border-radius: 14px;
            scrollbar-gutter: stable both-edges;
        }

        #daBackupsPage .da-backup-table-scroll table {
            direction: rtl;
            width: max-content !important;
            min-width: 980px;
            max-width: none !important;
            table-layout: auto !important;
        }

        #daBackupsPage .da-backup-table-scroll th,
        #daBackupsPage .da-backup-table-scroll td {
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        #daBackupsPage .da-backup-table-scroll::-webkit-scrollbar {
            height: 12px;
        }

        #daBackupsPage .da-backup-table-scroll::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.22);
            border-radius: 999px;
        }

        #daBackupsPage .da-backup-table-scroll::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.75);
            border-radius: 999px;
        }
    }

    @media print {
        #daBackupsPage .da-backup-table-scroll {
            overflow: visible !important;
        }

        #daBackupsPage .da-backup-table-scroll table {
            width: 100% !important;
            min-width: 0 !important;
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var page = document.getElementById('daBackupsPage');
        if (!page) return;

        page.querySelectorAll('table').forEach(function (table) {
            if (table.closest('.da-backup-table-scroll')) return;

            var wrapper = document.createElement('div');
            wrapper.className = 'da-backup-table-scroll';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);

            // In RTL pages, start from the right side where the primary columns are.
            setTimeout(function () {
                wrapper.scrollLeft = wrapper.scrollWidth;
            }, 0);
        });
    });
</script>
<!-- DA_BACKUPS_TABLE_INNER_SCROLL_END -->
<div id="daBackupsPage" class="da-backups-page">
<div class="page-header backup-polish-v1">
    <div>
        <h1>النسخ الاحتياطي</h1>
        <p>إنشاء نسخ احتياطية آمنة من بيانات الأرشيف وملفات المرفقات، وفحص النسخة قبل أي استعادة.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="backup-safety-banner">
    <strong>🛡️ تنبيه أمان:</strong>
    <span>قبل أي استعادة كبيرة، يفضّل إنشاء نسخة كاملة والاحتفاظ بها خارج الجهاز الرئيسي.</span>
</div>

<div class="backup-actions-grid">
    <div class="card backup-action-card">
        <h3>نسخة قاعدة البيانات</h3>
        <p>تصدير بيانات الأرشيف الأساسية إلى ملف SQL داخل ZIP. الاستعادة لا تغيّر المستخدمين أو كلمات المرور.</p>
        @if(auth()->user()?->hasPermission('backups.create'))
        <form method="POST" action="{{ route('backups.database') }}">@csrf<button type="submit" class="btn btn-primary">إنشاء نسخة قاعدة البيانات</button></form>
        @endif
    </div>
    <div class="card backup-action-card">
        <h3>نسخة ملفات المرفقات</h3>
        <p>نسخ ملفات الكتب والمرفقات من التخزين الخاص إلى ملف ZIP مستقل.</p>
        @if(auth()->user()?->hasPermission('backups.create'))
        <form method="POST" action="{{ route('backups.files') }}">@csrf<button type="submit" class="btn btn-secondary">إنشاء نسخة الملفات</button></form>
        @endif
    </div>
    <div class="card backup-action-card highlighted">
        <h3>نسخة كاملة</h3>
        <p>الخيار الأفضل قبل التحديثات: قاعدة البيانات + ملفات المرفقات في ملف ZIP واحد.</p>
        @if(auth()->user()?->hasPermission('backups.create'))
        <form method="POST" action="{{ route('backups.full') }}">@csrf<button type="submit" class="btn btn-success">إنشاء نسخة كاملة</button></form>
        @endif
    </div>
</div>

<div class="card mt-4 backup-list-card">
    <div class="card-header backup-list-header">
        <div><h3>ملفات النسخ الاحتياطية</h3><small>المسار: <span dir="ltr">{{ $backupPath }}</span></small></div>
    </div>
    <div class="table-responsive backup-table-wrap">
        <table class="table table-hover align-middle backup-table">
            <thead><tr><th>اسم الملف</th><th>نوع النسخة</th><th>الحجم</th><th>تاريخ الإنشاء</th><th>الإجراءات</th></tr></thead>
            <tbody>
                @forelse($backups as $backup)
                    @php
                        $name = (string) $backup['name'];
                        $type = 'غير محدد'; $badge = 'neutral';
                        if (str_starts_with($name, 'full-backup-')) { $type = 'نسخة كاملة'; $badge = 'success'; }
                        elseif (str_starts_with($name, 'database-backup-')) { $type = 'قاعدة بيانات'; $badge = 'primary'; }
                        elseif (str_starts_with($name, 'files-backup-')) { $type = 'ملفات مرفقات'; $badge = 'info'; }
                        elseif (str_starts_with($name, 'pre-restore-')) { $type = 'نسخة أمان قبل الاستعادة'; $badge = 'warning'; }
                    @endphp
                    <tr>
                        <td class="backup-file-name">{{ $backup['name'] }}</td>
                        <td><span class="backup-badge {{ $badge }}">{{ $type }}</span></td>
                        <td>{{ $backup['size'] }}</td>
                        <td>{{ $backup['created_at'] }}</td>
                        <td class="table-actions">
                            @if(auth()->user()?->hasPermission('backups.view'))<a href="{{ route('backups.inspect', $backup['name']) }}" class="btn btn-sm btn-info">فحص</a>@endif
                            @if(auth()->user()?->hasPermission('backups.restore'))<a href="{{ route('backups.restore', $backup['name']) }}" class="btn btn-sm btn-warning">استعادة</a>@endif
                            @if(auth()->user()?->hasPermission('backups.download'))<a href="{{ route('backups.download', $backup['name']) }}" class="btn btn-sm btn-primary">تنزيل</a>@endif
                            @if(auth()->user()?->hasPermission('backups.delete'))
                            <form method="POST" action="{{ route('backups.destroy', $backup['name']) }}" class="d-inline" onsubmit="return confirm('هل تريد حذف ملف النسخة الاحتياطية؟')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-danger">حذف</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">لا توجد نسخ احتياطية حالياً.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
.backup-safety-banner{display:flex;gap:8px;align-items:center;padding:14px 16px;border-radius:16px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.25);margin-bottom:16px;flex-wrap:wrap}.backup-safety-banner span{color:#64748b}.backup-actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.backup-action-card{padding:18px}.backup-action-card.highlighted{border-color:rgba(34,197,94,.35)}.backup-action-card p{color:#64748b;min-height:58px}.backup-list-card{padding:0;overflow:hidden}.backup-list-header{padding:18px 20px}.backup-list-header h3{margin:0 0 4px}.backup-table-wrap{overflow-x:auto}.backup-table{min-width:820px}.backup-file-name{direction:ltr;text-align:left;font-weight:700;white-space:nowrap}.table-actions{display:flex;gap:8px;flex-wrap:wrap}.backup-badge{display:inline-flex;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;white-space:nowrap}.backup-badge.success{background:rgba(34,197,94,.14);color:#15803d}.backup-badge.primary{background:rgba(59,130,246,.14);color:#1d4ed8}.backup-badge.info{background:rgba(14,165,233,.14);color:#0369a1}.backup-badge.warning{background:rgba(245,158,11,.16);color:#b45309}.backup-badge.neutral{background:rgba(148,163,184,.18);color:#475569}
</style>

</div>
@endsection
