# Security Policy

<!-- SECURITY_CONTACT: anton.a.semenov@proton.me -->
<!-- SECURITY_SLA_INITIAL_RESPONSE: 5 days -->
<!-- SECURITY_SLA_PATCH_CRITICAL: 14 days -->
<!-- SECURITY_SCOPE: aurynx/http-compression -->

## Scope

This security policy applies to the **aurynx/http-compression** package and its associated examples and documentation.

## Supported Versions

This project focuses on PHP 8.4+ and is actively developed. Security updates are provided for the latest release.

| Version | Supported |
|:-------:|:---------:|
| latest  | ✅        |

## Reporting a Vulnerability

If you discover a security vulnerability in this library, please report it privately to ensure the issue can be addressed before public disclosure.

### How to Report

You can report vulnerabilities through:

1. **GitHub Private Vulnerability Reporting (recommended):**  
   [Report a vulnerability](https://github.com/aurynx/http-compression/security/advisories/new)

2. **Email:** anton.a.semenov@proton.me  
   **Subject:** `[SECURITY] Vulnerability in aurynx/http-compression`

**Please include:**
- Description of the vulnerability
- Steps to reproduce the issue
- Affected versions (if known)
- Potential impact
- Suggested fix (if available)

### Response Timeline

- **Initial Response:** Within 5 business days
- **Status Update:** Within 14 days
- **Fix Timeline:** Depends on severity:
  - **Critical:** Patch within 14 days
  - **High:** Patch within 30 days
  - **Medium/Low:** Patch in the next regular release

### Disclosure Policy

- Security issues will be fixed privately before public disclosure
- Once fixed, a security advisory will be published on GitHub
- Credit will be given to reporters (unless anonymity is requested)

## Security Best Practices

When using this library:

1. **Validate Input:** Always validate content before compression.
2. **Limit Payload Size:** Guard memory with either per-item or global limits:
   ```php
   use Aurynx\HttpCompression\CompressorFacade;
   use Aurynx\HttpCompression\ValueObjects\ItemConfig;

   // Per-item limit via ItemConfig
   $config = ItemConfig::create()
       ->withGzip(6)
       ->limitBytes(10_485_760) // 10MB
       ->build();

   $result = CompressorFacade::make()
       ->addFile('large.json', $config)
       ->inMemory(maxBytes: 10_485_760) // global per-item guard
       ->compress();
   ```
3. **Check Algorithm Availability:** Verify extensions are loaded before using optional algorithms:
   ```php
   use Aurynx\HttpCompression\Enums\AlgorithmEnum;

   if (AlgorithmEnum::Brotli->isAvailable()) {
       // Safe to use Brotli
   }
   ```
4. **Handle Errors:** Always check results in production:
   ```php
   if (!$result->allOk()) {
       foreach ($result->failures() as $id => $item) {
           error_log($item->getFailureReason()?->getMessage() ?? 'compression failed');
       }
   }
   ```

## Recommended Configuration

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

$result = CompressorFacade::make()
    ->addGlob('public/**/*.{html,css,js,svg,json}')
    ->withDefaultConfig(
        ItemConfig::create()
            ->withGzip(9)
            ->withBrotli(11)
            ->build()
    )
    ->toDir('./dist', keepStructure: true)
    ->compress();
```

## Known Security Considerations

### Compression Bombs
The library supports maximum input size limits via `ItemConfig::limitBytes()` and `CompressorFacade::inMemory(maxBytes)`. Always set appropriate limits for your use case.

### Algorithm Availability
The library gracefully handles missing PHP extensions. However, always verify algorithm availability in your deployment environment to avoid unexpected failures.

## Updates

Security updates will be announced via:
- GitHub Security Advisories
- Release notes in [CHANGELOG.md](CHANGELOG.md)
- Git tags

Subscribe to repository releases to receive notifications.
