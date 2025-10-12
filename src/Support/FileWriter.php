<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Enums\OverwritePolicyEnum;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Throwable;

/**
 * Filesystem helper focused on fast, atomic writes of multiple outputs.
 *
 * Design:
 * - Stateless, static-only utility (mirrors existing Support classes style)
 * - Handles directory preparation, temp writes with LOCK_EX, and atomic renames
 * - Enforces OverwritePolicy semantics (fail/replace/skip)
 */
final class FileWriter
{
    private function __construct()
    {
    }

    /**
     * Ensure output directory exists and is writable, optionally creating it.
     * Returns realpath to the directory or throws CompressionException with context.
     */
    public static function prepareOutputDirectory(string $dir, bool $allowCreateDirs): string
    {
        if (!is_dir($dir)) {
            if (!$allowCreateDirs) {
                throw new CompressionException(
                    "Directory does not exist: {$dir}",
                    0,
                    null,
                    [ 'path' => $dir ],
                );
            }

            $created = @mkdir($dir, 0755, true);

            // Race-safe: if someone else created it, accept
            if (!$created && !is_dir($dir)) {
                $parent = dirname($dir) ?: '.';
                throw new CompressionException(
                    "Failed to create directory: {$dir}",
                    0,
                    null,
                    [
                        'path' => $dir,
                        'directoryWritable' => is_writable($parent),
                        'lastPhpError' => (error_get_last()['message'] ?? null),
                    ],
                );
            }
        }

        $real = realpath($dir);

        if ($real === false) {
            throw new CompressionException(
                "Directory does not exist: {$dir}",
                0,
                null,
                [ 'path' => $dir ],
            );
        }

        if (!is_writable($real)) {
            throw new CompressionException(
                "Directory is not writable: {$real}",
                0,
                null,
                [
                    'path' => $real,
                    'directoryWritable' => false,
                    'diskFreeSpace' => @disk_free_space($real) ?: null,
                ],
            );
        }

        return $real;
    }

    /**
     * Atomically write a single file to the given absolute path.
     * Default policy: Replace (matches prior saveTo() behavior based on file_put_contents).
     */
    public static function writeToPath(
        string $path,
        string $data,
        OverwritePolicyEnum $policy = OverwritePolicyEnum::Replace,
        ?int $permissions = null,
        bool $allowCreateDirs = true
    ): void {
        $dir = dirname($path);
        $file = basename($path);

        if ($file === '' || $file === '.' || $file === '..') {
            throw new CompressionException('Invalid target filename for writeToPath: ' . $path);
        }

        $realDir = self::prepareOutputDirectory($dir, $allowCreateDirs);
        $target = $realDir . DIRECTORY_SEPARATOR . $file;

        if (file_exists($target)) {
            if ($policy->isSkip()) {
                return; // keep existing
            }
            if ($policy === OverwritePolicyEnum::Fail) {
                throw new CompressionException(
                    "Target already exists: {$target}",
                    0,
                    null,
                    [ 'path' => $target ],
                );
            }
        }

        $tmp = $target . '.tmp.' . uniqid('', true);

        $bytes = @file_put_contents($tmp, $data, LOCK_EX);
        if ($bytes === false) {
            throw new CompressionException(
                "Failed to write temp file: {$tmp}",
                0,
                null,
                [
                    'path' => $tmp,
                    'bytesToWrite' => strlen($data),
                    'directoryWritable' => is_writable($realDir),
                    'diskFreeSpace' => @disk_free_space($realDir) ?: null,
                    'lastPhpError' => (error_get_last()['message'] ?? null),
                ],
            );
        }

        if (file_exists($target) && $policy->isReplace()) {
            @unlink($target);
        }

        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            throw new CompressionException(
                "Failed to move temp file to target: {$target}",
                0,
                null,
                [
                    'path' => $target,
                    'directoryWritable' => is_writable(dirname($target)),
                    'diskFreeSpace' => @disk_free_space(dirname($target)) ?: null,
                    'lastPhpError' => (error_get_last()['message'] ?? null),
                ],
            );
        }

        if (is_int($permissions)) {
            @chmod($target, $permissions);
        }
    }

    /**
     * Write multiple algorithm outputs atomically to the target directory.
     *
     * @param array<int, array{algo: AlgorithmEnum, data: string}> $entries
     * @throws CompressionException|Throwable
     */
    public static function writeAll(
        string $directory,
        string $basename,
        array $entries,
        OverwritePolicyEnum $policy,
        bool $atomicAll = true,
        ?int $permissions = null,
        bool $allowCreateDirs = true
    ): void {
        // Validate basename to avoid path traversal and unexpected subdirs
        if ($basename === '' || $basename === '.' || $basename === '..' || str_contains($basename, '/') || str_contains($basename, '\\')) {
            throw new CompressionException(
                'Invalid basename: ' . $basename,
                0,
                null,
                [ 'basename' => $basename ],
            );
        }

        $dir = rtrim($directory, '/\\');
        $realDir = self::prepareOutputDirectory($dir, $allowCreateDirs);

        $finalTargets = [];
        $toWrite = [];
        $dataByAlgo = [];

        foreach ($entries as $entry) {
            $algo = $entry['algo'];
            $data = $entry['data'];

            $target = $realDir . DIRECTORY_SEPARATOR . $basename . '.' . $algo->getExtension();
            $finalTargets[$algo->value] = $target;

            if (file_exists($target)) {
                if ($policy->isSkip()) {
                    // skip writing this algorithm
                    continue;
                }
                if ($policy === OverwritePolicyEnum::Fail) {
                    throw new CompressionException(
                        "Target already exists: {$target}",
                        0,
                        null,
                        [ 'path' => $target ],
                    );
                }
                // 'replace' will overwrite later
            }

            $toWrite[] = $algo;
            $dataByAlgo[$algo->value] = $data;
        }

        $tmpFiles = [];

        try {
            // Stage data into tmp files first
            foreach ($toWrite as $algo) {
                $data = $dataByAlgo[$algo->value];
                $tmp = $realDir . DIRECTORY_SEPARATOR . $basename . '.' . $algo->getExtension() . '.tmp.' . uniqid('', true);
                $bytes = @file_put_contents($tmp, $data, LOCK_EX);

                if ($bytes === false) {
                    throw new CompressionException(
                        "Failed to write temp file: {$tmp}",
                        0,
                        null,
                        [
                            'path' => $tmp,
                            'bytesToWrite' => strlen($data),
                            'directoryWritable' => is_writable($realDir),
                            'diskFreeSpace' => @disk_free_space($realDir) ?: null,
                            'lastPhpError' => (error_get_last()['message'] ?? null),
                        ],
                    );
                }

                $tmpFiles[$algo->value] = $tmp;
            }

            // Move into place
            foreach ($toWrite as $algo) {
                $target = $finalTargets[$algo->value] ?? null;
                $tmp = $tmpFiles[$algo->value] ?? null;
                if ($target === null || $tmp === null) {
                    continue;
                }

                if (file_exists($target) && $policy->isReplace()) {
                    // Best-effort removal before rename for portability
                    @unlink($target);
                }

                if (!@rename($tmp, $target)) {
                    throw new CompressionException(
                        "Failed to move temp file to target: {$target}",
                        0,
                        null,
                        [
                            'path' => $target,
                            'directoryWritable' => is_writable(dirname($target)),
                            'diskFreeSpace' => @disk_free_space(dirname($target)) ?: null,
                            'lastPhpError' => (error_get_last()['message'] ?? null),
                        ],
                    );
                }

                if (is_int($permissions)) {
                    @chmod($target, $permissions);
                }

                unset($tmpFiles[$algo->value]);
            }
        } catch (Throwable $e) {
            // Cleanup tmp files on failure
            foreach ($tmpFiles as $tmpPath) {
                @unlink($tmpPath);
            }

            if ($atomicAll === true) {
                // Attempt to remove any partially written targets
                foreach ($toWrite as $algo) {
                    $target = $finalTargets[$algo->value] ?? null;
                    if ($target !== null && file_exists($target)) {
                        @unlink($target);
                    }
                }
            }

            throw $e; // rethrow
        }
    }
}
