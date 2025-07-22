<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ABOUTME: Handles database field constraints and converts them to validation rules
 * ABOUTME: Ensures user input respects database column limitations across different database drivers
 */
final class DatabaseFieldConstraints
{
    /**
     * Cache configuration.
     */
    private const string CACHE_PREFIX = 'custom_fields_db_constraints';

    private const int CACHE_TTL = 86400; // 24 hours

    /**
     * Encryption safety margin - reduces max length for encrypted fields.
     */
    private const float ENCRYPTION_SAFETY_MARGIN = 0.66;

    /**
     * Column constraints and validation rules.
     * Each column defines its database constraints and Laravel validation rules.
     */
    private const array COLUMN_CONSTRAINTS = [
        'string_value' => [
            'max' => 255,
            'rules' => ['string'],
        ],
        'text_value' => [
            'max' => [
                'mysql' => 65535,
                'pgsql' => 1073741823,
                'sqlite' => 1000000000,
            ],
            'rules' => ['string'],
        ],
        'integer_value' => [
            'min' => -9223372036854775808,
            'max' => 9223372036854775807,
            'rules' => ['numeric', 'integer'],
        ],
        'float_value' => [
            'max_digits' => 30,
            'max_decimals' => 15,
            'rules' => ['numeric', 'digits_between:1,30', 'decimal:0,15'],
        ],
        'json_value' => [
            'max_items' => 500,
            'rules' => ['array'],
        ],
        'boolean_value' => [
            'rules' => ['boolean'],
        ],
        'date_value' => [
            'rules' => ['date'],
        ],
        'datetime_value' => [
            'rules' => ['datetime'],
        ],
    ];

    /**
     * Get the current database driver.
     */
    public static function getDatabaseDriver(): string
    {
        return Cache::remember(
            self::CACHE_PREFIX.'_driver',
            self::CACHE_TTL,
            fn () => DB::connection()->getDriverName()
        );
    }

    /**
     * Get constraints for a specific database column.
     *
     * @param  string  $columnName  The database column name
     * @return array<string, mixed>|null The constraints or null if not found
     */
    public static function getConstraintsForColumn(string $columnName): ?array
    {
        return self::COLUMN_CONSTRAINTS[$columnName] ?? null;
    }

    /**
     * Get validation rules for a database column.
     *
     * @param  string  $columnName  The database column name
     * @param  bool  $isEncrypted  Whether the field is encrypted
     * @return array<int, string> Array of validation rules
     */
    public static function getValidationRulesForColumn(string $columnName, bool $isEncrypted = false): array
    {
        $constraints = self::getConstraintsForColumn($columnName);

        if ($constraints === null || $constraints === []) {
            return [];
        }

        $rules = $constraints['rules'] ?? [];

        if (isset($constraints['min'])) {
            $rules[] = 'min:'.$constraints['min'];
        }

        // Add size constraints as validation rules
        if (isset($constraints['max'])) {
            $maxValue = self::resolveMaxValue($constraints['max']);

            if ($isEncrypted && is_numeric($maxValue)) {
                $maxValue = (int) ($maxValue * self::ENCRYPTION_SAFETY_MARGIN);
            }

            $rules[] = 'max:'.$maxValue;
        }

        return array_unique($rules);
    }

    /**
     * Get validation rules for JSON columns.
     *
     * @return array<int, string> Array of validation rules
     */
    public static function getJsonValidationRules(): array
    {
        $constraints = self::getConstraintsForColumn('json_value');
        $maxItems = $constraints['max_items'] ?? 500;

        return [
            'array',
            'max:'.$maxItems,
        ];
    }

    /**
     * Merge database constraints with user-defined validation rules.
     * Applies the stricter constraint when conflicts exist.
     *
     * @param  array<string, mixed>  $dbConstraints  Database constraints
     * @param  array<int, string>  $userRules  User-defined validation rules
     * @return array<int, string> Merged validation rules
     */
    public static function mergeConstraintsWithRules(array $dbConstraints, array $userRules): array
    {
        $mergedRules = $userRules;

        // Build database rules from constraints
        $dbRules = [];
        if (isset($dbConstraints['rules'])) {
            $dbRules = $dbConstraints['rules'];
        }

        // Add constraint-based rules
        if (isset($dbConstraints['max'])) {
            $dbRules[] = 'max:'.self::resolveMaxValue($dbConstraints['max']);
        }

        // Add min constraint if present
        if (isset($dbConstraints['min'])) {
            $dbRules[] = 'min:'.$dbConstraints['min'];
        }

        // Merge each database rule with user rules
        foreach ($dbRules as $dbRule) {
            $mergedRules = self::mergeRule($mergedRules, $dbRule, $dbConstraints);
        }

        return $mergedRules;
    }

    /**
     * Clear all caches.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX.'_driver');
        Log::info('Database field constraints cache cleared');
    }

    /**
     * Merge a single rule with existing rules.
     */
    private static function mergeRule(
        array $rules,
        string $ruleType,
        array $dbConstraints
    ): array {
        // Extract rule name and parameters
        $ruleParts = explode(':', $ruleType, 2);
        $ruleName = $ruleParts[0];

        // Find existing rule of this type
        $existingIndex = null;
        $existingValue = null;

        foreach ($rules as $index => $rule) {
            if (str_starts_with($rule, $ruleName.':') || $rule === $ruleName) {
                $existingIndex = $index;
                if (str_contains($rule, ':')) {
                    $existingValue = substr($rule, strlen($ruleName) + 1);
                }

                break;
            }
        }

        // If rule doesn't exist, add it
        if ($existingIndex === null) {
            if (! in_array($ruleType, $rules)) {
                $rules[] = $ruleType;
            }

            return $rules;
        }

        // If rule exists, apply stricter constraint
        return self::applyStricterConstraint(
            $rules,
            $ruleName,
            $existingIndex,
            $existingValue,
            $dbConstraints
        );
    }

    /**
     * Apply the stricter constraint between user and database rules.
     */
    private static function applyStricterConstraint(
        array $rules,
        string $ruleName,
        int $existingIndex,
        ?string $existingValue,
        array $dbConstraints
    ): array {
        if ($existingValue === null) {
            return $rules;
        }

        switch ($ruleName) {
            case 'max':
                $dbMax = $dbConstraints['max'] ?? null;
                if ($dbMax !== null && is_numeric($existingValue)) {
                    $dbMaxValue = self::resolveMaxValue($dbMax);
                    $stricterMax = min((int) $existingValue, $dbMaxValue);
                    $rules[$existingIndex] = 'max:'.$stricterMax;
                }

                break;

            case 'min':
                if (isset($dbConstraints['min']) && is_numeric($existingValue)) {
                    $stricterMin = max((int) $existingValue, $dbConstraints['min']);
                    $rules[$existingIndex] = 'min:'.$stricterMin;
                }

                break;

            case 'between':
                if (isset($dbConstraints['min'], $dbConstraints['max']) && str_contains($existingValue, ',')) {
                    [$userMin, $userMax] = array_map('intval', explode(',', $existingValue));
                    $dbMaxValue = self::resolveMaxValue($dbConstraints['max']);
                    $stricterMin = max($userMin, $dbConstraints['min']);
                    $stricterMax = min($userMax, $dbMaxValue);
                    $rules[$existingIndex] = 'between:'.$stricterMin.','.$stricterMax;
                }

                break;
        }

        return $rules;
    }

    /**
     * Resolve max value from constraints, handling driver-specific values.
     */
    private static function resolveMaxValue(mixed $maxConstraint): mixed
    {
        return is_array($maxConstraint)
            ? ($maxConstraint[self::getDatabaseDriver()] ?? current($maxConstraint))
            : $maxConstraint;
    }
}
