<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

use Aurynx\HttpCompression\Attributes\OutputFormatAttribute;
use Aurynx\HttpCompression\Compressors\BrotliCompressor;
use Aurynx\HttpCompression\Compressors\GzipCompressor;
use Aurynx\HttpCompression\Compressors\ZstdCompressor;
use Aurynx\HttpCompression\Contracts\CompressorInterface;
use Aurynx\HttpCompression\Contracts\StreamCompressorInterface;
use Aurynx\HttpCompression\DTO\CompressionResultDto;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\ErrorCodeEnum;
use Aurynx\HttpCompression\Enums\OutputModeEnum;
use Aurynx\HttpCompression\ValueObjects\CompressionInput;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\OutputConfig;
use ReflectionClass;
use Throwable;

/**
 * Core compression engine
 * Orchestrates compression process for items
 */
final class CompressionEngine
{
    /** @var array<string, CompressorInterface> */
    private array $compressors = [];

    public function __construct(
        private readonly OutputConfig $outputConfig,
        private readonly bool $failFast = true,
    ) {
        $this->initializeCompressors();
    }

    /**
     * Compress a single item with a given configuration
     *
     * @throws Throwable
     */
    public function compressItem(CompressionInput $input, ItemConfig $config): CompressionResultDto
    {
        $originalSize = $input->getSize();

        // Validate output mode compatibility for this input type
        if (!$this->isOutputModeAllowed($input, $this->outputConfig->mode)) {
            $message = sprintf(
                'Output mode %s is not supported for input type %s',
                $this->outputConfig->mode->name,
                get_class($input),
            );

            if ($this->failFast) {
                throw new CompressionException($message, ErrorCodeEnum::UNSUPPORTED_OUTPUT_MODE->value);
            }

            return CompressionResultDto::failed(
                id: $input->id,
                error: new CompressionException($message, ErrorCodeEnum::UNSUPPORTED_OUTPUT_MODE->value),
                originalSize: $originalSize,
            );
        }

        // Check size limit if configured
        if ($config->maxBytes !== null && $originalSize > $config->maxBytes) {
            if ($this->failFast) {
                throw new CompressionException(
                    "Input size ({$originalSize} bytes) exceeds limit ({$config->maxBytes} bytes)",
                    ErrorCodeEnum::PAYLOAD_TOO_LARGE->value,
                );
            }

            return CompressionResultDto::failed(
                $input->id,
                new CompressionException(
                    'Input size exceeds limit',
                    ErrorCodeEnum::PAYLOAD_TOO_LARGE->value,
                ),
                $originalSize,
            );
        }

        // Check memory limit for in-memory output
        $this->outputConfig->validateMemoryLimit($originalSize);

        $compressed = [];
        $compressedSizes = [];
        $compressionTimes = [];
        $errors = [];

        // Compress with each algorithm
        foreach ($config->algorithms->toArray() as [$algo, $level]) {
            try {
                $startTime = microtime(true);
                $compressedData = $this->compressWithAlgorithm($input, $algo, $level);
                $elapsedMs = (microtime(true) - $startTime) * 1000;

                $compressed[$algo->value] = $compressedData;
                $compressedSizes[$algo->value] = strlen($compressedData);
                $compressionTimes[$algo->value] = $elapsedMs;

            } catch (Throwable $e) {
                $errors[$algo->value] = $e;

                if ($this->failFast) {
                    throw $e;
                }
            }
        }

        $success = empty($errors) && !empty($compressed);

        return new CompressionResultDto(
            id: $input->id,
            success: $success,
            originalSize: $originalSize,
            compressed: $compressed,
            compressedSizes: $compressedSizes,
            compressionTimes: $compressionTimes,
            errors: $errors,
        );
    }

    /**
     * Compress input with a specific algorithm
     * @return string Compressed data
     */
    private function compressWithAlgorithm(
        CompressionInput $input,
        AlgorithmEnum $algo,
        int $level,
    ): string {
        $compressor = $this->getCompressor($algo);

        // Check if the algorithm is available
        if (!$algo->isAvailable()) {
            throw new CompressionException(
                "Algorithm {$algo->value} is not available. Install ext-{$algo->getRequiredExtension()}",
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value,
            );
        }

        $inputData = $input->getData();

        // Use stream compression if supported and input is a resource
        if (is_resource($inputData) && $compressor instanceof StreamCompressorInterface) {
            return $compressor->compressStream($inputData, $level);
        }

        // Convert resource to string if needed
        if (is_resource($inputData)) {
            rewind($inputData);
            $content = stream_get_contents($inputData);

            if ($content === false) {
                throw new CompressionException(
                    'Failed to read input stream',
                    ErrorCodeEnum::COMPRESSION_FAILED->value,
                );
            }

            return $compressor->compress($content, $level);
        }

        // At this point, $inputData is guaranteed to be a string
        assert(is_string($inputData));

        return $compressor->compress($inputData, $level);
    }

    /**
     * Get compressor instance for algorithm
     */
    private function getCompressor(AlgorithmEnum $algo): CompressorInterface
    {
        return $this->compressors[$algo->value]
            ?? throw new CompressionException("No compressor registered for {$algo->value}");
    }

    /**
     * Initialize all compressors
     */
    private function initializeCompressors(): void
    {
        $this->compressors = [
            AlgorithmEnum::Gzip->value => new GzipCompressor(),
            AlgorithmEnum::Brotli->value => new BrotliCompressor(),
            AlgorithmEnum::Zstd->value => new ZstdCompressor(),
        ];
    }

    /**
     * Check if output mode is allowed for a given input type using OutputFormatAttribute
     */
    private function isOutputModeAllowed(CompressionInput $input, OutputModeEnum $mode): bool
    {
        $ref = new ReflectionClass($input);
        $attrs = $ref->getAttributes(OutputFormatAttribute::class);

        if ($attrs === []) {
            // No attribute means no restriction
            return true;
        }

        /** @var OutputFormatAttribute $attr */
        $attr = $attrs[0]->newInstance();

        return array_any(
            $attr->allowedModes,
            static fn ($allowed): bool => $allowed === $mode,
        );
    }
}
