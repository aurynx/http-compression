<?php

declare(strict_types=1);

namespace Aurynx\HttpCompression;

/**
 * Fluent configurator for individual compression items
 */
final readonly class ItemConfigurator
{
    public function __construct(
        private CompressionBuilder $builder,
        private string $identifier
    ) {
    }

    /**
     * Set algorithms for this specific item
     *
     *
     * @return CompressionBuilder Returns to the main builder for chaining
     */
    public function withAlgorithms(
        AlgorithmEnum|iterable|null $algorithms
    ): CompressionBuilder {
        $this->builder->updateAlgorithms($this->identifier, $algorithms);

        return $this->builder;
    }

    /**
     * Get the identifier of this item
     *
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
