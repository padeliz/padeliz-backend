<?php

declare(strict_types=1);

$testsDir = dirname(__DIR__) . '/tests';
$allowedExact = array_values(array_filter(array_map(
    static fn (string $relative): string|false => realpath($testsDir . '/' . $relative),
    [
        'Feature/Console/RecordingGenerator.php',
        // EventSlugGenerator::generate is unrelated to schedule template generation.
        'Unit/Services/EventSlug/EventSlugGeneratorTest.php',
        // Approved upload helper (not a *Test.php pipeline).
        'Helpers/FileUploadTestHelper.php',
        // GD helper unit (must exercise imagecreatetruecolor).
        'Unit/Services/Pdf/PdfRoundMaskTest.php',
    ]
)));

$fixturesDir = realpath($testsDir . '/Fixtures');

$violations = [];

$forbiddenPatterns = [
    '/->generate\s*\(/' => '->generate(',
    '/\binvokePipeline/' => 'invokePipeline',
    '/\binvokePairingPhase\b/' => 'invokePairingPhase',
    '/\binvokeOrderingPhase\b/' => 'invokeOrderingPhase',
    '/\bcapturePipelineEvents\b/' => 'capturePipelineEvents',
    '/\bbruteForceBestOrder/' => 'bruteForceBestOrder',
    '/\bbuildMatchMakingByBacktracking\b/' => 'buildMatchMakingByBacktracking',
    // Require "(" so Mockery shouldReceive('generate…') strings are allowed.
    '/\bgenerateRankingFromScratch\s*\(/' => 'generateRankingFromScratch',
    '/\bgenerateAttendanceForEdition\s*\(/' => 'generateAttendanceForEdition',
    '/\bgenerateMatchesFromScratch\s*\(/' => 'generateMatchesFromScratch',
    '/\bgenerateMatchesForEventDivision\s*\(/' => 'generateMatchesForEventDivision',
    '/\bgenerateRankingForEventDivision\s*\(/' => 'generateRankingForEventDivision',
];

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
    $real = realpath($path);
    if ($real === false) {
        continue;
    }
    if (in_array($real, $allowedExact, true)) {
        continue;
    }
    if ($fixturesDir !== false && str_starts_with($real, $fixturesDir . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Could not read: {$path}\n");
        exit(1);
    }

    foreach ($forbiddenPatterns as $pattern => $label) {
        if (preg_match($pattern, $contents) === 1) {
            $violations[] = "{$path} ({$label})";
        }
    }

    // Ban GD canvas creation in *Test.php (use tests/Fixtures or Helpers instead).
    if (str_ends_with($real, 'Test.php') && preg_match('/\bimagecreatetruecolor\b/', $contents) === 1) {
        $violations[] = "{$path} (imagecreatetruecolor)";
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Forbidden combinatorial / PDF-render / GD usage in test files (except allowlisted helpers):\n");
    foreach ($violations as $path) {
        fwrite(STDERR, "  - {$path}\n");
    }
    exit(1);
}

fwrite(STDOUT, "test:lint OK — no forbidden generate/pipeline/DFS/PDF-render helpers in tests.\n");
exit(0);
