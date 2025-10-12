# Usage Examples

## Static Asset Precompression for Nginx

Generate precompressed files for nginx's `gzip_static` and `brotli_static` using the directory output mode:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$ok = CompressorFacade::make()
    // Portable patterns (no GLOB_BRACE)
    ->addGlob(__DIR__ . '/public/**/*.js')
    ->addGlob(__DIR__ . '/public/**/*.css')
    ->addGlob(__DIR__ . '/public/**/*.html')
    ->addGlob(__DIR__ . '/public/**/*.svg')
    ->addGlob(__DIR__ . '/public/**/*.json')
    ->withDefaultConfig(
        ItemConfig::create()
            ->withGzip(9)
            ->withBrotli(11)
            ->build()
    )
    ->skipAlreadyCompressed()
    ->toDir(__DIR__ . '/public', keepStructure: true)
    ->compress()
    ->allOk();

if (!$ok) {
    throw new RuntimeException('Precompression failed');
}
```

## API Response Compression

Compress JSON responses based on the client's Accept-Encoding header:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Support\AcceptEncoding;

function compressResponse(string $json, string $acceptEncoding): array
{
    // Negotiate the best acceptable algorithm among installed ones
    $algo = AcceptEncoding::negotiate($acceptEncoding, ...AlgorithmEnum::available());

    if ($algo === null) {
        return ['content' => $json, 'encoding' => 'identity'];
    }

    $result = CompressorFacade::once()
        ->data($json)
        ->withAlgorithm($algo, $algo->getDefaultLevel())
        ->compress();

    return ['content' => $result->getData($algo), 'encoding' => $algo->value];
}

// Usage in your controller/handler
$json = json_encode(['users' => $users]);
$response = compressResponse($json, $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');

if ($response['encoding'] !== 'identity') {
    header('Content-Encoding: ' . $response['encoding']);
}
header('Content-Type: application/json');

echo $response['content'];
```

## Batch Processing with Defaults and Per-Item Overrides

Set default algorithms and override per item:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$compressor = CompressorFacade::make()
    ->withDefaultConfig(
        ItemConfig::create()
            ->withGzip(6)
            ->withBrotli(4)
            ->build()
    );

// Uses defaults
$compressor->addData('Regular content');

// Override for an item
$override = ItemConfig::create()
    ->withGzip(9)
    ->withBrotli(11)
    ->withZstd(3)
    ->build();

$compressor->addData('Important data', $override);

$result = $compressor->inMemory()->compress();
```

## Advanced Error Handling (graceful)

Continue on errors (e.g., missing extensions) and inspect successes/failures:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$result = CompressorFacade::make()
    ->addData('Test data 1')
    ->addData('Test data 2')
    ->withDefaultConfig(
        ItemConfig::create()
            ->withGzip(9)
            ->withBrotli(11) // may fail if ext not installed
            ->withZstd(3)    // may fail if ext not installed
            ->build()
    )
    ->failFast(false)
    ->inMemory()
    ->compress();

foreach ($result->successes() as $id => $item) {
    echo "✓ {$id} compressed\n";
}

foreach ($result->failures() as $id => $item) {
    echo "✗ {$id} failed: " . $item->getFailureReason()?->getMessage() . "\n";
}
```

## Build Script for Static Assets

Create a build script to precompress assets:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$patterns = [
    __DIR__ . '/public/**/*.js',
    __DIR__ . '/public/**/*.css',
    __DIR__ . '/public/**/*.html',
];

$compressor = CompressorFacade::make()
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->withBrotli(11)->build())
    ->skipAlreadyCompressed()
    ->toDir(__DIR__ . '/public', keepStructure: true)
    ->failFast(true);

foreach ($patterns as $pattern) {
    $compressor->addGlob($pattern);
}

$result = $compressor->compress();

if (!$result->allOk()) {
    echo "Some files failed to compress\n";
    foreach ($result->failures() as $id => $item) {
        echo " - {$id}: " . $item->getFailureReason()?->getMessage() . "\n";
    }
    exit(1);
}

echo "✓ Successfully compressed {$result->count()} files\n";
```

Make it executable:

```bash
chmod +x scripts/precompress.php
./scripts/precompress.php
```
