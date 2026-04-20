<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Exceptions\MissingRecordTitleAttributeException;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;
use Throwable;

final readonly class LookupResolver
{
    public function __construct(private LookupCache $cache) {}

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
            static fn (mixed $id): bool => is_int($id) || is_string($id),
        ));

        $missing = $this->cache->missing($lookupType, $scalarIds);

        if ($missing !== []) {
            [$lookupInstance, $recordTitleAttribute] = $this->getLookupAttributes($lookupType);

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

    /**
     * @return array{0: mixed, 1: string}
     *
     * @throws Throwable
     */
    private function getLookupAttributes(string $lookupType): array
    {
        $lookupModelPath = Relation::getMorphedModel($lookupType) ?? $lookupType;
        $lookupInstance = app($lookupModelPath);

        $resourcePath = Filament::getModelResource($lookupModelPath);
        $resourceInstance = app($resourcePath);
        $recordTitleAttribute = $resourceInstance->getRecordTitleAttribute();

        throw_if(
            $recordTitleAttribute === null,
            new MissingRecordTitleAttributeException(sprintf('The `%s` does not have a record title custom attribute.', $resourcePath))
        );

        return [$lookupInstance, $recordTitleAttribute];
    }
}
