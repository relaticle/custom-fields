<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Illuminate\Support\Collection;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;
use Throwable;

final readonly class LookupResolver
{
    public function __construct(
        private LookupCache $cache,
        private LookupAttributeResolver $attributes,
    ) {}

    /**
     * Resolve lookup values based on the custom field configuration.
     *
     * @param  array<int, mixed>  $values
     * @return Collection<int, mixed>
     *
     * @throws Throwable
     */
    public function resolveLookupValues(array $values, CustomField $customField): Collection
    {
        // Check if the field type accepts arbitrary values (like tags-input)
        $fieldTypeManager = app(FieldManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($customField->type);

        if ($fieldTypeInstance && $fieldTypeInstance->getData()->acceptsArbitraryValues) {
            return collect($values);
        }

        if ($customField->lookup_type === null) {
            return $customField->options->whereIn('id', $values)->pluck('name');
        }

        return $this->resolveAgainstLookupModel($customField->lookup_type, $values);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return Collection<int, string>
     *
     * @throws Throwable
     */
    private function resolveAgainstLookupModel(string $lookupType, array $values): Collection
    {
        $scalarIds = array_values(array_filter(
            $values,
            static fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''),
        ));

        $missing = $this->cache->missing($lookupType, $scalarIds);

        if ($missing !== []) {
            [$lookupInstance, $recordTitleAttribute] = $this->attributes->resolve($lookupType);

            $freshTitles = $lookupInstance->newQuery()
                ->whereIn('id', $missing)
                ->pluck($recordTitleAttribute, 'id')
                ->map(static fn (mixed $title): string => (string) $title)
                ->all();

            $this->cache->remember($lookupType, $freshTitles);
        }

        return collect($scalarIds)
            ->map(fn (int|string $id): ?string => $this->cache->titleFor($lookupType, $id))
            ->reject(static fn (?string $title): bool => $title === null)
            ->values();
    }
}
