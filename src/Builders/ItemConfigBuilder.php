<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Builders;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

/**
 * Fluent builder for ItemConfig
 * Mutable builder that produces immutable ItemConfig
 */
final class ItemConfigBuilder
{
    /** @var array<array{AlgorithmEnum, int}> */
    private array $algorithmPairs = [];

    private ?int $maxBytes = null;

    /**
     * Add Gzip compression with a specified level
     */
    public function withGzip(int $level = 6): self
    {
        $algo = AlgorithmEnum::Gzip;
        $algo->validateLevel($level);
        $this->algorithmPairs[] = [$algo, $level];

        return $this;
    }

    /**
     * Add Brotli compression with a specified level
     */
    public function withBrotli(int $level = 11): self
    {
        $algo = AlgorithmEnum::Brotli;
        $algo->validateLevel($level);
        $this->algorithmPairs[] = [$algo, $level];

        return $this;
    }

    /**
     * Add Zstd compression with specified level
     */
    public function withZstd(int $level = 3): self
    {
        $algo = AlgorithmEnum::Zstd;
        $algo->validateLevel($level);
        $this->algorithmPairs[] = [$algo, $level];

        return $this;
    }

    /**
     * Add a specific algorithm with a custom level
     */
    public function withAlgorithm(AlgorithmEnum $algo, int $level): self
    {
        $algo->validateLevel($level);
        $this->algorithmPairs[] = [$algo, $level];

        return $this;
    }

    /**
     * Add all algorithms with their default levels
     */
    public function withDefaults(): self
    {
        $this->algorithmPairs[] = [AlgorithmEnum::Gzip, AlgorithmEnum::Gzip->getDefaultLevel()];
        $this->algorithmPairs[] = [AlgorithmEnum::Brotli, AlgorithmEnum::Brotli->getDefaultLevel()];
        $this->algorithmPairs[] = [AlgorithmEnum::Zstd, AlgorithmEnum::Zstd->getDefaultLevel()];

        return $this;
    }

    /**
     * Remove a specific algorithm from the set
     */
    public function skip(AlgorithmEnum $algo): self
    {
        $this->algorithmPairs = array_filter(
            $this->algorithmPairs,
            fn (array $pair) => $pair[0] !== $algo,
        );

        return $this;
    }

    /**
     * Keep only specified algorithms (filtering)
     */
    public function restrictTo(AlgorithmEnum ...$algos): self
    {
        $allowed = array_flip(array_map(fn ($a) => $a->value, $algos));

        $this->algorithmPairs = array_filter(
            $this->algorithmPairs,
            fn (array $pair) => isset($allowed[$pair[0]->value]),
        );

        return $this;
    }

    /**
     * Set maximum byte size for compression input
     * Files/data larger than this will be skipped or fail
     */
    public function limitBytes(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new CompressionException('Byte limit must be positive');
        }

        $this->maxBytes = $bytes;

        return $this;
    }

    /**
     * Build immutable ItemConfig
     *
     * @throws CompressionException if no algorithms configured
     */
    public function build(): ItemConfig
    {
        if (empty($this->algorithmPairs)) {
            throw new CompressionException('At least one algorithm must be configured');
        }

        return new ItemConfig(
            algorithms: AlgorithmSet::from($this->algorithmPairs),
            maxBytes: $this->maxBytes,
        );
    }
}
