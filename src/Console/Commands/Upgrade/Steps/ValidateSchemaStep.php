<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;

/**
 * Validates database schema compatibility for v3.
 */
final class ValidateSchemaStep implements UpgradeStep
{
    /** @var array<string, list<string>> */
    private const REQUIRED_COLUMNS = [
        'custom_fields' => [
            'id', 'entity_type', 'name', 'code', 'type',
            'lookup_type', 'settings', 'sort_order',
        ],
        'custom_field_values' => [
            'id', 'entity_type', 'entity_id', 'custom_field_id',
            'string_value', 'text_value', 'integer_value', 'float_value',
            'json_value', 'boolean_value', 'date_value', 'datetime_value',
        ],
        'custom_field_options' => [
            'id', 'custom_field_id', 'name', 'settings', 'sort_order',
        ],
    ];

    public function name(): string
    {
        return 'Validate Schema';
    }

    public function description(): string
    {
        return 'Verify database schema compatibility for v3';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $errors = [];
        $warnings = [];
        $validated = 0;

        foreach (self::REQUIRED_COLUMNS as $tableKey => $requiredColumns) {
            $tableName = config('custom-fields.database.table_names.'.$tableKey, $tableKey);

            if (! Schema::hasTable($tableName)) {
                $errors[] = sprintf("Table '%s' does not exist", $tableName);
                $command->line(sprintf('  <error>✗</error> Table %s: MISSING', $tableName));

                continue;
            }

            $missingColumns = [];
            foreach ($requiredColumns as $column) {
                if (! Schema::hasColumn($tableName, $column)) {
                    $missingColumns[] = $column;
                }
            }

            if ($missingColumns !== []) {
                $errors[] = sprintf("Table '%s' is missing columns: ", $tableName).implode(', ', $missingColumns);
                $command->line(sprintf(
                    '  <error>✗</error> Table %s: missing columns (%s)',
                    $tableName,
                    implode(', ', $missingColumns)
                ));
            } else {
                $command->line(sprintf('  <info>✓</info> Table %s: OK', $tableName));
                $validated++;
            }
        }

        // Check optional sections table
        $sectionsTable = config('custom-fields.database.table_names.custom_field_sections', 'custom_field_sections');
        if (Schema::hasTable($sectionsTable)) {
            $command->line(sprintf('  <info>✓</info> Table %s: OK (optional)', $sectionsTable));
            $validated++;
        } else {
            $warnings[] = sprintf("Optional table '%s' not found (sections feature may be disabled)", $sectionsTable);
            $command->line(sprintf('  <comment>○</comment> Table %s: not found (optional)', $sectionsTable));
        }

        if ($errors !== []) {
            return new UpgradeStepResult(
                success: false,
                itemsProcessed: $validated,
                itemsFailed: count($errors),
                errors: $errors,
                warnings: $warnings,
            );
        }

        return new UpgradeStepResult(
            success: true,
            itemsProcessed: $validated,
            warnings: $warnings,
        );
    }
}
