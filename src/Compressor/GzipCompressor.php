<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression\Compressor;

use Ayrunx\HttpCompression\CompressionAlgorithmEnum;
use Ayrunx\HttpCompression\CompressionException;
use Ayrunx\HttpCompression\CompressorInterface;

final class GzipCompressor implements CompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            throw new CompressionException(
                sprintf('%s extension not available', $algorithm->getRequiredExtension())
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        $result = gzencode($content, $level);

        if ($result === false) {
            throw new CompressionException('Gzip compression failed');
        }

        return $result;
    }

    public function decompress(string $content): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            throw new CompressionException(
                sprintf('%s extension not available', $algorithm->getRequiredExtension())
            );
        }

        $result = gzdecode($content);

        if ($result === false) {
            throw new CompressionException('Gzip decompression failed');
        }

        return $result;
    }

    public function getAlgorithm(): CompressionAlgorithmEnum
    {
        return CompressionAlgorithmEnum::Gzip;
    }
}
