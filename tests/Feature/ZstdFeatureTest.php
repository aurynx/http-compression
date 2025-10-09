<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\CompressionBuilder;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/compressor_test_' . uniqid('', true);
    mkdir($this->testDir);
});

afterEach(function () {
    if (is_dir($this->testDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $action = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $action($fileinfo->getRealPath());
        }

        rmdir($this->testDir);
    }
});

test('compress single data with zstd', function () {
    $builder = new CompressionBuilder();
    $builder->add('Hello Zstd', AlgorithmEnum::Zstd);
    $results = $builder->compress();

    expect($results)->toHaveCount(1);
    $result = array_values($results)[0];
    expect($result->isOk())->toBeTrue();
    expect($result->getCompressed())->toHaveKey('zstd');
})->skip(!extension_loaded('zstd'), 'Zstd extension not available');

test('compress file with zstd algorithm', function () {
    $inputFile = $this->testDir . '/test.txt';
    $content   = 'Zstd compression test';
    file_put_contents($inputFile, $content);

    $builder = new CompressionBuilder();
    $builder->addFile($inputFile, AlgorithmEnum::Zstd);
    $results = $builder->compress();

    $result = array_values($results)[0];
    expect($result->isOk())->toBeTrue();

    $compressed = $result->getCompressed()['zstd'];
    $outputFile = $inputFile . '.zst';
    file_put_contents($outputFile, $compressed);

    expect(file_exists($outputFile))->toBeTrue();
})->skip(!extension_loaded('zstd'), 'Zstd extension not available');
