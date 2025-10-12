<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\CompressorFacade;

it('saves gzip with saveAllTo() and creates expected file', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_saveall_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $basename = 'index.html';
    $data = str_repeat('Hello world! ', 1000);

    CompressorFacade::once()
        ->data($data)
        ->withGzip(6)
        ->saveAllTo($dir, $basename);

    $gz = $dir . DIRECTORY_SEPARATOR . $basename . '.gz';
    expect(file_exists($gz))->toBeTrue();
    expect(filesize($gz))->toBeGreaterThan(0);

    @unlink($gz);
    @rmdir($dir);
});

it('respects overwritePolicy=fail and throws if target exists', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_overwrite_fail_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $basename = 'index.html';
    $gz = $dir . DIRECTORY_SEPARATOR . $basename . '.gz';

    // Pre-create a target file
    file_put_contents($gz, 'stub');

    $data = 'payload';

    $fn = function () use ($dir, $basename, $data): void {
        CompressorFacade::once()
            ->data($data)
            ->withGzip(6)
            ->saveAllTo($dir, $basename, [
                'overwritePolicy' => 'fail',
            ]);
    };

    expect($fn)->toThrow(CompressionException::class);

    @unlink($gz);
    @rmdir($dir);
});

it('respects overwritePolicy=skip and keeps existing target', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_overwrite_skip_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $basename = 'index.html';
    $gz = $dir . DIRECTORY_SEPARATOR . $basename . '.gz';

    // Pre-create with known content
    file_put_contents($gz, 'original');

    $data = str_repeat('x', 5000);

    CompressorFacade::once()
        ->data($data)
        ->withGzip(6)
        ->saveAllTo($dir, $basename, [
            'overwritePolicy' => 'skip',
        ]);

    // File should remain with original content
    expect(file_exists($gz))->toBeTrue();
    expect(file_get_contents($gz))->toBe('original');

    @unlink($gz);
    @rmdir($dir);
});

it('saveCompressed() saves next to source file for file() input', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_savecompressed_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $src = $dir . DIRECTORY_SEPARATOR . 'page.html';
    file_put_contents($src, str_repeat('A', 10000));

    CompressorFacade::once()
        ->file($src)
        ->withGzip(6)
        ->saveCompressed();

    $gz = $src . '.gz';
    expect(file_exists($gz))->toBeTrue();
    expect(filesize($gz))->toBeGreaterThan(0);

    @unlink($gz);
    @unlink($src);
    @rmdir($dir);
});

it('does not fail when optional brotli is unavailable (tryBrotli) and writes required gzip', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_optional_brotli_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $basename = 'asset.txt';
    $data = str_repeat('optional-brotli ', 1000);

    // Required gzip + optional brotli
    CompressorFacade::once()
        ->data($data)
        ->withGzip(6)
        ->tryBrotli(5)
        ->saveAllTo($dir, $basename, [
            'overwritePolicy' => 'replace',
        ]);

    $gz = $dir . DIRECTORY_SEPARATOR . $basename . '.gz';
    expect(file_exists($gz))->toBeTrue();
    expect(filesize($gz))->toBeGreaterThan(0);

    // .br may or may not exist depending on ext-brotli presence — no assertion

    // Cleanup
    @unlink($gz);
    $br = $dir . DIRECTORY_SEPARATOR . $basename . '.br';
    if (file_exists($br)) {
        @unlink($br);
    }
    @rmdir($dir);
});

it('trySaveAllTo() returns false and exposes context when directory cannot be created', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_try_dir_' . uniqid('', true) . DIRECTORY_SEPARATOR . 'nested';
    // Do not pre-create parent, and disallow creation via options

    $basename = 'file.txt';

    $facade = CompressorFacade::once()
        ->data(str_repeat('X', 2048))
        ->withGzip(6);

    $ok = $facade->trySaveAllTo($dir, $basename, [
        'allowCreateDirs' => false,
    ]);

    expect($ok)->toBeFalse();

    $err = $facade->getLastError();
    expect($err)->not->toBeNull();
    expect($err?->getPath())->toBe($dir);
});
