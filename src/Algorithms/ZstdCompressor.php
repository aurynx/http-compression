<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Algorithms;

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Contracts\CompressorInterface;
use Aurynx\HttpCompression\ErrorCodeEnum;

final class ZstdCompressor implements CompressorInterface
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

        if (!function_exists('zstd_compress')) {
            // Defensive: extension loaded but function unavailable
            throw new CompressionException(
                'Zstd functions not available despite extension being loaded',
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value
            );
        }

        $result = zstd_compress($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Zstd compression failed',
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

        if (!function_exists('zstd_uncompress')) {
            throw new CompressionException(
                'Zstd functions not available despite extension being loaded',
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value
            );
        }

        $result = zstd_uncompress($content);

        if ($result === false) {
            throw new CompressionException(
                'Zstd decompression failed',
                ErrorCodeEnum::DECOMPRESSION_FAILED->value
            );
        }

        return $result;
    }

    public function getAlgorithm(): AlgorithmEnum
    {
        return AlgorithmEnum::Zstd;
    }
}
