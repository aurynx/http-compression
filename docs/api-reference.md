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
- tryGzip(int $level = 6): self — optional gzip (skip if unavailable)
- tryBrotli(int $level = 11): self — optional brotli (skip if unavailable)
- tryZstd(int $level = 3): self — optional zstd (skip if unavailable)
- tryAlgorithm(AlgorithmEnum $algo, int $level): self — optional custom algo
- compress(): CompressionItemResult
- saveTo(string $path): void — requires exactly one algorithm; atomic write via tmp+rename; replaces existing target; the target directory must already exist (no auto-create)
- saveAllTo(string $directory, string $basename, array $options = []): void
  - basename must be a plain filename (no '/' or '\\', not '.' or '..')
  - options:
    - overwritePolicy: OverwritePolicyEnum|'fail'|'replace'|'skip' (default 'fail')
    - atomicAll: bool (default true) — all-or-nothing; on failure nothing is renamed
    - allowCreateDirs: bool (default true) — create directory if missing
    - permissions: int|null — chmod after successful rename
- saveCompressed(array $options = []): void — save next to source file (requires file() input)
- streamTo(string $path, array $options = []): void — like saveTo(), but uses streaming mode to avoid in-memory limits; options: overwritePolicy (default 'replace'), allowCreateDirs (default true), permissions
- streamAllTo(string $directory, string $basename, array $options = []): void — like saveAllTo(), but uses streaming mode; options: overwritePolicy (default 'fail'), atomicAll (default true), allowCreateDirs (default true), permissions
- sendToCallback(callable $consumer): void — stream a single algorithm into a consumer callback: function(string $chunk): void
- sendAllToCallbacks(array $consumers): void — stream multiple algorithms into per-algorithm callbacks; array<string, callable(string):void>
- trySaveTo(string $path): bool — returns false and sets getLastError() on failure
- trySaveAllTo(string $directory, string $basename, array $options = []): bool
- trySaveCompressed(array $options = []): bool
- tryStreamTo(string $path, array $options = []): bool — soft variant of streamTo()
- tryStreamAllTo(string $directory, string $basename, array $ options = []): bool — soft variant of streamAllTo()
- trySendToCallback(callable $consumer): bool — soft variant of sendToCallback()
- trySendAllToCallbacks(array $consumers): bool — soft variant of sendAllToCallbacks()
- getLastError(): ?CompressionException

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

## OverwritePolicyEnum

Controls how existing files are handled by saveAllTo/saveCompressed.

### Values
- Fail — throw if target exists
- Replace — overwrite target
- Skip — keep existing target and skip writing

### Helpers
- fromOption(null|string|OverwritePolicyEnum): OverwritePolicyEnum
- isReplace(): bool
- isSkip(): bool

---

## Support Helpers

### AcceptEncoding
- AcceptEncoding::negotiate(string $header, AlgorithmEnum ...$available): ?AlgorithmEnum
  - Parses Accept-Encoding with q-factors and returns the best acceptable algorithm from the provided list.
  - Returns null to indicate identity (no compression) or if nothing acceptable.

### Stream Wrappers
- ReadableStream — thin wrapper over a readable stream resource (enforces readable mode)
- WritableStream — thin wrapper over a writable stream resource (enforces writable mode)
  - CompressionEngine::compressItemToSinks() accepts either native stream resources or WritableStream instances.

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
- getRatio(AlgorithmEnum $algo): float — compressed/original
- getSavingRatio(AlgorithmEnum $algo): float — saving share in [0..1]
- getCompressionPercent(AlgorithmEnum $algo, int $precision = 0): string — e.g. "48%" or "48.2%"
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
