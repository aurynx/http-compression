<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

use Ayrunx\HttpCompression\Compressor\BrotliCompressor;
use Ayrunx\HttpCompression\Compressor\GzipCompressor;

final class CompressorFactory
{
    /**
     * Create a compressor instance for the specified algorithm
     *
     * @param CompressionAlgorithmEnum $algorithm
     * @return CompressorInterface
     */
    public static function create(CompressionAlgorithmEnum $algorithm): CompressorInterface
    {
        return match ($algorithm) {
            CompressionAlgorithmEnum::Gzip => new GzipCompressor(),
            CompressionAlgorithmEnum::Brotli => new BrotliCompressor(),
        };
    }
}
