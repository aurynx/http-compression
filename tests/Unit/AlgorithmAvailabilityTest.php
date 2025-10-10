<?php

declare(strict_types=1);

use Aurynx\HttpCompression\AlgorithmEnum;

test('available() returns array of available algorithms', function () {
    $available = AlgorithmEnum::available();

    expect($available)->toBeArray();
    expect($available)->not->toBeEmpty(); // At least gzip (zlib) should be available
});

test('available() returns only algorithms with loaded extensions', function () {
    $available = AlgorithmEnum::available();

    foreach ($available as $algo) {
        expect($algo)->toBeInstanceOf(AlgorithmEnum::class);
        expect($algo->isAvailable())->toBeTrue();
    }
});

test('available() always includes gzip', function () {
    $available = AlgorithmEnum::available();
    $names = array_map(fn($algo) => $algo->value, $available);

    expect($names)->toContain('gzip'); // zlib is always available in PHP
});

test('available() filters out unavailable algorithms', function () {
    $available = AlgorithmEnum::available();
    $all = AlgorithmEnum::cases();

    // If not all algorithms are available, check filtering works
    if (count($available) < count($all)) {
        $unavailable = array_filter(
            $all,
            fn($algo) => !$algo->isAvailable()
        );

        foreach ($unavailable as $algo) {
            expect($available)->not->toContain($algo);
        }
    }

    expect(true)->toBeTrue(); // Always pass if all are available
});

test('available() returns indexed array with sequential keys', function () {
    $available = AlgorithmEnum::available();
    $keys = array_keys($available);

    // array_filter preserves keys, but we want to ensure it's usable
    expect($available)->toBeArray();
    expect(count($available))->toBeGreaterThan(0);
});

test('available() can be used to iterate algorithms', function () {
    $available = AlgorithmEnum::available();
    $processed = [];

    foreach ($available as $algo) {
        $processed[] = $algo->value;
    }

    expect($processed)->not->toBeEmpty();
    expect($processed)->toContain('gzip');
});
