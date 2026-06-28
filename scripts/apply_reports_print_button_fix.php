<?php
/**
 * Adds a visible "تقرير رسمي منسق" button to the reports page.
 * Run from Laravel project root:
 *   php scripts/apply_reports_print_button_fix.php
 */

$root = realpath(__DIR__ . '/..');
if (!$root) {
    fwrite(STDERR, "ERROR: Cannot resolve project root.\n");
    exit(1);
}

$target = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'index.blade.php';

if (!is_file($target)) {
    fwrite(STDERR, "ERROR: لم يتم العثور على ملف صفحة التقارير: {$target}\n");
    exit(1);
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDERR, "ERROR: تعذر قراءة ملف صفحة التقارير.\n");
    exit(1);
}

$start = '{{-- REPORTS_PRINT_BUTTON_FIX_START --}}';
$end   = '{{-- REPORTS_PRINT_BUTTON_FIX_END --}}';

// Remove any previous copy of this exact fix block.
$contents = preg_replace('/\s*' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '\s*/su', "\n", $contents);

$block = <<<'BLADE'

{{-- REPORTS_PRINT_BUTTON_FIX_START --}}
<style>
    .reports-print-button-panel {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: .65rem;
        flex-wrap: wrap;
        margin: 0 0 1rem 0;
        direction: rtl;
    }

    .reports-print-button-panel .report-print-professional-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        min-height: 40px;
        padding: .65rem 1rem;
        border-radius: .7rem;
        border: 1px solid rgba(59, 130, 246, .35);
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff !important;
        text-decoration: none !important;
        font-weight: 800;
        line-height: 1;
        box-shadow: 0 10px 22px rgba(37, 99, 235, .18);
        white-space: nowrap;
    }

    .reports-print-button-panel .report-print-professional-btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.04);
    }

    @media print {
        .reports-print-button-panel {
            display: none !important;
        }
    }
</style>

<div class="reports-print-button-panel" aria-label="إجراءات التقرير الرسمي">
    <a href="{{ url('/reports/print') }}" class="report-print-professional-btn" target="_blank" rel="noopener">
        <span aria-hidden="true">🧾</span>
        <span>تقرير رسمي منسق</span>
    </a>
</div>
{{-- REPORTS_PRINT_BUTTON_FIX_END --}}

BLADE;

$inserted = false;

// Preferred: place the button before the filter form, so it appears near existing report actions.
if (!$inserted && preg_match('/<form\b/iu', $contents, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1];
    $contents = substr($contents, 0, $pos) . $block . substr($contents, $pos);
    $inserted = true;
}

// Fallback: directly after @section('content') / @section("content").
if (!$inserted && preg_match('/@section\s*\(\s*[\'\"]content[\'\"]\s*\)/iu', $contents, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1] + strlen($m[0][0]);
    $contents = substr($contents, 0, $pos) . $block . substr($contents, $pos);
    $inserted = true;
}

// Last fallback: prepend to the file.
if (!$inserted) {
    $contents = $block . $contents;
    $inserted = true;
}

$backup = $target . '.before-reports-print-button-fix.bak';
if (!is_file($backup)) {
    if (!copy($target, $backup)) {
        fwrite(STDERR, "ERROR: تعذر إنشاء نسخة احتياطية من ملف التقارير.\n");
        exit(1);
    }
}

if (file_put_contents($target, $contents) === false) {
    fwrite(STDERR, "ERROR: تعذر حفظ تعديل زر التقرير الرسمي.\n");
    exit(1);
}

echo "DONE: تم إضافة زر التقرير الرسمي المنسق إلى صفحة التقارير.\n";
echo "افتح /reports ثم اضغط Ctrl+F5 للتأكد من ظهور الزر.\n";
