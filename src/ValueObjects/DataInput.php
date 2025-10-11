<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\ValueObjects;

use Aurynx\HttpCompression\Attributes\OutputFormatAttribute;
use Aurynx\HttpCompression\Enums\InputTypeEnum;
use Aurynx\HttpCompression\Enums\OutputModeEnum;

/**
 * In-memory data input for compression
 * Immutable value object
 */
#[OutputFormatAttribute(OutputModeEnum::InMemory, OutputModeEnum::Stream)]
final readonly class DataInput extends CompressionInput
{
    public function __construct(
        string $id,
        public string $data,
    ) {
        parent::__construct($id, InputTypeEnum::Data);
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getSize(): int
    {
        return strlen($this->data);
    }
}
