<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

final readonly class LinkReader
{
    /**
     * The far end of a record's active edges, in the order the payload set them.
     *
     * @return array<int, int|string>
     */
    public function orderedIdsFor(Model $record, CustomFieldRelationship $definition, string $direction): array
    {
        $ids = array_map(
            fn (CustomFieldLink $link): int|string => $this->otherEndId($link, $record, $direction),
            $this->orderedLinksFor($record, $definition, $direction),
        );

        return $this->castToTargetKeys($ids, $this->targetEntityType($definition, $direction));
    }

    /**
     * The same edges the ids come from, kept whole: provenance lives on the row, so a caller
     * that wants to say who linked a record and when reads it here rather than re-querying.
     *
     * @return array<int, CustomFieldLink>
     */
    public function orderedLinksFor(Model $record, CustomFieldRelationship $definition, string $direction): array
    {
        return $this->links($record, $definition, $direction)
            ->sortBy(static fn (CustomFieldLink $link): int => $link->sort_order ?? 0)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CustomFieldLink>
     */
    private function links(Model $record, CustomFieldRelationship $definition, string $direction): Collection
    {
        $loaded = $this->loadedLinks($record, $direction);

        if ($loaded instanceof Collection) {
            return $loaded->filter(fn (CustomFieldLink $link): bool => $link->active_until === null
                && (string) $link->relationship_id === (string) $definition->getKey())->values();
        }

        return $this->query($record, $definition, $direction)->get()->toBase();
    }

    /**
     * A table page loads both relations once, so a per-row read must never fall back to SQL.
     *
     * @return ?Collection<int, CustomFieldLink>
     */
    private function loadedLinks(Model $record, string $direction): ?Collection
    {
        $relations = $this->relationNames($direction);

        foreach ($relations as $relation) {
            if (! $record->relationLoaded($relation)) {
                return null;
            }
        }

        $links = new Collection;

        foreach ($relations as $relation) {
            /** @var iterable<CustomFieldLink> $related */
            $related = $record->getRelation($relation);

            foreach ($related as $link) {
                // A symmetric edge whose ends are the same record sits in both relations,
                // and the SQL path returns that row once.
                $links->put((string) $link->getKey(), $link);
            }
        }

        return $links->values();
    }

    /**
     * @return array<int, string>
     */
    private function relationNames(string $direction): array
    {
        return match ($direction) {
            CustomFieldRelationship::DIRECTION_FROM => ['outgoingLinks'],
            CustomFieldRelationship::DIRECTION_TO => ['incomingLinks'],
            default => ['outgoingLinks', 'incomingLinks'],
        };
    }

    /**
     * @return Builder<CustomFieldLink>
     */
    private function query(Model $record, CustomFieldRelationship $definition, string $direction): Builder
    {
        $query = CustomFields::newLinkModel()
            ->newQuery()
            ->where('relationship_id', $definition->getKey())
            ->whereNull('active_until')
            ->orderBy('sort_order');

        return $query->where(function (Builder $nested) use ($record, $direction): void {
            foreach ($this->ends($direction) as $end) {
                $nested->orWhere(fn (Builder $side): Builder => $side
                    ->where($end.'_entity_type', $record->getMorphClass())
                    ->where($end.'_entity_id', $record->getKey()));
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function ends(string $direction): array
    {
        return match ($direction) {
            CustomFieldRelationship::DIRECTION_FROM => [CustomFieldRelationship::DIRECTION_FROM],
            CustomFieldRelationship::DIRECTION_TO => [CustomFieldRelationship::DIRECTION_TO],
            default => [CustomFieldRelationship::DIRECTION_FROM, CustomFieldRelationship::DIRECTION_TO],
        };
    }

    /**
     * The record on the far end of an edge, from the point of view of the one that holds it.
     */
    public function otherEndId(CustomFieldLink $link, Model $record, string $direction): int|string
    {
        if ($direction === CustomFieldRelationship::DIRECTION_FROM) {
            return $link->to_entity_id;
        }

        if ($direction === CustomFieldRelationship::DIRECTION_TO) {
            return $link->from_entity_id;
        }

        $recordIsFromEnd = $link->from_entity_type === $record->getMorphClass()
            && (string) $link->from_entity_id === (string) $record->getKey();

        return $recordIsFromEnd ? $link->to_entity_id : $link->from_entity_id;
    }

    private function targetEntityType(CustomFieldRelationship $definition, string $direction): string
    {
        return $direction === CustomFieldRelationship::DIRECTION_TO
            ? $definition->from_entity_type
            : $definition->to_entity_type;
    }

    /**
     * Morph ids come back as strings on some drivers, and every consumer compares them
     * against real model keys, so they take the target's own key type.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int|string>
     */
    private function castToTargetKeys(array $ids, string $entityType): array
    {
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            return $ids;
        }

        if ((new $entityClass)->getKeyType() !== 'int') {
            return array_map(strval(...), $ids);
        }

        return array_map(intval(...), $ids);
    }
}
