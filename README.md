# http-compression
Framework-agnostic PHP library for efficient HTTP compression (gzip, brotli, zstd) — simple, safe, and deterministic.

<p align="center">
  <img width="256" height="256" alt="Aurynx Mascot" src="https://github.com/user-attachments/assets/80a3ece6-5c50-4b01-9aee-7f086b55a0ef" />
</p>

## Result model

Compression returns a CompressionResult per item with explicit, typed states and structured errors:

- isOk(): true when all algorithms succeeded
- isPartial(): true when some algorithms succeeded and some failed
- isError(): true when all algorithms failed (complete failure)
- getCompressed(): array<string, string> — successful payloads per algorithm
- getErrors(): array<string, array{code:int, message:string}> — structured errors
  - Complete failure: ['_error' => ['code' => int, 'message' => string]]
  - Partial failure: ['gzip' => ['code' => int, 'message' => string], 'br' => [...]]

Example:

```php
$result = $builder->compressOne('item_1');

if ($result->isOk()) {
    // All good
} elseif ($result->isPartial()) {
    $errors = $result->getErrors(); // per-algorithm errors, e.g. ['br' => ['code' => 1002, 'message' => '...']]
} elseif ($result->isError()) {
    $errors = $result->getErrors(); // ['_error' => ['code' => 1002, 'message' => '...']]
}
```
