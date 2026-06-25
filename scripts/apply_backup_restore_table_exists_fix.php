<?php

$root = dirname(__DIR__);
$source = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'BackupController.php';
$target = getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'BackupController.php';

if (! file_exists($source)) {
    fwrite(STDERR, "Source BackupController.php not found.\n");
    exit(1);
}

if (! is_dir(dirname($target))) {
    mkdir(dirname($target), 0777, true);
}

copy($source, $target);
echo "Backup restore table-exists fix applied successfully.\n";
