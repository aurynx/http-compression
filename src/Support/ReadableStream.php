<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

use Aurynx\HttpCompression\CompressionException;

/**
 * Thin, typed wrapper for readable stream resources.
 */
final class ReadableStream
{
    /**
     * @param resource $handle A readable stream resource
     */
    private function __construct(private $handle)
    {
    }

    /**
     * Wrap an existing resource as ReadableStream.
     *
     * @param resource $handle
     */
    public static function fromResource($handle): self
    {
        if (!is_resource($handle) || get_resource_type($handle) !== 'stream') {
            throw new CompressionException('ReadableStream expects a stream resource');
        }

        $meta = stream_get_meta_data($handle);
        $mode = (string)$meta['mode'];

        // basic check: mode contains 'r' or is read/write '+'
        if ($mode === '' || (str_contains($mode, 'r') === false && str_contains($mode, '+') === false)) {
            throw new CompressionException('Stream is not open in readable mode');
        }

        return new self($handle);
    }

    /**
     * Get the underlying resource handle.
     *
     * @return resource
     */
    public function handle()
    {
        return $this->handle;
    }
}
