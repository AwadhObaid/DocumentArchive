<?php
declare(strict_types=1);

/**
 * QR print position settings update for DocumentArchive.
 * Run from Laravel project root:
 * php scripts/apply_qr_print_position_settings_update.php
 */

function root_path_safe(): string
{
    $cwd = getcwd() ?: __DIR__;
    if (is_file($cwd . DIRECTORY_SEPARATOR . 'artisan')) {
        return $cwd;
    }
    $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if ($candidate && is_file($candidate . DIRECTORY_SEPARATOR . 'artisan')) {
        return $candidate;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel. شغّل السكربت من داخل مجلد المشروع الذي يحتوي ملف artisan.');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function write_file(string $path, string $content): void
{
    ensure_dir(dirname($path));
    if (file_exists($path)) {
        $backup = $path . '.before-qr-position-settings-' . date('Ymd_His') . '.bak';
        copy($path, $backup);
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function append_once(string $path, string $needle, string $append): void
{
    $content = file_exists($path) ? (string) file_get_contents($path) : '';
    if (str_contains($content, $needle)) {
        return;
    }
    if (file_put_contents($path, rtrim($content) . PHP_EOL . PHP_EOL . $append . PHP_EOL) === false) {
        throw new RuntimeException("تعذر تعديل الملف: {$path}");
    }
}

function insert_before_endsection_once(string $path, string $needle, string $insert): void
{
    if (!file_exists($path)) {
        return;
    }
    $content = (string) file_get_contents($path);
    if (str_contains($content, $needle)) {
        return;
    }
    $pos = strripos($content, '@endsection');
    if ($pos !== false) {
        $content = substr($content, 0, $pos) . PHP_EOL . $insert . PHP_EOL . substr($content, $pos);
    } else {
        $content .= PHP_EOL . $insert . PHP_EOL;
    }
    file_put_contents($path, $content);
}

$root = root_path_safe();

$service = <<<'PHP'
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QrPrintLayoutSettings
{
    public const TABLE = 'qr_print_settings';

    public const DEFAULTS = [
        'qr_enabled' => '1',
        'qr_x_mm' => '82',
        'qr_y_mm' => '50',
        'qr_size_mm' => '20',
        'qr_label_enabled' => '1',
        'qr_label_text' => 'رمز الوصول الإلكتروني',
        'qr_label_font_size_mm' => '2.6',
        'qr_card_padding_mm' => '2',
        'qr_background_enabled' => '1',
        'qr_show_border' => '1',
    ];

    public static function all(): array
    {
        $settings = self::DEFAULTS;

        try {
            if (Schema::hasTable(self::TABLE)) {
                $rows = DB::table(self::TABLE)->pluck('value', 'key')->toArray();
                foreach ($rows as $key => $value) {
                    if (array_key_exists($key, $settings)) {
                        $settings[$key] = (string) $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep defaults if database is not ready.
        }

        return $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    public static function upsertMany(array $values): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            DB::table(self::TABLE)->updateOrInsert(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public static function cssVariables(): string
    {
        $s = self::all();

        $enabled = ((string) ($s['qr_enabled'] ?? '1')) === '1' ? 'block' : 'none';
        $background = ((string) ($s['qr_background_enabled'] ?? '1')) === '1' ? '#ffffff' : 'transparent';
        $border = ((string) ($s['qr_show_border'] ?? '1')) === '1' ? '1px solid #cbd5e1' : '0';

        return implode("\n", [
            '--da-qr-display: ' . $enabled . ';',
            '--da-qr-x: ' . self::num($s['qr_x_mm'] ?? '82', 82) . 'mm;',
            '--da-qr-y: ' . self::num($s['qr_y_mm'] ?? '50', 50) . 'mm;',
            '--da-qr-size: ' . self::num($s['qr_size_mm'] ?? '20', 20) . 'mm;',
            '--da-qr-padding: ' . self::num($s['qr_card_padding_mm'] ?? '2', 2) . 'mm;',
            '--da-qr-label-font-size: ' . self::num($s['qr_label_font_size_mm'] ?? '2.6', 2.6) . 'mm;',
            '--da-qr-background: ' . $background . ';',
            '--da-qr-border: ' . $border . ';',
        ]);
    }

    public static function num(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }
}
PHP;

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Services\QrPrintLayoutSettings;
use Illuminate\Http\Request;

class QrPrintSettingsController extends Controller
{
    public function edit()
    {
        return view('settings.qr-print-position', [
            'settings' => QrPrintLayoutSettings::all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'qr_enabled' => ['nullable', 'boolean'],
            'qr_x_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'qr_y_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'qr_size_mm' => ['required', 'numeric', 'min:8', 'max:45'],
            'qr_label_enabled' => ['nullable', 'boolean'],
            'qr_label_text' => ['nullable', 'string', 'max:100'],
            'qr_label_font_size_mm' => ['required', 'numeric', 'min:1.5', 'max:6'],
            'qr_card_padding_mm' => ['required', 'numeric', 'min:0', 'max:8'],
            'qr_background_enabled' => ['nullable', 'boolean'],
            'qr_show_border' => ['nullable', 'boolean'],
        ], [], [
            'qr_x_mm' => 'المسافة من يسار الورقة',
            'qr_y_mm' => 'المسافة من أعلى الورقة',
            'qr_size_mm' => 'حجم رمز QR',
            'qr_label_text' => 'نص أسفل الرمز',
        ]);

        $data['qr_enabled'] = $request->boolean('qr_enabled') ? '1' : '0';
        $data['qr_label_enabled'] = $request->boolean('qr_label_enabled') ? '1' : '0';
        $data['qr_background_enabled'] = $request->boolean('qr_background_enabled') ? '1' : '0';
        $data['qr_show_border'] = $request->boolean('qr_show_border') ? '1' : '0';
        $data['qr_label_text'] = $data['qr_label_text'] ?: 'رمز الوصول الإلكتروني';

        QrPrintLayoutSettings::upsertMany($data);

        return redirect()
            ->route('settings.qr-print-position')
            ->with('success', 'تم حفظ موضع رمز QR بنجاح.');
    }
}
PHP;

$migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('qr_print_settings')) {
            Schema::create('qr_print_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $defaults = [
            'qr_enabled' => '1',
            'qr_x_mm' => '82',
            'qr_y_mm' => '50',
            'qr_size_mm' => '20',
            'qr_label_enabled' => '1',
            'qr_label_text' => 'رمز الوصول الإلكتروني',
            'qr_label_font_size_mm' => '2.6',
            'qr_card_padding_mm' => '2',
            'qr_background_enabled' => '1',
            'qr_show_border' => '1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('qr_print_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Keep settings table by default to avoid losing print calibration.
        // Schema::dropIfExists('qr_print_settings');
    }
};
PHP;

$settingsView = <<<'BLADE'
@extends('layouts.app')

@section('title', 'ضبط موضع رمز QR')

@section('content')
@php
    $s = $settings;
@endphp

<style>
.qr-settings-grid {
    display: grid;
    grid-template-columns: minmax(280px, 420px) 1fr;
    gap: 18px;
    align-items: start;
}
.qr-settings-card {
    background: var(--card-bg, #111827);
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 18px;
    padding: 18px;
    color: var(--text-color, #e5e7eb);
}
.qr-settings-card h2, .qr-settings-card h3 {
    margin: 0 0 14px;
}
.qr-field {
    margin-bottom: 13px;
}
.qr-field label {
    display: block;
    font-weight: 700;
    margin-bottom: 6px;
}
.qr-field input[type="number"],
.qr-field input[type="text"] {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, .35);
    border-radius: 10px;
    padding: 10px 12px;
    background: rgba(15, 23, 42, .75);
    color: #fff;
}
.qr-field small {
    display: block;
    opacity: .75;
    margin-top: 4px;
}
.qr-checks {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.qr-checks label {
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 12px;
    padding: 10px;
}
.qr-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.qr-preview-wrap {
    overflow: auto;
}
.qr-preview-paper {
    position: relative;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    aspect-ratio: 210 / 297;
    background: #fff;
    color: #111827;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 35px rgba(0,0,0,.25);
    transform-origin: top right;
}
.qr-preview-reference {
    position: absolute;
    top: 48mm;
    right: 63mm;
    font-weight: 800;
    font-size: 14px;
    line-height: 1.8;
    direction: rtl;
}
.qr-preview-box {
    position: absolute;
    left: calc(var(--preview-x, 82) * 1mm);
    top: calc(var(--preview-y, 50) * 1mm);
    width: calc((var(--preview-size, 20) + (var(--preview-padding, 2) * 2)) * 1mm);
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: calc(var(--preview-padding, 2) * 1mm);
    text-align: center;
    box-sizing: border-box;
}
.qr-preview-code {
    width: calc(var(--preview-size, 20) * 1mm);
    height: calc(var(--preview-size, 20) * 1mm);
    margin: auto;
    background:
        linear-gradient(90deg,#111 50%,transparent 50%) 0 0 / 4px 4px,
        linear-gradient(#111 50%,transparent 50%) 0 0 / 6px 6px,
        #fff;
    image-rendering: pixelated;
}
.qr-preview-label {
    font-size: calc(var(--preview-label-size, 2.6) * 1mm);
    margin-top: 1.2mm;
    color: #64748b;
    white-space: nowrap;
}
@media (max-width: 900px) {
    .qr-settings-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
    <h1>🎯 ضبط موضع رمز QR في طباعة رقم الكتاب</h1>
    <p>استخدم القيم بالملليمتر لضبط موضع الرمز بدقة داخل ورقة A4.</p>
</div>

<div class="qr-settings-grid">
    <form class="qr-settings-card" method="POST" action="{{ route('settings.qr-print-position.update') }}">
        @csrf

        <h2>إعدادات الموضع</h2>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="qr-checks">
            <label>
                <input type="checkbox" name="qr_enabled" value="1" @checked(($s['qr_enabled'] ?? '1') === '1')>
                إظهار QR في الطباعة
            </label>
            <label>
                <input type="checkbox" name="qr_label_enabled" value="1" @checked(($s['qr_label_enabled'] ?? '1') === '1')>
                إظهار النص أسفل QR
            </label>
            <label>
                <input type="checkbox" name="qr_background_enabled" value="1" @checked(($s['qr_background_enabled'] ?? '1') === '1')>
                خلفية بيضاء للرمز
            </label>
            <label>
                <input type="checkbox" name="qr_show_border" value="1" @checked(($s['qr_show_border'] ?? '1') === '1')>
                إطار حول الرمز
            </label>
        </div>

        <div class="qr-field">
            <label>المسافة من يسار الورقة X / mm</label>
            <input class="js-qr-preview" data-var="x" type="number" step="0.5" min="0" max="210" name="qr_x_mm" value="{{ old('qr_x_mm', $s['qr_x_mm'] ?? 82) }}">
            <small>كلما زادت القيمة تحرك الرمز إلى اليمين.</small>
        </div>

        <div class="qr-field">
            <label>المسافة من أعلى الورقة Y / mm</label>
            <input class="js-qr-preview" data-var="y" type="number" step="0.5" min="0" max="297" name="qr_y_mm" value="{{ old('qr_y_mm', $s['qr_y_mm'] ?? 50) }}">
            <small>كلما زادت القيمة نزل الرمز إلى الأسفل.</small>
        </div>

        <div class="qr-field">
            <label>حجم رمز QR / mm</label>
            <input class="js-qr-preview" data-var="size" type="number" step="0.5" min="8" max="45" name="qr_size_mm" value="{{ old('qr_size_mm', $s['qr_size_mm'] ?? 20) }}">
        </div>

        <div class="qr-field">
            <label>هوامش البطاقة حول الرمز / mm</label>
            <input class="js-qr-preview" data-var="padding" type="number" step="0.5" min="0" max="8" name="qr_card_padding_mm" value="{{ old('qr_card_padding_mm', $s['qr_card_padding_mm'] ?? 2) }}">
        </div>

        <div class="qr-field">
            <label>نص أسفل الرمز</label>
            <input type="text" name="qr_label_text" value="{{ old('qr_label_text', $s['qr_label_text'] ?? 'رمز الوصول الإلكتروني') }}">
        </div>

        <div class="qr-field">
            <label>حجم نص أسفل الرمز / mm</label>
            <input class="js-qr-preview" data-var="label-size" type="number" step="0.1" min="1.5" max="6" name="qr_label_font_size_mm" value="{{ old('qr_label_font_size_mm', $s['qr_label_font_size_mm'] ?? 2.6) }}">
        </div>

        <div class="qr-actions">
            <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            <a href="{{ route('settings.index') }}" class="btn btn-secondary">رجوع للإعدادات</a>
        </div>
    </form>

    <div class="qr-settings-card qr-preview-wrap">
        <h3>معاينة تقريبية على ورقة A4</h3>
        <div id="qrPreviewPaper" class="qr-preview-paper"
             style="--preview-x: {{ $s['qr_x_mm'] ?? 82 }}; --preview-y: {{ $s['qr_y_mm'] ?? 50 }}; --preview-size: {{ $s['qr_size_mm'] ?? 20 }}; --preview-padding: {{ $s['qr_card_padding_mm'] ?? 2 }}; --preview-label-size: {{ $s['qr_label_font_size_mm'] ?? 2.6 }};">
            <div class="qr-preview-reference">
                <div>الشحن والتأمين</div>
                <div>رقم الكتاب : 251230004</div>
                <div>تاريخ الكتاب : 28/06/2026</div>
            </div>
            <div class="qr-preview-box">
                <div class="qr-preview-code"></div>
                <div class="qr-preview-label">رمز الوصول الإلكتروني</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const paper = document.getElementById('qrPreviewPaper');
    document.querySelectorAll('.js-qr-preview').forEach(function (input) {
        const update = function () {
            paper.style.setProperty('--preview-' + input.dataset.var, input.value || 0);
        };
        input.addEventListener('input', update);
        update();
    });
})();
</script>
@endsection
BLADE;

$printCss = <<<'CSS'
/*
 * QR print calibration.
 * Values come from qr_print_settings through inline CSS variables in print-reference.blade.php.
 */
@media screen, print {
    .print-sheet,
    .print-page,
    .a4-page,
    .paper,
    .page,
    .document-print-page,
    .da-print-page {
        position: relative !important;
    }

    .da-qr-positioned-card,
    .document-print-qr-card,
    .document-qr-print-card,
    .document-qr-card,
    .document-print-qr,
    .qr-print-card,
    .qr-print-wrapper,
    .qr-access-code {
        display: var(--da-qr-display, block) !important;
        position: absolute !important;
        left: var(--da-qr-x, 82mm) !important;
        top: var(--da-qr-y, 50mm) !important;
        width: calc(var(--da-qr-size, 20mm) + (var(--da-qr-padding, 2mm) * 2)) !important;
        min-width: auto !important;
        max-width: none !important;
        padding: var(--da-qr-padding, 2mm) !important;
        margin: 0 !important;
        box-sizing: border-box !important;
        background: var(--da-qr-background, #fff) !important;
        border: var(--da-qr-border, 1px solid #cbd5e1) !important;
        border-radius: 2mm !important;
        text-align: center !important;
        z-index: 50 !important;
        transform: none !important;
    }

    .da-qr-positioned-card img,
    .da-qr-positioned-card svg,
    .document-print-qr-card img,
    .document-print-qr-card svg,
    .document-qr-print-card img,
    .document-qr-print-card svg,
    .document-qr-card img,
    .document-qr-card svg,
    .document-print-qr img,
    .document-print-qr svg,
    .qr-print-card img,
    .qr-print-card svg,
    .qr-print-wrapper img,
    .qr-print-wrapper svg,
    .qr-access-code img,
    .qr-access-code svg,
    img[src*="/qr.svg"],
    img[src*="/qr"] {
        width: var(--da-qr-size, 20mm) !important;
        height: var(--da-qr-size, 20mm) !important;
        display: block !important;
        margin: 0 auto !important;
    }

    .da-qr-positioned-card small,
    .da-qr-positioned-card .qr-label,
    .document-print-qr-card small,
    .document-print-qr-card .qr-label,
    .document-qr-print-card small,
    .document-qr-print-card .qr-label,
    .document-qr-card small,
    .document-qr-card .qr-label,
    .document-print-qr small,
    .document-print-qr .qr-label,
    .qr-print-card small,
    .qr-print-card .qr-label,
    .qr-print-wrapper small,
    .qr-print-wrapper .qr-label,
    .qr-access-code small,
    .qr-access-code .qr-label {
        font-size: var(--da-qr-label-font-size, 2.6mm) !important;
        line-height: 1.2 !important;
        margin-top: 1mm !important;
        color: #64748b !important;
        white-space: nowrap !important;
        display: block !important;
    }
}
CSS;

write_file($root . '/app/Services/QrPrintLayoutSettings.php', $service);
write_file($root . '/app/Http/Controllers/QrPrintSettingsController.php', $controller);
write_file($root . '/resources/views/settings/qr-print-position.blade.php', $settingsView);
write_file($root . '/public/css/document-qr-print-calibration.css', $printCss);

$migrationPath = $root . '/database/migrations/2026_06_28_000060_create_qr_print_settings_table.php';
if (!file_exists($migrationPath)) {
    write_file($migrationPath, $migration);
}

$web = $root . '/routes/web.php';
if (file_exists($web)) {
    $webContent = (string) file_get_contents($web);

    if (!str_contains($webContent, 'QrPrintSettingsController')) {
        $webContent = preg_replace('/<\?php\s*/', "<?php\n\nuse App\\Http\\Controllers\\QrPrintSettingsController;\n", $webContent, 1);
    }

    if (!str_contains($webContent, "settings.qr-print-position")) {
        $routes = <<<'PHP'

Route::middleware(['auth'])->group(function () {
    Route::get('/settings/qr-print-position', [QrPrintSettingsController::class, 'edit'])->name('settings.qr-print-position');
    Route::post('/settings/qr-print-position', [QrPrintSettingsController::class, 'update'])->name('settings.qr-print-position.update');
});
PHP;
        $webContent .= PHP_EOL . $routes . PHP_EOL;
    }

    file_put_contents($web, $webContent);
}

$printViewCandidates = [
    $root . '/resources/views/documents/print-reference.blade.php',
    $root . '/resources/views/documents/print_reference.blade.php',
    $root . '/resources/views/documents/print.blade.php',
];

$printHook = <<<'BLADE'
{{-- QR print position calibration --}}
@php($qrPrintSettings = \App\Services\QrPrintLayoutSettings::all())
<link rel="stylesheet" href="{{ asset('css/document-qr-print-calibration.css') }}">
<style>
:root {
{!! \App\Services\QrPrintLayoutSettings::cssVariables() !!}
}
@media screen, print {
    .da-qr-positioned-card,
    .document-print-qr-card,
    .document-qr-print-card,
    .document-qr-card,
    .document-print-qr,
    .qr-print-card,
    .qr-print-wrapper,
    .qr-access-code {
        display: {{ ($qrPrintSettings['qr_enabled'] ?? '1') === '1' ? 'block' : 'none' }} !important;
    }
    .da-qr-positioned-card small,
    .da-qr-positioned-card .qr-label,
    .document-print-qr-card small,
    .document-print-qr-card .qr-label,
    .document-qr-print-card small,
    .document-qr-print-card .qr-label,
    .document-qr-card small,
    .document-qr-card .qr-label,
    .document-print-qr small,
    .document-print-qr .qr-label,
    .qr-print-card small,
    .qr-print-card .qr-label,
    .qr-print-wrapper small,
    .qr-print-wrapper .qr-label,
    .qr-access-code small,
    .qr-access-code .qr-label {
        display: {{ ($qrPrintSettings['qr_label_enabled'] ?? '1') === '1' ? 'block' : 'none' }} !important;
    }
}
</style>
BLADE;

foreach ($printViewCandidates as $candidate) {
    if (file_exists($candidate)) {
        append_once($candidate, 'document-qr-print-calibration.css', $printHook);
    }
}

$settingsIndex = $root . '/resources/views/settings/index.blade.php';
$settingsLink = <<<'BLADE'
<div class="card" style="margin-top: 16px;">
    <div class="card-body">
        <h3>🎯 ضبط موضع رمز QR</h3>
        <p>تحديد موضع رمز الوصول الإلكتروني في صفحة طباعة رقم الكتاب بالملليمتر.</p>
        <a class="btn btn-primary" href="{{ route('settings.qr-print-position') }}">فتح إعدادات موضع QR</a>
    </div>
</div>
BLADE;
insert_before_endsection_once($settingsIndex, 'settings.qr-print-position', $settingsLink);

echo "DONE: تم إضافة إعدادات موضع QR للطباعة.\n";
echo "NEXT: php artisan migrate && php artisan route:clear && php artisan view:clear && php artisan optimize:clear\n";
