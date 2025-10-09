<?php

use Ayrunx\HttpCompression\CompressionAlgorithmEnum;
use Ayrunx\HttpCompression\Compressor;
use Ayrunx\HttpCompression\FileCompressor;
use Ayrunx\HttpCompression\CompressionException;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/compressor_test_' . uniqid();
    mkdir($this->testDir);
});

afterEach(function () {
    // Clean up test files
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

test('compress single file with auto-generated output path', function () {
    $inputFile = $this->testDir . '/test.txt';
    $content = 'Hello, World! This is test content for file compression.';
    file_put_contents($inputFile, $content);

    $outputFile = FileCompressor::compress($inputFile);

    expect($outputFile)->toBe($inputFile . '.gzip');
    expect(file_exists($outputFile))->toBeTrue();

    $compressed = file_get_contents($outputFile);
    $decompressed = Compressor::decompress($compressed, CompressionAlgorithmEnum::Gzip);
    expect($decompressed)->toBe($content);
});

test('compress single file with custom output path', function () {
    $inputFile = $this->testDir . '/input.txt';
    $outputFile = $this->testDir . '/output.gz';
    $content = 'Custom output path test content';
    file_put_contents($inputFile, $content);

    $result = FileCompressor::compress($inputFile, $outputFile, CompressionAlgorithmEnum::Gzip);

    expect($result)->toBe($outputFile);
    expect(file_exists($outputFile))->toBeTrue();
});

test('decompress single file with auto-generated output path', function () {
    $content = 'Test content for decompression';
    $compressedFile = $this->testDir . '/test.txt.gzip';

    $compressed = Compressor::compress($content, CompressionAlgorithmEnum::Gzip);
    file_put_contents($compressedFile, $compressed);

    $outputFile = FileCompressor::decompress($compressedFile);

    expect($outputFile)->toBe($this->testDir . '/test.txt');
    expect(file_exists($outputFile))->toBeTrue();
    expect(file_get_contents($outputFile))->toBe($content);
});

test('decompress single file with custom output path', function () {
    $content = 'Custom decompression test';
    $compressedFile = $this->testDir . '/compressed.gz';
    $outputFile = $this->testDir . '/decompressed.txt';

    $compressed = Compressor::compress($content, CompressionAlgorithmEnum::Gzip);
    file_put_contents($compressedFile, $compressed);

    $result = FileCompressor::decompress($compressedFile, $outputFile, CompressionAlgorithmEnum::Gzip);

    expect($result)->toBe($outputFile);
    expect(file_get_contents($outputFile))->toBe($content);
});

test('compress multiple files', function () {
    $files = [
        $this->testDir . '/file1.txt',
        $this->testDir . '/file2.txt',
        $this->testDir . '/file3.txt',
    ];

    foreach ($files as $file) {
        file_put_contents($file, "Content of {$file}");
    }

    $results = FileCompressor::compress($files);

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);

    foreach ($results as $index => $outputFile) {
        expect(file_exists($outputFile))->toBeTrue();
        expect($outputFile)->toBe($files[$index] . '.gzip');
    }
});

test('decompress multiple files', function () {
    $compressedFiles = [
        $this->testDir . '/file1.txt.gzip',
        $this->testDir . '/file2.txt.gzip',
    ];

    foreach ($compressedFiles as $file) {
        $compressed = Compressor::compress("Content of {$file}", CompressionAlgorithmEnum::Gzip);
        file_put_contents($file, $compressed);
    }

    $results = FileCompressor::decompress($compressedFiles);

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);

    foreach ($results as $outputFile) {
        expect(file_exists($outputFile))->toBeTrue();
    }
});

test('compress file with custom compression level', function () {
    $inputFile = $this->testDir . '/test.txt';
    $content = str_repeat('Test content with repetitive data for better compression. ', 100);
    file_put_contents($inputFile, $content);

    $outputFile = FileCompressor::compress($inputFile, level: 5);

    expect(file_exists($outputFile))->toBeTrue();

    $compressed = file_get_contents($outputFile);
    $decompressed = Compressor::decompress($compressed, CompressionAlgorithmEnum::Gzip);
    expect($decompressed)->toBe($content);
});

test('compress file with brotli algorithm', function () {
    if (!extension_loaded('brotli')) {
        $this->markTestSkipped('Brotli extension not available');
    }

    $inputFile = $this->testDir . '/test.txt';
    $content = 'Brotli compression test';
    file_put_contents($inputFile, $content);

    $outputFile = FileCompressor::compress($inputFile, algorithm: CompressionAlgorithmEnum::Brotli);

    expect($outputFile)->toBe($inputFile . '.br');
    expect(file_exists($outputFile))->toBeTrue();
});

test('compress file throws exception when file not found', function () {
    FileCompressor::compress($this->testDir . '/nonexistent.txt');
})->throws(CompressionException::class, 'Input file not found');

test('decompress file throws exception when file not found', function () {
    FileCompressor::decompress($this->testDir . '/nonexistent.gz');
})->throws(CompressionException::class, 'Input file not found');

test('compress creates output directory if not exists', function () {
    $inputFile = $this->testDir . '/test.txt';
    $outputFile = $this->testDir . '/subdir/output.gz';
    $content = 'Test content';
    file_put_contents($inputFile, $content);

    $result = FileCompressor::compress($inputFile, $outputFile);

    expect(file_exists($outputFile))->toBeTrue();
    expect(is_dir($this->testDir . '/subdir'))->toBeTrue();
});
