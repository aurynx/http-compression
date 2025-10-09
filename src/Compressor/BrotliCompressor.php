<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression\Compressor;

use Ayrunx\HttpCompression\CompressionAlgorithmEnum;
use Ayrunx\HttpCompression\CompressionErrorCode;
use Ayrunx\HttpCompression\CompressionException;
use Ayrunx\HttpCompression\CompressorInterface;

final class BrotliCompressor implements CompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            throw new CompressionException(
                sprintf('%s extension not available', $algorithm->getRequiredExtension()),
                CompressionErrorCode::ALGORITHM_UNAVAILABLE->value
            );
        }

        $level ??= $algorithm->getDefaultLevel();
        $algorithm->validateLevel($level);

        $result = brotli_compress($content, $level);

        if ($result === false) {
            throw new CompressionException(
                'Brotli compression failed',
                CompressionErrorCode::COMPRESSION_FAILED->value
            );
        }

        return $result;
    }

    public function decompress(string $content): string
    {
        $algorithm = $this->getAlgorithm();

        if (!$algorithm->isAvailable()) {
            throw new CompressionException(
                sprintf('%s extension not available', $algorithm->getRequiredExtension()),
                CompressionErrorCode::ALGORITHM_UNAVAILABLE->value
            );
        }

        $result = brotli_uncompress($content);

        if ($result === false) {
            throw new CompressionException(
                'Brotli decompression failed',
                CompressionErrorCode::DECOMPRESSION_FAILED->value
            );
        }

        return $result;
    }

    public function getAlgorithm(): CompressionAlgorithmEnum
    {
        return CompressionAlgorithmEnum::Brotli;
    }
}
