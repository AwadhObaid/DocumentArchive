<?php

$root = dirname(__DIR__);
$cssPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'app.css';
$marker = '/* DocumentArchive flash alerts final */';

if (!file_exists($cssPath)) {
    fwrite(STDERR, "public/css/app.css was not found.\n");
    exit(1);
}

$css = file_get_contents($cssPath);
if ($css === false) {
    fwrite(STDERR, "Unable to read public/css/app.css.\n");
    exit(1);
}

if (strpos($css, $marker) === false) {
    $block = <<<'CSS'

/* DocumentArchive flash alerts final */
.alert-success,
.flash-success,
.message-success {
    display: block;
    width: 100%;
    margin: 14px 0 18px;
    padding: 14px 18px;
    border-radius: 14px;
    border: 1px solid #22c55e;
    background: #dcfce7;
    color: #14532d;
    font-weight: 800;
    line-height: 1.8;
    box-shadow: 0 10px 24px rgba(34, 197, 94, 0.12);
}

.alert-error,
.flash-error,
.message-error,
.alert-danger {
    display: block;
    width: 100%;
    margin: 14px 0 18px;
    padding: 14px 18px;
    border-radius: 14px;
    border: 1px solid #ef4444;
    background: #fee2e2;
    color: #7f1d1d;
    font-weight: 800;
    line-height: 1.8;
    box-shadow: 0 10px 24px rgba(239, 68, 68, 0.12);
}

.alert-warning,
.flash-warning,
.message-warning {
    display: block;
    width: 100%;
    margin: 14px 0 18px;
    padding: 14px 18px;
    border-radius: 14px;
    border: 1px solid #f59e0b;
    background: #fef3c7;
    color: #78350f;
    font-weight: 800;
    line-height: 1.8;
    box-shadow: 0 10px 24px rgba(245, 158, 11, 0.12);
}

[data-theme="dark"] .alert-success,
body.dark .alert-success,
.dark .alert-success {
    background: rgba(22, 101, 52, 0.25);
    color: #bbf7d0;
    border-color: rgba(34, 197, 94, 0.7);
}

[data-theme="dark"] .alert-error,
body.dark .alert-error,
.dark .alert-error,
[data-theme="dark"] .alert-danger,
body.dark .alert-danger,
.dark .alert-danger {
    background: rgba(127, 29, 29, 0.35);
    color: #fecaca;
    border-color: rgba(239, 68, 68, 0.75);
}

[data-theme="dark"] .alert-warning,
body.dark .alert-warning,
.dark .alert-warning {
    background: rgba(120, 53, 15, 0.35);
    color: #fde68a;
    border-color: rgba(245, 158, 11, 0.75);
}
CSS;

    file_put_contents($cssPath, rtrim($css) . PHP_EOL . $block . PHP_EOL);
    echo "Flash alert styles appended to public/css/app.css\n";
} else {
    echo "Flash alert styles already exist. No changes needed.\n";
}

$layoutPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
if (file_exists($layoutPath)) {
    $layout = file_get_contents($layoutPath);
    if ($layout !== false && strpos($layout, "session('success')") === false && strpos($layout, '@yield(\'content\')') !== false) {
        $flash = <<<'BLADE'

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert-error">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-error">
                    <strong>يرجى تصحيح الأخطاء التالية:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
BLADE;
        $layout = str_replace("            @yield('content')", $flash . "\n            @yield('content')", $layout);
        file_put_contents($layoutPath, $layout);
        echo "Flash alert markup inserted into layout.\n";
    } else {
        echo "Layout already contains flash alerts or content marker was not found.\n";
    }
}
