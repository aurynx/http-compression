<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

/**
 * Example: Compress JSON API response according to client's Accept-Encoding.
 *
 * Agents: use this as a base for dynamic HTTP responses.
 * Prefer order: Brotli > Zstd > Gzip > Identity.
 */

$json = json_encode(['status' => 'ok', 'timestamp' => time()]);
$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

$algorithms = [];

// Detect accepted encodings
if (str_contains($acceptEncoding, 'br')) {
    $algorithms[AlgorithmEnum::Brotli->value] = 11;
}
if (str_contains($acceptEncoding, 'zstd')) {
    $algorithms[AlgorithmEnum::Zstd->value] = 3;
}
if (str_contains($acceptEncoding, 'gzip')) {
    $algorithms[AlgorithmEnum::Gzip->value] = 6;
}

// Fallback to gzip if no supported encodings
if (!$algorithms) {
    $algorithms[AlgorithmEnum::Gzip->value] = 6;
}

$builder = new CompressionBuilder()->graceful();
$builder->add($json, $algorithms);

$results = $builder->compress();
$result = $results[0] ?? null;

if (!$result || !$result->isOk()) {
    header('Content-Encoding: identity');
    header('Content-Type: application/json');
    echo $json;
    exit;
}

// Prefer best encoding
foreach ([AlgorithmEnum::Brotli, AlgorithmEnum::Zstd, AlgorithmEnum::Gzip] as $algo) {
    if ($compressed = $result->getCompressedFor($algo)) {
        header('Content-Encoding: ' . $algo->value);
        header('Content-Type: application/json');
        echo $compressed;
        exit;
    }
}

header('Content-Encoding: identity');
echo $json;
