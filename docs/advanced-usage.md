# Advanced Usage

This page shows patterns for integrating the library in non-trivial scenarios using the current API.

- Facades: `CompressorFacade::make()` (batch), `CompressorFacade::once()` (single item)
- Value objects: `ItemConfig`, `AlgorithmSet`
- Enums: `AlgorithmEnum`
- Results: `CompressionResult`, `CompressionItemResult`, `CompressionSummaryResult`

## Direct Compressor Access (low-level)

For simple one-off cases with a single algorithm, you can use specific compressor implementations directly:

```php
use Aurynx\HttpCompression\Compressors\GzipCompressor;

$gzip = new GzipCompressor();
$compressed = $gzip->compress('Hello, World!', level: 6);
$original = $gzip->decompress($compressed);
```

Prefer the facades for most application code.

## HTTP Middleware Example (PSR-15)

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Support\AcceptEncoding;

class CompressionMiddleware implements MiddlewareInterface
{
    private const MIN_LENGTH = 1024; // Only compress responses > 1KB

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);

        $accept = $request->getHeaderLine('Accept-Encoding');
        $algo = AcceptEncoding::negotiate($accept, ...AlgorithmEnum::available());

        if ($algo === null) {
            return $response; // No compression
        }

        $body = (string) $response->getBody();
        if (strlen($body) < self::MIN_LENGTH) {
            return $response; // Skip tiny bodies
        }

        $result = CompressorFacade::once()
            ->data($body)
            ->withAlgorithm($algo, $algo->getDefaultLevel())
            ->compress();

        $compressed = $result->getData($algo);

        $response->getBody()->rewind();
        $response->getBody()->write($compressed);

        return $response
            ->withHeader('Content-Encoding', $algo->value)
            ->withHeader('Vary', 'Accept-Encoding')
            ->withoutHeader('Content-Length');
    }
}
```

## Streaming large files to disk

Use batch facade with a file input and stream output via the result reader:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

$result = CompressorFacade::make()
    ->addFile('large-input.json')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
    ->inMemory(maxBytes: 100_000_000)
    ->compress();

$item = $result->first();

// Stream compressed data in chunks to file
$fp = fopen('large-input.json.gz', 'wb');
$item->read(AlgorithmEnum::Gzip, function (string $chunk) use ($fp) {
    fwrite($fp, $chunk);
});
fclose($fp);
```

## Callback streaming (single algorithm, no resources)

Stream compressed data to a callback without dealing with stream resources:

```php
use Aurynx\HttpCompression\CompressorFacade;

$buffer = '';
CompressorFacade::once()
    ->data(str_repeat('callback ', 5000))
    ->withGzip(6)
    ->sendToCallback(function (string $chunk) use (&$buffer): void {
        $buffer .= $chunk; // or write to socket, PSR-7 body, etc.
    });

// $buffer now contains gzip-compressed data
```

## Callback streaming (multiple algorithms)

Provide per-algorithm consumers; optional algorithms may be omitted:

```php
use Aurynx\HttpCompression\CompressorFacade;

$gz = fopen('out.gz', 'wb');
$brBuffer = '';

CompressorFacade::once()
    ->data(str_repeat('many ', 4000))
    ->withGzip(6)      // required by default
    ->tryBrotli(4)     // optional
    ->sendAllToCallbacks([
        'gzip' => static function (string $chunk) use ($gz): void {
            fwrite($gz, $chunk);
        },
        // 'br' => static function (string $chunk) use (&$brBuffer): void { $brBuffer .= $chunk; },
        // brotli consumer is optional because tryBrotli() was used
    ]);

fclose($gz);
```

Notes:
- Required algorithms must have a corresponding consumer and must succeed; otherwise an exception is thrown.
- Optional algorithms (added via try*) are skipped if not provided or if they fail.
- Chunks may arrive in multiple callback invocations; their order is preserved.

## Check available algorithms

```php
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

foreach (AlgorithmEnum::available() as $algo) {
    echo $algo->value . " is available (ext: " . $algo->getRequiredExtension() . ")\n";
}
```

## Dynamic fallback selection

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Support\AcceptEncoding;

function compressBest(string $content, string $acceptHeader): string
{
    $algo = AcceptEncoding::negotiate($acceptHeader, ...AlgorithmEnum::available());

    if ($algo === null) {
        return $content; // no compression available or identity preferred
    }

    $result = CompressorFacade::once()
        ->data($content)
        ->withAlgorithm($algo, $algo->getDefaultLevel())
        ->compress();

    return $result->getData($algo);
}
```

## Batch metrics and summary

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

$result = CompressorFacade::make()
    ->addGlob('public/**/*.html')
    ->withDefaultConfig(ItemConfig::create()->withGzip(6)->withBrotli(11)->build())
    ->inMemory()
    ->compress();

$summary = $result->summary();

echo "Total: {$summary->getTotalItems()}\n";
echo "Success: {$summary->getSuccessCount()}\n";
echo "Median gzip ratio: " . $summary->getMedianRatio(AlgorithmEnum::Gzip) . "\n";
echo "P95 br time: " . $summary->getP95TimeMs(AlgorithmEnum::Brotli) . " ms\n";
```

## Build-time precompression (save to directory)

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$ok = CompressorFacade::make()
    // Portable patterns (no GLOB_BRACE)
    ->addGlob('assets/**/*.js')
    ->addGlob('assets/**/*.css')
    ->addGlob('assets/**/*.html')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->withBrotli(11)->build())
    ->skipAlreadyCompressed()
    ->toDir('public/build', keepStructure: true)
    ->compress()
    ->allOk();

if (!$ok) {
    throw new RuntimeException('Asset compression failed');
}
```

### Low-Level Note: WritableStream wrapper

If you call the engine directly, `CompressionEngine::compressItemToSinks()` accepts either native stream resources or `Support\WritableStream` instances:

```php
use Aurynx\HttpCompression\CompressionEngine;
use Aurynx\HttpCompression\Support\WritableStream;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;

$engine = new CompressionEngine(\Aurynx\HttpCompression\ValueObjects\OutputConfig::stream());
$fp = fopen('out.gz', 'wb');
$ws = WritableStream::fromResource($fp);

$engine->compressItemToSinks($input, new ItemConfig(AlgorithmSet::gzip()), [ 'gzip' => $ws ]);
```
