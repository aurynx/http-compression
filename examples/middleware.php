<?php

declare(strict_types=1);

use Aurynx\HttpCompression\Enums\AlgorithmEnum;
use Aurynx\HttpCompression\Support\AcceptEncoding;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Minimal PHP middleware-like example for serving precompressed files.
 *
 * How it works:
 * - For a requested path (e.g., /assets/app.js), check for precompressed variants using Accept-Encoding
 * - Prefer .br over .gz if the client accepts it
 * - Serve the best existing compressed variant with proper headers
 * - Fallback to the original file if no suitable precompressed variant exists
 *
 * Note: In production, prefer precompression at build time using examples/precompress.php.
 */

// Resolve requested file (adapt to your routing)
$path = $_GET['file'] ?? null; // e.g., ?file=/assets/app.js
if ($path === null) {
    http_response_code(400);
    echo 'Missing "file" query parameter';
    exit;
}

$root = __DIR__ . '/public'; // your public dir
$original = realpath($root . $path);

if ($original === false || !is_file($original)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$accept = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

// Negotiate the best encoding among supported precompressed variants
// For static serving, we don't need PHP compression extensions — pass desired algos explicitly
$algo = AcceptEncoding::negotiate($accept, AlgorithmEnum::Brotli, AlgorithmEnum::Gzip);

$chosenFile = null;
$chosenEncoding = null;

if ($algo !== null) {
    $candidate = $original . '.' . $algo->getExtension();
    if (is_file($candidate)) {
        $chosenFile = $candidate;
        $chosenEncoding = $algo->value;
    }
}

if ($chosenFile === null) {
    // Try fallback order manually if negotiated algo not present
    $order = [
        [AlgorithmEnum::Brotli, 'br'],
        [AlgorithmEnum::Gzip, 'gzip'],
    ];

    foreach ($order as [$alg, $enc]) {
        $file = $original . '.' . $alg->getExtension();
        if (is_file($file) && ($algo === null || $enc === $algo->value)) {
            $chosenFile = $file;
            $chosenEncoding = $enc;
            break;
        }
    }
}

if ($chosenFile !== null) {
    header('Content-Encoding: ' . $chosenEncoding);
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . filesize($chosenFile));
    header('Cache-Control: public, max-age=86400');
    $mime = guess_mime($original);
    header('Content-Type: ' . $mime);

    readfile($chosenFile);
    exit;
}

// Fallback: serve original uncompressed
header('Vary: Accept-Encoding');
header('Cache-Control: public, max-age=86400');
$mime = guess_mime($original);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($original));
readfile($original);

/**
 * Minimal content-type guesser based on file extension to avoid ext-fileinfo dependency in example.
 */
function guess_mime(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return match ($ext) {
        'html', 'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js', 'mjs' => 'application/javascript; charset=utf-8',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'xml' => 'application/xml',
        'txt' => 'text/plain; charset=utf-8',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        default => 'application/octet-stream',
    };
}
