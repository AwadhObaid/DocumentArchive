<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = realpath(__DIR__ . '/..');
$importFile = $root . DIRECTORY_SEPARATOR . 'storage/app/imports/book_subjects_import_v53.txt';
$scriptFile = __DIR__ . '/import_book_subjects_from_txt_v53.php';
$table = 'book_subjects';

$ok = true;

function v53_check(bool $condition, string $message): void
{
    global $ok;

    if ($condition) {
        echo "[OK] {$message}\n";
    } else {
        echo "[FAIL] {$message}\n";
        $ok = false;
    }
}

function v53_clean_line(string $line): string
{
    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
    $line = preg_replace('/[\x{200E}\x{200F}\x{200B}\x{FEFF}]/u', '', $line) ?? $line;
    $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

    return trim($line);
}

v53_check(file_exists($scriptFile), 'import script exists');
v53_check(file_exists($importFile), 'import TXT file exists');

if (file_exists($importFile)) {
    $content = file_get_contents($importFile);
    $lines = preg_split('/\R/u', (string) $content) ?: [];
    $valid = array_values(array_filter(array_map('v53_clean_line', $lines), fn ($line) => $line !== ''));

    v53_check(count($valid) > 0, 'TXT file has valid subjects');
    v53_check(count($valid) >= 50, 'TXT file includes at least 50 subjects');
    v53_check(!preg_match('/ط§|ظ„|ï؟½|�/', (string) $content), 'TXT file does not contain common mojibake tokens');
    v53_check(in_array('الموانئ الشمالية - إفراج جمركي', $valid, true), 'TXT file contains first expected Arabic subject');
    v53_check(in_array('EXPEDITORS INTERNATIONAL | إفراج جمركي', $valid, true), 'TXT file contains mixed English/Arabic subject');
}

try {
    v53_check(Schema::hasTable($table), "table exists: {$table}");

    if (Schema::hasTable($table)) {
        $columns = Schema::getColumnListing($table);
        $nameCandidates = ['name', 'title', 'subject', 'subject_name', 'book_subject', 'label'];
        $found = array_values(array_intersect($nameCandidates, $columns));

        v53_check(count($found) > 0, 'book subject name column can be detected');
    }
} catch (Throwable $e) {
    v53_check(false, 'database check failed: ' . $e->getMessage());
}

if ($ok) {
    echo "\nBook subjects import V53 readiness check passed.\n";
    exit(0);
}

echo "\nBook subjects import V53 readiness check failed.\n";
exit(1);
