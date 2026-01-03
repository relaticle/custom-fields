<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire\Concerns;

use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\CodeGenerator;

/**
 * Shared logic for creating custom fields from Livewire components.
 */
trait CreatesCustomFields
{
    protected function mutateFieldData(array $data, string $entityType, int|string|null $sectionId = null): array
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE) && blank($data['code'] ?? null)) {
            $data['code'] = CodeGenerator::generateUniqueFieldCode($data['name'], $entityType);
        }

        $result = [
            ...$data,
            'entity_type' => $entityType,
        ];

        // Only include section_id when sections are enabled
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS_ENABLED) && $sectionId !== null) {
            $result['custom_field_section_id'] = $sectionId;
        }

        return $result;
    }

    protected function storeField(array $data): void
    {
        $options = collect($data['options'] ?? [])
            ->filter()
            ->map(function (array $option): array {
                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $option[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                }

                return $option;
            })
            ->values();

        unset($data['options']);

        $customField = CustomFields::newCustomFieldModel()->create($data);

        $customField->options()->createMany($options);
    }
}
