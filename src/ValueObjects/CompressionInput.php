<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\ValueObjects;

use Aurynx\HttpCompression\Enums\InputTypeEnum;

/**
 * Base class for compression input data
 * Immutable value object
 */
abstract readonly class CompressionInput
{
    public function __construct(
        public string $id,
        public InputTypeEnum $type,
    ) {
    }

    /**
     * Get input data as a string or resource
     *
     * @return string|resource
     */
    abstract public function getData(): mixed;

    /**
     * Get the size of input data in bytes
     */
    abstract public function getSize(): int;
}
