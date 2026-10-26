<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [];
foreach (['lib', 'tests/compatibility'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory)) as $file) {
        if ($file->getExtension() === 'php' && strpos($file->getPathname(), '/vendor/') === false) {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);
foreach ($files as $file) {
    $lines = [];
    exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=32767 -d display_errors=1 -l ' . escapeshellarg($file) . ' 2>&1', $lines, $status);
    $output = implode("\n", $lines);
    if ($status !== 0 || preg_match('/(?:Deprecated|Warning|Notice|Fatal error|Parse error):/', $output)) {
        fwrite(STDERR, $file . "\n" . $output . "\n");
        exit(1);
    }
}
echo 'OK: ' . count($files) . " PHP files linted under E_ALL\n";
