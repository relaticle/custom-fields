<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\TenantContextService;

final class UniqueCustomFieldValue implements ValidationRule
{
    public function __construct(
        private readonly CustomField $customField,
        private readonly string|int|null $ignoreEntityId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $values = is_array($value) ? $value : [$value];
        $fieldType = app(FieldManager::class)->getFieldTypeInstance($this->customField->type);

        $normalizedByOriginal = [];

        foreach ($values as $singleValue) {
            if (blank($singleValue) || ! is_scalar($singleValue)) {
                continue;
            }

            $normalizedByOriginal[(string) $singleValue] = $fieldType
                ? $fieldType->setValue((string) $singleValue)
                : (string) $singleValue;
        }

        if ($normalizedByOriginal === []) {
            return;
        }

        $takenValues = $this->findTakenValues(array_values($normalizedByOriginal));

        if ($takenValues === []) {
            return;
        }

        foreach ($normalizedByOriginal as $originalValue => $normalizedValue) {
            if (in_array($normalizedValue, $takenValues, true)) {
                $fail(__('custom-fields::custom-fields.validation.unique_value', [
                    'value' => $originalValue,
                ]));

                return;
            }
        }
    }

    /**
     * Return the subset of normalized values that already exist on another entity.
     *
     * Executes a single query regardless of how many values are submitted,
     * avoiding the N+1 pattern of checking each value individually.
     *
     * @param  array<int, string>  $normalizedValues
     * @return array<int, string>
     */
    private function findTakenValues(array $normalizedValues): array
    {
        $valueColumn = $this->customField->getValueColumn();
        $query = $this->baseQuery();

        if ($valueColumn === 'json_value') {
            $query->where(function (Builder $q) use ($normalizedValues): void {
                foreach ($normalizedValues as $value) {
                    $q->orWhereJsonContains('json_value', $value);
                }
            });

            $stored = [];

            foreach ($query->pluck('json_value')->all() as $storedArray) {
                if ($storedArray instanceof \Traversable) {
                    $storedArray = iterator_to_array($storedArray, false);
                }

                if (is_array($storedArray)) {
                    $stored = array_merge($stored, $storedArray);
                }
            }

            return array_values(array_intersect($normalizedValues, $stored));
        }

        return $query->whereIn($valueColumn, $normalizedValues)
            ->pluck($valueColumn)
            ->map(static fn (mixed $v): string => (string) $v)
            ->unique()
            ->values()
            ->all();
    }

    private function baseQuery(): Builder
    {
        $valueModel = CustomFields::newValueModel();

        $entityType = $this->customField->entity_type;
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;
        $morphAlias = (new $entityClass)->getMorphClass();

        $query = $valueModel->newQuery()
            ->where('custom_field_id', $this->customField->getKey())
            ->where('entity_type', $morphAlias);

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantFk = config('custom-fields.database.column_names.tenant_foreign_key');
            $query->where($tenantFk, TenantContextService::getCurrentTenantId());
        }

        if ($this->ignoreEntityId !== null) {
            $query->where('entity_id', '!=', $this->ignoreEntityId);
        }

        return $query;
    }
}
