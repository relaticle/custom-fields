<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldValue;
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
     * - Special handling for numeric values to prevent database errors
     *
     * Returns a combined array of validation rules in Laravel validator format.
     *
     * @param  CustomField  $customField  The custom field to get validation rules for
     * @return array<int, string> Combined array of validation rules
     */
    public function getValidationRules(CustomField $customField): array
    {
        // Convert user rules to Laravel validator format
        $userRules = $this->convertUserRulesToValidatorFormat($customField->validation_rules, $customField);

        // Get database constraint rules based on storage column
        $isEncrypted = $customField->settings->encrypted ?? false;
        $databaseRules = $this->getDatabaseValidationRules($customField->type, $isEncrypted);

        // Determine which rules take precedence
        return $this->mergeValidationRules($userRules, $databaseRules, $customField->type);
    }

    /**
     * Check if a field is required based on its validation rules.
     *
     * @param  CustomField  $customField  The custom field to check
     * @return bool True if the field is required
     */
    public function isRequired(CustomField $customField): bool
    {
        return $customField->validation_rules->toCollection()
            ->contains('name', ValidationRule::REQUIRED->value);
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
     * Merge user-defined rules with database constraint rules, applying appropriate precedence logic.
     * Ensures that user-defined rules that are stricter than database constraints are preserved.
     *
     * @param  array<int, string>  $userRules  User-defined validation rules
     * @param  array<int, string>  $databaseRules  Database constraint validation rules
     * @param  string  $fieldType  The field type
     * @return array<int, string> Merged validation rules
     */
    private function mergeValidationRules(array $userRules, array $databaseRules, string $fieldType): array
    {
        // Get constraints for the database column used by this field type
        $columnName = CustomFieldValue::getValueColumn($fieldType);
        $dbConstraints = DatabaseFieldConstraints::getConstraintsForColumn($columnName);

        // If we have constraints, use the constraint-aware merge function
        if ($dbConstraints !== null && $dbConstraints !== []) {
            // Important: we pass userRules first to ensure they take precedence
            // when they're stricter than system constraints
            return DatabaseFieldConstraints::mergeConstraintsWithRules($dbConstraints, $userRules);
        }

        // Otherwise, simply combine the rules, with user rules taking precedence
        return $this->combineRules($userRules, $databaseRules);
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
        return array_map(function ($name) use ($nameToIdMap): string {
            return (string) ($nameToIdMap[$name] ?? $name);
        }, $optionNames);
    }
}
