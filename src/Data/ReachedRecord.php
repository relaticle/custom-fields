<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\LinkSide;
use Spatie\LaravelData\Data;

/**
 * A record a reachability walk arrived at, identified but not loaded: the caller decides
 * whether it may see it before loading it.
 */
final class ReachedRecord extends Data
{
    public function __construct(
        public readonly string $entityType,
        public readonly int|string $entityId,
        public readonly int $depth,
        public readonly int|string $relationshipId,
        public readonly LinkSide $side,
    ) {}
}
