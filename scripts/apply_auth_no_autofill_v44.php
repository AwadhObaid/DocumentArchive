<?php

$root = dirname(__DIR__);
$changes = [];
$warnings = [];

function v44_read(string $path): ?string
{
    return is_file($path) ? file_get_contents($path) : null;
}

function v44_write_if_changed(string $path, string $content, array &$changes): void
{
    $old = is_file($path) ? file_get_contents($path) : null;
    if ($old !== $content) {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
        $changes[] = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
    }
}

function v44_patch_bootstrap(string $root, array &$changes, array &$warnings): void
{
    $file = $root . '/bootstrap/app.php';
    $content = v44_read($file);
    if ($content === null) {
        $warnings[] = 'bootstrap/app.php not found. Middleware registration skipped.';
        return;
    }

    if (str_contains($content, 'PreventBrowserCacheV44::class')) {
        return;
    }

    $insert = "\n        // auth-no-autofill-v44:start\n        // Prevent cached authenticated screens and login form values after logout.\n        \$middleware->web(append: [\n            \\App\\Http\\Middleware\\PreventBrowserCacheV44::class,\n        ]);\n        // auth-no-autofill-v44:end\n";

    $patterns = [
        '/(->withMiddleware\s*\(\s*function\s*\([^)]*\$middleware[^)]*\)\s*(?::\s*void)?\s*\{)/m',
        '/(->withMiddleware\s*\(\s*function\s*\([^)]*\$middleware[^)]*\)\s*\{)/m',
    ];

    foreach ($patterns as $pattern) {
        $patched = preg_replace($pattern, '$1' . $insert, $content, 1, $count);
        if ($count > 0 && is_string($patched)) {
            v44_write_if_changed($file, $patched, $changes);
            return;
        }
    }

    $warnings[] = 'Could not locate withMiddleware closure in bootstrap/app.php. Add PreventBrowserCacheV44 manually to the web middleware group.';
}

function v44_add_attr_to_tag(string $tag, string $attrName, string $attrValue = null): string
{
    if (preg_match('/\s' . preg_quote($attrName, '/') . '\s*=/i', $tag)) {
        return $tag;
    }

    $attr = $attrValue === null ? $attrName : $attrName . '="' . $attrValue . '"';
    return preg_replace('/>$/', ' ' . $attr . '>', $tag) ?: $tag;
}

function v44_patch_login_view_content(string $content): string
{
    if (str_contains($content, 'auth-no-autofill-v44:applied')) {
        return $content;
    }

    $content = preg_replace_callback('/<form\b[^>]*>/i', function ($matches) {
        $tag = $matches[0];
        $lower = strtolower($tag);
        $looksLikeLogin = str_contains($lower, 'login') || str_contains($lower, 'login.post') || str_contains($lower, 'method="post"') || str_contains($lower, "method='post'");
        if (! $looksLikeLogin) {
            return $tag;
        }

        $tag = v44_add_attr_to_tag($tag, 'autocomplete', 'off');
        $tag = v44_add_attr_to_tag($tag, 'data-da-login-form', '1');
        $tag = v44_add_attr_to_tag($tag, 'data-lpignore', 'true');
        $tag = v44_add_attr_to_tag($tag, 'data-1p-ignore', 'true');
        return $tag;
    }, $content) ?: $content;

    $content = preg_replace_callback('/<input\b[^>]*name=["\'](?:username|email)["\'][^>]*>/iu', function ($matches) {
        $tag = $matches[0];
        $tag = v44_add_attr_to_tag($tag, 'autocomplete', 'new-password');
        $tag = v44_add_attr_to_tag($tag, 'autocapitalize', 'none');
        $tag = v44_add_attr_to_tag($tag, 'spellcheck', 'false');
        $tag = v44_add_attr_to_tag($tag, 'data-da-secure-login-input', 'username');
        $tag = v44_add_attr_to_tag($tag, 'data-lpignore', 'true');
        $tag = v44_add_attr_to_tag($tag, 'data-1p-ignore', 'true');
        return $tag;
    }, $content) ?: $content;

    $content = preg_replace_callback('/<input\b[^>]*name=["\']password["\'][^>]*>/iu', function ($matches) {
        $tag = $matches[0];
        $tag = v44_add_attr_to_tag($tag, 'autocomplete', 'new-password');
        $tag = v44_add_attr_to_tag($tag, 'data-da-secure-login-input', 'password');
        $tag = v44_add_attr_to_tag($tag, 'data-lpignore', 'true');
        $tag = v44_add_attr_to_tag($tag, 'data-1p-ignore', 'true');
        return $tag;
    }, $content) ?: $content;


    $content = preg_replace_callback('/<input\b[^>]*name=["\'](?:remember|remember_me)["\'][^>]*>/iu', function ($matches) {
        $tag = $matches[0];
        $tag = preg_replace('/\schecked(?:=["\'][^"\']*["\'])?/i', '', $tag) ?: $tag;
        $tag = v44_add_attr_to_tag($tag, 'disabled', null);
        $tag = v44_add_attr_to_tag($tag, 'data-da-remember-disabled-v44', '1');
        return $tag;
    }, $content) ?: $content;

    $decoy = <<<'BLADE'
{{-- auth-no-autofill-v44:applied --}}
<div class="auth-autofill-decoys" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;opacity:0;">
    <input type="text" name="da_decoy_username_v44" tabindex="-1" autocomplete="username">
    <input type="password" name="da_decoy_password_v44" tabindex="-1" autocomplete="current-password">
</div>
BLADE;

    if (str_contains($content, '@csrf')) {
        $content = preg_replace('/@csrf/', '@csrf' . "\n" . $decoy, $content, 1) ?: $content;
    } elseif (preg_match('/<form\b[^>]*>/i', $content)) {
        $content = preg_replace('/(<form\b[^>]*>)/i', '$1' . "\n" . $decoy, $content, 1) ?: $content;
    }

    $script = "\n{{-- auth-no-autofill-v44:script --}}\n<script src=\"{{ asset('js/auth-no-autofill-v44.js') }}?v={{ filemtime(public_path('js/auth-no-autofill-v44.js')) }}\" defer></script>\n";
    if (! str_contains($content, 'auth-no-autofill-v44.js')) {
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', $script . '</body>', $content, 1) ?: $content;
        } else {
            $content .= $script;
        }
    }

    $meta = "\n{{-- auth-no-cache-v44:meta --}}\n<meta http-equiv=\"Cache-Control\" content=\"no-store, no-cache, must-revalidate, max-age=0\">\n<meta http-equiv=\"Pragma\" content=\"no-cache\">\n<meta http-equiv=\"Expires\" content=\"0\">\n";
    if (! str_contains($content, 'auth-no-cache-v44:meta') && stripos($content, '</head>') !== false) {
        $content = preg_replace('/<\/head>/i', $meta . '</head>', $content, 1) ?: $content;
    }

    return $content;
}

function v44_patch_login_views(string $root, array &$changes, array &$warnings): void
{
    $viewsRoot = $root . '/resources/views';
    if (! is_dir($viewsRoot)) {
        $warnings[] = 'resources/views not found. Login view patch skipped.';
        return;
    }

    $patched = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if (! $fileInfo->isFile() || ! str_ends_with($fileInfo->getFilename(), '.blade.php')) {
            continue;
        }

        $path = $fileInfo->getPathname();
        $content = file_get_contents($path);
        $haystack = strtolower($content);
        $isCandidate = str_contains($haystack, 'login.post')
            || str_contains($haystack, '/login')
            || (str_contains($haystack, 'name="username"') && str_contains($haystack, 'name="password"'))
            || (str_contains($haystack, "name='username'") && str_contains($haystack, "name='password'"));

        if (! $isCandidate) {
            continue;
        }

        $newContent = v44_patch_login_view_content($content);
        v44_write_if_changed($path, $newContent, $changes);
        $patched++;
    }

    if ($patched === 0) {
        $warnings[] = 'No login Blade view was detected automatically. Check resources/views/auth/login.blade.php manually.';
    }
}

v44_patch_bootstrap($root, $changes, $warnings);
v44_patch_login_views($root, $changes, $warnings);

echo 'Auth no-autofill V44 apply completed.' . PHP_EOL;
foreach ($changes as $change) {
    echo '[CHANGED] ' . $change . PHP_EOL;
}
foreach ($warnings as $warning) {
    echo '[WARNING] ' . $warning . PHP_EOL;
}
if (! $changes) {
    echo '[OK] No file changes were needed or files were already patched.' . PHP_EOL;
}
