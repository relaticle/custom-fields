<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

/**
 * The friendly half of cardinality enforcement (spec 2.1): what a payload would break, in
 * the same words on every path, before the ledger is touched.
 */
final readonly class CardinalityGuard
{
    /**
     * @param  array<int, int|string>  $targetIds
     * @param  array<int, string>  $confirmed  Ids the caller agreed to take from their holder.
     * @return array<int, string>
     */
    public function violations(
        CustomFieldRelationship $definition,
        string $direction,
        int|string|null $recordId,
        array $targetIds,
        array $confirmed = [],
    ): array {
        $targets = $this->normalize($targetIds);
        $messages = [];

        if ($this->endIsSingle($definition, $direction) && count($targets) > 1) {
            $messages[] = __('custom-fields::custom-fields.relationships.errors.single_value');
        }

        if ($targets === []) {
            return $messages;
        }

        if (! $this->endIsSingle($definition, $this->opposite($direction))) {
            return $messages;
        }

        foreach ($this->holders($definition, $direction, $recordId, $targets) as $targetId => $holderId) {
            // The confirmation belongs to the record it was given for, so every other holder
            // in the same payload is still reported.
            if (in_array((string) $targetId, $confirmed, true)) {
                continue;
            }

            $messages[] = __('custom-fields::custom-fields.relationships.errors.already_linked', [
                'record' => $this->label($this->entityType($definition, $this->opposite($direction)), $targetId),
                'holder' => $this->label($this->entityType($definition, $direction), $holderId),
            ]);
        }

        return $messages;
    }

    /**
     * The record already on the far end of an edge is the one a write would displace, so a
     * record keeping its own link reports nothing.
     *
     * @param  array<int, string>  $targets
     * @return array<int|string, int|string>
     */
    private function holders(CustomFieldRelationship $definition, string $direction, int|string|null $recordId, array $targets): array
    {
        $links = $this->activeLinks($definition)
            ->where(function (Builder $nested) use ($definition, $direction, $targets): void {
                foreach ($this->targetEnds($definition, $direction) as $end) {
                    $nested->orWhere(fn (Builder $side): Builder => $side
                        ->where($end.'_entity_type', $this->entityType($definition, $end))
                        ->whereIn($end.'_entity_id', $targets));
                }
            })
            ->get();

        $holders = [];

        foreach ($links as $link) {
            [$targetId, $holderId] = $this->ends($link, $definition, $direction, $targets);

            if ($targetId === null) {
                continue;
            }

            if ((string) $holderId === (string) $recordId) {
                continue;
            }

            $holders[$targetId] ??= $holderId;
        }

        return $holders;
    }

    /**
     * A directional edge is read by end: two entity types share one id space often enough
     * that finding the target by value would name the wrong pair. Only a symmetric edge,
     * canonicalized by value, has to be read that way.
     *
     * @param  array<int, string>  $targets
     * @return array{0: ?string, 1: int|string}
     */
    private function ends(CustomFieldLink $link, CustomFieldRelationship $definition, string $direction, array $targets): array
    {
        if (! $definition->is_symmetric) {
            $targetId = (string) $link->{$this->opposite($direction).'_entity_id'};

            return [
                in_array($targetId, $targets, true) ? $targetId : null,
                $link->{$direction.'_entity_id'},
            ];
        }

        $fromId = (string) $link->from_entity_id;
        $toId = (string) $link->to_entity_id;

        if (in_array($toId, $targets, true)) {
            return [$toId, $link->from_entity_id];
        }

        if (in_array($fromId, $targets, true)) {
            return [$fromId, $link->to_entity_id];
        }

        return [null, $toId];
    }

    /**
     * A symmetric definition stores its ends least first, so a target can sit on either.
     *
     * @return array<int, string>
     */
    private function targetEnds(CustomFieldRelationship $definition, string $direction): array
    {
        if ($definition->is_symmetric) {
            return [CustomFieldRelationship::DIRECTION_FROM, CustomFieldRelationship::DIRECTION_TO];
        }

        return [$this->opposite($direction)];
    }

    /**
     * @return Builder<CustomFieldLink>
     */
    private function activeLinks(CustomFieldRelationship $definition): Builder
    {
        return CustomFields::newLinkModel()
            ->newQuery()
            ->where('relationship_id', $definition->getKey())
            ->whereNull('active_until');
    }

    private function label(string $entityType, int|string $key): string
    {
        $entity = Entities::getEntity($entityType);

        if (! $entity instanceof EntityConfigurationData) {
            return (string) $key;
        }

        $record = $entity->newQuery()->whereKey($key)->first();
        $title = $record?->getAttribute($entity->getPrimaryAttribute());

        return is_scalar($title) && (string) $title !== '' ? (string) $title : (string) $key;
    }

    /**
     * Whether a record on the given end holds a single record on the other. The picker asks
     * before it offers to move a record, so the offer and the refusal read the same rule.
     */
    public function endHoldsOne(CustomFieldRelationship $definition, string $end): bool
    {
        return $this->endIsSingle($definition, $end);
    }

    private function endIsSingle(CustomFieldRelationship $definition, string $end): bool
    {
        return $end === CustomFieldRelationship::DIRECTION_TO
            ? $definition->cardinality->toSideIsSingle()
            : $definition->cardinality->fromSideIsSingle();
    }

    private function entityType(CustomFieldRelationship $definition, string $end): string
    {
        return $end === CustomFieldRelationship::DIRECTION_TO
            ? $definition->to_entity_type
            : $definition->from_entity_type;
    }

    private function opposite(string $end): string
    {
        return $end === CustomFieldRelationship::DIRECTION_FROM
            ? CustomFieldRelationship::DIRECTION_TO
            : CustomFieldRelationship::DIRECTION_FROM;
    }

    /**
     * @param  array<int, int|string>  $targetIds
     * @return array<int, string>
     */
    private function normalize(array $targetIds): array
    {
        return array_values(array_unique(array_map(
            static fn (int|string $id): string => (string) $id,
            $targetIds,
        )));
    }
}
