<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressorFacade;

it('streamTo() writes gzip file for data() input (single algo)', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_streamto_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $target = $dir . DIRECTORY_SEPARATOR . 'out.gz';

    CompressorFacade::once()
        ->data(str_repeat('stream me ', 5000))
        ->withGzip(6)
        ->streamTo($target, [
            'overwritePolicy' => 'replace',
            'allowCreateDirs' => true,
        ]);

    expect(file_exists($target))->toBeTrue();
    expect(filesize($target))->toBeGreaterThan(0);

    @unlink($target);
    @rmdir($dir);
});

it('streamAllTo() writes all configured outputs to directory', function (): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hc_streamall_' . uniqid('', true);
    $ok = mkdir($dir, 0700, true);
    expect($ok)->toBeTrue();

    $basename = 'asset.bin';

    CompressorFacade::once()
        ->data(str_repeat('ABCDEFGH', 2000))
        ->withGzip(6)
        ->tryBrotli(4) // optional: may be skipped if ext-brotli not available
        ->streamAllTo($dir, $basename, [
            'overwritePolicy' => 'replace',
            'atomicAll' => true,
            'allowCreateDirs' => true,
        ]);

    $gz = $dir . DIRECTORY_SEPARATOR . $basename . '.gz';
    expect(file_exists($gz))->toBeTrue();
    expect(filesize($gz))->toBeGreaterThan(0);

    // .br is optional
    $br = $dir . DIRECTORY_SEPARATOR . $basename . '.br';
    if (file_exists($br)) {
        expect(filesize($br))->toBeGreaterThan(0);
        @unlink($br);
    }

    @unlink($gz);
    @rmdir($dir);
});
