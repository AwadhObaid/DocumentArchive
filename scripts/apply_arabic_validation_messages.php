<?php

$root = dirname(__DIR__);
$projectRoot = getcwd();

function copy_file_with_backup(string $source, string $target): void
{
    if (!is_file($source)) {
        throw new RuntimeException("Source not found: {$source}");
    }

    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (is_file($target)) {
        $backup = $target . '.bak_arabic_validation_' . date('Ymd_His');
        copy($target, $backup);
    }

    copy($source, $target);
}

function inject_once(string $file, string $needle, string $insert, string $marker): void
{
    if (!is_file($file)) {
        throw new RuntimeException("File not found: {$file}");
    }

    $content = file_get_contents($file);
    if (str_contains($content, $marker)) {
        return;
    }

    $pos = stripos($content, $needle);
    if ($pos === false) {
        throw new RuntimeException("Needle '{$needle}' not found in {$file}");
    }

    $content = substr_replace($content, $insert . PHP_EOL, $pos, 0);
    copy($file, $file . '.bak_arabic_validation_' . date('Ymd_His'));
    file_put_contents($file, $content);
}

try {
    copy_file_with_backup($root . '/public/js/arabic-form-validation.js', $projectRoot . '/public/js/arabic-form-validation.js');
    copy_file_with_backup($root . '/public/css/arabic-validation.css', $projectRoot . '/public/css/arabic-validation.css');

    if (!is_dir($projectRoot . '/lang/ar')) {
        mkdir($projectRoot . '/lang/ar', 0777, true);
    }
    if (!is_file($projectRoot . '/lang/ar/validation.php')) {
        copy($root . '/lang/ar/validation.php', $projectRoot . '/lang/ar/validation.php');
    }

    $layoutCandidates = [
        $projectRoot . '/resources/views/layouts/app.blade.php',
        $projectRoot . '/resources/views/layouts/admin.blade.php',
        $projectRoot . '/resources/views/app.blade.php',
    ];

    $updatedAny = false;
    foreach ($layoutCandidates as $layout) {
        if (!is_file($layout)) {
            continue;
        }

        $content = file_get_contents($layout);

        if (!str_contains($content, 'arabic-validation.css')) {
            $css = "    {{-- Arabic browser/form validation messages --}}\n    <link rel=\"stylesheet\" href=\"{{ asset('css/arabic-validation.css') }}\">";
            if (stripos($content, '</head>') !== false) {
                $content = str_ireplace('</head>', $css . PHP_EOL . '</head>', $content);
            } else {
                $content = $css . PHP_EOL . $content;
            }
        }

        if (!str_contains($content, 'arabic-form-validation.js')) {
            $js = "    {{-- Arabic browser/form validation messages --}}\n    <script src=\"{{ asset('js/arabic-form-validation.js') }}\" defer></script>";
            if (stripos($content, '</body>') !== false) {
                $content = str_ireplace('</body>', $js . PHP_EOL . '</body>', $content);
            } else {
                $content .= PHP_EOL . $js . PHP_EOL;
            }
        }

        copy($layout, $layout . '.bak_arabic_validation_' . date('Ymd_His'));
        file_put_contents($layout, $content);
        $updatedAny = true;
    }

    if (!$updatedAny) {
        throw new RuntimeException('لم يتم العثور على ملف layout مناسب لإضافة ملفات JS/CSS.');
    }

    echo "OK: تم تركيب رسائل التحقق العربية بنجاح.\n";
    echo "- public/js/arabic-form-validation.js\n";
    echo "- public/css/arabic-validation.css\n";
    echo "- lang/ar/validation.php عند عدم وجوده مسبقاً\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
