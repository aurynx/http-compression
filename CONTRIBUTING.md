# Contributing to aurynx/http-compression

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)
- [Documentation](#documentation)

## Code of Conduct

This project follows the principles of respect and professionalism. Be kind, constructive, and welcoming to all contributors.

## How Can I Contribute?

### Reporting Bugs

Before creating a bug report:
1. Check if the issue already exists in [GitHub Issues](https://github.com/aurynx/http-compression/issues)
2. Verify you're using the latest version
3. Check that the required PHP extensions are installed

When creating a bug report, include:
- **PHP version** (`php -v`)
- **Installed extensions** (`php -m | grep -E 'zlib|brotli|zstd'`)
- **Code to reproduce** (minimal example)
- **Expected vs actual behavior**
- **Error messages** (full stack traces)

### Suggesting Enhancements

Enhancement suggestions are welcome! Please:
1. Check existing [Issues](https://github.com/aurynx/http-compression/issues) and [Discussions](https://github.com/aurynx/http-compression/discussions)
2. Provide clear use case and rationale
3. Consider backwards compatibility (we're in 0.x, but breaking changes need discussion)

### Pull Requests

We accept pull requests for:
- ✅ Bug fixes
- ✅ Performance improvements
- ✅ Documentation improvements
- ✅ Test coverage improvements
- ✅ New features (please discuss first in an Issue)

## Development Setup

### Requirements

- PHP 8.4 or higher
- Composer 2.x
- Required extensions:
  - `ext-zlib` (required)
  - `ext-brotli` (optional, for brotli tests)
  - `ext-zstd` (optional, for zstd tests)

### Clone and Install

```bash
git clone https://github.com/aurynx/http-compression.git
cd http-compression
composer install
```

### Verify Setup

```bash
# Check PHP version
php -v

# Check installed extensions
php -m | grep -E 'zlib|brotli|zstd'

# Run tests
composer test

# Run static analysis
composer stan

# Check code style
composer cs:check
```

## Coding Standards

### PHP Standards

- **PHP version:** 8.4+ features are allowed
- **Strict types:** Always use `declare(strict_types=1);`
- **Final classes:** Prefer `final` for classes not designed for inheritance
- **Readonly properties:** Use `readonly` for immutable DTOs
- **Type hints:** Always use return types and parameter types

### Code Style

We use **PHP-CS-Fixer** with PSR-12 standard:

```bash
# Check code style
composer cs:check

# Auto-fix code style
composer cs:fix
```

### Static Analysis

We use **PHPStan** at max level:

```bash
# Run static analysis
composer stan

# CI mode (no progress, for automation)
composer stan:ci
```

### Architecture Guidelines

1. **Contracts/** — Interfaces only, no implementation
2. **DTO/** — Immutable data objects with `final readonly`
3. **Algorithms/** — Compressor implementations
4. **Core classes** — Builder, Factory in root namespace

**Naming conventions:**
- DTOs: `*Dto` suffix (e.g., `CompressionResultDto`)
- Enums: `*Enum` suffix (e.g., `AlgorithmEnum`)
- Interfaces: `*Interface` suffix (e.g., `CompressorInterface`)
- Exceptions: `*Exception` suffix

## Commit Guidelines

We follow **Conventional Commits** (Angular preset). See [.github/git-commit-instructions.md](.github/git-commit-instructions.md) for full details.

### Format

```
<type>(<scope>): <subject>

[optional body]

[optional footer]
```

### Types

- `feat` — new feature
- `fix` — bug fix
- `refactor` — code refactoring (no behavior change)
- `perf` — performance improvement
- `docs` — documentation only
- `test` — tests only
- `build` — build system, composer
- `ci` — CI configuration
- `chore` — maintenance tasks

### Scopes

- `core` — shared code, interfaces
- `gzip` — Gzip compressor
- `brotli` — Brotli compressor
- `zstd` — Zstd compressor
- `dto` — DTO classes
- `tests` — test-related
- `docs` — documentation
- `ci-config` — GitHub Actions
- `repo` — repository meta files

### Examples

```bash
feat(core): add streaming compression support
fix(brotli): handle invalid compression level
refactor(dto): extract CompressionResultDto from core
docs(readme): add nginx configuration example
test(gzip): add edge case for empty input
chore(deps): update phpstan to 2.2
```

### Commit Message Rules

- ✅ Use imperative mood ("add feature", not "added feature")
- ✅ Keep subject ≤50 characters (hard limit 72)
- ✅ No trailing period in subject
- ✅ Wrap body at 72 characters
- ❌ Don't create release commits manually

## Pull Request Process

### Before Submitting

1. **Create an issue** (for features/significant changes)
2. **Fork the repository**
3. **Create a feature branch:**
   ```bash
   git checkout -b feat/my-feature
   # or
   git checkout -b fix/issue-123
   ```

4. **Make your changes**
5. **Add tests** for new functionality
6. **Run all checks:**
   ```bash
   composer test        # Run tests
   composer stan        # Static analysis
   composer cs:fix      # Fix code style
   ```

7. **Commit with conventional commits**
8. **Push to your fork**
9. **Open a Pull Request**

### PR Template

When creating a PR, include:

```markdown
## Description
Brief description of what this PR does.

## Motivation
Why is this change needed? What problem does it solve?

## Changes
- List of changes made
- Breaking changes (if any)

## Testing
- [ ] Tests added/updated
- [ ] All tests pass locally
- [ ] PHPStan passes
- [ ] Code style checked

## Related Issues
Closes #123
```

### Review Process

- PRs require at least one approval
- CI must pass (tests, PHPStan, code style)
- Maintainer will review within 5-7 business days
- Address review comments by pushing new commits
- Once approved, maintainer will squash and merge

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Unit/AlgorithmMetadataTest.php

# Run with coverage (requires Xdebug)
./vendor/bin/pest --coverage

# Run tests for specific feature
./vendor/bin/pest --filter=Compression
```

### Writing Tests

We use **Pest PHP** for testing:

```php
use Aurynx\HttpCompression\CompressorFacade;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

it('compresses with gzip returns compressed data', function () {
    $result = CompressorFacade::make()
        ->data('Hello, World!')
        ->withGzip(6)
        ->compress();
    
    expect($result->isOk())->toBeTrue();
    expect($result->getData(AlgorithmEnum::Gzip))->not->toBeEmpty();
});
```

### Test Coverage

- Aim for **>80% code coverage**
- All new features **must have tests**
- Bug fixes **must include regression tests**
- Edge cases and error paths should be tested

## Documentation

### Code Documentation

- Use **PHPDoc** for all public methods
- Document parameters with `@param` including types
- Document return values with `@return`
- Document thrown exceptions with `@throws`
- Add examples in docblocks for complex methods

Example:

```php
/**
 * Compress content with a specified algorithm
 *
 * @param string $content Content to compress
 * @param AlgorithmEnum $algorithm Compression algorithm to use
 * @param int|null $level Compression level (null = default)
 * @return string Compressed binary data
 * @throws CompressionException If compression fails
 */
public function compress(string $content, AlgorithmEnum $algorithm, ?int $level = null): string
{
    // implementation
}
```

### User Documentation

When changing public API, update:
- `README.md` — quick start examples
- `docs/api-reference.md` — API documentation
- `docs/examples.md` — usage examples
- `docs/advanced-usage.md` — advanced scenarios
- `CHANGELOG.md` — add entry for next release

### AI Documentation

When adding new features, consider updating:
- `docs/ai.md` — AI agent guide
- `docs/ai-tool.json` — machine-readable API schema

## Questions?

- **General questions:** [GitHub Discussions](https://github.com/aurynx/http-compression/discussions)
- **Bug reports:** [GitHub Issues](https://github.com/aurynx/http-compression/issues)
- **Security issues:** See [SECURITY.md](SECURITY.md)

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).

---

Thank you for contributing! 🎉
