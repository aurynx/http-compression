<?php

declare(strict_types=1);

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\Algorithms\BrotliCompressor;
use Aurynx\HttpCompression\Algorithms\GzipCompressor;
use Aurynx\HttpCompression\Algorithms\ZstdCompressor;
use Aurynx\HttpCompression\CompressorFactory;
use Aurynx\HttpCompression\Contracts\CompressorInterface;

test('factory creates gzip compressor', function () {
    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(GzipCompressor::class);
    expect($compressor->getAlgorithm())->toBe(AlgorithmEnum::Gzip);
});

test('factory creates brotli compressor', function () {
    $compressor = CompressorFactory::create(AlgorithmEnum::Brotli);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(BrotliCompressor::class);
    expect($compressor->getAlgorithm())->toBe(AlgorithmEnum::Brotli);
});

test('factory creates zstd compressor', function () {
    $compressor = CompressorFactory::create(AlgorithmEnum::Zstd);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(ZstdCompressor::class);
    expect($compressor->getAlgorithm())->toBe(AlgorithmEnum::Zstd);
});

test('compressor instance can be used directly', function () {
    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    $content    = 'Direct usage test';

    $compressed = $compressor->compress($content);
    expect($compressed)->not->toBe($content);

    $decompressed = $compressor->decompress($compressed);
    expect($decompressed)->toBe($content);
});
