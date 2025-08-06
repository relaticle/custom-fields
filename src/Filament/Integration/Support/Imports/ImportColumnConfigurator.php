<?php

declare(strict_types=1);

// ABOUTME: Unified configurator for all custom field import column types
// ABOUTME: Uses data-driven approach with FieldDataType enum for simplicity

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports;

use Carbon\Carbon;
use Exception;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Contracts\FieldImportExportInterface;
use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\FieldTypes\FieldTypeManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\Facades\Entities;
use Throwable;

/**
 * Unified configurator for import columns based on custom field types.
 * Simplifies the previous multi-class approach into a single, data-driven configurator.
 */
final class ImportColumnConfigurator
{
    /**
     * Configure an import column based on a custom field.
     *
     * This is the main entry point that delegates to specific configuration methods
     * based on the field's data type.
     */
    public function configure(ImportColumn $column, CustomField $customField): ImportColumn
    {
        // First, check if field type implements custom import/export behavior
        if ($this->configureViaFieldType($column, $customField)) {
            return $this->finalize($column, $customField);
        }

        // Configure based on data type (handle null typeData gracefully)
        $dataType = $customField->typeData?->dataType ?? FieldDataType::STRING;

        match ($dataType) {
            FieldDataType::SINGLE_CHOICE => $this->configureSingleChoice($column, $customField),
            FieldDataType::MULTI_CHOICE => $this->configureMultiChoice($column, $customField),
            FieldDataType::DATE => $this->configureDate($column),
            FieldDataType::DATE_TIME => $this->configureDateTime($column),
            FieldDataType::NUMERIC => $column->numeric(),
            FieldDataType::FLOAT => $column->numeric(),
            FieldDataType::BOOLEAN => $column->boolean(),
            default => $this->configureText($column, $customField),
        };

        return $this->finalize($column, $customField);
    }

    /**
     * Check if field type implements custom import/export interface and configure accordingly.
     */
    private function configureViaFieldType(ImportColumn $column, CustomField $customField): bool
    {
        $fieldTypeManager = app(FieldTypeManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($customField->type);

        if (! $fieldTypeInstance instanceof FieldImportExportInterface) {
            return false;
        }

        // Let the field type configure itself
        $fieldTypeInstance->configureImportColumn($column);

        // Set example if provided
        $example = $fieldTypeInstance->getImportExample();
        if ($example !== null) {
            $column->example($example);
        }

        // Apply transformation
        $column->castStateUsing(function ($state) use ($fieldTypeInstance) {
            if ($state === null || $state === '') {
                return null;
            }

            return $fieldTypeInstance->transformImportValue($state);
        });

        return true;
    }

    /**
     * Configure single choice fields (select, radio).
     */
    private function configureSingleChoice(ImportColumn $column, CustomField $customField): void
    {
        if ($customField->lookup_type) {
            $this->configureLookup($column, $customField, false);
        } else {
            $this->configureOptions($column, $customField, false);
        }
    }

    /**
     * Configure multi choice fields (multi-select, checkbox list, tags).
     */
    private function configureMultiChoice(ImportColumn $column, CustomField $customField): void
    {
        $column->array(',');

        if ($customField->lookup_type) {
            $this->configureLookup($column, $customField, true);
        } else {
            $this->configureOptions($column, $customField, true);
        }
    }

    /**
     * Configure lookup-based fields.
     */
    private function configureLookup(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        $column->castStateUsing(function ($state) use ($customField, $multiple) {
            if (blank($state)) {
                return $multiple ? [] : null;
            }

            $values = $multiple && ! is_array($state) ? [$state] : $state;

            if ($multiple) {
                return $this->resolveLookupValues($customField, $values);
            }

            return $this->resolveLookupValue($customField, $state);
        });

        $this->setLookupExamples($column, $customField, $multiple);
    }

    /**
     * Resolve a single lookup value.
     */
    private function resolveLookupValue(CustomField $customField, mixed $value): ?int
    {
        try {
            $entity = Entities::getEntity($customField->lookup_type);
            $modelInstance = $entity->createModelInstance();
            $primaryAttribute = $entity->getPrimaryAttribute();

            // Try to find by primary attribute
            $record = $modelInstance->newQuery()
                ->where($primaryAttribute, $value)
                ->first();

            if ($record) {
                return (int) $record->getKey();
            }

            // Try to find by ID if numeric
            if (is_numeric($value)) {
                $record = $modelInstance->newQuery()
                    ->where($modelInstance->getKeyName(), $value)
                    ->first();

                if ($record) {
                    return (int) $record->getKey();
                }
            }

            throw new RowImportFailedException(
                "No {$customField->lookup_type} record found matching '{$value}'"
            );
        } catch (Throwable $e) {
            if ($e instanceof RowImportFailedException) {
                throw $e;
            }

            throw new RowImportFailedException(
                "Error resolving lookup value: {$e->getMessage()}"
            );
        }
    }

    /**
     * Resolve multiple lookup values.
     */
    private function resolveLookupValues(CustomField $customField, array $values): array
    {
        $foundIds = [];
        $missingValues = [];

        foreach ($values as $value) {
            try {
                $id = $this->resolveLookupValue($customField, $value);
                if ($id !== null) {
                    $foundIds[] = $id;
                }
            } catch (RowImportFailedException) {
                $missingValues[] = $value;
            }
        }

        if (! empty($missingValues)) {
            throw new RowImportFailedException(
                "Could not find {$customField->lookup_type} records: " .
                implode(', ', $missingValues)
            );
        }

        return $foundIds;
    }

    /**
     * Configure option-based fields.
     */
    private function configureOptions(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        $column->castStateUsing(function ($state) use ($customField, $multiple) {
            if (blank($state)) {
                return $multiple ? [] : null;
            }

            $values = $multiple && ! is_array($state) ? [$state] : $state;

            if ($multiple) {
                return $this->resolveOptionValues($customField, $values);
            }

            return $this->resolveOptionValue($customField, $state);
        });

        $this->setOptionExamples($column, $customField, $multiple);
    }

    /**
     * Resolve a single option value.
     */
    private function resolveOptionValue(CustomField $customField, mixed $value): ?int
    {
        // If already numeric, assume it's an option ID
        if (is_numeric($value)) {
            return (int) $value;
        }

        // Try exact match
        $option = $customField->options->where('name', $value)->first();

        // Try case-insensitive match
        if (! $option) {
            $option = $customField->options->first(
                fn ($opt) => strtolower((string) $opt->name) === strtolower($value)
            );
        }

        if (! $option) {
            throw new RowImportFailedException(
                "Invalid option '{$value}' for {$customField->name}. Valid options: " .
                $customField->options->pluck('name')->implode(', ')
            );
        }

        return $option->getKey();
    }

    /**
     * Resolve multiple option values.
     */
    private function resolveOptionValues(CustomField $customField, array $values): array
    {
        $foundIds = [];
        $missingValues = [];

        foreach ($values as $value) {
            try {
                $id = $this->resolveOptionValue($customField, $value);
                if ($id !== null) {
                    $foundIds[] = $id;
                }
            } catch (RowImportFailedException) {
                $missingValues[] = $value;
            }
        }

        if (! empty($missingValues)) {
            throw new RowImportFailedException(
                "Invalid options for {$customField->name}: " .
                implode(', ', $missingValues) .
                '. Valid options: ' .
                $customField->options->pluck('name')->implode(', ')
            );
        }

        return $foundIds;
    }

    /**
     * Configure date fields.
     */
    private function configureDate(ImportColumn $column): void
    {
        $column->castStateUsing(function ($state): ?string {
            if (blank($state)) {
                return null;
            }

            try {
                // Try to parse DD/MM/YYYY format first
                if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $state, $matches)) {
                    return Carbon::createFromFormat('d/m/Y', $state)->format('Y-m-d');
                }

                // Fall back to Carbon's default parsing
                return Carbon::parse($state)->format('Y-m-d');
            } catch (Exception) {
                return null;
            }
        });
    }

    /**
     * Configure datetime fields.
     */
    private function configureDateTime(ImportColumn $column): void
    {
        $column->castStateUsing(function ($state): ?string {
            if (blank($state)) {
                return null;
            }

            try {
                return Carbon::parse($state)->format('Y-m-d H:i:s');
            } catch (Exception) {
                return null;
            }
        });
    }

    /**
     * Configure text fields with appropriate examples.
     */
    private function configureText(ImportColumn $column, CustomField $customField): void
    {
        $dataType = $customField->typeData?->dataType ?? FieldDataType::STRING;

        $example = match ($dataType) {
            FieldDataType::STRING => 'Sample text',
            FieldDataType::TEXT => 'Sample longer text',
            default => 'Sample value',
        };

        $column->example($example);
    }

    /**
     * Set lookup examples on the column.
     */
    private function setLookupExamples(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        try {
            $entity = Entities::getEntity($customField->lookup_type);
            $modelInstance = $entity->createModelInstance();
            $primaryAttribute = $entity->getPrimaryAttribute();

            $samples = $modelInstance->newQuery()
                ->limit(2)
                ->pluck($primaryAttribute)
                ->toArray();

            if (! empty($samples)) {
                $example = $multiple
                    ? implode(', ', $samples)
                    : $samples[0];

                $column->example($example);

                if ($multiple) {
                    $column->helperText('Separate multiple values with commas');
                }
            }
        } catch (Throwable) {
            $column->example($multiple ? 'Value1, Value2' : 'Sample value');
        }
    }

    /**
     * Set option examples on the column.
     */
    private function setOptionExamples(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        $options = $customField->options->pluck('name')->toArray();

        if (! empty($options)) {
            $exampleOptions = array_slice($options, 0, 2);
            $example = $multiple
                ? implode(', ', $exampleOptions)
                : $exampleOptions[0];

            $column->example($example);

            $helperText = $multiple
                ? 'Separate with commas. Options: ' . implode(', ', $options)
                : 'Options: ' . implode(', ', $options);

            $column->helperText($helperText);
        }
    }

    /**
     * Finalize column configuration.
     */
    private function finalize(ImportColumn $column, CustomField $customField): ImportColumn
    {
        // Apply validation rules
        $this->applyValidationRules($column, $customField);

        // CRITICAL: Prevent SQL errors by using fillRecordUsing
        $column->fillRecordUsing(function ($state, $record) use ($customField) {
            ImportDataStorage::set($record, $customField->code, $state);
        });

        return $column;
    }

    /**
     * Apply validation rules to the column.
     */
    private function applyValidationRules(ImportColumn $column, CustomField $customField): void
    {
        // Handle validation_rules being a DataCollection or Collection
        $validationRules = $customField->validation_rules;

        // Convert to regular collection if it's a DataCollection
        if (method_exists($validationRules, 'toCollection')) {
            $validationRules = $validationRules->toCollection();
        }

        $rules = $validationRules
            ->map(
                fn (ValidationRuleData $rule): string => $rule->parameters === []
                    ? $rule->name
                    : $rule->name . ':' . implode(',', $rule->parameters)
            )
            ->filter()
            ->toArray();

        if (! empty($rules)) {
            $column->rules($rules);
        }
    }
}
