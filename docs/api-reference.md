# API Reference

## CompressionBuilder

The main entry point for compression operations.

### Constructor

```php
new CompressionBuilder(?int $maxBytes = null)
```

**Parameters:**
- `$maxBytes` — Optional global size limit for all items (in bytes)

### Adding Content

#### add()

```php
add(string $content, AlgorithmEnum|iterable|null $algorithms = null, ?string $customIdentifier = null): self
```

Add raw content for compression.

**Parameters:**
- `$content` — The string content to compress
- `$algorithms` — Algorithm(s) to use: single enum, or array `['gzip' => 9, 'br' => 11]`
- `$customIdentifier` — Optional custom identifier (auto-generated if null)

**Returns:** `self` for method chaining

**Throws:** `CompressionException` if identifier already exists

---

#### addFile()

```php
addFile(string $filePath, AlgorithmEnum|iterable|null $algorithms = null, ?string $customIdentifier = null): self
```

Add a file for compression.

**Parameters:**
- `$filePath` — Path to the file
- `$algorithms` — Algorithm(s) to use
- `$customIdentifier` — Optional custom identifier (defaults to file path)

**Returns:** `self` for method chaining

**Throws:** 
- `CompressionException` with `FILE_NOT_FOUND` if file doesn't exist
- `CompressionException` with `FILE_NOT_READABLE` if file is not readable

---

#### addMany()

```php
addMany(iterable $payloads, AlgorithmEnum|iterable|null $defaultAlgorithms = null): self
```

Add multiple content items at once.

**Parameters:**
- `$payloads` — Array of strings or structured arrays with `['content' => '...', 'algorithms' => [...], 'identifier' => '...']`
- `$defaultAlgorithms` — Default algorithms for items that don't specify their own

**Returns:** `self` for method chaining

**Throws:** `CompressionException` with `INVALID_PAYLOAD` if payload structure is invalid

---

#### addManyFiles()

```php
addManyFiles(iterable $payloads, AlgorithmEnum|iterable|null $defaultAlgorithms = null): self
```

Add multiple files at once.

**Parameters:**
- `$payloads` — Array of file paths or structured arrays with `['path' => '...', 'algorithms' => [...], 'identifier' => '...']`
- `$defaultAlgorithms` — Default algorithms for items that don't specify their own

**Returns:** `self` for method chaining

### Configuration

#### withDefaultAlgorithms()

```php
withDefaultAlgorithms(AlgorithmEnum|iterable|null $algorithms): self
```

Set default algorithms for subsequently added items.

**Parameters:**
- `$algorithms` — Single algorithm enum or array of algorithms with levels

**Returns:** `self` for method chaining

---

#### forItem()

```php
forItem(string $identifier): ItemConfigurator
```

Get a configurator for a specific item (chainable).

**Parameters:**
- `$identifier` — The item identifier

**Returns:** `ItemConfigurator` instance

**Throws:** `CompressionException` with `ITEM_NOT_FOUND` if identifier doesn't exist

---

#### forLast()

```php
forLast(): ItemConfigurator
```

Get a configurator for the last added item (chainable).

**Returns:** `ItemConfigurator` instance

**Throws:** `CompressionException` with `NO_ITEMS` if no items have been added

---

#### failFast()

```php
failFast(): self
```

Enable fail-fast mode (throw exception on first error). **Default behavior.**

**Returns:** `self` for method chaining

---

#### graceful()

```php
graceful(): self
```

Enable graceful mode (continue on errors, collect them in results).

**Returns:** `self` for method chaining

### Execution

#### compress()

```php
compress(): array<string, CompressionResultDto>
```

Execute compression and return results indexed by identifier.

**Returns:** Array of `CompressionResultDto` objects indexed by item identifier

**Throws:** `CompressionException` in fail-fast mode when error occurs

### Other Methods

#### getLastIdentifier()

```php
getLastIdentifier(): ?string
```

Get the identifier of the last added item.

**Returns:** Identifier string or null if no items added

---

#### count()

```php
count(): int
```

Get the number of items added.

**Returns:** Number of items

---

#### getIterator()

```php
getIterator(): Traversable
```

Get iterator over items (for `IteratorAggregate`).

**Returns:** `ArrayIterator` of items

---

## AlgorithmEnum

Enum representing compression algorithms.

### Cases

- `AlgorithmEnum::Gzip` — gzip compression (requires `ext-zlib`)
- `AlgorithmEnum::Brotli` — Brotli compression (requires `ext-brotli`)
- `AlgorithmEnum::Zstd` — Zstandard compression (requires `ext-zstd`)

### Methods

#### isAvailable()

```php
isAvailable(): bool
```

Check if the algorithm's PHP extension is available.

**Returns:** `true` if extension is loaded, `false` otherwise

---

#### available()

```php
static available(): array<AlgorithmEnum>
```

Get all available algorithms on the current system (static method).

**Returns:** Array of `AlgorithmEnum` instances for algorithms with loaded extensions

**Example:**
```php
$available = AlgorithmEnum::available();
foreach ($available as $algo) {
    echo "{$algo->value} is available\n";
}
```

---

#### getDefaultLevel()

```php
getDefaultLevel(): int
```

Get the default compression level for this algorithm.

**Returns:** Default level (gzip: 6, brotli: 4, zstd: 3)

---

#### getMinLevel()

```php
getMinLevel(): int
```

Get the minimum compression level.

**Returns:** Minimum level (gzip: 1, brotli: 0, zstd: 1)

---

#### getMaxLevel()

```php
getMaxLevel(): int
```

Get the maximum compression level.

**Returns:** Maximum level (gzip: 9, brotli: 11, zstd: 22)

---

#### validateLevel()

```php
validateLevel(int $level): void
```

Validate that a compression level is within the valid range for this algorithm.

**Parameters:**
- `$level` — The compression level to validate

**Throws:** `CompressionException` with `LEVEL_OUT_OF_RANGE` if level is invalid

**Example:**
```php
$algo = AlgorithmEnum::Gzip;
$algo->validateLevel(6); // OK
$algo->validateLevel(15); // Throws CompressionException
```

---

#### getRequiredExtension()

```php
getRequiredExtension(): string
```

Get the name of the PHP extension required for this algorithm.

**Returns:** Extension name (gzip: 'zlib', brotli: 'brotli', zstd: 'zstd')

**Example:**
```php
$algo = AlgorithmEnum::Brotli;
echo $algo->getRequiredExtension(); // 'brotli'

// Check if extension is loaded
if (!extension_loaded($algo->getRequiredExtension())) {
    echo "Missing extension: {$algo->getRequiredExtension()}\n";
}
```

---

## CompressionResultDto

Result object for a single compression operation.

### Status Methods

#### isOk()

```php
isOk(): bool
```

Check if all algorithms succeeded.

**Returns:** `true` if all algorithms succeeded, `false` otherwise

---

#### isPartial()

```php
isPartial(): bool
```

Check if some algorithms succeeded and some failed.

**Returns:** `true` if partial success, `false` otherwise

---

#### isError()

```php
isError(): bool
```

Check if complete failure (no algorithms succeeded).

**Returns:** `true` if complete failure, `false` otherwise

---

#### isSuccess()

```php
isSuccess(): bool
```

Alias for `isOk()`.

### Data Access Methods

#### getIdentifier()

```php
getIdentifier(): string
```

Get the item identifier.

**Returns:** Item identifier string

---

#### getCompressed()

```php
getCompressed(): array<string, string>
```

Get all successful compressions.

**Returns:** Array mapping algorithm name (string) to compressed data (string)

---

#### getCompressedFor()

```php
getCompressedFor(AlgorithmEnum $algorithm): ?string
```

Get compressed data for a specific algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Compressed data string or `null` if algorithm wasn't used or failed

---

#### getAllCompressed()

```php
getAllCompressed(): array<string, string>
```

Alias for `getCompressed()`.

---

#### hasAlgorithm()

```php
hasAlgorithm(AlgorithmEnum $algorithm): bool
```

Check if an algorithm was successfully used.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** `true` if algorithm succeeded, `false` otherwise

---

#### getAlgorithms()

```php
getAlgorithms(): array<string>
```

Get list of algorithms that succeeded.

**Returns:** Array of algorithm names

### Error Methods

#### getErrors()

```php
getErrors(): array
```

Get all error details.

**Returns:** Array of error information

---

#### getError()

```php
getError(): ?CompressionException
```

Get complete failure exception (when `isError()` is true).

**Returns:** `CompressionException` or `null`

---

#### getErrorMessage()

```php
getErrorMessage(): ?string
```

Get error message from complete failure exception (convenience method).

**Returns:** Error message string or `null` if no error

**Example:**
```php
if ($result->isError()) {
    echo "Error: " . $result->getErrorMessage();
}
```

---

#### hasPartialFailures()

```php
hasPartialFailures(): bool
```

Check if this result has any per-algorithm failures (useful for partial success scenarios).

**Returns:** `true` if at least one algorithm failed while others succeeded, `false` otherwise

**Example:**
```php
if ($result->hasPartialFailures()) {
    echo "Some algorithms failed:\n";
    foreach ($result->getAlgorithmErrors() as $algo => $error) {
        echo "  - {$algo}: {$error['message']}\n";
    }
}
```

---

#### getAlgorithmErrors()

```php
getAlgorithmErrors(): array<string, array{code:int, message:string}>
```

Get per-algorithm errors (for partial failures).

**Returns:** Array mapping algorithm name to error details

---

#### hasAlgorithmError()

```php
hasAlgorithmError(AlgorithmEnum $algorithm): bool
```

Check if a specific algorithm failed.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** `true` if this algorithm failed, `false` otherwise

**Example:**
```php
if ($result->hasAlgorithmError(AlgorithmEnum::Brotli)) {
    $error = $result->getAlgorithmError(AlgorithmEnum::Brotli);
    echo "Brotli failed: {$error['message']}\n";
}
```

---

#### getAlgorithmError()

```php
getAlgorithmError(AlgorithmEnum $algorithm): ?array{code:int, message:string}
```

Get error for a specific algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Error array with 'code' and 'message' keys, or `null` if no error

---

### Compression Metrics Methods

#### getOriginalSize()

```php
getOriginalSize(): ?int
```

Get original uncompressed size in bytes.

**Returns:** Original size in bytes, or `null` if not available

**Example:**
```php
$result = $builder->compress()[0];
echo "Original: " . $result->getOriginalSize() . " bytes\n";
```

---

#### getCompressedSize()

```php
getCompressedSize(AlgorithmEnum $algorithm): ?int
```

Get compressed size for a specific algorithm in bytes.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Compressed size in bytes, or `null` if algorithm wasn't used

**Example:**
```php
$size = $result->getCompressedSize(AlgorithmEnum::Gzip);
echo "Compressed (gzip): $size bytes\n";
```

---

#### getCompressionRatio()

```php
getCompressionRatio(AlgorithmEnum $algorithm): ?float
```

Get compression ratio for a specific algorithm (0.0 to 1.0).

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Ratio of compressed/original size (e.g., 0.42 means 42% of original), or `null` if not available

**Example:**
```php
$ratio = $result->getCompressionRatio(AlgorithmEnum::Gzip);
echo "Compression ratio: " . ($ratio * 100) . "%\n";
```

---

#### getSavedBytes()

```php
getSavedBytes(AlgorithmEnum $algorithm): ?int
```

Get bytes saved by compression for a specific algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Number of bytes saved (negative if compressed is larger), or `null` if not available

**Example:**
```php
$saved = $result->getSavedBytes(AlgorithmEnum::Gzip);
echo "Saved: $saved bytes\n";
```

---

#### getCompressionPercentage()

```php
getCompressionPercentage(AlgorithmEnum $algorithm): ?float
```

Get compression percentage for a specific algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Percentage reduction (e.g., 58.0 means 58% size reduction), or `null` if not available

**Example:**
```php
$percentage = $result->getCompressionPercentage(AlgorithmEnum::Gzip);
echo "Reduced by: $percentage%\n";
```

---

#### isEffective()

```php
isEffective(AlgorithmEnum $algorithm): ?bool
```

Check if compression was effective for a specific algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** `true` if compressed size is smaller than original, `false` if not effective, `null` if no metadata available

**Example:**
```php
if ($result->isEffective(AlgorithmEnum::Gzip)) {
    echo "Compression was effective!\n";
}
```

---

## CompressionStatsDto

Aggregated compression statistics for batch operations.

### Static Factory Method

#### fromResults()

```php
static fromResults(array<CompressionResultDto> $results): CompressionStatsDto
```

Create statistics from an array of CompressionResultDto objects.

**Parameters:**
- `$results` — Array of `CompressionResultDto` objects from batch compression

**Returns:** `CompressionStatsDto` instance with aggregated metrics

**Example:**
```php
$results = $builder->compress();
$stats = CompressionStatsDto::fromResults($results);

echo "Compressed {$stats->getTotalItems()} files\n";
echo "Saved {$stats->getTotalSavedBytes(AlgorithmEnum::Gzip)} bytes\n";
```

---

### Item Count Methods

#### getTotalItems()

```php
getTotalItems(): int
```

Get total number of items processed.

**Returns:** Total item count

---

#### getSuccessfulItems()

```php
getSuccessfulItems(): int
```

Get number of successfully compressed items.

**Returns:** Successful item count

---

#### getFailedItems()

```php
getFailedItems(): int
```

Get number of failed items.

**Returns:** Failed item count

---

#### getSuccessRate()

```php
getSuccessRate(): float
```

Get success rate (0.0 to 1.0).

**Returns:** Success rate as a float (e.g., 0.95 = 95% success)

**Example:**
```php
$rate = $stats->getSuccessRate();
echo sprintf("Success rate: %.1f%%", $rate * 100);
```

---

### Aggregated Size Methods

#### getTotalOriginalBytes()

```php
getTotalOriginalBytes(): int
```

Get total original size across all items in bytes.

**Returns:** Total original size in bytes

---

#### getTotalCompressedBytes()

```php
getTotalCompressedBytes(AlgorithmEnum $algorithm): ?int
```

Get total compressed size for a specific algorithm in bytes.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Total compressed size in bytes, or `null` if algorithm wasn't used

**Example:**
```php
$totalGzip = $stats->getTotalCompressedBytes(AlgorithmEnum::Gzip);
echo "Total compressed (gzip): $totalGzip bytes\n";
```

---

#### getTotalSavedBytes()

```php
getTotalSavedBytes(AlgorithmEnum $algorithm): ?int
```

Get total bytes saved for a specific algorithm across all items.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Total bytes saved, or `null` if algorithm wasn't used

**Example:**
```php
$saved = $stats->getTotalSavedBytes(AlgorithmEnum::Gzip);
echo "Total saved: $saved bytes\n";
```

---

### Average Metrics Methods

#### getAverageRatio()

```php
getAverageRatio(AlgorithmEnum $algorithm): ?float
```

Get average compression ratio for a specific algorithm (0.0 to 1.0).

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Average ratio of compressed/original size, or `null` if algorithm wasn't used

---

#### getAveragePercentage()

```php
getAveragePercentage(AlgorithmEnum $algorithm): ?float
```

Get average compression percentage for a specific algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** Average percentage reduction (e.g., 58.0 means 58% average reduction), or `null` if algorithm wasn't used

**Example:**
```php
$avgPercentage = $stats->getAveragePercentage(AlgorithmEnum::Gzip);
echo "Average compression: $avgPercentage%\n";
```

---

### Algorithm Info Methods

#### hasAlgorithm()

```php
hasAlgorithm(AlgorithmEnum $algorithm): bool
```

Check if any items used the specified algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** `true` if at least one item used this algorithm

---

#### getAlgorithms()

```php
getAlgorithms(): array<string>
```

Get list of algorithms used across all items.

**Returns:** Array of algorithm names

---

### Utility Methods

#### summary()

```php
summary(): string
```

Format statistics as a human-readable string.

**Returns:** Multi-line formatted summary string

**Example:**
```php
echo $stats->summary();
// Output:
// Compression Statistics:
//   Total items: 50
//   Successful: 50
//   Original size: 125.45 KB
//   gzip: 42.31 KB (saved 83.14 KB, 66.3% reduction)
```

---

## ItemConfigurator

Fluent configurator for individual items (obtained via `forItem()` or `forLast()`).

### withAlgorithms()

```php
withAlgorithms(AlgorithmEnum|iterable $algorithms): CompressionBuilder
```

Set algorithms for this item.

**Parameters:**
- `$algorithms` — Single algorithm enum or array of algorithms with levels

**Returns:** `CompressionBuilder` instance for continued chaining

**Example:**
```php
$builder = new CompressionBuilder();
$builder->add('Content', AlgorithmEnum::Gzip, 'my-id');
$builder->forItem('my-id')
    ->withAlgorithms([
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11
    ]);
```

---

## CompressorInterface

Low-level interface for direct algorithm access.

### compress()

```php
compress(string $content, ?int $level = null): string
```

Compress content.

**Parameters:**
- `$content` — Content to compress
- `$level` — Compression level (null = default)

**Returns:** Compressed content

**Throws:** `CompressionException` on failure

---

### decompress()

```php
decompress(string $content): string
```

Decompress content.

**Parameters:**
- `$content` — Compressed content

**Returns:** Decompressed content

**Throws:** `CompressionException` on failure

---

### getAlgorithm()

```php
getAlgorithm(): AlgorithmEnum
```

Get the algorithm type.

**Returns:** `AlgorithmEnum` instance

---

## CompressorFactory

Factory for creating compressor instances.

### create()

```php
static create(AlgorithmEnum $algorithm): CompressorInterface
```

Create a compressor instance for the specified algorithm.

**Parameters:**
- `$algorithm` — The algorithm enum

**Returns:** `CompressorInterface` instance

**Example:**
```php
$compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
$compressed = $compressor->compress('data', 6);
```

---

## ErrorCodeEnum

Enum with machine-readable error codes.

### Cases

- `UNKNOWN_ALGORITHM` (1001) — Unknown algorithm specified
- `ALGORITHM_UNAVAILABLE` (1002) — Required PHP extension not loaded
- `LEVEL_OUT_OF_RANGE` (1003) — Compression level outside valid range
- `FILE_NOT_FOUND` (1004) — File does not exist
- `FILE_NOT_READABLE` (1005) — File is not readable (permissions issue)
- `PAYLOAD_TOO_LARGE` (1006) — Payload exceeds `maxBytes` limit
- `COMPRESSION_FAILED` (1007) — Compression operation failed
- `DECOMPRESSION_FAILED` (1008) — Decompression operation failed
- `DUPLICATE_IDENTIFIER` (1009) — Item with identifier already exists
- `ITEM_NOT_FOUND` (1010) — Item with identifier not found
- `INVALID_ALGORITHM_SPEC` (1011) — Invalid algorithm specification format
- `EMPTY_ALGORITHMS` (1012) — Empty algorithms array provided
- `INVALID_PAYLOAD` (1013) — Invalid payload structure in `addMany()` / `addManyFiles()`
- `NO_ITEMS` (1014) — No items added to builder
- `INVALID_LEVEL_TYPE` (1015) — Compression level is not an integer

---

## CompressionException

Exception class for all compression-related errors.

### Constructor

```php
new CompressionException(string $message, int $code)
```

**Parameters:**
- `$message` — Error message
- `$code` — Error code from `ErrorCodeEnum` enum

### Methods

Extends standard `Exception` class with all its methods:
- `getMessage(): string`
- `getCode(): int`
- `getFile(): string`
- `getLine(): int`
- `getTrace(): array`
- `getTraceAsString(): string`
- `getPrevious(): ?Throwable`
