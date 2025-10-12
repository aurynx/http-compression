<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Compressors;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Contracts\CompressorInterface;
use Aurynx\HttpCompression\Contracts\SinkCompressorInterface;
use Aurynx\HttpCompression\Contracts\StreamCompressorInterface;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\ErrorCodeEnum;

final class ZstdCompressor implements CompressorInterface, StreamCompressorInterface, SinkCompressorInterface
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

        if (!function_exists('zstd_compress')) {
            // Defensive: extension loaded but function unavailable
            throw new CompressionException(
                'Zstd functions not available despite extension being loaded',
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        $result = zstd_compress($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Zstd compression failed',
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

        if (!function_exists('zstd_uncompress')) {
            throw new CompressionException(
                'Zstd functions not available despite extension being loaded',
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        $result = zstd_uncompress($content);

        if ($result === false) {
            throw new CompressionException(
                'Zstd decompression failed',
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

        if (!function_exists('zstd_compress')) {
            throw new CompressionException(
                'Zstd functions not available despite extension being loaded',
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        // Zstd doesn't have a native stream filter in PHP
        // Read the entire stream into memory and compress
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

    public function compressToStream($input, $sink, ?int $level = null): void
    {
        if (!is_resource($sink)) {
            throw new CompressionException('Sink must be a writable stream', ErrorCodeEnum::COMPRESSION_FAILED->value);
        }

        $content = null;

        if (is_resource($input)) {
            rewind($input);
            $content = stream_get_contents($input);

            if ($content === false) {
                throw new CompressionException('Failed to read input stream', ErrorCodeEnum::COMPRESSION_FAILED->value);
            }
        } elseif (is_string($input)) {
            $content = $input;
        } else {
            throw new CompressionException('Invalid input type for compressToStream');
        }

        $compressed = $this->compress($content, $level);
        fwrite($sink, $compressed);
        fflush($sink);
    }

    public function getAlgorithm(): AlgorithmEnum
    {
        return AlgorithmEnum::Zstd;
    }
}
