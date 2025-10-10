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
use Aurynx\HttpCompression\CompressorInterface;
use Aurynx\HttpCompression\AlgorithmEnum;
use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\ErrorCode;

class LZ4Compressor implements CompressorInterface
{
    public function compress(string $content, ?int $level = null): string
    {
        if (!extension_loaded('lz4')) {
            throw new CompressionException(
                'LZ4 extension not available',
                ErrorCode::ALGORITHM_UNAVAILABLE->value
            );
        }
        
        $compressed = lz4_compress($content, $level ?? 3);
        
        if ($compressed === false) {
            throw new CompressionException(
                'LZ4 compression failed',
                ErrorCode::COMPRESSION_FAILED->value
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
                ErrorCode::COMPRESSION_FAILED->value
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
use PHPUnit\Framework\TestCase;
use Aurynx\HttpCompression\CompressorInterface;
use Aurynx\HttpCompression\AlgorithmEnum;

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
                ErrorCode::COMPRESSION_FAILED->value
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
