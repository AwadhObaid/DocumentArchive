<?php
/**
 * QR Total Isolation V7 - Provider fallback variables fix
 * Safely adds fallback QR view variables to AppServiceProvider so pages that do not define QR variables do not break.
 */

function failNow(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    failNow('تأكد أنك تشغل السكربت من داخل جذر مشروع Laravel.');
}

$providerPath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AppServiceProvider.php';
if (!file_exists($providerPath)) {
    failNow('الملف غير موجود: app/Providers/AppServiceProvider.php');
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'qr-total-isolation-v7-provider-' . date('Ymd_His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true)) {
    failNow('تعذر إنشاء مجلد النسخ الاحتياطي: ' . $backupDir);
}
copy($providerPath, $backupDir . DIRECTORY_SEPARATOR . 'AppServiceProvider.php');

$code = file_get_contents($providerPath);
if ($code === false) {
    failNow('تعذر قراءة AppServiceProvider.php');
}

// Ensure View facade import.
if (strpos($code, 'Illuminate\\Support\\Facades\\View') === false) {
    $namespacePos = strpos($code, "namespace App\\Providers;");
    if ($namespacePos === false) {
        failNow('تعذر تحديد namespace داخل AppServiceProvider.php');
    }

    $afterNamespace = $namespacePos + strlen("namespace App\\Providers;");
    $code = substr($code, 0, $afterNamespace)
        . PHP_EOL . PHP_EOL . 'use Illuminate\\Support\\Facades\\View;'
        . substr($code, $afterNamespace);
}

$block = <<<'PHPBLOCK'

        // BEGIN DA QR FALLBACK VIEW VARIABLES V7
        // هذه القيم الافتراضية تمنع ظهور أخطاء Undefined variable في الصفحات غير الخاصة بالطباعة.
        View::share('daQrUrl', null);
        View::share('daQrDocument', null);
        View::share('daQrSettings', []);
        View::share('daQrEnabled', false);
        View::share('daQrVisible', false);
        View::share('daQrPrintOnly', true);
        View::share('daQrX', null);
        View::share('daQrY', null);
        View::share('daQrSize', null);
        View::share('daQrPadding', null);
        View::share('daQrLabel', 'رمز الوصول الإلكتروني');
        // END DA QR FALLBACK VIEW VARIABLES V7
PHPBLOCK;

if (strpos($code, 'BEGIN DA QR FALLBACK VIEW VARIABLES V7') === false) {
    $bootPos = strpos($code, 'function boot(');

    if ($bootPos !== false) {
        $bracePos = strpos($code, '{', $bootPos);
        if ($bracePos === false) {
            failNow('تم العثور على boot() لكن تعذر تحديد بداية الدالة.');
        }
        $code = substr($code, 0, $bracePos + 1) . $block . substr($code, $bracePos + 1);
    } else {
        $lastBrace = strrpos($code, '}');
        if ($lastBrace === false) {
            failNow('تعذر تحديد نهاية كلاس AppServiceProvider.');
        }

        $method = <<<'PHPMETHOD'

    public function boot(): void
    {
        // BEGIN DA QR FALLBACK VIEW VARIABLES V7
        // هذه القيم الافتراضية تمنع ظهور أخطاء Undefined variable في الصفحات غير الخاصة بالطباعة.
        View::share('daQrUrl', null);
        View::share('daQrDocument', null);
        View::share('daQrSettings', []);
        View::share('daQrEnabled', false);
        View::share('daQrVisible', false);
        View::share('daQrPrintOnly', true);
        View::share('daQrX', null);
        View::share('daQrY', null);
        View::share('daQrSize', null);
        View::share('daQrPadding', null);
        View::share('daQrLabel', 'رمز الوصول الإلكتروني');
        // END DA QR FALLBACK VIEW VARIABLES V7
    }
PHPMETHOD;
        $code = substr($code, 0, $lastBrace) . $method . PHP_EOL . substr($code, $lastBrace);
    }
}

if (file_put_contents($providerPath, $code) === false) {
    failNow('تعذر حفظ AppServiceProvider.php');
}

// Clean accidental _backup folders inside active Laravel paths.
$activeDirs = [
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'resources',
    $root . DIRECTORY_SEPARATOR . 'routes',
];

$removed = [];
foreach ($activeDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $path = $item->getPathname();
            $children = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($children as $child) {
                $child->isDir() ? @rmdir($child->getPathname()) : @unlink($child->getPathname());
            }
            @rmdir($path);
            $removed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}

echo "DONE: تم إضافة متغيرات QR الاحتياطية إلى AppServiceProvider.\n";
echo "Backup: {$backupDir}\n";
if ($removed) {
    echo "Removed backup folders:\n- " . implode("\n- ", $removed) . "\n";
}
echo "NEXT: composer dump-autoload && php artisan view:clear && php artisan optimize:clear\n";
