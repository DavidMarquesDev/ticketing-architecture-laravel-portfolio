<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$testsDirectory = $root . '/tests';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDirectory, FilesystemIterator::SKIP_DOTS)
);

$testFiles = [];

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo) {
        continue;
    }

    $pathname = $file->getPathname();

    if (!str_ends_with($pathname, 'Test.php')) {
        continue;
    }

    $normalizedPath = str_replace('\\', '/', $pathname);

    if (str_ends_with($normalizedPath, '/tests/RunAllTests.php')) {
        continue;
    }

    $testFiles[] = $pathname;
}

sort($testFiles);

$failures = [];

foreach ($testFiles as $testFile) {
    $command = sprintf('php %s', escapeshellarg($testFile));
    $output = [];
    $status = 0;
    exec($command, $output, $status);

    echo sprintf('[%s] %s', $status === 0 ? 'PASS' : 'FAIL', str_replace($root . DIRECTORY_SEPARATOR, '', $testFile)) . PHP_EOL;

    if ($status !== 0) {
        $failures[] = [
            'file' => $testFile,
            'output' => $output,
        ];
    }
}

if ($failures !== []) {
    echo PHP_EOL . 'Falhas encontradas:' . PHP_EOL;

    foreach ($failures as $failure) {
        echo '- ' . $failure['file'] . PHP_EOL;
        foreach ($failure['output'] as $line) {
            echo '  ' . $line . PHP_EOL;
        }
    }

    exit(1);
}

echo PHP_EOL . 'Todos os testes passaram.' . PHP_EOL;
exit(0);
