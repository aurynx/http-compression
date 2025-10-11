<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\ValueObjects;

use Aurynx\HttpCompression\Attributes\OutputFormatAttribute;
use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\Enums\InputTypeEnum;
use Aurynx\HttpCompression\Enums\OutputModeEnum;

/**
 * File input for compression
 * Immutable value object with path validation
 */
#[OutputFormatAttribute(OutputModeEnum::Directory, OutputModeEnum::InMemory, OutputModeEnum::Stream)]
final readonly class FileInput extends CompressionInput
{
    public function __construct(
        string $id,
        public string $path,
    ) {
        // Validate file exists
        if (!file_exists($path)) {
            throw new CompressionException("File not found: {$path}");
        }

        if (!is_file($path)) {
            throw new CompressionException("Path is not a file: {$path}");
        }

        if (!is_readable($path)) {
            throw new CompressionException("File not readable: {$path}");
        }

        parent::__construct($id, InputTypeEnum::File);
    }

    /**
     * @return resource
     */
    public function getData(): mixed
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new CompressionException("Failed to open file: {$this->path}");
        }

        return $handle;
    }

    public function getSize(): int
    {
        $size = filesize($this->path);

        if ($size === false) {
            throw new CompressionException("Failed to get file size: {$this->path}");
        }

        return $size;
    }
}
