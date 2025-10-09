<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

/**
 * Example: Batch compression with graceful error handling.
 *
 * Agents: use this for background jobs or user-facing bulk compression.
 */

$builder = new CompressionBuilder()
    ->graceful()
    ->addMany([
        'Document 1: Hello world!',
        'Document 2: Example data',
        'Document 3: Another string to compress',
    ], [
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11,
        AlgorithmEnum::Zstd->value => 3,
    ]);

$results = $builder->compress();

foreach ($results as $result) {
    $id = $result->getIdentifier();

    if ($result->isOk()) {
        echo "✓ $id: all algorithms succeeded\n";
    } elseif ($result->isPartial()) {
        echo "⚠ $id: partial success\n";
        foreach ($result->getAlgorithmErrors() as $algo => $error) {
            echo "   ✗ $algo: {$error['message']}\n";
        }
    } else {
        echo "✗ $id: failed ({$result->getError()?->getMessage()})\n";
    }
}
