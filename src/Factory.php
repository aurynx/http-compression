<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

use Ayrunx\HttpCompression\Compressor\BrotliCompressor;
use Ayrunx\HttpCompression\Compressor\GzipCompressor;
use Ayrunx\HttpCompression\Compressor\ZstdCompressor;

final class Factory
{
    /**
     * Create a compressor instance for the specified algorithm
     *
     * @param AlgorithmEnum  $algorithm
     *
     * @return CompressorInterface
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
