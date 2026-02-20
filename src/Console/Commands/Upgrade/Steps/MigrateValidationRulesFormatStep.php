<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;

/**
 * Migrates validation_rules from the old array-of-objects format to the new key-value format.
 *
 * Old format: [{name: 'required', parameters: []}, {name: 'min', parameters: [{value: '5'}]}]
 * New format: {required: true, min_length: 5}
 */
final class MigrateValidationRulesFormatStep implements UpgradeStep
{
    /** @var list<string> */
    private const TEXT_LIKE_TYPES = [
        'text', 'textarea', 'markdown_editor', 'rich_editor', 'link', 'email', 'phone',
    ];

    /** @var list<string> */
    private const NUMERIC_TYPES = ['number', 'currency'];

    /** @var list<string> */
    private const MULTI_SELECT_TYPES = ['multi_select', 'checkbox_list', 'tags_input', 'record'];

    /** @var list<string> */
    private const FILE_TYPES = ['file_upload'];

    public function name(): string
    {
        return 'Migrate Validation Rules Format';
    }

    public function description(): string
    {
        return 'Convert validation_rules from old array-of-objects format to new key-value format';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $fieldModel = CustomFields::newCustomFieldModel();

        $fields = $fieldModel->newQuery()
            ->withoutGlobalScopes()
            ->whereNotNull('validation_rules')
            ->get();

        $processed = 0;
        $failed = 0;
        $warnings = [];

        foreach ($fields as $field) {
            $rules = $field->validation_rules;

            if (! $rules instanceof Collection || $rules->isEmpty()) {
                continue;
            }

            if (! $this->isOldFormat($rules)) {
                continue;
            }

            $command->line("  Processing field '{$field->name}' (type: {$field->type}, id: {$field->id})");

            $fieldWarnings = [];
            $newRules = $this->convertRules($rules, $field->type, $fieldWarnings);

            foreach ($fieldWarnings as $warning) {
                $command->line("    <comment>Warning:</comment> {$warning}");
                $warnings[] = "Field '{$field->name}' (id: {$field->id}): {$warning}";
            }

            if (! $dryRun) {
                try {
                    $field->update([
                        'validation_rules' => $newRules->isEmpty() ? null : $newRules->toArray(),
                    ]);
                    $processed++;
                } catch (\Throwable $e) {
                    $command->line("  <error>Failed to update field {$field->id}: {$e->getMessage()}</error>");
                    $failed++;
                }
            } else {
                $processed++;
            }
        }

        if ($processed === 0 && $failed === 0) {
            $command->line('  <info>No fields need migration</info>');

            return UpgradeStepResult::skipped('No fields with old validation rules format found');
        }

        $result = UpgradeStepResult::success($processed, $failed);

        if ($warnings !== []) {
            return new UpgradeStepResult(
                success: $result->success,
                itemsProcessed: $result->itemsProcessed,
                itemsFailed: $result->itemsFailed,
                warnings: $warnings,
            );
        }

        return $result;
    }

    /**
     * Detect if validation_rules is in the old sequential array-of-objects format.
     *
     * Old format: sequential array where items have a 'name' key.
     * New format: associative array with string keys like 'required', 'min_length'.
     */
    private function isOldFormat(Collection $rules): bool
    {
        if ($rules->isEmpty()) {
            return false;
        }

        $firstItem = $rules->first();

        if (is_array($firstItem) && array_key_exists('name', $firstItem)) {
            return true;
        }

        return false;
    }

    /**
     * Convert old-format rules to new key-value format.
     *
     * @param  list<string>  $warnings  Collected warnings (passed by reference)
     */
    private function convertRules(Collection $rules, string $fieldType, array &$warnings): Collection
    {
        $newRules = collect();
        $hasFileRule = $rules->contains(fn ($rule) => is_array($rule) && ($rule['name'] ?? '') === 'file');

        foreach ($rules as $rule) {
            if (! is_array($rule) || ! isset($rule['name'])) {
                continue;
            }

            $ruleName = $rule['name'];
            $parameters = $rule['parameters'] ?? [];
            $firstParam = $this->getFirstParameterValue($parameters);

            $converted = $this->convertRule($ruleName, $firstParam, $parameters, $fieldType, $hasFileRule, $warnings);

            if ($converted !== null) {
                foreach ($converted as $key => $value) {
                    $newRules->put($key, $value);
                }
            }
        }

        return $newRules;
    }

    /**
     * Convert a single old-format rule to the new format.
     *
     * @param  list<array{value: string}>  $parameters
     * @param  list<string>  $warnings
     * @return array<string, mixed>|null
     */
    private function convertRule(
        string $ruleName,
        ?string $firstParam,
        array $parameters,
        string $fieldType,
        bool $hasFileRule,
        array &$warnings,
    ): ?array {
        return match ($ruleName) {
            'required' => ['required' => true],
            'integer' => ['integer_only' => true],
            'file' => null,
            'min' => $this->convertMinRule($firstParam, $fieldType, $hasFileRule, $warnings),
            'max' => $this->convertMaxRule($firstParam, $fieldType, $hasFileRule, $warnings),
            'after', 'after_or_equal' => $this->convertDateMinRule($firstParam, $ruleName, $warnings),
            'before', 'before_or_equal' => $this->convertDateMaxRule($firstParam, $ruleName, $warnings),
            'decimal' => $this->convertDecimalRule($firstParam, $warnings),
            'mimes', 'mimetypes' => $this->convertMimesRule($parameters),
            default => $this->handleUnmappableRule($ruleName, $warnings),
        };
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>|null
     */
    private function convertMinRule(?string $value, string $fieldType, bool $hasFileRule, array &$warnings): ?array
    {
        if ($value === null) {
            $warnings[] = "Rule 'min' has no parameter value, skipping";

            return null;
        }

        if (in_array($fieldType, self::TEXT_LIKE_TYPES, true)) {
            return ['min_length' => (int) $value];
        }

        if (in_array($fieldType, self::NUMERIC_TYPES, true)) {
            return ['min_value' => (float) $value];
        }

        if (in_array($fieldType, self::MULTI_SELECT_TYPES, true)) {
            return ['min_selections' => (int) $value];
        }

        $warnings[] = "Rule 'min' not applicable for field type '{$fieldType}', discarding";

        return null;
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>|null
     */
    private function convertMaxRule(?string $value, string $fieldType, bool $hasFileRule, array &$warnings): ?array
    {
        if ($value === null) {
            $warnings[] = "Rule 'max' has no parameter value, skipping";

            return null;
        }

        if (in_array($fieldType, self::FILE_TYPES, true) || $hasFileRule) {
            return ['max_size_kb' => (int) $value];
        }

        if (in_array($fieldType, self::TEXT_LIKE_TYPES, true)) {
            return ['max_length' => (int) $value];
        }

        if (in_array($fieldType, self::NUMERIC_TYPES, true)) {
            return ['max_value' => (float) $value];
        }

        if (in_array($fieldType, self::MULTI_SELECT_TYPES, true)) {
            return ['max_selections' => (int) $value];
        }

        $warnings[] = "Rule 'max' not applicable for field type '{$fieldType}', discarding";

        return null;
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>|null
     */
    private function convertDateMinRule(?string $value, string $ruleName, array &$warnings): ?array
    {
        if ($value === null) {
            $warnings[] = "Rule '{$ruleName}' has no parameter value, skipping";

            return null;
        }

        $constraint = $this->parseDateConstraint($value, $warnings);

        if ($constraint === null) {
            return null;
        }

        return ['min_date' => $constraint];
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>|null
     */
    private function convertDateMaxRule(?string $value, string $ruleName, array &$warnings): ?array
    {
        if ($value === null) {
            $warnings[] = "Rule '{$ruleName}' has no parameter value, skipping";

            return null;
        }

        $constraint = $this->parseDateConstraint($value, $warnings);

        if ($constraint === null) {
            return null;
        }

        return ['max_date' => $constraint];
    }

    /**
     * Parse a date constraint value into the new relative format.
     *
     * @param  list<string>  $warnings
     * @return array{relative_value: int, relative_unit: string, direction: string}|null
     */
    private function parseDateConstraint(string $value, array &$warnings): ?array
    {
        return match ($value) {
            'today' => [
                'relative_value' => 0,
                'relative_unit' => 'days',
                'direction' => 'from_now',
            ],
            'tomorrow' => [
                'relative_value' => 1,
                'relative_unit' => 'days',
                'direction' => 'from_now',
            ],
            'yesterday' => [
                'relative_value' => 1,
                'relative_unit' => 'days',
                'direction' => 'ago',
            ],
            default => $this->handleAbsoluteDateConstraint($value, $warnings),
        };
    }

    /**
     * @param  list<string>  $warnings
     */
    private function handleAbsoluteDateConstraint(string $value, array &$warnings): null
    {
        $warnings[] = "Absolute date constraint '{$value}' cannot be automatically converted to relative format, discarding";

        return null;
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, int>|null
     */
    private function convertDecimalRule(?string $value, array &$warnings): ?array
    {
        if ($value === null) {
            $warnings[] = "Rule 'decimal' has no parameter value, skipping";

            return null;
        }

        return ['decimal_places' => (int) $value];
    }

    /**
     * @param  list<array{value: string}>  $parameters
     * @return array<string, list<string>>
     */
    private function convertMimesRule(array $parameters): array
    {
        $types = array_map(
            fn (array $param): string => $param['value'] ?? '',
            $parameters,
        );

        return ['accepted_types' => array_values(array_filter($types))];
    }

    /**
     * @param  list<string>  $warnings
     */
    private function handleUnmappableRule(string $ruleName, array &$warnings): null
    {
        $warnings[] = "Rule '{$ruleName}' cannot be mapped to the new format, discarding";

        return null;
    }

    /**
     * Extract the first parameter value from old-format parameters.
     *
     * @param  list<array{value: string}>  $parameters
     */
    private function getFirstParameterValue(array $parameters): ?string
    {
        if ($parameters === []) {
            return null;
        }

        $first = $parameters[0] ?? null;

        if (! is_array($first)) {
            return null;
        }

        return $first['value'];
    }
}
