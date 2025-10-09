<?php

declare(strict_types=1);

namespace Ayrunx\HttpCompression;

/**
 * Fluent configurator for individual compression items
 */
final readonly class ItemConfigurator
{
    public function __construct(
        private Builder $builder,
        private string $identifier
    ) {
    }

    /**
     * Set algorithms for this specific item
     *
     * @param  AlgorithmEnum|iterable|null  $algorithms
     *
     * @return Builder Returns to the main builder for chaining
     */
    public function withAlgorithms(
        AlgorithmEnum|iterable|null $algorithms
    ): Builder {
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
