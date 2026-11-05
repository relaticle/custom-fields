<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\LinkEnd;
use Relaticle\CustomFields\Data\WriteContext;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\LinkSide;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Events\RelationshipLinkClosed;
use Relaticle\CustomFields\Events\RelationshipLinkCreated;
use Relaticle\CustomFields\Exceptions\RelationshipDefinitionDoesNotExistException;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use RuntimeException;

final readonly class LinkWriter
{
    public function __construct(
        private CardinalityGuard $cardinality,
        private LinkReader $reader,
    ) {}

    /**
     * Apply the ordered payload of one record field as the record's active edges, stamped
     * with the current CustomFields::writeContext().
     *
     * @param  array<int, int|string>  $targetIds
     * @param  array<int, string>  $confirmed  Ids the caller agreed to take from their holder.
     *
     * @throws ValidationException
     */
    public function apply(Model $record, CustomField $field, array $targetIds, array $confirmed = []): void
    {
        $definition = $field->relationshipDefinitionOrFail();

        try {
            DB::transaction(function () use ($record, $definition, $field, $targetIds, $confirmed): void {
                $events = $this->diff($record, $definition, $field, $targetIds, $confirmed);

                // A rolled back write never happened, so its listeners must never hear about it.
                DB::afterCommit(static function () use ($events): void {
                    foreach ($events as $event) {
                        event($event);
                    }
                });
            });
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            if (! $this->isDuplicateActiveEdge($uniqueConstraintViolationException)) {
                throw $uniqueConstraintViolationException;
            }

            // Another writer took the edge between our read and our insert.
            throw ValidationException::withMessages([
                $field->getFieldName() => __('custom-fields::custom-fields.relationships.errors.conflict'),
            ]);
        }
    }

    /**
     * Only the duplicate-edge index becomes a field error. Anything else a listener or a host
     * hook violated inside the transaction stays the exception it was. Postgres names the
     * index; SQLite names the columns, so there the statement's table identifies it, the edge
     * ledger carrying no second unique key.
     */
    private function isDuplicateActiveEdge(UniqueConstraintViolationException $exception): bool
    {
        if (str_contains(strtolower($exception->getMessage()), CustomFieldLink::ACTIVE_EDGE_INDEX)) {
            return true;
        }

        return str_contains(strtolower($exception->getSql()), strtolower(CustomFields::newLinkModel()->getTable()));
    }

    /**
     * @param  array<int, int|string>  $targetIds
     * @param  array<int, string>  $confirmed
     * @return array<int, RelationshipLinkClosed|RelationshipLinkCreated>
     */
    private function diff(Model $record, CustomFieldRelationship $definition, CustomField $field, array $targetIds, array $confirmed): array
    {
        $definition = $this->lock($definition)->load(['fromField', 'toField']);
        $context = CustomFields::writeContext();

        $end = $definition->writeEnd($field);

        $this->assertRecordSitsOnEnd($record, $definition, $end);

        $targets = $this->normalize($targetIds);
        $current = $this->activeLinksFor($definition, $record->getMorphClass(), (string) $record->getKey(), $end->side)->get();

        // An edge the payload keeps is never touched (spec 2.1), so only the ids being added
        // are held to reachability: a target soft-deleted after it was linked would otherwise
        // make its own record unsaveable.
        $added = array_values(array_diff($targets, $current->map(fn (CustomFieldLink $link): string => (string) $this->reader->otherEndId($link, $record))->all()));

        $this->assertTargetsExist($definition, $field, $end, $added);
        $this->assertCardinality($definition, $field, $end, $record, $targets, $confirmed);

        $now = now();
        $events = [];

        foreach ($current as $link) {
            if (! in_array((string) $this->reader->otherEndId($link, $record), $targets, true)) {
                $events[] = $this->close($link, $now, $definition, $record, $context);
            }
        }

        foreach ($targets as $index => $targetId) {
            $kept = $current->first(fn (CustomFieldLink $link): bool => $link->active_until === null
                && (string) $this->reader->otherEndId($link, $record) === $targetId);

            if ($kept instanceof CustomFieldLink) {
                $this->reorder($kept, $index);

                continue;
            }

            $events = [
                ...$events,
                ...$this->closeDisplaced($definition, $record, $end, $targetId, $now, $context),
                new RelationshipLinkCreated($this->insert($definition, $record, $end, $targetId, $index, $now, $context), $definition, $record, $context),
            ];
        }

        return $events;
    }

    /**
     * Per-end exclusivity cannot be a static index, so writers serialize on the definition row,
     * which always exists. The stored row decides, never the memoised one, and many to many
     * skips the lock: there the duplicate-edge index is the whole wall (spec 1.2).
     */
    private function lock(CustomFieldRelationship $definition): CustomFieldRelationship
    {
        $stored = $this->findDefinition($definition->getKey(), locked: false);

        if ($stored->cardinality === RelationshipCardinality::ManyToMany) {
            return $stored;
        }

        return $this->findDefinition($definition->getKey(), locked: true);
    }

    /**
     * The key is the identity, so the read drops the tenant scope: a definition reached from a
     * cross-tenant context must lock or fail, never fall back to an unlocked copy.
     */
    private function findDefinition(int|string $key, bool $locked): CustomFieldRelationship
    {
        $query = CustomFields::newRelationshipModel()
            ->newQuery()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($key);

        if ($locked) {
            $query->lockForUpdate();
        }

        return $query->first() ?? throw RelationshipDefinitionDoesNotExistException::whenLinking($key);
    }

    /**
     * The definition is locked by now, so what the guard reads is what the write would
     * displace. The validation layer says the same thing earlier, where a caller can still
     * confirm the replacement.
     *
     * @param  array<int, string>  $targets
     * @param  array<int, string>  $confirmed
     *
     * @throws ValidationException
     */
    private function assertCardinality(CustomFieldRelationship $definition, CustomField $field, LinkEnd $end, Model $record, array $targets, array $confirmed): void
    {
        $violations = $this->cardinality->violations($definition, $end, $record->getKey(), $targets, $confirmed);

        if ($violations === []) {
            return;
        }

        throw ValidationException::withMessages([$field->getFieldName() => $violations]);
    }

    private function assertRecordSitsOnEnd(Model $record, CustomFieldRelationship $definition, LinkEnd $end): void
    {
        if ($record->getMorphClass() === $end->entityType) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Record [%s] does not sit on the [%s] end of relationship [%s].',
            $record->getMorphClass(),
            $end->side->value,
            $definition->code,
        ));
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

    /**
     * The target model's own query decides what is reachable, so a host's tenant scope and
     * its soft deletes rule out foreign ids before any edge is written.
     *
     * @param  array<int, string>  $targets
     */
    private function assertTargetsExist(CustomFieldRelationship $definition, CustomField $field, LinkEnd $end, array $targets): void
    {
        if ($targets === []) {
            return;
        }

        $entityType = $definition->end($end->side->opposite())->entityType;
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            throw new RuntimeException(sprintf(
                'Relationship "%s" references an unresolvable entity type "%s".',
                $definition->code,
                $entityType,
            ));
        }

        $target = new $entityClass;

        $reachable = $target->newQuery()
            ->whereKey($targets)
            ->pluck($target->getKeyName())
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        if (array_diff($targets, $reachable) === []) {
            return;
        }

        throw ValidationException::withMessages([
            $field->getFieldName() => __('custom-fields::custom-fields.relationships.errors.unknown_target'),
        ]);
    }

    /**
     * @return Builder<CustomFieldLink>
     */
    private function activeLinksFor(CustomFieldRelationship $definition, string $entityType, string $entityId, LinkSide $side): Builder
    {
        $query = CustomFields::newLinkModel()
            ->newQuery()
            ->where('relationship_id', $definition->getKey())
            ->whereNull('active_until')
            ->orderBy('sort_order');

        if (! $definition->is_symmetric) {
            return $query
                ->where($side->typeColumn(), $entityType)
                ->where($side->idColumn(), $entityId);
        }

        return $query->where(function (Builder $nested) use ($entityType, $entityId): void {
            foreach (LinkSide::cases() as $candidate) {
                $nested->orWhere(fn (Builder $end): Builder => $end
                    ->where($candidate->typeColumn(), $entityType)
                    ->where($candidate->idColumn(), $entityId));
            }
        });
    }

    /**
     * A record landing in a taken single end replaces what is there: the displaced edge is
     * closed, never deleted, so the history keeps it.
     *
     * @return array<int, RelationshipLinkClosed>
     */
    private function closeDisplaced(CustomFieldRelationship $definition, Model $record, LinkEnd $end, string $targetId, CarbonInterface $now, WriteContext $context): array
    {
        $cardinality = $definition->cardinality;
        $far = $definition->end($end->side->opposite());

        if ($definition->is_symmetric) {
            if (! $cardinality->fromSideIsSingle()) {
                return [];
            }

            return [
                ...$this->closeAll($this->activeLinksFor($definition, $record->getMorphClass(), (string) $record->getKey(), $end->side), $now, $definition, $record, $context),
                ...$this->closeAll($this->activeLinksFor($definition, $far->entityType, $targetId, $end->side), $now, $definition, $record, $context),
            ];
        }

        $events = [];

        if ($cardinality->isSingleOn($end->side)) {
            $events = $this->closeAll($this->activeLinksFor($definition, $record->getMorphClass(), (string) $record->getKey(), $end->side), $now, $definition, $record, $context);
        }

        if ($cardinality->isSingleOn($far->side)) {
            return [
                ...$events,
                ...$this->closeAll($this->activeLinksFor($definition, $far->entityType, $targetId, $far->side), $now, $definition, $record, $context),
            ];
        }

        return $events;
    }

    /**
     * @param  Builder<CustomFieldLink>  $query
     * @return array<int, RelationshipLinkClosed>
     */
    private function closeAll(Builder $query, CarbonInterface $now, CustomFieldRelationship $definition, Model $record, WriteContext $context): array
    {
        $events = [];

        foreach ($query->get() as $link) {
            $events[] = $this->close($link, $now, $definition, $record, $context);
        }

        return $events;
    }

    private function close(CustomFieldLink $link, CarbonInterface $now, CustomFieldRelationship $definition, Model $record, WriteContext $context): RelationshipLinkClosed
    {
        $link->close($now);

        return new RelationshipLinkClosed($link, $definition, $record, $context);
    }

    private function reorder(CustomFieldLink $link, int $index): void
    {
        if ($link->sort_order === $index) {
            return;
        }

        $link->sort_order = $index;
        $link->save();
    }

    private function insert(CustomFieldRelationship $definition, Model $record, LinkEnd $end, string $targetId, int $index, CarbonInterface $now, WriteContext $context): CustomFieldLink
    {
        [$fromId, $toId] = $this->storedIds($definition, $record, $end, $targetId);

        $attributes = [
            'relationship_id' => $definition->getKey(),
            'from_entity_type' => $definition->from_entity_type,
            'from_entity_id' => $fromId,
            'to_entity_type' => $definition->to_entity_type,
            'to_entity_id' => $toId,
            'sort_order' => $index,
            'active_from' => $now,
            'created_by_type' => $context->actor?->getMorphClass(),
            'created_by_id' => $context->actor?->getKey(),
            'source' => $context->source,
            'confidence' => $context->confidence,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantKey = config('custom-fields.database.column_names.tenant_foreign_key');
            $attributes[$tenantKey] = $definition->{$tenantKey};
        }

        return CustomFields::newLinkModel()->newQuery()->create($attributes);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function storedIds(CustomFieldRelationship $definition, Model $record, LinkEnd $end, string $targetId): array
    {
        $recordId = (string) $record->getKey();

        // One symmetric row is read from both sides, so its ends are stored least first.
        if ($definition->is_symmetric) {
            return strcmp($recordId, $targetId) <= 0
                ? [$recordId, $targetId]
                : [$targetId, $recordId];
        }

        return $end->side === LinkSide::From
            ? [$recordId, $targetId]
            : [$targetId, $recordId];
    }
}
