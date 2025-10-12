<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\OverwritePolicyEnum;
use Aurynx\HttpCompression\Support\FileWriter;

it('writeToPath with policy Fail throws when target exists and keeps original content', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_fw_fail_' . uniqid('', true);
    expect(@mkdir($dir, 0700, true))->toBeTrue();

    $path = $dir . DIRECTORY_SEPARATOR . 'file.txt';
    file_put_contents($path, 'old');

    $fn = function () use ($path): void {
        FileWriter::writeToPath(
            path: $path,
            data: 'new',
            policy: OverwritePolicyEnum::Fail,
            permissions: null,
            allowCreateDirs: true,
        );
    };

    expect($fn)->toThrow(CompressionException::class);
    expect(file_get_contents($path))->toBe('old');

    @unlink($path);
    @rmdir($dir);
});

it('writeToPath with policy Replace overwrites existing target atomically', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_fw_replace_' . uniqid('', true);
    expect(@mkdir($dir, 0700, true))->toBeTrue();

    $path = $dir . DIRECTORY_SEPARATOR . 'file.txt';
    file_put_contents($path, 'old');

    FileWriter::writeToPath(
        path: $path,
        data: 'new',
        policy: OverwritePolicyEnum::Replace,
        permissions: null,
        allowCreateDirs: true,
    );

    expect(file_get_contents($path))->toBe('new');

    @unlink($path);
    @rmdir($dir);
});

it('writeToPath with policy Skip keeps existing target', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_fw_skip_' . uniqid('', true);
    expect(@mkdir($dir, 0700, true))->toBeTrue();

    $path = $dir . DIRECTORY_SEPARATOR . 'file.txt';
    file_put_contents($path, 'old');

    FileWriter::writeToPath(
        path: $path,
        data: 'new',
        policy: OverwritePolicyEnum::Skip,
        permissions: null,
        allowCreateDirs: true,
    );

    expect(file_get_contents($path))->toBe('old');

    @unlink($path);
    @rmdir($dir);
});

it('writeAll throws on invalid basename (slash, dot, empty)', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_fw_basename_' . uniqid('', true);
    expect(@mkdir($dir, 0700, true))->toBeTrue();

    $entries = [
        [ 'algo' => AlgorithmEnum::Gzip, 'data' => 'payload' ],
    ];

    // Slash in basename
    $fn1 = function () use ($dir, $entries): void {
        FileWriter::writeAll(
            directory: $dir,
            basename: 'bad/name',
            entries: $entries,
            policy: OverwritePolicyEnum::Fail,
            atomicAll: true,
            permissions: null,
            allowCreateDirs: true,
        );
    };
    expect($fn1)->toThrow(CompressionException::class);

    // Dot basename
    $fn2 = function () use ($dir, $entries): void {
        FileWriter::writeAll(
            directory: $dir,
            basename: '.',
            entries: $entries,
            policy: OverwritePolicyEnum::Fail,
            atomicAll: true,
            permissions: null,
            allowCreateDirs: true,
        );
    };
    expect($fn2)->toThrow(CompressionException::class);

    // Empty basename
    $fn3 = function () use ($dir, $entries): void {
        FileWriter::writeAll(
            directory: $dir,
            basename: '',
            entries: $entries,
            policy: OverwritePolicyEnum::Fail,
            atomicAll: true,
            permissions: null,
            allowCreateDirs: true,
        );
    };
    expect($fn3)->toThrow(CompressionException::class);

    @rmdir($dir);
});
