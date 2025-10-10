<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

/**
 * Represents a single item to be compressed
 */
final readonly class CompressionItem
{
    /** @var int Default max size: 50MB */
    private const int DEFAULT_MAX_SIZE = 50 * 1024 * 1024;

    private int $maxSize;

    public function __construct(
        private string $content,
        private bool $isFile,
        private ?string $identifier = null,
        ?int $maxSize = null
    ) {
        $this->maxSize = $maxSize ?? self::DEFAULT_MAX_SIZE;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isFile(): bool
    {
        return $this->isFile;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Get the size of content/file in bytes
     *
     * @throws CompressionException
     */
    public function size(): int
    {
        if (!$this->isFile) {
            return strlen($this->content);
        }

        if (!file_exists($this->content)) {
            throw new CompressionException(
                sprintf('File not found: %s', $this->content),
                ErrorCode::FILE_NOT_FOUND->value
            );
        }

        $size = filesize($this->content);

        if ($size === false) {
            throw new CompressionException(
                sprintf('Failed to get file size: %s (check permissions/SELinux)', $this->content),
                ErrorCode::FILE_NOT_READABLE->value
            );
        }

        return $size;
    }

    /**
     * Ensure the payload is within the configured size limit
     *
     * @throws CompressionException
     */
    public function ensureWithinLimit(): void
    {
        $size = $this->isFile ? $this->size() : strlen($this->content);
        if ($size > $this->maxSize) {
            if ($this->isFile) {
                throw new CompressionException(
                    sprintf(
                        'File size (%d bytes) exceeds maximum allowed (%d bytes): %s',
                        $size,
                        $this->maxSize,
                        $this->content
                    ),
                    ErrorCode::PAYLOAD_TOO_LARGE->value
                );
            }

            throw new CompressionException(
                sprintf(
                    'Content size (%d bytes) exceeds maximum allowed (%d bytes)',
                    $size,
                    $this->maxSize
                ),
                ErrorCode::PAYLOAD_TOO_LARGE->value
            );
        }
    }

    /**
     * Read file content if this is a file item
     *
     * @throws CompressionException
     */
    public function readContent(): string
    {
        if (!$this->isFile) {
            $this->ensureWithinLimit();

            return $this->content;
        }

        if (!file_exists($this->content)) {
            throw new CompressionException(
                sprintf('File not found: %s', $this->content),
                ErrorCode::FILE_NOT_FOUND->value
            );
        }

        if (!is_readable($this->content)) {
            throw new CompressionException(
                sprintf('File not readable: %s (check permissions/SELinux)', $this->content),
                ErrorCode::FILE_NOT_READABLE->value
            );
        }

        $this->ensureWithinLimit();

        $content = file_get_contents($this->content);

        if ($content === false) {
            throw new CompressionException(
                sprintf('Failed to read file: %s (check permissions/SELinux)', $this->content),
                ErrorCode::FILE_NOT_READABLE->value
            );
        }

        return $content;
    }
}
