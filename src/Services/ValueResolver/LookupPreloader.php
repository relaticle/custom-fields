<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldValue;

/**
 * Scans a set of loaded host records for their custom-field lookup references
 * and primes the LookupCache with one query per target entity type.
 *
 * Called by scopeWithCustomFieldValues's afterQuery hook so tables and
 * infolists get batched lookup resolution for free.
 */
final readonly class LookupPreloader
{
    public function __construct(
        private LookupCache $cache,
        private LookupAttributeResolver $attributes,
    ) {}

    /**
     * @param  EloquentCollection<int, Model>  $records
     */
    public function preload(EloquentCollection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $idsByLookupType = [];

        foreach ($records as $record) {
            if (! $record instanceof HasCustomFields) {
                continue;
            }

            foreach ($this->linkedEnds($record) as $linkedType => $linkedIds) {
                foreach ($linkedIds as $linkedId) {
                    $idsByLookupType[$linkedType][] = $linkedId;
                }
            }

            if (! $record->relationLoaded('customFieldValues')) {
                continue;
            }

            /** @var iterable<CustomFieldValue> $values */
            $values = $record->getRelation('customFieldValues');

            foreach ($values as $value) {
                $field = $value->customField;

                if (! $field instanceof CustomField) {
                    continue;
                }

                $lookupType = $field->targetEntityType();

                if ($lookupType === null) {
                    continue;
                }

                foreach ($this->scalarIdsFromValue($value->getValue()) as $id) {
                    $idsByLookupType[$lookupType][] = $id;
                }
            }
        }

        foreach ($idsByLookupType as $lookupType => $ids) {
            $missing = $this->cache->missing($lookupType, $ids);

            if ($missing === []) {
                continue;
            }

            [$lookupInstance, $recordTitleAttribute] = $this->attributes->resolve($lookupType);

            $titles = $lookupInstance->newQuery()
                ->whereIn('id', $missing)
                ->pluck($recordTitleAttribute, 'id')
                ->map(static fn (mixed $title): string => (string) $title)
                ->all();

            $this->cache->remember($lookupType, $titles);
        }
    }

    /**
     * Record fields keep no value row, so their titles come off the loaded edges instead.
     *
     * @return array<string, array<int, int|string>>
     */
    private function linkedEnds(Model $record): array
    {
        $ends = [];

        foreach (['outgoingLinks' => 'to', 'incomingLinks' => 'from'] as $relation => $end) {
            if (! $record->relationLoaded($relation)) {
                continue;
            }

            /** @var iterable<CustomFieldLink> $links */
            $links = $record->getRelation($relation);

            foreach ($links as $link) {
                if ($link->active_until !== null) {
                    continue;
                }

                $ends[$link->{$end.'_entity_type'}][] = $link->{$end.'_entity_id'};
            }
        }

        return $ends;
    }

    /**
     * @return array<int, int|string>
     */
    private function scalarIdsFromValue(mixed $value): array
    {
        if (in_array($value, [null, '', []], true)) {
            return [];
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        $candidates = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            $candidates,
            static fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''),
        ));
    }
}
