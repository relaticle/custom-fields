<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Options\ComponentOptionsExtractor;
use Throwable;

/**
 * Backend Visibility Service
 *
 * Handles server-side visibility evaluation using the CoreVisibilityLogicService.
 * Used by infolists, exports, and other backend components that need to
 * determine field visibility.
 *
 * This service provides PHP-based evaluation of visibility conditions.
 */
final class BackendVisibilityService
{
    /**
     * Request-scoped cache of fields by entity type.
     *
     * @var array<string, Collection<string, CustomField>>
     */
    private static array $fieldCache = [];

    public function __construct(
        private readonly CoreVisibilityLogicService $coreLogic,
        private readonly ComponentOptionsExtractor $optionsExtractor,
    ) {}

    /**
     * Get cached fields for an entity type (O(1) lookup by code).
     *
     * @return Collection<string, CustomField>
     */
    private function getCachedFieldsForEntity(string $entityType): Collection
    {
        if (! isset(self::$fieldCache[$entityType])) {
            self::$fieldCache[$entityType] = CustomFields::newCustomFieldModel()::forMorphEntity($entityType)
                ->with('options')
                ->get()
                ->keyBy('code');
        }

        return self::$fieldCache[$entityType];
    }

    /**
     * Clear field cache (call on field create/update/delete).
     */
    public static function clearCache(?string $entityType = null): void
    {
        if ($entityType === null) {
            self::$fieldCache = [];
        } else {
            unset(self::$fieldCache[$entityType]);
        }
    }

    /**
     * Extract field values from a record for visibility evaluation.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    public function extractFieldValues(Model $record, Collection $fields): array
    {
        if (! $record instanceof HasCustomFields) {
            return [];
        }

        // Ensure custom field values are loaded
        if (! $record->relationLoaded('customFieldValues')) {
            $record->load('customFieldValues.customField');
        }

        $fieldValues = [];

        foreach ($fields as $field) {
            $rawValue = $record->getCustomFieldValue($field);
            $fieldValues[$field->code] = $this->normalizeValueForEvaluation(
                $rawValue,
                $field
            );
        }

        return $fieldValues;
    }

    /**
     * Check if a field should be visible for the given record.
     *
     * @param  Collection<int, CustomField>  $allFields
     */
    public function isFieldVisible(
        Model $record,
        CustomField $field,
        Collection $allFields
    ): bool {
        $fieldValues = $this->extractFieldValues($record, $allFields);

        return $this->coreLogic->evaluateVisibilityWithCascading(
            $field,
            $fieldValues,
            $allFields
        );
    }

    /**
     * Filter fields to only those that should be visible for the given record.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return Collection<int, CustomField>
     */
    public function getVisibleFields(
        Model $record,
        Collection $fields
    ): Collection {
        $fieldValues = $this->extractFieldValues($record, $fields);

        return $fields->filter(
            fn (
                CustomField $field
            ): bool => $this->coreLogic->evaluateVisibilityWithCascading(
                $field,
                $fieldValues,
                $fields
            )
        );
    }

    /**
     * Get field values normalized for visibility evaluation.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    /**
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    public function getNormalizedFieldValues(
        Model $record,
        Collection $fields
    ): array {
        $rawValues = $this->extractFieldValues($record, $fields);
        $fieldCodes = $fields->pluck('code')->toArray();

        return $this->normalizeFieldValues($fieldCodes, $rawValues);
    }

    /**
     * Normalize field values for consistent evaluation.
     * Converts option IDs to names and handles different data types.
     *
     * @param  array<string>  $fieldCodes
     * @param  array<string, mixed>  $rawValues
     * @return array<string, mixed>
     */
    public function normalizeFieldValues(
        array $fieldCodes,
        array $rawValues
    ): array {
        if ($fieldCodes === []) {
            return $rawValues;
        }

        $fields = CustomFields::newCustomFieldModel()::whereIn('code', $fieldCodes)
            ->with('options')
            ->get()
            ->keyBy('code');

        $normalized = [];

        foreach ($rawValues as $fieldCode => $value) {
            $field = $fields->get($fieldCode);
            $normalized[$fieldCode] = $this->normalizeValueForEvaluation(
                $value,
                $field
            );
        }

        return $normalized;
    }

    /**
     * Normalize a single field value for visibility evaluation.
     */
    private function normalizeValueForEvaluation(
        mixed $value,
        ?CustomField $field
    ): mixed {
        if (
            $value === null ||
            $value === '' ||
            ! $field->isChoiceField()
        ) {
            return $value;
        }

        // Get options for the field
        $options = $field->options->keyBy('id');

        // Single value optionable fields
        if (! $field->isMultiChoiceField()) {
            return $options->get($value)?->name ?? $value;
        }

        // Multi-value optionable fields
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $id) => $options->get($id)?->name ?? $id)
                ->all();
        }

        return $value;
    }

    /**
     * Get field dependencies for multiple fields efficiently.
     *
     * @param  Collection<int, CustomField>  $allFields
     * @return array<string, array<string>>
     */
    public function calculateDependencies(Collection $allFields): array
    {
        return $this->coreLogic->calculateDependencies($allFields);
    }

    /**
     * Get field options for optionable fields.
     * Universal method that handles field type options, lookup options, and database options.
     *
     * @return array<string, string>
     */
    public function getFieldOptions(
        string $fieldCode,
        string $entityType
    ): array {
        // Use cached fields for O(1) lookup
        $field = $this->getCachedFieldsForEntity($entityType)->get($fieldCode);

        if (! $field || ! $field->isChoiceField()) {
            return [];
        }

        // Priority 1: Check for component-provided options (dynamic extraction)
        $fieldTypeData = CustomFieldsType::getFieldType($field->type);
        if ($fieldTypeData?->withoutUserOptions) {
            $options = $this->optionsExtractor->extractOptionsFromFieldType($field->type, $field);

            return $this->normalizeOptionsForVisibility($options);
        }

        // Priority 2: Handle lookup types (existing functionality)
        if ($field->lookup_type) {
            return $this->getLookupOptions($field->lookup_type);
        }

        // Priority 3: Fallback to database options (existing functionality)
        return $field
            ->options()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->toArray();
    }

    /**
     * Get field metadata for visibility evaluation.
     *
     * @return array<string, mixed>|null
     */
    public function getFieldMetadata(
        string $fieldCode,
        string $entityType
    ): ?array {
        // Use cached fields for O(1) lookup
        $field = $this->getCachedFieldsForEntity($entityType)->get($fieldCode);

        if (! $field) {
            return null;
        }

        return $this->coreLogic->getFieldMetadata($field);
    }

    /**
     * Normalize field options for visibility dropdown usage.
     * Preserves original keys (IDs) while showing display values (names).
     *
     * @param  array<string|int, mixed>  $options
     * @return array<string, string>
     */
    private function normalizeOptionsForVisibility(array $options): array
    {
        if ($options === []) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $value) {
            // Determine the display value
            $displayValue = match (true) {
                is_string($value) => $value,
                is_object($value) && method_exists($value, '__toString') => (string) $value,
                is_scalar($value) => (string) $value,
                default => (string) $key
            };

            // Preserve original key (ID) and use display value (name) as option text
            // This allows visibility conditions to compare against actual stored values
            $normalized[(string) $key] = $displayValue;
        }

        return $normalized;
    }

    /**
     * Get lookup options from entity management system.
     *
     * @return array<string, string>
     */
    private function getLookupOptions(string $lookupType): array
    {
        try {
            $entity = Entities::getEntity($lookupType);

            if (! $entity) {
                return [];
            }

            $primaryAttribute = $entity->getPrimaryAttribute();

            return $entity->newQuery()
                ->limit(50)
                ->pluck($primaryAttribute, $primaryAttribute)
                ->toArray();

        } catch (Throwable $throwable) {
            // Log error in production, return empty array
            return [];
        }
    }
}
