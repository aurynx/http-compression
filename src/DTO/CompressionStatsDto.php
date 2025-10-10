<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\DTO;

use Aurynx\HttpCompression\AlgorithmEnum;

/**
 * Aggregated compression statistics for batch operations
 */
final readonly class CompressionStatsDto
{
    /**
     * @param int $totalItems Total number of items processed
     * @param int $successfulItems Number of successfully compressed items
     * @param int $failedItems Number of failed items
     * @param int $totalOriginalBytes Total original size across all items
     * @param array<string, int> $totalCompressedBytes Total compressed size per algorithm
     * @param array<string, int> $totalSavedBytes Total bytes saved per algorithm
     * @param array<string, float> $averageRatio Average compression ratio per algorithm
     */
    private function __construct(
        private int $totalItems,
        private int $successfulItems,
        private int $failedItems,
        private int $totalOriginalBytes,
        private array $totalCompressedBytes,
        private array $totalSavedBytes,
        private array $averageRatio
    ) {
    }

    /**
     * Create statistics from an array of CompressionResult objects
     *
     * @param array<CompressionResultDto> $results
     */
    public static function fromResults(array $results): self
    {
        $totalItems = count($results);
        $successfulItems = 0;
        $failedItems = 0;
        $totalOriginalBytes = 0;
        $compressedByAlgo = [];
        $countsByAlgo = [];

        foreach ($results as $result) {
            if ($result->isError()) {
                $failedItems++;
                continue;
            }

            if ($result->isOk() || $result->isPartial()) {
                $successfulItems++;
            }

            $originalSize = $result->getOriginalSize();
            if ($originalSize !== null) {
                $totalOriginalBytes += $originalSize;
            }

            // Aggregate by algorithm
            foreach ($result->getCompressed() as $algoName => $compressed) {
                if (!isset($compressedByAlgo[$algoName])) {
                    $compressedByAlgo[$algoName] = 0;
                    $countsByAlgo[$algoName] = 0;
                }

                $algo = AlgorithmEnum::from($algoName);
                $compressedSize = $result->getCompressedSize($algo);

                if ($compressedSize !== null) {
                    $compressedByAlgo[$algoName] += $compressedSize;
                    $countsByAlgo[$algoName]++;
                }
            }
        }

        // Calculate saved bytes and average ratios
        $totalSavedBytes = [];
        $averageRatio = [];

        foreach ($compressedByAlgo as $algoName => $totalCompressed) {
            $totalSavedBytes[$algoName] = $totalOriginalBytes - $totalCompressed;

            // Average ratio across all items that used this algorithm
            if ($totalOriginalBytes > 0) {
                $averageRatio[$algoName] = $totalCompressed / $totalOriginalBytes;
            } else {
                $averageRatio[$algoName] = 0.0;
            }
        }

        return new self(
            $totalItems,
            $successfulItems,
            $failedItems,
            $totalOriginalBytes,
            $compressedByAlgo,
            $totalSavedBytes,
            $averageRatio
        );
    }

    /**
     * Get total number of items processed
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Get number of successfully compressed items
     */
    public function getSuccessfulItems(): int
    {
        return $this->successfulItems;
    }

    /**
     * Get number of failed items
     */
    public function getFailedItems(): int
    {
        return $this->failedItems;
    }

    /**
     * Get success rate (0.0 to 1.0)
     */
    public function getSuccessRate(): float
    {
        if ($this->totalItems === 0) {
            return 0.0;
        }

        return $this->successfulItems / $this->totalItems;
    }

    /**
     * Get total original size across all items in bytes
     */
    public function getTotalOriginalBytes(): int
    {
        return $this->totalOriginalBytes;
    }

    /**
     * Get total compressed size for a specific algorithm in bytes
     */
    public function getTotalCompressedBytes(AlgorithmEnum $algorithm): ?int
    {
        return $this->totalCompressedBytes[$algorithm->value] ?? null;
    }

    /**
     * Get total bytes saved for a specific algorithm
     */
    public function getTotalSavedBytes(AlgorithmEnum $algorithm): ?int
    {
        return $this->totalSavedBytes[$algorithm->value] ?? null;
    }

    /**
     * Get average compression ratio for a specific algorithm (0.0 to 1.0)
     */
    public function getAverageRatio(AlgorithmEnum $algorithm): ?float
    {
        return $this->averageRatio[$algorithm->value] ?? null;
    }

    /**
     * Get average compression percentage for a specific algorithm
     */
    public function getAveragePercentage(AlgorithmEnum $algorithm): ?float
    {
        $ratio = $this->getAverageRatio($algorithm);
        if ($ratio === null) {
            return null;
        }

        return (1.0 - $ratio) * 100;
    }

    /**
     * Check if any items used the specified algorithm
     */
    public function hasAlgorithm(AlgorithmEnum $algorithm): bool
    {
        return isset($this->totalCompressedBytes[$algorithm->value]);
    }

    /**
     * Get list of algorithms used across all items
     *
     * @return array<string>
     */
    public function getAlgorithms(): array
    {
        return array_keys($this->totalCompressedBytes);
    }

    /**
     * Format statistics as a human-readable string
     */
    public function summary(): string
    {
        $lines = [];
        $lines[] = "Compression Statistics:";
        $lines[] = "  Total items: {$this->totalItems}";
        $lines[] = "  Successful: {$this->successfulItems}";

        if ($this->failedItems > 0) {
            $lines[] = "  Failed: {$this->failedItems}";
        }

        $lines[] = "  Original size: " . $this->formatBytes($this->totalOriginalBytes);

        foreach ($this->getAlgorithms() as $algoName) {
            $algo = AlgorithmEnum::from($algoName);
            $compressed = $this->getTotalCompressedBytes($algo);
            $saved = $this->getTotalSavedBytes($algo);
            $percentage = $this->getAveragePercentage($algo);

            $lines[] = sprintf(
                "  %s: %s (saved %s, %.1f%% reduction)",
                $algoName,
                $this->formatBytes($compressed ?? 0),
                $this->formatBytes($saved ?? 0),
                $percentage ?? 0.0
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Format bytes as human-readable string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $size, $units[$unitIndex]);
    }
}
