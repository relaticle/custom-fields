<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
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

        $fieldType = app(FieldManager::class)->getFieldTypeInstance($this->customField->type);

        $normalizedByOriginal = collect(Arr::wrap($value))
            ->reject(fn (mixed $v): bool => blank($v) || ! is_scalar($v))
            ->mapWithKeys(fn (mixed $v): array => [
                (string) $v => $fieldType?->setValue((string) $v) ?? (string) $v,
            ]);

        if ($normalizedByOriginal->isEmpty()) {
            return;
        }

        $takenValues = $this->findTakenValues($normalizedByOriginal->values()->all());

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

            $stored = $query->pluck('json_value')->flatten(1)->all();

            return array_values(array_intersect($normalizedValues, $stored));
        }

        return $query->whereIn($valueColumn, $normalizedValues)
            ->distinct()
            ->pluck($valueColumn)
            ->map(static fn (mixed $v): string => (string) $v)
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
