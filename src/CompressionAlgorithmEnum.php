<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

enum CompressionAlgorithmEnum: string
{
    case Gzip = 'gzip';
    case Brotli = 'br';

    /**
     * Check if the algorithm is available on the current system
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Gzip => extension_loaded('zlib'),
            self::Brotli => extension_loaded('brotli'),
        };
    }

    /**
     * Get the default compression level for this algorithm
     */
    public function getDefaultLevel(): int
    {
        return match ($this) {
            self::Gzip => 9,
            self::Brotli => 11,
        };
    }

    /**
     * Get the minimum compression level for this algorithm
     */
    public function getMinLevel(): int
    {
        return match ($this) {
            self::Gzip => 1,
            self::Brotli => 0,
        };
    }

    /**
     * Get the maximum compression level for this algorithm
     */
    public function getMaxLevel(): int
    {
        return match ($this) {
            self::Gzip => 9,
            self::Brotli => 11,
        };
    }

    /**
     * Validate compression level for this algorithm
     *
     * @throws CompressionException
     */
    public function validateLevel(int $level): void
    {
        if ($level < $this->getMinLevel() || $level > $this->getMaxLevel()) {
            throw new CompressionException(
                sprintf(
                    '%s level must be between %d and %d, got %d',
                    $this->name,
                    $this->getMinLevel(),
                    $this->getMaxLevel(),
                    $level
                )
            );
        }
    }

    /**
     * Get the required PHP extension name
     */
    public function getRequiredExtension(): string
    {
        return match ($this) {
            self::Gzip => 'zlib',
            self::Brotli => 'brotli',
        };
    }
}
