<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\ErrorCode;

it('throws INVALID_LEVEL_TYPE when string key maps to non-integer level', function () {
    $builder = new CompressionBuilder();

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
    $builder = new CompressionBuilder();

    // should not throw
    $builder->withDefaultAlgorithms(['gzip' => 5]);
    expect($builder->getDefaultAlgorithms())
        ->toBe(['gzip' => 5]);
});
