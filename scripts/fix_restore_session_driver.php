<?php

/**
 * DocumentArchive - Restore Session Driver Fix
 *
 * الهدف:
 * تحويل SESSION_DRIVER إلى file حتى لا تعتمد جلسة Laravel على جدول sessions
 * أثناء استعادة قاعدة البيانات؛ لأن الاستعادة قد تحذف جدول sessions مؤقتاً.
 */

$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';

if (!file_exists($envPath)) {
    fwrite(STDERR, "ERROR: .env file not found at: {$envPath}\n");
    exit(1);
}

$content = file_get_contents($envPath);
if ($content === false) {
    fwrite(STDERR, "ERROR: Unable to read .env file.\n");
    exit(1);
}

function setEnvValue(string $content, string $key, string $value): string
{
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, $key . '=' . $value, $content);
    }

    return rtrim($content) . PHP_EOL . $key . '=' . $value . PHP_EOL;
}

$content = setEnvValue($content, 'SESSION_DRIVER', 'file');
$content = setEnvValue($content, 'SESSION_COOKIE', 'documentarchive-session');

// تأكيد إعدادات MySQL الأساسية بدون تغيير كلمة المرور إن كانت موجودة.
$content = setEnvValue($content, 'DB_CONNECTION', 'mysql');

if (!preg_match('/^DB_HOST=/m', $content)) {
    $content .= PHP_EOL . 'DB_HOST=127.0.0.1';
}
if (!preg_match('/^DB_PORT=/m', $content)) {
    $content .= PHP_EOL . 'DB_PORT=3306';
}
if (!preg_match('/^DB_DATABASE=/m', $content)) {
    $content .= PHP_EOL . 'DB_DATABASE=document_archive';
}
if (!preg_match('/^DB_USERNAME=/m', $content)) {
    $content .= PHP_EOL . 'DB_USERNAME=root';
}
if (!preg_match('/^DB_PASSWORD=/m', $content)) {
    $content .= PHP_EOL . 'DB_PASSWORD=';
}

file_put_contents($envPath, $content);

echo "Done: SESSION_DRIVER changed to file.\n";
echo "Next commands:\n";
echo "php artisan optimize:clear\n";
echo "php artisan config:clear\n";
echo "php artisan cache:clear\n";
echo "php artisan serve\n";
