<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Fluent configurator for individual compression items
 */
final class CompressionItemConfigurator
{
    public function __construct(
        private readonly CompressionBuilder $builder,
        private readonly string $identifier
    ) {
    }

    /**
     * Set algorithms for this specific item
     *
     * @param CompressionAlgorithmEnum|array<CompressionAlgorithmEnum|string, int>|null $algorithms
     * @return CompressionBuilder Returns to the main builder for chaining
     */
    public function withAlgorithms(
        CompressionAlgorithmEnum|array|null $algorithms
    ): CompressionBuilder {
        $this->builder->updateAlgorithms($this->identifier, $algorithms);

        return $this->builder;
    }

    /**
     * Get the identifier of this item
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
