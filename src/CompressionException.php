<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

use Aurynx\HttpCompression\Enums\ErrorCodeEnum;
use RuntimeException;
use Throwable;

class CompressionException extends RuntimeException
{
    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getPath(): ?string
    {
        $val = $this->context['path'] ?? null;

        return is_string($val) ? $val : null;
    }

    public function getBytesToWrite(): ?int
    {
        $val = $this->context['bytesToWrite'] ?? null;

        return is_int($val) ? $val : null;
    }

    public function isDirectoryWritable(): ?bool
    {
        $val = $this->context['directoryWritable'] ?? null;

        return is_bool($val) ? $val : null;
    }

    public function getDiskFreeSpace(): ?int
    {
        $val = $this->context['diskFreeSpace'] ?? null;

        return is_int($val) ? $val : null;
    }

    public function getLastPhpError(): ?string
    {
        $val = $this->context['lastPhpError'] ?? null;

        return is_string($val) ? $val : null;
    }

    public function getErrorCode(): ?ErrorCodeEnum
    {
        return ErrorCodeEnum::tryFrom($this->code);
    }
}
