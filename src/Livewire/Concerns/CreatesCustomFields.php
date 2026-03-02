<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire\Concerns;

use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\CodeGenerator;

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

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS) && $sectionId !== null) {
            $result['custom_field_section_id'] = $sectionId;
        }

        return $result;
    }

    protected function storeField(array $data): void
    {
        $data = DateConstraintField::sanitizeValidationRules($data);

        $options = collect($data['options'] ?? [])
            ->filter()
            ->values()
            ->map(function (array $option, int $index): array {
                $option['sort_order'] = $index;

                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $option[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                }

                return $option;
            });

        unset($data['options']);

        $customField = CustomFields::newCustomFieldModel()->create($data);

        $customField->options()->createMany($options);
    }
}
