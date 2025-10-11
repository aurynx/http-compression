<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Support;

use Aurynx\HttpCompression\DTO\AlgorithmMetaDto;
use Aurynx\HttpCompression\Enums\AlgorithmEnum;

final class AlgorithmMetadata
{
    /**
     * Get metadata for a specific algorithm.
     */
    public static function for(AlgorithmEnum $algo): AlgorithmMetaDto
    {
        return new AlgorithmMetaDto(
            requiredPhpExtension: $algo->getRequiredExtension(),
            fileExtension: $algo->getExtension(),
            contentEncoding: $algo->getContentEncoding(),
            minLevel: $algo->getMinLevel(),
            maxLevel: $algo->getMaxLevel(),
            defaultLevel: $algo->getDefaultLevel(),
            cpuIntensive: $algo->isCpuIntensive(),
        );
    }

    /**
     * Get metadata for all algorithms.
     *
     * @return array<string, AlgorithmMetaDto> keyed by AlgorithmEnum value
     */
    public static function all(): array
    {
        $result = [];

        foreach (AlgorithmEnum::cases() as $algo) {
            $result[$algo->value] = self::for($algo);
        }

        return $result;
    }

    /**
     * Get metadata for available algorithms only.
     *
     * @return array<string, AlgorithmMetaDto> keyed by AlgorithmEnum value
     */
    public static function available(): array
    {
        $result = [];

        foreach (AlgorithmEnum::available() as $algo) {
            $result[$algo->value] = self::for($algo);
        }

        return $result;
    }
}
