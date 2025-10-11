# AI Guide

> Audience: AI coding assistants (GitHub Copilot, ChatGPT, Claude, etc.)  
> Purpose: a concise, copy-pasteable reference for LLM/agent usage of **aurynx/http-compression**

---

## 🎯 Quick Cheat Sheet

### Single File Compression

```php
use Aurynx\HttpCompression\CompressorFacade;

// Compress and save
CompressorFacade::once()
    ->file('index.html')
    ->withGzip(9)
    ->saveTo('index.html.gz');

// Compress in-memory
$result = CompressorFacade::once()
    ->data($html)
    ->withBrotli(11)
    ->compress();
```

### Batch Compression

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$result = CompressorFacade::make()
    ->addGlob('*.html')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
    ->toDir('./dist')
    ->compress();
```

---

## 📋 Use Case → Code Mapping

| User wants to... | Code pattern |
|-----------------|--------------|
| Compress one file | `CompressorFacade::once()->file($path)->withGzip()->saveTo($out)` |
| Compress data in memory | `CompressorFacade::once()->data($str)->withBrotli()->compress()` |
| Compress multiple files | `CompressorFacade::make()->addFile($p1)->addFile($p2)->...->compress()` |
| Compress directory | `CompressorFacade::make()->addGlob('dir/**/*.ext')->...->compress()` |
| Skip images/videos | `->skipAlreadyCompressed()` |
| Multiple algorithms | `ItemConfig::create()->withGzip(9)->withBrotli(11)->build()` |
| Save to directory | `->toDir('./output', keepStructure: true)` |
| Keep in memory | `->inMemory(maxBytes: 10_000_000)` |
| Stop on first error | `->failFast(true)` (default) |
| Continue on errors | `->failFast(false)` |

---

## 🏗️ Architecture Overview

```
CompressorFacade (facade)
    ├► CompressionInput (FileInput, DataInput)
    ├► ItemConfig (algorithms + limits)
    ├► OutputConfig (directory, in-memory, stream)
    └► CompressionResult
            └► CompressionItemResult (per-item data + metrics)
                    └► CompressionSummaryResult (aggregated stats)
```

---

## 🔑 Key Types

### Enums

```php
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

AlgorithmEnum::Gzip    // 'gzip'
AlgorithmEnum::Brotli  // 'br'
AlgorithmEnum::Zstd    // 'zstd'
```

### Value Objects

```php
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\ValueObjects\AlgorithmSet;

// Configuration for compression
$config = ItemConfig::create()
    ->withGzip(9)
    ->withBrotli(11)
    ->build();

// Algorithm set (immutable)
$algos = AlgorithmSet::gzip(9);
$algos = AlgorithmSet::fromDefaults();  // All algorithms with defaults
```

### Results

```php
// Batch result
$result = CompressorFacade::make()->compress();
$result->count(): int
$result->allOk(): bool
$result->first(): CompressionItemResult
$result->summary(): CompressionSummaryResult

// Single item result
$item = $result->first();
$item->getData(AlgorithmEnum::Gzip): string
$item->getStream(AlgorithmEnum::Gzip): resource
$item->isOk(): bool
$item->originalSize: int
$item->compressedSizes: array<string, int>
```

---

## ⚠️ Common Mistakes

### ✖️ Mistake 1: Multiple algorithms with `saveTo()`

```php
// WRONG - saveTo() requires exactly ONE algorithm
CompressorFacade::once()
    ->file('test.txt')
    ->withGzip(9)
    ->withBrotli(11)  // ← Second algorithm
    ->saveTo('test.gz');  // ← Throws CompressionException
```

✅ Fix: Use `compress()` instead:
```php
$result = CompressorFacade::once()
    ->file('test.txt')
    ->withGzip(9)
    ->withBrotli(11)
    ->compress();

// Access each algorithm separately
$gzipData = $result->getData(AlgorithmEnum::Gzip);
$brotliData = $result->getData(AlgorithmEnum::Brotli);
```

---

### ✖️ Mistake 2: Forgetting to set configuration

```php
// WRONG - no algorithm specified
CompressorFacade::make()
    ->addFile('test.txt')
    ->compress();  // ← Throws CompressionException
```

✅ Fix: Always provide configuration:
```php
CompressorFacade::make()
    ->addFile('test.txt')
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
    ->inMemory()
    ->compress();
```

---

### ✖️ Mistake 3: Invalid compression levels

```php
// WRONG - gzip levels are 1-9
AlgorithmSet::gzip(15);  // ← Throws InvalidArgumentException
```

✅ Valid ranges:
- Gzip: 1-9 (default: 6)
- Brotli: 0-11 (default: 11)
- Zstd: 1-22 (default: 3)

---

### ✖️ Mistake 4: Not handling failures

```php
// WRONG - assumes all succeeded
$result = CompressorFacade::make()
    ->addGlob('*.html')
    ->failFast(false)  // Continues on errors
    ->compress();

foreach ($result as $item) {
    echo $item->getData(AlgorithmEnum::Gzip);  // ← May throw if item failed
}
```

✅ Fix: Check status first:
```php
foreach ($result as $item) {
    if ($item->isOk()) {
        echo $item->getData(AlgorithmEnum::Gzip);
    } else {
        error_log("Failed: " . $item->getFailureReason()?->getMessage());
    }
}
```

---

### ✖️ Mistake 5: Using non-existent classes

```php
// WRONG — these classes do not exist in the library
$builder = new CompressionBuilder();
$builder->addFile('test.txt')->setAlgorithms(['gzip' => 9]);
// Also wrong: CompressorFactory::create(...)
```

✅ Fix: Use the current API:
```php
CompressorFacade::once()
    ->file('test.txt')
    ->withGzip(9)
    ->saveTo('test.txt.gz');
```

---

## 🎨 Code Generation Templates

### Template 1: Static Site Pre-Compression

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

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
    ->failFast(true)
    ->compress();

if (!$result->allOk()) {
    echo "❌ Compression failed\n";
    foreach ($result->failures() as $id => $item) {
        echo "  - {$id}: {$item->getFailureReason()?->getMessage()}\n";
    }
    exit(1);
}

echo "✓ Compressed {$result->count()} files\n";
```

---

### Template 2: Dynamic HTTP Response

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

function compressResponse(string $content, string $acceptEncoding): string
{
    // Determine best algorithm
    if (str_contains($acceptEncoding, 'br')) {
        $algo = AlgorithmEnum::Brotli;
        header('Content-Encoding: br');
    } elseif (str_contains($acceptEncoding, 'gzip')) {
        $algo = AlgorithmEnum::Gzip;
        header('Content-Encoding: gzip');
    } else {
        return $content;  // No compression
    }
    
    $result = CompressorFacade::once()
        ->data($content)
        ->withAlgorithm($algo, $algo->getDefaultLevel())
        ->compress();
    
    return $result->getData($algo);
}

// Usage
$html = view('welcome')->render();
echo compressResponse($html, $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
```

---

### Template 3: CLI Compression Tool

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

// php compress.php input.txt output.txt.gz
[$script, $input, $output] = $argv;

if (!file_exists($input)) {
    die("File not found: {$input}\n");
}

try {
    CompressorFacade::once()
        ->file($input)
        ->withGzip(9)
        ->saveTo($output);
    
    $originalSize = filesize($input);
    $compressedSize = filesize($output);
    $ratio = round(($compressedSize / $originalSize) * 100, 1);
    
    echo "✓ Compressed {$input} → {$output}\n";
    echo "  Original: {$originalSize} bytes\n";
    echo "  Compressed: {$compressedSize} bytes ({$ratio}%)\n";
    
} catch (\Throwable $e) {
    die("❌ Compression failed: {$e->getMessage()}\n");
}
```

---

### Template 4: Batch with Progress

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

$compressor = CompressorFacade::make()
    ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
    ->toDir('./dist', keepStructure: true)
    ->failFast(false);

// Add files via glob patterns (portable; no GLOB_BRACE)
$compressor
    ->addGlob('assets/**/*.css')
    ->addGlob('assets/**/*.js');

$result = $compressor->compress();

// Report
echo "\n";
echo "✓ Success: " . count($result->successes()) . "\n";
echo "✗ Failed: " . count($result->failures()) . "\n";

$summary = $result->summary();
echo "Avg compression ratio: " . round($summary->getAverageRatio(AlgorithmEnum::Gzip) * 100, 1) . "%\n";
echo "Total time: " . round($summary->getTotalTimeMs(AlgorithmEnum::Gzip)) . " ms\n";
```

---

## 🧪 Testing Patterns

### Unit Test

```php
use Aurynx\HttpCompression\CompressorFacade;
use PHPUnit\Framework\TestCase;

final class CompressionTest extends TestCase
{
    public function test_compresses_html_file(): void
    {
        $html = '<html><body>Test</body></html>';
        
        $result = CompressorFacade::once()
            ->data($html)
            ->withGzip(9)
            ->compress();
        
        $this->assertTrue($result->isOk());
        $this->assertLessThan(strlen($html), $result->compressedSizes['gzip']);
    }
}
```

### Integration Test

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;
use PHPUnit\Framework\TestCase;

final class BatchCompressionTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test-' . uniqid();
        mkdir($this->tempDir);
        file_put_contents("{$this->tempDir}/test1.html", '<html>1</html>');
        file_put_contents("{$this->tempDir}/test2.html", '<html>2</html>');
    }
    
    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->tempDir}/*"));
        rmdir($this->tempDir);
    }
    
    public function test_batch_compression(): void
    {
        $result = CompressorFacade::make()
            ->addGlob("{$this->tempDir}/*.html")
            ->withDefaultConfig(ItemConfig::create()->withGzip(9)->build())
            ->toDir($this->tempDir)
            ->compress();
        
        $this->assertCount(2, $result);
        $this->assertTrue($result->allOk());
        $this->assertFileExists("{$this->tempDir}/test1.html.gz");
        $this->assertFileExists("{$this->tempDir}/test2.html.gz");
    }
}
```

---

## 🚀 Performance Tips

### 1. Choose appropriate compression levels

```php
// Fast (for dynamic responses)
ItemConfig::create()->withGzip(1)->build();       // Fastest
ItemConfig::create()->withBrotli(4)->build();     // Fast
ItemConfig::create()->withZstd(1)->build();       // Fastest

// Balanced (general use)
ItemConfig::create()->withGzip(6)->build();       // Default
ItemConfig::create()->withBrotli(6)->build();     // Balanced
ItemConfig::create()->withZstd(3)->build();       // Default

// Maximum (for static assets)
ItemConfig::create()->withGzip(9)->build();       // Maximum
ItemConfig::create()->withBrotli(11)->build();    // Maximum
ItemConfig::create()->withZstd(19)->build();      // Maximum (slow!)
```

### 2. Skip pre-compressed formats

```php
// Always use for static sites
CompressorFacade::make()
    ->addGlob('public/**/*')
    ->skipAlreadyCompressed()  // Skips images, videos, fonts, archives
    ->compress();
```

### 3. Use streaming for large files

```php
// For files > 5MB
$result = CompressorFacade::make()
    ->addFile('large.json')
    ->withDefaultConfig(ItemConfig::create()->withGzip(6)->build())
    ->inMemory(maxBytes: 100_000_000)  // 100MB limit
    ->compress();

// Stream output
$result->first()->read(AlgorithmEnum::Gzip, function (string $chunk) {
    file_put_contents('output.gz', $chunk, FILE_APPEND);
});
```

---

## 📊 Statistics Access

```php
$result = CompressorFacade::make()->compress();

// Per-item metrics
foreach ($result as $item) {
    echo "Original: {$item->originalSize} bytes\n";
    echo "Gzip: {$item->compressedSizes['gzip']} bytes\n";
    echo "Time: {$item->compressionTimes['gzip']} ms\n";
}

// Aggregated statistics
$summary = $result->summary();
echo "Median ratio: " . $summary->getMedianRatio(AlgorithmEnum::Gzip) . "\n";
echo "P95 time: " . $summary->getP95TimeMs(AlgorithmEnum::Gzip) . " ms\n";
echo "Success rate: " . ($summary->getSuccessCount() / $summary->getTotalItems()) . "\n";
```

---

## 🔗 Quick Links

- Full API Reference: See README.md § API Reference
- Use Cases: See README.md § Use Cases
- GitHub Issues: https://github.com/aurynx/http-compression/issues

---

## 💡 Decision Tree

```
User wants to compress...
│
├─ ONE file/data?
│  └─ Use CompressorFacade::once()
│     │
│     ├─ Save to file? → ->saveTo($path)
│     └─ Get result?   → ->compress()
│
└─ MULTIPLE files?
   └─ Use CompressorFacade::make()
      │
      ├─ From glob pattern?     → ->addGlob($pattern)
      ├─ Individual files?      → ->addFile($path) (repeat)
      └─ Custom source?         → ->addFrom($provider)
      │
      └─ Output where?
         ├─ To directory?       → ->toDir($dir)
         └─ In memory?          → ->inMemory()
         │
         └─ ->compress()
```

---

<p align="center">
<b>Remember:</b> This library uses native PHP 8.4 types.  
No docblock parsing needed—trust the type signatures! 🎯
</p>
