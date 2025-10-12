<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

use Aurynx\HttpCompression\Enums\AlgorithmEnum;

final class AcceptEncoding
{
    /**
     * Negotiate best encoding from Accept-Encoding header and available algorithms.
     * Returns null for identity (no compression) or if nothing acceptable.
     */
    public static function negotiate(string $header, AlgorithmEnum ...$available): ?AlgorithmEnum
    {
        $prefs = self::parseHeader($header);

        // Explicit excludes (q=0) map for quick checks
        $q0 = array_keys(array_filter($prefs, static fn (float $q): bool => $q === 0.0));

        // If there is an explicit identity with q>0 and no better choice, return null
        // We still consider concrete encodings first.

        // Consider explicit encodings with q>0, ordered by q desc
        $candidates = [];

        foreach ($prefs as $enc => $q) {
            if ($q <= 0.0 || $enc === 'identity' || $enc === '*') {
                continue;
            }
            $candidates[] = [$enc, $q];
        }

        usort($candidates, static function (array $a, array $b): int {
            return $b[1] <=> $a[1]; // sort by q desc
        });

        foreach ($candidates as [$encoding]) {
            foreach ($available as $algo) {
                if ($encoding === $algo->getContentEncoding()) {
                    return $algo;
                }
            }
        }

        // Wildcard support ('*' with q>0): choose the best available by project preference (br > zstd > gzip)
        if (isset($prefs['*']) && $prefs['*'] > 0.0) {
            $order = [AlgorithmEnum::Brotli, AlgorithmEnum::Zstd, AlgorithmEnum::Gzip];

            foreach ($order as $preferred) {
                if (array_any($available, static fn (AlgorithmEnum $a): bool => $a === $preferred)) {
                    // Ensure not explicitly excluded by q=0
                    $enc = $preferred->getContentEncoding();
                    $explicitlyRejected = array_any($q0, static fn (string $rej): bool => $rej === $enc);

                    if (!$explicitlyRejected) {
                        return $preferred;
                    }
                }
            }
        }

        // Identity acceptable?
        if (isset($prefs['identity']) && $prefs['identity'] > 0.0) {
            return null; // no compression
        }

        // No acceptable encoding
        return null;
    }

    /**
     * @return array<string, float> encoding => q
     */
    private static function parseHeader(string $header): array
    {
        $header = trim($header);

        if ($header === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $header));
        $prefs = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            [$enc, $q] = self::parsePart($part);
            $prefs[$enc] = $q;
        }

        return $prefs;
    }

    /**
     * @return array{0:string,1:float}
     */
    private static function parsePart(string $part): array
    {
        $semi = array_map('trim', explode(';', $part));
        $enc = strtolower($semi[0]);
        $q = 1.0;

        if (isset($semi[1]) && str_starts_with($semi[1], 'q=')) {
            $raw = substr($semi[1], 2);
            $val = is_numeric($raw) ? (float) $raw : 1.0;

            if ($val < 0.0) {
                $val = 0.0;
            }

            if ($val > 1.0) {
                $val = 1.0;
            }
            $q = $val;
        }

        return [$enc, $q];
    }
}
