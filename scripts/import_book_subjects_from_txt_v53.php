<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$table = 'book_subjects';
$importFile = __DIR__ . '/../storage/app/imports/book_subjects_import_v53.txt';
$reportFile = __DIR__ . '/../storage/logs/book_subjects_import_v53_report.json';

function v53_clean_subject(?string $value): string
{
    $value = (string) $value;
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = preg_replace('/[\x{200E}\x{200F}\x{200B}\x{FEFF}]/u', '', $value) ?? $value;
    $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function v53_subject_key(string $value): string
{
    $value = v53_clean_subject($value);
    $value = mb_strtolower($value, 'UTF-8');

    $value = strtr($value, [
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
        'ٱ' => 'ا',
        'ى' => 'ي',
        'ة' => 'ه',
    ]);

    $value = preg_replace('/\s*[-–—]\s*/u', '-', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function v53_default_for_type(string $dataType): mixed
{
    $dataType = strtolower($dataType);

    if (in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
        return 0;
    }

    if (in_array($dataType, ['decimal', 'float', 'double', 'real'], true)) {
        return 0;
    }

    if (in_array($dataType, ['date'], true)) {
        return now()->toDateString();
    }

    if (in_array($dataType, ['datetime', 'timestamp'], true)) {
        return now();
    }

    if (in_array($dataType, ['time'], true)) {
        return now()->format('H:i:s');
    }

    if (in_array($dataType, ['json'], true)) {
        return json_encode([], JSON_UNESCAPED_UNICODE);
    }

    return '';
}

try {
    if (!file_exists($importFile)) {
        echo "[FAIL] Import file not found: {$importFile}\n";
        exit(1);
    }

    if (!Schema::hasTable($table)) {
        echo "[FAIL] Table not found: {$table}\n";
        exit(1);
    }

    $columns = Schema::getColumnListing($table);

    $nameCandidates = ['name', 'title', 'subject', 'subject_name', 'book_subject', 'label'];
    $nameColumn = null;

    foreach ($nameCandidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $nameColumn = $candidate;
            break;
        }
    }

    if ($nameColumn === null) {
        echo "[FAIL] Could not detect subject name column in {$table}. Checked: " . implode(', ', $nameCandidates) . "\n";
        exit(1);
    }

    $database = DB::getDatabaseName();

    $metaRows = DB::select(
        "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, EXTRA
         FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ?
         ORDER BY ORDINAL_POSITION",
        [$database, $table]
    );

    $meta = [];
    foreach ($metaRows as $row) {
        $meta[$row->COLUMN_NAME] = $row;
    }

    $rawLines = file($importFile, FILE_IGNORE_NEW_LINES);
    if ($rawLines === false) {
        echo "[FAIL] Could not read import file.\n";
        exit(1);
    }

    $subjects = [];
    $fileDuplicateCount = 0;
    $emptyCount = 0;

    foreach ($rawLines as $line) {
        $subject = v53_clean_subject($line);

        if ($subject === '') {
            $emptyCount++;
            continue;
        }

        $key = v53_subject_key($subject);

        if (isset($subjects[$key])) {
            $fileDuplicateCount++;
            continue;
        }

        $subjects[$key] = $subject;
    }

    if ($subjects === []) {
        echo "[FAIL] Import file has no valid subjects.\n";
        exit(1);
    }

    $selectColumns = ['id', $nameColumn];
    $hasDeletedAt = in_array('deleted_at', $columns, true);

    if ($hasDeletedAt) {
        $selectColumns[] = 'deleted_at';
    }

    $existingRows = DB::table($table)->select($selectColumns)->get();
    $existingByKey = [];

    foreach ($existingRows as $row) {
        $existingKey = v53_subject_key((string) $row->{$nameColumn});
        if ($existingKey !== '' && !isset($existingByKey[$existingKey])) {
            $existingByKey[$existingKey] = $row;
        }
    }

    $added = 0;
    $restored = 0;
    $existing = 0;
    $failed = [];

    DB::transaction(function () use (
        $table,
        $nameColumn,
        $columns,
        $meta,
        $subjects,
        $existingByKey,
        $hasDeletedAt,
        &$added,
        &$restored,
        &$existing,
        &$failed
    ) {
        foreach ($subjects as $key => $subject) {
            try {
                if (isset($existingByKey[$key])) {
                    $row = $existingByKey[$key];

                    if ($hasDeletedAt && !empty($row->deleted_at)) {
                        DB::table($table)->where('id', $row->id)->update([
                            'deleted_at' => null,
                            'updated_at' => in_array('updated_at', $columns, true) ? now() : DB::raw('updated_at'),
                        ]);
                        $restored++;
                    } else {
                        $existing++;
                    }

                    continue;
                }

                $insert = [
                    $nameColumn => $subject,
                ];

                if (in_array('is_active', $columns, true)) {
                    $insert['is_active'] = 1;
                }

                if (in_array('active', $columns, true)) {
                    $insert['active'] = 1;
                }

                if (in_array('status', $columns, true)) {
                    $insert['status'] = 'active';
                }

                if (in_array('sort_order', $columns, true) && !array_key_exists('sort_order', $insert)) {
                    $insert['sort_order'] = 0;
                }

                if (in_array('display_order', $columns, true) && !array_key_exists('display_order', $insert)) {
                    $insert['display_order'] = 0;
                }

                if (in_array('created_at', $columns, true)) {
                    $insert['created_at'] = now();
                }

                if (in_array('updated_at', $columns, true)) {
                    $insert['updated_at'] = now();
                }

                foreach ($meta as $column => $info) {
                    if (array_key_exists($column, $insert)) {
                        continue;
                    }

                    if ($column === 'id' || $column === $nameColumn || $column === 'deleted_at') {
                        continue;
                    }

                    if (str_contains(strtolower((string) $info->EXTRA), 'auto_increment')) {
                        continue;
                    }

                    if ((string) $info->IS_NULLABLE === 'YES') {
                        continue;
                    }

                    if ($info->COLUMN_DEFAULT !== null) {
                        continue;
                    }

                    if (in_array($column, ['created_at', 'updated_at'], true)) {
                        continue;
                    }

                    if (in_array($column, ['created_by', 'updated_by', 'user_id'], true)) {
                        $insert[$column] = auth()->id() ?: 1;
                        continue;
                    }

                    $insert[$column] = v53_default_for_type((string) $info->DATA_TYPE);
                }

                DB::table($table)->insert($insert);
                $added++;
            } catch (Throwable $e) {
                $failed[] = [
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                ];
            }
        }
    });

    $report = [
        'table' => $table,
        'name_column' => $nameColumn,
        'import_file' => realpath($importFile) ?: $importFile,
        'total_valid_unique_in_file' => count($subjects),
        'empty_lines_skipped' => $emptyCount,
        'duplicate_lines_skipped_in_file' => $fileDuplicateCount,
        'added' => $added,
        'restored_from_trash' => $restored,
        'already_existing' => $existing,
        'failed_count' => count($failed),
        'failed' => $failed,
        'created_at' => now()->toDateTimeString(),
    ];

    @file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo "Book subjects import V53 completed.\n";
    echo "Table: {$table}\n";
    echo "Name column: {$nameColumn}\n";
    echo "Unique subjects in file: " . count($subjects) . "\n";
    echo "Added: {$added}\n";
    echo "Restored from trash: {$restored}\n";
    echo "Already existing: {$existing}\n";
    echo "Duplicate lines skipped in file: {$fileDuplicateCount}\n";
    echo "Failed: " . count($failed) . "\n";
    echo "Report: {$reportFile}\n";

    exit(count($failed) > 0 ? 1 : 0);
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    exit(1);
}
