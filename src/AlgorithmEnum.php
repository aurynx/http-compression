<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

enum AlgorithmEnum: string
{
    case Gzip   = 'gzip';
    case Brotli = 'br';
    case Zstd   = 'zstd';

    /**
     * Check if the algorithm is available on the current system
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Gzip   => extension_loaded('zlib'),
            self::Brotli => extension_loaded('brotli'),
            self::Zstd   => extension_loaded('zstd'),
        };
    }

    /**
     * Get all available algorithms on the current system
     *
     * @return array<self> List of available algorithm enums
     */
    public static function available(): array
    {
        return array_filter(
            self::cases(),
            static fn (self $algo): bool => $algo->isAvailable()
        );
    }

    /**
     * Get the default compression level for this algorithm
     *
     * These levels are balanced for dynamic HTTP content:
     * - gzip: 6 (good compression with acceptable speed)
     * - brotli: 4 (balanced compression/speed ratio)
     * - zstd: 3 (fast with good compression)
     */
    public function getDefaultLevel(): int
    {
        return match ($this) {
            self::Gzip   => 6,
            self::Brotli => 4,
            self::Zstd   => 3,
        };
    }

    /**
     * Get the minimum compression level for this algorithm
     */
    public function getMinLevel(): int
    {
        return match ($this) {
            self::Gzip   => 1,
            self::Brotli => 0,
            self::Zstd   => 1,
        };
    }

    /**
     * Get the maximum compression level for this algorithm
     */
    public function getMaxLevel(): int
    {
        return match ($this) {
            self::Gzip   => 9,
            self::Brotli => 11,
            self::Zstd   => 22,
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
                    '%s level out of range: level=%d, allowed=[%d..%d]',
                    $this->name,
                    $level,
                    $this->getMinLevel(),
                    $this->getMaxLevel()
                ),
                ErrorCode::LEVEL_OUT_OF_RANGE->value
            );
        }
    }

    /**
     * Get the required PHP extension name
     */
    public function getRequiredExtension(): string
    {
        return match ($this) {
            self::Gzip   => 'zlib',
            self::Brotli => 'brotli',
            self::Zstd   => 'zstd',
        };
    }
}
