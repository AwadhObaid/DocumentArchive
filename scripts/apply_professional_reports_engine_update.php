<?php

$root = dirname(__DIR__);

function source_path(string $relative): string
{
    return __DIR__ . '/../' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function target_path(string $relative): string
{
    global $root;
    return $root . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function copy_update_file(string $relative): void
{
    $source = source_path($relative);
    $target = target_path($relative);

    if (! file_exists($source)) {
        throw new RuntimeException("ملف المصدر غير موجود: {$relative}");
    }

    $dir = dirname($target);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    copy($source, $target);
    echo "OK: تم تحديث {$relative}\n";
}

try {
    copy_update_file('app/Http/Controllers/ReportPrintController.php');
    copy_update_file('resources/views/reports/print.blade.php');
    copy_update_file('resources/views/reports/partials/print-summary-table.blade.php');

    $routesPath = target_path('routes/web.php');
    if (! file_exists($routesPath)) {
        throw new RuntimeException('لم يتم العثور على routes/web.php');
    }

    $routes = file_get_contents($routesPath);
    if (strpos($routes, "reports.print") === false && strpos($routes, "ReportPrintController") === false) {
        $routeLine = "\n// Professional printable documents report\nRoute::get('/reports/print', [\\App\\Http\\Controllers\\ReportPrintController::class, 'index'])->middleware(['auth'])->name('reports.print');\n";
        $routes .= $routeLine;
        file_put_contents($routesPath, $routes);
        echo "OK: تمت إضافة مسار التقرير الرسمي /reports/print\n";
    } else {
        echo "SKIP: مسار reports.print موجود مسبقاً.\n";
    }

    $reportsIndex = target_path('resources/views/reports/index.blade.php');
    if (file_exists($reportsIndex)) {
        $view = file_get_contents($reportsIndex);

        if (strpos($view, "reports.print") === false) {
            $officialButton = '<a href="{{ route(\'reports.print\', request()->query()) }}" target="_blank" class="btn btn-secondary">🖨️ تقرير رسمي منسق</a>';

            $oldButton = '<button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ طباعة التقرير</button>';
            if (strpos($view, $oldButton) !== false) {
                $view = str_replace($oldButton, $officialButton, $view);
            } elseif (strpos($view, 'class="page-actions no-print"') !== false) {
                $view = preg_replace('/(<div[^>]*class="page-actions no-print"[^>]*>)/', '$1' . "\n        " . $officialButton, $view, 1);
            } else {
                $view = "<div class=\"no-print\" style=\"margin:10px 0;\">{$officialButton}</div>\n" . $view;
            }

            file_put_contents($reportsIndex, $view);
            echo "OK: تمت إضافة زر التقرير الرسمي المنسق إلى صفحة التقارير.\n";
        } else {
            echo "SKIP: زر التقرير الرسمي موجود مسبقاً في صفحة التقارير.\n";
        }
    } else {
        echo "WARN: لم يتم العثور على resources/views/reports/index.blade.php. يمكنك فتح التقرير مباشرة من /reports/print\n";
    }

    echo "\nتم تركيب محرك التقرير الرسمي للكتب والمرفقات بنجاح.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
