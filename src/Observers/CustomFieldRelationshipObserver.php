<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Observers;

use Relaticle\CustomFields\Models\CustomFieldRelationship;
use RuntimeException;

final class CustomFieldRelationshipObserver
{
    /**
     * Ends and symmetry describe every edge already stored, including its canonical order,
     * so they are fixed once a definition exists. Cardinality stays changeable.
     */
    public function updating(CustomFieldRelationship $relationship): void
    {
        if (! $relationship->isDirty(['from_entity_type', 'to_entity_type', 'is_symmetric'])) {
            return;
        }

        throw new RuntimeException('Cannot change the entity types or the symmetry of an existing relationship.');
    }
}
