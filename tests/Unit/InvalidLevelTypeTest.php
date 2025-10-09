<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\Builder;
use Ayrunx\HttpCompression\ErrorCode;
use Ayrunx\HttpCompression\CompressionException;

it('throws INVALID_LEVEL_TYPE when string key maps to non-integer level', function () {
    $builder = new Builder();

    try {
        $builder->withDefaultAlgorithms(['gzip' => '9']);
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(ErrorCode::INVALID_LEVEL_TYPE->value);
        expect($e->getMessage())->toContain('gzip');
        expect($e->getMessage())->toContain('got string');
    }
});

it('accepts integer level values', function () {
    $builder = new Builder();

    // should not throw
    $builder->withDefaultAlgorithms(['gzip' => 5]);
    expect($builder->getDefaultAlgorithms())
        ->toBe(['gzip' => 5]);
});
