<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

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
final readonly class CompressionResult
{
    /**
     * @param string $identifier Item identifier
     * @param array<string, string> $compressed Map of algorithm => compressed content (successful compressions)
     * @param CompressionException|null $error Complete failure error (mutually exclusive with partial)
     * @param array<string, array{code:int, message:string}> $algorithmErrors Map of algorithm => structured error (for partial failures)
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
     */
    public static function createError(string $identifier, CompressionException $error): CompressionResult
    {
        return new CompressionResult($identifier, [], $error);
    }

    /**
     * Create a result with partial success (some algorithms succeeded, some failed)
     *
     * @param array<string, string> $compressed
     * @param array<string, array{code:int, message:string}> $algorithmErrors
     */
    public static function createPartial(
        string $identifier,
        array $compressed,
        array $algorithmErrors
    ): CompressionResult {
        return new CompressionResult($identifier, $compressed, null, $algorithmErrors);
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
}
