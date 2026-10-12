<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Console\Commands\Upgrade\UnmigratedRecordFields;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\Console\Commands\UpgradeCommand;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

final class ValidateSchemaStep implements UpgradeStep
{
    public function __construct(private readonly UnmigratedRecordFields $unmigrated) {}

    /** @var array<string, list<string>> */
    private const REQUIRED_COLUMNS = [
        'custom_fields' => [
            'id', 'entity_type', 'name', 'code', 'type',
            'settings', 'sort_order',
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

    /** @var array<string, list<string>> */
    private const RELATIONSHIP_COLUMNS = [
        'custom_field_relationships' => [
            'id', 'code', 'from_entity_type', 'to_entity_type', 'cardinality',
            'from_field_id', 'to_field_id', 'is_symmetric',
        ],
        'custom_field_links' => [
            'id', 'relationship_id', 'from_entity_type', 'from_entity_id',
            'to_entity_type', 'to_entity_id', 'sort_order', 'active_from',
            'active_until', 'source',
        ],
    ];

    public function name(): string
    {
        return 'Validate Schema';
    }

    public function description(): string
    {
        return 'Verify the database schema before running upgrade steps';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $errors = [];
        $warnings = [];
        $validated = 0;

        foreach ($this->requiredColumns() as $tableKey => $requiredColumns) {
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

        $this->reportUnmigratedRecordFields($command, $errors, $warnings);

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

    /**
     * A host with the relationships feature on has the two tables, or it cannot store a
     * single edge. With the feature off they are never migrated, so they are never required.
     *
     * @return array<string, list<string>>
     */
    private function requiredColumns(): array
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS)) {
            return self::REQUIRED_COLUMNS;
        }

        return [...self::REQUIRED_COLUMNS, ...self::RELATIONSHIP_COLUMNS];
    }

    /**
     * Record links left in json_value are invisible to 4.x, which reads the ledger. It is a
     * warning while this run still migrates them, and the wall otherwise.
     *
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    private function reportUnmigratedRecordFields(Command $command, array &$errors, array &$warnings): void
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS)) {
            return;
        }

        $codes = $this->unmigrated->codes();

        if ($codes === []) {
            return;
        }

        $fields = implode(', ', $codes);

        if ($errors === [] && $command instanceof UpgradeCommand && $command->willRun(UpgradeCommand::STEP_MIGRATE_RECORD_LINKS)) {
            $warnings[] = sprintf('%s still store record links in json_value; the Migrate Record Links step moves them', $fields);
            $command->line(sprintf('  <comment>○</comment> %s: links still in json_value, migrating below', $fields));

            return;
        }

        $errors[] = sprintf(
            '%s still store record links in json_value with no relationship definition. Run custom-fields:upgrade with the %s step after publishing the relationship migrations.',
            $fields,
            UpgradeCommand::STEP_MIGRATE_RECORD_LINKS,
        );
        $command->line(sprintf('  <error>✗</error> %s: record links still in json_value', $fields));
    }
}
