<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

use Aurynx\HttpCompression\CompressionException;

/**
 * Thin, typed wrapper for writable stream resources.
 */
final class WritableStream
{
    /**
     * @param resource $handle A writable stream resource
     */
    private function __construct(private $handle)
    {
    }

    /**
     * Wrap an existing resource as WritableStream.
     *
     * @param resource $handle
     */
    public static function fromResource($handle): self
    {
        if (!is_resource($handle) || get_resource_type($handle) !== 'stream') {
            throw new CompressionException('WritableStream expects a stream resource');
        }
        $meta = stream_get_meta_data($handle);
        $mode = (string)$meta['mode'];

        // basic check: mode contains 'w' or 'a' or is read/write '+'
        if ($mode === '' || (
            str_contains($mode, 'w') === false &&
            str_contains($mode, 'a') === false &&
            str_contains($mode, '+') === false
        )) {
            throw new CompressionException('Stream is not open in writable mode');
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
