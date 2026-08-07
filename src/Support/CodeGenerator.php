<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
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
    /** @var (Closure(string, string, int|string|null): (Closure(Builder): Builder)|null)|null */
    private static ?Closure $uniquenessScopeResolver = null;

    /**
     * Generate a slug-style code from a name.
     */
    public static function generateFromName(string $name): string
    {
        return Str::of($name)->slug('_')->toString();
    }

    /**
     * Narrow the uniqueness check to a subset of rows.
     *
     * The callback receives the entity type, either 'field' or 'section', and the section
     * the code is being generated within (null when there is none). It returns a query
     * scope closure, or null to leave the check global.
     *
     * @param  (Closure(string, string, int|string|null): (Closure(Builder): Builder)|null)|null  $callback
     */
    public static function resolveUniquenessScopeUsing(?Closure $callback): void
    {
        self::$uniquenessScopeResolver = $callback;
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
     */
    public static function generateUniqueSectionCode(string $name, string $entityType, ?int $ignoreId = null, int|string|null $sectionId = null): string
    {
        $baseCode = self::generateFromName($name);

        return self::ensureUniqueCode(
            $baseCode,
            $entityType,
            'section',
            $ignoreId,
            $sectionId
        );
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

        $query = $model->newQuery()
            ->withDeactivated()
            ->where('code', $code)
            ->where('entity_type', $entityType);

        if ($ignoreId !== null) {
            $query->where($model->getKeyName(), '!=', $ignoreId);
        }

        if (self::$uniquenessScopeResolver !== null) {
            $scope = (self::$uniquenessScopeResolver)($entityType, $type, $sectionId);

            if ($scope !== null) {
                $scope($query);
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
