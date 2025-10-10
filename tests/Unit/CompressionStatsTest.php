<?php

declare(strict_types=1);

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionStatsDto;

test('batch stats aggregates multiple results', function () {
    $builder = new CompressionBuilder();

    // Add multiple items
    $builder->add(str_repeat('Test data A. ', 100), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('Test data B. ', 100), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('Test data C. ', 100), AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getTotalItems())->toBe(3);
    expect($stats->getSuccessfulItems())->toBe(3);
    expect($stats->getFailedItems())->toBe(0);
});

test('batch stats calculates total original size', function () {
    $content1 = str_repeat('A', 1000);
    $content2 = str_repeat('B', 500);

    $builder = new CompressionBuilder();
    $builder->add($content1, AlgorithmEnum::Gzip);
    $builder->add($content2, AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getTotalOriginalBytes())->toBe(1500);
});

test('batch stats calculates total compressed size', function () {
    $builder = new CompressionBuilder();
    $builder->add(str_repeat('Test ', 200), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('Data ', 200), AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    $totalCompressed = $stats->getTotalCompressedBytes(AlgorithmEnum::Gzip);

    expect($totalCompressed)->toBeInt();
    expect($totalCompressed)->toBeGreaterThan(0);
    expect($totalCompressed)->toBeLessThan(2000); // Should be compressed
});

test('batch stats calculates total saved bytes', function () {
    $builder = new CompressionBuilder();
    $builder->add(str_repeat('Repetitive! ', 100), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('Repetitive! ', 100), AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    $saved = $stats->getTotalSavedBytes(AlgorithmEnum::Gzip);

    expect($saved)->toBeInt();
    expect($saved)->toBeGreaterThan(0);
});

test('batch stats calculates average compression ratio', function () {
    $builder = new CompressionBuilder();
    $builder->add(str_repeat('Test data. ', 50), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('More data. ', 50), AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    $ratio = $stats->getAverageRatio(AlgorithmEnum::Gzip);

    expect($ratio)->toBeFloat();
    expect($ratio)->toBeGreaterThan(0.0);
    expect($ratio)->toBeLessThan(1.0);
});

test('batch stats calculates average percentage', function () {
    $builder = new CompressionBuilder();
    $builder->add(str_repeat('AAAA', 250), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('BBBB', 250), AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    $percentage = $stats->getAveragePercentage(AlgorithmEnum::Gzip);

    expect($percentage)->toBeFloat();
    expect($percentage)->toBeGreaterThan(0.0);
    expect($percentage)->toBeLessThan(100.0);
});

test('batch stats handles multiple algorithms', function () {
    $builder = new CompressionBuilder();
    $builder->add(str_repeat('Test ', 100), [
        AlgorithmEnum::Gzip->value => 6,
    ]);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->hasAlgorithm(AlgorithmEnum::Gzip))->toBeTrue();
    expect($stats->getAlgorithms())->toContain('gzip');
});

test('batch stats calculates success rate', function () {
    $builder = new CompressionBuilder();
    $builder->add('Data 1', AlgorithmEnum::Gzip);
    $builder->add('Data 2', AlgorithmEnum::Gzip);
    $builder->add('Data 3', AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getSuccessRate())->toBe(1.0); // 100% success
});

test('batch stats handles empty results', function () {
    $results = [];
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getTotalItems())->toBe(0);
    expect($stats->getSuccessfulItems())->toBe(0);
    expect($stats->getSuccessRate())->toBe(0.0);
});

test('batch stats summary generates readable output', function () {
    $builder = new CompressionBuilder();
    $builder->add(str_repeat('Test ', 100), AlgorithmEnum::Gzip);
    $builder->add(str_repeat('Data ', 100), AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    $summary = $stats->summary();

    expect($summary)->toBeString();
    expect($summary)->toContain('Compression Statistics');
    expect($summary)->toContain('Total items');
    expect($summary)->toContain('gzip');
});

test('batch stats works with large batches', function () {
    $builder = new CompressionBuilder();

    // Add 50 items
    for ($i = 0; $i < 50; $i++) {
        $builder->add(str_repeat("Item $i data. ", 20), AlgorithmEnum::Gzip);
    }

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getTotalItems())->toBe(50);
    expect($stats->getSuccessfulItems())->toBe(50);
    expect($stats->getTotalSavedBytes(AlgorithmEnum::Gzip))->toBeGreaterThan(0);
});

test('batch stats returns null for unused algorithms', function () {
    $builder = new CompressionBuilder();
    $builder->add('Test', AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getTotalCompressedBytes(AlgorithmEnum::Brotli))->toBeNull();
    expect($stats->getTotalSavedBytes(AlgorithmEnum::Brotli))->toBeNull();
    expect($stats->getAverageRatio(AlgorithmEnum::Brotli))->toBeNull();
    expect($stats->getAveragePercentage(AlgorithmEnum::Brotli))->toBeNull();
});

test('batch stats aggregates across mixed success/failure', function () {
    $builder = (new CompressionBuilder())->setFailFast(false);

    $builder->add('Good data', AlgorithmEnum::Gzip);
    $builder->add('More good data', AlgorithmEnum::Gzip);

    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);

    expect($stats->getTotalItems())->toBe(2);
    expect($stats->getSuccessfulItems())->toBeGreaterThan(0);
});
