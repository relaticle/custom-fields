<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Data\LinkEnd;
use Relaticle\CustomFields\Enums\LinkSide;
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
     * @param  LinkEnd  $end  The end the writing record sits on.
     * @param  array<int, int|string>  $targetIds
     * @param  array<int, string>  $confirmed  Ids the caller agreed to take from their holder.
     * @return array<int, string>
     */
    public function violations(
        CustomFieldRelationship $definition,
        LinkEnd $end,
        int|string|null $recordId,
        array $targetIds,
        array $confirmed = [],
    ): array {
        $targets = $this->normalize($targetIds);
        $messages = [];
        $far = $definition->end($end->side->opposite());

        if ($this->endHoldsOne($definition, $end->side) && count($targets) > 1) {
            $messages[] = __('custom-fields::custom-fields.relationships.errors.single_value');
        }

        if ($targets === []) {
            return $messages;
        }

        if (! $this->endHoldsOne($definition, $far->side)) {
            return $messages;
        }

        foreach ($this->holders($definition, $end, $recordId, $targets) as $targetId => $holderId) {
            // The confirmation belongs to the record it was given for, so every other holder
            // in the same payload is still reported.
            if (in_array((string) $targetId, $confirmed, true)) {
                continue;
            }

            $messages[] = __('custom-fields::custom-fields.relationships.errors.already_linked', [
                'record' => $this->label($far->entityType, $targetId),
                'holder' => $this->label($end->entityType, $holderId),
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
    private function holders(CustomFieldRelationship $definition, LinkEnd $end, int|string|null $recordId, array $targets): array
    {
        $links = $this->activeLinks($definition)
            ->where(function (Builder $nested) use ($definition, $end, $targets): void {
                foreach ($this->targetEnds($definition, $end) as $targetEnd) {
                    $nested->orWhere(fn (Builder $side): Builder => $side
                        ->where($targetEnd->typeColumn(), $targetEnd->entityType)
                        ->whereIn($targetEnd->idColumn(), $targets));
                }
            })
            ->get();

        $holders = [];

        foreach ($links as $link) {
            [$targetId, $holderId] = $this->targetAndHolder($link, $definition, $end, $targets);

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
    private function targetAndHolder(CustomFieldLink $link, CustomFieldRelationship $definition, LinkEnd $end, array $targets): array
    {
        if (! $definition->is_symmetric) {
            $targetId = (string) $link->{$end->side->opposite()->idColumn()};

            return [
                in_array($targetId, $targets, true) ? $targetId : null,
                $link->{$end->idColumn()},
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
     * @return list<LinkEnd>
     */
    private function targetEnds(CustomFieldRelationship $definition, LinkEnd $end): array
    {
        if ($definition->is_symmetric) {
            return [$definition->end(LinkSide::From), $definition->end(LinkSide::To)];
        }

        return [$definition->end($end->side->opposite())];
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
    public function endHoldsOne(CustomFieldRelationship $definition, LinkSide $side): bool
    {
        return $definition->cardinality->isSingleOn($side);
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
