<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

/**
 * Static hashing utilities.
 *
 * Not a singleton: no state is stored. The private constructor is used to
 * prevent instantiation, enforcing static-only usage via Hashing::fastId().
 */
final class Hashing
{
    /**
     * Prevent instantiation — this is a static utility class.
     */
    private function __construct()
    {
    }

    /**
     * Return a fast, deterministic hex string ID for the given input.
     * Prefers xxh3 when available (fastest), falls back to sha1, then md5.
     */
    public static function fastId(string $input): string
    {
        if (function_exists('hash')) {
            $algos = hash_algos();

            if (array_any($algos, static fn (string $algo): bool => $algo === 'xxh3')) {
                return hash('xxh3', $input);
            }

            // sha1 is widely available and faster than md5 in many setups
            if (array_any($algos, static fn (string $algo): bool => $algo === 'sha1')) {
                return hash('sha1', $input);
            }

            if (array_any($algos, static fn (string $algo): bool => $algo === 'md5')) {
                return hash('md5', $input);
            }
        }

        // As a last resort (should not happen on supported PHP), mimic md5
        return bin2hex(substr($input, 0, 16));
    }
}
