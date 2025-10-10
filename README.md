# Aurynx | HttpCompression

<p align="center">
  <img width="256" height="256" alt="Aurynx Mascot" src="https://github.com/user-attachments/assets/80a3ece6-5c50-4b01-9aee-7f086b55a0ef" />
</p>

<p align="center">
    <b>Framework-agnostic PHP library for efficient HTTP compression</b>
</p>
<p align="center">gzip • brotli • zstd — simple, safe, and fast</p>


<p align="center">
  <a href="https://packagist.org/packages/aurynx/http-compression"><img src="https://img.shields.io/packagist/v/aurynx/http-compression.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/aurynx/http-compression"><img src="https://img.shields.io/packagist/dt/aurynx/http-compression.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/aurynx/http-compression"><img src="https://img.shields.io/packagist/php-v/aurynx/http-compression.svg?style=flat-square" alt="PHP Version"></a>
  <a href="https://github.com/aurynx/http-compression/blob/main/LICENSE"><img src="https://img.shields.io/packagist/l/aurynx/http-compression.svg?style=flat-square" alt="License"></a>
</p>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="#usage-examples">Usage</a> •
  <a href="#api-reference">API</a> •
  <a href="#contributing">Contributing</a>
</p>

---

## Why HttpCompression?

Modern web applications need efficient compression to reduce bandwidth and improve response times. **HttpCompression** makes it simple to compress content for HTTP responses with multiple algorithms, while providing:

- **Multiple Algorithm Support** — gzip, brotli (br), and zstd compression out of the box
- **Fluent API** — intuitive builder pattern for configuring compression
- **Batch Operations** — compress multiple files or content strings efficiently
- **Deterministic Builds** — perfect for static file precompression (e.g., for nginx)
- **Safe & Robust** — comprehensive error handling with fail-fast or graceful degradation
- **Zero Framework Dependency** — works with any PHP application or framework

## Installation

**Requirements:**
- PHP 8.4 or higher
- `ext-zlib` (required for gzip)
- `ext-brotli` (optional, for brotli compression)
- `ext-zstd` (optional, for zstd compression)

Install via Composer:

```bash
composer require aurynx/http-compression
```

## Quick Start

### Basic Example

Compress a string with gzip:

```php
use aurynx\HttpCompression\CompressionBuilder;
use aurynx\HttpCompression\AlgorithmEnum;

$builder = new CompressionBuilder();
$builder->add('Hello, World!', AlgorithmEnum::Gzip);

$results = $builder->compress();

foreach ($results as $result) {
    if ($result->isOk()) {
        $compressed = $result->getCompressedFor(AlgorithmEnum::Gzip);
        // Use compressed data in your HTTP response
        header('Content-Encoding: gzip');
        echo $compressed;
    }
}
```

### For AI assistants

You are a coding agent working in PHP 8.4+. Use this library to compress HTTP payloads
(strings or files) with gzip, brotli, or zstd.

For a detailed guide and server integration examples, see [docs/ai.md](docs/ai.md).

**Core flow:**
1. Create `CompressionBuilder()`. Use `graceful()` for user-facing paths, `failFast()` for build steps.
2. Add payloads via `add()` or `addFile()`; for batches use `addMany()` / `addManyFiles()`.
3. Choose algorithms by `Accept-Encoding` (prefer `br`, then `zstd`, then `gzip`).
4. Call `compress()`; inspect `CompressionResult`:
    - `isOk()` → use `getCompressed()` or `getCompressedFor()`
    - `isPartial()` → use successes and log `getAlgorithmErrors()`
    - `isError()` → fall back to identity

**Defaults:** gzip=6, br=4, zstd=3.  
**Static precompression:** save `.gz` / `.br` files next to original and enable `gzip_static on;` / `brotli_static on;`.
**Guardrails:** set `maxBytes` on the builder for large data.

### Static File Delivery with Nginx and Apache

If you precompress files using this library, web servers can automatically serve them
without recompression.

#### Nginx example (`examples/nginx.conf`)

```nginx
# Serve precompressed static assets (.gz, .br, .zst)
gzip_static   on;   # .gz support
brotli_static on;   # .br support (requires ngx_brotli)
zstd_static   on;   # .zst support (requires ngx_zstd)

# Optional: static HTML cache location
location / {
    try_files
        /cache/static$uri.html
        /cache/static$uri/index.html
        $uri
        $uri/
        /index.php?$query_string;
}
```

### Apache example (`examples/apache.conf`)

```apacheconf
AddEncoding br   .br
AddEncoding gzip .gz
AddEncoding zstd .zst

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Accept-encoding} br
    RewriteCond %{REQUEST_FILENAME}\.br -f
    RewriteRule ^(.+)$ $1.br [QSA,L]

    RewriteCond %{HTTP:Accept-encoding} zstd
    RewriteCond %{REQUEST_FILENAME}\.zst -f
    RewriteRule ^(.+)$ $1.zst [QSA,L]

    RewriteCond %{HTTP:Accept-encoding} gzip
    RewriteCond %{REQUEST_FILENAME}\.gz -f
    RewriteRule ^(.+)$ $1.gz [QSA,L]
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "\.(js|css|html|json|xml|svg|zst|gz|br)$">
        Header append Vary Accept-Encoding
    </FilesMatch>
</IfModule>
```

This setup allows Nginx or Apache to serve the compressed versions
generated by `CompressionBuilder::addManyFiles()` automatically.

---

### Multiple Algorithms

Compress the same content with multiple algorithms:

```php
$builder = new CompressionBuilder();
$builder->add('{"user": "john", "active": true}', [
    AlgorithmEnum::Gzip->value => 9,    // level 9
    AlgorithmEnum::Brotli->value => 11, // level 11
    AlgorithmEnum::Zstd->value => 3     // level 3
]);

$results = $builder->compress();
$result = $results[0];

// Get all compressed variants
$allCompressed = $result->getCompressed();
// ['gzip' => '...', 'br' => '...', 'zstd' => '...']
```

### File Compression

Perfect for static asset precompression:

```php
$builder = new CompressionBuilder();
$builder->addFile('/path/to/app.js', [
    AlgorithmEnum::Gzip->value => 9,
    AlgorithmEnum::Brotli->value => 11
]);

$results = $builder->compress();

if ($results[0]->isOk()) {
    $gzipped = $results[0]->getCompressedFor(AlgorithmEnum::Gzip);
    $brotlied = $results[0]->getCompressedFor(AlgorithmEnum::Brotli);
    
    // Save precompressed files
    file_put_contents('/path/to/app.js.gz', $gzipped);
    file_put_contents('/path/to/app.js.br', $brotlied);
}
```

## Features

### Fluent Builder API

Chain methods for intuitive configuration:

```php
$results = new CompressionBuilder()
    ->add('Content 1', AlgorithmEnum::Gzip)
    ->add('Content 2', AlgorithmEnum::Brotli)
    ->addFile('/path/to/file.txt', [
        AlgorithmEnum::Gzip->value => 6,
        AlgorithmEnum::Brotli->value => 4
    ])
    ->compress();
```

### Batch Operations

Compress multiple items efficiently:

```php
// Raw content
$builder->addMany(['data1', 'data2', 'data3'], AlgorithmEnum::Gzip);

// Multiple files
$builder->addManyFiles([
    '/path/to/file1.css',
    '/path/to/file2.js',
    '/path/to/file3.html'
], [
    AlgorithmEnum::Gzip->value => 9,
    AlgorithmEnum::Brotli->value => 11
]);
```

### Fine-Grained Control

Configure individual items:

```php
$builder = new CompressionBuilder();
$builder->add('Content', AlgorithmEnum::Gzip, 'my-custom-id');

// Reconfigure later
$builder->forItem('my-custom-id')
    ->withAlgorithms([
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Zstd->value => 3
    ]);

// Or configure the last added item
$builder->add('Another content')
    ->forLast()
    ->withAlgorithms(AlgorithmEnum::Brotli);
```

### Error Handling

Choose between fail-fast or graceful degradation:

```php
// Fail-fast mode (default): throws exception on first error
$builder = new CompressionBuilder()->failFast();

// Graceful mode: continues on errors, collects them in results
$builder = new CompressionBuilder()->graceful();

$results = $builder->compress();

foreach ($results as $result) {
    if ($result->isOk()) {
        // All algorithms succeeded
        $compressed = $result->getCompressed();
    } elseif ($result->isPartial()) {
        // Some algorithms succeeded, some failed
        $successful = $result->getCompressed();
        $errors = $result->getAlgorithmErrors();
    } else {
        // Complete failure
        $error = $result->getError();
        echo "Error: " . $error->getMessage();
    }
}
```

### Size Limits

Protect against excessive memory usage:

```php
// Global limit for all items (1MB)
$builder = new CompressionBuilder(maxBytes: 1_048_576);

// Or set per item
$builder->add('Large content', AlgorithmEnum::Gzip)
    ->forLast()
    ->withMaxBytes(512_000); // 500KB limit
```

### Result Inspection

Rich result objects with comprehensive information:

```php
$result = $results[0];

// Check status
$result->isOk();        // All algorithms succeeded
$result->isPartial();   // Some algorithms succeeded
$result->isError();     // Complete failure

// Get compressed data
$result->getCompressed();                           // All successful compressions
$result->getCompressedFor(AlgorithmEnum::Gzip);     // Specific algorithm
$result->hasAlgorithm(AlgorithmEnum::Brotli);       // Check if the algorithm was used

// Get errors
$result->getErrors();                               // All errors
$result->getAlgorithmError(AlgorithmEnum::Zstd);    // Error for a specific algorithm

// Get identifier
$result->getIdentifier(); // Item identifier
```

## Usage Examples

### Static Asset Precompression for Nginx

Generate precompressed files for nginx's `gzip_static` and `brotli_static`:

```php
use aurynx\HttpCompression\CompressionBuilder;
use aurynx\HttpCompression\AlgorithmEnum;

$publicDir = __DIR__ . '/public';
$assets = [
    $publicDir . '/css/app.css',
    $publicDir . '/js/app.js',
    $publicDir . '/js/vendor.js'
];

$builder = new CompressionBuilder()
    ->addManyFiles($assets, [
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11
    ]);

$results = $builder->compress();

foreach ($results as $result) {
    if (!$result->isOk()) {
        continue;
    }

    $filePath = $result->getIdentifier();
    
    // Save .gz file
    if ($gzipped = $result->getCompressedFor(AlgorithmEnum::Gzip)) {
        file_put_contents($filePath . '.gz', $gzipped);
    }

    // Save .br file
    if ($brotlied = $result->getCompressedFor(AlgorithmEnum::Brotli)) {
        file_put_contents($filePath . '.br', $brotlied);
    }
}

echo "Precompressed " . count($results) . " files!\n";
```

### API Response Compression

Compress JSON responses based on the client's Accept-Encoding:

```php
function compressResponse(string $json, string $acceptEncoding): array
{
    $builder = new CompressionBuilder();

    // Determine which algorithms the client accepts
    $algorithms = [];

    if (str_contains($acceptEncoding, 'br')) {
        $algorithms[AlgorithmEnum::Brotli->value] = 11;
    }

    if (str_contains($acceptEncoding, 'gzip')) {
        $algorithms[AlgorithmEnum::Gzip->value] = 6;
    }

    if (str_contains($acceptEncoding, 'zstd')) {
        $algorithms[AlgorithmEnum::Zstd->value] = 3;
    }

    // Fallback to gzip if nothing matches
    if (empty($algorithms)) {
        $algorithms[AlgorithmEnum::Gzip->value] = 6;
    }

    $builder->add($json, $algorithms);
    $results = $builder->compress();
    $result = $results[0];

    if (!$result->isOk()) {
        return ['content' => $json, 'encoding' => 'identity'];
    }

    // Prefer brotli, then zstd, then gzip
    foreach ([AlgorithmEnum::Brotli, AlgorithmEnum::Zstd, AlgorithmEnum::Gzip] as $algo) {
        if ($compressed = $result->getCompressedFor($algo)) {
            return ['content' => $compressed, 'encoding' => $algo->value];
        }
    }

    return ['content' => $json, 'encoding' => 'identity'];
}

// Usage in your controller/handler
$json = json_encode(['users' => $users]);
$response = compressResponse($json, $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');

header('Content-Encoding: ' . $response['encoding']);
header('Content-Type: application/json');

echo $response['content'];
```

### Batch Processing with Default Algorithms

Set default algorithms and override per item:

```php
$builder = new CompressionBuilder()
    ->withDefaultAlgorithms([
        AlgorithmEnum::Gzip->value => 6,
        AlgorithmEnum::Brotli->value => 4
    ])
    ->add('Regular content')  // Uses defaults
    ->add('Important data')
    ->forLast()
    ->withAlgorithms([        // Override for this item
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11,
        AlgorithmEnum::Zstd->value => 3
    ]);

$results = $builder->compress();
```

### Advanced Error Handling

Handle partial failures gracefully:

```php
$builder = new CompressionBuilder()
    ->graceful()  // Don't throw exceptions
    ->add('Test data', [
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11,  // May fail if ext not installed
        AlgorithmEnum::Zstd->value => 3      // May fail if ext not installed
    ]);

$results = $builder->compress();
$result = $results[0];

if ($result->isPartial()) {
    echo "Partial success:\n";
    
    // Use what succeeded
    foreach ($result->getCompressed() as $algo => $compressed) {
        echo "  ✓ $algo: " . strlen($compressed) . " bytes\n";
    }
    
    // Log what failed
    foreach ($result->getAlgorithmErrors() as $algo => $error) {
        echo "  ✗ $algo: {$error['message']} (code: {$error['code']})\n";
    }
}
```

## API Reference

### CompressionBuilder

The main entry point for compression operations.

#### Constructor

```php
new CompressionBuilder(?int $maxBytes = null)
```

**Parameters:**
- `$maxBytes` — Optional global size limit for all items (in bytes)

#### Adding Content

```php
add(string $content, AlgorithmEnum|iterable|null $algorithms = null, ?string $customIdentifier = null): self
```

Add raw content for compression.

**Parameters:**
- `$content` — The string content to compress
- `$algorithms` — Algorithm(s) to use: single enum, or array `['gzip' => 9, 'br' => 11]`
- `$customIdentifier` — Optional custom identifier (auto-generated if null)

---

```php
addFile(string $filePath, AlgorithmEnum|iterable|null $algorithms = null, ?string $customIdentifier = null): self
```

Add a file for compression.

**Parameters:**
- `$filePath` — Path to the file
- `$algorithms` — Algorithm(s) to use
- `$customIdentifier` — Optional custom identifier (defaults to file path)

---

```php
addMany(iterable $payloads, AlgorithmEnum|iterable|null $defaultAlgorithms = null): self
```

Add multiple content items at once.

**Parameters:**
- `$payloads` — Array of strings or structured arrays with `['content' => '...', 'algorithms' => [...], 'identifier' => '...']`
- `$defaultAlgorithms` — Default algorithms for items that don't specify their own

---

```php
addManyFiles(iterable $payloads, AlgorithmEnum|iterable|null $defaultAlgorithms = null): self
```

Add multiple files at once.

#### Configuration

```php
withDefaultAlgorithms(AlgorithmEnum|iterable|null $algorithms): self
```

Set default algorithms for subsequently added items.

---

```php
forItem(string $identifier): ItemConfigurator
```

Get a configurator for a specific item (chainable).

---

```php
forLast(): ItemConfigurator
```

Get a configurator for the last added item (chainable).

---

```php
failFast(): self
```

Enable fail-fast mode (throw exception on first error). **Default behavior.**

---

```php
graceful(): self
```

Enable graceful mode (continue on errors, collect them in results).

#### Execution

```php
compress(): array<string, CompressionResult>
```

Execute compression and return results indexed by identifier.

### AlgorithmEnum

Enum representing compression algorithms.

**Cases:**
- `AlgorithmEnum::Gzip` — gzip compression (requires `ext-zlib`)
- `AlgorithmEnum::Brotli` — Brotli compression (requires `ext-brotli`)
- `AlgorithmEnum::Zstd` — Zstandard compression (requires `ext-zstd`)

**Methods:**
```php
isAvailable(): bool           // Check if the algorithm is available
getDefaultLevel(): int        // Get the default compression level
getMinLevel(): int            // Get minimum compression level
getMaxLevel(): int            // Get maximum compression level
```

### CompressionResult

Result object for a single compression operation.

**Methods:**

```php
isOk(): bool                  // True if all algorithms succeeded
isPartial(): bool             // True if some algorithms succeeded, some failed
isError(): bool               // True if complete failure

getIdentifier(): string       // Get item identifier

getCompressed(): array        // Get all successful compressions ['gzip' => '...', ...]
getCompressedFor(AlgorithmEnum $algorithm): ?string  // Get compressed data for a specific algorithm

getErrors(): array            // Get all error details
getError(): ?CompressionException  // Get a complete failure exception
getAlgorithmErrors(): array   // Get per-algorithm errors
getAlgorithmError(AlgorithmEnum $algorithm): ?array  // Get error for specific algorithm
```

### ItemConfigurator

Fluent configurator for individual items (obtained via `forItem()` or `forLast()`).

**Methods:**

```php
withAlgorithms(AlgorithmEnum|iterable $algorithms): CompressionBuilder
```

Set algorithms for this item.

---

```php
withMaxBytes(?int $maxBytes): CompressionBuilder
```

Set a size limit for this item.

### ErrorCode

Enum with machine-readable error codes.

**Cases:**
- `UNKNOWN_ALGORITHM` (1001)
- `ALGORITHM_UNAVAILABLE` (1002)
- `LEVEL_OUT_OF_RANGE` (1003)
- `FILE_NOT_FOUND` (1004)
- `FILE_NOT_READABLE` (1005)
- `PAYLOAD_TOO_LARGE` (1006)
- `COMPRESSION_FAILED` (1007)
- `DUPLICATE_IDENTIFIER` (1009)
- `ITEM_NOT_FOUND` (1010)
- `INVALID_ALGORITHM_SPEC` (1011)
- `EMPTY_ALGORITHMS` (1012)
- `INVALID_PAYLOAD` (1013)
- `NO_ITEMS` (1014)
- `INVALID_LEVEL_TYPE` (1015)

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer stan
```

Check code style:

```bash
composer cs:check
```

Fix code style:

```bash
composer cs:fix
```

## Best Practices

### 1. Use Appropriate Compression Levels

Different algorithms and levels are suited for different use cases:

- **Gzip**: Level 6 for dynamic content, level 9 for static precompression
- **Brotli**: Level 4-5 for dynamic content, level 11 for static precompression
- **Zstd**: Level 3 for dynamic content, level 19+ for static precompression

### 2. Precompress Static Assets

For static files served by nginx or similar:

```php
$builder->addManyFiles($staticAssets, [
    AlgorithmEnum::Gzip->value => 9,
    AlgorithmEnum::Brotli->value => 11
]);
```

Then configure nginx:
```nginx
gzip_static on;
brotli_static on;
```

### 3. Use Graceful Mode for Client-Facing Operations

When compressing API responses, use graceful mode to ensure at least some compression succeeds:

```php
$builder = new CompressionBuilder()->graceful();
```

### 4. Set Size Limits

Protect against memory exhaustion:

```php
$builder = new CompressionBuilder(maxBytes: 10_485_760); // 10MB limit
```

### 5. Check Algorithm Availability

Before deploying, verify required extensions are installed:

```php
if (!AlgorithmEnum::Brotli->isAvailable()) {
    throw new RuntimeException('Brotli extension not installed');
}
```

## Performance Considerations

- **Precompression**: For static assets, precompress once at build time rather than on every request
- **Level Selection**: Higher compression levels significantly increase CPU time with diminishing returns
- **Algorithm Choice**: Brotli typically offers best compression ratio, but slower than gzip; zstd offers good balance
- **Caching**: Cache compressed responses in memory (Redis, Memcached) or on disk

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests and code style checks
5. Commit your changes (see commit message guidelines)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer stan

# Check code style
composer cs:check
```

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Credits

Created and maintained by [Anton Semenov](mailto:anton.a.semenov@proton.me).

---

<p align="center">Crafted by Aurynx 🔮</p>
