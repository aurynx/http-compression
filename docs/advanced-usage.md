# Advanced Usage

## Direct Compressor Access

For simple use cases where you need to compress a single payload with one algorithm (e.g., in middleware, HTTP handlers, or queue jobs), you can use the `CompressorInterface` directly without the builder:

```php
use Aurynx\HttpCompression\CompressorFactory;
use Aurynx\HttpCompression\AlgorithmEnum;

// Create a compressor for a specific algorithm
$compressor = CompressorFactory::create(AlgorithmEnum::Gzip);

// Compress data
$compressed = $compressor->compress('Hello, World!', level: 6);

// Decompress data
$original = $compressor->decompress($compressed);

// Get algorithm info
$algorithm = $compressor->getAlgorithm(); // AlgorithmEnum::Gzip
```

**When to use direct access:**
- Single algorithm compression in HTTP middleware
- Stream processing or worker queues
- Simple utility scripts
- Testing individual algorithm behavior

**When to use CompressionBuilder:**
- Multiple algorithms or payloads
- Batch operations
- Error handling strategies (fail-fast vs graceful)
- Complex configuration with identifiers and per-item settings

## HTTP Middleware Example

Direct compressor usage in a PSR-15 style middleware:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Aurynx\HttpCompression\CompressorFactory;
use Aurynx\HttpCompression\AlgorithmEnum;

class CompressionMiddleware implements MiddlewareInterface
{
    private const MIN_LENGTH = 1024; // Only compress responses > 1KB

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        
        // Get Accept-Encoding header
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding');
        
        // Determine best algorithm
        $algorithm = $this->negotiateAlgorithm($acceptEncoding);
        
        if (!$algorithm) {
            return $response; // No compression
        }
        
        $body = (string) $response->getBody();
        
        // Skip small responses
        if (strlen($body) < self::MIN_LENGTH) {
            return $response;
        }
        
        try {
            $compressor = CompressorFactory::create($algorithm);
            $compressed = $compressor->compress($body, level: 6);
            
            $response->getBody()->rewind();
            $response->getBody()->write($compressed);
            
            return $response
                ->withHeader('Content-Encoding', $algorithm->value)
                ->withHeader('Vary', 'Accept-Encoding')
                ->withoutHeader('Content-Length'); // Let the server recalculate
        } catch (\Exception $e) {
            // Log error and return uncompressed response
            error_log("Compression failed: " . $e->getMessage());
            return $response;
        }
    }
    
    private function negotiateAlgorithm(string $acceptEncoding): ?AlgorithmEnum
    {
        // Prefer brotli, then zstd, then gzip
        if (str_contains($acceptEncoding, 'br') && AlgorithmEnum::Brotli->isAvailable()) {
            return AlgorithmEnum::Brotli;
        }
        
        if (str_contains($acceptEncoding, 'zstd') && AlgorithmEnum::Zstd->isAvailable()) {
            return AlgorithmEnum::Zstd;
        }
        
        if (str_contains($acceptEncoding, 'gzip') && AlgorithmEnum::Gzip->isAvailable()) {
            return AlgorithmEnum::Gzip;
        }
        
        return null;
    }
}
```

## Custom Compressor Implementation

You can implement `CompressorInterface` to add support for custom algorithms or wrappers:

```php
use Aurynx\HttpCompression\AlgorithmEnum;use Aurynx\HttpCompression\CompressionException;use Aurynx\HttpCompression\Contracts\CompressorInterface;use Aurynx\HttpCompression\ErrorCodeEnum;

class LZ4Compressor implements CompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        if (!extension_loaded('lz4')) {
            throw new CompressionException(
                'LZ4 extension not available',
                ErrorCodeEnum::ALGORITHM_UNAVAILABLE->value
            );
        }
        
        $compressed = lz4_compress($content, $level ?? 3);
        
        if ($compressed === false) {
            throw new CompressionException(
                'LZ4 compression failed',
                ErrorCodeEnum::COMPRESSION_FAILED->value
            );
        }
        
        return $compressed;
    }
    
    public function decompress(string $content): string
    {
        $decompressed = lz4_uncompress($content);
        
        if ($decompressed === false) {
            throw new CompressionException(
                'LZ4 decompression failed',
                ErrorCodeEnum::COMPRESSION_FAILED->value
            );
        }
        
        return $decompressed;
    }
    
    public function getAlgorithm(): AlgorithmEnum
    {
        // For custom algorithms, return the closest match
        // or extend AlgorithmEnum in your application
        return AlgorithmEnum::Gzip;
    }
}

// Usage
$compressor = new LZ4Compressor();
$compressed = $compressor->compress('test data', level: 5);
```

## Testing with Mock Compressors

Direct access makes it easy to mock compressor behavior in tests:

```php
use Aurynx\HttpCompression\AlgorithmEnum;use Aurynx\HttpCompression\Contracts\CompressorInterface;use PHPUnit\Framework\TestCase;

class CompressionServiceTest extends TestCase
{
    public function testCompressionWithMock(): void
    {
        // Create a mock compressor
        $mockCompressor = $this->createMock(CompressorInterface::class);
        
        $mockCompressor->expects($this->once())
            ->method('compress')
            ->with('test data', 6)
            ->willReturn('compressed-data');
        
        $mockCompressor->method('getAlgorithm')
            ->willReturn(AlgorithmEnum::Gzip);
        
        // Inject into your service
        $service = new CompressionService($mockCompressor);
        $result = $service->processData('test data');
        
        $this->assertEquals('compressed-data', $result);
    }
    
    public function testFailureScenario(): void
    {
        $mockCompressor = $this->createMock(CompressorInterface::class);
        
        $mockCompressor->method('compress')
            ->willThrowException(new CompressionException(
                'Simulated failure',
                ErrorCodeEnum::COMPRESSION_FAILED->value
            ));
        
        $service = new CompressionService($mockCompressor);
        
        $this->expectException(CompressionException::class);
        $service->processData('test data');
    }
}
```

## Stream Processing

For large files or streaming scenarios, read and compress in chunks:

```php
use Aurynx\HttpCompression\CompressorFactory;
use Aurynx\HttpCompression\AlgorithmEnum;

function compressLargeFile(string $inputPath, string $outputPath): void
{
    $compressor = CompressorFactory::create(AlgorithmEnum::Gzip);
    
    $chunkSize = 8192; // 8KB chunks
    $buffer = '';
    
    $input = fopen($inputPath, 'rb');
    $output = fopen($outputPath, 'wb');
    
    while (!feof($input)) {
        $chunk = fread($input, $chunkSize);
        $buffer .= $chunk;
        
        // Compress when buffer reaches certain size
        if (strlen($buffer) >= 65536) { // 64KB
            $compressed = $compressor->compress($buffer, level: 9);
            fwrite($output, $compressed);
            $buffer = '';
        }
    }
    
    // Compress remaining data
    if ($buffer !== '') {
        $compressed = $compressor->compress($buffer, level: 9);
        fwrite($output, $compressed);
    }
    
    fclose($input);
    fclose($output);
}
```

**Note:** For true streaming compression, consider using PHP's native stream filters like `zlib.deflate` or implementing `StreamCompressorInterface` (if you need to extend the library).

## Checking Available Algorithms

Before using specific algorithms, you can check which compression extensions are available on the current system:

```php
use Aurynx\HttpCompression\AlgorithmEnum;

// Get all available algorithms
$available = AlgorithmEnum::available();

foreach ($available as $algo) {
    echo "{$algo->value} is available (extension: {$algo->getRequiredExtension()})\n";
}

// Example output:
// gzip is available (extension: zlib)
// br is available (extension: brotli)
// zstd is available (extension: zstd)
```

### Use Case: Dynamic Fallback Selection

Choose the best available algorithm based on what's installed:

```php
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressorFactory;

function getBestCompressor(): CompressorInterface
{
    $available = AlgorithmEnum::available();
    
    // Prefer brotli, then zstd, then gzip
    $preference = [AlgorithmEnum::Brotli, AlgorithmEnum::Zstd, AlgorithmEnum::Gzip];
    
    foreach ($preference as $algo) {
        if (in_array($algo, $available, true)) {
            return CompressorFactory::create($algo);
        }
    }
    
    throw new RuntimeException('No compression algorithm available');
}

$compressor = getBestCompressor();
$compressed = $compressor->compress($data);
```

### Use Case: Build-Time Validation

Validate that required extensions are installed before running compression tasks:

```php
use Aurynx\HttpCompression\AlgorithmEnum;

// In your build script or deployment check
$required = [AlgorithmEnum::Gzip, AlgorithmEnum::Brotli];
$available = AlgorithmEnum::available();

$missing = array_filter(
    $required,
    fn($algo) => !in_array($algo, $available, true)
);

if ($missing) {
    $names = array_map(fn($a) => $a->value, $missing);
    throw new RuntimeException(
        'Missing required compression extensions: ' . implode(', ', $names)
    );
}

echo "✓ All required compression extensions are available\n";
```

### Use Case: Compress with All Available Algorithms

Automatically use all available algorithms without hardcoding:

```php
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;

$builder = new CompressionBuilder();
$available = AlgorithmEnum::available();

// Build algorithm map with default levels
$algorithms = [];
foreach ($available as $algo) {
    $algorithms[$algo->value] = $algo->getDefaultLevel();
}

$builder->add($content, $algorithms);
$result = $builder->compress()[0];

// Now compressed with all available algorithms
foreach ($result->getCompressed() as $algo => $compressed) {
    echo "Compressed with $algo: " . strlen($compressed) . " bytes\n";
}
```

## Using Compression Metrics

The library automatically collects metrics about compression efficiency. You can access these metrics to:
- Verify that compression actually reduces size
- Log compression statistics
- Debug compression issues
- Choose optimal algorithms

### Basic Metrics Usage

```php
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

$content = file_get_contents('large-file.json');

$builder = new CompressionBuilder();
$builder->add($content, AlgorithmEnum::Gzip);
$id = $builder->getLastIdentifier();
$results = $builder->compress();
$result = $results[$id];

// Get sizes
$originalSize = $result->getOriginalSize();
$compressedSize = $result->getCompressedSize(AlgorithmEnum::Gzip);
$saved = $result->getSavedBytes(AlgorithmEnum::Gzip);
$percentage = $result->getCompressionPercentage(AlgorithmEnum::Gzip);

echo "Original: {$originalSize} bytes\n";
echo "Compressed: {$compressedSize} bytes\n";
echo "Saved: {$saved} bytes ({$percentage}% reduction)\n";

// Check if compression was effective
if ($result->isEffective(AlgorithmEnum::Gzip)) {
    echo "✓ Compression was effective\n";
} else {
    echo "✗ Compression increased size (not effective)\n";
}
```

### Logging Compression Statistics

```php
use Psr\Log\LoggerInterface;

function compressAndLog(string $content, LoggerInterface $logger): string
{
    $builder = new CompressionBuilder();
    $builder->add($content, AlgorithmEnum::Gzip);
    $id = $builder->getLastIdentifier();
    $results = $builder->compress();
    $result = $results[$id];
    
    if ($result->isOk()) {
        $logger->info('Compression successful', [
            'original_bytes' => $result->getOriginalSize(),
            'compressed_bytes' => $result->getCompressedSize(AlgorithmEnum::Gzip),
            'saved_bytes' => $result->getSavedBytes(AlgorithmEnum::Gzip),
            'reduction_percent' => $result->getCompressionPercentage(AlgorithmEnum::Gzip),
            'ratio' => $result->getCompressionRatio(AlgorithmEnum::Gzip),
        ]);
        
        return $result->getCompressedFor(AlgorithmEnum::Gzip);
    }
    
    $logger->error('Compression failed', ['error' => $result->getError()->getMessage()]);
    return $content;
}
```

### Comparing Algorithm Efficiency

```php
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

$content = str_repeat('Sample data for testing. ', 1000);

$builder = new CompressionBuilder();
$builder->add($content, [
    AlgorithmEnum::Gzip->value => 6,
    AlgorithmEnum::Brotli->value => 4,
    AlgorithmEnum::Zstd->value => 3,
]);

$id = $builder->getLastIdentifier();
$results = $builder->compress();
$result = $results[$id];

echo "Compression comparison:\n";
foreach ([AlgorithmEnum::Gzip, AlgorithmEnum::Brotli, AlgorithmEnum::Zstd] as $algo) {
    if (!$result->hasAlgorithm($algo)) {
        continue;
    }
    
    $size = $result->getCompressedSize($algo);
    $percentage = $result->getCompressionPercentage($algo);
    
    echo sprintf("  %s: %d bytes (%.1f%% reduction)\n", 
        $algo->value, $size, $percentage);
}

// Output example:
// Compression comparison:
//   gzip: 142 bytes (94.3% reduction)
//   br: 118 bytes (95.3% reduction)
//   zstd: 135 bytes (94.6% reduction)
```

### Asserting Compression Effectiveness in Tests

```php
use PHPUnit\Framework\TestCase;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

class CompressionTest extends TestCase
{
    public function testCompressionReducesSize(): void
    {
        $content = str_repeat('Repetitive data. ', 100);
        
        $builder = new CompressionBuilder();
        $builder->add($content, AlgorithmEnum::Gzip);
        $id = $builder->getLastIdentifier();
        $results = $builder->compress();
        $result = $results[$id];
        
        // Assert compression was effective
        $this->assertTrue($result->isEffective(AlgorithmEnum::Gzip));
        
        // Assert at least 50% compression
        $percentage = $result->getCompressionPercentage(AlgorithmEnum::Gzip);
        $this->assertGreaterThan(50.0, $percentage);
        
        // Assert compressed size is less than original
        $this->assertLessThan(
            $result->getOriginalSize(),
            $result->getCompressedSize(AlgorithmEnum::Gzip)
        );
    }
    
    public function testHighlyCompressibleData(): void
    {
        $content = str_repeat('A', 10000);
        
        $builder = new CompressionBuilder();
        $builder->add($content, AlgorithmEnum::Gzip);
        $id = $builder->getLastIdentifier();
        $results = $builder->compress();
        $result = $results[$id];
        
        // Highly repetitive data should achieve >90% compression
        $percentage = $result->getCompressionPercentage(AlgorithmEnum::Gzip);
        $this->assertGreaterThan(90.0, $percentage);
    }
}
```

### Conditional Response Compression

Only compress if it's actually beneficial:

```php
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

function smartCompress(string $content, string $acceptEncoding): array
{
    // Determine algorithm from Accept-Encoding
    $algorithm = str_contains($acceptEncoding, 'br') 
        ? AlgorithmEnum::Brotli 
        : AlgorithmEnum::Gzip;
    
    $builder = new CompressionBuilder();
    $builder->add($content, $algorithm);
    $id = $builder->getLastIdentifier();
    $results = $builder->compress();
    $result = $results[$id];
    
    if (!$result->isOk()) {
        return ['content' => $content, 'encoding' => 'identity'];
    }
    
    // Only use compression if it saves at least 10% (avoids overhead for small gains)
    $percentage = $result->getCompressionPercentage($algorithm);
    
    if ($percentage >= 10.0) {
        return [
            'content' => $result->getCompressedFor($algorithm),
            'encoding' => $algorithm->value,
            'saved_bytes' => $result->getSavedBytes($algorithm),
        ];
    }
    
    // Not worth compressing
    return ['content' => $content, 'encoding' => 'identity'];
}
```

## Batch Compression Statistics

When compressing multiple files or content items, use `CompressionStatsDto` to get aggregated metrics across the entire batch:

### Basic Batch Statistics

```php
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionStatsDto;

$publicDir = __DIR__ . '/public';
$assets = glob("$publicDir/**/*.{js,css,html}", GLOB_BRACE);

$builder = new CompressionBuilder();
$builder->addManyFiles($assets, [
    AlgorithmEnum::Gzip->value => 9,
    AlgorithmEnum::Brotli->value => 11,
]);

$results = $builder->compress();
$stats = CompressionStatsDto::fromResults($results);

// Print summary
echo $stats->summary();

// Output:
// Compression Statistics:
//   Total items: 45
//   Successful: 45
//   Original size: 2.34 MB
//   gzip: 512.45 KB (saved 1.85 MB, 78.9% reduction)
//   br: 398.12 KB (saved 1.96 MB, 83.1% reduction)
```

### Logging Batch Compression Results

```php
use Psr\Log\LoggerInterface;

function compressAndLogBatch(array $files, LoggerInterface $logger): void
{
    $builder = new CompressionBuilder();
    $builder->addManyFiles($files, AlgorithmEnum::Gzip);
    
    $results = $builder->compress();
    $stats = CompressionStatsDto::fromResults($results);
    
    $logger->info('Batch compression completed', [
        'total_items' => $stats->getTotalItems(),
        'successful' => $stats->getSuccessfulItems(),
        'failed' => $stats->getFailedItems(),
        'success_rate' => $stats->getSuccessRate() * 100 . '%',
        'original_bytes' => $stats->getTotalOriginalBytes(),
        'compressed_bytes' => $stats->getTotalCompressedBytes(AlgorithmEnum::Gzip),
        'saved_bytes' => $stats->getTotalSavedBytes(AlgorithmEnum::Gzip),
        'average_reduction' => $stats->getAveragePercentage(AlgorithmEnum::Gzip) . '%',
    ]);
}
```

### Build-Time Statistics Report

Generate a report for precompressed static assets:

```php
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionStatsDto;

$publicDir = __DIR__ . '/public';
$assets = [
    ...glob("$publicDir/js/*.js"),
    ...glob("$publicDir/css/*.css"),
    ...glob("$publicDir/*.html"),
];

echo "Precompressing " . count($assets) . " files...\n";

$builder = new CompressionBuilder();
$builder->addManyFiles($assets, [
    AlgorithmEnum::Gzip->value => 9,
    AlgorithmEnum::Brotli->value => 11,
]);

$results = $builder->compress();

// Save compressed files
foreach ($results as $result) {
    if (!$result->isOk()) {continue;}
    
    $filePath = $result->getIdentifier();
    
    if ($gzipped = $result->getCompressedFor(AlgorithmEnum::Gzip)) {
        file_put_contents($filePath . '.gz', $gzipped);
    }
    
    if ($brotli = $result->getCompressedFor(AlgorithmEnum::Brotli)) {
        file_put_contents($filePath . '.br', $brotli);
    }
}

// Generate statistics report
$stats = CompressionStatsDto::fromResults($results);

echo "\n" . $stats->summary() . "\n";

if ($stats->getFailedItems() > 0) {
    echo "\n⚠️  Warning: {$stats->getFailedItems()} files failed to compress\n";
    exit(1);
}

echo "\n✓ All files compressed successfully!\n";
```

### Comparing Algorithm Efficiency Across Batch

```php
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionStatsDto;

$files = glob(__DIR__ . '/public/**/*.js');

$builder = new CompressionBuilder();
$builder->addManyFiles($files, [
    AlgorithmEnum::Gzip->value => 6,
    AlgorithmEnum::Brotli->value => 4,
    AlgorithmEnum::Zstd->value => 3,
]);

$results = $builder->compress();
$stats = CompressionStatsDto::fromResults($results);

echo "Algorithm comparison for " . $stats->getTotalItems() . " files:\n\n";

foreach ([AlgorithmEnum::Gzip, AlgorithmEnum::Brotli, AlgorithmEnum::Zstd] as $algo) {
    if (!$stats->hasAlgorithm($algo)) {
        continue;
    }
    
    $compressed = $stats->getTotalCompressedBytes($algo);
    $saved = $stats->getTotalSavedBytes($algo);
    $avgPercentage = $stats->getAveragePercentage($algo);
    
    echo sprintf(
        "%s:\n  Total: %s\n  Saved: %s\n  Average reduction: %.1f%%\n\n",
        $algo->value,
        formatBytes($compressed),
        formatBytes($saved),
        $avgPercentage
    );
}

// Helper function
function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
```

### Conditional Batch Processing

Only proceed if compression is effective for the batch:

```php
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionStatsDto;

$files = glob(__DIR__ . '/data/*.json');

$builder = new CompressionBuilder();
$builder->addManyFiles($files, AlgorithmEnum::Gzip);
$results = $builder->compress();
$stats = CompressionStatsDto::fromResults($results);

// Only deploy compressed versions if average compression is >20%
if ($stats->getAveragePercentage(AlgorithmEnum::Gzip) >= 20.0) {
    echo "✓ Compression effective, deploying compressed files\n";
    
    foreach ($results as $result) {
        if ($result->isOk()) {
            $filePath = $result->getIdentifier();
            $compressed = $result->getCompressedFor(AlgorithmEnum::Gzip);
            file_put_contents($filePath . '.gz', $compressed);
        }
    }
} else {
    echo "⚠️  Average compression <20%, skipping deployment\n";
    echo "Original size: " . $stats->getTotalOriginalBytes() . " bytes\n";
    echo "Compressed size: " . $stats->getTotalCompressedBytes(AlgorithmEnum::Gzip) . " bytes\n";
}
```

### CI/CD Pipeline Integration

Use batch statistics in CI/CD to ensure compression targets are met:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\DTO\CompressionStatsDto;

$publicDir = __DIR__ . '/public';
$assets = glob("$publicDir/**/*.{js,css}", GLOB_BRACE);

$builder = new CompressionBuilder();
$builder->addManyFiles($assets, AlgorithmEnum::Brotli);
$results = $builder->compress();
$stats = CompressionStatsDto::fromResults($results);

echo $stats->summary() . "\n\n";

// Validate compression targets
$failures = [];

if ($stats->getSuccessRate() < 1.0) {
    $failures[] = "Not all files compressed successfully";
}

$avgReduction = $stats->getAveragePercentage(AlgorithmEnum::Brotli);
if ($avgReduction < 30.0) {
    $failures[] = sprintf(
        "Average compression (%.1f%%) below target (30%%)",
        $avgReduction
    );
}

$totalSaved = $stats->getTotalSavedBytes(AlgorithmEnum::Brotli);
$minSaved = 100 * 1024; // 100KB minimum
if ($totalSaved < $minSaved) {
    $failures[] = sprintf(
        "Total saved (%d bytes) below minimum (%d bytes)",
        $totalSaved,
        $minSaved
    );
}

if (!empty($failures)) {
    echo "❌ Compression validation failed:\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "✅ Compression targets met!\n";
exit(0);
```

## Algorithm Performance Comparison

Benchmark different algorithms to choose the best for your use case:

```php
use Aurynx\HttpCompression\CompressorFactory;
use Aurynx\HttpCompression\AlgorithmEnum;

function benchmarkAlgorithms(string $data): array
{
    $results = [];
    
    foreach ([AlgorithmEnum::Gzip, AlgorithmEnum::Brotli, AlgorithmEnum::Zstd] as $algo) {
        if (!$algo->isAvailable()) {
            continue;
        }
        
        $compressor = CompressorFactory::create($algo);
        
        $start = microtime(true);
        $compressed = $compressor->compress($data, $algo->getDefaultLevel());
        $compressionTime = microtime(true) - $start;
        
        $start = microtime(true);
        $decompressed = $compressor->decompress($compressed);
        $decompressionTime = microtime(true) - $start;
        
        $results[$algo->value] = [
            'original_size' => strlen($data),
            'compressed_size' => strlen($compressed),
            'ratio' => round(strlen($compressed) / strlen($data) * 100, 2),
            'compression_time' => round($compressionTime * 1000, 2) . 'ms',
            'decompression_time' => round($decompressionTime * 1000, 2) . 'ms',
        ];
    }
    
    return $results;
}

// Usage
$testData = str_repeat('Lorem ipsum dolor sit amet... ', 1000);
$results = benchmarkAlgorithms($testData);

print_r($results);
// Example output:
// [
//     'gzip' => ['compressed_size' => 1234, 'ratio' => 12.34, ...],
//     'br' => ['compressed_size' => 987, 'ratio' => 9.87, ...],
//     'zstd' => ['compressed_size' => 1050, 'ratio' => 10.50, ...]
// ]
```
