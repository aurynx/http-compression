<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Results;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use LogicException;
use OutOfBoundsException;
use Traversable;

/**
 * Result of batch compression
 * Container for multiple item results with convenient access methods
 *
 * @implements IteratorAggregate<string, CompressionItemResult>
 */
final readonly class CompressionResult implements IteratorAggregate, Countable
{
    /**
     * @param array<string, CompressionItemResult> $results
     */
    public function __construct(
        private array $results,
    ) {
    }

    /**
     * Get result by item ID
     * @throws OutOfBoundsException if ID not found
     */
    public function get(string $id): CompressionItemResult
    {
        return $this->results[$id] ?? throw new OutOfBoundsException("No result for ID: {$id}");
    }

    /**
     * Get the first result (useful for single-item compression)
     * @throws LogicException if no results available
     */
    public function first(): CompressionItemResult
    {
        foreach ($this->results as $result) {
            return $result;
        }

        throw new LogicException('No results available');
    }

    /**
     * Check if all items succeeded without errors
     */
    public function allOk(): bool
    {
        return array_all(
            $this->results,
            static fn ($result): bool => $result->isOk(),
        );
    }

    /**
     * Get only failed results
     * @return array<string, CompressionItemResult>
     */
    public function failures(): array
    {
        return array_filter(
            $this->results,
            static fn (CompressionItemResult $result): bool => !$result->isOk(),
        );
    }

    /**
     * Get only successful results
     * @return array<string, CompressionItemResult>
     */
    public function successes(): array
    {
        return array_filter(
            $this->results,
            static fn (CompressionItemResult $result): bool => $result->isOk(),
        );
    }

    /**
     * Get aggregated statistics
     */
    public function summary(): CompressionSummaryResult
    {
        return new CompressionSummaryResult($this->results);
    }

    /**
     * Iterate over all results
     * @return Traversable<string, CompressionItemResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    /**
     * Get the total number of items
     */
    public function count(): int
    {
        return count($this->results);
    }

    /**
     * Get all results as an array
     * @return array<string, CompressionItemResult>
     */
    public function toArray(): array
    {
        return $this->results;
    }
}
