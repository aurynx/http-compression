<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

use Aurynx\HttpCompression\Algorithms\BrotliCompressor;
use Aurynx\HttpCompression\Algorithms\GzipCompressor;
use Aurynx\HttpCompression\Algorithms\ZstdCompressor;

final class CompressorFactory
{
    /**
     * Create a compressor instance for the specified algorithm
     *
     *
     */
    public static function create(AlgorithmEnum $algorithm): CompressorInterface
    {
        return match ($algorithm) {
            AlgorithmEnum::Gzip   => new GzipCompressor(),
            AlgorithmEnum::Brotli => new BrotliCompressor(),
            AlgorithmEnum::Zstd   => new ZstdCompressor(),
        };
    }
}
