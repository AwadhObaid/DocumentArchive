<?php

$root = dirname(__DIR__);
$checks = [
    'app/Http/Controllers/FormLinkController.php' => [
        "route('form-links.inline.file'",
        'public_url',
        'encodePublicPath',
        'Content-Disposition',
    ],
    'resources/views/form-links/preview.blade.php' => [
        'form-preview-object',
        '<object',
        '<embed',
        'فتح كرابط مباشر',
    ],
    'routes/web.php' => [
        '/form-links/{formLink}/inline/{filename}',
        'form-links.inline.file',
    ],
];

$ok = true;
foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        echo "MISSING: {$file}
";
        $ok = false;
        continue;
    }
    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            echo "MISSING TOKEN: {$file} => {$needle}
";
            $ok = false;
        } else {
            echo "OK: {$file} => {$needle}
";
        }
    }
}

echo $ok ? "RESULT: OK
" : "RESULT: FAILED
";
exit($ok ? 0 : 1);
