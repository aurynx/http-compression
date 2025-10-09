<?php

use Ayrunx\HttpCompression\CompressionAlgorithmEnum;
use Ayrunx\HttpCompression\Compressor;
use Ayrunx\HttpCompression\CompressionResult;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/compressor_test_' . uniqid();
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

// 1.1. Single raw data + single algorithm
test('compress single raw data with single algorithm', function () {
    $result = Compressor::compress('Hello, World!', CompressionAlgorithmEnum::Gzip);

    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->algorithm)->toBe(CompressionAlgorithmEnum::Gzip);
    expect($result->isFile)->toBeFalse();
    expect($result->content)->not->toBe('Hello, World!');
});

// 1.2. Single file + single algorithm
test('compress single file with single algorithm', function () {
    $file = $this->testDir . '/test.txt';
    file_put_contents($file, 'File content');

    $result = Compressor::compress('file://' . $file, CompressionAlgorithmEnum::Gzip);

    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->algorithm)->toBe(CompressionAlgorithmEnum::Gzip);
    expect($result->isFile)->toBeTrue();
    expect($result->identifier)->toBe($file);
});

// 1.3. Array of raw data + single algorithm
test('compress array of raw data with single algorithm', function () {
    $results = Compressor::compress(['data1', 'data2', 'data3'], CompressionAlgorithmEnum::Gzip);

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(CompressionResult::class);
        expect($result->algorithm)->toBe(CompressionAlgorithmEnum::Gzip);
        expect($result->isFile)->toBeFalse();
    }
});

// 1.4. Array of files + single algorithm
test('compress array of files with single algorithm', function () {
    $file1 = $this->testDir . '/file1.txt';
    $file2 = $this->testDir . '/file2.txt';
    file_put_contents($file1, 'Content 1');
    file_put_contents($file2, 'Content 2');

    $results = Compressor::compress(['file://' . $file1, 'file://' . $file2], CompressionAlgorithmEnum::Gzip);

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(CompressionResult::class);
        expect($result->isFile)->toBeTrue();
    }
});

// 1.5. Mixed array (raw data + files) + single algorithm
test('compress mixed array with single algorithm', function () {
    $file = $this->testDir . '/test.txt';
    file_put_contents($file, 'File content');

    $results = Compressor::compress(['raw data', 'file://' . $file], CompressionAlgorithmEnum::Gzip);

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
    expect($results[0]->isFile)->toBeFalse();
    expect($results[1]->isFile)->toBeTrue();
});

// 2.1. Single data + single algorithm (already tested above)

// 2.2. Single data + array of algorithms
test('compress single data with multiple algorithms', function () {
    $results = Compressor::compress(
        'Test data',
        [CompressionAlgorithmEnum::Gzip, CompressionAlgorithmEnum::Brotli]
    );

    expect($results)->toBeArray();
    expect($results)->toHaveKeys(['gzip', 'br']);
    expect($results['gzip'])->toBeInstanceOf(CompressionResult::class);
    expect($results['br'])->toBeInstanceOf(CompressionResult::class);
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

// 2.3. Single data + algorithms with levels
test('compress single data with algorithms and custom levels', function () {
    $results = Compressor::compress('Test data', [
        CompressionAlgorithmEnum::Gzip => 5,
        CompressionAlgorithmEnum::Brotli => 6,
    ]);

    expect($results)->toBeArray();
    expect($results)->toHaveKeys(['gzip', 'br']);
    expect($results['gzip']->algorithm)->toBe(CompressionAlgorithmEnum::Gzip);
    expect($results['br']->algorithm)->toBe(CompressionAlgorithmEnum::Brotli);
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

// Array of data + multiple algorithms
test('compress array of data with multiple algorithms', function () {
    $results = Compressor::compress(
        ['data1', 'data2'],
        [CompressionAlgorithmEnum::Gzip, CompressionAlgorithmEnum::Brotli]
    );

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);

    foreach ($results as $dataResults) {
        expect($dataResults)->toBeArray();
        expect($dataResults)->toHaveKeys(['gzip', 'br']);
    }
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

// Decompression tests
test('decompress single data with single algorithm', function () {
    $compressed = Compressor::compress('Hello!', CompressionAlgorithmEnum::Gzip);
    $decompressed = Compressor::decompress($compressed->content, CompressionAlgorithmEnum::Gzip);

    expect($decompressed)->toBe('Hello!');
});

test('decompress array of data with single algorithm', function () {
    $data = ['data1', 'data2'];
    $compressed = Compressor::compress($data, CompressionAlgorithmEnum::Gzip);

    $compressedStrings = array_map(fn($r) => $r->content, $compressed);
    $decompressed = Compressor::decompress($compressedStrings, CompressionAlgorithmEnum::Gzip);

    expect($decompressed)->toBe($data);
});

test('compress with associative keys preserves keys', function () {
    $data = ['key1' => 'value1', 'key2' => 'value2'];
    $results = Compressor::compress($data, CompressionAlgorithmEnum::Gzip);

    expect($results)->toHaveKeys(['key1', 'key2']);
});

test('compress file without file:// prefix works', function () {
    $file = $this->testDir . '/test.txt';
    file_put_contents($file, 'Content');

    $result = Compressor::compress($file, CompressionAlgorithmEnum::Gzip);

    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->isFile)->toBeTrue();
});
