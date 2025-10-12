<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Enums\OverwritePolicyEnum;
use Aurynx\HttpCompression\Results\CompressionItemResult;
use Aurynx\HttpCompression\Support\FileWriter;
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;
use Aurynx\HttpCompression\ValueObjects\CompressionInput;
use Aurynx\HttpCompression\ValueObjects\DataInput;
use Aurynx\HttpCompression\ValueObjects\FileInput;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\OutputConfig;
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

    /** @var array<string, bool> Map of algo->value => optional? */
    private array $optionalAlgorithms = [];
    private ?CompressionException $lastError = null;

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
     * Use Gzip compression (accumulates; chainable)
     */
    public function withGzip(int $level = 6): self
    {
        $this->addAlgorithm(AlgorithmEnum::Gzip, $level);

        return $this;
    }

    /**
     * Use Brotli compression (accumulates; chainable)
     */
    public function withBrotli(int $level = 11): self
    {
        $this->addAlgorithm(AlgorithmEnum::Brotli, $level);

        return $this;
    }

    /**
     * Use Zstd compression (accumulates; chainable)
     */
    public function withZstd(int $level = 3): self
    {
        $this->addAlgorithm(AlgorithmEnum::Zstd, $level);

        return $this;
    }

    /**
     * Use custom algorithm with a specific level (accumulates; chainable)
     */
    public function withAlgorithm(AlgorithmEnum $algo, int $level): self
    {
        $this->addAlgorithm($algo, $level);

        return $this;
    }

    /**
     * Mark Gzip as optional (graceful fallback if unavailable)
     */
    public function tryGzip(int $level = 6): self
    {
        $this->addAlgorithm(AlgorithmEnum::Gzip, $level, required: false);

        return $this;
    }

    /**
     * Mark Brotli as optional (graceful fallback if unavailable)
     */
    public function tryBrotli(int $level = 11): self
    {
        $this->addAlgorithm(AlgorithmEnum::Brotli, $level, required: false);

        return $this;
    }

    /**
     * Mark Zstd as optional (graceful fallback if unavailable)
     */
    public function tryZstd(int $level = 3): self
    {
        $this->addAlgorithm(AlgorithmEnum::Zstd, $level, required: false);

        return $this;
    }

    /**
     * Mark custom algorithm as optional (graceful fallback if unavailable)
     */
    public function tryAlgorithm(AlgorithmEnum $algo, int $level): self
    {
        $this->addAlgorithm($algo, $level, required: false);

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
        $this->lastError = null;
        $this->validateInput();
        $this->validateConfig();

        assert($this->config !== null);

        // Validate exactly one algorithm
        $algorithms = $this->config->algorithms->toArray();

        if (count($algorithms) !== 1) {
            throw new CompressionException(
                'saveTo() requires exactly one algorithm, got ' . count($algorithms) . '. ' .
                'Use saveAllTo() for multiple algorithms or compress() to get data back.',
            );
        }

        // Compress (in-memory mode, fail-fast)
        $result = $this->executeCompression();

        // Extract the only algorithm enum from the first pair [AlgorithmEnum, level]
        [$algoEnum] = $algorithms[0];
        $data = $result->getData($algoEnum);

        // Delegate to FileWriter for atomic write (keep previous semantics: replace, no auto-create dirs)
        try {
            FileWriter::writeToPath(
                path: $path,
                data: $data,
                policy: OverwritePolicyEnum::Replace,
                permissions: null,
                allowCreateDirs: false,
            );
        } catch (CompressionException $e) {
            $this->lastError = $e;

            throw $e;
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
     * Compress with potentially multiple algorithms and save all outputs
     * to a directory using a common basename and per-algorithm extensions.
     *
     * Example: saveAllTo('dist', 'index.html') will write
     * - dist/index.html.gz
     * - dist/index.html.br
     * - dist/index.html.zst (if configured)
     *
     * Options:
     * - overwritePolicy: OverwritePolicyEnum|'fail'|'replace'|'skip' (default: 'fail')
     * - atomicAll: bool (default: true) — all-or-nothing; on failure nothing is renamed
     * - allowCreateDirs: bool (default: true) — create directory if missing
     * - permissions: int|null — chmod after successful rename
     *
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,atomicAll?:bool,allowCreateDirs?:bool,permissions?:int|null} $options
     * @throws CompressionException|Throwable
     */
    public function saveAllTo(string $directory, string $basename, array $options = []): void
    {
        $this->lastError = null;
        $this->validateInput();
        $this->validateConfig();

        assert($this->config !== null);

        $policy = OverwritePolicyEnum::fromOption($options['overwritePolicy'] ?? null);
        $atomicAll = $options['atomicAll'] ?? true;
        $allowCreateDirs = $options['allowCreateDirs'] ?? true;
        $permissions = $options['permissions'] ?? null;

        // Compress with non-fail-fast to collect per-algorithm errors for fallback
        $result = $this->compressInternal(false);

        // Prepare entries for successful (and required) algorithms
        $pairs = $this->config->algorithms->toArray();
        $entries = [];

        foreach ($pairs as [$algo, $_level]) {
            if (!$result->has($algo)) {
                $isOptional = $this->optionalAlgorithms[$algo->value] ?? false;
                $error = $result->getError($algo);

                if ($isOptional) {
                    continue; // skip optional failures
                }

                $reason = $error?->getMessage() ?? 'unknown error';
                $this->lastError = new CompressionException(
                    "Compression failed for required {$algo->value}: {$reason}",
                    0,
                    $error instanceof Throwable ? $error : null,
                    [ 'algorithm' => $algo->value ],
                );

                throw $this->lastError;
            }

            $entries[] = [
                'algo' => $algo,
                'data' => $result->getData($algo),
            ];
        }

        // Delegate filesystem work to Support\FileWriter
        try {
            FileWriter::writeAll(
                directory: $directory,
                basename: $basename,
                entries: $entries,
                policy: $policy,
                atomicAll: $atomicAll,
                permissions: $permissions,
                allowCreateDirs: $allowCreateDirs,
            );
        } catch (CompressionException $e) {
            $this->lastError = $e;

            throw $e;
        }
    }

    /**
     * Save compressed outputs next to the source file using its basename.
     * Example: if input is file('path/to/index.html'), this creates:
     * - path/to/index.html.gz
     * - path/to/index.html.br
     * - ... based on configured algorithms
     *
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,atomicAll?:bool,allowCreateDirs?:bool,permissions?:int|null} $options
     * @throws CompressionException|Throwable
     */
    public function saveCompressed(array $options = []): void
    {
        $this->validateInput();
        $this->validateConfig();

        if (!$this->input instanceof FileInput) {
            throw new CompressionException('saveCompressed() requires file() input. Use saveAllTo() for data().');
        }

        $dir = dirname($this->input->path);
        $basename = basename($this->input->path);

        $this->saveAllTo($dir, $basename, $options);
    }

    /**
     * Stream-compress a SINGLE algorithm and write directly to a file via tmp+rename.
     * Disables in-memory size limit checks by using OutputConfig::stream().
     *
     * Options:
     * - overwritePolicy: OverwritePolicyEnum|'fail'|'replace'|'skip' (default: 'replace')
     * - allowCreateDirs: bool (default: true)
     * - permissions: int|null
     *
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,allowCreateDirs?:bool,permissions?:int|null} $options
     * @throws CompressionException|Throwable
     */
    public function streamTo(string $path, array $options = []): void
    {
        $this->lastError = null;
        $this->validateInput();
        $this->validateConfig();
        assert($this->config !== null);

        $algorithms = $this->config->algorithms->toArray();

        if (count($algorithms) !== 1) {
            throw new CompressionException(
                'streamTo() requires exactly one algorithm, got ' . count($algorithms) . '. ' .
                'Use streamAllTo() for multiple algorithms.',
            );
        }
        $policy = OverwritePolicyEnum::fromOption($options['overwritePolicy'] ?? OverwritePolicyEnum::Replace);
        $allowCreateDirs = $options['allowCreateDirs'] ?? true;
        $permissions = $options['permissions'] ?? null;

        FileWriter::writeToPathWithSink(
            path: $path,
            policy: $policy,
            permissions: $permissions,
            allowCreateDirs: $allowCreateDirs,
            /** @param resource $sink */
            producer: function ($sink): void {
                assert($this->input !== null);
                assert($this->config !== null);

                /** @var resource $sink */
                assert(is_resource($sink));

                $engine = new CompressionEngine(
                    outputConfig: OutputConfig::stream(),
                    failFast: true,
                );

                $pairs = $this->config->algorithms->toArray();
                [$algoEnum, $level] = $pairs[0];
                $engine->compressItemToSinks($this->input, new ItemConfig(AlgorithmSet::from([[$algoEnum, $level]])), [
                    $algoEnum->value => $sink,
                ]);
            },
        );
    }

    /**
     * Stream-compress MULTIPLE algorithms and write all outputs to a directory via tmp+rename.
     * Uses OutputConfig::stream() to avoid in-memory size checks.
     *
     * Options:
     * - overwritePolicy: OverwritePolicyEnum|'fail'|'replace'|'skip' (default: 'fail')
     * - atomicAll: bool (default: true)
     * - allowCreateDirs: bool (default: true)
     * - permissions: int|null
     *
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,atomicAll?:bool,allowCreateDirs?:bool,permissions?:int|null} $options
     * @throws CompressionException|Throwable
     */
    public function streamAllTo(string $directory, string $basename, array $options = []): void
    {
        $this->lastError = null;
        $this->validateInput();
        $this->validateConfig();
        assert($this->config !== null);

        $policy = OverwritePolicyEnum::fromOption($options['overwritePolicy'] ?? null);
        $atomicAll = $options['atomicAll'] ?? true;
        $allowCreateDirs = $options['allowCreateDirs'] ?? true;
        $permissions = $options['permissions'] ?? null;

        $pairs = $this->config->algorithms->toArray();
        $targets = [];

        foreach ($pairs as [$algo, $_level]) {
            $targets[] = ['algo' => $algo, 'target' => $basename . '.' . $algo->getExtension()];
        }

        FileWriter::writeAllWithSinks(
            directory: $directory,
            basename: $basename,
            targets: $targets,
            policy: $policy,
            atomicAll: $atomicAll,
            permissions: $permissions,
            allowCreateDirs: $allowCreateDirs,
            /** @param array<string, resource> $sinks */
            producer: function (array $sinks): void {
                assert($this->input !== null);
                assert($this->config !== null);

                /** @var array<string, resource> $typedSinks */
                $typedSinks = $sinks;

                $engine = new CompressionEngine(
                    outputConfig: OutputConfig::stream(),
                    failFast: false,
                );

                $engine->compressItemToSinks($this->input, $this->config, $typedSinks);
            },
        );
    }

    /**
     * Stream a SINGLE algorithm into a callback consumer.
     * Callback receives compressed chunks: function(string $chunk): void
     *
     * @param callable(string):void $consumer
     * @throws CompressionException|Throwable
     */
    public function sendToCallback(callable $consumer): void
    {
        $this->lastError = null;
        $this->validateInput();
        $this->validateConfig();
        assert($this->config !== null);

        $pairs = $this->config->algorithms->toArray();

        if (count($pairs) !== 1) {
            throw new CompressionException(
                'sendToCallback() requires exactly one algorithm. Use sendAllToCallbacks() for multiple algorithms.',
            );
        }

        [$algo, $level] = $pairs[0];

        $engine = new CompressionEngine(
            outputConfig: OutputConfig::stream(),
            failFast: true,
        );

        $input = $this->input;
        assert($input instanceof CompressionInput);

        $engine->compressItemToCallbacks(
            input: $input,
            config: new ItemConfig(AlgorithmSet::from([[ $algo, $level ]])),
            callbacks: [ $algo->value => $consumer ],
        );
    }

    /**
     * Stream MULTIPLE algorithms into per-algorithm callbacks.
     * Keys of $consumers must be algorithm values (e.g., 'gzip', 'br', 'zst').
     * Optional algorithms without a consumer are skipped; required ones must be provided and succeed.
     *
     * @param array<string, callable(string):void> $consumers
     * @throws CompressionException|Throwable
     */
    public function sendAllToCallbacks(array $consumers): void
    {
        $this->lastError = null;
        $this->validateInput();
        $this->validateConfig();
        assert($this->config !== null);

        $pairs = $this->config->algorithms->toArray();
        $filtered = [];
        $callbacks = [];

        foreach ($pairs as [$algo, $level]) {
            $key = $algo->value;
            $has = array_key_exists($key, $consumers);
            $isOptional = $this->optionalAlgorithms[$key] ?? false;

            if ($has) {
                $filtered[] = [ $algo, $level ];
                $callbacks[$key] = $consumers[$key];
            } elseif (!$isOptional) {
                throw new CompressionException("Missing consumer callback for required algorithm: {$key}");
            }
        }

        if (empty($filtered)) {
            // Nothing to do
            return;
        }

        $engine = new CompressionEngine(
            outputConfig: OutputConfig::stream(),
            failFast: false,
        );

        $input = $this->input;
        assert($input instanceof CompressionInput);

        $dto = $engine->compressItemToCallbacks(
            input: $input,
            config: new ItemConfig(AlgorithmSet::from($filtered)),
            callbacks: $callbacks,
        );

        // Validate required algorithms succeeded
        foreach ($filtered as [$algo, $_level]) {
            $key = $algo->value;
            $isOptional = $this->optionalAlgorithms[$key] ?? false;
            $error = $dto->errors[$key] ?? null;
            $size = $dto->compressedSizes[$key] ?? 0;

            if ($error !== null || $size <= 0) {
                if ($isOptional) {
                    continue; // tolerate optional failures
                }
                $reason = $error instanceof Throwable ? $error->getMessage() : 'unknown error';
                $this->lastError = new CompressionException(
                    "Compression failed for required {$key}: {$reason}",
                    0,
                    $error instanceof Throwable ? $error : null,
                    [ 'algorithm' => $key ],
                );

                throw $this->lastError;
            }
        }
    }

    /**
     * Try variants: return false instead of throwing and capture last error
     */
    public function trySaveTo(string $path): bool
    {
        try {
            $this->saveTo($path);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
    }

    /**
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,atomicAll?:bool,allowCreateDirs?:bool,permissions?:int|null} $options
     */
    public function trySaveAllTo(string $directory, string $basename, array $options = []): bool
    {
        try {
            $this->saveAllTo($directory, $basename, $options);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
    }

    /**
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,atomicAll?:bool,allowCreateDirs?:bool,permissions?:int|null} $options
     */
    public function trySaveCompressed(array $options = []): bool
    {
        try {
            $this->saveCompressed($options);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
    }

    /**
     * Try variants for streaming methods: return false instead of throwing and capture last error
     *
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,allowCreateDirs?:bool,permissions?:int|null} $options
     */
    public function tryStreamTo(string $path, array $options = []): bool
    {
        try {
            $this->streamTo($path, $options);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
    }

    /**
     * @param array{overwritePolicy?:OverwritePolicyEnum|string,atomicAll?:bool,allowCreateDirs?:bool,permissions?:int|null} $options
     */
    public function tryStreamAllTo(string $directory, string $basename, array $options = []): bool
    {
        try {
            $this->streamAllTo($directory, $basename, $options);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
    }

    /**
     * Try variants for callback streaming: swallow errors and set lastError.
     */
    public function trySendToCallback(callable $consumer): bool
    {
        try {
            $this->sendToCallback($consumer);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
    }

    /**
     * @param array<string, callable(string):void> $consumers
     */
    public function trySendAllToCallbacks(array $consumers): bool
    {
        try {
            $this->sendAllToCallbacks($consumers);

            return true;
        } catch (CompressionException $e) {
            $this->lastError = $e;

            return false;
        } catch (Throwable $e) {
            $this->lastError = new CompressionException($e->getMessage(), 0, $e);

            return false;
        }
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
     * Execute compression with configurable failFast (in-memory mode)
     *
     * @throws Throwable
     */
    private function compressInternal(bool $failFast): CompressionItemResult
    {
        assert($this->input !== null);
        assert($this->config !== null);

        return $this->compressWithOutput(OutputConfig::inMemory(), $failFast);
    }

    /**
     * Execute compression with explicit OutputConfig
     *
     * @throws Throwable
     */
    private function compressWithOutput(OutputConfig $outputConfig, bool $failFast): CompressionItemResult
    {
        assert($this->input !== null);
        assert($this->config !== null);

        $engine = new CompressionEngine(
            outputConfig: $outputConfig,
            failFast: $failFast,
        );

        $dto = $engine->compressItem($this->input, $this->config);

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

    /**
     * Execute compression (fail-fast)
     *
     * @throws Throwable
     */
    private function executeCompression(): CompressionItemResult
    {
        return $this->compressInternal(true);
    }

    /**
     * Add or update algorithm in the current config (accumulative)
     */
    private function addAlgorithm(AlgorithmEnum $algo, int $level, bool $required = true): void
    {
        $algo->validateLevel($level);

        $newSet = AlgorithmSet::from([[ $algo, $level ]]);

        if ($this->config === null) {
            $this->config = new ItemConfig($newSet);
        } else {
            $this->config = new ItemConfig(
                algorithms: $this->config->algorithms->merge($newSet),
                maxBytes: $this->config->maxBytes,
            );
        }

        // Track optionality
        $this->optionalAlgorithms[$algo->value] = $this->optionalAlgorithms[$algo->value] ?? false;

        if ($required === false) {
            $this->optionalAlgorithms[$algo->value] = true;
        }
    }

    /**
     * Expose last error for try* methods
     */

    public function getLastError(): ?CompressionException
    {
        return $this->lastError;
    }
}
