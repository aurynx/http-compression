<?php

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\CompressionBuilder;
use Ayrunx\HttpCompression\CompressionResult;
use Ayrunx\HttpCompression\CompressorFactory;

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
    $builder = new CompressionBuilder();
    $builder->add('Hello, World!', AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(1);

    $result = array_values($results)[0];
    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->isOk())->toBeTrue();

    $compressed = $result->getCompressed();
    expect($compressed)->toHaveKey('gzip');
    expect($compressed['gzip'])->not->toBe('Hello, World!');
});

// 1.2. Single file + single algorithm
test('compress single file with single algorithm', function () {
    $file = $this->testDir . '/test.txt';
    file_put_contents($file, 'File content');

    $builder = new CompressionBuilder();
    $builder->addFile($file, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(1);

    $result = array_values($results)[0];
    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->isOk())->toBeTrue();
    expect($result->getCompressed())->toHaveKey('gzip');
});

// 1.3. Array of raw data + single algorithm
test('compress array of raw data with single algorithm', function () {
    $builder = new CompressionBuilder();
    $builder->addMany(['data1', 'data2', 'data3'], AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(CompressionResult::class);
        expect($result->isOk())->toBeTrue();
        expect($result->getCompressed())->toHaveKey('gzip');
    }
});

// 1.4. Array of files + single algorithm
test('compress array of files with single algorithm', function () {
    $file1 = $this->testDir . '/file1.txt';
    $file2 = $this->testDir . '/file2.txt';
    file_put_contents($file1, 'Content 1');
    file_put_contents($file2, 'Content 2');

    $builder = new CompressionBuilder();
    $builder->addManyFiles([$file1, $file2], AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(CompressionResult::class);
        expect($result->isOk())->toBeTrue();
    }
});

// 1.5. Mixed array (raw data + files) + single algorithm
test('compress mixed array with single algorithm', function () {
    $file = $this->testDir . '/test.txt';
    file_put_contents($file, 'File content');

    $builder = new CompressionBuilder();
    $builder->add('raw data', AlgorithmEnum::Gzip);
    $builder->addFile($file, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(CompressionResult::class);
        expect($result->isOk())->toBeTrue();
    }
});

// 2.2. Single data + array of algorithms
test('compress single data with multiple algorithms', function () {
    $builder = new CompressionBuilder();
    $builder->add('Test data', [AlgorithmEnum::Gzip, AlgorithmEnum::Brotli]);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(1);

    $result = array_values($results)[0];
    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->isOk())->toBeTrue();

    $compressed = $result->getCompressed();
    expect($compressed)->toHaveKey('gzip');
    expect($compressed)->toHaveKey('br');
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

// 2.3. Single data + algorithms with levels
test('compress single data with algorithms and custom levels', function () {
    $builder = new CompressionBuilder();
    $builder->add('Test data', [
        'gzip' => 5,
        'br' => 6,
    ]);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(1);

    $result = array_values($results)[0];
    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->isOk())->toBeTrue();

    $compressed = $result->getCompressed();
    expect($compressed)->toHaveKey('gzip');
    expect($compressed)->toHaveKey('br');
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

// Array of data + multiple algorithms
test('compress array of data with multiple algorithms', function () {
    $builder = new CompressionBuilder();
    $builder->addMany(['data1', 'data2'], [AlgorithmEnum::Gzip, AlgorithmEnum::Brotli]);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(CompressionResult::class);
        expect($result->isOk())->toBeTrue();

        $compressed = $result->getCompressed();
        expect($compressed)->toHaveKey('gzip');
        expect($compressed)->toHaveKey('br');
    }
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');

// Decompression tests
test('decompress single data with single algorithm', function () {
    $builder = new CompressionBuilder();
    $builder->add('Hello!', AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = array_values($results)[0];
    $compressed = $result->getCompressed()['gzip'];

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $decompressed = $compressor->decompress($compressed);

    expect($decompressed)->toBe('Hello!');
});

test('decompress array of data with single algorithm', function () {
    $data = ['data1', 'data2'];

    $builder = new CompressionBuilder();
    $builder->addMany($data, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $decompressed = [];

    foreach ($results as $result) {
        $compressed = $result->getCompressed()['gzip'];
        $decompressed[] = $compressor->decompress($compressed);
    }

    expect($decompressed)->toBe($data);
});

test('compress with associative keys preserves keys', function () {
    $data = ['key1' => 'value1', 'key2' => 'value2'];

    $builder = new CompressionBuilder();
    foreach ($data as $key => $value) {
        $builder->add($value, AlgorithmEnum::Gzip, $key);
    }
    $results = $builder->compress();

    expect($results)->toHaveKeys(['key1', 'key2']);
});

test('compress file without file:// prefix works', function () {
    $file = $this->testDir . '/test.txt';
    file_put_contents($file, 'Content');

    $builder = new CompressionBuilder();
    $builder->addFile($file, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(1);

    $result = array_values($results)[0];
    expect($result)->toBeInstanceOf(CompressionResult::class);
    expect($result->isOk())->toBeTrue();
});
