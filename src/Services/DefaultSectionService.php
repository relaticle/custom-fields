<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomFieldSection;

/**
 * Service for managing the default hidden section when sections are disabled.
 */
final class DefaultSectionService
{
    /**
     * The code used for the default hidden section.
     */
    public const DEFAULT_SECTION_CODE = '_default';

    /**
     * Get or create the default hidden section for an entity type.
     */
    public function getOrCreateDefaultSection(string $entityType): CustomFieldSection
    {
        $sectionModel = CustomFields::newSectionModel();

        $query = $sectionModel->newQuery()
            ->withDeactivated()
            ->where('code', self::DEFAULT_SECTION_CODE)
            ->where('entity_type', $entityType);

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $query->where(
                config('custom-fields.database.column_names.tenant_foreign_key'),
                TenantContextService::getCurrentTenantId()
            );
        }

        $section = $query->first();

        if ($section instanceof CustomFieldSection) {
            return $section;
        }

        return $this->createDefaultSection($entityType);
    }

    /**
     * Check if a section is the default hidden section.
     */
    public function isDefaultSection(CustomFieldSection $section): bool
    {
        return $section->code === self::DEFAULT_SECTION_CODE;
    }

    /**
     * Create the default hidden section for an entity type.
     */
    private function createDefaultSection(string $entityType): CustomFieldSection
    {
        $data = [
            'name' => __('custom-fields::custom-fields.section.default_section_name'),
            'code' => self::DEFAULT_SECTION_CODE,
            'type' => CustomFieldSectionType::HEADLESS->value,
            'entity_type' => $entityType,
            'sort_order' => 0,
            'active' => true,
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
        }

        return CustomFields::newSectionModel()->create($data);
    }
}
