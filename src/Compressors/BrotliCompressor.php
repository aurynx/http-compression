<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Compressors;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Contracts\CompressorInterface;
use Aurynx\HttpCompression\Contracts\StreamCompressorInterface;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\ErrorCodeEnum;

final class BrotliCompressor implements CompressorInterface, StreamCompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            $ext = $algorithm->getRequiredExtension();

            throw new CompressionException(
                sprintf('%s extension not available; install/enable ext-%s', $ext, $ext),
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        $result = brotli_compress($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Brotli compression failed',
                ErrorCodeEnum::COMPRESSION_FAILED->value,
            );
        }

        return $result;
    }

    public function decompress(string $content): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            $ext = $algorithm->getRequiredExtension();

            throw new CompressionException(
                sprintf('%s extension not available; install/enable ext-%s', $ext, $ext),
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        $result = brotli_uncompress($content);

        if ($result === false) {
            throw new CompressionException(
                'Brotli decompression failed',
                ErrorCodeEnum::DECOMPRESSION_FAILED->value,
            );
        }

        return $result;
    }

    public function compressStream($stream, ?int $level = null): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            $ext = $algorithm->getRequiredExtension();

            throw new CompressionException(
                sprintf('%s extension not available; install/enable ext-%s', $ext, $ext),
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        if (!is_resource($stream)) {
            throw new CompressionException(
                'Stream must be a valid resource',
                ErrorCodeEnum::COMPRESSION_FAILED->value,
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        // Brotli doesn't have a native stream filter in PHP
        // Read the entire stream into memory and compress
        // For large files, caller should use chunked reading
        rewind($stream);
        $content = stream_get_contents($stream);

        if ($content === false) {
            throw new CompressionException(
                'Failed to read stream content',
                ErrorCodeEnum::COMPRESSION_FAILED->value,
            );
        }

        return $this->compress($content, $level);
    }


    public function getAlgorithm(): AlgorithmEnum
    {
        return AlgorithmEnum::Brotli;
    }
}
