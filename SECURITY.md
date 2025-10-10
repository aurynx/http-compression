# Security Policy

<!-- SECURITY_CONTACT: anton.a.semenov@proton.me -->
<!-- SECURITY_SLA_INITIAL_RESPONSE: 5 days -->
<!-- SECURITY_SLA_PATCH_CRITICAL: 14 days -->
<!-- SECURITY_SCOPE: aurynx/http-compression -->

## Scope

This security policy applies to the **aurynx/http-compression** package and its associated examples and documentation.

## Supported Versions

This project is currently in **pre-release** development (0.x versions). Security updates are provided for the latest 0.x release only.

| Version | Supported |
|:-------:|:---------:|
| 0.x.x   | ✅        |

Once the project reaches 1.0.0, this policy will be updated to reflect long-term support for stable versions.

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
  - **Medium/Low:** Patch in next regular release

### Disclosure Policy

- Security issues will be fixed privately before public disclosure
- Once fixed, a security advisory will be published on GitHub
- Credit will be given to reporters (unless anonymity is requested)

## Security Best Practices

When using this library:

1. **Validate Input:** Always validate content before compression
2. **Limit Payload Size:** Use `maxBytes` parameter to prevent memory exhaustion:
   ```php
   $builder = new CompressionBuilder(maxBytes: 10_485_760); // 10MB limit
   ```
3. **Check Algorithm Availability:** Verify extensions are loaded before compression:
   ```php
   if (AlgorithmEnum::Brotli->isAvailable()) {
       // Safe to use brotli
   }
   ```
4. **Handle Errors:** Always check compression results in production:
   ```php
   if ($result->isError()) {
       // Log error and fallback to uncompressed
   }
   ```

## Known Security Considerations

### Compression Bombs
This library includes built-in protection against decompression bombs through the `maxBytes` parameter. Always set appropriate limits for your use case.

### Algorithm Availability
The library gracefully handles missing PHP extensions. However, always verify algorithm availability in your deployment environment to avoid unexpected failures.

## Updates

Security updates will be announced via:
- GitHub Security Advisories
- Release notes in [CHANGELOG.md](CHANGELOG.md)
- Git tags

Subscribe to repository releases to receive notifications.
