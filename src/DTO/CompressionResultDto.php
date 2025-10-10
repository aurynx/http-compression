<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\DTO;

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionException;

/**
 * Represents the result of a compression operation
 *
 * Contract:
 * - successful: array<string, string> - successfully compressed data per algorithm
 * - errors: array<string, array{code:int, message:string}> - structured errors per algorithm (empty on full success)
 * - isOk(): bool - true when all algorithms succeeded (alias of isSuccess)
 * - isPartial(): bool - true when some algorithms succeeded, some failed
 * - isError(): bool - true when complete failure (no algorithms succeeded)
 * - getCompressed(): array<string, string> - all successful compressed payloads
 */
final readonly class CompressionResultDto
{
    /**
     * @param string $identifier Item identifier
     * @param array<string, string> $compressed Map of algorithm => compressed content (successful compressions)
     * @param CompressionException|null $error Complete failure error (mutually exclusive with partial)
     * @param array<string, array{code:int, message:string}> $algorithmErrors Map of algorithm => structured error (for partial failures)
     * @param array<string, array{input: int, output: int}> $metadata Compression metrics per algorithm (input/output bytes)
     * @param int|null $originalSize Original uncompressed size in bytes
     */
    public function __construct(
        private string $identifier,
        private array $compressed,
        private ?CompressionException $error = null,
        private array $algorithmErrors = [],
        private array $metadata = [],
        private ?int $originalSize = null
    ) {
    }

    /**
     * Create a result representing a complete error
     */
    public static function createError(string $identifier, CompressionException $error): CompressionResultDto
    {
        return new CompressionResultDto($identifier, [], $error, [], [], null);
    }

    /**
     * Create a result with partial success (some algorithms succeeded, some failed)
     *
     * @param array<string, string> $compressed
     * @param array<string, array{code:int, message:string}> $algorithmErrors
     * @param array<string, array{input: int, output: int}> $metadata
     */
    public static function createPartial(
        string $identifier,
        array $compressed,
        array $algorithmErrors,
        array $metadata = [],
        ?int $originalSize = null
    ): CompressionResultDto {
        return new CompressionResultDto($identifier, $compressed, null, $algorithmErrors, $metadata, $originalSize);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /** Get compressed content for a specific algorithm */
    public function getCompressedFor(AlgorithmEnum $algorithm): ?string
    {
        return $this->compressed[$algorithm->value] ?? null;
    }

    /**
     * Get all compressed results (consumer-friendly alias)
     *
     * @return array<string, string>
     */
    public function getCompressed(): array
    {
        return $this->compressed;
    }

    /** @return array<string, string> */
    public function getAllCompressed(): array
    {
        return $this->compressed;
    }

    /** Check if the algorithm was used for compression */
    public function hasAlgorithm(AlgorithmEnum $algorithm): bool
    {
        return isset($this->compressed[$algorithm->value]);
    }

    /**
     * @return string[] List of algorithms used
     */
    public function getAlgorithms(): array
    {
        return array_keys($this->compressed);
    }

    /** True when complete failure (no algorithms succeeded) */
    public function isError(): bool
    {
        return $this->error !== null;
    }

    /** True when all algorithms succeeded */
    public function isSuccess(): bool
    {
        return $this->error === null && empty($this->algorithmErrors);
    }

    /** Stable alias for success state used by consumers */
    public function isOk(): bool
    {
        return $this->isSuccess();
    }

    /** True when some algorithms succeeded, some failed */
    public function isPartial(): bool
    {
        return !empty($this->compressed) && !empty($this->algorithmErrors);
    }

    /**  */
    public function getError(): ?CompressionException
    {
        return $this->error;
    }

    /**  */
    public function getErrorMessage(): ?string
    {
        return $this->error?->getMessage();
    }

    /**
     * Get all error details.
     * - Complete failure: ['_error' => ['code' => int, 'message' => string]]
     * - Partial failure: [algorithm => ['code' => int, 'message' => string], ...]
     * - Full success: []
     *
     * @return array<string, array{code:int, message:string}>
     */
    public function getErrors(): array
    {
        if ($this->error !== null) {
            return ['_error' => ['code' => $this->error->getCode(), 'message' => $this->error->getMessage()]];
        }

        return $this->algorithmErrors;
    }

    /** True if this result has any per-algorithm failures */
    public function hasPartialFailures(): bool
    {
        return !empty($this->algorithmErrors);
    }

    /**
     * Get per-algorithm error details
     *
     * @return array<string, array{code:int, message:string}>
     */
    public function getAlgorithmErrors(): array
    {
        return $this->algorithmErrors;
    }

    /** Check if a specific algorithm failed */
    public function hasAlgorithmError(AlgorithmEnum $algorithm): bool
    {
        return isset($this->algorithmErrors[$algorithm->value]);
    }

    /**
     * Get error details for a specific algorithm
     *
     * @return array{code:int, message:string}|null
     */
    public function getAlgorithmError(AlgorithmEnum $algorithm): ?array
    {
        return $this->algorithmErrors[$algorithm->value] ?? null;
    }

    /**
     * Get original uncompressed size in bytes
     */
    public function getOriginalSize(): ?int
    {
        return $this->originalSize;
    }

    /**
     * Get compressed size for a specific algorithm in bytes
     */
    public function getCompressedSize(AlgorithmEnum $algorithm): ?int
    {
        return $this->metadata[$algorithm->value]['output'] ?? null;
    }

    /**
     * Get compression ratio for a specific algorithm (0.0 to 1.0)
     *
     * @return float|null Ratio of compressed/original size (e.g., 0.42 means 42% of original)
     */
    public function getCompressionRatio(AlgorithmEnum $algorithm): ?float
    {
        $meta = $this->metadata[$algorithm->value] ?? null;
        if (!$meta || $meta['input'] === 0) {
            return null;
        }

        return $meta['output'] / $meta['input'];
    }

    /**
     * Get bytes saved by compression for a specific algorithm
     *
     * @return int|null Number of bytes saved (negative if compressed is larger)
     */
    public function getSavedBytes(AlgorithmEnum $algorithm): ?int
    {
        $meta = $this->metadata[$algorithm->value] ?? null;

        if (!$meta) {
            return null;
        }

        return $meta['input'] - $meta['output'];
    }

    /**
     * Get compression percentage for a specific algorithm
     *
     * @return float|null Percentage reduction (e.g., 58.0 means 58% size reduction)
     */
    public function getCompressionPercentage(AlgorithmEnum $algorithm): ?float
    {
        $ratio = $this->getCompressionRatio($algorithm);

        if ($ratio === null) {
            return null;
        }

        return (1.0 - $ratio) * 100;
    }

    /**
     * Check if compression was effective for a specific algorithm
     *
     * @return bool|null True if compressed size is smaller than original, null if no metadata
     */
    public function isEffective(AlgorithmEnum $algorithm): ?bool
    {
        $saved = $this->getSavedBytes($algorithm);

        return $saved !== null ? $saved > 0 : null;
    }
}
