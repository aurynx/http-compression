<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Optional interface for compressors that support streaming I/O
 */
interface StreamCompressorInterface extends CompressorInterface
{
    /**
     * Compress content from a stream resource to a compressed string
     *
     * @param resource $stream Readable stream (e.g., fopen($path, 'rb'))
     * @param int|null $level Compression level (null = default)
     * @return string Compressed content
     */
    public function compressStream($stream, ?int $level = null): string;
}
