<?php
/**
 * Robust QR print settings binder V2
 * Run from Laravel project root: php scripts/apply_qr_settings_bind_to_print_v2_fix.php
 */

function project_root(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan') && is_dir($dir . DIRECTORY_SEPARATOR . 'resources')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    fwrite(STDERR, "ERROR: تأكد أنك داخل جذر مشروع Laravel حيث يوجد ملف artisan.\n");
    exit(1);
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

function remove_backup_dirs(string $root): void
{
    foreach (['app', 'resources', 'routes'] as $rel) {
        $base = $root . DIRECTORY_SEPARATOR . $rel;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
                rrmdir($file->getPathname());
            }
        }
    }
}

function blade_files(string $dir): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function remove_marked_blocks(string $content): string
{
    $markers = [
        ['<!-- DA_QR_PRINT_SETTINGS_BLOCK_START -->', '<!-- DA_QR_PRINT_SETTINGS_BLOCK_END -->'],
        ['<!-- DA_QR_SETTINGS_BIND_START -->', '<!-- DA_QR_SETTINGS_BIND_END -->'],
        ['<!-- DA_DOCUMENT_QR_PRINT_START -->', '<!-- DA_DOCUMENT_QR_PRINT_END -->'],
        ['<!-- DA_QR_PRECISE_SIDE_START -->', '<!-- DA_QR_PRECISE_SIDE_END -->'],
        ['<!-- DA_QR_SIDE_POSITION_START -->', '<!-- DA_QR_SIDE_POSITION_END -->'],
        ['<!-- DA_QR_PRINT_POSITION_START -->', '<!-- DA_QR_PRINT_POSITION_END -->'],
    ];
    foreach ($markers as [$start, $end]) {
        while (($s = strpos($content, $start)) !== false && ($e = strpos($content, $end, $s)) !== false) {
            $content = substr($content, 0, $s) . substr($content, $e + strlen($end));
        }
    }
    return $content;
}

function is_print_reference_view(string $content, string $path): bool
{
    $name = str_replace('\\', '/', $path);
    if (str_contains($content, 'رقم الكتاب') && str_contains($content, 'تاريخ الكتاب')) return true;
    if (str_contains($name, '/documents/') && (str_contains($name, 'print') || str_contains($name, 'reference'))) return true;
    return false;
}

$root = project_root();
remove_backup_dirs($root);

$viewsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
if (!is_dir($viewsDir)) {
    fwrite(STDERR, "ERROR: مجلد resources/views غير موجود.\n");
    exit(1);
}

$targetFiles = [];
foreach (blade_files($viewsDir) as $file) {
    $content = file_get_contents($file);
    if ($content !== false && is_print_reference_view($content, $file)) {
        $targetFiles[] = $file;
    }
}

if (!$targetFiles) {
    fwrite(STDERR, "ERROR: لم أجد ملف طباعة رقم الكتاب. ابحث عن ملف يحتوي: رقم الكتاب / تاريخ الكتاب.\n");
    exit(1);
}

$block = <<<'BLADE'

<!-- DA_QR_SETTINGS_BIND_V2_START -->
@php
    $daQrDocument = $document ?? $book ?? $currentDocument ?? null;
    $daQrSettings = [
        'qr_print_show' => '1',
        'qr_print_show_label' => '1',
        'qr_print_x_mm' => '72',
        'qr_print_y_mm' => '50',
        'qr_print_size_mm' => '16',
        'qr_print_padding_mm' => '1',
        'qr_print_label_font_size_pt' => '6',
        'qr_print_label' => 'رمز الوصول الإلكتروني',
    ];

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')) {
            $daQrRows = \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
            if (is_array($daQrRows)) {
                $daQrSettings = array_merge($daQrSettings, $daQrRows);
            }
        }
    } catch (\Throwable $e) {
        // fallback to defaults
    }

    $daQrVisible = (string)($daQrSettings['qr_print_show'] ?? '1') !== '0';
    $daQrLabelVisible = (string)($daQrSettings['qr_print_show_label'] ?? '1') !== '0';
    $daQrUrl = $daQrDocument ? url('/documents/' . $daQrDocument->id . '/qr.svg') : null;
    $daQrX = (float)($daQrSettings['qr_print_x_mm'] ?? 72);
    $daQrY = (float)($daQrSettings['qr_print_y_mm'] ?? 50);
    $daQrSize = (float)($daQrSettings['qr_print_size_mm'] ?? 16);
    $daQrPadding = (float)($daQrSettings['qr_print_padding_mm'] ?? 1);
    $daQrLabelFont = (float)($daQrSettings['qr_print_label_font_size_pt'] ?? 6);
    $daQrLabel = trim((string)($daQrSettings['qr_print_label'] ?? 'رمز الوصول الإلكتروني'));
@endphp

@if($daQrVisible && $daQrUrl)
<style>
    .da-qr-settings-v2-box {
        position: absolute !important;
        left: {{ $daQrX }}mm !important;
        top: {{ $daQrY }}mm !important;
        width: {{ $daQrSize + ($daQrPadding * 2) }}mm !important;
        min-height: {{ $daQrSize + ($daQrPadding * 2) }}mm !important;
        padding: {{ $daQrPadding }}mm !important;
        box-sizing: border-box !important;
        background: #fff !important;
        border: 0.25mm solid #d1d5db !important;
        border-radius: 1.5mm !important;
        text-align: center !important;
        z-index: 25 !important;
        direction: rtl !important;
        overflow: visible !important;
    }
    .da-qr-settings-v2-box img {
        width: {{ $daQrSize }}mm !important;
        height: {{ $daQrSize }}mm !important;
        display: block !important;
        margin: 0 auto !important;
    }
    .da-qr-settings-v2-label {
        margin-top: 1mm !important;
        font-size: {{ $daQrLabelFont }}pt !important;
        line-height: 1.1 !important;
        color: #334155 !important;
        white-space: nowrap !important;
        font-weight: 400 !important;
    }
    @media print {
        .da-qr-settings-v2-box {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>
<div id="daQrSettingsV2Box" class="da-qr-settings-v2-box">
    <img src="{{ $daQrUrl }}" alt="QR">
    @if($daQrLabelVisible && $daQrLabel !== '')
        <div class="da-qr-settings-v2-label">{{ $daQrLabel }}</div>
    @endif
</div>
<script>
(function () {
    function mountQrBox() {
        var box = document.getElementById('daQrSettingsV2Box');
        if (!box) return;
        var page = document.querySelector('.a4-page, .print-page, .document-print-page, .paper, .print-sheet, .page, [data-print-page]');
        if (!page) {
            var candidates = Array.prototype.slice.call(document.body.children).filter(function (el) {
                var r = el.getBoundingClientRect();
                return r.width > 500 && r.height > 700;
            });
            page = candidates[0] || document.body;
        }
        page.style.position = 'relative';
        if (box.parentElement !== page) page.appendChild(box);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountQrBox);
    } else {
        mountQrBox();
    }
})();
</script>
@endif
<!-- DA_QR_SETTINGS_BIND_V2_END -->
BLADE;

$changed = [];
foreach ($targetFiles as $file) {
    $content = file_get_contents($file);
    if ($content === false) continue;
    $original = $content;
    $content = remove_marked_blocks($content);

    // Add block before closing body when possible, otherwise append.
    $pos = strripos($content, '</body>');
    if ($pos !== false) {
        $content = substr($content, 0, $pos) . $block . "\n" . substr($content, $pos);
    } else {
        $content .= $block . "\n";
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        $changed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    }
}

// Patch settings view back route if present.
$settingsView = $viewsDir . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'qr-print-position.blade.php';
if (is_file($settingsView)) {
    $c = file_get_contents($settingsView);
    if ($c !== false && str_contains($c, "route('settings.index')")) {
        $c = str_replace("route('settings.index')", "url('/settings')", $c);
        file_put_contents($settingsView, $c);
        $changed[] = 'resources/views/settings/qr-print-position.blade.php';
    }
}

echo "DONE: تم تطبيق ربط إعدادات QR بالطباعة V2 بدون Regex معقد.\n";
echo "FILES:\n- " . implode("\n- ", array_unique($changed)) . "\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";
