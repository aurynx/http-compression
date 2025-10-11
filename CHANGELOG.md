# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-10-11

### Added
- Added comprehensive documentation for compression metrics usage and examples
- Added examples for algorithm availability and selection in advanced usage docs
- Added advanced usage documentation for compressors
- Added Contributor Covenant code of conduct
- Added contributing guidelines for contributors
- Enhanced security policy with GitHub advisories and SLA
- Added security policy for vulnerability reporting
- Configured Dependabot for Composer dependencies
- Added homepage and support links to composer.json
- Added constructor and last identifier retrieval method to AI tool documentation

### Changed
- **BREAKING**: Major refactor of core compression classes and architecture
- Updated composer.json with additional support information
- Renamed Compression classes and updated all references
- Aligned variable spacing for consistency in tests

### Fixed
- Fixed PHP code example for result inspection in README
- Adjusted default compression levels for algorithms
- Fixed namespace casing for HttpCompression classes in documentation

## [0.1.1] - 2024-12-XX

### Added
- Added server integration examples for static file delivery
- Added AI assistant instructions and examples for HTTP compression
- Added Packagist badges for version, downloads, and license

### Changed
- Simplified CompressionBuilder instantiation
- Enhanced project description and usage examples
- Updated description to include Zstd compression support

## [0.1.0] - 2024-12-XX

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
