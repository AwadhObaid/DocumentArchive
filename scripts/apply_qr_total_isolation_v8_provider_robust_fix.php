<?php
/**
 * Robust QR provider defaults fix for DocumentArchive.
 * Adds safe default QR variables to AppServiceProvider without relying on brittle regex.
 */

function project_root(): string
{
    $dir = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    fwrite(STDERR, "ERROR: تعذر تحديد جذر مشروع Laravel. تأكد أنك تفك الضغط داخل جذر المشروع.\n");
    exit(1);
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

function clean_backups(string $root): int
{
    $count = 0;
    foreach (['app', 'resources', 'routes'] as $base) {
        $basePath = $root . DIRECTORY_SEPARATOR . $base;
        if (!is_dir($basePath)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
                rrmdir($file->getPathname());
                $count++;
            }
        }
    }
    return $count;
}

function find_last_class_brace(string $code): int
{
    $tokens = token_get_all($code);
    $level = 0;
    $classSeen = false;
    $classBraceLevel = null;
    $offset = 0;
    $lastClassClose = -1;

    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $id = is_array($token) ? $token[0] : null;

        if ($id === T_CLASS) {
            $classSeen = true;
        }

        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '{') {
                $level++;
                if ($classSeen && $classBraceLevel === null) {
                    $classBraceLevel = $level;
                }
            } elseif ($ch === '}') {
                if ($classBraceLevel !== null && $level === $classBraceLevel) {
                    $lastClassClose = $offset + $i;
                    $classBraceLevel = null;
                    $classSeen = false;
                }
                $level--;
            }
        }
        $offset += $len;
    }
    return $lastClassClose;
}

function inject_into_boot(string $code, string $snippet): string
{
    if (strpos($code, 'daQrUrl') !== false && strpos($code, 'daQrPrintOnly') !== false && strpos($code, 'View::share') !== false) {
        return $code;
    }

    $tokens = token_get_all($code);
    $offset = 0;
    $foundFunction = false;
    $foundBoot = false;

    foreach ($tokens as $idx => $token) {
        $text = is_array($token) ? $token[1] : $token;
        $id = is_array($token) ? $token[0] : null;

        if ($id === T_FUNCTION) {
            $foundFunction = true;
            $foundBoot = false;
        } elseif ($foundFunction && $id === T_STRING && strtolower($text) === 'boot') {
            $foundBoot = true;
        }

        if ($foundBoot && $text === '{') {
            $insertAt = $offset + strlen($text);
            return substr($code, 0, $insertAt) . "\n" . $snippet . "\n" . substr($code, $insertAt);
        }

        // If another function name appears, reset.
        if ($foundFunction && $id === T_STRING && strtolower($text) !== 'boot') {
            $foundFunction = false;
            $foundBoot = false;
        }

        $offset += strlen($text);
    }

    // No boot method: add one before the end of the class.
    $classEnd = find_last_class_brace($code);
    if ($classEnd < 0) {
        fwrite(STDERR, "ERROR: تعذر تحديد نهاية كلاس AppServiceProvider.\n");
        exit(1);
    }

    $method = "\n    public function boot(): void\n    {\n" . $snippet . "\n    }\n";
    return substr($code, 0, $classEnd) . $method . substr($code, $classEnd);
}

$root = project_root();
$provider = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AppServiceProvider.php';
if (!is_file($provider)) {
    fwrite(STDERR, "ERROR: الملف غير موجود: app/Providers/AppServiceProvider.php\n");
    exit(1);
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'qr-total-isolation-v8-provider-' . date('Ymd_His');
@mkdir($backupDir, 0777, true);
@copy($provider, $backupDir . DIRECTORY_SEPARATOR . 'AppServiceProvider.php');

$code = file_get_contents($provider);
if ($code === false) {
    fwrite(STDERR, "ERROR: تعذر قراءة AppServiceProvider.php\n");
    exit(1);
}

// Normalize only for editing; preserve practical PHP syntax.
$code = str_replace("\r\n", "\n", $code);

// Ensure namespace if somehow missing.
if (strpos($code, 'namespace App\\Providers;') === false) {
    if (preg_match('/^<\?php\s*/', $code)) {
        $code = preg_replace('/^<\?php\s*/', "<?php\n\nnamespace App\\Providers;\n\n", $code, 1);
    } else {
        $code = "<?php\n\nnamespace App\\Providers;\n\n" . $code;
    }
}

// Ensure use statements.
if (strpos($code, 'use Illuminate\\Support\\Facades\\View;') === false) {
    if (strpos($code, 'use Illuminate\\Support\\ServiceProvider;') !== false) {
        $code = str_replace(
            'use Illuminate\\Support\\ServiceProvider;',
            "use Illuminate\\Support\\ServiceProvider;\nuse Illuminate\\Support\\Facades\\View;",
            $code
        );
    } else {
        $code = str_replace(
            'namespace App\\Providers;',
            "namespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\nuse Illuminate\\Support\\Facades\\View;",
            $code
        );
    }
}

// Ensure class extends ServiceProvider if malformed.
if (strpos($code, 'class AppServiceProvider') === false) {
    fwrite(STDERR, "ERROR: لم يتم العثور على class AppServiceProvider داخل الملف.\n");
    exit(1);
}

$snippet = <<<'PHP_SNIPPET'
        // QR isolation defaults: keep QR variables safe for every non-print view.
        $__daQrDefaults = [
            'daQrUrl' => null,
            'daQrDocument' => null,
            'daQrSettings' => [],
            'daQrEnabled' => false,
            'daQrVisible' => false,
            'daQrPrintOnly' => true,
        ];

        foreach ($__daQrDefaults as $__daQrKey => $__daQrValue) {
            View::share($__daQrKey, $__daQrValue);
        }
PHP_SNIPPET;

$code = inject_into_boot($code, $snippet);
file_put_contents($provider, str_replace("\n", PHP_EOL, $code));

$cleaned = clean_backups($root);

echo "DONE: تم إصلاح AppServiceProvider وإضافة متغيرات QR الاحتياطية V8.\n";
echo "Backup: {$backupDir}\n";
echo "Cleaned backup folders: {$cleaned}\n";
echo "NEXT: composer dump-autoload && php artisan view:clear && php artisan optimize:clear\n";
