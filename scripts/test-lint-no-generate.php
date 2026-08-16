<?php

declare(strict_types=1);

$testsDir = dirname(__DIR__) . '/tests';
$allowed = realpath($testsDir . '/Feature/Console/RecordingGenerator.php');
$violations = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile()) {
        continue;
    }
    $path = $fileInfo->getPathname();
    if (! str_ends_with($path, '.php')) {
        continue;
    }
    if (realpath($path) === $allowed) {
        continue;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Could not read: {$path}\n");
        exit(1);
    }

    if (preg_match('/->generate\s*\(/', $contents) === 1) {
        $violations[] = $path;
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Forbidden ->generate( usage in test files (except RecordingGenerator.php):\n");
    foreach ($violations as $path) {
        fwrite(STDERR, "  - {$path}\n");
    }
    exit(1);
}

fwrite(STDOUT, "test:lint OK — no forbidden ->generate( in tests.\n");
exit(0);
