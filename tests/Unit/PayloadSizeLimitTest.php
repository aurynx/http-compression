<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\CompressionAlgorithmEnum;
use Ayrunx\HttpCompression\CompressionBuilder;
use Ayrunx\HttpCompression\CompressionErrorCode;
use Ayrunx\HttpCompression\CompressionException;

it('throws PAYLOAD_TOO_LARGE for raw content over limit (failFast)', function () {
    $builder = new CompressionBuilder(maxBytes: 10);
    $builder->withDefaultAlgorithms(CompressionAlgorithmEnum::Gzip);

    $builder->add(str_repeat('A', 11));

    try {
        $builder->compress();
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(CompressionErrorCode::PAYLOAD_TOO_LARGE->value);
        expect($e->getMessage())->toContain('Content size');
    }
});

it('throws PAYLOAD_TOO_LARGE for file over limit (failFast)', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'httpc_');
    file_put_contents($tmp, str_repeat('B', 1024)); // 1KB

    $builder = new CompressionBuilder(maxBytes: 512);
    $builder->withDefaultAlgorithms(CompressionAlgorithmEnum::Gzip);
    $builder->addFile($tmp);

    try {
        $builder->compress();
        expect()->fail('Expected CompressionException was not thrown');
    } catch (CompressionException $e) {
        expect($e->getCode())->toBe(CompressionErrorCode::PAYLOAD_TOO_LARGE->value);
        expect($e->getMessage())->toContain('File size');
    } finally {
        @unlink($tmp);
    }
});
