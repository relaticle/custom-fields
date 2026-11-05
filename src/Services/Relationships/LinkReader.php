<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\LinkEnd;
use Relaticle\CustomFields\Data\ReachedRecord;
use Relaticle\CustomFields\Enums\LinkSide;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

final readonly class LinkReader
{
    /**
     * The far end of a record's active edges, in the order the payload set them.
     *
     * @return array<int, int|string>
     */
    public function orderedIdsFor(Model $record, CustomField $field): array
    {
        $ids = array_map(
            fn (CustomFieldLink $link): int|string => $this->otherEndId($link, $record),
            $this->orderedLinksFor($record, $field),
        );

        return $this->castToTargetKeys($ids, $field->relationshipDefinitionOrFail()->farEnd($field)->entityType);
    }

    /**
     * The same edges the ids come from, kept whole: provenance lives on the row, so a caller
     * that wants to say who linked a record and when reads it here rather than re-querying.
     *
     * @return array<int, CustomFieldLink>
     */
    public function orderedLinksFor(Model $record, CustomField $field): array
    {
        $definition = $field->relationshipDefinitionOrFail();

        return $this->links($record, $definition, $definition->readEnds($field))
            ->sortBy(static fn (CustomFieldLink $link): int => $link->sort_order ?? 0)
            ->values()
            ->all();
    }

    /**
     * The record on the far end of an edge, from the point of view of the one that holds it.
     */
    public function otherEndId(CustomFieldLink $link, Model $record): int|string
    {
        $recordIsFromEnd = $link->from_entity_type === $record->getMorphClass()
            && (string) $link->from_entity_id === (string) $record->getKey();

        return $recordIsFromEnd ? $link->to_entity_id : $link->from_entity_id;
    }

    /**
     * The records within $depth active links of $record, following edges both ways, nearest
     * first and then by entity type and id. Each record appears once, at the hop that first
     * reached it, and the start record never does. Nothing is loaded: the caller authorises
     * and loads what it keeps.
     *
     * @return list<ReachedRecord>
     */
    public function reachable(Model $record, int $depth, int $limit): array
    {
        $visited = [$this->visitKey($record->getMorphClass(), $record->getKey()) => true];
        $frontier = [$record->getMorphClass() => [$record->getKey()]];
        $reached = [];

        for ($hop = 1; $hop <= $depth && $frontier !== [] && count($reached) < $limit; $hop++) {
            $found = array_slice($this->nextHop($frontier, $visited, $hop), 0, $limit - count($reached));
            $frontier = [];

            foreach ($found as $next) {
                $visited[$this->visitKey($next->entityType, $next->entityId)] = true;
                $frontier[$next->entityType][] = $next->entityId;
                $reached[] = $next;
            }
        }

        return $reached;
    }

    /**
     * @param  array<string, list<int|string>>  $frontier
     * @param  array<string, true>  $visited
     * @return list<ReachedRecord>
     */
    private function nextHop(array $frontier, array $visited, int $hop): array
    {
        $found = [];

        foreach (LinkSide::cases() as $side) {
            $far = $side->opposite();

            foreach ($this->hopQuery($frontier, $side)->get() as $link) {
                $entityType = (string) $link->getAttribute($far->typeColumn());
                $entityId = $this->castToTargetKeys([$link->getAttribute($far->idColumn())], $entityType)[0];
                $key = $this->visitKey($entityType, $entityId);

                if (! isset($visited[$key])) {
                    $found[$key] ??= new ReachedRecord($entityType, $entityId, $hop, $link->relationship_id, $far);
                }
            }
        }

        usort($found, static fn (ReachedRecord $a, ReachedRecord $b): int => strcmp($a->entityType, $b->entityType)
            ?: strnatcmp((string) $a->entityId, (string) $b->entityId));

        return $found;
    }

    /**
     * One query per side and hop. The relationship_id subquery is what lets the planner use
     * the cf_links_from_idx and cf_links_to_idx indexes, which lead with it.
     *
     * @param  array<string, list<int|string>>  $frontier
     * @return Builder<CustomFieldLink>
     */
    private function hopQuery(array $frontier, LinkSide $side): Builder
    {
        $far = $side->opposite();
        $definitions = CustomFields::newRelationshipModel();
        $links = CustomFields::newLinkModel();

        return $links->newQuery()
            ->select([$links->getKeyName(), 'relationship_id', $far->typeColumn(), $far->idColumn()])
            ->whereIn('relationship_id', $definitions->newQuery()
                ->whereIn($side->typeColumn(), array_keys($frontier))
                ->select($definitions->getKeyName()))
            ->whereNull('active_until')
            ->where(function (Builder $nested) use ($frontier, $side): void {
                foreach ($frontier as $entityType => $ids) {
                    $nested->orWhere(fn (Builder $end): Builder => $end
                        ->where($side->typeColumn(), $entityType)
                        ->whereIn($side->idColumn(), $ids));
                }
            })
            ->orderBy($links->getKeyName());
    }

    private function visitKey(string $entityType, int|string $entityId): string
    {
        return $entityType.'|'.$entityId;
    }

    /**
     * @param  list<LinkEnd>  $ends
     * @return Collection<int, CustomFieldLink>
     */
    private function links(Model $record, CustomFieldRelationship $definition, array $ends): Collection
    {
        $loaded = $this->loadedLinks($record, $ends);

        if ($loaded instanceof Collection) {
            return $loaded->filter(fn (CustomFieldLink $link): bool => (string) $link->relationship_id === (string) $definition->getKey())->values();
        }

        return $this->query($record, $definition, $ends)->get()->toBase();
    }

    /**
     * A table page loads both relations once, so a per-row read must never fall back to SQL.
     *
     * @param  list<LinkEnd>  $ends
     * @return ?Collection<int, CustomFieldLink>
     */
    private function loadedLinks(Model $record, array $ends): ?Collection
    {
        foreach ($ends as $end) {
            if (! $record->relationLoaded($end->side->linksRelation())) {
                return null;
            }
        }

        $links = new Collection;

        foreach ($ends as $end) {
            /** @var iterable<CustomFieldLink> $related */
            $related = $record->getRelation($end->side->linksRelation());

            foreach ($related as $link) {
                // A symmetric edge whose ends are the same record sits in both relations,
                // and the SQL path returns that row once.
                $links->put((string) $link->getKey(), $link);
            }
        }

        return $links->values();
    }

    /**
     * @param  list<LinkEnd>  $ends
     * @return Builder<CustomFieldLink>
     */
    private function query(Model $record, CustomFieldRelationship $definition, array $ends): Builder
    {
        $query = CustomFields::newLinkModel()
            ->newQuery()
            ->where('relationship_id', $definition->getKey())
            ->whereNull('active_until')
            ->orderBy('sort_order');

        return $query->where(function (Builder $nested) use ($record, $ends): void {
            foreach ($ends as $end) {
                $nested->orWhere(fn (Builder $side): Builder => $side
                    ->where($end->typeColumn(), $record->getMorphClass())
                    ->where($end->idColumn(), $record->getKey()));
            }
        });
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
