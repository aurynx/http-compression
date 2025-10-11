<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Enums;

/**
 * Input type for compression operations
 *
 * Note: Glob is NOT an InputTypeEnum — it's a provider that generates FileInputs
 */
enum InputTypeEnum
{
    case File;
    case Data;
}
