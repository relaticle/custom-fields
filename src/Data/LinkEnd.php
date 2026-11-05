<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\LinkSide;
use Spatie\LaravelData\Data;

final class LinkEnd extends Data
{
    public function __construct(
        public readonly LinkSide $side,
        public readonly string $entityType,
        public readonly int|string|null $fieldId,
    ) {}

    public function typeColumn(): string
    {
        return $this->side->typeColumn();
    }

    public function idColumn(): string
    {
        return $this->side->idColumn();
    }
}
