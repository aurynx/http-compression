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
  <a href="#documentation">Documentation</a> •
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
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

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

### Algorithm Detection

Check which compression algorithms are available on your system:

```php
// Get all available algorithms
$available = AlgorithmEnum::available();

foreach ($available as $algo) {
    echo "{$algo->value} is available\n";
}

// Check a specific algorithm
if (AlgorithmEnum::Brotli->isAvailable()) {
    // Use brotli compression
}
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

## Documentation

- **[Usage Examples](docs/examples.md)** — Static asset precompression, API responses, build scripts
- **[Advanced Usage](docs/advanced-usage.md)** — Direct compressor access, middleware, custom implementations, testing, benchmarks
- **[API Reference](docs/api-reference.md)** — Complete API documentation for all classes and methods
- **[AI Integration Guide](docs/ai.md)** — Guide for AI coding assistants

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
