<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$errors = [];
$checks = [];

$expectColumns = [
    'documents' => ['workflow_status', 'workflow_note', 'workflow_submitted_by', 'workflow_submitted_at', 'workflow_reviewed_by', 'workflow_reviewed_at', 'workflow_finalized_by', 'workflow_finalized_at'],
    'memos' => ['workflow_status', 'workflow_note', 'workflow_submitted_by', 'workflow_submitted_at', 'workflow_reviewed_by', 'workflow_reviewed_at', 'workflow_finalized_by', 'workflow_finalized_at'],
    'workflow_actions' => ['workflowable_type', 'workflowable_id', 'action', 'from_status', 'to_status', 'note', 'created_by'],
];

foreach ($expectColumns as $table => $columns) {
    if (! Schema::hasTable($table)) {
        $errors[] = "Missing table: {$table}";
        continue;
    }

    foreach ($columns as $column) {
        if (! Schema::hasColumn($table, $column)) {
            $errors[] = "Missing column: {$table}.{$column}";
        } else {
            $checks[] = "OK: {$table}.{$column}";
        }
    }
}

$files = [
    'app/Http/Controllers/WorkflowController.php',
    'app/Models/WorkflowAction.php',
    'resources/views/partials/workflow-panel.blade.php',
    'public/css/workflow.css',
];

foreach ($files as $file) {
    if (! file_exists(base_path($file))) {
        $errors[] = "Missing file: {$file}";
    } else {
        $checks[] = "OK: {$file}";
    }
}

$routes = [
    'documents.workflow.submit', 'documents.workflow.approve', 'documents.workflow.reject', 'documents.workflow.return', 'documents.workflow.finalize', 'documents.workflow.reopen',
    'memos.workflow.submit', 'memos.workflow.approve', 'memos.workflow.reject', 'memos.workflow.return', 'memos.workflow.finalize', 'memos.workflow.reopen',
];

foreach ($routes as $route) {
    if (! Route::has($route)) {
        $errors[] = "Missing route: {$route}";
    } else {
        $checks[] = "OK: route {$route}";
    }
}

foreach ($checks as $check) {
    echo $check . PHP_EOL;
}

if ($errors) {
    echo PHP_EOL . 'RESULT: FAILED' . PHP_EOL;
    foreach ($errors as $error) {
        echo 'ERROR: ' . $error . PHP_EOL;
    }
    exit(1);
}

echo PHP_EOL . 'RESULT: OK - Workflow approval update is installed.' . PHP_EOL;
