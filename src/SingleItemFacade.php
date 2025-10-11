<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

use Aurynx\HttpCompression\Results\CompressionItemResult;
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;
use Aurynx\HttpCompression\ValueObjects\CompressionInput;
use Aurynx\HttpCompression\ValueObjects\DataInput;
use Aurynx\HttpCompression\ValueObjects\FileInput;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\OutputConfig;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Throwable;

/**
 * Simplified facade for single-item compression
 * Optimized for quick one-off compression tasks
 *
 * @example
 * ```php
 * // Compress and save to file
 * CompressorFacade::once()
 *     ->file('index.html')
 *     ->withGzip(9)
 *     ->saveTo('index.html.gz');
 *
 * // Compress and get result
 * $result = CompressorFacade::once()
 *     ->data($html)
 *     ->withBrotli(11)
 *     ->compress();
 * ```
 */
final class SingleItemFacade
{
    private ?CompressionInput $input = null;
    private ?ItemConfig $config = null;

    /**
     * Set input from a file path
     */
    public function file(string $path): self
    {
        $this->input = new FileInput(
            id: 'single',
            path: $path,
        );

        return $this;
    }

    /**
     * Set input from in-memory data
     */
    public function data(string $data): self
    {
        $this->input = new DataInput(
            id: 'single',
            data: $data,
        );

        return $this;
    }

    /**
     * Use Gzip compression
     */
    public function withGzip(int $level = 6): self
    {
        $this->config = new ItemConfig(AlgorithmSet::gzip($level));

        return $this;
    }

    /**
     * Use Brotli compression
     */
    public function withBrotli(int $level = 11): self
    {
        $this->config = new ItemConfig(AlgorithmSet::brotli($level));

        return $this;
    }

    /**
     * Use Zstd compression
     */
    public function withZstd(int $level = 3): self
    {
        $this->config = new ItemConfig(AlgorithmSet::zstd($level));

        return $this;
    }

    /**
     * Use custom algorithm with a specific level
     */
    public function withAlgorithm(AlgorithmEnum $algo, int $level): self
    {
        $algo->validateLevel($level);
        $this->config = new ItemConfig(AlgorithmSet::from([[ $algo, $level ]]));

        return $this;
    }

    /**
     * Compress and save to file
     * Requires exactly ONE algorithm to be configured
     *
     * @throws CompressionException if no input, no config, or multiple algorithms
     * @throws Throwable
     */
    public function saveTo(string $path): void
    {
        $this->validateInput();
        $this->validateConfig();

        assert($this->config !== null);

        // Validate exactly one algorithm
        $algorithms = $this->config->algorithms->toArray();

        if (count($algorithms) !== 1) {
            throw new CompressionException(
                'saveTo() requires exactly one algorithm, got ' . count($algorithms) . '. ' .
                'Use compress() for multiple algorithms.',
            );
        }

        // Compress
        $result = $this->executeCompression();

        // Extract the only algorithm enum from the first pair [AlgorithmEnum, level]
        [$algoEnum] = $algorithms[0];
        $data = $result->getData($algoEnum);

        // Save to a file with exclusive lock
        $written = file_put_contents($path, $data, LOCK_EX);

        if ($written === false) {
            throw new CompressionException("Failed to write compressed data to: {$path}");
        }
    }

    /**
     * Compress and return result
     *
     * @throws CompressionException if no input or no config
     * @throws Throwable
     */
    public function compress(): CompressionItemResult
    {
        $this->validateInput();
        $this->validateConfig();

        return $this->executeCompression();
    }

    /**
     * Validate that input is set
     *
     * @throws CompressionException
     */
    private function validateInput(): void
    {
        if ($this->input === null) {
            throw new CompressionException(
                'No input specified. Use file() or data() first.',
            );
        }
    }

    /**
     * Validate that config is set
     *
     * @throws CompressionException
     */
    private function validateConfig(): void
    {
        if ($this->config === null) {
            throw new CompressionException(
                'No compression algorithm specified. Use withGzip(), withBrotli(), or withZstd().',
            );
        }
    }

    /**
     * Execute compression
     *
     * @throws Throwable
     */
    private function executeCompression(): CompressionItemResult
    {
        assert($this->input !== null);
        assert($this->config !== null);

        $engine = new CompressionEngine(
            outputConfig: OutputConfig::inMemory(),
            failFast: true,
        );

        $dto = $engine->compressItem($this->input, $this->config);

        // Convert DTO to CompressionItemResult
        return new CompressionItemResult(
            id: $dto->id,
            success: $dto->success,
            originalSize: $dto->originalSize,
            compressed: $dto->compressed,
            compressedSizes: $dto->compressedSizes,
            compressionTimes: $dto->compressionTimes,
            errors: $dto->errors,
        );
    }
}
