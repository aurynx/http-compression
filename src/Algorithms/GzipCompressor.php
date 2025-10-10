<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Algorithms;

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Contracts\CompressorInterface;
use Aurynx\HttpCompression\ErrorCodeEnum;

final class GzipCompressor implements CompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            $ext = $algorithm->getRequiredExtension();
            throw new CompressionException(
                sprintf('%s extension not available; install/enable ext-%s', $ext, $ext),
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        $result = gzencode($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Gzip compression failed',
                ErrorCodeEnum::COMPRESSION_FAILED->value
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
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value
            );
        }

        $result = gzdecode($content);

        if ($result === false) {
            throw new CompressionException(
                'Gzip decompression failed',
                ErrorCodeEnum::DECOMPRESSION_FAILED->value
            );
        }

        return $result;
    }

    public function getAlgorithm(): AlgorithmEnum
    {
        return AlgorithmEnum::Gzip;
    }
}
