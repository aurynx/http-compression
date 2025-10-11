<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Providers;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Contracts\InputProviderInterface;
use Aurynx\HttpCompression\Support\Hashing;
use Aurynx\HttpCompression\ValueObjects\FileInput;

/**
 * Provider that generates FileInputs from glob patterns
 * NOT a CompressionInput itself — prevents LSP violation
 */
final class GlobInputProvider implements InputProviderInterface
{
    /** @var array<string, true> */
    private array $seenPaths = [];

    public function __construct(
        public readonly string $pattern,
        public readonly bool $failOnEmpty = true,
    ) {
    }

    /**
     * Provide file inputs matching the glob pattern
     *
     * @return FileInput[]
     */
    public function provide(): array
    {
        // Expand any brace patterns manually to avoid using GLOB_BRACE (not portable)
        $patterns = self::expandBracePatterns($this->pattern);

        $files = [];
        foreach ($patterns as $pattern) {
            $matches = glob($pattern); // default flags
            if ($matches !== false) {
                // Avoid array_merge in loops — push with unpacking for scalars
                array_push($files, ...$matches);
            }
        }

        // Deduplicate matched files and reindex
        $files = array_values(array_unique($files));

        if (empty($files) && $this->failOnEmpty) {
            throw new CompressionException("No files matched pattern: {$this->pattern}");
        }

        $inputs = [];

        foreach ($files as $path) {
            // Resolve a real path (handles symlinks)
            $realPath = realpath($path);

            if ($realPath === false) {
                continue; // Skip broken symlinks
            }

            // Skip directories
            if (!is_file($realPath)) {
                continue;
            }

            // Deduplicate by real path
            if (isset($this->seenPaths[$realPath])) {
                continue;
            }

            // Security: validate no null bytes
            $this->validatePathSecurity($realPath);

            // Mark as seen
            $this->seenPaths[$realPath] = true;

            $inputs[] = new FileInput(
                id: $this->generateFileId($realPath),
                path: $realPath
            );
        }

        return $inputs;
    }

    /**
     * Generate unique ID for a file using a fast hash
     */
    private function generateFileId(string $path): string
    {
        return Hashing::fastId($path);
    }

    /**
     * Expand brace patterns like assets/*.{css,js} into [assets/*.css, assets/*.js].
     * Supports nested braces by processing the first outermost group recursively.
     *
     * @return list<string>
     */
    private static function expandBracePatterns(string $pattern): array
    {
        $openPos = strpos($pattern, '{');
        if ($openPos === false) {
            return [$pattern];
        }

        // Find matching closing brace for the first '{'
        $depth = 0;
        $closePos = null;
        $len = strlen($pattern);
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $pattern[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $closePos = $i;
                    break;
                }
            }
        }

        if ($closePos === null) {
            // Unbalanced brace — return as-is for glob to handle normally
            return [$pattern];
        }

        $prefix = substr($pattern, 0, $openPos);
        $inside = substr($pattern, $openPos + 1, $closePos - $openPos - 1);
        $suffix = substr($pattern, $closePos + 1);

        $options = self::splitBraceOptions($inside);

        $expanded = [];
        foreach ($options as $option) {
            // Recurse — suffix may contain more brace groups
            foreach (self::expandBracePatterns($prefix . $option . $suffix) as $p) {
                $expanded[] = $p;
            }
        }

        return $expanded;
    }

    /**
     * Split the inside of a brace group by commas at depth 0, supporting nested braces.
     * Example: "a,b,{c,d}" -> ["a", "b", "{c,d}"]
     *
     * @return list<string>
     */
    private static function splitBraceOptions(string $inside): array
    {
        $parts = [];
        $buf = '';
        $depth = 0;
        $len = strlen($inside);
        for ($i = 0; $i < $len; $i++) {
            $ch = $inside[$i];
            if ($ch === '{') {
                $depth++;
                $buf .= $ch;
            } elseif ($ch === '}') {
                $depth--;
                $buf .= $ch;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $buf;
                $buf = '';
            } else {
                $buf .= $ch;
            }
        }
        if ($buf !== '') {
            $parts[] = $buf;
        }

        return array_map(static fn (string $segment): string => trim($segment), $parts);
    }

    /**
     * Prevent path injection attacks
     */
    private function validatePathSecurity(string $path): void
    {
        // Check for null bytes (PHP injection)
        if (str_contains($path, "\0")) {
            throw new CompressionException("Path contains null byte: {$path}");
        }

        // Additional security checks can be added here
        // (e.g., whitelist base directory)
    }
}
