<?php
declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root=resource_path('views/errors');
$files=['layout.blade.php','500.blade.php','502.blade.php','503.blade.php'];
$failed=false;

foreach($files as $file){
    $ok=is_file($root.DIRECTORY_SEPARATOR.$file);
    echo ($ok?'[ OK ] ':'[FAIL] ')."Exists: $file\n";
    $failed=$failed||!$ok;
}

$layout=@file_get_contents($root.DIRECTORY_SEPARATOR.'layout.blade.php');
$checks=[
 'Arabic RTL layout'=>is_string($layout)&&str_contains($layout,'dir="rtl"'),
 'System identity'=>is_string($layout)&&str_contains($layout,'نظام الأرشفة الإلكترونية'),
 'Retry button'=>is_string($layout)&&str_contains($layout,'location.reload()'),
 'Automatic retry'=>is_string($layout)&&str_contains($layout,'id="count"'),
 'No external assets'=>is_string($layout)&&!str_contains($layout,'<link ')&&!str_contains($layout,'<script src=')
];

foreach($checks as $label=>$ok){
    echo ($ok?'[ OK ] ':'[FAIL] ').$label."\n";
    $failed=$failed||!$ok;
}

if($failed){fwrite(STDERR,"Branded error pages V81.7 check failed.\n");exit(1);}
echo "Branded error pages V81.7 check passed.\n";
