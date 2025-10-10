<?php

declare(strict_types=1);

use Aurynx\HttpCompression\AlgorithmEnum as Algo;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionResultDto as Result;
use Aurynx\HttpCompression\ErrorCodeEnum as Err;

it('keeps codes for partial errors (brotli unavailable)', function () {
    $builder = new CompressionBuilder();
    $builder->setFailFast(false);
    // both algorithms; gzip should succeed, brotli may be unavailable on CI
    $builder->withDefaultAlgorithms([
        'gzip' => Algo::Gzip->getDefaultLevel(),
        'br'   => Algo::Brotli->getDefaultLevel(),
    ]);
    $builder->add('hello');

    $results = $builder->compress();

    expect($results)->toBeArray();
    $result = $results[array_key_first($results)];
    expect($result)->toBeInstanceOf(Result::class);
    // If brotli is unavailable, we should have partial errors with structured entry for 'br'
    if (!extension_loaded('brotli')) {
        expect($result->isPartial())->toBeTrue();
        $errors = $result->getErrors();
        expect($errors)->toHaveKey('br');
        expect($errors['br'])->toHaveKey('code');
        expect($errors['br'])->toHaveKey('message');
        expect($errors['br']['code'])->toBe(Err::ALGORITHM_UNAVAILABLE->value);
    } else {
        // If brotli is available locally, the result should be full success (no errors)
        expect($result->isOk())->toBeTrue();
        expect($result->getErrors())->toBe([]);
    }
});

it('keeps code for complete failure (all algorithms fail)', function () {
    $builder = new CompressionBuilder();
    $builder->setFailFast(false);
    // Only brotli; when unavailable, complete failure is expected
    $builder->withDefaultAlgorithms(['br' => Algo::Brotli->getDefaultLevel()]);
    $builder->add('world');

    $results = $builder->compress();

    expect($results)->toBeArray();
    $result = $results[array_key_first($results)];
    expect($result)->toBeInstanceOf(Result::class);

    if (!extension_loaded('brotli')) {
        expect($result->isError())->toBeTrue();
        $errors = $result->getErrors();
        expect($errors)->toHaveKey('_error');
        expect($errors['_error']['code'])->toBe(Err::ALGORITHM_UNAVAILABLE->value);
        expect($errors['_error']['message'])->toBeString();
    } else {
        // On platforms with brotli available, this should be ok
        expect($result->isOk())->toBeTrue();
    }
});
