@extends('layouts.app')

@section('title', 'تقرير فحص واستيراد المذكرات القديمة')
@section('page_title', 'تقرير فحص واستيراد المذكرات القديمة')
@section('page_subtitle', 'مراجعة البيانات ثم نسخ المذكرات المحددة بأمان إلى النظام')

@section('content')
@php
    $sizeForHumans = function ($bytes): string {
        $bytes = (int) $bytes;

        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    };

    $toneStyle = function ($tone): string {
        return match ($tone) {
            'success' => 'background:#dcfce7;color:#166534;border-color:#86efac;',
            'warning' => 'background:#fef3c7;color:#92400e;border-color:#fcd34d;',
            'danger' => 'background:#fee2e2;color:#991b1b;border-color:#fca5a5;',
            default => 'background:#e2e8f0;color:#334155;border-color:#cbd5e1;',
        };
    };

    $importableStatuses = ['ready', 'needs_review', 'import_failed'];
@endphp

<style>
    .legacy-memo-report { display:grid; gap:18px; }
    .legacy-memo-report-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; }
    .legacy-memo-report-head h1 { margin:0; }
    .legacy-memo-report-head p { margin:7px 0 0; color:var(--muted, #94a3b8); line-height:1.75; }
    .legacy-memo-stats { display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); gap:10px; }
    .legacy-memo-stat { padding:14px; border:1px solid rgba(148,163,184,.28); border-radius:14px; background:rgba(255,255,255,.68); }
    html[data-theme="dark"] .legacy-memo-stat { background:rgba(15,23,42,.55); }
    .legacy-memo-stat span { display:block; color:var(--muted, #94a3b8); font-size:12px; margin-bottom:7px; }
    .legacy-memo-stat strong { font-size:22px; }
    .legacy-memo-filter { display:grid; grid-template-columns:2fr 1fr auto; gap:10px; align-items:end; }
    .legacy-memo-filter .form-group { margin:0; }
    .legacy-memo-actions { display:flex; gap:7px; flex-wrap:wrap; align-items:center; }
    .legacy-memo-path { direction:ltr; unicode-bidi:plaintext; word-break:break-all; white-space:normal; min-width:260px; }
    .legacy-memo-subject { min-width:280px; white-space:normal; line-height:1.65; }
    .legacy-memo-subject textarea { width:100%; min-height:72px; resize:vertical; }
    .legacy-memo-date input { min-width:145px; }
    .legacy-memo-badge { display:inline-flex; padding:5px 9px; border:1px solid; border-radius:999px; font-size:11px; font-weight:850; white-space:nowrap; }
    .legacy-memo-hash { direction:ltr; font-family:monospace; font-size:11px; }
    .legacy-memo-note { padding:14px 16px; border:1px solid rgba(59,130,246,.35); border-radius:14px; background:rgba(59,130,246,.08); line-height:1.8; }
    .legacy-memo-import-note { border-color:rgba(34,197,94,.42); background:rgba(34,197,94,.08); }
    .legacy-memo-import-toolbar { display:grid; grid-template-columns:1fr minmax(220px, 320px) auto; gap:12px; align-items:end; padding:14px; border-bottom:1px solid rgba(148,163,184,.25); }
    .legacy-memo-import-toolbar label { display:block; margin-bottom:7px; font-weight:800; }
    .legacy-memo-import-toolbar input[type="text"] { width:100%; }
    .legacy-memo-select { width:42px; text-align:center; }
    .legacy-memo-import-warning { color:#f59e0b; font-size:12px; line-height:1.7; margin-top:7px; }
    .legacy-memo-result { min-width:250px; white-space:normal; line-height:1.65; }
    @media (max-width:1050px) {
        .legacy-memo-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .legacy-memo-import-toolbar { grid-template-columns:1fr; }
    }
    @media (max-width:760px) {
        .legacy-memo-filter { grid-template-columns:1fr; }
        .legacy-memo-stats { grid-template-columns:1fr; }
    }
</style>

<div class="legacy-memo-report">
    <div class="legacy-memo-report-head">
        <div>
            <h1>عملية الفحص #{{ $run->id }}</h1>
            <p>
                حالة الفحص: <strong>{{ $run->status_label }}</strong><br>
                حالة الاستيراد:
                <strong>{{ $run->import_status_label }}</strong><br>
                المسار:
                <span dir="ltr">{{ $run->source_root }}</span>
            </p>
        </div>

        <div class="legacy-memo-actions">
            <a class="btn btn-light" href="{{ route('memo-legacy-import.index') }}">
                عمليات الفحص
            </a>
            <a class="btn btn-secondary" href="{{ route('memos.index') }}">
                صفحة المذكرات
            </a>
        </div>
    </div>

    <div class="legacy-memo-note legacy-memo-import-note">
        <strong>الاستيراد الآمن V84.1:</strong>
        راجع التاريخ والموضوع، وحدد الملفات المطلوبة، ثم أكد العملية.
        سيُحجز الرقم الداخلي لحظة الاستيراد بدءًا من 2600000،
        وسيُنسخ الملف إلى تخزين النظام مع التحقق من الحجم وبصمة SHA-256.
        الملفات الأصلية على Server-1 لن تُنقل أو تُحذف أو تُعدّل.
    </div>

    <div class="legacy-memo-stats">
        <div class="legacy-memo-stat"><span>إجمالي الملفات</span><strong>{{ number_format($run->total_files) }}</strong></div>
        <div class="legacy-memo-stat"><span>جاهزة</span><strong>{{ number_format($run->items()->where('status', 'ready')->count()) }}</strong></div>
        <div class="legacy-memo-stat"><span>تحتاج مراجعة</span><strong>{{ number_format($run->items()->where('status', 'needs_review')->count()) }}</strong></div>
        <div class="legacy-memo-stat"><span>تم استيرادها</span><strong>{{ number_format($run->imported_files) }}</strong></div>
        <div class="legacy-memo-stat"><span>فشل استيرادها</span><strong>{{ number_format($run->import_failed_files) }}</strong></div>
        <div class="legacy-memo-stat"><span>مكررة بالنظام</span><strong>{{ number_format($run->items()->where('status', 'duplicate_system')->count()) }}</strong></div>
        <div class="legacy-memo-stat"><span>مكررة بالمجلد</span><strong>{{ number_format($run->duplicate_scan_files) }}</strong></div>
        <div class="legacy-memo-stat"><span>غير قابلة للقراءة</span><strong>{{ number_format($run->unreadable_files) }}</strong></div>
        <div class="legacy-memo-stat"><span>الحجم الإجمالي</span><strong>{{ $sizeForHumans($run->bytes_total) }}</strong></div>
        <div class="legacy-memo-stat"><span>الفحص الفرعي</span><strong>{{ $run->recursive ? 'نعم' : 'لا' }}</strong></div>
    </div>

    <div class="card">
        <form method="GET"
              action="{{ route('memo-legacy-import.show', $run) }}"
              class="legacy-memo-filter">
            <div class="form-group">
                <label>بحث في الاسم أو المسار أو الموضوع أو الرقم أو البصمة</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}">
            </div>

            <div class="form-group">
                <label>الحالة</label>
                <select name="status">
                    <option value="all">كل الحالات</option>
                    @foreach($statusOptions as $option)
                        @php
                            $sample = new \App\Models\LegacyMemoImportItem([
                                'status' => $option->status,
                            ]);
                        @endphp
                        <option value="{{ $option->status }}"
                                @selected(($filters['status'] ?? 'all') === $option->status)>
                            {{ $sample->status_label }} ({{ number_format($option->total) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="legacy-memo-actions">
                <button class="btn btn-primary" type="submit">تطبيق</button>
                <a class="btn btn-light"
                   href="{{ route('memo-legacy-import.show', $run) }}">إلغاء</a>
            </div>
        </form>
    </div>

    <div class="card">
        @if($items->count())
            <form method="POST"
                  action="{{ route('memo-legacy-import.import', $run) }}"
                  id="legacy-memo-import-form">
                @csrf

                <div class="legacy-memo-import-toolbar">
                    <div>
                        <div class="legacy-memo-actions">
                            <button class="btn btn-light"
                                    type="button"
                                    id="select-importable-items">
                                تحديد الجاهز في الصفحة
                            </button>
                            <button class="btn btn-light"
                                    type="button"
                                    id="clear-importable-items">
                                إلغاء التحديد
                            </button>
                            <strong>
                                المحدد:
                                <span id="selected-import-count">0</span>
                            </strong>
                        </div>
                        <div class="legacy-memo-import-warning">
                            راجع الموضوع والتاريخ قبل التنفيذ. المذكرات المستوردة
                            ستُنشأ بحالة «مسودة» داخل دورة الاعتماد.
                        </div>
                    </div>

                    <div>
                        <label for="confirmation_text">
                            للتأكيد اكتب: استيراد المذكرات
                        </label>
                        <input id="confirmation_text"
                               type="text"
                               name="confirmation_text"
                               autocomplete="off"
                               required
                               placeholder="استيراد المذكرات">
                    </div>

                    <button class="btn btn-success" type="submit">
                        استيراد المذكرات المحددة
                    </button>
                </div>

                @error('items')
                    <div class="alert alert-danger" style="margin:12px;">
                        {{ $message }}
                    </div>
                @enderror

                @error('confirmation_text')
                    <div class="alert alert-danger" style="margin:12px;">
                        {{ $message }}
                    </div>
                @enderror

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="legacy-memo-select">تحديد</th>
                                <th>الحالة</th>
                                <th>الرقم</th>
                                <th>التاريخ</th>
                                <th>الموضوع</th>
                                <th>الملف والمسار</th>
                                <th>الحجم</th>
                                <th>البصمة</th>
                                <th>النتيجة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php
                                    $isImportable = in_array(
                                        $item->status,
                                        $importableStatuses,
                                        true
                                    );
                                    $dateValue = old(
                                        'dates.' . $item->id,
                                        optional($item->proposed_memo_date)
                                            ->format('Y-m-d')
                                    );
                                    $subjectValue = old(
                                        'subjects.' . $item->id,
                                        $item->proposed_subject
                                    );
                                @endphp
                                <tr>
                                    <td class="legacy-memo-select">
                                        @if($isImportable)
                                            <input type="checkbox"
                                                   class="legacy-memo-item-checkbox"
                                                   name="items[]"
                                                   value="{{ $item->id }}">
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <span class="legacy-memo-badge"
                                              style="{{ $toneStyle($item->status_tone) }}">
                                            {{ $item->status_label }}
                                        </span>

                                        @if($item->memo)
                                            <div style="margin-top:7px;">
                                                <a href="{{ route('memos.show', $item->memo) }}">
                                                    مذكرة {{ $item->memo->memo_number }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    <td dir="ltr">
                                        @if($item->memo)
                                            <strong>{{ $item->memo->memo_number }}</strong>
                                        @else
                                            {{ $item->proposed_memo_number ?: '-' }}
                                        @endif
                                    </td>
                                    <td class="legacy-memo-date">
                                        @if($isImportable)
                                            <input type="date"
                                                   name="dates[{{ $item->id }}]"
                                                   value="{{ $dateValue }}">
                                        @else
                                            {{ optional($item->proposed_memo_date)->format('Y-m-d') ?: '-' }}
                                        @endif

                                        @if(data_get($item->payload, 'date_source'))
                                            <div class="text-muted" style="font-size:10px;">
                                                {{ data_get($item->payload, 'date_source') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="legacy-memo-subject">
                                        @if($isImportable)
                                            <textarea name="subjects[{{ $item->id }}]"
                                                      maxlength="5000">{{ $subjectValue }}</textarea>
                                        @else
                                            {{ $item->proposed_subject ?: '-' }}
                                        @endif
                                    </td>
                                    <td class="legacy-memo-path">
                                        <strong>{{ $item->original_name }}</strong><br>
                                        <small>{{ $item->relative_path }}</small>
                                    </td>
                                    <td>{{ $sizeForHumans($item->file_size) }}</td>
                                    <td>
                                        @if($item->sha256)
                                            <span class="legacy-memo-hash"
                                                  title="{{ $item->sha256 }}">
                                                {{ substr($item->sha256, 0, 12) }}…
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="legacy-memo-result">
                                        {{ $item->message ?: '-' }}

                                        @if($item->status === 'imported')
                                            <div style="margin-top:6px;color:#16a34a;">
                                                تم التحقق من الحجم والبصمة بعد النسخ.
                                            </div>
                                        @endif

                                        @if(data_get($item->payload, 'duplicate_of_path'))
                                            <div class="text-muted" style="margin-top:6px;">
                                                النسخة الأولى:
                                                <span dir="ltr">{{ data_get($item->payload, 'duplicate_of_path') }}</span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            {{ $items->links() }}
        @else
            <p class="empty-state">لا توجد نتائج مطابقة للفلتر الحالي.</p>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(
        document.querySelectorAll('.legacy-memo-item-checkbox')
    );
    const countNode = document.getElementById('selected-import-count');
    const selectButton = document.getElementById('select-importable-items');
    const clearButton = document.getElementById('clear-importable-items');
    const form = document.getElementById('legacy-memo-import-form');

    const updateCount = function () {
        const total = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        if (countNode) {
            countNode.textContent = String(total);
        }
    };

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateCount);
    });

    if (selectButton) {
        selectButton.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = true;
            });
            updateCount();
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
            updateCount();
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (selected === 0) {
                event.preventDefault();
                alert('حدد مذكرة واحدة على الأقل للاستيراد.');
                return;
            }

            if (! confirm(
                'سيتم إنشاء ' + selected
                + ' مذكرة ونسخ ملفاتها إلى النظام دون حذف ملفات Server-1. متابعة؟'
            )) {
                event.preventDefault();
            }
        });
    }

    updateCount();
});
</script>
@endsection
