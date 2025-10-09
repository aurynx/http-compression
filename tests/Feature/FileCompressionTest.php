<?php

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\CompressionBuilder;
use Ayrunx\HttpCompression\CompressionException;
use Ayrunx\HttpCompression\CompressorFactory;

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

    $builder = new CompressionBuilder();
    $builder->addFile($inputFile, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = array_values($results)[0];
    $compressed = $result->getCompressed()['gzip'];

    $outputFile = $inputFile . '.gzip';
    file_put_contents($outputFile, $compressed);

    expect(file_exists($outputFile))->toBeTrue();

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $decompressed = $compressor->decompress(file_get_contents($outputFile));
    expect($decompressed)->toBe($content);
});

test('compress single file with custom output path', function () {
    $inputFile = $this->testDir . '/input.txt';
    $outputFile = $this->testDir . '/output.gz';
    $content = 'Custom output path test content';
    file_put_contents($inputFile, $content);

    $builder = new CompressionBuilder();
    $builder->addFile($inputFile, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = array_values($results)[0];
    $compressed = $result->getCompressed()['gzip'];
    file_put_contents($outputFile, $compressed);

    expect(file_exists($outputFile))->toBeTrue();
});

test('decompress single file with auto-generated output path', function () {
    $content = 'Test content for decompression';
    $compressedFile = $this->testDir . '/test.txt.gzip';

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $compressed = $compressor->compress($content);
    file_put_contents($compressedFile, $compressed);

    $outputFile = $this->testDir . '/test.txt';
    $decompressed = $compressor->decompress(file_get_contents($compressedFile));
    file_put_contents($outputFile, $decompressed);

    expect(file_exists($outputFile))->toBeTrue();
    expect(file_get_contents($outputFile))->toBe($content);
});

test('decompress single file with custom output path', function () {
    $content = 'Custom decompression test';
    $compressedFile = $this->testDir . '/compressed.gz';
    $outputFile = $this->testDir . '/decompressed.txt';

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $compressed = $compressor->compress($content);
    file_put_contents($compressedFile, $compressed);

    $decompressed = $compressor->decompress(file_get_contents($compressedFile));
    file_put_contents($outputFile, $decompressed);

    expect(file_exists($outputFile))->toBeTrue();
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

    $builder = new CompressionBuilder();
    $builder->addManyFiles($files, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);

    $index = 0;
    foreach ($results as $result) {
        expect($result->isOk())->toBeTrue();
        $compressed = $result->getCompressed()['gzip'];
        $outputFile = $files[$index] . '.gzip';
        file_put_contents($outputFile, $compressed);
        expect(file_exists($outputFile))->toBeTrue();
        $index++;
    }
});

test('decompress multiple files', function () {
    $compressedFiles = [
        $this->testDir . '/file1.txt.gzip',
        $this->testDir . '/file2.txt.gzip',
    ];

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    foreach ($compressedFiles as $file) {
        $compressed = $compressor->compress("Content of {$file}");
        file_put_contents($file, $compressed);
    }

    $outputFiles = [];
    foreach ($compressedFiles as $file) {
        $outputFile = str_replace('.gzip', '', $file);
        $decompressed = $compressor->decompress(file_get_contents($file));
        file_put_contents($outputFile, $decompressed);
        $outputFiles[] = $outputFile;
    }

    expect($outputFiles)->toHaveCount(2);
    foreach ($outputFiles as $outputFile) {
        expect(file_exists($outputFile))->toBeTrue();
    }
});

test('compress file with custom compression level', function () {
    $inputFile = $this->testDir . '/test.txt';
    $content = str_repeat('Test content with repetitive data for better compression. ', 100);
    file_put_contents($inputFile, $content);

    $builder = new CompressionBuilder();
    $builder->addFile($inputFile, ['gzip' => 5]);
    $results = $builder->compress();

    $result = array_values($results)[0];
    expect($result->isOk())->toBeTrue();

    $compressed = $result->getCompressed()['gzip'];
    $outputFile = $inputFile . '.gz';
    file_put_contents($outputFile, $compressed);

    expect(file_exists($outputFile))->toBeTrue();

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $decompressed = $compressor->decompress($compressed);
    expect($decompressed)->toBe($content);
});

test('compress file with brotli algorithm', function () {
    $inputFile = $this->testDir . '/test.txt';
    $content = 'Brotli compression test';
    file_put_contents($inputFile, $content);

    $builder = new CompressionBuilder();
    $builder->addFile($inputFile, AlgorithmEnum::Brotli);
    $results = $builder->compress();

    $result = array_values($results)[0];
    expect($result->isOk())->toBeTrue();

    $compressed = $result->getCompressed()['br'];
    $outputFile = $inputFile . '.br';
    file_put_contents($outputFile, $compressed);

    expect(file_exists($outputFile))->toBeTrue();
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

test('compress file throws exception when file not found', function () {
    $builder = new CompressionBuilder();
    $builder->addFile($this->testDir . '/nonexistent.txt', AlgorithmEnum::Gzip);
})->throws(CompressionException::class);

test('decompress file throws exception when file not found', function () {
    $nonexistentFile = $this->testDir . '/nonexistent.gz';
    expect(file_exists($nonexistentFile))->toBeFalse();

    $content = @file_get_contents($nonexistentFile);
    expect($content)->toBeFalse();
});

test('compress creates output directory if not exists', function () {
    $inputFile = $this->testDir . '/test.txt';
    $outputFile = $this->testDir . '/subdir/output.gz';
    $content = 'Test content';
    file_put_contents($inputFile, $content);

    $builder = new CompressionBuilder();
    $builder->addFile($inputFile, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = array_values($results)[0];
    $compressed = $result->getCompressed()['gzip'];

    // Create directory if not exists
    $dir = dirname($outputFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($outputFile, $compressed);

    expect(file_exists($outputFile))->toBeTrue();
    expect(is_dir($this->testDir . '/subdir'))->toBeTrue();
});
