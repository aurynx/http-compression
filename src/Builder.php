<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use ValueError;

/**
 * Fluent API builder for compression operations
 */
final class Builder implements Countable, IteratorAggregate
{
    /** @var array<string, Item> */
    private array $items = [];

    /** @var array<string, array<string, int>> Map of identifier => [algorithm => level] */
    private array $algorithms = [];

    private int $itemCounter = 0;

    private ?string $lastAddedIdentifier = null;

    private bool $failFast = true;

    /** @var array<string, int>|null Default algorithms for all items */
    private ?array $defaultAlgorithms = null;

    /** Optional max payload size limit in bytes (null = default per item) */
    private ?int $maxBytes;

    public function __construct(?int $maxBytes = null)
    {
        $this->maxBytes = $maxBytes;
    }

    /**
     * Add raw content for compression
     *
     * @param  string  $content  Raw content to compress
     * @param  AlgorithmEnum|iterable|null  $algorithms
     * @param  string|null  $customIdentifier  Optional custom identifier for the item
     *
     * @return $this
     */
    public function add(
        string $content,
        AlgorithmEnum|iterable|null $algorithms = null,
        ?string $customIdentifier = null
    ): self {
        $identifier = $customIdentifier ?? $this->generateIdentifier();

        if (isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" already exists', $identifier),
                ErrorCode::DUPLICATE_IDENTIFIER->value
            );
        }

        $this->items[$identifier] = new Item($content, false, $identifier, $this->maxBytes);
        $this->algorithms[$identifier] = $this->resolveAlgorithms($algorithms);
        $this->lastAddedIdentifier = $identifier;

        return $this;
    }

    /**
     * Add a file for compression
     *
     * @param  string  $filePath  Path to the file to compress
     * @param  AlgorithmEnum|iterable|null  $algorithms
     * @param  string|null  $customIdentifier  Optional custom identifier for the item
     *
     * @return $this
     */
    public function addFile(
        string $filePath,
        AlgorithmEnum|iterable|null $algorithms = null,
        ?string $customIdentifier = null
    ): self {
        $identifier = $customIdentifier ?? $this->generateIdentifier();

        if (isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" already exists', $identifier),
                ErrorCode::DUPLICATE_IDENTIFIER->value
            );
        }

        if (!is_file($filePath)) {
            throw new CompressionException(
                sprintf('File not found: %s', $filePath),
                ErrorCode::FILE_NOT_FOUND->value
            );
        }

        if (!is_readable($filePath)) {
            throw new CompressionException(
                sprintf('File not readable: %s (check permissions/SELinux)', $filePath),
                ErrorCode::FILE_NOT_READABLE->value
            );
        }

        // Normalize a file path for stable logging
        $normalizedPath = realpath($filePath);
        if ($normalizedPath === false) {
            $normalizedPath = $filePath;
        }

        $this->items[$identifier] = new Item($normalizedPath, true, $identifier, $this->maxBytes);
        $this->algorithms[$identifier] = $this->resolveAlgorithms($algorithms);
        $this->lastAddedIdentifier = $identifier;

        return $this;
    }

    /**
     * Add multiple items at once
     *
     * @param  iterable<array{content: string, algorithms?: AlgorithmEnum|iterable|null, identifier?: string|null}|string>  $payloads
     * @param  AlgorithmEnum|iterable|null  $defaultAlgorithms
     *
     * Note: empty iterable is not allowed and will result in CompressionException with code EMPTY_ALGORITHMS.
     *
     * @return $this
     */
    public function addMany(
        iterable $payloads,
        AlgorithmEnum|iterable|null $defaultAlgorithms = null
    ): self {
        foreach ($payloads as $payload) {
            if (is_string($payload)) {
                $this->add($payload, $defaultAlgorithms);
            } elseif (is_array($payload)) {
                $content = $payload['content'] ?? throw new CompressionException(
                    'Payload array must contain "content" key',
                    ErrorCode::INVALID_PAYLOAD->value
                );
                $algorithms = $payload['algorithms'] ?? $defaultAlgorithms;
                $identifier = $payload['identifier'] ?? null;

                $this->add($content, $algorithms, $identifier);
            } else {
                throw new CompressionException(
                    'Payload must be a string or an array with "content" key',
                    ErrorCode::INVALID_PAYLOAD->value
                );
            }
        }

        return $this;
    }

    /**
     * Add multiple files at once
     *
     * @param  iterable<array{path: string, algorithms?: AlgorithmEnum|iterable|null, identifier?: string|null}|string>  $payloads
     * @param  AlgorithmEnum|iterable|null  $defaultAlgorithms
     *
     * Note: empty iterable is not allowed and will result in CompressionException with code EMPTY_ALGORITHMS.
     *
     * @return $this
     */
    public function addManyFiles(
        iterable $payloads,
        AlgorithmEnum|iterable|null $defaultAlgorithms = null
    ): self {
        foreach ($payloads as $payload) {
            if (is_string($payload)) {
                $this->addFile($payload, $defaultAlgorithms);
            } elseif (is_array($payload)) {
                $path = $payload['path'] ?? throw new CompressionException(
                    'Payload array must contain "path" key',
                    ErrorCode::INVALID_PAYLOAD->value
                );
                $algorithms = $payload['algorithms'] ?? $defaultAlgorithms;
                $identifier = $payload['identifier'] ?? null;

                $this->addFile($path, $algorithms, $identifier);
            } else {
                throw new CompressionException(
                    'Payload must be a string or an array with "path" key',
                    ErrorCode::INVALID_PAYLOAD->value
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
     * Note: Empty iterable is not allowed and will throw CompressionException with code EMPTY_ALGORITHMS.
     *
     * @param  AlgorithmEnum|iterable|null  $algorithms
     *
     * @return $this
     */
    public function withDefaultAlgorithms(
        AlgorithmEnum|iterable|null $algorithms
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
     * @return $this
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
                ErrorCode::ITEM_NOT_FOUND->value
            );
        }

        return $this->algorithms[$identifier];
    }

    /**
     * Replace algorithms for a specific item (no merge with defaults)
     *
     * Note: Empty iterable is not allowed and will throw CompressionException with code EMPTY_ALGORITHMS.
     *
     * @param  string  $identifier
     * @param  AlgorithmEnum|iterable  $algorithms
     *
     * @return $this
     * @throws CompressionException if item not found or algorithms invalid
     */
    public function replaceAlgorithms(
        string $identifier,
        AlgorithmEnum|iterable $algorithms
    ): self {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                ErrorCode::ITEM_NOT_FOUND->value
            );
        }

        $this->algorithms[$identifier] = $this->normalizeAlgorithms($algorithms);

        return $this;
    }

    /**
     * Configure algorithms for a specific item (chainable)
     *
     * @param string $identifier
     *
     * @return ItemConfigurator
     * @throws CompressionException if item not found
     */
    public function forItem(string $identifier): ItemConfigurator
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                ErrorCode::ITEM_NOT_FOUND->value
            );
        }

        return new ItemConfigurator($this, $identifier);
    }

    /**
     * Configure the last added item (chainable)
     *
     * Definition of "last": the item that is last by insertion order.
     * PHP arrays preserve insertion order, and after deletions this method
     * refers to the last remaining item by that order (array_key_last()).
     *
     * If all items were deleted, throws an exception.
     *
     * @return ItemConfigurator
     * @throws CompressionException if no items exist in the builder
     */
    public function forLast(): ItemConfigurator
    {
        if ($this->lastAddedIdentifier === null) {
            throw new CompressionException(
                'There are no items in the builder',
                ErrorCode::NO_ITEMS->value
            );
        }

        return $this->forItem($this->lastAddedIdentifier);
    }

    /**
     * Internal method to update algorithms for a specific item
     *
     * Uses resolveAlgorithms() to merge with defaults, maintaining consistency with add() and addFile().
     * Note: Empty iterable is not allowed and will throw CompressionException with code EMPTY_ALGORITHMS.
     *
     * @internal Used by CompressionItemConfigurator
     * @throws CompressionException if item not found or empty iterable provided (EMPTY_ALGORITHMS)
     */
    public function updateAlgorithms(
        string $identifier,
        AlgorithmEnum|iterable|null $algorithms
    ): void {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                ErrorCode::ITEM_NOT_FOUND->value
            );
        }
        $this->algorithms[$identifier] = $this->resolveAlgorithms($algorithms);
    }

    /**
     * Set fail-fast mode
     *
     * @param bool $failFast If true, throw exception on first error. If false, collect errors and continue.
     * @return $this
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
     * If the deleted item was the last added, the lastAddedIdentifier is updated to the
     * last remaining item (or null if no items remain).
     *
     * @param string $identifier
     * @return $this
     * @throws CompressionException if item not found
     */
    public function delete(string $identifier): self
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                ErrorCode::ITEM_NOT_FOUND->value
            );
        }

        unset($this->items[$identifier], $this->algorithms[$identifier]);

        // Update lastAddedIdentifier if we just deleted it
        if ($this->lastAddedIdentifier === $identifier) {
            // Set to the last remaining item, or null if empty
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
     * @return array<string, Result>
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

                $results[$identifier] = Result::createError($identifier, $e);
            }
        }

        return $results;
    }

    /**
     * Compress a specific item by its identifier
     *
     * @param string $identifier
     *
     * @return Result
     * @throws CompressionException if failFast is true and compression fails, or if item not found
     */
    public function compressOne(string $identifier): Result
    {
        if (!isset($this->items[$identifier])) {
            throw new CompressionException(
                sprintf('Item with identifier "%s" not found', $identifier),
                ErrorCode::ITEM_NOT_FOUND->value
            );
        }

        try {
            return $this->compressItem($identifier, $this->items[$identifier]);
        } catch (CompressionException $e) {
            if ($this->failFast) {
                throw $e;
            }

            return Result::createError($identifier, $e);
        }
    }

    /**
     * Internal method to compress a single item
     *
     * @param string $identifier
     * @param Item $item
     *
     * @return Result
     * @throws CompressionException if failFast is true and any algorithm fails
     */
    private function compressItem(string $identifier, Item $item): Result
    {
        // Avoid reading payload early; enforce limit first
        $algorithms = $this->algorithms[$identifier];
        $compressed = [];
        $algorithmErrors = [];
        $lastError = null;

        // Enforce limit once before processing (applies to both raw and file)
        try {
            $item->ensureWithinLimit();
        } catch (CompressionException $e) {
            // Fail-fast behavior preserved
            if ($this->failFast) {
                throw $e;
            }
            // Collect as a complete failure
            return Result::createError($identifier, $e);
        }

        $content = null; // lazy-load for a non-stream path

        foreach ($algorithms as $algorithmValue => $level) {
            try {
                $algorithm = AlgorithmEnum::from($algorithmValue);

                if (!$algorithm->isAvailable()) {
                    throw new CompressionException(
                        sprintf(
                            'Algorithm %s requires %s extension; install/enable ext-%s',
                            $algorithm->name,
                            $algorithm->getRequiredExtension(),
                            $algorithm->getRequiredExtension()
                        ),
                        ErrorCode::ALGORITHM_UNAVAILABLE->value
                    );
                }

                $compressor = Factory::create($algorithm);

                // Prefer a streaming path for files if supported by the compressor
                if ($item->isFile() && $compressor instanceof StreamCompressorInterface) {
                    $fp = @fopen($item->getContent(), 'rb');

                    if ($fp === false) {
                        throw new CompressionException(
                            sprintf('File not readable: %s (check permissions/SELinux)', $item->getContent()),
                            ErrorCode::FILE_NOT_READABLE->value
                        );
                    }
                    try {
                        $compressed[$algorithmValue] = $compressor->compressStream($fp, $level);
                    } finally {
                        fclose($fp);
                    }
                } else {
                    $content ??= $item->readContent();
                    $compressed[$algorithmValue] = $compressor->compress($content, $level);
                }
            } catch (CompressionException $e) {
                $lastError = $e;
                if ($this->failFast) {
                    throw $e;
                }
                // In non-failFast mode, track per-algorithm error with code and message
                $algorithmErrors[$algorithmValue] = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ];
            } catch (ValueError $e) {
                $lastError = new CompressionException(
                    sprintf('Unknown algorithm: %s', $algorithmValue),
                    ErrorCode::UNKNOWN_ALGORITHM->value,
                    $e
                );
                if ($this->failFast) {
                    throw $lastError;
                }
                // Track per-algorithm error with code and message
                $algorithmErrors[$algorithmValue] = [
                    'code' => $lastError->getCode(),
                    'message' => $lastError->getMessage(),
                ];
            }
        }

        if (empty($compressed) && $lastError !== null) {
            throw $lastError;
        }

        if (!empty($algorithmErrors)) {
            return Result::createPartial($identifier, $compressed, $algorithmErrors);
        }

        return new Result($identifier, $compressed);
    }

    /**
     * Get all items currently in the builder
     *
     * @return array<string, Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get item identifiers in insertion order
     *
     * @return list<string>
     */
    public function getIdentifiers(): array
    {
        return array_keys($this->items);
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
     * @return Traversable<string, Item>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
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
     * @return $this
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
     * @param  AlgorithmEnum|iterable|null  $algorithms
     *
     * @return array<string, int>
     */
    private function resolveAlgorithms(
        AlgorithmEnum|iterable|null $algorithms
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
     * @param  AlgorithmEnum|iterable|null  $algorithms
     *
     * @return array<string, int>
     */
    private function normalizeAlgorithms(
        AlgorithmEnum|iterable|null $algorithms
    ): array {
        if ($algorithms === null) {
            return [
                AlgorithmEnum::Gzip->value   => AlgorithmEnum::Gzip->getDefaultLevel(),
                AlgorithmEnum::Brotli->value => AlgorithmEnum::Brotli->getDefaultLevel(),
            ];
        }

        if ($algorithms instanceof AlgorithmEnum) {
            return [
                $algorithms->value => $algorithms->getDefaultLevel(),
            ];
        }

        // Accept any iterable (arrays, generators, collections)
        $input = is_array($algorithms) ? $algorithms : iterator_to_array($algorithms);

        $normalized = [];
        foreach ($input as $key => $value) {
            try {
                if ($key instanceof AlgorithmEnum) {
                    $algorithm = $key;
                    $level = $value;
                    if (!is_int($level)) {
                        throw new CompressionException(
                            sprintf('Level must be integer for %s, got %s', $algorithm->value, get_debug_type($level)),
                            ErrorCode::INVALID_LEVEL_TYPE->value
                        );
                    }
                } elseif ($value instanceof AlgorithmEnum) {
                    $algorithm = $value;
                    $level = $algorithm->getDefaultLevel();
                } elseif (is_string($key)) {
                    // Key is algorithm name; parse it regardless of value type to provide better errors
                    $algorithm = AlgorithmEnum::from($key);
                    $level = $value;
                    if (!is_int($level)) {
                        throw new CompressionException(
                            sprintf('Level must be integer for %s, got %s', $algorithm->value, get_debug_type($level)),
                            ErrorCode::INVALID_LEVEL_TYPE->value
                        );
                    }
                } elseif (is_int($key) && is_string($value)) {
                    $algorithm = AlgorithmEnum::from($value);
                    $level = $algorithm->getDefaultLevel();
                } else {
                    $contextKey = json_encode($key, JSON_THROW_ON_ERROR);
                    $contextValue = json_encode($value, JSON_THROW_ON_ERROR);
                    throw new CompressionException(
                        sprintf('Invalid algorithm specification: key=%s, value=%s', $contextKey, $contextValue),
                        ErrorCode::INVALID_ALGORITHM_SPEC->value
                    );
                }

                // Validate level range after type check
                $algorithm->validateLevel($level);
                $normalized[$algorithm->value] = $level;
            } catch (ValueError $e) {
                // Extract the actual problematic value for better error messages
                // Wrapped json_encode to avoid leaking JsonException
                try {
                    $rawValue = is_string($key) ? $key : (is_string($value) ? $value : json_encode([$key, $value], JSON_THROW_ON_ERROR));
                } catch (\JsonException $je) {
                    throw new CompressionException(
                        'Invalid algorithm specification (json encode failed)',
                        ErrorCode::INVALID_ALGORITHM_SPEC->value,
                        $je
                    );
                }
                throw new CompressionException(
                    sprintf('Unknown algorithm: %s', $rawValue),
                    ErrorCode::UNKNOWN_ALGORITHM->value,
                    $e
                );
            } catch (\JsonException $je) {
                // Guard any unexpected JSON failures from above json_encode calls
                throw new CompressionException(
                    'Invalid algorithm specification (json encode failed)',
                    ErrorCode::INVALID_ALGORITHM_SPEC->value,
                    $je
                );
            }
        }

        if (empty($normalized)) {
            throw new CompressionException(
                'At least one compression algorithm must be specified',
                ErrorCode::EMPTY_ALGORITHMS->value
            );
        }

        return $normalized;
    }
}
