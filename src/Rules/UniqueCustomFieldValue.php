<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\TenantContextService;
use RuntimeException;

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

        $equivalentsByOriginal = collect(Arr::wrap($value))
            ->reject(fn (mixed $v): bool => blank($v) || ! is_scalar($v))
            ->mapWithKeys(fn (mixed $v): array => [(string) $v => $this->equivalents($fieldType, (string) $v)]);

        if ($this->exceptHeldValues && $this->ignoreEntityId !== null) {
            $held = $this->heldValues($fieldType);
            $equivalentsByOriginal = $equivalentsByOriginal->reject(fn (array $equivalents): bool => array_intersect($equivalents, $held) !== []);
        }

        if ($equivalentsByOriginal->isEmpty()) {
            return;
        }

        $takenValues = $this->findTakenValues($equivalentsByOriginal->flatten()->unique()->values()->all());

        if ($takenValues === []) {
            return;
        }

        $collision = $equivalentsByOriginal->search(
            fn (array $equivalents): bool => array_intersect($equivalents, $takenValues) !== []
        );

        if ($collision !== false) {
            $fail(__('custom-fields::custom-fields.validation.unique_value', [
                'value' => $collision,
            ]));
        }
    }

    /**
     * @return list<string>
     */
    private function equivalents(?FieldTypeDefinitionInterface $fieldType, string $value): array
    {
        return $fieldType instanceof BaseFieldType ? $fieldType->equivalentValues($value, $this->customField) : [$value];
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

    /**
     * @return list<string>
     */
    private function heldValues(?FieldTypeDefinitionInterface $fieldType): array
    {
        $stored = $this->fieldValuesQuery()
            ->where('entity_id', $this->ignoreEntityId)
            ->first()?->getAttribute($this->customField->getValueColumn());

        // The json cast hands back a Collection, a plain array, or a scalar depending on
        // the column, so each shape becomes a list before the values are compared.
        $values = match (true) {
            $stored === null => [],
            $stored instanceof Collection => $stored->all(),
            is_array($stored) => array_values($stored),
            default => [$stored],
        };

        return collect($values)
            ->filter(fn (mixed $value): bool => is_scalar($value) && filled($value))
            ->flatMap(fn (mixed $value): array => $this->equivalents($fieldType, (string) $value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Builder<CustomFieldValue>
     */
    private function fieldValuesQuery(): Builder
    {
        $entityType = $this->customField->entity_type;
        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            throw new RuntimeException(sprintf(
                'Custom field "%s" references an unresolvable entity type "%s".',
                $this->customField->code,
                $entityType,
            ));
        }

        $query = CustomFields::newValueModel()->newQuery()
            ->where('custom_field_id', $this->customField->getKey())
            ->where('entity_type', (new $entityClass)->getMorphClass());

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantFk = config('custom-fields.database.column_names.tenant_foreign_key');
            $query->where($tenantFk, TenantContextService::getCurrentTenantId());
        }

        return $query;
    }

    /**
     * @return Builder<CustomFieldValue>
     */
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
