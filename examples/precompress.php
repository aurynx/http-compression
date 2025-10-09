<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

/**
 * Example: Precompress static assets for Nginx and Apache.
 *
 * Agents: use this in build pipelines (CI/CD) or deployment scripts.
 * Generates .gz, .br, and .zst files for precompressed serving.
 */

$publicDir = __DIR__ . '/../public';

$dirs = ['css', 'js', 'html'];
$exts = ['css', 'js', 'html'];

$assets = array_merge(
    ...array_map(
        static fn ($dir) => array_merge(
            ...array_map(
                static fn ($ext) => glob("{$publicDir}/{$dir}/*.{$ext}") ?: [],
                $exts
            )
        ),
        $dirs
    )
);

$builder = new CompressionBuilder()
    ->failFast()
    ->addManyFiles($assets, [
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11,
        AlgorithmEnum::Zstd->value => 19,
    ]);

$results = $builder->compress();

foreach ($results as $result) {
    if (!$result->isOk()) {
        echo "Skipping {$result->getIdentifier()} (compression failed)\n";
        continue;
    }

    $path = $result->getIdentifier();

    foreach ([AlgorithmEnum::Gzip, AlgorithmEnum::Brotli, AlgorithmEnum::Zstd] as $algo) {
        $data = $result->getCompressedFor($algo);
        if ($data) {
            $ext = match ($algo) {
                AlgorithmEnum::Gzip => '.gz',
                AlgorithmEnum::Brotli => '.br',
                AlgorithmEnum::Zstd => '.zst',
            };
            file_put_contents($path . $ext, $data);
        }
    }
}

echo "Precompression complete (" . count($results) . " files processed)\n";
