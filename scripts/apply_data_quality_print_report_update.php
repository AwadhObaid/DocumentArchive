<?php

$root = dirname(__DIR__);

function put_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
    echo "OK: كتب الملف {$path}\n";
}

function read_source(string $relative): string
{
    $source = __DIR__ . '/../' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (! file_exists($source)) {
        throw new RuntimeException("ملف المصدر غير موجود: {$relative}");
    }
    return file_get_contents($source);
}

try {
    put_file($root . '/app/Http/Controllers/DataQualityPrintController.php', read_source('app/Http/Controllers/DataQualityPrintController.php'));
    put_file($root . '/resources/views/data-quality/print.blade.php', read_source('resources/views/data-quality/print.blade.php'));
    put_file($root . '/resources/views/data-quality/partials/document-table.blade.php', read_source('resources/views/data-quality/partials/document-table.blade.php'));
    put_file($root . '/resources/views/data-quality/partials/duplicate-policy-table.blade.php', read_source('resources/views/data-quality/partials/duplicate-policy-table.blade.php'));

    $routesPath = $root . '/routes/web.php';
    if (! file_exists($routesPath)) {
        throw new RuntimeException('لم يتم العثور على routes/web.php');
    }

    $routes = file_get_contents($routesPath);
    if (strpos($routes, "data-quality.print") === false && strpos($routes, "DataQualityPrintController") === false) {
        $routeLine = "\n// Professional printable data quality report\nRoute::get('/data-quality/print', [\\App\\Http\\Controllers\\DataQualityPrintController::class, 'index'])->middleware(['auth'])->name('data-quality.print');\n";
        $routes .= $routeLine;
        file_put_contents($routesPath, $routes);
        echo "OK: تمت إضافة مسار تقرير جودة البيانات المنسق.\n";
    } else {
        echo "SKIP: مسار تقرير جودة البيانات المنسق موجود مسبقاً.\n";
    }

    $indexPath = $root . '/resources/views/data-quality/index.blade.php';
    if (file_exists($indexPath)) {
        $view = file_get_contents($indexPath);
        if (strpos($view, "data-quality.print") === false) {
            $button = <<<'BLADE'

<div class="dq-print-report-action" style="display:flex;justify-content:flex-start;gap:8px;margin:10px 0 16px;">
    <a href="{{ route('data-quality.print') }}" target="_blank" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
        🖨️ تقرير منسق للطباعة
    </a>
</div>
BLADE;
            if (preg_match('/@section\s*\(\s*[\'\"]content[\'\"]\s*\)/', $view, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1] + strlen($m[0][0]);
                $view = substr($view, 0, $pos) . $button . substr($view, $pos);
            } else {
                $view = $button . "\n" . $view;
            }
            file_put_contents($indexPath, $view);
            echo "OK: تمت إضافة زر التقرير المنسق داخل صفحة جودة البيانات.\n";
        } else {
            echo "SKIP: زر التقرير المنسق موجود مسبقاً في صفحة جودة البيانات.\n";
        }
    } else {
        echo "WARN: لم يتم العثور على resources/views/data-quality/index.blade.php، يمكنك فتح التقرير مباشرة من /data-quality/print\n";
    }

    echo "\nتم تركيب تقرير جودة البيانات المنسق بنجاح.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
