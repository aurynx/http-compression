<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\Builder;
use Ayrunx\HttpCompression\ErrorCode;
use Ayrunx\HttpCompression\CompressionException;

it('throws PAYLOAD_TOO_LARGE for raw content over limit (failFast)', function () {
    $builder = new Builder(maxBytes: 10);
    $builder->withDefaultAlgorithms(AlgorithmEnum::Gzip);

    $builder->add(str_repeat('A', 11));

    try {
        $builder->compress();
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(ErrorCode::PAYLOAD_TOO_LARGE->value);
        expect($e->getMessage())->toContain('Content size');
    }
});

it('throws PAYLOAD_TOO_LARGE for file over limit (failFast)', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'httpc_');
    file_put_contents($tmp, str_repeat('B', 1024)); // 1KB

    $builder = new Builder(maxBytes: 512);
    $builder->withDefaultAlgorithms(AlgorithmEnum::Gzip);
    $builder->addFile($tmp);

    try {
        $builder->compress();
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(ErrorCode::PAYLOAD_TOO_LARGE->value);
        expect($e->getMessage())->toContain('File size');
    } finally {
        @unlink($tmp);
    }
});
