<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\ValueObjects;

use Aurynx\HttpCompression\Builders\ItemConfigBuilder;

/**
 * Configuration for a single compression item
 * Immutable value object
 */
final readonly class ItemConfig
{
    public function __construct(
        public AlgorithmSet $algorithms,
        public ?int $maxBytes = null,
    ) {
    }

    /**
     * Create a builder for fluent configuration
     */
    public static function create(): ItemConfigBuilder
    {
        return new ItemConfigBuilder();
    }
}
