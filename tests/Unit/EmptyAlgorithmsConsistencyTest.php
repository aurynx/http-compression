<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\Builder;
use Ayrunx\HttpCompression\ErrorCode;
use Ayrunx\HttpCompression\CompressionException;

it('ensures withDefaultAlgorithms([]) throws EMPTY_ALGORITHMS with stable message', function () {
    $builder = new Builder();

    try {
        $builder->withDefaultAlgorithms([]);
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(ErrorCode::EMPTY_ALGORITHMS->value);
        expect($e->getMessage())->toBe('At least one compression algorithm must be specified');
    }
});

it('ensures replaceAlgorithms($id, []) throws the same EMPTY_ALGORITHMS with identical message', function () {
    $builder = new Builder();
    $builder->add('content');
    $id = $builder->getLastIdentifier();

    try {
        $builder->replaceAlgorithms($id, []);
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(ErrorCode::EMPTY_ALGORITHMS->value);
        expect($e->getMessage())->toBe('At least one compression algorithm must be specified');
    }
});
