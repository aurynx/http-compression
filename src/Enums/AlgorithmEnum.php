<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Enums;

use Aurynx\HttpCompression\Attributes\AlgorithmAttribute;
use Aurynx\HttpCompression\CompressionException;

enum AlgorithmEnum: string
{
    #[AlgorithmAttribute(
        requiredPhpExtension: 'zlib',
        fileExtension: 'gz',
        contentEncoding: 'gzip',
        minLevel: 1,
        maxLevel: 9,
        defaultLevel: 6,
        cpuIntensive: false,
    )]
    case Gzip = 'gzip';

    #[AlgorithmAttribute(
        requiredPhpExtension: 'brotli',
        fileExtension: 'br',
        contentEncoding: 'br',
        minLevel: 0,
        maxLevel: 11,
        defaultLevel: 4,
        cpuIntensive: true,
    )]
    case Brotli = 'br';

    #[AlgorithmAttribute(
        requiredPhpExtension: 'zstd',
        fileExtension: 'zst',
        contentEncoding: 'zstd',
        minLevel: 1,
        maxLevel: 22,
        defaultLevel: 3,
        cpuIntensive: true,
    )]
    case Zstd = 'zstd';

    private function meta(): AlgorithmAttribute
    {
        /** @var array<string, AlgorithmAttribute> $cache */
        static $cache = [];

        $key = $this->value;

        if (isset($cache[$key])) {
            /** @var AlgorithmAttribute $cached */
            $cached = $cache[$key];

            return $cached;
        }

        $ref = new \ReflectionEnumUnitCase(self::class, $this->name);
        $attrs = $ref->getAttributes(AlgorithmAttribute::class);

        if ($attrs === []) {
            throw new CompressionException("Missing AlgorithmAttribute for {$this->name}");
        }

        /** @var AlgorithmAttribute $meta */
        $meta = $attrs[0]->newInstance();
        $cache[$key] = $meta;

        return $meta;
    }

    public function isAvailable(): bool
    {
        $ext = $this->meta()->requiredPhpExtension;

        return extension_loaded($ext);
    }

    /**
     * @return array<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $algo): bool => $algo->isAvailable(),
        ));
    }

    public function getDefaultLevel(): int
    {
        return $this->meta()->defaultLevel;
    }

    public function getMinLevel(): int
    {
        return $this->meta()->minLevel;
    }

    public function getMaxLevel(): int
    {
        return $this->meta()->maxLevel;
    }

    public function validateLevel(int $level): void
    {
        $meta = $this->meta();

        if ($level < $meta->minLevel || $level > $meta->maxLevel) {
            throw new CompressionException(
                sprintf(
                    '%s level out of range: level=%d, allowed=[%d..%d]',
                    $this->name,
                    $level,
                    $meta->minLevel,
                    $meta->maxLevel,
                ),
            );
        }
    }

    public function getRequiredExtension(): string
    {
        return $this->meta()->requiredPhpExtension;
    }

    public function getExtension(): string
    {
        return $this->meta()->fileExtension;
    }

    public function getContentEncoding(): string
    {
        return $this->meta()->contentEncoding;
    }

    public function isCpuIntensive(): bool
    {
        return $this->meta()->cpuIntensive;
    }
}
