<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Example: Compress JSON API response according to client's Accept-Encoding.
 *
 * Agents: use this as a base for dynamic HTTP responses.
 * Prefer order: Brotli > Zstd > Gzip > Identity.
 */

$json = json_encode(['status' => 'ok', 'timestamp' => time()]);
$accept = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

// Build config based on Accept-Encoding
$algo = str_contains($accept, 'br') ? AlgorithmEnum::Brotli : AlgorithmEnum::Gzip;

$result = CompressorFacade::once()
    ->data($json)
    ->withAlgorithm($algo, $algo->getDefaultLevel())
    ->compress();

echo $result->getData($algo);
