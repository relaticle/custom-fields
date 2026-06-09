<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Relaticle\CustomFields\EntitySystem\EntityManager;

final class RelationConditionConfig
{
    /**
     * Allowed cross-record relation paths for an entity, read from its
     * per-entity configuration (EntityModel::configure(conditionRelations: ...)).
     *
     * @return array<string, string> path => label
     */
    public function relationsFor(string $entityClass): array
    {
        return app(EntityManager::class)
            ->getEntity($entityClass)?->getConditionRelations() ?? [];
    }

    /**
     * Relation sources are never auto-discovered; they are available only when
     * the entity declares at least one relation path in its configuration.
     */
    public function isRelationSourceAvailable(string $entityClass): bool
    {
        return $this->relationsFor($entityClass) !== [];
    }
}
