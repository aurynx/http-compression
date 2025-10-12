<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Compressors;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Contracts\CompressorInterface;
use Aurynx\HttpCompression\Contracts\SinkCompressorInterface;
use Aurynx\HttpCompression\Contracts\StreamCompressorInterface;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\ErrorCodeEnum;

final class GzipCompressor implements CompressorInterface, StreamCompressorInterface, SinkCompressorInterface
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

        $result = gzencode($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Gzip compression failed',
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

        $result = gzdecode($content);

        if ($result === false) {
            throw new CompressionException(
                'Gzip decompression failed',
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

        // Create temporary memory stream for compressed output
        $outputStream = fopen('php://temp', 'r+b');

        if ($outputStream === false) {
            throw new CompressionException(
                'Failed to create temporary stream',
                ErrorCodeEnum::COMPRESSION_FAILED->value,
            );
        }

        // Use deflate filter for streaming compression
        $filter = stream_filter_append(
            $outputStream,
            'zlib.deflate',
            STREAM_FILTER_WRITE,
            ['level' => $level, 'window' => 15 + 16], // +16 for gzip header
        );

        if ($filter === false) {
            fclose($outputStream);

            throw new CompressionException(
                'Failed to attach compression filter',
                ErrorCodeEnum::COMPRESSION_FAILED->value,
            );
        }

        // Stream data through a compression filter
        rewind($stream);

        while (!feof($stream)) {
            $chunk = fread($stream, 8192);

            if ($chunk === false) {
                break;
            }

            fwrite($outputStream, $chunk);
        }

        stream_filter_remove($filter);

        // Read compressed data
        rewind($outputStream);
        $compressed = stream_get_contents($outputStream);
        fclose($outputStream);

        if ($compressed === false) {
            throw new CompressionException(
                'Failed to read compressed data',
                ErrorCodeEnum::COMPRESSION_FAILED->value,
            );
        }

        return $compressed;
    }

    public function compressToStream($input, $sink, ?int $level = null): void
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            $ext = $algorithm->getRequiredExtension();

            throw new CompressionException(
                sprintf('%s extension not available; install/enable ext-%s', $ext, $ext),
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        if (!is_resource($sink)) {
            throw new CompressionException('Sink must be a writable stream');
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        $filter = stream_filter_append(
            $sink,
            'zlib.deflate',
            STREAM_FILTER_WRITE,
            ['level' => $level, 'window' => 15 + 16],
        );

        if ($filter === false) {
            throw new CompressionException('Failed to attach gzip filter', ErrorCodeEnum::COMPRESSION_FAILED->value);
        }

        if (is_resource($input)) {
            rewind($input);

            while (!feof($input)) {
                $chunk = fread($input, 8192);

                if ($chunk === false) {
                    break;
                }
                fwrite($sink, $chunk);
            }
        } elseif (is_string($input)) {
            fwrite($sink, $input);
        } else {
            stream_filter_remove($filter);

            throw new CompressionException('Invalid input type for compressToStream');
        }

        stream_filter_remove($filter);
        fflush($sink);
    }

    public function getAlgorithm(): AlgorithmEnum
    {
        return AlgorithmEnum::Gzip;
    }
}
