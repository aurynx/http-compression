<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Represents the result of compression operation
 *
 * Contract:
 * - successful: array<string, string> - successfully compressed data per algorithm
 * - errors: array<string, string> - error messages per algorithm (empty on full success)
 * - isOk(): bool - true when all algorithms succeeded (alias of isSuccess)
 * - isPartial(): bool - true when some algorithms succeeded, some failed
 * - isError(): bool - true when complete failure (no algorithms succeeded)
 * - getCompressed(): array<string, string> - all successful compressed payloads
 */
final readonly class CompressionResult
{
    /**
     * @param string $identifier Item identifier
     * @param array<string, string> $compressed Map of algorithm => compressed content (successful compressions)
     * @param CompressionException|null $error Complete failure error (mutually exclusive with partial)
     * @param array<string, string> $algorithmErrors Map of algorithm => error message (for partial failures)
     */
    public function __construct(
        private string $identifier,
        private array $compressed,
        private ?CompressionException $error = null,
        private array $algorithmErrors = []
    ) {
    }

    /**
     * Create a result representing a complete error
     *
     * @param string $identifier
     * @param CompressionException $error
     * @return self
     */
    public static function createError(string $identifier, CompressionException $error): self
    {
        return new self($identifier, [], $error);
    }

    /**
     * Create a result with partial success (some algorithms succeeded, some failed)
     *
     * @param string $identifier
     * @param array<string, string> $compressed
     * @param array<string, string> $algorithmErrors
     * @return self
     */
    public static function createPartial(
        string $identifier,
        array $compressed,
        array $algorithmErrors
    ): self {
        return new self($identifier, $compressed, null, $algorithmErrors);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get compressed content for specific algorithm
     */
    public function getCompressedFor(CompressionAlgorithmEnum $algorithm): ?string
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

    /**
     * Check if the algorithm was used for compression
     */
    public function hasAlgorithm(CompressionAlgorithmEnum $algorithm): bool
    {
        return isset($this->compressed[$algorithm->value]);
    }

    /**
     * Get a list of algorithms used
     *
     * @return string[]
     */
    public function getAlgorithms(): array
    {
        return array_keys($this->compressed);
    }

    /**
     * Check if this result represents a complete error (no algorithms succeeded)
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Check if this result is fully successful (all algorithms succeeded)
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->error === null && empty($this->algorithmErrors);
    }

    /**
     * Stable alias for success state used by consumers
     */
    public function isOk(): bool
    {
        return $this->isSuccess();
    }

    /**
     * Check if this result is partial (some algorithms succeeded, some failed)
     *
     * @return bool
     */
    public function isPartial(): bool
    {
        return !empty($this->compressed) && !empty($this->algorithmErrors);
    }

    /**
     * Get the error if this result failed
     *
     * @return CompressionException|null
     */
    public function getError(): ?CompressionException
    {
        return $this->error;
    }

    /**
     * Get an error message if this result failed
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->error?->getMessage();
    }

    /**
     * Get all error messages (both complete and per-algorithm)
     *
     * Returns an empty array on full success.
     * Always safe to call without checking isError() first.
     *
     * @return array<string, string> Map of algorithm => error message, or ['_error' => message] for complete failure
     */
    public function getErrors(): array
    {
        if ($this->error !== null) {
            return ['_error' => $this->error->getMessage()];
        }

        return $this->algorithmErrors;
    }

    /**
     * Check if this result has partial failures (some algorithms failed)
     *
     * @return bool
     */
    public function hasPartialFailures(): bool
    {
        return !empty($this->algorithmErrors);
    }

    /**
     * Get per-algorithm error messages
     *
     * @return array<string, string> Map of algorithm => error message
     */
    public function getAlgorithmErrors(): array
    {
        return $this->algorithmErrors;
    }

    /**
     * Check if a specific algorithm failed
     *
     * @param CompressionAlgorithmEnum $algorithm
     * @return bool
     */
    public function hasAlgorithmError(CompressionAlgorithmEnum $algorithm): bool
    {
        return isset($this->algorithmErrors[$algorithm->value]);
    }

    /**
     * Get an error message for a specific algorithm
     *
     * @param CompressionAlgorithmEnum $algorithm
     * @return string|null
     */
    public function getAlgorithmError(CompressionAlgorithmEnum $algorithm): ?string
    {
        return $this->algorithmErrors[$algorithm->value] ?? null;
    }
}
