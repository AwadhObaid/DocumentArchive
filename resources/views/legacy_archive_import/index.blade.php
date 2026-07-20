@extends('layouts.app')

@section('page_title', 'استيراد الأرشيف القديم')
@section('page_subtitle', 'فحص واستيراد بيانات TbArchive ونسخ المرفقات دون حذف الملفات الأصلية')

@section('content')
<div class="legacy-import-page">
    <section class="legacy-import-hero">
        <div>
            <span class="legacy-import-kicker">V81 — Legacy Archive Importer</span>
            <h2>استيراد الأرشيف القديم بسرعة وأمان</h2>
            <p>
                الأداة تدعم ملف CSV/TSV الناتج من جدول <strong>TbArchive</strong>، وتبدأ بفحص تجريبي قبل أي إنشاء أو نسخ.
                الملفات الأصلية لا تُحذف مطلقًا.
            </p>
        </div>
        <div class="legacy-import-safety">
            <strong>وضع الحماية</strong>
            <span>نسخ فقط + SHA-256 + مقارنة الحجم + منع التكرار</span>
        </div>
    </section>


    {{-- legacy-archive-existing-attachment-repair-v81-5:start --}}
    <section class="legacy-import-card">
        <div class="legacy-import-section-head">
            <div>
                <h3>🧩 استكمال مرفقات الكتب المستوردة — V81.5</h3>
                <p>
                    العثور على رقم ID قديم لا يجعل السجل مكررًا مباشرة. يفحص النظام أولًا وجود مرفق فعلي صالح؛
                    فإن لم يوجد، يبحث في FilePath وOriginalFilePath مع الحفاظ على أسماء المشاركات العربية كما هي.
                </p>
            </div>
        </div>
        <div class="legacy-import-warning">
            ابدأ بالفحص التجريبي. الكتب الموجودة دون مرفق صالح ستظهر بالحالة «جاهز لاستكمال المرفق»،
            بينما الكتب التي لديها ملف فعلي صالح فقط ستظهر «مستورد سابقًا».
        </div>
    </section>
    {{-- legacy-archive-existing-attachment-repair-v81-5:end --}}

    <section class="legacy-import-grid">
        <article class="legacy-import-card">
            <h3>مصدر البيانات</h3>

            <form method="POST" action="{{ route('legacy-archive-import.dry-run') }}" enctype="multipart/form-data" class="legacy-import-form">
                @csrf

                <label class="legacy-import-check">
                    <input type="checkbox" name="use_bundled_file" value="1" checked>
                    <span>
                        استخدام ملف Results.csv المرفق
                        @if($bundledExists)
                            <small>موجود — {{ number_format($bundledSize / 1024, 1) }} KB</small>
                        @else
                            <small class="legacy-import-danger">غير موجود</small>
                        @endif
                    </span>
                </label>

                <label>
                    <span>أو اختر ملف تصدير آخر</span>
                    <input type="file" name="archive_file" accept=".csv,.txt,.tsv">
                </label>

                <label>
                    <span>جذر بديل للملفات القديمة — اختياري</span>
                    <input type="text"
                           name="source_root_override"
                           value="{{ old('source_root_override') }}"
                           placeholder="مثال: \\SERVER-3\ESIS_Archive أو E:\ESIS_Archive">
                    <small>
                        استخدمه إذا تغيّر اسم السيرفر أو تم نسخ مجلد ESIS_Archive إلى قرص آخر.
                    </small>
                </label>

                <label>
                    <span>أقصى عدد سجلات في العملية</span>
                    <input type="number" name="limit" value="{{ old('limit', 100000) }}" min="1" max="100000">
                </label>

                <label class="legacy-import-check">
                    <input type="checkbox" name="import_missing_without_file" value="1" checked>
                    <span>
                        عند التنفيذ: استيراد بيانات الكتاب حتى لو كان المرفق مفقودًا
                        <small>سيظهر الكتاب في تقرير المرفقات المفقودة للمراجعة لاحقًا.</small>
                    </span>
                </label>

                <button type="submit" class="btn btn-primary legacy-import-main-button">
                    🔍 فحص تجريبي بدون استيراد
                </button>
            </form>
        </article>

        <article class="legacy-import-card legacy-import-execute-card">
            <h3>الاستيراد الفعلي</h3>
            <p>
                نفّذ الفحص التجريبي أولًا، ثم راجع الملفات المفقودة والتكرارات. التنفيذ ينشئ الكتب وينسخ الملفات الموجودة
                إلى التصنيف الجديد.
            </p>

            <form method="POST" action="{{ route('legacy-archive-import.execute') }}" enctype="multipart/form-data" class="legacy-import-form" data-legacy-import-confirm>
                @csrf

                <label class="legacy-import-check">
                    <input type="checkbox" name="use_bundled_file" value="1" checked>
                    <span>استخدام ملف Results.csv المرفق</span>
                </label>

                <label>
                    <span>أو اختر ملف التصدير</span>
                    <input type="file" name="archive_file" accept=".csv,.txt,.tsv">
                </label>

                <label>
                    <span>جذر بديل للملفات القديمة — اختياري</span>
                    <input type="text"
                           name="source_root_override"
                           value="{{ old('source_root_override') }}"
                           placeholder="\\SERVER-3\ESIS_Archive">
                </label>

                <input type="hidden" name="limit" value="100000">
                <input type="hidden" name="import_missing_without_file" value="1">

                <label>
                    <span>عبارة التأكيد</span>
                    <input type="text"
                           name="confirm_phrase"
                           autocomplete="off"
                           placeholder="اكتب: استيراد الأرشيف القديم"
                           required>
                </label>

                <div class="legacy-import-warning">
                    لن تُحذف ملفات النظام القديم. قد تستغرق العملية عدة دقائق بحسب سرعة الشبكة وحجم الملفات.
                </div>

                <button type="submit" class="btn btn-danger legacy-import-main-button">
                    📥 تنفيذ الاستيراد الفعلي
                </button>
            </form>
        </article>
    </section>

    <section class="legacy-import-card">
        <div class="legacy-import-section-head">
            <div>
                <h3>سجل عمليات الاستيراد</h3>
                <p>آخر عمليات الفحص والتنفيذ مع ملخص النتائج.</p>
            </div>
        </div>

        <div class="legacy-import-table-wrap">
            <table class="legacy-import-table">
                <thead>
                    <tr>
                        <th>العملية</th>
                        <th>الحالة</th>
                        <th>الإجمالي</th>
                        <th>المستوردة</th>
                        <th>المكررة</th>
                        <th>المفقودة</th>
                        <th>الأخطاء</th>
                        <th>التاريخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->mode_label }}</td>
                            <td>{{ $run->status_label }}</td>
                            <td>{{ number_format($run->total_rows) }}</td>
                            <td>{{ number_format($run->imported_rows) }}</td>
                            <td>{{ number_format($run->duplicate_rows) }}</td>
                            <td>{{ number_format($run->missing_file_rows) }}</td>
                            <td>{{ number_format($run->failed_rows) }}</td>
                            <td>{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('legacy-archive-import.show', $run) }}">عرض</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="legacy-import-empty">لم تُنفذ أي عملية حتى الآن.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
