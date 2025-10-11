<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Results;

use Aurynx\HttpCompression\Enums\AlgorithmEnum;

/**
 * Aggregated statistics for batch compression
 * Provides metrics like p50/p95, average ratios, total times
 */
final readonly class CompressionSummaryResult
{
    private int $totalItems;
    private int $successCount;
    private int $failureCount;
    private int $totalOriginalBytes;

    /**
     * @param array<string, CompressionItemResult> $results
     */
    public function __construct(
        private array $results,
    ) {
        $this->totalItems = count($results);
        $this->successCount = count(array_filter($results, static fn ($result): bool => $result->isOk()));
        $this->failureCount = $this->totalItems - $this->successCount;
        $this->totalOriginalBytes = array_sum(array_map(static fn ($result) => $result->originalSize, $results));
    }

    /**
     * Get the total number of items processed
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Get the number of successful items
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get the number of failed items
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get the success rate (0.0 to 1.0)
     */
    public function getSuccessRate(): float
    {
        if ($this->totalItems === 0) {
            return 0.0;
        }

        return $this->successCount / $this->totalItems;
    }

    /**
     * Get the total original size in bytes
     */
    public function getTotalOriginalBytes(): int
    {
        return $this->totalOriginalBytes;
    }

    /**
     * Get total compressed size for algorithm
     */
    public function getTotalCompressedBytes(AlgorithmEnum $algo): int
    {
        return array_sum(
            array_map(
                static fn ($result) => $result->has($algo) ? $result->getSize($algo) : 0,
                $this->results,
            ),
        );
    }

    /**
     * Get total bytes saved by compression
     */
    public function getTotalBytesSaved(AlgorithmEnum $algo): int
    {
        return $this->totalOriginalBytes - $this->getTotalCompressedBytes($algo);
    }

    /**
     * Average compression ratio (compressed / original)
     * Value < 1.0 means compression was effective
     * Value = 1.0 means no compression gain
     * Value > 1.0 means compressed is larger (unlikely but possible)
     */
    public function getAverageRatio(AlgorithmEnum $algo): float
    {
        $ratios = array_map(
            static fn ($result) => $result->has($algo) ? $result->getRatio($algo) : null,
            $this->results,
        );

        $ratios = array_filter(
            $ratios,
            static fn ($result): bool => $result !== null,
        );

        if (empty($ratios)) {
            return 0.0;
        }

        return array_sum($ratios) / count($ratios);
    }

    /**
     * Median compression ratio (p50)
     * Value < 1.0 = good compression
     */
    public function getMedianRatio(AlgorithmEnum $algo): float
    {
        return $this->getPercentileRatio($algo, 50);
    }

    /**
     * 95th percentile compression ratio
     * Useful to detect outliers with poor compression
     */
    public function getP95Ratio(AlgorithmEnum $algo): float
    {
        return $this->getPercentileRatio($algo, 95);
    }

    /**
     * Median compression time in milliseconds (p50)
     */
    public function getMedianTimeMs(AlgorithmEnum $algo): float
    {
        return $this->getPercentileTime($algo, 50);
    }

    /**
     * 95th percentile compression time in milliseconds
     */
    public function getP95TimeMs(AlgorithmEnum $algo): float
    {
        return $this->getPercentileTime($algo, 95);
    }

    /**
     * Total compression time for all items
     */
    public function getTotalTimeMs(AlgorithmEnum $algo): float
    {
        return array_sum(
            array_map(
                static fn ($result) => $result->has($algo) ? $result->getCompressionTimeMs($algo) : 0.0,
                $this->results,
            ),
        );
    }

    /**
     * Average compression time per item
     */
    public function getAverageTimeMs(AlgorithmEnum $algo): float
    {
        $times = array_map(
            static fn ($result) => $result->has($algo) ? $result->getCompressionTimeMs($algo) : null,
            $this->results,
        );

        $times = array_filter(
            $times,
            static fn ($time) => $time !== null,
        );

        if (empty($times)) {
            return 0.0;
        }

        return array_sum($times) / count($times);
    }

    /**
     * Get the percentile of compression ratios
     */
    private function getPercentileRatio(AlgorithmEnum $algo, int $percentile): float
    {
        $ratios = array_map(
            static fn ($result) => $result->has($algo) ? $result->getRatio($algo) : null,
            $this->results,
        );

        $ratios = array_filter(
            $ratios,
            static fn ($result): bool => $result !== null,
        );

        if (empty($ratios)) {
            return 0.0;
        }

        sort($ratios);
        $index = (int) ceil(count($ratios) * $percentile / 100) - 1;
        $index = max(0, $index);

        return $ratios[$index] ?? 0.0;
    }

    /**
     * Get the percentile of compression times
     */
    private function getPercentileTime(AlgorithmEnum $algo, int $percentile): float
    {
        $times = array_map(
            static fn ($result) => $result->has($algo) ? $result->getCompressionTimeMs($algo) : null,
            $this->results,
        );

        $times = array_filter(
            $times,
            static fn ($time) => $time !== null,
        );

        if (empty($times)) {
            return 0.0;
        }

        sort($times);
        $index = (int) ceil(count($times) * $percentile / 100) - 1;
        $index = max(0, $index);

        return $times[$index] ?? 0.0;
    }
}
