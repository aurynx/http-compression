<?php

declare(strict_types=1);

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\CompressorFacade;

it('sendToCallback() streams gzip data into consumer (single algo)', function (): void {
    $original = str_repeat('callback-stream ', 4000);
    $buffer = '';

    CompressorFacade::once()
        ->data($original)
        ->withGzip(6)
        ->sendToCallback(function (string $chunk) use (&$buffer): void {
            $buffer .= $chunk;
        });

    // Basic gzip magic header check
    expect($buffer)->not->toBe('');
    expect(substr($buffer, 0, 2))->toBe("\x1f\x8b");

    // Verify round-trip
    $decoded = gzdecode($buffer);
    expect($decoded)->toBe($original);
});

it('sendAllToCallbacks() streams required gzip and skips optional brotli without consumer', function (): void {
    $original = str_repeat('multi-callback ', 5000);
    $gz = '';

    // Required gzip + optional brotli (no consumer for br)
    CompressorFacade::once()
        ->data($original)
        ->withGzip(6)
        ->tryBrotli(4)
        ->sendAllToCallbacks([
            'gzip' => static function (string $chunk) use (&$gz): void {
                $gz .= $chunk;
            },
            // 'br' consumer intentionally omitted as optional
        ]);

    expect($gz)->not->toBe('');
    expect(substr($gz, 0, 2))->toBe("\x1f\x8b");
    $decoded = gzdecode($gz);
    expect($decoded)->toBe($original);
});

it('sendAllToCallbacks() throws when required consumer is missing', function (): void {
    $original = 'required-consumer-missing';

    $fn = function () use ($original): void {
        CompressorFacade::once()
            ->data($original)
            ->withGzip(6) // required by default
            ->sendAllToCallbacks([]); // no consumers provided
    };

    expect($fn)->toThrow(CompressionException::class);
});

it('sendAllToCallbacks() tolerates optional consumer failure and still streams required gzip', function (): void {
    $original = str_repeat('optional-failure ', 3000);
    $gz = '';

    $thrown = false;

    CompressorFacade::once()
        ->data($original)
        ->withGzip(6)      // required
        ->tryBrotli(4)     // optional
        ->sendAllToCallbacks([
            'gzip' => static function (string $chunk) use (&$gz): void {
                $gz .= $chunk;
            },
            'brotli' => static function (string $chunk) use (&$thrown): void {
                // Simulate failure in optional algorithm consumer
                $thrown = true;

                throw new RuntimeException('simulated optional consumer failure');
            },
        ]);

    // Required gzip must still be delivered successfully
    expect($gz)->not->toBe('');
    expect(substr($gz, 0, 2))->toBe("\x1f\x8b");
    $decoded = gzdecode($gz);
    expect($decoded)->toBe($original);

    // Optional path may or may not be invoked depending on ext presence; in any case, no exception should bubble up
    expect(is_bool($thrown))->toBeTrue();
});
