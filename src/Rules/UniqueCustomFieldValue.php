<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\TenantContextService;

final class UniqueCustomFieldValue implements ValidationRule
{
    public function __construct(
        private readonly CustomField $customField,
        private readonly string|int|null $ignoreEntityId = null,
        private readonly bool $exceptHeldValues = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $fieldType = app(FieldManager::class)->getFieldTypeInstance($this->customField->type);

        $normalizedByOriginal = collect(Arr::wrap($value))
            ->reject(fn (mixed $v): bool => blank($v) || ! is_scalar($v))
            ->mapWithKeys(fn (mixed $v): array => [
                (string) $v => $this->normalized($fieldType, (string) $v),
            ]);

        if ($this->exceptHeldValues && $this->ignoreEntityId !== null) {
            $held = $this->heldValues($fieldType);
            $normalizedByOriginal = $normalizedByOriginal->reject(fn (string $normalized): bool => in_array($normalized, $held, true));
        }

        if ($normalizedByOriginal->isEmpty()) {
            return;
        }

        $takenValues = $this->findTakenValues($normalizedByOriginal, $fieldType);

        if ($takenValues === []) {
            return;
        }

        $collision = $normalizedByOriginal->search(
            fn (string $normalized): bool => in_array($normalized, $takenValues, true)
        );

        if ($collision !== false) {
            $fail(__('custom-fields::custom-fields.validation.unique_value', [
                'value' => $collision,
            ]));
        }
    }

    /**
     * Return the subset of normalized values that already exist on another entity.
     *
     * Executes a single query regardless of how many values are submitted,
     * avoiding the N+1 pattern of checking each value individually.
     *
     * @param  Collection<string, string>  $normalizedByOriginal
     * @return array<int, string>
     */
    private function findTakenValues(Collection $normalizedByOriginal, ?FieldTypeDefinitionInterface $fieldType): array
    {
        $valueColumn = $this->customField->getValueColumn();
        $query = $this->baseQuery();
        $normalizedValues = $normalizedByOriginal->values()->all();

        if ($valueColumn === 'json_value') {
            $stored = $query->pluck('json_value')
                ->flatten(1)
                ->filter(fn (mixed $value): bool => is_scalar($value) && filled($value))
                ->map(fn (mixed $value): string => $this->normalized($fieldType, (string) $value));

            return array_values(array_intersect($normalizedValues, $stored->all()));
        }

        return $query->whereIn($valueColumn, $normalizedByOriginal->keys()->merge($normalizedValues)->unique(strict: true)->all())
            ->distinct()
            ->pluck($valueColumn)
            ->map(fn (mixed $v): string => $this->normalized($fieldType, (string) $v))
            ->values()
            ->all();
    }

    private function normalized(?FieldTypeDefinitionInterface $fieldType, string $value): string
    {
        return $fieldType instanceof BaseFieldType ? $fieldType->normalize($value, $this->customField) : $value;
    }

    /**
     * @return list<string>
     */
    private function heldValues(?FieldTypeDefinitionInterface $fieldType): array
    {
        $stored = $this->fieldValuesQuery()
            ->where('entity_id', $this->ignoreEntityId)
            ->first()?->getAttribute($this->customField->getValueColumn());

        return collect($stored)
            ->filter(fn (mixed $value): bool => is_scalar($value) && filled($value))
            ->map(fn (mixed $value): string => $this->normalized($fieldType, (string) $value))
            ->values()
            ->all();
    }

    private function fieldValuesQuery(): Builder
    {
        $entityType = $this->customField->entity_type;
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        $query = CustomFields::newValueModel()->newQuery()
            ->where('custom_field_id', $this->customField->getKey())
            ->where('entity_type', (new $entityClass)->getMorphClass());

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantFk = config('custom-fields.database.column_names.tenant_foreign_key');
            $query->where($tenantFk, TenantContextService::getCurrentTenantId());
        }

        return $query;
    }

    private function baseQuery(): Builder
    {
        $entityType = $this->customField->entity_type;
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        $query = $this->fieldValuesQuery();

        if ($this->ignoreEntityId !== null) {
            $query->where('entity_id', '!=', $this->ignoreEntityId);
        }

        // Only the trashed rows are excluded: a host app's other entity scopes must never hide a taken value.
        if (in_array(SoftDeletes::class, class_uses_recursive($entityClass), true)) {
            $entity = new $entityClass;

            $query->whereNotIn('entity_id', $entity->newQueryWithoutScopes()
                ->select($entity->getKeyName())
                ->whereNotNull($entity->getQualifiedDeletedAtColumn()));
        }

        return $query;
    }
}
