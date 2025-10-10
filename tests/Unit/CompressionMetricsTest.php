<?php

declare(strict_types=1);

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;

// Helper function to get first result from associative array
function firstResult(array $results)
{
    return array_values($results)[0];
}

test('compression actually reduces data size', function () {
    $content = str_repeat('Hello, World! This is test data. ', 100); // ~3.3KB repetitive data

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result     = array_values($results)[0];
    $compressed = $result->getCompressedFor(AlgorithmEnum::Gzip);

    expect(strlen($compressed))->toBeLessThan(strlen($content));
    expect($result->isEffective(AlgorithmEnum::Gzip))->toBeTrue();
});

test('getOriginalSize returns correct uncompressed size', function () {
    $content = 'Test data for compression';

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);

    expect($result->getOriginalSize())->toBe(strlen($content));
});

test('getCompressedSize returns correct compressed size', function () {
    $content = str_repeat('A', 1000);

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result     = firstResult($results);
    $compressed = $result->getCompressedFor(AlgorithmEnum::Gzip);

    expect($result->getCompressedSize(AlgorithmEnum::Gzip))->toBe(strlen($compressed));
});

test('getCompressionRatio returns value between 0 and 1', function () {
    $content = str_repeat('Test data. ', 100);

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);
    $ratio  = $result->getCompressionRatio(AlgorithmEnum::Gzip);

    expect($ratio)->toBeFloat();
    expect($ratio)->toBeGreaterThan(0.0);
    expect($ratio)->toBeLessThan(1.0);
});

test('getSavedBytes returns positive value for good compression', function () {
    $content = str_repeat('Repetitive data! ', 200);

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);
    $saved  = $result->getSavedBytes(AlgorithmEnum::Gzip);

    expect($saved)->toBeInt();
    expect($saved)->toBeGreaterThan(0);
});

test('getCompressionPercentage returns meaningful percentage', function () {
    $content = str_repeat('Lorem ipsum dolor sit amet. ', 50);

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result     = firstResult($results);
    $percentage = $result->getCompressionPercentage(AlgorithmEnum::Gzip);

    expect($percentage)->toBeFloat();
    expect($percentage)->toBeGreaterThan(0.0);
    expect($percentage)->toBeLessThan(100.0);
});

test('isEffective returns true when compression saves space', function () {
    $content = str_repeat('AAAAAAAAAA', 100);

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);

    expect($result->isEffective(AlgorithmEnum::Gzip))->toBeTrue();
});

test('metrics return null for non-existent algorithm', function () {
    $builder = new CompressionBuilder();
    $builder->add('Test', AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);

    expect($result->getCompressedSize(AlgorithmEnum::Brotli))->toBeNull();
    expect($result->getCompressionRatio(AlgorithmEnum::Brotli))->toBeNull();
    expect($result->getSavedBytes(AlgorithmEnum::Brotli))->toBeNull();
    expect($result->getCompressionPercentage(AlgorithmEnum::Brotli))->toBeNull();
    expect($result->isEffective(AlgorithmEnum::Brotli))->toBeNull();
});

test('metrics work with multiple algorithms', function () {
    $content = str_repeat('Multiple algorithm test. ', 100);

    $builder = new CompressionBuilder();
    $builder->add($content, [
        AlgorithmEnum::Gzip->value => 6,
    ]);
    $results = $builder->compress();

    $result = firstResult($results);

    expect($result->getOriginalSize())->toBe(strlen($content));
    expect($result->getCompressedSize(AlgorithmEnum::Gzip))->toBeInt();
    expect($result->isEffective(AlgorithmEnum::Gzip))->toBeTrue();
});

test('file compression includes metrics', function () {
    $testFile = sys_get_temp_dir() . '/metrics_test_' . uniqid() . '.txt';
    $content  = str_repeat('File compression test data. ', 50);
    file_put_contents($testFile, $content);

    try {
        $builder = new CompressionBuilder();
        $builder->addFile($testFile, AlgorithmEnum::Gzip);
        $results = $builder->compress();

        $result = firstResult($results);

        expect($result->getOriginalSize())->toBe(strlen($content));
        expect($result->getCompressedSize(AlgorithmEnum::Gzip))->toBeLessThan(strlen($content));
        expect($result->isEffective(AlgorithmEnum::Gzip))->toBeTrue();
    } finally {
        @unlink($testFile);
    }
});

test('highly compressible data shows good compression ratio', function () {
    // Very repetitive data should compress well
    $content = str_repeat('A', 10000);

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);

    // Should achieve at least 90% compression on repetitive data
    $percentage = $result->getCompressionPercentage(AlgorithmEnum::Gzip);
    expect($percentage)->toBeGreaterThan(90.0);
});

test('compression ratio calculation is accurate', function () {
    $content = str_repeat('Test ', 200); // 1000 bytes

    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $results = $builder->compress();

    $result = firstResult($results);

    $originalSize   = $result->getOriginalSize();
    $compressedSize = $result->getCompressedSize(AlgorithmEnum::Gzip);
    $ratio          = $result->getCompressionRatio(AlgorithmEnum::Gzip);

    // Manual calculation should match
    expect($ratio)->toBe($compressedSize / $originalSize);
});
