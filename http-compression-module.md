# HTTP Compression Module Architecture

## Project Overview

Create a standalone, framework-agnostic PHP library for HTTP compression (gzip, brotli, deflate) that can be used as a dependency in any project.

## Core Principles

### Single Responsibility
The compression library should **ONLY** handle compression. It must NOT:
- ❌ Manage HTTP Request/Response objects
- ❌ Implement middleware patterns
- ❌ Handle terminate callbacks
- ❌ Make decisions about when/where to compress
- ❌ Know about static caching or any specific use case
- ❌ Set HTTP headers (Vary, ETag, Content-Length, etc.)

The library provides **tools for compression**. The consuming application decides **how and when** to use them.

**The compressor doesn't know about HTTP.** It just compresses bytes:

```php
// ✅ What the compressor does:
$compressor = new BrotliCompressor();
$compressed = $compressor->compress($data); // bytes in, bytes out

// ❌ What the compressor does NOT do:
$compressor->setHeader('Vary', 'Accept-Encoding'); // NO!
$compressor->negotiate($_SERVER['HTTP_ACCEPT_ENCODING']); // NO!
```

HTTP-level logic (headers, negotiation, policies) is the responsibility of the **consuming application**.

## Naming Convention

**Package name:** `ayrunx/http-compression`

**Why not just "compression"?**
- Avoids confusion with file archiving (zip, tar, rar)
- Clearly indicates purpose: compressing HTTP traffic/responses
- Aligns with nginx terminology (gzip_static, brotli_static)
- HTTP compression creates `.gz` and `.br` files that nginx serves directly

## PSR Compliance

This library must follow PHP-FIG standards:

### PSR-4: Autoloading
```json
{
    "autoload": {
        "psr-4": {
            "Ayrunx\\HttpCompression\\": "src/"
        }
    }
}
```

**Namespace structure:**
- `Ayrunx\HttpCompression\CompressorInterface`
- `Ayrunx\HttpCompression\GzipCompressor`
- `Ayrunx\HttpCompression\BrotliCompressor`
- `Ayrunx\HttpCompression\FileCompressor`
- etc.

### PSR-12: Coding Style
- Use 4 spaces for indentation (no tabs)
- Opening brace `{` on same line for classes/methods
- `declare(strict_types=1);` at the top of every file
- One blank line after namespace declaration
- Visibility (`public`, `private`, `protected`) declared on all properties and methods

```php
<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

final readonly class BrotliCompressor implements CompressorInterface
{
    public function __construct(
        private int $defaultLevel = 11,
    ) {}
    
    public function compress(string $data, ?int $level = null): string
    {
        // Implementation...
    }
}
```

### PSR-3: Logger Interface (Optional)
If logging is needed, accept `Psr\Log\LoggerInterface`:

```php
use Psr\Log\LoggerInterface;

final readonly class FileCompressor
{
    public function __construct(
        private ?CompressionPolicyInterface $policy = null,
        private ?LoggerInterface $logger = null,
    ) {}
    
    public function compressFile(string $sourcePath, CompressorInterface $compressor): CompressionResult
    {
        $this->logger?->info('Compressing file: {path}', ['path' => $sourcePath]);
        
        // Compression logic...
        
        $this->logger?->debug('Compression complete: {ratio}%', [
            'ratio' => $result->savedPercentage(),
        ]);
        
        return $result;
    }
}
```

**Note:** Logger is optional dependency (`require-dev` or `suggest` in composer.json).

### Why NOT PSR-7/PSR-15?

**PSR-7** (HTTP Message Interface) and **PSR-15** (HTTP Server Request Handlers) are intentionally **NOT** used:

❌ **Reasons to avoid:**
- Makes library HTTP-framework dependent
- Adds unnecessary dependencies (`psr/http-message`, `psr/http-server-handler`)
- Forces consumers to use PSR-7 Request/Response objects
- Contradicts "framework-agnostic" principle

✅ **Instead:**
- Library works with raw `string` data (bytes)
- No dependencies on HTTP abstractions
- Can be used in ANY PHP project (Laravel, Symfony, Slim, vanilla PHP, CLI)
- Consumers integrate however they want

```php
// ✅ Framework-agnostic approach:
$compressed = $compressor->compress($data); // string → string

// ❌ PSR-7 approach (would force dependency):
$compressor->compress($request->getBody()); // Requires PSR-7
```

If a consuming application wants PSR-7 integration, they can build a thin adapter:

```php
// User can create their own adapter if needed:
class Psr7CompressionAdapter
{
    public function __construct(
        private CompressorInterface $compressor,
    ) {}
    
    public function compressResponse(ResponseInterface $response): ResponseInterface
    {
        $body = (string) $response->getBody();
        $compressed = $this->compressor->compress($body);
        
        return $response
            ->withBody(new Stream($compressed))
            ->withHeader('Content-Encoding', $this->compressor->getEncoding())
            ->withHeader('Vary', 'Accept-Encoding');
    }
}
```

### PSR Recommendations Summary

| PSR | Status | Reason |
|-----|--------|--------|
| **PSR-4** | ✅ Required | Autoloading standard |
| **PSR-12** | ✅ Required | Coding style consistency |
| **PSR-3** | ⚠️ Optional | Logger interface (if logging needed) |
| **PSR-7** | ❌ Not used | Would break framework-agnostic principle |
| **PSR-15** | ❌ Not used | Middleware pattern - not compressor's job |

## Supported Algorithms

This library implements only **two compression algorithms**:

1. **Gzip** - Universal compatibility, supported by all browsers
2. **Brotli** - Best compression ratio (15-20% better than gzip), supported by all modern browsers

**Why no Deflate?**
- Historical ambiguity (raw deflate vs zlib-wrapped)
- Interoperability issues between implementations
- No practical advantage over Gzip
- Modern use: Brotli for best compression, Gzip for compatibility

## Module Structure

```
ayrunx/http-compression/
├── composer.json
├── README.md
├── src/
│   ├── CompressorInterface.php           # Contract for all compressors
│   ├── GzipCompressor.php                # gzip implementation
│   ├── BrotliCompressor.php              # brotli implementation
│   ├── FileCompressor.php                # File-level compression operations
│   ├── EncodingNegotiator.php            # Parse Accept-Encoding with q-weights
│   ├── CompressionPolicyInterface.php    # Policy for compression decisions
│   ├── DefaultCompressionPolicy.php      # Default policy implementation
│   ├── CompressionResult.php             # Compression operation metadata
│   ├── CompressionException.php          # Custom exception class
│   └── CompressionAlgorithmEnum.php      # Algorithm enumeration (Gzip, Brotli)
└── tests/
    ├── GzipCompressorTest.php
    ├── BrotliCompressorTest.php
    ├── EncodingNegotiatorTest.php
    ├── FileCompressorTest.php
    ├── DeterministicCompressionTest.php  # Golden tests
    └── FuzzTest.php                      # Fuzz testing
```

**Note:** Only Gzip and Brotli are implemented. Deflate is intentionally excluded due to historical ambiguity issues.

## API Design

### 1. CompressorInterface

```php
interface CompressorInterface
{
    /**
     * Compress data with specified level
     * 
     * @param string $data Raw data to compress
     * @param int|null $level Compression level (null uses default)
     * @return string Compressed data
     * @throws CompressionException If compression fails
     */
    public function compress(string $data, ?int $level = null): string;
    
    /**
     * Decompress data
     * 
     * @param string $data Compressed data
     * @param int|null $maxSize Maximum decompressed size (zip bomb protection)
     * @return string Decompressed data
     * @throws CompressionException If decompression fails or exceeds maxSize
     */
    public function decompress(string $data, ?int $maxSize = null): string;
    
    /**
     * Check if required PHP extension is available
     * 
     * @return bool True if this compressor can be used
     */
    public function supports(): bool;
    
    /**
     * Get valid compression level range
     * 
     * @return array{min: int, max: int} Level boundaries
     */
    public function levelRange(): array;
    
    /**
     * Get encoding name for Content-Encoding header
     * 
     * @return string 'gzip', 'br', or 'deflate'
     */
    public function getEncoding(): string;
    
    /**
     * Get file extension for pre-compressed files
     * 
     * @return string '.gz', '.br', or '.deflate'
     */
    public function getExtension(): string;
}
```

### 2. Concrete Implementations

```php
final readonly class BrotliCompressor implements CompressorInterface
{
    public function __construct(
        private int $defaultLevel = 11, // Maximum compression for static files
    ) {}
    
    public function compress(string $data, ?int $level = null): string
    {
        $level ??= $this->defaultLevel;
        
        if (!$this->supports()) {
            throw new CompressionException('Brotli extension not available');
        }
        
        // Validate level
        $range = $this->levelRange();
        if ($level < $range['min'] || $level > $range['max']) {
            throw new CompressionException(
                "Brotli level must be between {$range['min']} and {$range['max']}, got {$level}"
            );
        }
        
        $result = brotli_compress($data, $level);
        if ($result === false) {
            throw new CompressionException('Brotli compression failed');
        }
        
        return $result;
    }
    
    public function decompress(string $data, ?int $maxSize = null): string
    {
        if (!$this->supports()) {
            throw new CompressionException('Brotli extension not available');
        }
        
        $result = brotli_uncompress($data);
        
        if ($result === false) {
            throw new CompressionException('Brotli decompression failed');
        }
        
        // Zip bomb protection
        if ($maxSize !== null && strlen($result) > $maxSize) {
            throw new CompressionException(
                "Decompressed size " . strlen($result) . " exceeds limit {$maxSize}"
            );
        }
        
        return $result;
    }
    
    public function supports(): bool
    {
        return extension_loaded('brotli');
    }
    
    public function levelRange(): array
    {
        return ['min' => 0, 'max' => 11];
    }
    
    public function getEncoding(): string
    {
        return 'br';
    }
    
    public function getExtension(): string
    {
        return '.br';
    }
}

final readonly class GzipCompressor implements CompressorInterface
{
    public function __construct(
        private int $defaultLevel = 9,      // Maximum compression for static files
        private bool $deterministic = false, // Zero mtime for reproducible builds
    ) {}
    
    public function compress(string $data, ?int $level = null): string
    {
        $level ??= $this->defaultLevel;
        
        // Validate level
        $range = $this->levelRange();
        if ($level < $range['min'] || $level > $range['max']) {
            throw new CompressionException(
                "Gzip level must be between {$range['min']} and {$range['max']}, got {$level}"
            );
        }
        
        // Compress with optional deterministic mode
        if ($this->deterministic) {
            // Use gzcompress (raw deflate) and manually add gzip header with zero mtime
            $compressed = gzcompress($data, $level);
            if ($compressed === false) {
                throw new CompressionException('Gzip compression failed');
            }
            
            // Build deterministic gzip header (mtime = 0)
            $header = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\xff";
            $footer = pack('V', crc32($data)) . pack('V', strlen($data));
            
            return $header . substr($compressed, 2, -4) . $footer;
        }
        
        $result = gzencode($data, $level);
        if ($result === false) {
            throw new CompressionException('Gzip compression failed');
        }
        
        return $result;
    }
    
    public function decompress(string $data, ?int $maxSize = null): string
    {
        $result = gzdecode($data);
        
        if ($result === false) {
            throw new CompressionException('Gzip decompression failed');
        }
        
        // Zip bomb protection
        if ($maxSize !== null && strlen($result) > $maxSize) {
            throw new CompressionException(
                "Decompressed size " . strlen($result) . " exceeds limit {$maxSize}"
            );
        }
        
        return $result;
    }
    
    public function supports(): bool
    {
        return extension_loaded('zlib');
    }
    
    public function levelRange(): array
    {
        return ['min' => 1, 'max' => 9];
    }
    
    public function getEncoding(): string
    {
        return 'gzip';
    }
    
    public function getExtension(): string
    {
        return '.gz';
    }
}
```

### 3. FileCompressor - High-level File Operations

```php
final readonly class FileCompressor
{
    public function __construct(
        private ?CompressionPolicyInterface $policy = null,
    ) {}
    
    /**
     * Compress a file and save with appropriate extension
     * 
     * Example: compressFile('index.html', new BrotliCompressor())
     * Creates: index.html.br
     * 
     * Uses atomic write (temp file -> fsync -> rename) to prevent corruption.
     * Preserves original file permissions and timestamps.
     * 
     * @param string $sourcePath Original file path
     * @param CompressorInterface $compressor Compression algorithm
     * @param int|null $level Compression level (null uses compressor default)
     * @return CompressionResult Metadata about compression operation
     * @throws CompressionException If compression fails
     */
    public function compressFile(
        string $sourcePath,
        CompressorInterface $compressor,
        ?int $level = null
    ): CompressionResult {
        if (!file_exists($sourcePath)) {
            throw new CompressionException("File not found: {$sourcePath}");
        }
        
        // Check policy if provided
        if ($this->policy && !$this->policy->shouldCompress($sourcePath)) {
            throw new CompressionException("Policy rejected compression for: {$sourcePath}");
        }
        
        $targetPath = $sourcePath . $compressor->getExtension();
        
        // Skip if compressed version is up-to-date
        if (file_exists($targetPath) && filemtime($targetPath) >= filemtime($sourcePath)) {
            return new CompressionResult(
                sourcePath: $sourcePath,
                targetPath: $targetPath,
                originalSize: filesize($sourcePath),
                compressedSize: filesize($targetPath),
                compressionTime: 0,
                skipped: true,
            );
        }
        
        $startTime = microtime(true);
        $content = file_get_contents($sourcePath);
        $originalSize = strlen($content);
        
        $compressed = $compressor->compress($content, $level);
        $compressedSize = strlen($compressed);
        
        // Atomic write: temp file -> fsync -> rename
        $tempPath = $targetPath . '.' . uniqid('tmp', true);
        
        try {
            file_put_contents($tempPath, $compressed, LOCK_EX);
            
            // Sync to disk (prevent corruption on crash)
            if (function_exists('fsync')) {
                $handle = fopen($tempPath, 'r');
                fsync($handle);
                fclose($handle);
            }
            
            // Preserve original permissions
            $perms = fileperms($sourcePath);
            if ($perms !== false) {
                chmod($tempPath, $perms);
            }
            
            // Atomic rename
            rename($tempPath, $targetPath);
            
            // Preserve original mtime (for cache validation)
            touch($targetPath, filemtime($sourcePath));
            
        } catch (\Throwable $e) {
            // Cleanup temp file on failure
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw new CompressionException("Failed to write compressed file: {$e->getMessage()}", 0, $e);
        }
        
        $compressionTime = microtime(true) - $startTime;
        
        return new CompressionResult(
            sourcePath: $sourcePath,
            targetPath: $targetPath,
            originalSize: $originalSize,
            compressedSize: $compressedSize,
            compressionTime: $compressionTime,
            skipped: false,
        );
    }
    
    /**
     * Pre-compress a file with multiple algorithms
     * 
     * Example: precompressFile('index.html', [new BrotliCompressor(), new GzipCompressor()])
     * Creates: index.html.br AND index.html.gz
     * 
     * @param string $sourcePath Original file path
     * @param array<CompressorInterface> $compressors Array of compressors to use
     * @return array<string> Paths to created compressed files
     */
    public function precompressFile(string $sourcePath, array $compressors): array
    {
        $results = [];
        
        foreach ($compressors as $compressor) {
            $results[] = $this->compressFile($sourcePath, $compressor);
        }
        
        return $results;
    }
    
    /**
     * Check if pre-compressed version exists and is up-to-date
     * 
     * @param string $sourcePath Original file path
     * @param CompressorInterface $compressor Compression algorithm
     * @return bool True if compressed version exists and is newer than source
     */
    public function hasValidCompressedVersion(
        string $sourcePath,
        CompressorInterface $compressor
    ): bool {
        $compressedPath = $sourcePath . $compressor->getExtension();
        
        if (!file_exists($compressedPath)) {
            return false;
        }
        
        // Check if compressed file is newer than source
        return filemtime($compressedPath) >= filemtime($sourcePath);
    }
}
```

### 4. EncodingNegotiator - Accept-Encoding Parser with Q-Weights

```php
final readonly class EncodingNegotiator
{
    /**
     * Parse Accept-Encoding header and select best compressor based on q-weights
     * 
     * Supports:
     * - Quality values (q-weights): br;q=1.0, gzip;q=0.8
     * - Wildcard: *;q=0.1 (matches any encoding)
     * - Identity: identity (no compression)
     * - Rejection: br;q=0 (explicitly reject encoding)
     * 
     * Example: negotiate('br;q=1.0, gzip;q=0.8, *;q=0.1', [...])
     * Returns: BrotliCompressor (highest q-weight)
     * 
     * @param string $acceptEncoding Value from Accept-Encoding header
     * @param array<CompressorInterface> $availableCompressors Available compressors (server priority order)
     * @return CompressorInterface|null Best matching compressor or null if none match
     */
    public function negotiate(
        string $acceptEncoding,
        array $availableCompressors = []
    ): ?CompressorInterface {
        if (empty($availableCompressors)) {
            $availableCompressors = $this->getDefaultCompressors();
        }
        
        $preferences = $this->parseAcceptEncoding($acceptEncoding);
        
        // Build scored list: [encoding => q-weight]
        $scores = [];
        foreach ($availableCompressors as $compressor) {
            $encoding = $compressor->getEncoding();
            
            // Check explicit encoding preference
            if (isset($preferences[$encoding])) {
                $scores[$encoding] = ['compressor' => $compressor, 'q' => $preferences[$encoding]];
            }
            // Check wildcard fallback
            elseif (isset($preferences['*'])) {
                $scores[$encoding] = ['compressor' => $compressor, 'q' => $preferences['*']];
            }
        }
        
        // Sort by q-weight (descending)
        uasort($scores, fn($a, $b) => $b['q'] <=> $a['q']);
        
        // Return first with q > 0
        foreach ($scores as $score) {
            if ($score['q'] > 0) {
                return $score['compressor'];
            }
        }
        
        // Check if identity is acceptable
        if (isset($preferences['identity']) && $preferences['identity'] > 0) {
            return null; // No compression
        }
        
        // If wildcard allows and identity not rejected
        if (isset($preferences['*']) && $preferences['*'] > 0 && 
            (!isset($preferences['identity']) || $preferences['identity'] > 0)) {
            return null; // No compression acceptable
        }
        
        return null;
    }
    
    /**
     * Parse Accept-Encoding header with full q-weight support
     * 
     * Examples:
     * - "gzip, deflate, br" → ['gzip'=>1.0, 'deflate'=>1.0, 'br'=>1.0]
     * - "br;q=1.0, gzip;q=0.8, *;q=0.1" → ['br'=>1.0, 'gzip'=>0.8, '*'=>0.1]
     * - "gzip, identity;q=0" → ['gzip'=>1.0, 'identity'=>0.0]
     * 
     * @param string $acceptEncoding Raw header value
     * @return array<string, float> Map of encoding => q-weight
     */
    private function parseAcceptEncoding(string $acceptEncoding): array
    {
        $preferences = [];
        
        foreach (explode(',', $acceptEncoding) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            
            // Split encoding and parameters
            $segments = explode(';', $part);
            $encoding = trim($segments[0]);
            
            // Default q-weight is 1.0
            $qValue = 1.0;
            
            // Parse q-weight if present
            for ($i = 1; $i < count($segments); $i++) {
                $param = trim($segments[$i]);
                if (preg_match('/^q\s*=\s*(0(?:\.\d{1,3})?|1(?:\.0{1,3})?)$/', $param, $matches)) {
                    $qValue = (float) $matches[1];
                    break;
                }
            }
            
            // Store preference (later values override earlier for same encoding)
            $preferences[$encoding] = $qValue;
        }
        
        return $preferences;
    }
    
    /**
     * Get default compressors in priority order
     * 
     * @return array<CompressorInterface>
     */
    private function getDefaultCompressors(): array
    {
        $compressors = [];
        
        // Brotli has best compression ratio
        if (extension_loaded('brotli')) {
            $compressors[] = new BrotliCompressor();
        }
        
        // Gzip is widely supported fallback
        $compressors[] = new GzipCompressor();
        
        return $compressors;
    }
}
```

### 5. Supporting Classes

#### CompressionResult - Operation Metadata

```php
final readonly class CompressionResult
{
    public function __construct(
        public string $sourcePath,
        public string $targetPath,
        public int $originalSize,
        public int $compressedSize,
        public float $compressionTime,
        public bool $skipped = false,
    ) {}
    
    /**
     * Get compression ratio (0.0 to 1.0)
     * 
     * @return float 0.15 means compressed size is 15% of original
     */
    public function ratio(): float
    {
        if ($this->originalSize === 0) {
            return 1.0;
        }
        
        return $this->compressedSize / $this->originalSize;
    }
    
    /**
     * Get space saved in bytes
     */
    public function savedBytes(): int
    {
        return $this->originalSize - $this->compressedSize;
    }
    
    /**
     * Get compression percentage (e.g., 85% means 85% size reduction)
     */
    public function savedPercentage(): float
    {
        return (1.0 - $this->ratio()) * 100.0;
    }
}
```

#### CompressionException - Custom Exception

```php
final class CompressionException extends \RuntimeException
{
    // Custom exception for all compression-related errors
}
```

#### CompressionPolicyInterface - Compression Decision Logic

```php
interface CompressionPolicyInterface
{
    /**
     * Decide if a file should be compressed
     * 
     * @param string $filePath Path to file
     * @return bool True if file should be compressed
     */
    public function shouldCompress(string $filePath): bool;
}
```

#### DefaultCompressionPolicy - Default Implementation

```php
final readonly class DefaultCompressionPolicy implements CompressionPolicyInterface
{
    /**
     * @param int $minSize Minimum file size in bytes (default 1KB)
     * @param array<string> $allowedMimeTypes MIME types to compress
     * @param array<string> $allowedExtensions File extensions to compress
     */
    public function __construct(
        private int $minSize = 1024,
        private array $allowedMimeTypes = [
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'application/xml',
            'text/xml',
            'image/svg+xml',
            'application/wasm',
        ],
        private array $allowedExtensions = [
            'html', 'htm', 'css', 'js', 'json', 'xml', 'svg', 'wasm', 'txt',
        ],
    ) {}
    
    public function shouldCompress(string $filePath): bool
    {
        // File must exist
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Check minimum size
        $size = filesize($filePath);
        if ($size < $this->minSize) {
            return false; // Too small, overhead not worth it
        }
        
        // Check extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return false;
        }
        
        // Check MIME type (if finfo is available)
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType && !$this->isMimeTypeAllowed($mimeType)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function isMimeTypeAllowed(string $mimeType): bool
    {
        foreach ($this->allowedMimeTypes as $allowed) {
            if (str_starts_with($mimeType, $allowed)) {
                return true;
            }
        }
        
        return false;
    }
}
```

## Integration Guidelines for Applications

**Important:** The compression library is framework-agnostic and knows nothing about HTTP. It provides **metadata** that applications can use to set proper HTTP headers, but it never sends headers itself.

### Separation of Concerns

```php
// ✅ Compressor provides metadata:
$compressor = new BrotliCompressor();
$compressed = $compressor->compress($data);

// Metadata available:
$encoding = $compressor->getEncoding();      // "br"
$extension = $compressor->getExtension();    // ".br"
$size = strlen($compressed);                 // Compressed size

// ❌ Compressor does NOT:
// - Send HTTP headers
// - Call header() function
// - Know about Response objects
// - Make HTTP decisions

// ✅ Application uses metadata to set headers:
header('Content-Encoding: ' . $encoding);
header('Content-Length: ' . $size);
header('Vary: Accept-Encoding');
```

### 1. HTTP Headers - Application Responsibility

The compressor provides data (`getEncoding()`, compressed size), your application sets headers:

#### Vary: Accept-Encoding
```php
// Application code:
$compressed = $compressor->compress($response->content);

header('Vary: Accept-Encoding');
header('Content-Encoding: ' . $compressor->getEncoding()); // Uses metadata
header('Content-Length: ' . strlen($compressed));
```

Why? CDNs and proxies cache different versions for different clients.

#### ETag Modification
```php
// Application decides ETag strategy:
// Weak ETag approach (recommended)
header('ETag: W/"abc123"');

// OR encoding-specific
$encoding = $compressor->getEncoding();
header('ETag: "abc123-' . $encoding . '"');
```

#### Content-Length
Application calculates from compressed output:
```php
$compressed = $compressor->compress($data);
header('Content-Length: ' . strlen($compressed));
```

### 2. Cache-Control: no-transform

Your application must respect `Cache-Control: no-transform`:

```php
// In your middleware/application:
$cacheControl = $response->headers['Cache-Control'] ?? '';

if (str_contains($cacheControl, 'no-transform')) {
    // Do NOT compress - CDN/proxy contract
    return $response;
}

// Otherwise, compress
$compressed = $compressor->compress($response->content);
```

This is critical for:
- CDN compatibility (Cloudflare, Fastly)
- Signed responses (compression breaks signature)
- Content integrity requirements

### 3. Content Negotiation

Use `EncodingNegotiator` in your application to choose algorithm:

```php
// In your middleware/controller:
$negotiator = new EncodingNegotiator();
$compressor = $negotiator->negotiate(
    $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    [new BrotliCompressor(), new GzipCompressor()]
);

if ($compressor) {
    $compressed = $compressor->compress($response->content);
    header('Content-Encoding: ' . $compressor->getEncoding());
    header('Vary: Accept-Encoding');
    return $compressed;
}

return $response->content; // No compression
```

**Remember:** The compressor just compresses bytes. Your application handles HTTP semantics.

## Usage Examples

### Example 1: Pre-compress static HTML cache (Framework integration)

```php
// In StaticCacheMiddleware (Ayrunx Framework)
class StaticCacheMiddleware
{
    public function __construct(
        private string $cacheBasePath,
        private ?FileCompressor $compressor = null,
    ) {}
    
    private function saveToCache(Request $request, Response $response): void
    {
        $cachePath = $this->cacheBasePath . $request->uri;
        
        if (str_ends_with($cachePath, '/')) {
            $cachePath .= 'index.html';
        } else {
            $cachePath .= '.html';
        }
        
        // Save original HTML
        $directory = dirname($cachePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Failed to create directory: {$directory}");
        }
        
        file_put_contents($cachePath, $response->content, LOCK_EX);
        
        // Pre-compress for nginx (if compressor is injected)
        if ($this->compressor) {
            // Creates index.html.br and index.html.gz
            $this->compressor->precompressFile($cachePath, [
                new BrotliCompressor(level: 11), // Best compression for static files
                new GzipCompressor(level: 9),    // Fallback for older clients
            ]);
        }
    }
}
```

### Example 2: On-the-fly compression for API responses

```php
// In a controller or response handler
public function largeJsonEndpoint(): Response
{
    $data = $this->service->getLargeDataset();
    $json = json_encode($data);
    
    // Negotiate best compression algorithm
    $negotiator = new EncodingNegotiator();
    $compressor = $negotiator->negotiate(
        $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        [new BrotliCompressor(level: 6), new GzipCompressor(level: 6)]
    );
    
    if ($compressor && strlen($json) > 1024) { // Only compress if > 1KB
        $json = $compressor->compress($json);
        return new Response(
            content: $json,
            headers: [
                'Content-Type' => 'application/json',
                'Content-Encoding' => $compressor->getEncoding(),
            ]
        );
    }
    
    return Response::json($data);
}
```

### Example 3: CLI batch pre-compression

```php
// CLI command to pre-compress all cached HTML files
class PrecompressStaticCacheCommand
{
    public function handle(): void
    {
        $compressor = new FileCompressor(
            policy: new DefaultCompressionPolicy(minSize: 512)
        );
        
        // Use RecursiveDirectoryIterator (more reliable than glob with **)
        $directory = new \RecursiveDirectoryIterator(
            '/public/cache/static',
            \RecursiveDirectoryIterator::SKIP_DOTS
        );
        
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = new \RegexIterator($iterator, '/\.html$/');
        
        $processed = 0;
        $skipped = 0;
        $totalSaved = 0;
        
        foreach ($files as $file) {
            $filePath = $file->getPathname();
            
            try {
                $results = $compressor->precompressFile($filePath, [
                    new BrotliCompressor(level: 11),
                    new GzipCompressor(level: 9, deterministic: true),
                ]);
                
                foreach ($results as $result) {
                    if ($result->skipped) {
                        $skipped++;
                    } else {
                        $processed++;
                        $totalSaved += $result->savedBytes();
                        
                        echo sprintf(
                            "✓ %s: %s → %s (%.1f%% saved)\n",
                            basename($result->targetPath),
                            $this->formatBytes($result->originalSize),
                            $this->formatBytes($result->compressedSize),
                            $result->savedPercentage()
                        );
                    }
                }
            } catch (CompressionException $e) {
                echo "✗ {$filePath}: {$e->getMessage()}\n";
            }
        }
        
        echo "\nSummary:\n";
        echo "  Processed: {$processed}\n";
        echo "  Skipped: {$skipped}\n";
        echo "  Total saved: " . $this->formatBytes($totalSaved) . "\n";
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
```

### Example 4: Framework-agnostic usage

```php
// Can be used in ANY PHP project, not just Ayrunx
function compressAssets(string $buildDir): void
{
    $compressor = new FileCompressor();
    
    // Compress all CSS and JS files
    $files = array_merge(
        glob("{$buildDir}/**/*.css"),
        glob("{$buildDir}/**/*.js")
    );
    
    foreach ($files as $file) {
        $compressor->precompressFile($file, [
            new BrotliCompressor(),
            new GzipCompressor(),
        ]);
    }
}

// Works with Laravel, Symfony, Slim, or plain PHP
compressAssets(__DIR__ . '/public/build');
```

## Nginx Integration

### Configuration for pre-compressed files

```nginx
# Enable serving pre-compressed static files
gzip_static on;      # Serve .gz files if available
brotli_static on;    # Serve .br files if available (requires ngx_brotli module)

location / {
    # Nginx automatically looks for .br and .gz versions
    # No need to explicitly list them in try_files
    try_files 
        /cache/static$uri.html 
        /cache/static$uri/index.html 
        $uri 
        $uri/ 
        /index.php?$query_string;
}
```

When nginx has `gzip_static on` and `brotli_static on`:
1. Client requests `/`
2. Nginx checks for `/cache/static/index.html`
3. Nginx **automatically** checks for `/cache/static/index.html.br` (if client accepts brotli)
4. If `.br` doesn't exist, checks for `/cache/static/index.html.gz` (if client accepts gzip)
5. If neither exists, serves original `/cache/static/index.html`

**Result:** Pre-compressed files served instantly by nginx, zero PHP overhead.

## Performance Characteristics

### Compression Levels

**Brotli:**
- Level 1-4: Fast, moderate compression (~5-10ms per 100KB)
- Level 5-9: Balanced (default for on-the-fly)
- Level 10-11: Maximum compression, slow (~20-50ms per 100KB)
- **Use case:** Level 11 for static pre-compression, level 6 for on-the-fly

**Gzip:**
- Level 1-3: Fast, lower compression
- Level 6: Balanced (default)
- Level 9: Maximum compression, slower
- **Use case:** Level 9 for static pre-compression, level 6 for on-the-fly

### Compression Ratios (typical)

| Content Type | Original | Gzip (level 9) | Brotli (level 11) |
|--------------|----------|----------------|-------------------|
| HTML         | 100 KB   | 15-20 KB       | 12-18 KB          |
| CSS          | 100 KB   | 18-22 KB       | 15-20 KB          |
| JSON         | 100 KB   | 10-15 KB       | 8-12 KB           |
| JavaScript   | 100 KB   | 20-25 KB       | 18-22 KB          |

**Brotli is ~15-20% better than gzip, especially for text/HTML.**

### When to skip compression

- Files < 1KB (overhead not worth it)
- Already compressed formats (JPEG, PNG, WebP, MP4, ZIP)
- Real-time streaming data
- Content that changes constantly (no caching benefit)

## Dependencies

### composer.json Configuration

```json
{
    "name": "ayrunx/http-compression",
    "description": "Framework-agnostic HTTP compression library (gzip, brotli)",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "ext-zlib": "*"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "phpstan/phpstan": "^2.0"
    },
    "suggest": {
        "ext-brotli": "Enables Brotli compression (15-20% better than gzip)"
    },
    "autoload": {
        "psr-4": {
            "Ayrunx\\HttpCompression\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Ayrunx\\HttpCompression\\Tests\\": "tests/"
        }
    },
    "config": {
        "platform": {
            "php": "8.4.0"
        },
        "sort-packages": true
    }
}
```

### Required Extensions
- **PHP 8.4+** - Minimum version
- **ext-zlib** - For gzip/deflate compression (usually bundled with PHP)

### Optional Extensions
- **ext-brotli** - For brotli compression (15-20% better compression than gzip)
- Library gracefully falls back to gzip if brotli is not available
- Check availability with `extension_loaded('brotli')`

### Installing Brotli Extension

#### Via PECL (Linux/macOS)
```bash
pecl install brotli
echo "extension=brotli.so" > /etc/php/8.4/mods-available/brotli.ini
phpenmod brotli
php -m | grep brotli  # Verify installation
```

#### Docker
```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache $PHPIZE_DEPS brotli-dev \
    && pecl install brotli \
    && docker-php-ext-enable brotli
```

#### Verification
```php
if (extension_loaded('brotli')) {
    echo "✅ Brotli available\n";
} else {
    echo "⚠️  Brotli not available, falling back to gzip\n";
}
```

## Testing Requirements

**Testing Framework:** Use **Pest** (modern testing framework built on PHPUnit).

### Why Pest?
- ✅ Cleaner, more readable syntax
- ✅ Less boilerplate than PHPUnit
- ✅ Built-in expectations and matchers
- ✅ Better for modern PHP 8.4+ projects
- ✅ Compatible with PHPUnit ecosystem

### Unit Tests

Test each compressor independently with round-trip tests:

```php
// tests/Unit/GzipCompressorTest.php
use Ayrunx\HttpCompression\GzipCompressor;
use Ayrunx\HttpCompression\CompressionException;

it('compresses and decompresses data correctly', function () {
    $compressor = new GzipCompressor();
    $data = 'test data';
    
    $compressed = $compressor->compress($data);
    $decompressed = $compressor->decompress($compressed);
    
    expect($decompressed)->toBe($data);
});

it('validates compression levels', function () {
    $compressor = new GzipCompressor();
    
    expect(fn() => $compressor->compress('data', level: 10))
        ->toThrow(CompressionException::class, 'level must be between 1 and 9');
});

it('checks if zlib extension is available', function () {
    $compressor = new GzipCompressor();
    
    expect($compressor->supports())->toBeTrue();
});

test('levelRange returns correct boundaries', function () {
    $compressor = new GzipCompressor();
    
    expect($compressor->levelRange())->toBe(['min' => 1, 'max' => 9]);
});

test('getEncoding returns gzip', function () {
    $compressor = new GzipCompressor();
    
    expect($compressor->getEncoding())->toBe('gzip');
});

test('getExtension returns .gz', function () {
    $compressor = new GzipCompressor();
    
    expect($compressor->getExtension())->toBe('.gz');
});
```

### Deterministic Compression Tests (Golden Tests)

Verify that deterministic mode produces identical output every time:

```php
// tests/Unit/DeterministicCompressionTest.php
use Ayrunx\HttpCompression\GzipCompressor;

it('produces byte-identical output in deterministic mode', function () {
    $compressor = new GzipCompressor(level: 9, deterministic: true);
    $data = 'test data';
    
    $compressed1 = $compressor->compress($data);
    $compressed2 = $compressor->compress($data);
    
    expect($compressed1)->toBe($compressed2);
});

it('matches golden file output', function () {
    $compressor = new GzipCompressor(level: 9, deterministic: true);
    $data = file_get_contents(__DIR__ . '/../fixtures/test-data.txt');
    
    $compressed = $compressor->compress($data);
    $golden = file_get_contents(__DIR__ . '/../fixtures/test-data.txt.gz');
    
    expect($compressed)->toBe($golden);
});

test('brotli is deterministic by default', function () {
    $compressor = new BrotliCompressor(level: 11);
    $data = 'test data';
    
    $compressed1 = $compressor->compress($data);
    sleep(1); // Wait to ensure timestamp would differ if included
    $compressed2 = $compressor->compress($data);
    
    expect($compressed1)->toBe($compressed2);
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');
```

**Why golden tests matter:**
- CI/CD reproducible builds
- Content addressing (hash-based caching)
- Checksum verification of artifacts

### Fuzz Tests

Test with corrupted/malicious input:

```php
// tests/Unit/FuzzTest.php
use Ayrunx\HttpCompression\GzipCompressor;
use Ayrunx\HttpCompression\CompressionException;

it('rejects corrupted data', function () {
    $compressor = new GzipCompressor();
    
    expect(fn() => $compressor->decompress('corrupted data'))
        ->toThrow(CompressionException::class);
});

it('protects against zip bombs', function () {
    $compressor = new GzipCompressor();
    
    // Create a zip bomb (small compressed, huge decompressed)
    $smallData = str_repeat('A', 1000);
    $compressed = $compressor->compress($smallData);
    
    // Simulate a bomb by trying to decompress with tiny limit
    expect(fn() => $compressor->decompress($compressed, maxSize: 100))
        ->toThrow(CompressionException::class, 'exceeds limit');
});

it('handles empty string gracefully', function () {
    $compressor = new GzipCompressor();
    
    $compressed = $compressor->compress('');
    $decompressed = $compressor->decompress($compressed);
    
    expect($decompressed)->toBe('');
});

it('handles large files', function () {
    $compressor = new GzipCompressor();
    $largeData = str_repeat('Lorem ipsum dolor sit amet ', 100000); // ~2.7 MB
    
    $compressed = $compressor->compress($largeData);
    $decompressed = $compressor->decompress($compressed);
    
    expect($decompressed)->toBe($largeData);
})->group('slow');
```

### Integration Tests

Test FileCompressor with real files:

```php
// tests/Integration/FileCompressorTest.php
use Ayrunx\HttpCompression\FileCompressor;
use Ayrunx\HttpCompression\BrotliCompressor;
use Ayrunx\HttpCompression\GzipCompressor;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/compression-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    // Cleanup
    array_map('unlink', glob($this->tempDir . '/*'));
    rmdir($this->tempDir);
});

it('creates compressed file with correct extension', function () {
    $testFile = $this->tempDir . '/test.html';
    file_put_contents($testFile, '<html>test</html>');
    
    $compressor = new FileCompressor();
    $result = $compressor->compressFile($testFile, new GzipCompressor());
    
    expect($result->targetPath)->toBe($testFile . '.gz');
    expect(file_exists($result->targetPath))->toBeTrue();
});

it('preserves file permissions', function () {
    $testFile = $this->tempDir . '/test.html';
    file_put_contents($testFile, '<html>test</html>');
    chmod($testFile, 0644);
    
    $compressor = new FileCompressor();
    $result = $compressor->compressFile($testFile, new GzipCompressor());
    
    $originalPerms = fileperms($testFile) & 0777;
    $compressedPerms = fileperms($result->targetPath) & 0777;
    
    expect($compressedPerms)->toBe($originalPerms);
});

it('skips compression if compressed file is newer', function () {
    $testFile = $this->tempDir . '/test.html';
    file_put_contents($testFile, '<html>test</html>');
    
    $compressor = new FileCompressor();
    
    // First compression
    $result1 = $compressor->compressFile($testFile, new GzipCompressor());
    expect($result1->skipped)->toBeFalse();
    
    // Second compression (should skip)
    $result2 = $compressor->compressFile($testFile, new GzipCompressor());
    expect($result2->skipped)->toBeTrue();
    expect($result2->compressionTime)->toBe(0.0);
});

it('pre-compresses file with multiple algorithms', function () {
    $testFile = $this->tempDir . '/test.html';
    file_put_contents($testFile, '<html>test</html>');
    
    $compressor = new FileCompressor();
    $results = $compressor->precompressFile($testFile, [
        new BrotliCompressor(),
        new GzipCompressor(),
    ]);
    
    expect($results)->toHaveCount(2);
    expect(file_exists($testFile . '.br'))->toBeTrue();
    expect(file_exists($testFile . '.gz'))->toBeTrue();
})->skip(!extension_loaded('brotli'), 'Brotli extension not available');
```

Test EncodingNegotiator with various Accept-Encoding headers:

```php
// tests/Integration/EncodingNegotiatorTest.php
use Ayrunx\HttpCompression\EncodingNegotiator;
use Ayrunx\HttpCompression\BrotliCompressor;
use Ayrunx\HttpCompression\GzipCompressor;

it('selects brotli when available and preferred', function () {
    $negotiator = new EncodingNegotiator();
    
    $compressor = $negotiator->negotiate(
        'br;q=1.0, gzip;q=0.8',
        [new BrotliCompressor(), new GzipCompressor()]
    );
    
    expect($compressor)->toBeInstanceOf(BrotliCompressor::class);
})->skip(!extension_loaded('brotli'));

it('falls back to gzip when brotli unavailable', function () {
    $negotiator = new EncodingNegotiator();
    
    $compressor = $negotiator->negotiate(
        'br, gzip',
        [new GzipCompressor()] // Only gzip available
    );
    
    expect($compressor)->toBeInstanceOf(GzipCompressor::class);
});

it('respects quality values', function () {
    $negotiator = new EncodingNegotiator();
    
    $compressor = $negotiator->negotiate(
        'gzip;q=1.0, br;q=0.5',
        [new BrotliCompressor(), new GzipCompressor()]
    );
    
    expect($compressor)->toBeInstanceOf(GzipCompressor::class);
})->skip(!extension_loaded('brotli'));

it('handles wildcard with fallback', function () {
    $negotiator = new EncodingNegotiator();
    
    $compressor = $negotiator->negotiate(
        '*;q=0.1',
        [new BrotliCompressor(), new GzipCompressor()]
    );
    
    expect($compressor)->not->toBeNull();
});

it('rejects explicitly disabled encodings', function () {
    $negotiator = new EncodingNegotiator();
    
    $compressor = $negotiator->negotiate(
        'gzip;q=0, br;q=0',
        [new BrotliCompressor(), new GzipCompressor()]
    );
    
    expect($compressor)->toBeNull();
});

it('returns null when identity is preferred', function () {
    $negotiator = new EncodingNegotiator();
    
    $compressor = $negotiator->negotiate(
        'identity;q=1.0, gzip;q=0.5',
        [new GzipCompressor()]
    );
    
    expect($compressor)->toBeNull();
});
```

### Nginx Integration Tests

Test pre-compressed files are served correctly with Pest:

```php
// tests/Integration/NginxStaticCompressionTest.php

it('serves brotli version when client accepts br', function () {
    // Setup test file
    $publicDir = __DIR__ . '/../../public';
    file_put_contents("$publicDir/test.html", '<html>test</html>');
    
    $compressor = new FileCompressor();
    $compressor->compressFile("$publicDir/test.html", new BrotliCompressor());
    
    // Test nginx serves .br file
    $response = file_get_contents('http://localhost:8080/test.html', false, stream_context_create([
        'http' => ['header' => 'Accept-Encoding: br']
    ]));
    
    $headers = get_headers('http://localhost:8080/test.html', associative: true);
    
    expect($headers['Content-Encoding'])->toBe('br');
})->skip(!extension_loaded('brotli') || !getenv('NGINX_RUNNING'), 'Requires Brotli and nginx');

it('serves gzip version when client accepts gzip', function () {
    $publicDir = __DIR__ . '/../../public';
    file_put_contents("$publicDir/test.html", '<html>test</html>');
    
    $compressor = new FileCompressor();
    $compressor->compressFile("$publicDir/test.html", new GzipCompressor());
    
    $headers = get_headers('http://localhost:8080/test.html', associative: true, context: stream_context_create([
        'http' => ['header' => 'Accept-Encoding: gzip']
    ]));
    
    expect($headers['Content-Encoding'])->toBe('gzip');
})->skip(!getenv('NGINX_RUNNING'), 'Requires nginx');

it('serves original when no compression accepted', function () {
    $publicDir = __DIR__ . '/../../public';
    file_put_contents("$publicDir/test.html", '<html>test</html>');
    
    $headers = get_headers('http://localhost:8080/test.html', associative: true, context: stream_context_create([
        'http' => ['header' => 'Accept-Encoding: identity']
    ]));
    
    expect($headers)->not->toHaveKey('Content-Encoding');
})->skip(!getenv('NGINX_RUNNING'), 'Requires nginx');
```

**Running nginx tests:**
```bash
# Start nginx first
docker compose up -d nginx

# Run all tests
NGINX_RUNNING=1 ./vendor/bin/pest

# Run only nginx tests
NGINX_RUNNING=1 ./vendor/bin/pest --group=nginx
```

### Pest Configuration

Create `tests/Pest.php` configuration file:

```php
<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\CompressionException;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses()->beforeEach(function () {
    // Global setup before each test
})->in('Unit', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeCompressed', function () {
    $data = $this->value;
    
    // Check if data looks like compressed data (binary, non-printable)
    return expect(strlen($data))->toBeGreaterThan(0)
        ->and(ctype_print($data))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createZipBomb(): string
{
    // Create a simple zip bomb for testing
    $data = str_repeat('A', 1000000); // 1MB of repeated character
    $compressor = new \Ayrunx\HttpCompression\GzipCompressor();
    return $compressor->compress($data);
}
```

### Test Structure

```
tests/
├── Pest.php                          # Pest configuration
├── Unit/
│   ├── GzipCompressorTest.php
│   ├── BrotliCompressorTest.php
│   ├── DeterministicCompressionTest.php
│   ├── FuzzTest.php
│   └── EncodingNegotiatorTest.php
├── Integration/
│   ├── FileCompressorTest.php
│   ├── EncodingNegotiatorTest.php
│   └── NginxStaticCompressionTest.php
└── fixtures/
    ├── test-data.txt
    └── test-data.txt.gz              # Golden file for deterministic tests
```

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/GzipCompressorTest.php

# Run tests by group
./vendor/bin/pest --group=slow

# Run with coverage
./vendor/bin/pest --coverage

# Run in parallel (faster)
./vendor/bin/pest --parallel

# Watch mode (re-run on file change)
./vendor/bin/pest --watch
```

### Performance Benchmarks
Create a benchmark suite with typical content:

| File Type | Size   | Gzip Level 6 | Gzip Level 9 | Brotli Level 6 | Brotli Level 11 |
|-----------|--------|--------------|--------------|----------------|-----------------|
| HTML      | 100 KB | 2ms / 18 KB  | 5ms / 15 KB  | 8ms / 14 KB    | 25ms / 12 KB    |
| JSON      | 100 KB | 1ms / 12 KB  | 3ms / 10 KB  | 6ms / 9 KB     | 20ms / 8 KB     |
| CSS       | 100 KB | 2ms / 20 KB  | 5ms / 18 KB  | 9ms / 16 KB    | 28ms / 15 KB    |

Document compression ratios and timing for different levels.

## Design Patterns

### Strategy Pattern
Each compressor implements `CompressorInterface`, allowing runtime selection of compression algorithm.

### Dependency Injection
The library accepts compressor instances via constructor or method parameters. The consuming application controls which algorithms to use.

### Immutability
All compressor classes are `readonly` to prevent accidental state mutations.

### Explicit Configuration
No magic defaults or hidden behavior. Every parameter must be explicitly set or uses documented defaults.

## Security Considerations

### 1. Zip Bomb Protection
Always set `maxSize` when decompressing untrusted data:

```php
$compressor = new GzipCompressor();

try {
    // Limit decompressed output to 10MB
    $data = $compressor->decompress($untrustedInput, maxSize: 10 * 1024 * 1024);
} catch (CompressionException $e) {
    // Handle zip bomb attack
    error_log("Possible zip bomb detected: {$e->getMessage()}");
}
```

A zip bomb is a small compressed file that expands to enormous size:
- Input: 10 KB compressed
- Output: 10 GB decompressed
- Without limit: server runs out of memory

### 2. CLI Timeouts and Worker Limits
For batch compression operations:

```php
class BatchCompressor
{
    public function compressDirectory(string $dir, int $maxWorkers = 4, int $timeout = 300): void
    {
        set_time_limit($timeout);
        
        $files = iterator_to_array(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            )
        );
        
        // Limit number of concurrent workers
        $chunks = array_chunk($files, ceil(count($files) / $maxWorkers));
        
        foreach ($chunks as $chunk) {
            // Process chunk...
        }
    }
}
```

### 3. Input Validation
Always validate input before compression:

```php
// Validate data is not empty
if (empty($data)) {
    throw new CompressionException("Cannot compress empty data");
}

// Validate compression level
if ($level < 1 || $level > 11) {
    throw new CompressionException("Invalid compression level: {$level}");
}
```

### 4. Input Size Limits
Limit input size for on-the-fly compression:

```php
const MAX_COMPRESS_SIZE = 10 * 1024 * 1024; // 10MB

if (strlen($data) > MAX_COMPRESS_SIZE) {
    throw new CompressionException("Data too large to compress");
}
```

For files larger than 10MB, consider:
- Streaming compression (future feature)
- Background processing
- Pre-compression during build/deploy

## Non-Goals (What NOT to include)

- ❌ HTTP middleware implementations
- ❌ Framework-specific adapters (unless in separate namespace)
- ❌ File archiving (tar, zip) functionality
- ❌ Streaming compression (future v2.0)
- ❌ Automatic compression level selection (application decides)
- ❌ Cache management or TTL handling
- ❌ Response/Request object handling

## Performance: Why NOT Fibers for Compression

### ❌ Fibers Do NOT Speed Up CPU-Bound Tasks

**Important:** PHP Fibers provide **cooperative multitasking**, not parallelism. They allow switching between tasks, but still execute **one at a time** in a single thread.

#### When Fibers Help
✅ I/O-bound operations (network, disk, database)
✅ Coordinating multiple async operations
✅ Waiting for external resources

#### When Fibers DON'T Help
❌ CPU-intensive work (compression, encryption, hashing)
❌ Pure computation with no waiting

### Compression is CPU-Bound

Brotli/Gzip compression is **pure CPU work** with no I/O waiting:

```php
// ❌ BAD: Fibers don't help here
$fiber1 = new Fiber(fn() => $compressor->compress($data1)); // CPU work
$fiber2 = new Fiber(fn() => $compressor->compress($data2)); // CPU work

$fiber1->start(); // Blocks CPU
$fiber2->start(); // Still waiting for fiber1 to finish
// Total time: time1 + time2 (no speedup!)
```

### For Real Parallelism: Use Process Pool

If you need to compress multiple files in parallel:

```php
// ✅ GOOD: Actual parallelism with multiple processes
use Symfony\Component\Process\Process;

class ParallelCompressor
{
    public function compressFiles(array $files, int $maxWorkers = 4): void
    {
        $processes = [];
        
        foreach (array_chunk($files, $maxWorkers) as $batch) {
            foreach ($batch as $file) {
                $process = new Process(['php', 'compress.php', $file]);
                $process->start();
                $processes[] = $process;
            }
            
            // Wait for batch to complete
            foreach ($processes as $process) {
                $process->wait();
            }
            
            $processes = [];
        }
    }
}
```

Or use `pcntl_fork()` for more control (Unix only):

```php
class ForkCompressor
{
    public function compressFiles(array $files, int $workers = 4): void
    {
        $chunks = array_chunk($files, ceil(count($files) / $workers));
        $pids = [];
        
        foreach ($chunks as $chunk) {
            $pid = pcntl_fork();
            
            if ($pid === -1) {
                throw new \RuntimeException('Fork failed');
            } elseif ($pid === 0) {
                // Child process
                $this->compressChunk($chunk);
                exit(0);
            } else {
                // Parent process
                $pids[] = $pid;
            }
        }
        
        // Wait for all workers
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }
}
```

### Summary: Concurrency Strategy

| Task Type | Best Approach |
|-----------|---------------|
| Single file compression | Synchronous (no overhead) |
| Multiple files (1-10) | Synchronous (fast enough) |
| Batch compression (100+) | Process pool (true parallelism) |
| Mixed I/O + compression | Fibers to coordinate, processes to compress |

**Don't use Fibers for pure CPU work** - they add complexity without benefits.

## Future Enhancements (v2.0+)

### Streaming Compression
For very large files, support streaming to avoid loading entire file into memory.

```php
interface StreamingCompressorInterface extends CompressorInterface
{
    /**
     * Compress stream chunk-by-chunk
     * 
     * @param resource $input Input stream
     * @param resource $output Output stream
     * @param int|null $level Compression level
     * @return int Bytes written
     */
    public function compressStream($input, $output, ?int $level = null): int;
}
```

Use case: Compressing 1GB+ files without loading into memory.

### Compression Metrics Collection

```php
final readonly class CompressionMetrics
{
    public function __construct(
        public int $filesProcessed,
        public int $filesSkipped,
        public int $totalOriginalSize,
        public int $totalCompressedSize,
        public float $totalCompressionTime,
    ) {}
    
    public function averageRatio(): float
    {
        return $this->totalCompressedSize / $this->totalOriginalSize;
    }
    
    public function averageSpeed(): float
    {
        return $this->totalOriginalSize / $this->totalCompressionTime; // bytes/sec
    }
}
```

### Adaptive Compression
Automatically choose compression level based on file size and content type.

```php
final readonly class AdaptiveCompressor
{
    /**
     * Automatically select best compression strategy
     * 
     * Rules:
     * - Small files (< 10KB): Skip compression (overhead not worth it)
     * - Medium files (10KB - 1MB): Brotli level 6 (balanced)
     * - Large files (> 1MB): Brotli level 11 (best compression)
     * - Already compressed (JPEG, PNG, WebP): Skip
     */
    public function compress(string $data, string $mimeType): CompressionResult;
}
```

## Deterministic Compression for CI/CD

### Why Determinism Matters

By default, gzip includes a **timestamp** (mtime) in the compressed file header. This means compressing the same file twice produces **different output**:

```php
$compressor = new GzipCompressor(deterministic: false);

$compressed1 = $compressor->compress('same data');
sleep(1);
$compressed2 = $compressor->compress('same data');

// ❌ Different bytes due to embedded timestamp!
assert($compressed1 !== $compressed2);
```

This breaks:
- **Content-addressable storage** (hash-based caching)
- **Reproducible builds** (CI checksums fail)
- **Artifact verification** (Docker layer caching breaks)

### Enabling Deterministic Mode

```php
$compressor = new GzipCompressor(
    level: 9,
    deterministic: true  // Zero mtime, fixed headers
);

$compressed1 = $compressor->compress('same data');
$compressed2 = $compressor->compress('same data');

// ✅ Byte-identical output!
assert($compressed1 === $compressed2);
```

### Use Cases

#### 1. CI/CD Artifact Verification
```yaml
# .gitlab-ci.yml
build:
  script:
    - php compress.php --deterministic
    - sha256sum build/*.gz > checksums.txt
    
verify:
  script:
    - php compress.php --deterministic
    - sha256sum -c checksums.txt  # Must match exactly
```

#### 2. Docker Layer Caching
```dockerfile
FROM php:8.4

# Pre-compress assets with deterministic mode
COPY compress.php /usr/local/bin/
RUN find /var/www -name "*.html" -exec compress.php --deterministic {} \;

# ✅ This layer will be cached if files haven't changed
# ❌ Without deterministic mode, layer cache would always miss
```

#### 3. Content-Addressed Storage
```php
// Store compressed files by content hash
$compressed = $compressor->compress($data);
$hash = hash('sha256', $compressed);

// Same content always produces same hash
$cachePath = "/cache/{$hash}.gz";
if (!file_exists($cachePath)) {
    file_put_contents($cachePath, $compressed);
}
```

### Implementation Details

Deterministic gzip sets:
- **mtime** = 0 (instead of current timestamp)
- **OS flag** = 255 (unknown OS, instead of actual OS)
- **Extra flags** = 0 (no extra metadata)

This matches `gzip --no-name` behavior:
```bash
# Standard gzip (non-deterministic)
gzip file.txt

# Deterministic gzip
gzip --no-name file.txt
# OR
gzip -n file.txt
```

### Brotli Determinism

Good news: **Brotli is deterministic by default!** It doesn't include timestamps or metadata in compressed output.

```php
$compressor = new BrotliCompressor(level: 11);

// ✅ Always produces identical output
$compressed1 = $compressor->compress('data');
$compressed2 = $compressor->compress('data');
assert($compressed1 === $compressed2);
```

### Recommendation

For CI/CD and reproducible builds:
```php
// Always use deterministic mode for gzip
$gzip = new GzipCompressor(level: 9, deterministic: true);

// Brotli is naturally deterministic
$brotli = new BrotliCompressor(level: 11);
```

## Summary

This is a **pure, focused library** that does one thing well: compress data. It provides the **tools and metadata**, while the application provides the **strategy** for when and how to use them.

**Key Principles:**
1. **Single Responsibility** - only compression (Gzip, Brotli)
2. **Framework-agnostic** - works anywhere, no HTTP dependencies
3. **Metadata provider** - compressor gives you `getEncoding()`, `getExtension()`, application sets headers
4. **Explicit over implicit** - no magic, no auto-header setting
5. **Composable** - mix and match compressors
6. **Type-safe** - full PHP 8.4 type hints
7. **Immutable** - readonly classes
8. **Deterministic builds** - reproducible compression for CI/CD
9. **Security-first** - zip bomb protection, input validation
10. **Performance-aware** - know when NOT to use Fibers

**What the compressor does:**
- ✅ Compress bytes → compressed bytes
- ✅ Provide metadata (`getEncoding()`, `getExtension()`)
- ✅ Validate compression levels
- ✅ Check extension availability (`supports()`)
- ✅ Protect against zip bombs

**What the compressor does NOT do:**
- ❌ Send HTTP headers
- ❌ Make HTTP decisions
- ❌ Know about Request/Response objects
- ❌ Implement middleware patterns
- ❌ Manage caching or TTL
