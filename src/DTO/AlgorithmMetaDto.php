<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\DTO;

final readonly class AlgorithmMetaDto
{
    public function __construct(
        public string $requiredPhpExtension,
        public string $fileExtension,
        public string $contentEncoding,
        public int $minLevel,
        public int $maxLevel,
        public int $defaultLevel,
        public bool $cpuIntensive,
    ) {
    }
}
