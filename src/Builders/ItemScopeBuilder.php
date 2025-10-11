<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression\Builders;

use Aurynx\HttpCompression\CompressionException;
use Aurynx\HttpCompression\ValueObjects\ItemConfig;

/**
 * Builder for configuring specific items within a CompressorFacade instance
 * Used in CompressorFacade::item() callback for advanced item-specific configuration
 */
final class ItemScopeBuilder
{
    private ?string $inputId = null;
    private ?ItemConfig $config = null;

    /**
     * Select item by ID for configuration
     */
    public function withId(string $id): self
    {
        $this->inputId = $id;

        return $this;
    }

    /**
     * Apply configuration to the selected item
     *
     * @throws CompressionException if no item selected
     */
    public function use(ItemConfig $config): self
    {
        if ($this->inputId === null) {
            throw new CompressionException('No item selected. Call withId() first.');
        }

        $this->config = $config;

        return $this;
    }

    /**
     * Apply configuration using builder callback
     *
     * Example:
     * ```php
     * $compressor->item(function($item) {
     *     $item->withId('file1')->configure(fn($b) =>
     *         $b->withGzip(9)->withBrotli(11)->build()
     *     );
     * });
     * ```
     */
    public function configure(callable $configurator): self
    {
        if ($this->inputId === null) {
            throw new CompressionException('No item selected. Call withId() first.');
        }

        $builder = new ItemConfigBuilder();
        $result = $configurator($builder);

        if (!$result instanceof ItemConfig) {
            throw new CompressionException(
                'Configurator must return ItemConfig (call ->build() on ItemConfigBuilder)',
            );
        }

        $this->config = $result;

        return $this;
    }

    /**
     * Get configured item ID and config (used by parent CompressorFacade)
     * @internal
     * @return array{string, ItemConfig}|null
     */
    public function getConfiguredItem(): ?array
    {
        if ($this->inputId === null || $this->config === null) {
            return null;
        }

        return [$this->inputId, $this->config];
    }
}
