<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Results;

use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\ValueObjects\CompressionInput;
use OutOfBoundsException;
use RuntimeException;
use Throwable;

/**
 * Result of compressing a single item
 * An immutable value object with compressed data and metrics
 */
final readonly class CompressionItemResult
{
    /**
     * @param array<string, string|resource> $compressed Map of algo->value => compressed data
     * @param array<string, int> $compressedSizes Map of algo->value => size in bytes
     * @param array<string, float> $compressionTimes Map of algo->value => time in milliseconds
     * @param array<string, Throwable> $errors Map of algo->value => error
     */
    public function __construct(
        public string $id,
        public bool $success,
        public int $originalSize,
        public array $compressed,
        public array $compressedSizes,
        public array $compressionTimes,
        public array $errors,
    ) {
    }

    /**
     * Create a failed result (no placeholder algorithms!)
     */
    public static function failed(CompressionInput $input, Throwable $error): self
    {
        return new self(
            id: $input->id,
            success: false,
            originalSize: $input->getSize(),
            compressed: [],
            compressedSizes: [],
            compressionTimes: [],
            errors: ['_general' => $error],
        );
    }

    /**
     * Get a general failure reason (if item-level failure)
     */
    public function getFailureReason(): ?Throwable
    {
        if ($this->success) {
            return null;
        }

        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Check if a result has no errors
     */
    public function isOk(): bool
    {
        return $this->success && empty($this->errors);
    }

    /**
     * Check if the algorithm succeeded
     */
    public function has(AlgorithmEnum $algo): bool
    {
        return isset($this->compressed[$algo->value]);
    }

    /**
     * Get compressed data as string
     * @throws OutOfBoundsException if algorithm not found
     * @throws RuntimeException if stream reading fails
     */
    public function getData(AlgorithmEnum $algo): string
    {
        $data = $this->compressed[$algo->value] ?? throw new OutOfBoundsException(
            "No data for algorithm: {$algo->value}",
        );

        if (is_resource($data)) {
            rewind($data);
            $buffer = '';

            while (!feof($data)) {
                $chunk = fread($data, 8192);

                if ($chunk === false) {
                    throw new RuntimeException('Failed to read stream');
                }
                $buffer .= $chunk;
            }

            return $buffer;
        }

        // Explicit cast to string for static analysis
        return (string) $data;
    }

    /**
     * Get compressed data as stream
     * @return resource
     * @throws OutOfBoundsException if algorithm not found
     */
    public function getStream(AlgorithmEnum $algo): mixed
    {
        $data = $this->compressed[$algo->value] ?? throw new OutOfBoundsException(
            "No data for algorithm: {$algo->value}",
        );

        if (is_resource($data)) {
            rewind($data);

            return $data;
        }

        $stream = fopen('php://memory', 'rb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create memory stream');
        }

        if (is_string($data)) {
            fwrite($stream, $data);
        }

        rewind($stream);

        return $stream;
    }

    /**
     * Read compressed data in chunks
     *
     * Note: Only closes streams CREATED by this library.
     * External streams passed to constructor remain open.
     *
     * @throws OutOfBoundsException if algorithm not found
     */
    public function read(AlgorithmEnum $algo, callable $consumer): void
    {
        $data = $this->compressed[$algo->value] ?? throw new OutOfBoundsException(
            "No data for algorithm: {$algo->value}",
        );

        if (is_string($data)) {
            $offset = 0;
            $chunkSize = 8192;

            while ($offset < strlen($data)) {
                $chunk = substr($data, $offset, $chunkSize);
                $consumer($chunk);
                $offset += $chunkSize;
            }

            return;
        }

        if (is_resource($data)) {
            $meta = stream_get_meta_data($data);
            $shouldClose = isset($meta['uri']) && $meta['uri'] === 'php://memory';

            rewind($data);

            while (!feof($data)) {
                $chunk = fread($data, 8192);

                if ($chunk === false) {
                    break;
                }
                $consumer($chunk);
            }

            if ($shouldClose) {
                fclose($data);
            }
        }
    }

    /**
     * Get compressed size in bytes
     * @throws OutOfBoundsException if algorithm not found
     */
    public function getSize(AlgorithmEnum $algo): int
    {
        return $this->compressedSizes[$algo->value] ?? throw new OutOfBoundsException(
            "No size for algorithm: {$algo->value}",
        );
    }

    /**
     * Get compression ratio (compressed / original)
     * Value < 1.0 means compression was effective
     * Value = 1.0 means no compression gain
     * Value > 1.0 means compressed is larger
     */
    public function getRatio(AlgorithmEnum $algo): float
    {
        if ($this->originalSize === 0) {
            return 0.0;
        }

        return $this->getSize($algo) / $this->originalSize;
    }

    /**
     * Get compression time in milliseconds
     */
    public function getCompressionTimeMs(AlgorithmEnum $algo): float
    {
        return $this->compressionTimes[$algo->value] ?? 0.0;
    }

    /**
     * Get error for a specific algorithm (if any)
     */
    public function getError(AlgorithmEnum $algo): ?Throwable
    {
        return $this->errors[$algo->value] ?? null;
    }

    /**
     * Get all errors
     * @return array<string, Throwable>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
