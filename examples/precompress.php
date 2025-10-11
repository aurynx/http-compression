<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Example: Precompress static assets for Nginx and Apache.
 *
 * Agents: use this in build pipelines (CI/CD) or deployment scripts.
 * Generates .gz, .br, and .zst files for precompressed serving.
 */

$config = ItemConfig::create()
    ->withGzip(9)
    ->withBrotli(11)
    ->withZstd(19)
    ->build();

$result = CompressorFacade::make()
    // Add your asset patterns here (portable: no GLOB_BRACE)
    ->addGlob(__DIR__ . '/public/**/*.js')
    ->addGlob(__DIR__ . '/public/**/*.css')
    ->addGlob(__DIR__ . '/public/**/*.html')
    // Default config for all matched files
    ->withDefaultConfig($config)
    // Save alongside originals; keep a directory structure
    ->toDir(__DIR__ . '/public', keepStructure: true)
    // Skip already compressed formats (images, archives, etc.)
    ->skipAlreadyCompressed()
    // Fail fast on errors in CI
    ->failFast(true)
    // Execute
    ->compress();

if (!$result->allOk()) {
    fwrite(STDERR, "Precompression failed\n");
    foreach ($result->failures() as $id => $item) {
        fwrite(STDERR, "  - {$id}: " . ($item->getFailureReason()?->getMessage() ?? 'unknown') . "\n");
    }
    exit(1);
}

// Report summary
$summary = $result->summary();
$success = $summary->getSuccessCount();
$total = $summary->getTotalItems();

echo "✓ Precompression complete\n";
echo "  Processed: {$total}\n";
echo "  Success: {$success}, Failed: {$summary->getFailureCount()}\n";
echo "  Median gzip ratio: " . round($summary->getMedianRatio(AlgorithmEnum::Gzip) * 100, 1) . "%\n";
