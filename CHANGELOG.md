# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-10-11

### Changed
- **BREAKING**: Major architectural refactoring of the entire library
  - Replaced `CompressionBuilder` with new `CompressionEngine` and `CompressorFacade`
  - Introduced `SingleItemFacade` for individual item compression
  - Reorganized compressor classes: moved from `Algorithms/` to `Compressors/` namespace
  - Refactored DTOs: replaced `CompressionItemDto`, `CompressionStatsDto` with new Result objects
  - Introduced new builder pattern: `ItemConfigBuilder` and `ItemScopeBuilder`
  - Added value objects: `AlgorithmSet`, `CompressionInput`, `DataInput`, `FileInput`, `ItemConfig`, `OutputConfig`
  - Added result objects: `CompressionItemResult`, `CompressionResult`, `CompressionSummaryResult`
  - Enhanced `AlgorithmEnum` with metadata and attributes support
- Completely rewrote and expanded documentation:
  - Enhanced README with clearer examples and structure
  - Added comprehensive AI_GUIDE.md for AI assistants
  - Simplified advanced-usage.md and api-reference.md
  - Updated all code examples across documentation
- Updated composer.json with additional support information
- Enhanced CONTRIBUTING.md with better guidelines
- Improved SECURITY.md with GitHub advisories integration

### Added
- New `Attributes/` namespace with `AlgorithmAttribute`, `CompressionLevelAttribute`, `OutputFormatAttribute`
- New `Builders/` namespace for configuration builders
- New `Providers/GlobInputProvider` for glob-based file input
- New `Support/` namespace with `AlgorithmMetadata` and `Hashing` utilities
- New enums: `ErrorCodeEnum`, `InputTypeEnum`, `OutputModeEnum`, `PrecompressedExtensionEnum`
- DTO for algorithm metadata: `AlgorithmMetaDto`
- PHPStan stub for Zstd extension
- Input provider interface for extensibility

### Removed
- Removed old `CompressionBuilder` class (replaced by new architecture)
- Removed old DTO classes: `CompressionItemDto`, `CompressionStatsDto`
- Removed `CompressorFactory` (functionality integrated into new architecture)
- Removed `ItemConfigurator` (replaced by builders)
- Removed legacy test files that tested old architecture
- Removed old algorithm classes from `Algorithms/` namespace

## [0.1.1] - 2025-10-10

### Added
- Added server integration examples for static file delivery
- Added AI assistant instructions and examples for HTTP compression
- Added Packagist badges for version, downloads, and license

### Changed
- Simplified CompressionBuilder instantiation
- Enhanced project description and usage examples
- Updated description to include Zstd compression support

## [0.1.0] - 2025-10-10

### Added
- Initial release
- Support for gzip, Brotli, and Zstandard (zstd) compression
- Framework-agnostic compression engine
- Deterministic builds support
- Safe file precompression capabilities
- PHP CS Fixer configuration for code style enforcement
- PHPStan configuration and scripts for static analysis
- Comprehensive test suite with Pest PHP
- Payload size limit to compression operations
- Error handling with specific error codes
- Compression algorithm metadata and availability checks

### Features
- CompressionEngine for processing multiple files
- SingleItemFacade for individual compressions
- Algorithm selection and configuration builders
- Support for multiple input types (file, string, stream)
- Compression result metrics and reporting
- Glob-based input provider for batch operations

[0.2.0]: https://github.com/aurynx/http-compression/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/aurynx/http-compression/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/aurynx/http-compression/releases/tag/v0.1.0
