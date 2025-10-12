<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Enums;

use Aurynx\HttpCompression\CompressionException;

/**
 * Overwrite policy for file writes when targets already exist.
 */
enum OverwritePolicyEnum: string
{
    case Fail = 'fail';
    case Replace = 'replace';
    case Skip = 'skip';

    /**
     * Parse option value to enum.
     */
    public static function fromOption(null|string|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null) {
            return self::Fail;
        }

        return match ($value) {
            'fail' => self::Fail,
            'replace' => self::Replace,
            'skip' => self::Skip,
            default => throw new CompressionException('Invalid overwritePolicy: ' . $value),
        };
    }

    public function isReplace(): bool
    {
        return $this === self::Replace;
    }

    public function isSkip(): bool
    {
        return $this === self::Skip;
    }
}
