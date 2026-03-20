<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Relaticle\CustomFields\Enums\CustomFieldType;

/**
 * Class for handling database field constraints and converting them to validation rules.
 * This class ensures that user input respects database column limitations.
 */
final class DatabaseFieldConstraints
{
    /**
     * Cache prefix for database constraints.
     */
    private const CACHE_PREFIX = 'custom_fields_db_constraints';

    /**
     * Cache TTL in seconds (24 hours by default).
     */
    private const CACHE_TTL = 86400;

    /**
     * Default safety margin for encrypted fields (reduces max length by this percentage).
     */
    private const ENCRYPTION_SAFETY_MARGIN = 0.66;

    /**
     * Default constraints for field types by database type.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private static array $constraints = [
        'mysql' => [
            'text_value' => [
                'max' => 65535,
                'validator' => 'max',
                'field_types' => [CustomFieldType::TEXT, CustomFieldType::TEXTAREA, CustomFieldType::RICH_EDITOR, CustomFieldType::MARKDOWN_EDITOR],
            ],
            'string_value' => [
                'max' => 255,
                'validator' => 'max',
                'field_types' => [CustomFieldType::LINK, CustomFieldType::COLOR_PICKER],
            ],
            'integer_value' => [
                'min' => -9223372036854775808,
                'max' => 9223372036854775807,
                'validator' => 'between',
                'field_types' => [CustomFieldType::NUMBER, CustomFieldType::RADIO, CustomFieldType::SELECT],
            ],
            'float_value' => [
                'max_digits' => 30,
                'max_decimals' => 15,
                'validator' => ['decimal:0,15'],
                'field_types' => [CustomFieldType::CURRENCY],
            ],
            'json_value' => [
                'max_items' => 500, // Reasonable limit for array items
                'max_item_length' => 255, // Each array item string length
                'validator' => null, // Custom validation needed
                'field_types' => [CustomFieldType::CHECKBOX_LIST, CustomFieldType::TOGGLE_BUTTONS, CustomFieldType::TAGS_INPUT, CustomFieldType::MULTI_SELECT],
            ],
        ],
        'pgsql' => [
            'text_value' => [
                'max' => 1073741823, // Postgres has much larger text capacity
                'validator' => 'max',
                'field_types' => [CustomFieldType::TEXT, CustomFieldType::TEXTAREA, CustomFieldType::RICH_EDITOR, CustomFieldType::MARKDOWN_EDITOR],
            ],
            'string_value' => [
                'max' => 255,
                'validator' => 'max',
                'field_types' => [CustomFieldType::LINK, CustomFieldType::COLOR_PICKER],
            ],
            'integer_value' => [
                'min' => -9223372036854775808,
                'max' => 9223372036854775807,
                'validator' => 'between',
                'field_types' => [CustomFieldType::NUMBER, CustomFieldType::RADIO, CustomFieldType::SELECT],
            ],
            'float_value' => [
                'max_digits' => 30,
                'max_decimals' => 15,
                'validator' => ['decimal:0,15'],
                'field_types' => [CustomFieldType::CURRENCY],
            ],
            'json_value' => [
                'max_items' => 500,
                'max_item_length' => 255,
                'validator' => null,
                'field_types' => [CustomFieldType::CHECKBOX_LIST, CustomFieldType::TOGGLE_BUTTONS, CustomFieldType::TAGS_INPUT, CustomFieldType::MULTI_SELECT],
            ],
        ],
        'mariadb' => [
            'text_value' => [
                'max' => 65535,
                'validator' => 'max',
                'field_types' => [CustomFieldType::TEXT, CustomFieldType::TEXTAREA, CustomFieldType::RICH_EDITOR, CustomFieldType::MARKDOWN_EDITOR],
            ],
            'string_value' => [
                'max' => 255,
                'validator' => 'max',
                'field_types' => [CustomFieldType::LINK, CustomFieldType::COLOR_PICKER],
            ],
            'integer_value' => [
                'min' => -9223372036854775808,
                'max' => 9223372036854775807,
                'validator' => 'between',
                'field_types' => [CustomFieldType::NUMBER, CustomFieldType::RADIO, CustomFieldType::SELECT],
            ],
            'float_value' => [
                'max_digits' => 30,
                'max_decimals' => 15,
                'validator' => ['decimal:0,15'],
                'field_types' => [CustomFieldType::CURRENCY],
            ],
            'json_value' => [
                'max_items' => 500,
                'max_item_length' => 255,
                'validator' => null,
                'field_types' => [CustomFieldType::CHECKBOX_LIST, CustomFieldType::TOGGLE_BUTTONS, CustomFieldType::TAGS_INPUT, CustomFieldType::MULTI_SELECT],
            ],
        ],
        'sqlite' => [
            'text_value' => [
                'max' => 1000000000, // SQLite has essentially no limit, but setting a reasonable one
                'validator' => 'max',
                'field_types' => [CustomFieldType::TEXT, CustomFieldType::TEXTAREA, CustomFieldType::RICH_EDITOR, CustomFieldType::MARKDOWN_EDITOR],
            ],
            'string_value' => [
                'max' => 255,
                'validator' => 'max',
                'field_types' => [CustomFieldType::LINK, CustomFieldType::COLOR_PICKER],
            ],
            'integer_value' => [
                'min' => -9223372036854775808,
                'max' => 9223372036854775807,
                'validator' => 'between',
                'field_types' => [CustomFieldType::NUMBER, CustomFieldType::RADIO, CustomFieldType::SELECT],
            ],
            'float_value' => [
                'max_digits' => 30,
                'max_decimals' => 15,
                'validator' => ['decimal:0,15'],
                'field_types' => [CustomFieldType::CURRENCY],
            ],
            'json_value' => [
                'max_items' => 500,
                'max_item_length' => 255,
                'validator' => null,
                'field_types' => [CustomFieldType::CHECKBOX_LIST, CustomFieldType::TOGGLE_BUTTONS, CustomFieldType::TAGS_INPUT, CustomFieldType::MULTI_SELECT],
            ],
        ],
    ];

    /**
     * Get the current database driver.
     */
    public static function getDatabaseDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Get the constraints for a specific column type.
     *
     * @param  string  $columnName  The name of the column
     * @return array<string, mixed>|null The constraints array or null if not found
     */
    public static function getConstraintsForColumn(string $columnName): ?array
    {
        $driver = self::getDatabaseDriver();

        return self::$constraints[$driver][$columnName] ?? null;
    }

    /**
     * Get the constraints for a specific field type.
     *
     * @param  CustomFieldType  $fieldType  The field type
     * @return array<string, mixed>|null The constraints array or null if not found
     */
    public static function getConstraintsForFieldType(CustomFieldType $fieldType): ?array
    {
        $driver = self::getDatabaseDriver();
        $columnName = self::getColumnNameForFieldType($fieldType);

        if (! $columnName) {
            return null;
        }

        return self::$constraints[$driver][$columnName] ?? null;
    }

    /**
     * Get the column name for a specific field type.
     */
    private static function getColumnNameForFieldType(CustomFieldType $fieldType): ?string
    {
        $driver = self::getDatabaseDriver();

        foreach (self::$constraints[$driver] as $columnName => $config) {
            if (in_array($fieldType, $config['field_types'])) {
                return $columnName;
            }
        }

        return null;
    }

    /**
     * Merge database constraints with user-defined validation rules.
     * This ensures that user-defined rules are respected when they are stricter than database constraints.
     * System limits are only applied when user values would exceed database capabilities.
     *
     * @param  array<string, mixed>  $dbConstraints  Database constraints
     * @param  array<int, string>  $userRules  User-defined validation rules
     * @return array<int, string> Merged validation rules
     */
    public static function mergeConstraintsWithRules(array $dbConstraints, array $userRules): array
    {
        // Make a copy of user rules to avoid modifying the original
        $mergedRules = $userRules;
        $validator = $dbConstraints['validator'] ?? null;

        if (! $validator) {
            return $mergedRules;
        }

        // Handle validators that are arrays (multiple rules)
        if (is_array($validator)) {
            foreach ($validator as $rule) {
                $mergedRules = self::insertOrUpdateRule($mergedRules, $rule, $dbConstraints);
            }

            return $mergedRules;
        }

        // Handle single validator
        return self::insertOrUpdateRule($mergedRules, $validator, $dbConstraints);
    }

    /**
     * Insert or update a rule in the rules array based on database constraints.
     * For constraints like 'max', it will apply the stricter value (lower max).
     * For constraints like 'min', it will apply the stricter value (higher min).
     *
     * @param  array<int, string>  $rules  The existing rules array
     * @param  string  $ruleType  The type of rule (e.g., 'max', 'min')
     * @param  array<string, mixed>  $dbConstraints  Database constraints
     * @return array<int, string> Updated rules array
     */
    private static function insertOrUpdateRule(array $rules, string $ruleType, array $dbConstraints): array
    {
        // Use regular expression to find the rule more reliably
        $existingRuleIndex = null;
        $existingRuleValue = null;
        $hasExistingRule = false;

        // First find any existing rule of this type
        foreach ($rules as $index => $rule) {
            // Match rule name exactly at start of string followed by : or end of string
            if (preg_match('/^'.preg_quote($ruleType, '/').'($|:)/', $rule)) {
                $existingRuleIndex = $index;
                // Extract parameters if any (after the colon)
                if (strpos($rule, ':') !== false) {
                    $existingRuleValue = substr($rule, strpos($rule, ':') + 1);
                }
                $hasExistingRule = true;
                break;
            }
        }

        // If rule doesn't exist yet, add the database constraint
        if (! $hasExistingRule) {
            return self::addNewConstraintRule($rules, $ruleType, $dbConstraints);
        }

        // If rule exists, apply the stricter constraint
        return self::applyStricterConstraint(
            $rules,
            $ruleType,
            $existingRuleIndex,
            $existingRuleValue,
            $dbConstraints
        );
    }

    /**
     * Add a new constraint-based rule to the rules array.
     *
     * @param  array<int, string>  $rules  The existing rules array
     * @param  string  $ruleType  The type of rule to add
     * @param  array<string, mixed>  $dbConstraints  The database constraints
     * @return array<int, string> Updated rules array
     */
    private static function addNewConstraintRule(array $rules, string $ruleType, array $dbConstraints): array
    {
        // Special handling for common rule types
        switch ($ruleType) {
            case 'max':
                if (isset($dbConstraints['max'])) {
                    $rules[] = $ruleType.':'.$dbConstraints['max'];
                }
                break;

            case 'min':
                if (isset($dbConstraints['min'])) {
                    $rules[] = $ruleType.':'.$dbConstraints['min'];
                }
                break;

            case 'between':
                if (isset($dbConstraints['min'], $dbConstraints['max'])) {
                    $rules[] = $ruleType.':'.$dbConstraints['min'].','.$dbConstraints['max'];
                }
                break;

            default:
                // For pre-formatted rules or rules without parameters
                if (strpos($ruleType, ':') !== false) {
                    $rules[] = $ruleType;
                } elseif (! in_array($ruleType, $rules)) {
                    $rules[] = $ruleType;
                }
                break;
        }

        return $rules;
    }

    /**
     * Apply the stricter constraint between user rule and database constraint.
     * Respects user-defined values that are stricter (e.g., smaller max or larger min)
     * than system-defined constraints.
     *
     * @param  array<int, string>  $rules  The existing rules array
     * @param  string  $ruleType  The type of rule
     * @param  int  $existingRuleIndex  Index of the existing rule in the array
     * @param  string|null  $existingRuleValue  Value of the existing rule
     * @param  array<string, mixed>  $dbConstraints  Database constraints
     * @return array<int, string> Updated rules array
     */
    private static function applyStricterConstraint(
        array $rules,
        string $ruleType,
        int $existingRuleIndex,
        ?string $existingRuleValue,
        array $dbConstraints
    ): array {
        if ($existingRuleValue === null) {
            return $rules; // No parameters to compare, keep existing rule
        }

        switch ($ruleType) {
            case 'max':
                if (isset($dbConstraints['max']) && is_numeric($existingRuleValue)) {
                    // Always keep the user-defined value if it's stricter (smaller) than the system limit
                    // This ensures we respect user-defined max values even if they're smaller than system limits
                    if ((int) $existingRuleValue <= $dbConstraints['max']) {
                        // User's value is already stricter or equal to system limit, keep it
                        $rules[$existingRuleIndex] = 'max:'.$existingRuleValue;
                    } else {
                        // User's value exceeds system limit, use system limit
                        $rules[$existingRuleIndex] = 'max:'.$dbConstraints['max'];
                    }
                }
                break;

            case 'min':
                if (isset($dbConstraints['min']) && is_numeric($existingRuleValue)) {
                    // Always keep the user-defined value if it's stricter (larger) than the system limit
                    // This ensures we respect user-defined min values even if they're larger than system limits
                    if ((int) $existingRuleValue >= $dbConstraints['min']) {
                        // User's value is already stricter or equal to system limit, keep it
                        $rules[$existingRuleIndex] = 'min:'.$existingRuleValue;
                    } else {
                        // User's value is below system minimum, use system limit
                        $rules[$existingRuleIndex] = 'min:'.$dbConstraints['min'];
                    }
                }
                break;

            case 'between':
                if (isset($dbConstraints['min'], $dbConstraints['max']) && strpos($existingRuleValue, ',') !== false) {
                    // For between, compare parts separately
                    [$existingMin, $existingMax] = explode(',', $existingRuleValue);
                    if (is_numeric($existingMin) && is_numeric($existingMax)) {
                        // Keep user's min if it's stricter (larger) than system min
                        $newMin = (int) $existingMin >= $dbConstraints['min']
                            ? (int) $existingMin
                            : $dbConstraints['min'];

                        // Keep user's max if it's stricter (smaller) than system max
                        $newMax = (int) $existingMax <= $dbConstraints['max']
                            ? (int) $existingMax
                            : $dbConstraints['max'];

                        $rules[$existingRuleIndex] = 'between:'.$newMin.','.$newMax;
                    }
                }
                break;

                // Add cases for other rule types that need special handling
        }

        return $rules;
    }

    /**
     * Get validation rules for a specific field type that enforce database constraints.
     * These rules ensure that user input doesn't exceed database column limitations.
     * It will return separate min/max rules instead of a between rule to allow for
     * better merging with user-defined rules.
     *
     * @param  CustomFieldType  $fieldType  The field type
     * @param  bool  $isEncrypted  Whether the field is encrypted
     * @return array<int, string> Array of validation rules
     */
    public static function getValidationRulesForFieldType(CustomFieldType $fieldType, bool $isEncrypted = false): array
    {
        // Get cached rules if available
        $cacheKey = self::CACHE_PREFIX.'_rules_'.$fieldType->value.'_'.($isEncrypted ? '1' : '0');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fieldType, $isEncrypted) {
            $constraints = self::getConstraintsForFieldType($fieldType);
            if (! $constraints) {
                return [];
            }

            $rules = [];
            $validator = $constraints['validator'] ?? null;

            if (! $validator) {
                return $rules;
            }

            // Handle validators that are arrays (multiple rules)
            if (is_array($validator)) {
                $rules = $validator;
            } else {
                // Handle single validator with its constraints
                switch ($validator) {
                    case 'max':
                        $maxValue = $constraints['max'] ?? 255;
                        if ($isEncrypted) {
                            // Encrypted values use more space, reduce max by defined safety margin
                            $maxValue = (int) ($maxValue * self::ENCRYPTION_SAFETY_MARGIN);
                        }
                        $rules[] = "max:{$maxValue}";
                        break;
                    case 'between':
                        // Use string representation for min/max values to avoid floating point issues
                        $minValue = $constraints['min'] ?? PHP_INT_MIN;
                        $maxValue = $constraints['max'] ?? PHP_INT_MAX;

                        // For integer_value fields, add numeric validation to ensure proper format
                        if (isset($constraints['field_types']) &&
                            (in_array(CustomFieldType::NUMBER, $constraints['field_types']) ||
                             in_array(CustomFieldType::RADIO, $constraints['field_types']) ||
                             in_array(CustomFieldType::SELECT, $constraints['field_types']))) {
                            $rules[] = 'numeric';
                            // Add integer validation to ensure we're dealing with integer values
                            $rules[] = 'integer';
                        }

                        // Use separate min/max rules instead of a between rule to allow better merging
                        // with user-defined validation rules
                        $rules[] = "min:{$minValue}";
                        $rules[] = "max:{$maxValue}";
                        break;
                    default:
                        // For other validators, just add them as is
                        $rules[] = $validator;
                }
            }

            // Add field type specific validations
            $rules = array_merge($rules, self::getTypeSpecificRules($fieldType));

            return $rules;
        });
    }

    /**
     * Get validation rules specific to field type data validation requirements.
     *
     * @param  CustomFieldType  $fieldType  The field type
     * @return array<int, string> Array of validation rules
     */
    private static function getTypeSpecificRules(CustomFieldType $fieldType): array
    {
        $rules = [];

        // Add type-specific validation rules
        switch ($fieldType) {
            case CustomFieldType::CURRENCY:
            case CustomFieldType::NUMBER:
                $rules[] = 'numeric';
                break;
            case CustomFieldType::DATE:
                $rules[] = 'date';
                break;
            case CustomFieldType::DATE_TIME:
                $rules[] = 'datetime';
                break;
            case CustomFieldType::TEXT:
            case CustomFieldType::TEXTAREA:
            case CustomFieldType::RICH_EDITOR:
            case CustomFieldType::MARKDOWN_EDITOR:
            case CustomFieldType::LINK:
            case CustomFieldType::COLOR_PICKER:
                $rules[] = 'string';
                break;
        }

        return $rules;
    }

    /**
     * Get validation rules for array/json field types.
     * These rules ensure JSON data fits within database constraints.
     *
     * @param  CustomFieldType  $fieldType  The field type
     * @param  bool  $isEncrypted  Whether the field is encrypted
     * @return array<int, string> Array of validation rules
     */
    public static function getJsonValidationRules(CustomFieldType $fieldType, bool $isEncrypted = false): array
    {
        // Cache the rules to avoid repeated processing
        $cacheKey = self::CACHE_PREFIX.'_json_rules_'.$fieldType->value.'_'.($isEncrypted ? '1' : '0');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fieldType, $isEncrypted) {
            // Only apply these rules to array-type fields
            if (! $fieldType->hasMultipleValues()) {
                return [];
            }

            $driver = self::getDatabaseDriver();
            $constraints = self::$constraints[$driver]['json_value'] ?? null;

            if (! $constraints) {
                Log::warning("No JSON constraints defined for database driver: {$driver}");

                return ['array']; // Return basic array validation as fallback
            }

            $maxItems = $constraints['max_items'] ?? 500;
            $maxItemLength = $constraints['max_item_length'] ?? 255;

            if ($isEncrypted) {
                // Reduce limits for encrypted values using the safety margin
                $maxItemLength = (int) ($maxItemLength * self::ENCRYPTION_SAFETY_MARGIN);
            }

            $rules = [
                'array',
                'max:'.$maxItems, // Max number of items
            ];

            // Add custom rule for validating individual array items
            // This could be extended with a more sophisticated approach if needed

            return $rules;
        });
    }

    /**
     * Clear all constraint and rule caches.
     * Should be called when database schema changes or application settings are updated.
     */
    public static function clearCache(): void
    {
        // Clear driver cache
        Cache::forget(self::CACHE_PREFIX.'_driver');

        // Clear all field type rule caches - both encrypted and non-encrypted variants
        foreach (CustomFieldType::cases() as $fieldType) {
            // Clear non-encrypted rules
            Cache::forget(self::CACHE_PREFIX.'_rules_'.$fieldType->value.'_0');

            // Clear encrypted rules
            Cache::forget(self::CACHE_PREFIX.'_rules_'.$fieldType->value.'_1');

            // Clear JSON rules if applicable
            if ($fieldType->hasMultipleValues()) {
                Cache::forget(self::CACHE_PREFIX.'_json_rules_'.$fieldType->value.'_0');
                Cache::forget(self::CACHE_PREFIX.'_json_rules_'.$fieldType->value.'_1');
            }
        }

        // Log cache clear for debugging purposes
        Log::info('Database field constraints cache cleared');
    }
}
