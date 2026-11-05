<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\Scopes\CustomFieldsActivableScope;

/**
 * @template TModelClass of CustomField
 *
 * @extends Builder<TModelClass>
 */
class CustomFieldQueryBuilder extends Builder
{
    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function forType(string $type): self
    {
        return $this->where('type', $type);
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function forEntity(string $model): self
    {
        $entityType = (Entities::getEntity($model)?->getAlias()) ?? $model;

        return $this->where('entity_type', $entityType);
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function forMorphEntity(string $entity): self
    {
        $entityType = (Entities::getEntity($entity)?->getAlias()) ?? $entity;

        return $this->where('entity_type', $entityType);
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function encrypted(): self
    {
        return $this->whereJsonContains('settings->encrypted', true);
    }

    /**
     * Scope to filter non-encrypted fields including NULL settings
     */
    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function nonEncrypted(): self
    {
        return $this->where(function (Builder $query): void {
            $query->whereNull('settings')->orWhereJsonDoesntContain('settings->encrypted', true);
        });
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function visibleInList(): self
    {
        return $this->where(function (Builder $query): void {
            $query->whereNull('settings')->orWhereJsonDoesntContain('settings->visible_in_list', false);
        });
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function visibleInView(): self
    {
        return $this->where(function (Builder $query): void {
            $query->whereNull('settings')->orWhereJsonDoesntContain('settings->visible_in_view', false);
        });
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function searchable(): self
    {
        return $this->whereJsonContains('settings->searchable', true);
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function active(): self
    {
        return $this->where('active', true);
    }

    /**
     * Fields that are a slot of some relationship definition, on either end.
     *
     * @return CustomFieldQueryBuilder<TModelClass>
     */
    public function linkFields(): self
    {
        return $this->where(function (Builder $query): void {
            $query->has('fromEndDefinition')->orHas('toEndDefinition');
        });
    }

    /**
     * Loads what relationshipDefinition() reads, so a list of fields answers it with no query per field.
     *
     * @return CustomFieldQueryBuilder<TModelClass>
     */
    public function withRelationshipDefinition(): self
    {
        return $this->with(['fromEndDefinition', 'toEndDefinition']);
    }

    /** @return CustomFieldQueryBuilder<TModelClass> */
    public function withDeactivated(bool $withDeactivated = true): self
    {
        if (! $withDeactivated) {
            return $this;
        }

        return $this->withoutGlobalScope(CustomFieldsActivableScope::class);
    }
}
