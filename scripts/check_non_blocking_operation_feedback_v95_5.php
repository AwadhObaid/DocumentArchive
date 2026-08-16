<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$js = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js'
    . DIRECTORY_SEPARATOR . 'global-operation-loading-v90.js';
$css = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css'
    . DIRECTORY_SEPARATOR . 'global-operation-loading-v90.css';
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'
    . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';

$errors = [];

foreach ([$js, $css, $layout] as $file) {
    if (! is_file($file)) {
        $errors[] = 'Missing file: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    }
}

if (is_file($js)) {
    $contents = (string) file_get_contents($js);

    $required = [
        'Unified Non-Blocking Operation Feedback V95.5',
        'window.DocumentArchiveLoading',
        "document.addEventListener('da:loading:progress'",
        "data-da-loading=\"off\"",
        'markControlBusy',
        'notice: false',
        'window.__daSkipNextBeforeUnloadLoading',
        "window.addEventListener('pageshow', hideImmediately)",
    ];

    foreach ($required as $needle) {
        if (! str_contains($contents, $needle)) {
            $errors[] = 'Missing JavaScript marker: ' . $needle;
        }
    }

    $forbidden = [
        "document.body?.classList.add('da-loading-active')",
        "document.body?.setAttribute('aria-busy', 'true')",
    ];

    foreach ($forbidden as $needle) {
        if (str_contains($contents, $needle)) {
            $errors[] = 'Blocking JavaScript behavior still present: ' . $needle;
        }
    }
}

if (is_file($css)) {
    $contents = (string) file_get_contents($css);

    $required = [
        'Unified Non-Blocking Operation Feedback V95.5',
        'top: 18px;',
        'left: 18px;',
        'pointer-events: none;',
        'display: none !important;',
        '.da-action-busy',
    ];

    foreach ($required as $needle) {
        if (! str_contains($contents, $needle)) {
            $errors[] = 'Missing CSS marker: ' . $needle;
        }
    }

    if (str_contains($contents, 'inset: 0;') && str_contains($contents, '.da-global-loading')) {
        $errors[] = 'Potential full-screen loading inset remains in the V95.5 stylesheet.';
    }
}

if (is_file($layout)) {
    $contents = (string) file_get_contents($layout);

    foreach ([
        'id="daGlobalLoading"',
        'data-da-page-progress',
        "asset('css/global-operation-loading-v90.css')",
        "asset('js/global-operation-loading-v90.js')",
    ] as $needle) {
        if (! str_contains($contents, $needle)) {
            $errors[] = 'Required compatibility marker missing from layout: ' . $needle;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "DocumentArchive Non-Blocking Operation Feedback V95.5 verification FAILED.\n");

    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . PHP_EOL);
    }

    exit(1);
}

echo "DocumentArchive Non-Blocking Operation Feedback V95.5 verification PASSED.\n";
