<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Fluent configurator for individual compression items
 */
final readonly class CompressionItemConfigurator
{
    public function __construct(
        private CompressionBuilder $builder,
        private string $identifier
    ) {
    }

    /**
     * Set algorithms for this specific item
     *
     * @param  CompressionAlgorithmEnum|iterable|null  $algorithms
     *
     * @return CompressionBuilder Returns to the main builder for chaining
     */
    public function withAlgorithms(
        CompressionAlgorithmEnum|iterable|null $algorithms
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
