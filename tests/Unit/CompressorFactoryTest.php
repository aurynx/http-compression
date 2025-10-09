<?php

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\Factory;
use Ayrunx\HttpCompression\Compressor\GzipCompressor;
use Ayrunx\HttpCompression\Compressor\BrotliCompressor;
use Ayrunx\HttpCompression\CompressorInterface;

test('factory creates gzip compressor', function () {
    $compressor = Factory::create(AlgorithmEnum::Gzip);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(GzipCompressor::class);
    expect($compressor->getAlgorithm())->toBe(AlgorithmEnum::Gzip);
});

test('factory creates brotli compressor', function () {
    $compressor = Factory::create(AlgorithmEnum::Brotli);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(BrotliCompressor::class);
    expect($compressor->getAlgorithm())->toBe(AlgorithmEnum::Brotli);
});

test('compressor instance can be used directly', function () {
    $compressor = Factory::create(AlgorithmEnum::Gzip);
    $content = 'Direct usage test';

    $compressed = $compressor->compress($content);
    expect($compressed)->not->toBe($content);

    $decompressed = $compressor->decompress($compressed);
    expect($decompressed)->toBe($content);
});
