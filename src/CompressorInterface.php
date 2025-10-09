<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

interface CompressorInterface
{
    /**
     * Compress content
     *
     * @param string $content Content to compress
     * @param int|null $level Compression level (null = default)
     * @return string Compressed content
     * @throws CompressionException
     */
    public function compress(string $content, ?int $level = null): string;

    /**
     * Decompress content
     *
     * @param string $content Compressed content
     * @return string Decompressed content
     * @throws CompressionException
     */
    public function decompress(string $content): string;

    /**
     * Get the algorithm type
     */
    public function getAlgorithm(): CompressionAlgorithmEnum;
}
