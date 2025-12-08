<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\TenantContextService;

/**
 * Validation rule that ensures a custom field value is unique per entity type.
 *
 * This rule checks if any other entity of the same type already has the given value
 * for this custom field. Works with any field type that has uniqueness enabled.
 * For multi-value fields (arrays stored in json_value), each value is checked.
 */
final class UniqueCustomFieldValue implements ValidationRule
{
    public function __construct(
        private readonly CustomField $customField,
        private readonly ?int $ignoreEntityId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        // Handle both single values and arrays (for multi-value fields)
        $values = is_array($value) ? $value : [$value];

        $valueModel = CustomFields::newValueModel();
        $valueColumn = $this->customField->getValueColumn();

        foreach ($values as $singleValue) {
            if (blank($singleValue)) {
                continue;
            }

            $query = $valueModel->newQuery()
                ->where('custom_field_id', $this->customField->getKey())
                ->where('entity_type', $this->customField->entity_type);

            // Check the appropriate value column based on field type's storage column
            if ($valueColumn === 'json_value') {
                // For JSON columns, search within the array
                $query->whereJsonContains('json_value', $singleValue);
            } else {
                // For scalar columns (string_value, integer_value, etc.)
                $query->where($valueColumn, $singleValue);
            }

            // Apply tenant scope if multi-tenancy is enabled
            if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                $tenantFk = config('custom-fields.database.column_names.tenant_foreign_key');
                $query->where($tenantFk, TenantContextService::getCurrentTenantId());
            }

            // Exclude the current entity if updating
            if ($this->ignoreEntityId !== null) {
                $query->where('entity_id', '!=', $this->ignoreEntityId);
            }

            if ($query->exists()) {
                $fail(__('custom-fields::custom-fields.validation.unique_value', [
                    'value' => $singleValue,
                ]));

                return;
            }
        }
    }
}
