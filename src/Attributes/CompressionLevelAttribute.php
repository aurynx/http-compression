<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Attribute for validating compression levels on enums
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CompressionLevelAttribute
{
    public function __construct(
        public int $min,
        public int $max,
    ) {
    }

    /**
     * Validate that level is within the allowed range
     *
     * @throws InvalidArgumentException
     */
    public function validate(int $level): void
    {
        if ($level < $this->min || $level > $this->max) {
            throw new InvalidArgumentException(
                "Level must be between {$this->min} and {$this->max}, got {$level}",
            );
        }
    }
}
