<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\ValueObjects;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use LogicException;

/**
 * Immutable set of compression algorithms with levels
 * All levels are validated at construction time (fail-fast)
 */
final readonly class AlgorithmSet
{
    /** @var array<string, int> Internal storage: algo->value => level */
    private array $algorithms;

    /**
     * Private constructor - use static factory methods
     * @param array<string, int> $normalized Already validated and normalized algorithms
     */
    private function __construct(array $normalized)
    {
        $this->algorithms = $normalized;
    }

    /**
     * Create from multiple algorithm/level pairs
     * @param array<array{AlgorithmEnum, int}> $pairs Array of [algo, level] pairs
     */
    public static function from(array $pairs): self
    {
        if (empty($pairs)) {
            throw new CompressionException('At least one algorithm required');
        }

        $normalized = [];

        foreach ($pairs as $pair) {
            [$algo, $level] = $pair;
            $algo->validateLevel($level);
            $normalized[$algo->value] = $level;
        }

        return new self($normalized);
    }

    /**
     * Create a set with all algorithms at default levels
     */
    public static function fromDefaults(): self
    {
        $gzip = AlgorithmEnum::Gzip;
        $brotli = AlgorithmEnum::Brotli;
        $zstd = AlgorithmEnum::Zstd;

        return new self([
            $gzip->value => $gzip->getDefaultLevel(),
            $brotli->value => $brotli->getDefaultLevel(),
            $zstd->value => $zstd->getDefaultLevel(),
        ]);
    }

    /**
     * Create set with only Gzip
     */
    public static function gzip(int $level = 6): self
    {
        $algo = AlgorithmEnum::Gzip;
        $algo->validateLevel($level);

        return new self([$algo->value => $level]);
    }

    /**
     * Create a set with only Brotli
     */
    public static function brotli(int $level = 11): self
    {
        $algo = AlgorithmEnum::Brotli;
        $algo->validateLevel($level);

        return new self([$algo->value => $level]);
    }

    /**
     * Create a set with only Zstd
     */
    public static function zstd(int $level = 3): self
    {
        $algo = AlgorithmEnum::Zstd;
        $algo->validateLevel($level);

        return new self([$algo->value => $level]);
    }

    /**
     * Merge with another set (additive)
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->algorithms, $other->algorithms));
    }

    /**
     * Check if the algorithm is in this set
     */
    public function has(AlgorithmEnum $algo): bool
    {
        return isset($this->algorithms[$algo->value]);
    }

    /**
     * Get compression level for algorithm
     *
     * @throws LogicException if algorithm isn't in set
     */
    public function getLevel(AlgorithmEnum $algo): int
    {
        return $this->algorithms[$algo->value] ?? throw new LogicException("Algorithm not in set: {$algo->value}");
    }

    /**
     * Get all algorithms as a list of [algo, level] pairs
     *
     * @return array<array{AlgorithmEnum, int}>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->algorithms as $value => $level) {
            $result[] = [AlgorithmEnum::from($value), $level];
        }

        return $result;
    }

    /**
     * Count algorithms in a set
     */
    public function count(): int
    {
        return count($this->algorithms);
    }
}
