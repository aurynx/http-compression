<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

use Aurynx\HttpCompression\Builders\ItemScopeBuilder;
use Aurynx\HttpCompression\Contracts\InputProviderInterface;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\OutputModeEnum;
use Aurynx\HttpCompression\Enums\PrecompressedExtensionEnum;
use Aurynx\HttpCompression\Providers\GlobInputProvider;
use Aurynx\HttpCompression\Results\CompressionResult;
use Aurynx\HttpCompression\Support\Hashing;
use Aurynx\HttpCompression\ValueObjects\CompressionInput;
use Aurynx\HttpCompression\ValueObjects\DataInput;
use Aurynx\HttpCompression\ValueObjects\FileInput;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\OutputConfig;
use Throwable;

/**
 * Main facade for batch compression (GoF Facade pattern)
 * Fluent instance-based API that hides complexity
 *
 * Pattern: CompressorFacade::make()->addFile()->compress()
 * NOT: CompressorFacade::compress() (Laravel-style static proxy)
 *
 * @example
 * ```php
 * $result = CompressorFacade::make()
 *     ->addFile('index.html')
 *     ->addGlob('*.css')
 *     ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
 *     ->toDir('./dist')
 *     ->compress();
 * ```
 */
final class CompressorFacade
{
    /** @var array<string, CompressionInput> */
    private array $inputs = [];

    /** @var array<string, ItemConfig> */
    private array $itemConfigs = [];

    private ?ItemConfig $defaultConfig = null;
    private ?OutputConfig $outputConfig = null;
    private bool $failFast = true;

    /** @var list<string> */
    private array $skipExtensions = [];

    private function __construct()
    {
    }

    /**
     * Create a new compressor instance
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Create a SingleItemFacade helper for one-off operations
     */
    public static function once(): SingleItemFacade
    {
        return new SingleItemFacade();
    }

    /**
     * Add single input with optional item-specific config
     *
     * @throws CompressionException if input ID already exists
     */
    public function add(CompressionInput $input, ?ItemConfig $config = null): self
    {
        if (isset($this->inputs[$input->id])) {
            throw new CompressionException("Duplicate input ID: {$input->id}");
        }

        $this->inputs[$input->id] = $input;

        if ($config !== null) {
            $this->itemConfigs[$input->id] = $config;
        }

        return $this;
    }

    /**
     * Add multiple inputs at once
     *
     * @param iterable<CompressionInput> $inputs
     */
    public function addMany(iterable $inputs): self
    {
        foreach ($inputs as $input) {
            $this->add($input);
        }

        return $this;
    }

    /**
     * Add a file by path
     *
     * @param string $path Absolute or relative file path
     * @param ItemConfig|null $config Item-specific configuration
     * @param string|null $id Custom ID (defaults to hash of realpath)
     */
    public function addFile(string $path, ?ItemConfig $config = null, ?string $id = null): self
    {
        $real = realpath($path) ?: $path;
        $input = new FileInput(
            id: $id ?? Hashing::fastId($real),
            path: $path,
        );

        return $this->add($input, $config);
    }

    /**
     * Add in-memory data
     *
     * @param string $data Raw data to compress
     * @param ItemConfig|null $config Item-specific configuration
     * @param string|null $id Custom ID (defaults to hash of data)
     */
    public function addData(string $data, ?ItemConfig $config = null, ?string $id = null): self
    {
        $input = new DataInput(
            id: $id ?? Hashing::fastId($data),
            data: $data,
        );

        return $this->add($input, $config);
    }

    /**
     * Add files matching glob pattern
     *
     * @param string $pattern Glob pattern (e.g., "*.html", "assets/**\\/*.css")
     * @param ItemConfig|null $config Configuration for all matched files
     *
     * @example
     * ```php
     * ->addGlob('*.html')
     * ->addGlob('assets/**\\/*.{css,js}')
     * ```
     */
    public function addGlob(string $pattern, ?ItemConfig $config = null): self
    {
        $provider = new GlobInputProvider($pattern);
        $inputs = $provider->provide();

        foreach ($inputs as $input) {
            $this->add($input, $config);
        }

        return $this;
    }

    /**
     * Add inputs from any provider
     *
     * @param InputProviderInterface $provider Custom input provider
     * @param ItemConfig|null $config Configuration for all inputs
     */
    public function addFrom(InputProviderInterface $provider, ?ItemConfig $config = null): self
    {
        foreach ($provider->provide() as $input) {
            $this->add($input, $config);
        }

        return $this;
    }

    /**
     * Configure a specific item via builder callback
     *
     * @example
     * $compressor->item(function (\Aurynx\HttpCompression\Builders\ItemScopeBuilder $item) {
     *     $item->withId('file1')->use(
     *         \Aurynx\HttpCompression\ValueObjects\ItemConfig::create()->withGzip(9)->build()
     *     );
     * });
     */
    public function item(callable $callback): self
    {
        $builder = new ItemScopeBuilder();
        $callback($builder);

        $configured = $builder->getConfiguredItem();

        if ($configured !== null) {
            [$id, $config] = $configured;
            $this->itemConfigs[$id] = $config;
        }

        return $this;
    }

    /**
     * Set default configuration for all items without explicit config
     *
     * @example
     * ```php
     * ->withDefaultConfig(
     *     ItemConfig::create()
     *         ->withGzip(9)
     *         ->withBrotli(11)
     *         ->build()
     * )
     * ```
     */
    public function withDefaultConfig(ItemConfig $config): self
    {
        $this->defaultConfig = $config;

        return $this;
    }

    /**
     * Save compressed files to a directory
     *
     * @param string $dir Directory path (created if it doesn't exist)
     * @param bool $keepStructure Preserve the source directory structure
     */
    public function toDir(string $dir, bool $keepStructure = false): self
    {
        $this->outputConfig = OutputConfig::toDirectory($dir, $keepStructure);

        return $this;
    }

    /**
     * Keep compressed data in memory
     *
     * @param int $maxBytes Memory limit per item (default: 5MB)
     */
    public function inMemory(int $maxBytes = 5_000_000): self
    {
        $this->outputConfig = OutputConfig::inMemory($maxBytes);

        return $this;
    }

    /**
     * Enable/disable fail-fast mode
     *
     * When enabled (default), first error stops entire batch
     * When disabled, collects all errors and returns partial results
     */
    public function failFast(bool $enable = true): self
    {
        $this->failFast = $enable;

        return $this;
    }

    /**
     * Skip files with specific extensions
     *
     * @param list<string> $extensions Extensions without dot (e.g., ['png', 'jpg'])
     *
     * @example
     * ```php
     * ->skipExtensions(['png', 'jpg', 'webp'])
     * ```
     */
    public function skipExtensions(array $extensions): self
    {
        // Normalize to lowercase and deduplicate
        $normalized = array_map(static fn (string $ext): string => strtolower($ext), $extensions);
        $this->skipExtensions = array_values(
            array_unique(array_merge($this->skipExtensions, $normalized)),
        );

        return $this;
    }

    /**
     * Skip common pre-compressed formats (images, videos, archives, etc.)
     *
     * Automatically skips: png, jpg, jpeg, gif, webp, avif, woff, woff2,
     * mp4, webm, mp3, zip, gz, br, zst, 7z, rar, pdf, etc.
     */
    public function skipAlreadyCompressed(): self
    {
        $this->skipExtensions = array_values(
            array_unique(array_merge(
                $this->skipExtensions,
                PrecompressedExtensionEnum::defaults(),
            )),
        );

        return $this;
    }

    /**
     * Execute compression
     *
     * @return CompressionResult Batch results with iteration support
     * @throws CompressionException|Throwable if validation fails or errors occur in fail-fast mode
     */
    public function compress(): CompressionResult
    {
        // Validation
        if (empty($this->inputs)) {
            throw new CompressionException('No inputs added. Use add() or addFile() first.');
        }

        if ($this->defaultConfig === null && empty($this->itemConfigs)) {
            throw new CompressionException(
                'No configuration provided. Use withDefaultConfig() or provide per-item configs.',
            );
        }

        // Default output config
        $outputConfig = $this->outputConfig ?? OutputConfig::inMemory();

        // Filter inputs by extension
        $filteredInputs = $this->filterInputsByExtension();

        // Create engine
        $engine = new CompressionEngine($outputConfig, $this->failFast);

        // Compress each input
        $results = [];

        foreach ($filteredInputs as $input) {
            $config = $this->itemConfigs[$input->id] ?? $this->defaultConfig;

            if ($config === null) {
                throw new CompressionException(
                    "No configuration for input {$input->id} and no default config set",
                );
            }

            $dto = $engine->compressItem($input, $config);

            // Convert DTO to CompressionItemResult
            $itemResult = new Results\CompressionItemResult(
                id: $dto->id,
                success: $dto->success,
                originalSize: $dto->originalSize,
                compressed: $dto->compressed,
                compressedSizes: $dto->compressedSizes,
                compressionTimes: $dto->compressionTimes,
                errors: $dto->errors,
            );

            // Optionally persist to directory
            $this->maybeSaveToDirectory($input, $itemResult, $outputConfig);

            $results[$input->id] = $itemResult;
        }

        return new CompressionResult($results);
    }

    /**
     * Filter inputs by extension skip rules
     *
     * @return array<string, CompressionInput>
     */
    private function filterInputsByExtension(): array
    {
        if (empty($this->skipExtensions)) {
            return $this->inputs;
        }

        $filtered = [];

        foreach ($this->inputs as $id => $input) {
            // Only filter file inputs
            if (!$input instanceof FileInput) {
                $filtered[$id] = $input;

                continue;
            }

            $ext = strtolower(pathinfo($input->path, PATHINFO_EXTENSION));

            // Use array_any with a static closure for membership check
            $shouldSkip = array_any(
                $this->skipExtensions,
                static fn (string $skipped): bool => $skipped === $ext,
            );

            if (!$shouldSkip) {
                $filtered[$id] = $input;
            }
        }

        return $filtered;
    }

    /**
     * Save compressed data to a directory when configured.
     */
    private function maybeSaveToDirectory(CompressionInput $input, Results\CompressionItemResult $itemResult, OutputConfig $outputConfig): void
    {
        if ($outputConfig->mode !== OutputModeEnum::Directory) {
            return;
        }

        // Only persist file-based inputs
        if (!$input instanceof FileInput) {
            return;
        }

        $baseName = basename($input->path);
        $dir = $outputConfig->directory ?? null;

        if ($dir === null) {
            return; // Defensive; should not happen thanks to OutputConfig validation
        }

        // Determine a destination directory with optional structure preservation
        $destDir = $dir;

        if ($outputConfig->keepStructure) {
            $relative = $this->relativeToCwd(dirname($input->path));

            if ($relative !== null && $relative !== '.') {
                $destDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $relative;
            }
        }

        // Ensure a directory exists
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            throw new CompressionException("Failed to create directory: {$destDir}");
        }

        // Persist each algorithm result
        foreach (array_keys($itemResult->compressed) as $algoValue) {
            $algo = AlgorithmEnum::from($algoValue);
            $ext = $algo->getExtension();

            $target = $destDir . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;

            $data = $itemResult->getData($algo);
            $written = file_put_contents($target, $data, LOCK_EX);

            if ($written === false) {
                throw new CompressionException("Failed to write compressed file: {$target}");
            }
        }
    }

    /**
     * Get $path relative to the current working directory, or null if it's outside.
     */
    private function relativeToCwd(string $path): ?string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            return null;
        }

        $cwd = rtrim((string) realpath($cwd), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $real = realpath($path);

        if ($real === false) {
            return null;
        }

        $real = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with($real, $cwd)) {
            $relative = substr($real, strlen($cwd));

            return rtrim($relative, DIRECTORY_SEPARATOR);
        }

        return null;
    }
}
