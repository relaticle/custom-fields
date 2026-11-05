<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Collections;

use Illuminate\Support\Collection;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Enums\FieldCapability;

/**
 * @extends Collection<array-key, FieldTypeData>
 */
final class FieldTypeCollection extends Collection
{
    public function acceptsArbitraryValues(): static
    {
        return $this->filter(fn (FieldTypeData $fieldType): bool => $fieldType->supports(FieldCapability::AcceptsArbitraryValues));
    }

    public function selectable(?string $except = null): static
    {
        return $this->filter(fn (FieldTypeData $fieldType): bool => ! $fieldType->supports(FieldCapability::SystemOnly) || $fieldType->key === $except);
    }
}
