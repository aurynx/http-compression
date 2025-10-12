<?php

declare(strict_types=1);

use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Support\AlgorithmMetadata;

it('provides correct metadata for gzip', function () {
    $meta = AlgorithmMetadata::for(AlgorithmEnum::Gzip);

    expect($meta->requiredPhpExtension)->toBe('zlib');
    expect($meta->fileExtension)->toBe('gz');
    expect($meta->contentEncoding)->toBe('gzip');
    expect($meta->minLevel)->toBe(1);
    expect($meta->maxLevel)->toBe(9);
    expect($meta->defaultLevel)->toBe(6);
    expect($meta->cpuIntensive)->toBeFalse();
});

it('provides correct metadata for brotli', function () {
    $meta = AlgorithmMetadata::for(AlgorithmEnum::Brotli);

    expect($meta->requiredPhpExtension)->toBe('brotli');
    expect($meta->fileExtension)->toBe('br');
    expect($meta->contentEncoding)->toBe('br');
    expect($meta->minLevel)->toBe(0);
    expect($meta->maxLevel)->toBe(11);
    expect($meta->defaultLevel)->toBe(4);
    expect($meta->cpuIntensive)->toBeTrue();
});

it('provides correct metadata for zstd', function () {
    $meta = AlgorithmMetadata::for(AlgorithmEnum::Zstd);

    expect($meta->requiredPhpExtension)->toBe('zstd');
    expect($meta->fileExtension)->toBe('zst');
    expect($meta->contentEncoding)->toBe('zstd');
    expect($meta->minLevel)->toBe(1);
    expect($meta->maxLevel)->toBe(22);
    expect($meta->defaultLevel)->toBe(3);
    expect($meta->cpuIntensive)->toBeTrue();
});
