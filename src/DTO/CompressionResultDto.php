<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\DTO;

use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Throwable;

/**
 * Result of compressing a single item
 * Immutable DTO with compressed data and metrics
 */
final readonly class CompressionResultDto
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
     * Create a failed result for the entire item
     */
    public static function failed(string $id, Throwable $error, int $originalSize = 0): self
    {
        return new self(
            id: $id,
            success: false,
            originalSize: $originalSize,
            compressed: [],
            compressedSizes: [],
            compressionTimes: [],
            errors: ['_general' => $error],
        );
    }

    /**
     * Get compressed data for a specific algorithm
     * @return string|resource|null
     */
    public function getData(AlgorithmEnum $algo): mixed
    {
        return $this->compressed[$algo->value] ?? null;
    }

    /**
     * Check if the algorithm succeeded
     */
    public function has(AlgorithmEnum $algo): bool
    {
        return isset($this->compressed[$algo->value]);
    }

    /**
     * Get error for a specific algorithm (if any)
     */
    public function getError(AlgorithmEnum $algo): ?Throwable
    {
        return $this->errors[$algo->value] ?? null;
    }

    /**
     * Get a general failure reason (item-level failure)
     */
    public function getGeneralError(): ?Throwable
    {
        return $this->errors['_general'] ?? null;
    }

    /**
     * Check if the result is OK (no errors)
     */
    public function isOk(): bool
    {
        return $this->success && empty($this->errors);
    }
}
