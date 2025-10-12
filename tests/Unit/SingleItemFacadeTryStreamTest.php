<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressorFacade;

it('tryStreamTo() returns false and sets lastError when directory cannot be created', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_trystream_dir_' . uniqid('', true) . DIRECTORY_SEPARATOR . 'nested';
    $target = $dir . DIRECTORY_SEPARATOR . 'asset.gz';

    $facade = CompressorFacade::once()
        ->data(str_repeat('Z', 10000))
        ->withGzip(6);

    $ok = $facade->tryStreamTo($target, [
        'allowCreateDirs' => false,
    ]);

    expect($ok)->toBeFalse();
    expect($facade->getLastError())->not->toBeNull();
    expect($facade->getLastError()?->getPath())->toBe(dirname($target));
});

it('tryStreamAllTo() returns true when write succeeds and false otherwise', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_trystreamall_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $facade = CompressorFacade::once()
        ->data(str_repeat('Hello world! ', 1000))
        ->withGzip(6);

    $ok = $facade->tryStreamAllTo($dir, 'index.html', [
        'overwritePolicy' => 'replace',
        'allowCreateDirs' => true,
    ]);

    expect($ok)->toBeTrue();

    // cleanup
    $gz = $dir . DIRECTORY_SEPARATOR . 'index.html.gz';
    if (file_exists($gz)) {
        @unlink($gz);
    }
    @rmdir($dir);
});
