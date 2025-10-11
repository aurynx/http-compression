# API Reference

## CompressorFacade (Batch Facade)

Main entry point for batch compression.

### Static constructors
- make(): self — create a new batch compressor
- once(): SingleItemFacade — create a single-item facade

### Adding inputs
- add(CompressionInput $input, ?ItemConfig $config = null): self
- addMany(iterable $inputs): self
- addFile(string $path, ?ItemConfig $config = null, ?string $id = null): self
- addData(string $data, ?ItemConfig $config = null, ?string $id = null): self
- addGlob(string $pattern, ?ItemConfig $config = null): self
- addFrom(InputProviderInterface $provider, ?ItemConfig $config = null): self

### Configuration
- item(callable $callback): self — configure a specific item via ItemScopeBuilder
- withDefaultConfig(ItemConfig $config): self
- toDir(string $dir, bool $keepStructure = false): self
- inMemory(int $maxBytes = 5_000_000): self
- failFast(bool $enable = true): self
- skipExtensions(array $extensions): self
- skipAlreadyCompressed(): self

### Execution
- compress(): CompressionResult

---

## SingleItemFacade

Simplified facade for one-off compression.

### Methods
- file(string $path): self
- data(string $data): self
- withGzip(int $level = 6): self
- withBrotli(int $level = 11): self
- withZstd(int $level = 3): self
- withAlgorithm(AlgorithmEnum $algo, int $level): self
- compress(): CompressionItemResult
- saveTo(string $path): void — requires exactly one algorithm

---

## ItemConfig (Value Object)

Immutable configuration for a single compression item.

### Construction
- new ItemConfig(AlgorithmSet $algorithms, ?int $maxBytes = null)
- ItemConfig::create(): ItemConfigBuilder — fluent builder

### Builder methods (ItemConfigBuilder)
- withGzip(int $level = 6): self
- withBrotli(int $level = 11): self
- withZstd(int $level = 3): self
- withAlgorithm(AlgorithmEnum $algo, int $level): self
- withDefaults(): self — add all algorithms at default levels
- skip(AlgorithmEnum $algo): self — remove algo from the set
- restrictTo(AlgorithmEnum ...$algos): self — keep only specified algos
- limitBytes(int $bytes): self — set per-item maximum input size
- build(): ItemConfig

---

## AlgorithmSet (Value Object)

Immutable set of algorithms with validated levels.

### Factories
- from(array $pairs): self — array of [AlgorithmEnum, int]
- fromDefaults(): self — all algorithms at default levels
- gzip(int $level = 6): self
- brotli(int $level = 11): self
- zstd(int $level = 3): self

### Methods
- merge(self $other): self
- has(AlgorithmEnum $algo): bool
- getLevel(AlgorithmEnum $algo): int
- toArray(): array<array{AlgorithmEnum,int}>
- count(): int

---

## AlgorithmEnum

Cases: Gzip, Brotli, Zstd.

### Methods
- isAvailable(): bool
- static available(): array<AlgorithmEnum>
- getDefaultLevel(): int
- getMinLevel(): int
- getMaxLevel(): int
- validateLevel(int $level): void
- getRequiredExtension(): string
- getExtension(): string — file extension (gz/br/zst)
- getContentEncoding(): string — HTTP content-encoding value
- isCpuIntensive(): bool

---

## CompressionResult (Batch Results)

Container for multiple item results.

### Methods
- get(string $id): CompressionItemResult
- first(): CompressionItemResult
- allOk(): bool
- failures(): array<string, CompressionItemResult>
- successes(): array<string, CompressionItemResult>
- summary(): CompressionSummaryResult
- getIterator(): Traversable<string, CompressionItemResult>
- count(): int
- toArray(): array<string, CompressionItemResult>

---

## CompressionItemResult (Single Item Result)

Per-item data + metrics.

### Properties (readonly)
- id: string
- success: bool
- originalSize: int
- compressed: array<string, string|resource>
- compressedSizes: array<string, int>
- compressionTimes: array<string, float>
- errors: array<string, Throwable>

### Methods
- getFailureReason(): ?Throwable
- isOk(): bool
- has(AlgorithmEnum $algo): bool
- getData(AlgorithmEnum $algo): string
- getStream(AlgorithmEnum $algo): resource
- read(AlgorithmEnum $algo, callable $consumer): void
- getSize(AlgorithmEnum $algo): int
- getRatio(AlgorithmEnum $algo): float
- getCompressionTimeMs(AlgorithmEnum $algo): float
- getError(AlgorithmEnum $algo): ?Throwable
- getErrors(): array<string, Throwable>

---

## CompressionSummaryResult (Aggregated Statistics)

Aggregated stats over a batch.

### Methods
- getAverageRatio(AlgorithmEnum $algo): float
- getMedianRatio(AlgorithmEnum $algo): float
- getP95Ratio(AlgorithmEnum $algo): float
- getMedianTimeMs(AlgorithmEnum $algo): float
- getP95TimeMs(AlgorithmEnum $algo): float
- getTotalTimeMs(AlgorithmEnum $algo): float
- getTotalItems(): int
- getSuccessCount(): int
- getFailureCount(): int
