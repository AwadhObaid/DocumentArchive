<?php

$root = realpath(__DIR__ . '/..');
if (!$root) {
    fwrite(STDERR, "ERROR: تعذر تحديد مسار المشروع.\n");
    exit(1);
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function writeFileOrFail(string $path, string $content): void
{
    ensureDir(dirname($path));
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function getLayoutFiles(string $root): array
{
    $dir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts';
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . DIRECTORY_SEPARATOR . '*.blade.php') ?: [];
    return array_values(array_filter($files, static function ($file) {
        return strpos($file, DIRECTORY_SEPARATOR . '_backup') === false;
    }));
}

function injectOnce(string $file, string $marker, string $snippet, string $beforeTag): bool
{
    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException("تعذر قراءة الملف: {$file}");
    }

    if (strpos($content, $marker) !== false) {
        return false;
    }

    $pos = stripos($content, $beforeTag);
    if ($pos !== false) {
        $content = substr($content, 0, $pos) . $snippet . PHP_EOL . substr($content, $pos);
    } else {
        $content .= PHP_EOL . $snippet . PHP_EOL;
    }

    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("تعذر تحديث الملف: {$file}");
    }

    return true;
}

$css = <<<'CSS'
/* Documents grid action buttons inline fix */
body.documents-page table td.documents-actions-fixed,
body.documents-page table th.documents-actions-fixed {
    min-width: 360px !important;
    width: 360px !important;
    white-space: nowrap !important;
    vertical-align: middle !important;
}

body.documents-page .documents-actions-inline {
    display: inline-flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 8px !important;
    flex-wrap: nowrap !important;
    white-space: nowrap !important;
    width: max-content !important;
    max-width: none !important;
}

body.documents-page .documents-actions-inline > *,
body.documents-page .documents-actions-inline form,
body.documents-page .documents-actions-inline a,
body.documents-page .documents-actions-inline button,
body.documents-page .documents-actions-inline .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 auto !important;
    margin: 0 !important;
    white-space: nowrap !important;
}

body.documents-page .documents-actions-inline button,
body.documents-page .documents-actions-inline .btn,
body.documents-page .documents-actions-inline a {
    min-width: auto !important;
}

body.documents-page .table-responsive,
body.documents-page .documents-table-wrap,
body.documents-page .documents-grid-wrap {
    overflow-x: auto !important;
}

@media print {
    body.documents-page .documents-actions-inline {
        display: none !important;
    }
}
CSS;

$js = <<<'JS'
(function () {
    'use strict';

    function isDocumentsPage() {
        var path = window.location.pathname || '';
        return path === '/documents' || path.indexOf('/documents?') === 0 || path.indexOf('/documents/') === 0;
    }

    function normalizeText(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    function findActionsColumnIndex(table) {
        var headers = table.querySelectorAll('thead th');
        for (var i = 0; i < headers.length; i++) {
            if (normalizeText(headers[i].textContent).indexOf('إجراءات') !== -1) {
                return i;
            }
        }
        return headers.length ? headers.length - 1 : -1;
    }

    function hasActionControls(cell) {
        if (!cell) return false;
        var text = normalizeText(cell.textContent);
        return cell.querySelector('a, button, form, input[type="submit"]') &&
            (text.indexOf('عرض') !== -1 ||
             text.indexOf('تعديل') !== -1 ||
             text.indexOf('حذف') !== -1 ||
             text.indexOf('طباعة') !== -1 ||
             text.indexOf('استعادة') !== -1);
    }

    function wrapCellActions(cell) {
        if (!hasActionControls(cell)) return;
        cell.classList.add('documents-actions-fixed');

        if (cell.querySelector(':scope > .documents-actions-inline')) {
            return;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'documents-actions-inline';

        var children = Array.prototype.slice.call(cell.childNodes);
        children.forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE && !normalizeText(node.textContent)) {
                cell.removeChild(node);
                return;
            }
            wrapper.appendChild(node);
        });

        cell.appendChild(wrapper);
    }

    function applyFix() {
        if (!isDocumentsPage()) return;
        document.body.classList.add('documents-page');

        var tables = document.querySelectorAll('table');
        tables.forEach(function (table) {
            var actionIndex = findActionsColumnIndex(table);
            if (actionIndex < 0) return;

            var headerCells = table.querySelectorAll('thead th');
            if (headerCells[actionIndex]) {
                headerCells[actionIndex].classList.add('documents-actions-fixed');
            }

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var cells = row.children;
                var target = cells[actionIndex] || cells[cells.length - 1];
                wrapCellActions(target);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyFix);
    } else {
        applyFix();
    }
})();
JS;

writeFileOrFail($root . '/public/css/documents-grid-actions-fix.css', $css);
writeFileOrFail($root . '/public/js/documents-grid-actions-fix.js', $js);

$layouts = getLayoutFiles($root);
if (!$layouts) {
    fwrite(STDERR, "ERROR: لم يتم العثور على ملفات layout داخل resources/views/layouts.\n");
    exit(1);
}

$cssSnippet = <<<'BLADE'
<!-- Documents grid actions inline fix:start -->
<link rel="stylesheet" href="{{ asset('css/documents-grid-actions-fix.css') }}">
<!-- Documents grid actions inline fix:end -->
BLADE;

$jsSnippet = <<<'BLADE'
<!-- Documents grid actions inline fix script:start -->
<script src="{{ asset('js/documents-grid-actions-fix.js') }}" defer></script>
<!-- Documents grid actions inline fix script:end -->
BLADE;

$updated = [];
foreach ($layouts as $layout) {
    $changedCss = injectOnce($layout, 'Documents grid actions inline fix:start', $cssSnippet, '</head>');
    $changedJs = injectOnce($layout, 'Documents grid actions inline fix script:start', $jsSnippet, '</body>');
    if ($changedCss || $changedJs) {
        $updated[] = str_replace($root . DIRECTORY_SEPARATOR, '', $layout);
    }
}

echo "OK: تم تركيب إصلاح أزرار جدول الكتب V2.\n";
echo "- تم إنشاء public/css/documents-grid-actions-fix.css\n";
echo "- تم إنشاء public/js/documents-grid-actions-fix.js\n";
if ($updated) {
    echo "- تم تحديث ملفات layout:\n  " . implode("\n  ", $updated) . "\n";
} else {
    echo "- روابط CSS/JS كانت موجودة مسبقاً داخل layout.\n";
}
