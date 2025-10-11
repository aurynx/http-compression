<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Enums;

/**
 * Output mode for compressed data
 */
enum OutputModeEnum
{
    case Directory;
    case InMemory;
    case Stream;
}
