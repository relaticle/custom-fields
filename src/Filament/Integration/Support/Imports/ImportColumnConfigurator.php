<?php

declare(strict_types=1);

// ABOUTME: Unified configurator for all custom field import column types
// ABOUTME: Uses data-driven approach with FieldDataType enum for simplicity

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Imports\UnresolvedValue;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Rules\RejectsUnresolvedValue;
use Relaticle\CustomFields\Services\ValidationService;
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

        match ($customField->typeData->dataType) {
            FieldDataType::SINGLE_CHOICE => $this->configureSingleChoice($column, $customField),
            FieldDataType::MULTI_CHOICE => $this->configureMultiChoice($column, $customField),
            FieldDataType::DATE => $this->configureDate($column, $customField),
            FieldDataType::DATE_TIME => $this->configureDateTime($column, $customField),
            FieldDataType::NUMERIC, FieldDataType::FLOAT => $this->configureNumeric($column, $customField),
            FieldDataType::BOOLEAN => $this->configureBoolean($column, $customField),
            default => $this->configureText($column, $customField),
        };

        return $this->finalize($column, $customField);
    }

    /**
     * Check if field type implements custom import transformer and configure accordingly.
     */
    private function configureViaFieldType(ImportColumn $column, CustomField $customField): bool
    {
        $fieldTypeInstance = CustomFieldsType::getFieldTypeInstance($customField->typeData->key);

        if (! $fieldTypeInstance) {
            return false;
        }

        $schema = $fieldTypeInstance->configure();
        $transformer = $schema->getImportTransformer();

        if ($transformer === null) {
            return false;
        }

        $column->castStateUsing($this->wrapTransformer($transformer, $customField));

        $example = $schema->getImportExample();
        if ($example !== null) {
            $column->example($example);
        }

        return true;
    }

    /**
     * Configure single choice fields (select, radio).
     */
    private function configureSingleChoice(ImportColumn $column, CustomField $customField): void
    {
        // Lookup fields (Record type) handle entity references
        if ($customField->typeData->requiresLookupType) {
            $this->configureLookup($column, $customField, false);
        } else {
            $this->configureChoices($column, $customField, false);
        }
    }

    /**
     * Configure multi choice fields (multi-select, checkbox list, tags).
     */
    private function configureMultiChoice(ImportColumn $column, CustomField $customField): void
    {
        $column->array(',');

        // If field accepts arbitrary values (like tags-input), don't validate against options
        if ($customField->typeData->acceptsArbitraryValues) {
            $column->castStateUsing(function (mixed $state): array {
                if (blank($state)) {
                    return [];
                }

                // Convert string to array if needed
                if (is_string($state)) {
                    return array_map('trim', explode(',', $state));
                }

                return is_array($state) ? $state : [$state];
            });

            $column->example('tag1, tag2, tag3');
            $column->helperText(__('custom-fields::custom-fields.import.multi_value_helper'));
        } elseif ($customField->typeData->requiresLookupType) {
            // Lookup fields (Record type) handle entity references
            $this->configureLookup($column, $customField, true);
        } else {
            $this->configureChoices($column, $customField, true);
        }
    }

    /**
     * Configure lookup-based fields.
     */
    private function configureLookup(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        $column->castStateUsing(function (mixed $state) use ($customField, $multiple): array|int|UnresolvedValue|null {
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
    private function resolveLookupValue(CustomField $customField, mixed $value): int|UnresolvedValue
    {
        try {
            $entity = Entities::getEntity($customField->lookup_type);
            $modelInstance = $entity->createModelInstance();
            $primaryAttribute = $entity->getPrimaryAttribute();

            $record = $modelInstance->newQuery()
                ->where($primaryAttribute, $value)
                ->first();

            if ($record) {
                return (int) $record->getKey();
            }

            if (is_numeric($value)) {
                $record = $modelInstance->newQuery()
                    ->where($modelInstance->getKeyName(), $value)
                    ->first();

                if ($record) {
                    return (int) $record->getKey();
                }
            }

            return UnresolvedValue::make($value, sprintf(
                "No %s found matching '%s' for %s.",
                $this->lookupRecordLabel($customField),
                is_scalar($value) ? (string) $value : gettype($value),
                $customField->name,
            ));
        } catch (Throwable $throwable) {
            return UnresolvedValue::make($value, 'Error resolving lookup value: '.$throwable->getMessage());
        }
    }

    /**
     * Resolve multiple lookup values.
     */
    private function resolveLookupValues(CustomField $customField, array $values): array|UnresolvedValue
    {
        $foundIds = [];
        $missingValues = [];

        foreach ($values as $value) {
            $id = $this->resolveLookupValue($customField, $value);

            if ($id instanceof UnresolvedValue) {
                $missingValues[] = $value;

                continue;
            }

            $foundIds[] = $id;
        }

        if ($missingValues !== []) {
            return UnresolvedValue::make($values, sprintf(
                'Could not find a %s for %s: %s',
                $this->lookupRecordLabel($customField),
                $customField->name,
                implode(', ', $missingValues),
            ));
        }

        return $foundIds;
    }

    private function lookupRecordLabel(CustomField $customField): string
    {
        return filled($customField->lookup_type)
            ? $customField->lookup_type.' record'
            : 'record';
    }

    /**
     * Configure choice-based fields.
     */
    private function configureChoices(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        $column->castStateUsing(function (mixed $state) use ($customField, $multiple): array|int|string|UnresolvedValue|null {
            if (blank($state)) {
                return $multiple ? [] : null;
            }

            $values = $multiple && ! is_array($state) ? [$state] : $state;

            if ($multiple) {
                return $this->resolveChoiceValues($customField, $values);
            }

            return $this->resolveChoiceValue($customField, $state);
        });

        $this->setChoiceExamples($column, $customField, $multiple);
    }

    /**
     * Resolve a single choice value.
     */
    private function resolveChoiceValue(CustomField $customField, mixed $value): int|string|UnresolvedValue|null
    {
        // If already numeric, assume it's a choice ID
        if (is_numeric($value)) {
            return CustomFields::optionModelUsesStringKeys() ? (string) $value : (int) $value;
        }

        // Try exact match
        $choice = $customField->options->where('name', $value)->first();

        // Try case-insensitive match
        if (! $choice) {
            $choice = $customField->options->first(
                fn (CustomFieldOption $opt): bool => strtolower((string) $opt->name) === strtolower((string) $value)
            );
        }

        if (! $choice) {
            return UnresolvedValue::make($value, sprintf(
                "Invalid choice '%s' for %s. Valid choices: %s",
                is_scalar($value) ? (string) $value : gettype($value),
                $customField->name,
                $customField->options->pluck('name')->implode(', '),
            ));
        }

        $key = $choice->getKey();

        return CustomFields::optionModelUsesStringKeys() ? (string) $key : $key;
    }

    private function resolveChoiceValues(CustomField $customField, array $values): array|UnresolvedValue
    {
        $foundIds = [];
        $missingValues = [];

        foreach ($values as $value) {
            $id = $this->resolveChoiceValue($customField, $value);

            if ($id instanceof UnresolvedValue) {
                $missingValues[] = $value;

                continue;
            }

            if ($id !== null) {
                $foundIds[] = $id;
            }
        }

        if ($missingValues !== []) {
            return UnresolvedValue::make($values, sprintf(
                'Invalid choices for %s: %s. Valid choices: %s',
                $customField->name,
                implode(', ', $missingValues),
                $customField->options->pluck('name')->implode(', '),
            ));
        }

        return $foundIds;
    }

    /**
     * Configure boolean fields with string coercion for CSV values.
     */
    private function configureBoolean(ImportColumn $column, CustomField $customField): void
    {
        $column->castStateUsing(function (mixed $state) use ($customField): bool|UnresolvedValue|null {
            if (blank($state)) {
                return null;
            }

            if (is_bool($state)) {
                return $state;
            }

            return match (strtolower(trim((string) $state))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => UnresolvedValue::make($state, sprintf(
                    "'%s' is not a valid value for %s. Use true or false (accepted: true, false, 1, 0, yes, no, on, off).",
                    $state,
                    $customField->name,
                )),
            };
        });

        $column->example('true or false');
    }

    private function configureDate(ImportColumn $column, CustomField $customField): void
    {
        $column->castStateUsing(fn (mixed $state): string|UnresolvedValue|null => $this->parseDate($state, false, $customField));

        $column->example(CustomFields::importDateFormat()->getExamples()[0]);
    }

    private function configureDateTime(ImportColumn $column, CustomField $customField): void
    {
        $column->castStateUsing(fn (mixed $state): string|UnresolvedValue|null => $this->parseDate($state, true, $customField));

        $column->example(CustomFields::importDateFormat()->getExamples(withTime: true)[0]);
    }

    private function parseDate(mixed $state, bool $withTime, CustomField $customField): string|UnresolvedValue|null
    {
        if (blank($state)) {
            return null;
        }

        $format = CustomFields::importDateFormat();
        $parsed = $format->parse((string) $state, $withTime);

        if (! $parsed instanceof CarbonImmutable) {
            return UnresolvedValue::make($state, sprintf(
                "'%s' is not a valid date for %s. Expected format: %s.",
                $state,
                $customField->name,
                implode(' or ', $format->getExamples($withTime)),
            ));
        }

        return $parsed->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    private function configureNumeric(ImportColumn $column, CustomField $customField): void
    {
        $column->castStateUsing(function (mixed $state) use ($customField): float|UnresolvedValue|null {
            if (blank($state)) {
                return null;
            }

            $format = CustomFields::importNumberFormat();
            $parsed = $format->parse((string) $state);

            if ($parsed === null) {
                return UnresolvedValue::make($state, sprintf(
                    "'%s' is not a valid number for %s. Expected format: %s.",
                    $state,
                    $customField->name,
                    $format->getExample(),
                ));
            }

            return $parsed;
        });

        $column->example('99.99');
    }

    /**
     * Field types registered by the host application supply their own import
     * transformers. A throw from one would abort the whole row inside Filament's cast
     * loop, taking every other column's error with it, so it is converted into a value
     * the validator can report alongside them.
     */
    public function wrapTransformer(Closure $transformer, CustomField $customField): Closure
    {
        return function (mixed $state) use ($transformer, $customField): mixed {
            try {
                $result = $transformer($state);
            } catch (Throwable $throwable) {
                $result = UnresolvedValue::make($state, $throwable->getMessage());
            }

            if (! $result instanceof UnresolvedValue) {
                return $result;
            }

            // A field type does not know which field it is configuring, and ImportCsv
            // flattens the error bag to message text, so an unnamed reason reaches the
            // user with no way to tell which column to fix.
            if (str_contains($result->reason, (string) $customField->name)) {
                return $result;
            }

            return UnresolvedValue::make($result->raw, $customField->name.': '.$result->reason);
        };
    }

    /**
     * Configure text fields with appropriate examples.
     */
    private function configureText(ImportColumn $column, CustomField $customField): void
    {
        $dataType = $customField->typeData->dataType;

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
                    $column->helperText(__('custom-fields::custom-fields.import.multi_value_helper'));
                }
            }
        } catch (Throwable) {
            $column->example($multiple ? 'Value1, Value2' : 'Sample value');
        }
    }

    /**
     * Set choice examples on the column.
     */
    private function setChoiceExamples(ImportColumn $column, CustomField $customField, bool $multiple): void
    {
        $choices = $customField->options->pluck('name')->toArray();

        if (! empty($choices)) {
            $exampleChoices = array_slice($choices, 0, 2);
            $example = $multiple
                ? implode(', ', $exampleChoices)
                : $exampleChoices[0];

            $column->example($example);

            $helperText = $multiple
                ? 'Separate with commas. Choices: '.implode(', ', $choices)
                : 'Choices: '.implode(', ', $choices);

            $column->helperText($helperText);
        }
    }

    /**
     * Finalize column configuration.
     *
     * `bail` plus the sentinel rule go first so an unresolvable cell reports its own
     * reason once, instead of also tripping the type rule behind it with a message that
     * describes the cast's fallback rather than the user's mistake.
     */
    private function finalize(ImportColumn $column, CustomField $customField): ImportColumn
    {
        $column->rules([
            'bail',
            new RejectsUnresolvedValue,
            ...app(ValidationService::class)->getValidationRules($customField),
        ]);

        $column->fillRecordUsing(function (mixed $state, mixed $record) use ($customField): void {
            if ($state instanceof UnresolvedValue) {
                return;
            }

            ImportDataStorage::set($record, $customField->code, $state);
        });

        return $column;
    }
}
