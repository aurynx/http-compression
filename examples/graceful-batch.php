<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Example: Batch compression with graceful error handling.
 *
 * Agents: use this for background jobs or user-facing bulk compression.
 */

$result = CompressorFacade::make()
    ->addGlob(__DIR__ . '/data/**/*.json')
    ->withDefaultConfig(ItemConfig::create()->withGzip(6)->withBrotli(11)->build())
    ->failFast(false)
    ->inMemory()
    ->compress();

foreach ($result->failures() as $id => $item) {
    fwrite(STDERR, "Failed: {$id} - " . $item->getFailureReason()?->getMessage() . "\n");
}
