<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Events\RelationshipLinkClosed;
use Relaticle\CustomFields\Exceptions\RelationshipDefinitionDoesNotExistException;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\Scopes\TenantScope;

/**
 * The ends of a relationship are locked once it exists (spec 2.3), so the only thing left to
 * change is its cardinality. Narrowing an end to a single record keeps that record's first
 * edge and closes the rest, which the caller has to confirm.
 */
final readonly class UpdateRelationshipDefinition
{
    /**
     * @throws ValidationException
     */
    public function execute(CustomFieldRelationship $definition, RelationshipCardinality $cardinality, bool $keepFirst = false): CustomFieldRelationship
    {
        return DB::transaction(function () use ($definition, $cardinality, $keepFirst): CustomFieldRelationship {
            $locked = $this->lock($definition->getKey());

            if ($locked->cardinality === $cardinality) {
                return $locked;
            }

            $narrowed = $this->narrowedEnds($locked->cardinality, $cardinality);

            if ($narrowed !== [] && ! $keepFirst) {
                throw ValidationException::withMessages([
                    'cardinality' => __('custom-fields::custom-fields.relationships.errors.keep_first_required'),
                ]);
            }

            $events = $narrowed === [] ? [] : $this->closeSurplus($locked, $narrowed, now());

            $locked->update(['cardinality' => $cardinality]);

            DB::afterCommit(static function () use ($events): void {
                foreach ($events as $event) {
                    event($event);
                }
            });

            return $locked;
        });
    }

    /**
     * Writers serialize on the definition row, so the narrowing that decides which edges
     * survive takes the same lock rather than racing them.
     */
    private function lock(int|string $key): CustomFieldRelationship
    {
        return CustomFields::newRelationshipModel()
            ->newQuery()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($key)
            ->lockForUpdate()
            ->first() ?? throw RelationshipDefinitionDoesNotExistException::whenLinking($key);
    }

    /**
     * The ends that go from holding many edges to holding one.
     *
     * @return array<int, string>
     */
    private function narrowedEnds(RelationshipCardinality $from, RelationshipCardinality $to): array
    {
        $ends = [];

        if (! $from->fromSideIsSingle() && $to->fromSideIsSingle()) {
            $ends[] = CustomFieldRelationship::DIRECTION_FROM;
        }

        if (! $from->toSideIsSingle() && $to->toSideIsSingle()) {
            $ends[] = CustomFieldRelationship::DIRECTION_TO;
        }

        return $ends;
    }

    /**
     * The first edge a record holds on a narrowed end is the one it keeps, in the order the
     * lists were written. The rest are closed, never deleted, so the history keeps them.
     *
     * @param  array<int, string>  $ends
     * @return array<int, RelationshipLinkClosed>
     */
    private function closeSurplus(CustomFieldRelationship $definition, array $ends, CarbonInterface $now): array
    {
        $taken = [];
        $events = [];

        $links = CustomFields::newLinkModel()
            ->newQuery()
            ->withoutGlobalScope(TenantScope::class)
            ->where('relationship_id', $definition->getKey())
            ->whereNull('active_until')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($links as $link) {
            $holders = $this->holders($definition, $link, $ends);

            if (array_intersect($holders, $taken) !== []) {
                $link->close($now);
                $events[] = new RelationshipLinkClosed($link);

                continue;
            }

            $taken = [...$taken, ...$holders];
        }

        return $events;
    }

    /**
     * A directional edge holds its ends apart, so a self relation counts a record once per
     * end: the record something reports to is not the record reporting to it. Only a
     * symmetric edge, canonicalized by value, is counted by value (CardinalityGuard::ends()).
     *
     * @param  array<int, string>  $ends
     * @return array<int, string>
     */
    private function holders(CustomFieldRelationship $definition, CustomFieldLink $link, array $ends): array
    {
        $holders = [];

        foreach ($ends as $end) {
            $holders[] = $definition->is_symmetric
                ? sprintf('%s:%s', $link->{$end.'_entity_type'}, $link->{$end.'_entity_id'})
                : sprintf('%s:%s:%s', $end, $link->{$end.'_entity_type'}, $link->{$end.'_entity_id'});
        }

        return $holders;
    }
}
