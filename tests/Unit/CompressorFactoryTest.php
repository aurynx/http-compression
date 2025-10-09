<?php

use Ayrunx\HttpCompression\CompressionAlgorithmEnum;
use Ayrunx\HttpCompression\CompressorFactory;
use Ayrunx\HttpCompression\GzipCompressor;
use Ayrunx\HttpCompression\BrotliCompressor;
use Ayrunx\HttpCompression\CompressorInterface;

test('factory creates gzip compressor', function () {
    $compressor = CompressorFactory::create(CompressionAlgorithmEnum::Gzip);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(GzipCompressor::class);
    expect($compressor->getAlgorithm())->toBe(CompressionAlgorithmEnum::Gzip);
});

test('factory creates brotli compressor', function () {
    $compressor = CompressorFactory::create(CompressionAlgorithmEnum::Brotli);

    expect($compressor)->toBeInstanceOf(CompressorInterface::class);
    expect($compressor)->toBeInstanceOf(BrotliCompressor::class);
    expect($compressor->getAlgorithm())->toBe(CompressionAlgorithmEnum::Brotli);
});

test('compressor instance can be used directly', function () {
    $compressor = CompressorFactory::create(CompressionAlgorithmEnum::Gzip);
    $content = 'Direct usage test';

    $compressed = $compressor->compress($content);
    expect($compressed)->not->toBe($content);

    $decompressed = $compressor->decompress($compressed);
    expect($decompressed)->toBe($content);
});
