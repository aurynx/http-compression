<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Attributes;

use Attribute;

/**
 * Metadata attribute for compression algorithms
 * Attach to enum cases of AlgorithmEnum (TARGET_CLASS_CONSTANT)
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class AlgorithmAttribute
{
    public function __construct(
        public string $requiredPhpExtension,
        public string $fileExtension,
        public string $contentEncoding,
        public int $minLevel,
        public int $maxLevel,
        public int $defaultLevel,
        public bool $cpuIntensive = false,
    ) {
    }
}
