<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Represents a single item to be compressed
 */
final readonly class CompressionItem
{
    /** @var int Default max size: 50MB */
    private const int DEFAULT_MAX_SIZE = 50 * 1024 * 1024;

    public function __construct(
        private string $content,
        private bool $isFile,
        private ?string $identifier = null,
        private int $maxSize = self::DEFAULT_MAX_SIZE
    ) {
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
     * @return int
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
                CompressionErrorCode::FILE_NOT_FOUND->value
            );
        }

        $size = filesize($this->content);

        if ($size === false) {
            throw new CompressionException(
                sprintf('Failed to get file size: %s', $this->content),
                CompressionErrorCode::FILE_NOT_READABLE->value
            );
        }

        return $size;
    }

    /**
     * Read file content if this is a file item
     *
     * @throws CompressionException
     */
    public function readContent(): string
    {
        if (!$this->isFile) {
            $size = strlen($this->content);
            if ($size > $this->maxSize) {
                throw new CompressionException(
                    sprintf(
                        'Content size (%d bytes) exceeds maximum allowed (%d bytes)',
                        $size,
                        $this->maxSize
                    ),
                    CompressionErrorCode::FILE_TOO_LARGE->value
                );
            }
            return $this->content;
        }

        if (!file_exists($this->content)) {
            throw new CompressionException(
                sprintf('File not found: %s', $this->content),
                CompressionErrorCode::FILE_NOT_FOUND->value
            );
        }

        if (!is_readable($this->content)) {
            throw new CompressionException(
                sprintf('File not readable: %s', $this->content),
                CompressionErrorCode::FILE_NOT_READABLE->value
            );
        }

        $size = $this->size();

        if ($size > $this->maxSize) {
            throw new CompressionException(
                sprintf(
                    'File size (%d bytes) exceeds maximum allowed (%d bytes): %s',
                    $size,
                    $this->maxSize,
                    $this->content
                ),
                CompressionErrorCode::FILE_TOO_LARGE->value
            );
        }

        $content = file_get_contents($this->content);

        if ($content === false) {
            throw new CompressionException(
                sprintf('Failed to read file: %s', $this->content),
                CompressionErrorCode::FILE_NOT_READABLE->value
            );
        }

        return $content;
    }
}
