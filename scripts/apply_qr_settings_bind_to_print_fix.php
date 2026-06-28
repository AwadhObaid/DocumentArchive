<?php
/**
 * Bind QR print-position settings to document print page.
 * Run from Laravel project root:
 *   php scripts/apply_qr_settings_bind_to_print_fix.php
 */

$root = getcwd();
if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من جذر مشروع Laravel حيث يوجد ملف artisan.\n");
    exit(1);
}

function normalize_path(string $path): string {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function backup_file(string $root, string $file): void {
    $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
    $backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'qr-settings-bind-' . date('Ymd_His');
    $dest = $backupDir . DIRECTORY_SEPARATOR . $relative;
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0777, true);
    }
    copy($file, $dest);
}

function read_file(string $file): string {
    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException("تعذر قراءة الملف: {$file}");
    }
    return $content;
}

function write_file(string $file, string $content): void {
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$file}");
    }
}

$viewsRoot = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
if (!is_dir($viewsRoot)) {
    fwrite(STDERR, "ERROR: مجلد resources/views غير موجود.\n");
    exit(1);
}

// Locate likely document print views.
$candidates = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || !str_ends_with($fileInfo->getFilename(), '.blade.php')) {
        continue;
    }
    $file = $fileInfo->getPathname();
    $relative = str_replace($viewsRoot . DIRECTORY_SEPARATOR, '', $file);
    $content = read_file($file);

    $isPrintNamed = preg_match('/(^|[\\\/])(documents|document)[\\\/].*print.*\.blade\.php$/i', $relative)
        || preg_match('/(^|[\\\/]).*print.*document.*\.blade\.php$/i', $relative)
        || preg_match('/(^|[\\\/]).*reference.*\.blade\.php$/i', $relative);

    $hasDocumentPrintText = str_contains($content, 'رقم الكتاب') && str_contains($content, 'تاريخ الكتاب');
    $hasQrOrAccess = str_contains($content, 'رمز الوصول') || str_contains(strtolower($content), 'qr.svg') || str_contains(strtolower($content), 'documents.qr');

    if (($isPrintNamed && $hasDocumentPrintText) || ($hasDocumentPrintText && $hasQrOrAccess)) {
        $candidates[] = $file;
    }
}

if (empty($candidates)) {
    fwrite(STDERR, "ERROR: لم يتم العثور على صفحة طباعة رقم الكتاب. ابحث عن ملف Blade الذي يحتوي رقم الكتاب/تاريخ الكتاب.\n");
    exit(1);
}

$phpBlock = <<<'BLADE'
{{-- QR_PRINT_SETTINGS_BIND_START --}}
@php
    try {
        $daQrSettings = \Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')
            ? \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray()
            : [];
    } catch (\Throwable $e) {
        $daQrSettings = [];
    }

    $daQrSetting = function (string $key, $default = null) use ($daQrSettings) {
        return array_key_exists($key, $daQrSettings) ? $daQrSettings[$key] : $default;
    };

    $daQrEnabledRaw = $daQrSetting('enabled', $daQrSetting('show_qr', '1'));
    $daQrEnabled = filter_var($daQrEnabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $daQrEnabled = $daQrEnabled === null ? ((string) $daQrEnabledRaw !== '0') : $daQrEnabled;

    $daQrShowLabelRaw = $daQrSetting('show_label', '1');
    $daQrShowLabel = filter_var($daQrShowLabelRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $daQrShowLabel = $daQrShowLabel === null ? ((string) $daQrShowLabelRaw !== '0') : $daQrShowLabel;

    $daQrX = (float) $daQrSetting('x_mm', $daQrSetting('qr_x_mm', '74'));
    $daQrY = (float) $daQrSetting('y_mm', $daQrSetting('qr_y_mm', '48'));
    $daQrSize = (float) $daQrSetting('size_mm', $daQrSetting('qr_size_mm', '18'));
    $daQrPadding = (float) $daQrSetting('padding_mm', $daQrSetting('qr_padding_mm', '1.5'));
    $daQrLabelSize = (float) $daQrSetting('label_font_size_pt', $daQrSetting('qr_label_font_size_pt', '6'));
    $daQrDocument = $document ?? $book ?? null;
@endphp
<style id="da-qr-print-settings-style">
    @media screen, print {
        .print-page,
        .print-paper,
        .paper,
        .a4-page,
        .a4-sheet,
        .document-print-page,
        .document-print-paper,
        .page {
            position: relative !important;
        }

        .document-qr-card,
        .document-qr-print-card,
        .document-qr-side,
        .document-qr-print-side,
        .qr-print-block,
        .qr-print-box:not(.da-qr-from-settings),
        .da-qr-positioned:not(.da-qr-from-settings),
        .da-qr-precise-side:not(.da-qr-from-settings) {
            display: none !important;
        }

        .da-qr-from-settings {
            position: absolute !important;
            left: {{ rtrim(rtrim(number_format($daQrX, 2, '.', ''), '0'), '.') }}mm !important;
            top: {{ rtrim(rtrim(number_format($daQrY, 2, '.', ''), '0'), '.') }}mm !important;
            width: {{ rtrim(rtrim(number_format($daQrSize + ($daQrPadding * 2), 2, '.', ''), '0'), '.') }}mm !important;
            min-height: {{ rtrim(rtrim(number_format($daQrSize + ($daQrPadding * 2), 2, '.', ''), '0'), '.') }}mm !important;
            box-sizing: border-box !important;
            padding: {{ rtrim(rtrim(number_format($daQrPadding, 2, '.', ''), '0'), '.') }}mm !important;
            background: #fff !important;
            border: 0.25mm solid #d5dbe7 !important;
            border-radius: 2mm !important;
            z-index: 50 !important;
            text-align: center !important;
            direction: rtl !important;
            line-height: 1.1 !important;
            box-shadow: none !important;
        }

        .da-qr-from-settings img,
        .da-qr-from-settings svg {
            display: block !important;
            width: {{ rtrim(rtrim(number_format($daQrSize, 2, '.', ''), '0'), '.') }}mm !important;
            height: {{ rtrim(rtrim(number_format($daQrSize, 2, '.', ''), '0'), '.') }}mm !important;
            max-width: none !important;
            max-height: none !important;
            margin: 0 auto !important;
        }

        .da-qr-from-settings-label {
            display: block !important;
            margin-top: 1mm !important;
            font-size: {{ rtrim(rtrim(number_format($daQrLabelSize, 2, '.', ''), '0'), '.') }}pt !important;
            color: #4b5563 !important;
            white-space: nowrap !important;
        }
    }
</style>
{{-- QR_PRINT_SETTINGS_BIND_END --}}
BLADE;

$qrMarkup = <<<'BLADE'
{{-- QR_PRINT_SETTINGS_MARKUP_START --}}
@if($daQrEnabled && $daQrDocument)
    <div class="da-qr-from-settings">
        <img src="{{ url('/documents/' . $daQrDocument->id . '/qr.svg') }}" alt="QR Code">
        @if($daQrShowLabel)
            <span class="da-qr-from-settings-label">رمز الوصول الإلكتروني</span>
        @endif
    </div>
@endif
{{-- QR_PRINT_SETTINGS_MARKUP_END --}}
BLADE;

$patched = 0;
foreach ($candidates as $file) {
    $content = read_file($file);
    backup_file($root, $file);

    // Remove our previous versions.
    $content = preg_replace('/\s*\{\{-- QR_PRINT_SETTINGS_BIND_START --\}\}.*?\{\{-- QR_PRINT_SETTINGS_BIND_END --\}\}\s*/s', "\n", $content);
    $content = preg_replace('/\s*\{\{-- QR_PRINT_SETTINGS_MARKUP_START --\}\}.*?\{\{-- QR_PRINT_SETTINGS_MARKUP_END --\}\}\s*/s', "\n", $content);

    // Insert settings block before first style/head/body content.
    if (str_contains($content, '<head')) {
        $content = preg_replace('/(<head[^>]*>)/i', "$1\n" . $phpBlock . "\n", $content, 1);
    } else {
        $content = $phpBlock . "\n" . $content;
    }

    // Insert QR markup inside the first likely A4/print page container.
    $containerPattern = '/(<div\s+[^>]*class=["\'][^"\']*(?:print-page|print-paper|a4-page|a4-sheet|document-print-page|document-print-paper|paper|page)[^"\']*["\'][^>]*>)/i';
    if (preg_match($containerPattern, $content)) {
        $content = preg_replace($containerPattern, "$1\n" . $qrMarkup . "\n", $content, 1);
    } elseif (str_contains($content, '<body')) {
        $content = preg_replace('/(<body[^>]*>)/i', "$1\n" . $qrMarkup . "\n", $content, 1);
    } else {
        $content .= "\n" . $qrMarkup . "\n";
    }

    // Make settings back route safe if present in QR settings page too? No-op for print page.
    write_file($file, $content);
    $patched++;
}

// Fix QR settings page back link if settings.index is missing.
$qrSettingsView = $viewsRoot . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'qr-print-position.blade.php';
if (file_exists($qrSettingsView)) {
    $content = read_file($qrSettingsView);
    if (str_contains($content, "route('settings.index')") || str_contains($content, 'route("settings.index")')) {
        backup_file($root, $qrSettingsView);
        $content = str_replace(["route('settings.index')", 'route("settings.index")'], "url('/settings')", $content);
        write_file($qrSettingsView, $content);
    }
}

echo "DONE: تم ربط إعدادات موضع QR بصفحة طباعة رقم الكتاب. الملفات المعدلة: {$patched}\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";
