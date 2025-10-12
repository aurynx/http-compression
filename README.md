# Aurynx | HttpCompression

<p align="center">
  <img width="256" height="256" alt="Aurynx Mascot" src="https://github.com/user-attachments/assets/80a3ece6-5c50-4b01-9aee-7f086b55a0ef" />
</p>

<p align="center">
    <b>Modern PHP library for HTTP compression with native type safety</b>
</p>
<p align="center">gzip • brotli • zstd — simple, safe, and fast</p>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="#use-cases">Use Cases</a> •
  <a href="#api-reference">API</a> •
  <a href="./docs/AI_GUIDE.md">AI Guide</a>
</p>

---

## Why HttpCompression?

Modern web applications need efficient compression to reduce bandwidth and improve response times. HttpCompression makes it simple with a clean, modern API focused on:

- 🔷 **Native PHP 8.4+ types** — zero docblock types, full IDE autocomplete
- 🎯 **Single facade pattern** — one intuitive API for all scenarios
- 🚀 **Glob pattern support** — compress entire directories with wildcards
- 💾 **Memory-safe streaming** — handle large files without memory limits
- 🛡️ **Fail-fast validation** — catch errors at configuration time
- 🤖 **AI-friendly design** — perfect for code generation and assistants

## Installation

**Requirements:**
- PHP 8.4 or higher
- `ext-zlib` (required for gzip)
- `ext-brotli` (optional, for brotli compression)
- `ext-zstd` (optional, for zstd compression)

```bash
composer require aurynx/http-compression
```

## Quick Start

### Single File Compression

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

// Compress and save to file
CompressorFacade::once()
    ->file('public/index.html')
    ->withGzip(9)
    ->saveTo('public/index.html.gz');

// Compress in-memory data
$html = '<html><body>Hello World</body></html>';
$result = CompressorFacade::once()
    ->data($html)
    ->withBrotli(11)
    ->compress();

$compressed = $result->getData(AlgorithmEnum::Brotli);
```

### Batch Compression

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$result = CompressorFacade::make()
    ->addGlob('public/**/*.{html,css,js}')
    ->withDefaultConfig(
        ItemConfig::create()
            ->withGzip(9)
            ->withBrotli(11)
            ->build()
    )
    ->skipAlreadyCompressed()
    ->toDir('./dist')
    ->compress();

echo "Compressed {$result->count()} files\n";
echo "Success rate: " . ($result->allOk() ? '100%' : 'partial') . "\n";
```

## Features

### ✨ Native Type Safety

The public API uses native PHP 8.4+ types everywhere (parameters, return types, readonly DTOs). This makes the library:
- Easier for IDEs and AI agents to navigate (no docblock type guessing)
- Safer at runtime thanks to engine-level type checks
- More self-documenting due to explicit signatures

Example signature:
```php
public function compress(ItemConfig $config): CompressionResult
```

---

### 🎯 Fluent Facade API

Two facades for different scenarios:

#### `CompressorFacade::make()` — Batch compression
```php
CompressorFacade::make()
    ->addFile('index.html')
    ->addGlob('assets/*.css')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
    ->toDir('./output')
    ->compress();
```

#### `CompressorFacade::once()` — Quick single-item tasks
```php
CompressorFacade::once()
    ->file('logo.svg')
    ->withGzip(9)
    ->saveTo('logo.svg.gz');
```

---

### 🚀 Glob Pattern Support

Compress entire directories with powerful glob patterns:

```php
CompressorFacade::make()
    ->addGlob('public/**/*.html')           // All HTML files recursively
    ->addGlob('assets/*.{css,js}')          // CSS and JS in assets/
    ->addGlob('fonts/*.woff2')              // Specific extension
    ->skipAlreadyCompressed()               // Skip images, videos, etc.
    ->toDir('./dist', keepStructure: true)
    ->compress();
```

---

### 💾 Memory-Safe Streaming

Handle large files without loading into memory:

```php
use Aurynx\HttpCompression\ValueObjects\OutputConfig;

$result = CompressorFacade::make()
    ->addFile('large-file.json')  // 500MB file
    ->withDefaultConfig(ItemConfig::create()->withGzip(6)->build())
    ->inMemory(maxBytes: 100_000_000)  // 100MB limit
    ->compress();

// Stream compressed data
$result->first()->read(AlgorithmEnum::Gzip, function (string $chunk) {
    echo $chunk;  // Process in chunks
});
```

---

### 🛡️ Fail-Fast Validation

Errors are caught at configuration time, not during compression:

```php
// ❌ Throws immediately (invalid level)
AlgorithmSet::gzip(99);  // InvalidArgumentException: Level must be between 1 and 9

// ❌ Throws immediately (multiple algorithms for saveTo)
CompressorFacade::once()
    ->file('test.txt')
    ->withGzip(9)
    ->withBrotli(11)  // Multiple algorithms
    ->saveTo('test.gz');  // CompressionException: saveTo() requires exactly one algorithm
```

---

### 📈 Rich Result Objects

Detailed statistics and easy access:

```php
$result = CompressorFacade::make()
    ->addGlob('*.html')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->withBrotli(11)->build())
    ->inMemory()
    ->compress();

// Access results
foreach ($result as $id => $item) {
    if ($item->isOk()) {
        echo "Original: {$item->originalSize} bytes\n";
        echo "Gzip: {$item->compressedSizes['gzip']} bytes\n";
        echo "Brotli: {$item->compressedSizes['brotli']} bytes\n";
    }
}

// Aggregated statistics
$summary = $result->summary();
echo "Median compression ratio (gzip): " . $summary->getMedianRatio(AlgorithmEnum::Gzip) . "\n";
echo "P95 compression time (brotli): " . $summary->getP95TimeMs(AlgorithmEnum::Brotli) . " ms\n";
```

---

## Use Cases

### 1. Static Site Pre-Compression

Compress assets during build for nginx `gzip_static`:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

// Build script
$result = CompressorFacade::make()
    ->addGlob('dist/**/*.{html,css,js,svg,json}')
    ->withDefaultConfig(
        ItemConfig::create()
            ->withGzip(9)
            ->withBrotli(11)
            ->build()
    )
    ->skipAlreadyCompressed()
    ->toDir('./dist', keepStructure: true)
    ->compress();

if (!$result->allOk()) {
    foreach ($result->failures() as $id => $failure) {
        echo "Failed: {$id} - {$failure->getFailureReason()?->getMessage()}\n";
    }
    exit(1);
}

echo "✓ Compressed {$result->count()} files\n";
```

**Nginx configuration:**
```nginx
gzip_static on;
brotli_static on;
```

---

### 2. Dynamic HTTP Response Compression

Compress content on-the-fly with caching:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\AlgorithmEnum;

function compressResponse(string $content, string $acceptEncoding): string
{
    $cacheKey = 'compressed_' . md5($content) . '_' . $acceptEncoding;
    
    if ($cached = apcu_fetch($cacheKey)) {
        return $cached;
    }
    
    $algo = str_contains($acceptEncoding, 'br') ? AlgorithmEnum::Brotli : AlgorithmEnum::Gzip;
    
    $result = CompressorFacade::once()
        ->data($content)
        ->withAlgorithm($algo, $algo->getDefaultLevel())
        ->compress();
    
    $compressed = $result->getData($algo);
    apcu_store($cacheKey, $compressed, 3600);
    
    return $compressed;
}

// In your controller
$html = view('welcome')->render();
$acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

if (str_contains($acceptEncoding, 'br') || str_contains($acceptEncoding, 'gzip')) {
    $compressed = compressResponse($html, $acceptEncoding);
    header('Content-Encoding: ' . (str_contains($acceptEncoding, 'br') ? 'br' : 'gzip'));
    echo $compressed;
} else {
    echo $html;
}
```

---

### 3. API Response Compression

Compress JSON API responses:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\AlgorithmEnum;

function compressApiResponse(array $data, string $acceptEncoding): string
{
    $json = json_encode($data);
    
    if (!str_contains($acceptEncoding, 'gzip')) {
        return $json;
    }
    
    $result = CompressorFacade::once()
        ->data($json)
        ->withGzip(6)  // Lower level for speed
        ->compress();
    
    header('Content-Encoding: gzip');
    header('Vary: Accept-Encoding');
    
    return $result->getData(AlgorithmEnum::Gzip);
}

// Usage
$data = ['users' => User::all()];
echo compressApiResponse($data, $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
```

---

### 4. Log File Archival

Compress and archive old log files:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

// Daily cron job
$result = CompressorFacade::make()
    ->addGlob('storage/logs/*.log')
    ->withDefaultConfig(ItemConfig::create()->withZstd(19)->build())  // Maximum compression
    ->toDir('storage/logs/archive', keepStructure: false)
    ->compress();

// Delete originals
foreach ($result->successes() as $id => $item) {
    $originalPath = "storage/logs/{$id}";
    if (file_exists($originalPath)) {
        unlink($originalPath);
    }
}

echo "Archived {$result->count()} log files\n";
```

---

### 5. Asset Pipeline Integration

Integrate with your build tools:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

class AssetCompiler
{
    public function compile(): void
    {
        // Step 1: Bundle and minify (webpack, vite, etc.)
        system('npm run build');
        
        // Step 2: Compress for production
        $result = CompressorFacade::make()
            ->addGlob('public/build/**/*.{js,css}')
            ->addGlob('public/build/**/*.{svg,json}')
            ->withDefaultConfig(
                ItemConfig::create()
                    ->withGzip(9)
                    ->withBrotli(11)
                    ->build()
            )
            ->skipExtensions(['woff2', 'png', 'jpg'])
            ->toDir('public/build', keepStructure: true)
            ->failFast(true)
            ->compress();
        
        if (!$result->allOk()) {
            throw new \RuntimeException('Asset compression failed');
        }
        
        $summary = $result->summary();
        $avgRatio = $summary->getAverageRatio(AlgorithmEnum::Gzip);
        echo "✓ Compressed {$result->count()} assets (avg ratio: " . round($avgRatio * 100, 1) . "%)\n";
    }
}
```

---

## API Reference

### Facades

#### `CompressorFacade::make()` — Batch Compression

```php
use Aurynx\HttpCompression\CompressorFacade;

$result = CompressorFacade::make()
    // Add inputs
    ->add(CompressionInput $input, ?ItemConfig $config = null)
    ->addMany(iterable $inputs)
    ->addFile(string $path, ?ItemConfig $config = null, ?string $id = null)
    ->addData(string $data, ?ItemConfig $config = null, ?string $id = null)
    ->addGlob(string $pattern, ?ItemConfig $config = null)
    ->addFrom(InputProviderInterface $provider, ?ItemConfig $config = null)
    
    // Configuration
    ->withDefaultConfig(ItemConfig $config)
    
    // Output
    ->toDir(string $dir, bool $keepStructure = false)
    ->inMemory(int $maxBytes = 5_000_000)
    
    // Options
    ->failFast(bool $enable = true)
    ->skipExtensions(array $extensions)
    ->skipAlreadyCompressed()
    
    // Execute
    ->compress(): CompressionResult;
```

#### `CompressorFacade::once()` — Single Item

```php
use Aurynx\HttpCompression\CompressorFacade;

CompressorFacade::once()
    // Input
    ->file(string $path)
    ->data(string $data)
    
    // Algorithm (choose ONE)
    ->withGzip(int $level = 6)
    ->withBrotli(int $level = 11)
    ->withZstd(int $level = 3)
    
    // Execute
    ->compress(): CompressionItemResult
    ->saveTo(string $path): void;  // Requires exactly one algorithm
```

---

## Notes on Saving Files

- saveTo(path):
  - Atomic write (tmp + rename) to the target path
  - Existing target is replaced (OverwritePolicy=Replace)
  - The target directory must already exist (no auto-create)

- saveAllTo(directory, basename, options):
  - basename must be a plain filename (no '/' or '\\', not '.' or '..')
  - Options:
    - overwritePolicy: fail|replace|skip (default fail)
    - atomicAll: bool (default true) — all-or-nothing semantics
    - allowCreateDirs: bool (default true)
    - permissions: int|null — chmod after successful rename

---

### Configuration

#### `ItemConfig` — Compression Configuration

```php
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;

// Using builder
$config = ItemConfig::create()
    ->withGzip(9)
    ->withBrotli(11)
    ->withZstd(3)
    ->limitBytes(5_000_000)
    ->build();

// Direct instantiation
$config = new ItemConfig(
    algorithms: AlgorithmSet::gzip(9),
    maxBytes: 1_000_000
);

// Static factories
$config = ItemConfig::gzip(9);
$config = ItemConfig::brotli(11);
$config = ItemConfig::zstd(3);
```

#### `AlgorithmSet` — Algorithm Configuration

```php
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

// Static factories
$set = AlgorithmSet::gzip(9);
$set = AlgorithmSet::brotli(11);
$set = AlgorithmSet::zstd(3);
$set = AlgorithmSet::fromDefaults();  // All algorithms with default levels

// Manual construction from pairs
$set = AlgorithmSet::from([
    [AlgorithmEnum::Gzip, 9],
    [AlgorithmEnum::Brotli, 11],
]);
```

---

### Results

#### `CompressionResult` — Batch Results

```php
$result = CompressorFacade::make()->compress();

// Access
$result->get(string $id): CompressionItemResult
$result->first(): CompressionItemResult
$result->toArray(): array

// Filtering
$result->successes(): array
$result->failures(): array
$result->allOk(): bool

// Statistics
$result->summary(): CompressionSummaryResult
$result->count(): int

// Iteration
foreach ($result as $id => $item) {
    // Process each item
}
```

#### `CompressionItemResult` — Single Item Result

```php
$item = $result->first();

// Status
$item->isOk(): bool
$item->success: bool
$item->originalSize: int

// Data access
$item->getData(AlgorithmEnum $algo): string
$item->getStream(AlgorithmEnum $algo): resource
$item->read(AlgorithmEnum $algo, callable $consumer): void

// Metadata
$item->has(AlgorithmEnum $algo): bool
$item->compressedSizes: array<string, int>
$item->compressionTimes: array<string, float>
$item->errors: array<string, \Throwable>
$item->getFailureReason(): ?\Throwable
```

#### `CompressionSummaryResult` — Aggregated Statistics

```php
$summary = $result->summary();

// Compression ratios (compressed / original)
$summary->getAverageRatio(AlgorithmEnum $algo): float
$summary->getMedianRatio(AlgorithmEnum $algo): float  // p50
$summary->getP95Ratio(AlgorithmEnum $algo): float

// Timing (milliseconds)
$summary->getMedianTimeMs(AlgorithmEnum $algo): float  // p50
$summary->getP95TimeMs(AlgorithmEnum $algo): float
$summary->getTotalTimeMs(AlgorithmEnum $algo): float

// Counts
$summary->getTotalItems(): int
$summary->getSuccessCount(): int
$summary->getFailureCount(): int
```

---


## For AI Assistants

This library is designed to be AI-friendly with:

- ✅ **Native types** — no docblock parsing needed
- ✅ **Explicit naming** — `CompressionResult`, `AlgorithmEnum`, etc.
- ✅ **Fluent API** — easy to chain methods
- ✅ **Fail-fast** — errors are obvious and immediate
- ✅ **Immutable value objects** — no side effects

For a deeper, agent-focused walkthrough, see the AI Guide: [AI_GUIDE.md](./docs/AI_GUIDE.md). You can also use the machine-readable schema [`docs/ai-manifest.json`](docs/ai-manifest.json).

### Common Patterns

```php
// Quick compression
CompressorFacade::once()->file('test.txt')->withGzip(9)->saveTo('test.txt.gz');

// Batch with glob
CompressorFacade::make()
    ->addGlob('*.html')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
    ->toDir('./out')
    ->compress();

// Multiple algorithms
$config = ItemConfig::create()
    ->withGzip(9)
    ->withBrotli(11)
    ->withZstd(3)
    ->build();
```

### Avoid These Mistakes

❌ Multiple algorithms with `saveTo()`:
```php
// WRONG - saveTo() requires exactly one algorithm
CompressorFacade::once()->file('x')->withGzip()->withBrotli()->saveTo('x.gz');
```

✅ Use `compress()` instead:
```php
$result = CompressorFacade::once()->file('x')->withGzip()->withBrotli()->compress();
$result->getData(AlgorithmEnum::Gzip);
```

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run PHPStan
composer phpstan

# Run CS Fixer
composer cs-fix
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

## Credits

Created and maintained by [Anton Semenov](mailto:anton.a.semenov@proton.me).

---

<p align="center">Crafted by Aurynx 🔮</p>
