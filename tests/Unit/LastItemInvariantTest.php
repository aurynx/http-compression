<?php

declare(strict_types=1);

use Ayrunx\HttpCompression\CompressionBuilder;

it('maintains "last" invariant after deleting non-last then last', function () {
    $builder = new CompressionBuilder();

    // Add three items with explicit identifiers to make assertions stable
    $builder->add('A', null, 'A');
    $builder->add('B', null, 'B');
    $builder->add('C', null, 'C');

    // Last should be the last inserted
    expect($builder->forLast()->getIdentifier())->toBe('C');

    // Delete a non-last item; last should remain unchanged
    $builder->delete('B');
    expect($builder->forLast()->getIdentifier())->toBe('C');

    // Delete the last item; last should fall back to the last remaining by insertion order
    $builder->delete('C');
    expect($builder->forLast()->getIdentifier())->toBe('A');
});
