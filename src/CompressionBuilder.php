<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Fluent API builder for compression operations
 */
final class CompressionBuilder implements \Countable, \IteratorAggregate
{
    /** @var array<string, CompressionItem> */
    private array $items = [];

    /** @var array<string, array<string, int>> Map of identifier => [algorithm => level] */
    private array $algorithms = [];

    private int $itemCounter = 0;

    private ?string $lastAddedIdentifier = null;

    private bool $failFast = true;

    /** @var array<string, int>|null Default algorithms for all items */
    private ?array $defaultAlgorithms = null;

    /**
     * Add raw content for compression
     *
     * @param string $content Raw content to compress
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $algorithms
     * @param string|null $customIdentifier Optional custom identifier for the item
     * @return self
     */
    public function add(
        string $content,
        CompressionAlgorithmEnum|array|null $algorithms = null,
        ?string $customIdentifier = null
    ): self {
        $identifier = $customIdentifier ?? $this->generateIdentifier();

        if (isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" already exists', $identifier),
                CompressionErrorCode::DUPLICATE_IDENTIFIER->value
            );
        }

        $this->items[$identifier] = new CompressionItem($content, false, $identifier);
        $this->algorithms[$identifier] = $this->resolveAlgorithms($algorithms);
        $this->lastAddedIdentifier = $identifier;

        return $this;
    }

    /**
     * Add file for compression
     *
     * @param string $filePath Path to the file to compress
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $algorithms
     * @param string|null $customIdentifier Optional custom identifier for the item
     * @return self
     * @throws CompressionException if file does not exist or is not readable
     */
    public function addFile(
        string $filePath,
        CompressionAlgorithmEnum|array|null $algorithms = null,
        ?string $customIdentifier = null
    ): self {
        $identifier = $customIdentifier ?? $this->generateIdentifier();

        if (isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" already exists', $identifier),
                CompressionErrorCode::DUPLICATE_IDENTIFIER->value
            );
        }

        if (!is_file($filePath)) {
            throw new CompressionException(
                sprintf('File not found: %s', $filePath),
                CompressionErrorCode::FILE_NOT_FOUND->value
            );
        }

        if (!is_readable($filePath)) {
            throw new CompressionException(
                sprintf('File not readable: %s', $filePath),
                CompressionErrorCode::FILE_NOT_READABLE->value
            );
        }

        // Normalize file path for stable logging
        $normalizedPath = realpath($filePath);
        if ($normalizedPath === false) {
            $normalizedPath = $filePath;
        }

        $this->items[$identifier] = new CompressionItem($normalizedPath, true, $identifier);
        $this->algorithms[$identifier] = $this->resolveAlgorithms($algorithms);
        $this->lastAddedIdentifier = $identifier;

        return $this;
    }

    /**
     * Add multiple items at once
     *
     * @param iterable<array{content: string, algorithms?: CompressionAlgorithmEnum|array|null, identifier?: string|null}|string> $payloads
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $defaultAlgorithms
     * @return self
     */
    public function addMany(
        iterable $payloads,
        CompressionAlgorithmEnum|array|null $defaultAlgorithms = null
    ): self {
        foreach ($payloads as $payload) {
            if (is_string($payload)) {
                $this->add($payload, $defaultAlgorithms);
            } elseif (is_array($payload)) {
                $content = $payload['content'] ?? throw new CompressionException(
                    'Payload array must contain "content" key',
                    CompressionErrorCode::INVALID_PAYLOAD->value
                );
                $algorithms = $payload['algorithms'] ?? $defaultAlgorithms;
                $identifier = $payload['identifier'] ?? null;

                $this->add($content, $algorithms, $identifier);
            } else {
                throw new CompressionException(
                    'Payload must be a string or an array with "content" key',
                    CompressionErrorCode::INVALID_PAYLOAD->value
                );
            }
        }

        return $this;
    }

    /**
     * Add multiple files at once
     *
     * @param iterable<array{path: string, algorithms?: CompressionAlgorithmEnum|array|null, identifier?: string|null}|string> $payloads
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $defaultAlgorithms
     * @return self
     */
    public function addManyFiles(
        iterable $payloads,
        CompressionAlgorithmEnum|array|null $defaultAlgorithms = null
    ): self {
        foreach ($payloads as $payload) {
            if (is_string($payload)) {
                $this->addFile($payload, $defaultAlgorithms);
            } elseif (is_array($payload)) {
                $path = $payload['path'] ?? throw new CompressionException(
                    'Payload array must contain "path" key',
                    CompressionErrorCode::INVALID_PAYLOAD->value
                );
                $algorithms = $payload['algorithms'] ?? $defaultAlgorithms;
                $identifier = $payload['identifier'] ?? null;

                $this->addFile($path, $algorithms, $identifier);
            } else {
                throw new CompressionException(
                    'Payload must be a string or an array with "path" key',
                    CompressionErrorCode::INVALID_PAYLOAD->value
                );
            }
        }

        return $this;
    }

    /**
     * Get the identifier of the last added item
     *
     * @return string|null
     */
    public function getLastIdentifier(): ?string
    {
        return $this->lastAddedIdentifier;
    }

    /**
     * Set default algorithms for all subsequently added items
     *
     * Pass null to clear defaults.
     * Note: Empty arrays are not allowed and will throw CompressionException.
     *
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $algorithms
     * @return self
     * @throws CompressionException if empty array is provided
     */
    public function withDefaultAlgorithms(
        CompressionAlgorithmEnum|array|null $algorithms
    ): self {
        $this->defaultAlgorithms = $algorithms !== null
            ? $this->normalizeAlgorithms($algorithms)
            : null;

        return $this;
    }

    /**
     * Get default algorithms
     *
     * @return array<string, int>|null
     */
    public function getDefaultAlgorithms(): ?array
    {
        return $this->defaultAlgorithms;
    }

    /**
     * Clear default algorithms (sugar for withDefaultAlgorithms(null))
     *
     * @return self
     */
    public function withoutDefaultAlgorithms(): self
    {
        $this->defaultAlgorithms = null;

        return $this;
    }

    /**
     * Get normalized algorithms for a specific item
     *
     * @param string $identifier
     * @return array<string, int> Map of algorithm => level
     * @throws CompressionException if item not found
     */
    public function getAlgorithms(string $identifier): array
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                CompressionErrorCode::ITEM_NOT_FOUND->value
            );
        }

        return $this->algorithms[$identifier];
    }

    /**
     * Replace algorithms for a specific item (no merge with defaults)
     *
     * @param string $identifier
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int> $algorithms
     * @return self
     * @throws CompressionException if item not found or algorithms invalid
     */
    public function replaceAlgorithms(
        string $identifier,
        CompressionAlgorithmEnum|array $algorithms
    ): self {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                CompressionErrorCode::ITEM_NOT_FOUND->value
            );
        }

        $this->algorithms[$identifier] = $this->normalizeAlgorithms($algorithms);

        return $this;
    }

    /**
     * Configure algorithms for a specific item (chainable)
     *
     * @param string $identifier
     * @return CompressionItemConfigurator
     * @throws CompressionException if item not found
     */
    public function forItem(string $identifier): CompressionItemConfigurator
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                CompressionErrorCode::ITEM_NOT_FOUND->value
            );
        }

        return new CompressionItemConfigurator($this, $identifier);
    }

    /**
     * Configure the last added item (chainable)
     *
     * After delete(), this points to the last remaining item in the builder.
     * If all items were deleted, throws an exception.
     *
     * @return CompressionItemConfigurator
     * @throws CompressionException if no items exist in the builder
     */
    public function forLast(): CompressionItemConfigurator
    {
        if ($this->lastAddedIdentifier === null) {
            throw new CompressionException(
                'There are no items in the builder',
                CompressionErrorCode::NO_ITEMS->value
            );
        }

        return $this->forItem($this->lastAddedIdentifier);
    }

    /**
     * Internal method to update algorithms for a specific item
     *
     * Uses resolveAlgorithms() to merge with defaults, maintaining consistency with add() and addFile().
     * Note: Empty arrays are not allowed.
     *
     * @internal Used by CompressionItemConfigurator
     * @throws CompressionException if empty array is provided
     */
    public function updateAlgorithms(
        string $identifier,
        CompressionAlgorithmEnum|array|null $algorithms
    ): void {
        $this->algorithms[$identifier] = $this->resolveAlgorithms($algorithms);
    }

    /**
     * Set fail-fast mode
     *
     * @param bool $failFast If true, throw exception on first error. If false, collect errors and continue.
     * @return self
     */
    public function setFailFast(bool $failFast): self
    {
        $this->failFast = $failFast;

        return $this;
    }

    /**
     * Get current fail-fast mode
     *
     * @return bool
     */
    public function isFailFast(): bool
    {
        return $this->failFast;
    }

    /**
     * Delete an item by its identifier
     *
     * If the deleted item was the last added, lastAddedIdentifier is updated to the
     * last remaining item (or null if no items remain).
     *
     * @param string $identifier
     * @return self
     * @throws CompressionException if item not found
     */
    public function delete(string $identifier): self
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                CompressionErrorCode::ITEM_NOT_FOUND->value
            );
        }

        unset($this->items[$identifier], $this->algorithms[$identifier]);

        // Update lastAddedIdentifier if we just deleted it
        if ($this->lastAddedIdentifier === $identifier) {
            // Set to last remaining item, or null if empty
            $this->lastAddedIdentifier = empty($this->items)
                ? null
                : array_key_last($this->items);
        }

        return $this;
    }

    /**
     * Check if an item with the given identifier exists
     *
     * @param string $identifier
     * @return bool
     */
    public function has(string $identifier): bool
    {
        return isset($this->items[$identifier]);
    }

    /**
     * Compress all added items
     *
     * @return array<string, CompressionResult>
     * @throws CompressionException if failFast is true and any item fails
     */
    public function compress(): array
    {
        if (empty($this->items)) {
            return [];
        }

        $results = [];

        foreach ($this->items as $identifier => $item) {
            try {
                $results[$identifier] = $this->compressItem($identifier, $item);
            } catch (CompressionException $e) {
                if ($this->failFast) {
                    throw $e;
                }

                $results[$identifier] = CompressionResult::createError($identifier, $e);
            }
        }

        return $results;
    }

    /**
     * Compress a specific item by its identifier
     *
     * @param string $identifier
     * @return CompressionResult
     * @throws CompressionException if failFast is true and compression fails, or if item not found
     */
    public function compressOne(string $identifier): CompressionResult
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                CompressionErrorCode::ITEM_NOT_FOUND->value
            );
        }

        try {
            return $this->compressItem($identifier, $this->items[$identifier]);
        } catch (CompressionException $e) {
            if ($this->failFast) {
                throw $e;
            }

            return CompressionResult::createError($identifier, $e);
        }
    }

    /**
     * Internal method to compress a single item
     *
     * @param string $identifier
     * @param CompressionItem $item
     * @return CompressionResult
     * @throws CompressionException if failFast is true and any algorithm fails
     */
    private function compressItem(string $identifier, CompressionItem $item): CompressionResult
    {
        $content = $item->readContent();
        $algorithms = $this->algorithms[$identifier];
        $compressed = [];
        $algorithmErrors = [];
        $lastError = null;

        foreach ($algorithms as $algorithmValue => $level) {
            try {
                $algorithm = CompressionAlgorithmEnum::from($algorithmValue);

                if (!$algorithm->isAvailable()) {
                    throw new CompressionException(
                        sprintf(
                            'Algorithm %s requires %s extension',
                            $algorithm->name,
                            $algorithm->getRequiredExtension()
                        ),
                        CompressionErrorCode::ALGORITHM_UNAVAILABLE->value
                    );
                }

                $compressor = CompressorFactory::create($algorithm);
                $compressed[$algorithmValue] = $compressor->compress($content, $level);
            } catch (CompressionException $e) {
                $lastError = $e;
                if ($this->failFast) {
                    throw $e;
                }
                // In non-failFast mode, track per-algorithm error
                $algorithmErrors[$algorithmValue] = $e->getMessage();
            } catch (\ValueError $e) {
                $lastError = new CompressionException(
                    sprintf('Unknown algorithm: %s', $algorithmValue),
                    CompressionErrorCode::UNKNOWN_ALGORITHM->value,
                    $e
                );
                if ($this->failFast) {
                    throw $lastError;
                }
                // Track per-algorithm error
                $algorithmErrors[$algorithmValue] = $lastError->getMessage();
            }
        }

        // If no algorithms succeeded, this is a complete failure
        if (empty($compressed) && $lastError !== null) {
            throw $lastError;
        }

        // Return partial result if we have both successes and errors
        if (!empty($algorithmErrors)) {
            return CompressionResult::createPartial($identifier, $compressed, $algorithmErrors);
        }

        return new CompressionResult($identifier, $compressed);
    }

    /**
     * Get all items currently in the builder
     *
     * @return array<string, CompressionItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the number of items in the builder
     *
     * Implements Countable interface.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get iterator over items in the builder
     *
     * Implements IteratorAggregate interface.
     * Allows using builder in foreach loops.
     *
     * @return \Traversable<string, CompressionItem>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Check if the builder is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Clear all added items from the builder
     *
     * Note: Default algorithms and failFast mode remain unchanged.
     * Use withDefaultAlgorithms(null) to clear defaults if needed.
     *
     * @return self
     */
    public function clear(): self
    {
        $this->items = [];
        $this->algorithms = [];
        $this->lastAddedIdentifier = null;

        return $this;
    }

    /**
     * Generate unique identifier for an item
     */
    private function generateIdentifier(): string
    {
        return 'item_' . (++$this->itemCounter);
    }

    /**
     * Resolve algorithms: merge provided with defaults
     *
     * If both defaults and item-specific algorithms are provided, they are merged.
     * Item-specific algorithms override defaults for the same algorithm type.
     *
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $algorithms
     * @return array<string, int>
     */
    private function resolveAlgorithms(
        CompressionAlgorithmEnum|array|null $algorithms
    ): array {
        // If no algorithms provided and no defaults, use all available
        if ($algorithms === null && $this->defaultAlgorithms === null) {
            return $this->normalizeAlgorithms(null);
        }

        // If no algorithms provided, use defaults
        if ($algorithms === null) {
            return $this->defaultAlgorithms;
        }

        $normalized = $this->normalizeAlgorithms($algorithms);

        // If no defaults, just return normalized
        if ($this->defaultAlgorithms === null) {
            return $normalized;
        }

        // Merge: defaults as base, normalized overrides
        return array_merge($this->defaultAlgorithms, $normalized);
    }

    /**
     * Normalize algorithms parameter to standardized format
     *
     * If the same algorithm appears multiple times in the array, the last occurrence wins.
     * This behavior is consistent with array_merge() used in resolveAlgorithms().
     *
     * Note: Empty arrays are not allowed and will throw CompressionException.
     *
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $algorithms
     * @return array<string, int>
     * @throws CompressionException if algorithms specification is invalid or empty
     */
    private function normalizeAlgorithms(
        CompressionAlgorithmEnum|array|null $algorithms
    ): array {
        if ($algorithms === null) {
            return [
                CompressionAlgorithmEnum::Gzip->value => CompressionAlgorithmEnum::Gzip->getDefaultLevel(),
                CompressionAlgorithmEnum::Brotli->value => CompressionAlgorithmEnum::Brotli->getDefaultLevel(),
            ];
        }

        if ($algorithms instanceof CompressionAlgorithmEnum) {
            return [
                $algorithms->value => $algorithms->getDefaultLevel(),
            ];
        }

        $normalized = [];
        foreach ($algorithms as $key => $value) {
            try {
                if ($key instanceof CompressionAlgorithmEnum) {
                    $algorithm = $key;
                    $level = $value;
                } elseif (is_string($key) && is_int($value)) {
                    $algorithm = CompressionAlgorithmEnum::from($key);
                    $level = $value;
                } elseif ($value instanceof CompressionAlgorithmEnum) {
                    $algorithm = $value;
                    $level = $algorithm->getDefaultLevel();
                } elseif (is_int($key) && is_string($value)) {
                    $algorithm = CompressionAlgorithmEnum::from($value);
                    $level = $algorithm->getDefaultLevel();
                } else {
                    throw new CompressionException(
                        'Invalid algorithm specification in array',
                        CompressionErrorCode::INVALID_ALGORITHM_SPEC->value
                    );
                }

                $algorithm->validateLevel($level);
                $normalized[$algorithm->value] = $level;
            } catch (\ValueError $e) {
                // Extract the actual problematic value for better error messages
                $rawValue = is_string($key) ? $key : (is_string($value) ? $value : json_encode([$key, $value]));
                throw new CompressionException(
                    sprintf('Unknown algorithm: %s', $rawValue),
                    CompressionErrorCode::UNKNOWN_ALGORITHM->value,
                    $e
                );
            }
        }

        if (empty($normalized)) {
            throw new CompressionException(
                'At least one compression algorithm must be specified',
                CompressionErrorCode::EMPTY_ALGORITHMS->value
            );
        }

        return $normalized;
    }
}
