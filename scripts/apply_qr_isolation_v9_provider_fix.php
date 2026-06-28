<?php
/**
 * QR Isolation V9 Provider Fix
 * لا يلمس AppServiceProvider نهائياً.
 * ينشئ ServiceProvider مستقل لقيم QR الافتراضية، ويسجله في bootstrap/providers.php.
 */

function root_path_from_script(): string
{
    $root = realpath(__DIR__ . '/..');
    if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
        fwrite(STDERR, "ERROR: شغل السكربت من داخل جذر مشروع Laravel.\n");
        exit(1);
    }
    return $root;
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "ERROR: تعذر إنشاء المجلد: {$dir}\n");
        exit(1);
    }
}

function write_file(string $path, string $content): void
{
    ensure_dir(dirname($path));
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERROR: تعذر كتابة الملف: {$path}\n");
        exit(1);
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function clean_backup_dirs(string $root): array
{
    $cleaned = [];
    foreach (['app', 'resources', 'routes'] as $top) {
        $base = $root . DIRECTORY_SEPARATOR . $top;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                $path = $item->getPathname();
                rrmdir($path);
                $cleaned[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }
    return $cleaned;
}

$root = root_path_from_script();
$providerDir = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers';
$providerFile = $providerDir . DIRECTORY_SEPARATOR . 'QrViewDefaultsServiceProvider.php';
$providersFile = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'providers.php';
$cssFile = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'qr-global-isolation-v9.css';
$jsFile = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'qr-global-isolation-v9.js';
$layoutFile = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';

$providerContent = <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class QrViewDefaultsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // لا شيء هنا حالياً.
    }

    public function boot(): void
    {
        /**
         * قيم احتياطية تمنع تعطل أي صفحة وصلها أثر قديم من QR.
         * القيمة الافتراضية: QR مخفي في كل الصفحات العامة.
         * صفحة طباعة رقم الكتاب هي وحدها التي يجب أن تضبط القيم الحقيقية محلياً.
         */
        View::share('daQrUrl', '');
        View::share('daQrDocument', (object) [
            'id' => 0,
            'reference_number' => '',
        ]);
        View::share('daQrSettings', [
            'show_qr' => '0',
            'show_label' => '0',
            'x' => '55',
            'y' => '48',
            'size' => '16',
            'padding' => '1',
            'label_font_size' => '7',
        ]);
        View::share('daQrEnabled', false);
        View::share('daQrVisible', false);
        View::share('daQrPrintOnly', true);
    }
}
PHP;

write_file($providerFile, $providerContent);

// سجل ServiceProvider مستقل في Laravel 11+/13: bootstrap/providers.php
ensure_dir(dirname($providersFile));
if (!file_exists($providersFile)) {
    $providersContent = "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n    App\\Providers\\QrViewDefaultsServiceProvider::class,\n];\n";
    write_file($providersFile, $providersContent);
} else {
    $providersContent = file_get_contents($providersFile);
    if ($providersContent === false) {
        fwrite(STDERR, "ERROR: تعذر قراءة bootstrap/providers.php\n");
        exit(1);
    }
    if (!str_contains($providersContent, 'QrViewDefaultsServiceProvider::class')) {
        $line = "    App\\Providers\\QrViewDefaultsServiceProvider::class,";
        $pos = strrpos($providersContent, '];');
        if ($pos === false) {
            // ملف غير قياسي، نعيد بناءه بشكل آمن مع AppServiceProvider و QrViewDefaultsServiceProvider
            $providersContent = "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n{$line}\n];\n";
        } else {
            $providersContent = substr($providersContent, 0, $pos) . $line . "\n" . substr($providersContent, $pos);
        }
        write_file($providersFile, $providersContent);
    }
}

$cssContent = <<<'CSS'
/* QR Emergency Isolation V9
   يمنع ظهور أي QR متسرب في الصفحات العامة.
   صفحة الطباعة نفسها لا تعتمد على هذا الملف لإظهار QR، بل على قالبها المستقل. */
body:not(.document-print-reference-page) .document-qr-card,
body:not(.document-print-reference-page) .da-document-qr,
body:not(.document-print-reference-page) .da-qr-print,
body:not(.document-print-reference-page) .da-print-qr,
body:not(.document-print-reference-page) .qr-print-position,
body:not(.document-print-reference-page) .qr-position-card,
body:not(.document-print-reference-page) [data-da-qr],
body:not(.document-print-reference-page) [id*="qr" i][class*="document" i] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
CSS;
write_file($cssFile, $cssContent);

$jsContent = <<<'JS'
(function () {
    var isPrintReference = /\/documents\/\d+\/print-reference(?:$|[?#\/])/.test(window.location.pathname);
    if (isPrintReference) return;

    function removeLeakedQr() {
        var selectors = [
            '.document-qr-card',
            '.da-document-qr',
            '.da-qr-print',
            '.da-print-qr',
            '.qr-print-position',
            '.qr-position-card',
            '[data-da-qr]'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function (node) {
            node.remove();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeLeakedQr);
    } else {
        removeLeakedQr();
    }
    setTimeout(removeLeakedQr, 300);
    setTimeout(removeLeakedQr, 1200);
})();
JS;
write_file($jsFile, $jsContent);

// اربط CSS/JS الحارس في layout إذا كان موجوداً
if (file_exists($layoutFile)) {
    $layout = file_get_contents($layoutFile);
    if ($layout !== false) {
        $cssLink = "<link rel=\"stylesheet\" href=\"{{ asset('css/qr-global-isolation-v9.css') }}\">";
        $jsLink = "<script src=\"{{ asset('js/qr-global-isolation-v9.js') }}\" defer></script>";

        if (!str_contains($layout, 'qr-global-isolation-v9.css')) {
            $headPos = stripos($layout, '</head>');
            if ($headPos !== false) {
                $layout = substr($layout, 0, $headPos) . "    {$cssLink}\n" . substr($layout, $headPos);
            } else {
                $layout = $cssLink . "\n" . $layout;
            }
        }
        if (!str_contains($layout, 'qr-global-isolation-v9.js')) {
            $bodyPos = strripos($layout, '</body>');
            if ($bodyPos !== false) {
                $layout = substr($layout, 0, $bodyPos) . "    {$jsLink}\n" . substr($layout, $bodyPos);
            } else {
                $layout .= "\n" . $jsLink . "\n";
            }
        }
        write_file($layoutFile, $layout);
    }
}

$cleaned = clean_backup_dirs($root);

echo "DONE: تم إضافة QrViewDefaultsServiceProvider وعزل QR عن الصفحات العامة V9.\n";
echo "Provider: app/Providers/QrViewDefaultsServiceProvider.php\n";
echo "Registered: bootstrap/providers.php\n";
echo "Guard CSS/JS: public/css + public/js\n";
if ($cleaned) {
    echo "Cleaned backup dirs:\n- " . implode("\n- ", $cleaned) . "\n";
}
echo "NEXT: composer dump-autoload && php artisan view:clear && php artisan optimize:clear\n";
