<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression\Algorithms;

use Ayrunx\HttpCompression\AlgorithmEnum;
use Ayrunx\HttpCompression\ErrorCode;
use Ayrunx\HttpCompression\CompressionException;
use Ayrunx\HttpCompression\CompressorInterface;

final class BrotliCompressor implements CompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            $ext = $algorithm->getRequiredExtension();
            throw new CompressionException(
                sprintf('%s extension not available; install/enable ext-%s', $ext, $ext),
                ErrorCode::ALGORITHM_UNAVAILABLE->value
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        $result = brotli_compress($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Brotli compression failed',
                ErrorCode::COMPRESSION_FAILED->value
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
                ErrorCode::ALGORITHM_UNAVAILABLE->value
            );
        }

        $result = brotli_uncompress($content);

        if ($result === false) {
            throw new CompressionException(
                'Brotli decompression failed',
                ErrorCode::DECOMPRESSION_FAILED->value
            );
        }

        return $result;
    }

    public function getAlgorithm(): AlgorithmEnum
    {
        return AlgorithmEnum::Brotli;
    }
}
