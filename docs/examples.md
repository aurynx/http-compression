# Usage Examples

## Static Asset Precompression for Nginx

Generate precompressed files for nginx's `gzip_static` and `brotli_static`:

```php
use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

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

## API Response Compression

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
    $id = $builder->getLastIdentifier();
    $results = $builder->compress();
    $result = $results[$id];

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

## Batch Processing with Default Algorithms

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

## Advanced Error Handling

Handle partial failures gracefully:

```php
$builder = new CompressionBuilder()
    ->graceful()  // Don't throw exceptions
    ->add('Test data', [
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11,  // May fail if ext not installed
        AlgorithmEnum::Zstd->value => 3      // May fail if ext not installed
    ]);

$id = $builder->getLastIdentifier();
$results = $builder->compress();
$result = $results[$id];

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


## Build Script for Static Assets

Create a build script to precompress assets:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Aurynx\HttpCompression\CompressionBuilder;
use Aurynx\HttpCompression\AlgorithmEnum;

$publicDir = __DIR__ . '/public';
$extensions = ['js', 'css', 'html', 'svg', 'json', 'xml'];

$files = [];
foreach ($extensions as $ext) {
    $found = glob("$publicDir/**/*.$ext");
    $files = array_merge($files, $found);
}

echo "Found " . count($files) . " files to compress\n";

$builder = new CompressionBuilder()
    ->failFast()
    ->addManyFiles($files, [
        AlgorithmEnum::Gzip->value => 9,
        AlgorithmEnum::Brotli->value => 11,
        AlgorithmEnum::Zstd->value => 19
    ]);

$results = $builder->compress();

$success = 0;
$failed = 0;

foreach ($results as $result) {
    if (!$result->isOk()) {
        echo "✗ Failed: {$result->getIdentifier()}\n";
        $failed++;
        continue;
    }
    
    $filePath = $result->getIdentifier();
    
    foreach ([AlgorithmEnum::Gzip, AlgorithmEnum::Brotli, AlgorithmEnum::Zstd] as $algo) {
        if ($compressed = $result->getCompressedFor($algo)) {
            $ext = match($algo) {
                AlgorithmEnum::Gzip => '.gz',
                AlgorithmEnum::Brotli => '.br',
                AlgorithmEnum::Zstd => '.zst',
            };
            file_put_contents($filePath . $ext, $compressed);
        }
    }
    
    $success++;
}

echo "\n✓ Successfully compressed: $success files\n";
if ($failed > 0) {
    echo "✗ Failed: $failed files\n";
    exit(1);
}
```

Make it executable:

```bash
chmod +x scripts/precompress.php
./scripts/precompress.php
```
