<?php
/**
 * Arabic UI final repair for DocumentArchive.
 * - No mb_convert_encoding
 * - No Windows-1256
 * - Repairs known corrupted Arabic UI fragments in active files and settings table
 * - Adds a strong CSS + JS display guard to stop/fix Arabic ellipsis corruption in browser
 */

$root = realpath(__DIR__ . '/..');
if (!$root) {
    echo "ERROR: Cannot resolve project root.\n";
    exit(1);
}

$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-ui-final-' . $timestamp;
@mkdir($backupRoot, 0777, true);

function norm_path($path) { return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path); }
function rel_path($root, $path) { return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR); }
function backup_file($root, $backupRoot, $path) {
    if (!is_file($path)) return;
    $rel = rel_path($root, $path);
    $dest = $backupRoot . DIRECTORY_SEPARATOR . $rel;
    @mkdir(dirname($dest), 0777, true);
    @copy($path, $dest);
}

function repair_arabic_text($text) {
    if (!is_string($text) || $text === '') return $text;

    // Replacement-character variants: in this project they appeared in Arabic words where م was lost.
    $text = str_replace([
        "ï¿½", "\xEF\xBF\xBD", "&#65533;", "&#xFFFD;", "&amp;#65533;", "&amp;#xFFFD;"
    ], "م", $text);

    // Common literal ellipsis corruptions created by earlier repairs/display values.
    $pairs = [
        'الأرشيفة الإلكتروني' => 'الأرشيف الإلكتروني',
        'الأرشيفة الإلكترونية' => 'الأرشفة الإلكترونية',
        'نظا...' => 'نظام', 'نظا…' => 'نظام',
        'النظا...' => 'النظام', 'النظا…' => 'النظام',
        'فحص النظا...' => 'فحص النظام', 'فحص النظا…' => 'فحص النظام',
        'لوحة التحك...' => 'لوحة التحكم', 'لوحة التحك…' => 'لوحة التحكم',
        'التحك...' => 'التحكم', 'التحك…' => 'التحكم',
        'رق...' => 'رقم', 'رق…' => 'رقم',
        'اس...' => 'اسم', 'اس…' => 'اسم',
        'الاس...' => 'الاسم', 'الاس…' => 'الاسم',
        'أفه...' => 'أفهم', 'أفه…' => 'أفهم',
        'شا...' => 'شامل', 'شا…' => 'شامل',
        'كا...' => 'كامل', 'كا…' => 'كامل',
        'عا...' => 'عام', 'عا…' => 'عام',
        'الع...' => 'العمليات', 'الع…' => 'العمليات',
        'كل الع...' => 'كل العمليات', 'كل الع…' => 'كل العمليات',
        'التأ...ين' => 'التأمين', 'التأ…ين' => 'التأمين',
        'والتأ...ين' => 'والتأمين', 'والتأ…ين' => 'والتأمين',
        'التأظ...ين' => 'التأمين', 'والتأظ...ين' => 'والتأمين',
        'التأط...ين' => 'التأمين', 'والتأط...ين' => 'والتأمين',
        'بقشط...' => 'بقسم', 'بقشط…' => 'بقسم',
        'بقسط...' => 'بقسم', 'بقسط…' => 'بقسم',
        'بقظ...' => 'بقسم', 'بقظ…' => 'بقسم',
        'بقط...' => 'بقسم', 'بقط…' => 'بقسم',
        'الط...لف' => 'الملف', 'الط…لف' => 'الملف',
        'الظ...لف' => 'الملف', 'الظ…لف' => 'الملف',
        'ال...لف' => 'الملف', 'ال…لف' => 'الملف',
        '...لف' => 'ملف', '…لف' => 'ملف',
        'الط...رفقات' => 'المرفقات', 'الط…رفقات' => 'المرفقات',
        'الظ...رفقات' => 'المرفقات', 'الظ…رفقات' => 'المرفقات',
        'ال...رفقات' => 'المرفقات', 'ال…رفقات' => 'المرفقات',
        '...رفقات' => 'مرفقات', '…رفقات' => 'مرفقات',
        'الط...حذوفات' => 'المحذوفات', 'الط…حذوفات' => 'المحذوفات',
        'الظ...حذوفات' => 'المحذوفات', 'الظ…حذوفات' => 'المحذوفات',
        'ال...حذوفات' => 'المحذوفات', 'ال…حذوفات' => 'المحذوفات',
        '...حذوفات' => 'محذوفات', '…حذوفات' => 'محذوفات',
        'الط...ستخدمين' => 'المستخدمين', 'الظ...ستخدمين' => 'المستخدمين', 'ال...ستخدمين' => 'المستخدمين',
        '...ستخدمين' => 'مستخدمين', '...ستخدم' => 'مستخدم',
        'الط...دير' => 'المدير', 'الظ...دير' => 'المدير', 'ال...دير' => 'المدير', '...دير' => 'مدير',
        '...ركز' => 'مركز', '…ركز' => 'مركز',
        '...عاينة' => 'معاينة', '…عاينة' => 'معاينة',
        '...راجعة' => 'مراجعة', '…راجعة' => 'مراجعة',
        '...هم' => 'مهم', '…هم' => 'مهم',
        '...ن' => 'من', '…ن' => 'من',
    ];

    // Prefer longer keys first.
    uksort($pairs, function($a, $b) { return strlen($b) <=> strlen($a); });
    return str_replace(array_keys($pairs), array_values($pairs), $text);
}

function list_text_files($root) {
    $dirs = ['resources/views', 'app', 'routes', 'public/css', 'public/js'];
    $exts = ['php','blade.php','css','js'];
    $files = [];
    foreach ($dirs as $dir) {
        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (!is_dir($full)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $path = $file->getPathname();
            $rel = rel_path($root, $path);
            if (stripos($rel, '_backup') !== false || stripos($rel, 'patch-backups') !== false) continue;
            $lower = strtolower($path);
            if (preg_match('/\.(php|blade\.php|css|js)$/i', $lower)) $files[] = $path;
        }
    }
    return $files;
}

$changed = [];
foreach (list_text_files($root) as $path) {
    $old = @file_get_contents($path);
    if ($old === false) continue;
    $new = repair_arabic_text($old);
    if ($new !== $old) {
        backup_file($root, $backupRoot, $path);
        file_put_contents($path, $new);
        $changed[] = rel_path($root, $path);
    }
}

// Write CSS guard.
$cssPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'arabic-ui-final-fix.css';
$css = <<<CSS
/* Arabic UI final fix: prevent unwanted clipping/ellipsis in Arabic interface text. */
html[dir="rtl"] body,
html[dir="rtl"] body * {
    text-overflow: clip !important;
}

html[dir="rtl"] .sidebar,
html[dir="rtl"] .sidebar *,
html[dir="rtl"] nav,
html[dir="rtl"] nav *,
html[dir="rtl"] header,
html[dir="rtl"] header *,
html[dir="rtl"] .card,
html[dir="rtl"] .card *,
html[dir="rtl"] .btn,
html[dir="rtl"] button,
html[dir="rtl"] .alert,
html[dir="rtl"] .alert *,
html[dir="rtl"] table,
html[dir="rtl"] th,
html[dir="rtl"] td {
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
}

html[dir="rtl"] .sidebar,
html[dir="rtl"] .sidebar * {
    overflow: visible !important;
}
CSS;
if (!is_file($cssPath) || file_get_contents($cssPath) !== $css) {
    if (is_file($cssPath)) backup_file($root, $backupRoot, $cssPath);
    file_put_contents($cssPath, $css);
    $changed[] = rel_path($root, $cssPath);
}

// Write JS guard/normalizer.
$jsPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'arabic-ui-final-fix.js';
$js = <<<'JS'
(function () {
    'use strict';

    const pairs = [
        ['الأرشيفة الإلكتروني', 'الأرشيف الإلكتروني'],
        ['الأرشيفة الإلكترونية', 'الأرشفة الإلكترونية'],
        ['النظا...', 'النظام'], ['النظا…', 'النظام'], ['نظا...', 'نظام'], ['نظا…', 'نظام'],
        ['لوحة التحك...', 'لوحة التحكم'], ['لوحة التحك…', 'لوحة التحكم'], ['التحك...', 'التحكم'], ['التحك…', 'التحكم'],
        ['رق...', 'رقم'], ['رق…', 'رقم'], ['اس...', 'اسم'], ['اس…', 'اسم'], ['الاس...', 'الاسم'], ['الاس…', 'الاسم'],
        ['أفه...', 'أفهم'], ['أفه…', 'أفهم'], ['شا...', 'شامل'], ['شا…', 'شامل'], ['كا...', 'كامل'], ['كا…', 'كامل'],
        ['كل الع...', 'كل العمليات'], ['كل الع…', 'كل العمليات'], ['الع...', 'العمليات'], ['الع…', 'العمليات'],
        ['والتأظ...ين', 'والتأمين'], ['والتأط...ين', 'والتأمين'], ['والتأ...ين', 'والتأمين'], ['والتأ…ين', 'والتأمين'],
        ['التأظ...ين', 'التأمين'], ['التأط...ين', 'التأمين'], ['التأ...ين', 'التأمين'], ['التأ…ين', 'التأمين'],
        ['بقشط...', 'بقسم'], ['بقسط...', 'بقسم'], ['بقظ...', 'بقسم'], ['بقط...', 'بقسم'],
        ['الط...رفقات', 'المرفقات'], ['الظ...رفقات', 'المرفقات'], ['ال...رفقات', 'المرفقات'], ['...رفقات', 'مرفقات'],
        ['الط...حذوفات', 'المحذوفات'], ['الظ...حذوفات', 'المحذوفات'], ['ال...حذوفات', 'المحذوفات'], ['...حذوفات', 'محذوفات'],
        ['الط...لف', 'الملف'], ['الظ...لف', 'الملف'], ['ال...لف', 'الملف'], ['...لف', 'ملف'],
        ['الط...ستخدمين', 'المستخدمين'], ['الظ...ستخدمين', 'المستخدمين'], ['ال...ستخدمين', 'المستخدمين'], ['...ستخدمين', 'مستخدمين'], ['...ستخدم', 'مستخدم'],
        ['الط...دير', 'المدير'], ['الظ...دير', 'المدير'], ['ال...دير', 'المدير'], ['...دير', 'مدير'],
        ['...ركز', 'مركز'], ['…ركز', 'مركز'], ['...عاينة', 'معاينة'], ['…عاينة', 'معاينة'], ['...راجعة', 'مراجعة'], ['…راجعة', 'مراجعة']
    ];

    function fixText(value) {
        if (!value) return value;
        let out = String(value)
            .replace(/ï¿½/g, 'م')
            .replace(/\uFFFD/g, 'م')
            .replace(/\u{FFFD}/gu, 'م')
            .replace(/&#65533;/g, 'م')
            .replace(/&#xFFFD;/gi, 'م');
        for (const [bad, good] of pairs) out = out.split(bad).join(good);
        return out;
    }

    function walk(node) {
        if (!node) return;
        if (node.nodeType === Node.TEXT_NODE) {
            const fixed = fixText(node.nodeValue);
            if (fixed !== node.nodeValue) node.nodeValue = fixed;
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        const tag = node.tagName ? node.tagName.toLowerCase() : '';
        if (['script', 'style', 'textarea', 'input'].includes(tag)) return;
        for (const attr of ['title', 'aria-label', 'placeholder', 'alt']) {
            if (node.hasAttribute && node.hasAttribute(attr)) {
                const val = node.getAttribute(attr);
                const fixed = fixText(val);
                if (fixed !== val) node.setAttribute(attr, fixed);
            }
        }
        for (const child of Array.from(node.childNodes)) walk(child);
    }

    function disableEllipsis() {
        document.querySelectorAll('body *').forEach(el => {
            const cs = window.getComputedStyle(el);
            if (cs.textOverflow === 'ellipsis') {
                el.style.textOverflow = 'clip';
                el.style.whiteSpace = 'normal';
                el.style.overflow = 'visible';
            }
        });
    }

    function run() {
        walk(document.body);
        disableEllipsis();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
    else run();
    window.addEventListener('load', run);
    setTimeout(run, 300);
    setTimeout(run, 1000);
})();
JS;
if (!is_file($jsPath) || file_get_contents($jsPath) !== $js) {
    if (is_file($jsPath)) backup_file($root, $backupRoot, $jsPath);
    file_put_contents($jsPath, $js);
    $changed[] = rel_path($root, $jsPath);
}

// Inject assets in layout.
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
if (is_file($layout)) {
    $old = file_get_contents($layout);
    $new = $old;
    if (strpos($new, 'arabic-ui-final-fix.css') === false) {
        $link = "    <link rel=\"stylesheet\" href=\"{{ asset('css/arabic-ui-final-fix.css') }}?v=2026062802\">\n";
        if (stripos($new, '</head>') !== false) $new = str_ireplace('</head>', $link . '</head>', $new);
        else $new = $link . $new;
    }
    if (strpos($new, 'arabic-ui-final-fix.js') === false) {
        $script = "    <script src=\"{{ asset('js/arabic-ui-final-fix.js') }}?v=2026062802\"></script>\n";
        if (stripos($new, '</body>') !== false) $new = str_ireplace('</body>', $script . '</body>', $new);
        else $new .= "\n" . $script;
    }
    if ($new !== $old) {
        backup_file($root, $backupRoot, $layout);
        file_put_contents($layout, $new);
        $changed[] = rel_path($root, $layout);
    }
}

// Repair settings table values if Laravel can bootstrap.
$dbChanged = 0;
try {
    $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
    if (is_file($bootstrap)) {
        $app = require $bootstrap;
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        if (class_exists(Illuminate\Support\Facades\Schema::class) && Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $cols = Illuminate\Support\Facades\Schema::getColumnListing('settings');
            if (in_array('key', $cols, true) && in_array('value', $cols, true)) {
                $rows = Illuminate\Support\Facades\DB::table('settings')->get(['key','value']);
                foreach ($rows as $row) {
                    $oldVal = (string)($row->value ?? '');
                    $newVal = repair_arabic_text($oldVal);
                    // Clean known system title values directly if they contain visible corruption fragments.
                    $key = (string)$row->key;
                    if (preg_match('/(system|app|title|name|subtitle|description)/i', $key)) {
                        $newVal = str_replace([
                            'نظام الأرشيفة الإلكتروني الخاص بقسم الشحن والتأمين',
                            'نظام الأرشيفة الإلكتروني الخاص بقشط... الشحن والتأمين',
                            'نظام الأرشيف الإلكتروني الخاص بقشط... الشحن والتأمين',
                        ], 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين', $newVal);
                    }
                    if ($newVal !== $oldVal) {
                        Illuminate\Support\Facades\DB::table('settings')->where('key', $row->key)->update(['value' => $newVal]);
                        $dbChanged++;
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    echo "WARN: لم يتم إصلاح قاعدة البيانات تلقائياً: " . $e->getMessage() . "\n";
}

echo "DONE: تم تطبيق إصلاح عرض النصوص العربية النهائي.\n";
echo "Changed files: " . count(array_unique($changed)) . "\n";
echo "Changed settings rows: " . $dbChanged . "\n";
echo "Backup: " . $backupRoot . "\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";
