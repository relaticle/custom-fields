<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Closure;
use Illuminate\Support\Str;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Services\TenantContextService;

/**
 * Helper class for generating unique codes for custom fields and sections.
 */
final class CodeGenerator
{
    /**
     * Generate a slug-style code from a name.
     */
    public static function generateFromName(string $name): string
    {
        return Str::of($name)->slug('_')->toString();
    }

    /**
     * Generate a unique code for a custom field within an entity type.
     */
    public static function generateUniqueFieldCode(string $name, string $entityType, ?int $ignoreId = null, int|string|null $sectionId = null): string
    {
        $baseCode = self::generateFromName($name);

        return self::ensureUniqueCode(
            $baseCode,
            $entityType,
            'field',
            $ignoreId,
            $sectionId
        );
    }

    /**
     * Generate a unique code for a section within an entity type.
     *
     * @internal
     */
    public static function generateUniqueSectionCode(string $name, string $entityType, ?int $ignoreId = null): string
    {
        $baseCode = self::generateFromName($name);

        return self::ensureUniqueCode(
            $baseCode,
            $entityType,
            'section',
            $ignoreId
        );
    }

    /**
     * Generate a unique code for a relationship definition, whose codes are unique per tenant
     * rather than per entity type.
     */
    public static function generateUniqueRelationshipCode(string $baseCode): string
    {
        $code = $baseCode;
        $counter = 1;

        while (CustomFields::newRelationshipModel()->newQuery()->where('code', $code)->exists()) {
            $code = sprintf('%s_%d', $baseCode, $counter);
            $counter++;
        }

        return $code;
    }

    /**
     * Check if a code already exists and append a counter if needed.
     */
    private static function ensureUniqueCode(
        string $baseCode,
        string $entityType,
        string $type,
        ?int $ignoreId = null,
        int|string|null $sectionId = null
    ): string {
        $code = $baseCode;
        $counter = 1;

        while (self::codeExists($code, $entityType, $type, $ignoreId, $sectionId)) {
            $code = sprintf('%s_%d', $baseCode, $counter);
            $counter++;
        }

        return $code;
    }

    /**
     * Check if a code exists in the database.
     */
    private static function codeExists(
        string $code,
        string $entityType,
        string $type,
        ?int $ignoreId = null,
        int|string|null $sectionId = null
    ): bool {
        $model = $type === 'field'
            ? CustomFields::newCustomFieldModel()
            : CustomFields::newSectionModel();

        $query = $model::withDeactivated()
            ->where('code', $code)
            ->where('entity_type', $entityType);

        if ($ignoreId !== null) {
            $query->where($model->getKeyName(), '!=', $ignoreId);
        }

        $uniquenessScope = CustomFields::codeUniquenessScope();

        if ($uniquenessScope instanceof Closure) {
            $scope = $uniquenessScope($entityType, $type, $sectionId);

            if ($scope !== null) {
                $query = $scope($query);
            }
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $query->where(
                config('custom-fields.database.column_names.tenant_foreign_key'),
                TenantContextService::getCurrentTenantId()
            );
        }

        return $query->exists();
    }
}
