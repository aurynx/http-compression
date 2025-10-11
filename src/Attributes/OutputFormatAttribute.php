<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Attributes;

use Attribute;
use Aurynx\HttpCompression\Enums\OutputModeEnum;

/**
 * Attribute to specify allowed output formats for input types
 * (Future-proofing for compile-time validation)
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class OutputFormatAttribute
{
    /** @var OutputModeEnum[] */
    public array $allowedModes;

    public function __construct(OutputModeEnum ...$allowedModes)
    {
        $this->allowedModes = $allowedModes;
    }
}
