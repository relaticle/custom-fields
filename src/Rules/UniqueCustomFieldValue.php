<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
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

        foreach ($values as $singleValue) {
            if (blank($singleValue)) {
                continue;
            }

            $normalizedValue = $fieldType
                ? $fieldType->setValue((string) $singleValue)
                : (string) $singleValue;

            if ($this->existsOnAnotherEntity($normalizedValue)) {
                $fail(__('custom-fields::custom-fields.validation.unique_value', [
                    'value' => $singleValue,
                ]));

                return;
            }
        }
    }

    private function existsOnAnotherEntity(string $normalizedValue): bool
    {
        $valueModel = CustomFields::newValueModel();
        $valueColumn = $this->customField->getValueColumn();

        $entityType = $this->customField->entity_type;
        $morphAlias = Relation::getMorphAlias($entityType) ?? (new $entityType)->getMorphClass();

        $query = $valueModel->newQuery()
            ->where('custom_field_id', $this->customField->getKey())
            ->where('entity_type', $morphAlias);

        if ($valueColumn === 'json_value') {
            $query->whereJsonContains('json_value', $normalizedValue);
        } else {
            $query->where($valueColumn, $normalizedValue);
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $tenantFk = config('custom-fields.database.column_names.tenant_foreign_key');
            $query->where($tenantFk, TenantContextService::getCurrentTenantId());
        }

        if ($this->ignoreEntityId !== null) {
            $query->where('entity_id', '!=', $this->ignoreEntityId);
        }

        return $query->exists();
    }
}
