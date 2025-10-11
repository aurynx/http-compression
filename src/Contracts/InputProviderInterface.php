<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Contracts;

use Aurynx\HttpCompression\ValueObjects\CompressionInput;

/**
 * Interface for input providers
 * Generates CompressionInput instances from various sources
 */
interface InputProviderInterface
{
    /**
     * Provide compression inputs
     *
     * @return CompressionInput[]
     */
    public function provide(): array;
}
