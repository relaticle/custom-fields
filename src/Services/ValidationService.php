<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Rules\UniqueCustomFieldValue;
use Relaticle\CustomFields\Support\DatabaseFieldConstraints;
use Spatie\LaravelData\DataCollection;

/**
 * Service for handling field validation rules and constraints.
 */
final class ValidationService
{
    /**
     * Get all validation rules for a custom field, applying both:
     * - User-defined validation rules from the field configuration
     * - Database field constraints based on field type
     * - Type-specific settings (e.g., unique per entity type for email fields)
     * - Special handling for numeric values to prevent database errors
     *
     * Returns a combined array of validation rules in Laravel validator format.
     *
     * @param  CustomField  $customField  The custom field to get validation rules for
     * @param  string|int|null  $ignoreEntityId  Entity ID to ignore for unique checks (when updating)
     * @return array<int, mixed> Combined array of validation rules
     */
    public function getValidationRules(CustomField $customField, string|int|null $ignoreEntityId = null): array
    {
        // Get capability-based rules from stored values
        $capabilityRules = $this->getCapabilityRules($customField);

        // Get field type default rules (always applied for data integrity)
        $fieldTypeDefaultRules = $this->getFieldTypeDefaultRules($customField->type);

        // Get database constraint rules based on storage column
        $isEncrypted = $customField->settings->encrypted ?? false;
        $databaseRules = $this->getDatabaseValidationRules($customField->type, $isEncrypted);

        // Merge all rule types
        $rules = $this->mergeAllValidationRules($fieldTypeDefaultRules, $capabilityRules, $databaseRules, $customField->type);

        // Add type-specific rules based on settings
        $typeSpecificRules = $this->getTypeSpecificRules($customField, $ignoreEntityId);

        return array_merge($rules, $typeSpecificRules);
    }

    /**
     * Check if a field is required based on its validation rules.
     *
     * @param  CustomField  $customField  The custom field to check
     * @return bool True if the field is required
     */
    public function isRequired(CustomField $customField): bool
    {
        return (bool) ($customField->validation_rules?->get('required', false));
    }

    /**
     * Get validation rules from a field type's registered capabilities using stored values.
     *
     * @return array<int, string>
     */
    private function getCapabilityRules(CustomField $customField): array
    {
        $fieldTypeManager = app(FieldManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($customField->type);

        if (! $fieldTypeInstance) {
            return [];
        }

        $capabilities = $fieldTypeInstance->configure()->data()->validationCapabilities;
        $validationRules = $customField->validation_rules;
        $rules = [];

        foreach ($capabilities as $capabilityClass) {
            /** @var ValidationCapability $capability */
            $capability = app($capabilityClass);
            $value = $validationRules?->get($capability->key());

            if ($value !== null) {
                $rules = array_merge($rules, $capability->toRules($value));
            }
        }

        return $rules;
    }

    /**
     * Convert user validation rules from DataCollection format to Laravel validator format.
     *
     * @param  DataCollection<int, ValidationRuleData>|null  $rules  The validation rules to convert
     * @param  CustomField  $customField  The custom field for context
     * @return array<int, string> The converted rules
     */
    private function convertUserRulesToValidatorFormat(?DataCollection $rules, CustomField $customField): array
    {
        if (! $rules instanceof DataCollection || $rules->toCollection()->isEmpty()) {
            return [];
        }

        return $rules->toCollection()
            ->map(function (ValidationRuleData $ruleData) use ($customField): string {
                if ($ruleData->parameters === []) {
                    return $ruleData->name;
                }

                // For choice fields with IN or NOT_IN rules, convert option names to IDs
                if ($customField->isChoiceField() && in_array($ruleData->name, ['in', 'not_in'])) {
                    $parameters = $this->convertOptionNamesToIds($ruleData->parameters, $customField);

                    return $ruleData->name.':'.implode(',', $parameters);
                }

                return $ruleData->name.':'.implode(',', $ruleData->parameters);
            })
            ->toArray();
    }

    /**
     * Get all database validation rules for a specific field type.
     * Now uses database column-based validation for better extensibility.
     *
     * @param  string  $fieldType  The field type
     * @param  bool  $isEncrypted  Whether the field is encrypted
     * @return array<int, string> Array of validation rules
     */
    public function getDatabaseValidationRules(string $fieldType, bool $isEncrypted = false): array
    {
        // Determine the database column for this field type
        $columnName = CustomFieldValue::getValueColumn($fieldType);

        // Get base database rules for this column
        $dbRules = DatabaseFieldConstraints::getValidationRulesForColumn($columnName, $isEncrypted);

        // For JSON fields, add array validation rules
        if ($columnName === 'json_value') {
            $jsonRules = DatabaseFieldConstraints::getJsonValidationRules();

            return array_merge($dbRules, $jsonRules);
        }

        return $dbRules;
    }

    /**
     * Combine two sets of rules, removing duplicates but preserving rule precedence.
     *
     * @param  array<int, string>  $primaryRules  Rules that take precedence
     * @param  array<int, string>  $secondaryRules  Rules that are overridden by primary rules
     * @return array<int, string> Combined rules
     */
    private function combineRules(array $primaryRules, array $secondaryRules): array
    {
        // Extract rule names (without parameters) from primary rules
        $primaryRuleNames = array_map(fn (string $rule): string => explode(':', $rule, 2)[0], $primaryRules);

        // Filter secondary rules to only include those that don't conflict with primary rules
        $filteredSecondaryRules = array_filter($secondaryRules, function (string $rule) use ($primaryRuleNames): bool {
            $ruleName = explode(':', $rule, 2)[0];

            return ! in_array($ruleName, $primaryRuleNames);
        });

        // Combine the rules, with primary rules first
        return array_merge($primaryRules, $filteredSecondaryRules);
    }

    /**
     * Convert option names to their corresponding IDs for choice field validation.
     *
     * @param  array<array-key, string>  $optionNames  Array of option names
     * @param  CustomField  $customField  The custom field with options
     * @return array<int, string> Array of option IDs
     */
    private function convertOptionNamesToIds(array $optionNames, CustomField $customField): array
    {
        // Load options if not already loaded
        $customField->loadMissing('options');

        // Create a mapping of option names to IDs
        $nameToIdMap = $customField->options->pluck('id', 'name')->toArray();

        // Convert names to IDs, keeping the original value if not found
        return array_map(function (string $name) use ($nameToIdMap): string {
            return (string) ($nameToIdMap[$name] ?? $name);
        }, $optionNames);
    }

    /**
     * Get default validation rules that should always be applied for specific field types.
     * These ensure data integrity and proper field behavior regardless of user configuration.
     *
     * Rules are retrieved from:
     * 1. Field type configuration (highest priority)
     * 2. Field type definition's defaultValidationRules
     *
     * @param  string  $fieldType  The field type
     * @return array<int, string> Array of default validation rules
     */
    private function getFieldTypeDefaultRules(string $fieldType): array
    {
        // Get from field type definition's defaultValidationRules
        $fieldTypeManager = app(FieldManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($fieldType);

        if ($fieldTypeInstance) {
            $configurator = $fieldTypeInstance->configure();

            return $configurator->getDefaultValidationRules();
        }

        return [];
    }

    /**
     * Get type-specific validation rules based on field settings.
     *
     * @param  CustomField  $customField  The custom field
     * @param  string|int|null  $ignoreEntityId  Entity ID to ignore for unique checks
     * @return array<int, mixed> Type-specific validation rules
     */
    private function getTypeSpecificRules(CustomField $customField, string|int|null $ignoreEntityId = null): array
    {
        $rules = [];

        // Handle unique per entity type setting (available for any field type)
        if ($customField->settings->unique_per_entity_type) {
            $rules[] = new UniqueCustomFieldValue($customField, $ignoreEntityId);
        }

        return $rules;
    }

    /**
     * Get validation rules for individual items in multi-value fields.
     * For single-value fields, returns empty array.
     *
     * @param  CustomField  $customField  The custom field
     * @return array<int, string> Array of item validation rules
     */
    public function getItemValidationRules(CustomField $customField): array
    {
        $fieldTypeManager = app(FieldManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($customField->type);

        if ($fieldTypeInstance) {
            return $fieldTypeInstance->configure()->getDefaultItemValidationRules();
        }

        return [];
    }

    /**
     * Merge field type defaults, user rules, and database constraints with proper precedence.
     * Field type defaults are always applied, user rules can add additional constraints,
     * database constraints provide system-level limits.
     *
     * @param  array<int, string>  $fieldTypeDefaults  Field type default rules
     * @param  array<int, string>  $userRules  User-defined validation rules
     * @param  array<int, string>  $databaseRules  Database constraint validation rules
     * @param  string  $fieldType  The field type
     * @return array<int, string> Merged validation rules
     */
    private function mergeAllValidationRules(array $fieldTypeDefaults, array $userRules, array $databaseRules, string $fieldType): array
    {
        // Start with field type defaults (always applied)
        $mergedRules = $fieldTypeDefaults;

        // Add user rules (can override or supplement defaults)
        $mergedRules = $this->combineRules($mergedRules, $userRules);

        // Apply database constraint rules using existing logic
        $columnName = CustomFieldValue::getValueColumn($fieldType);
        $dbConstraints = DatabaseFieldConstraints::getConstraintsForColumn($columnName);

        if ($dbConstraints !== null && $dbConstraints !== []) {
            return DatabaseFieldConstraints::mergeConstraintsWithRules($dbConstraints, $mergedRules);
        }

        // Otherwise, simply combine with database rules
        return $this->combineRules($mergedRules, $databaseRules);
    }
}
