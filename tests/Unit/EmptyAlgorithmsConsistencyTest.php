<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\ErrorCode;

it('ensures withDefaultAlgorithms([]) throws EMPTY_ALGORITHMS with stable message', function () {
    $builder = new CompressionBuilder();

    try {
        $builder->withDefaultAlgorithms([]);
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(ErrorCode::EMPTY_ALGORITHMS->value);
        expect($e->getMessage())->toBe('At least one compression algorithm must be specified');
    }
});

it('ensures replaceAlgorithms($id, []) throws the same EMPTY_ALGORITHMS with identical message', function () {
    $builder = new CompressionBuilder();
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
