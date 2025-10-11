<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\ValueObjects;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Enums\OutputModeEnum;

/**
 * Configuration for compression output
 * Immutable value object with fail-fast validation
 */
final readonly class OutputConfig
{
    public function __construct(
        public OutputModeEnum $mode,
        public ?string $directory = null,
        public bool $keepStructure = false,
        public int $maxMemoryBytes = 5_000_000, // 5MB default
        public bool $enforceMemoryLimit = true,
    ) {
        // Validate directory mode
        if ($mode === OutputModeEnum::Directory && $directory === null) {
            throw new CompressionException('Directory required for OutputModeEnum::Directory');
        }

        // Validate memory limit
        if ($mode === OutputModeEnum::InMemory && $maxMemoryBytes <= 0) {
            throw new CompressionException('maxMemoryBytes must be positive for in-memory mode');
        }
    }

    /**
     * Create output config for the directory with validation
     */
    public static function toDirectory(string $dir, bool $keepStructure = false): self
    {
        // Normalize a path
        $dir = rtrim($dir, '/\\');

        // Create a directory if it doesn't exist
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new CompressionException("Failed to create directory: {$dir}");
        }

        // Resolve the real path
        $realDir = realpath($dir);

        if ($realDir === false) {
            throw new CompressionException("Directory does not exist: {$dir}");
        }

        // Check writable
        if (!is_writable($realDir)) {
            throw new CompressionException("Directory is not writable: {$realDir}");
        }

        return new self(
            mode: OutputModeEnum::Directory,
            directory: $realDir,
            keepStructure: $keepStructure,
        );
    }

    /**
     * Create output config for in-memory compression
     */
    public static function inMemory(int $maxBytes = 5_000_000, bool $enforceLimit = true): self
    {
        return new self(
            mode: OutputModeEnum::InMemory,
            maxMemoryBytes: $maxBytes,
            enforceMemoryLimit: $enforceLimit,
        );
    }

    /**
     * Create output config for streaming
     */
    public static function stream(): self
    {
        return new self(mode: OutputModeEnum::Stream);
    }

    /**
     * Validate that the output size doesn't exceed the memory limit
     *
     * @throws CompressionException if limit exceeded and enforceMemoryLimit=true
     */
    public function validateMemoryLimit(int $bytes): void
    {
        if ($this->mode !== OutputModeEnum::InMemory) {
            return;
        }

        if (($bytes > $this->maxMemoryBytes) && $this->enforceMemoryLimit) {
            throw new CompressionException(
                "Output size ({$bytes} bytes) exceeds memory limit ({$this->maxMemoryBytes} bytes). " .
                'Use toDir() or increase limit with inMemory($higherLimit)',
            );
        }
    }
}
