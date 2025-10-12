<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Contracts;

/**
 * Optional interface for compressors that can write compressed output directly to a provided sink stream.
 */
interface SinkCompressorInterface extends CompressorInterface
{
    /**
     * @param resource|string $input Readable stream resource or string content
     * @param resource $sink Writable stream resource to write compressed data into
     * @param int|null $level Compression level (null = default)
     */
    public function compressToStream($input, $sink, ?int $level = null): void;
}
