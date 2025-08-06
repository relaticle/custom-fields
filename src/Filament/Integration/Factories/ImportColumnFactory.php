<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Data\ValidationRuleData;
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\ColumnConfigurators\BasicColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\ColumnConfigurators\ColumnConfiguratorInterface;
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\ColumnConfigurators\MultiSelectColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\ColumnConfigurators\SelectColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Actions\Imports\Exceptions\UnsupportedColumnTypeException;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Factory for creating import columns based on custom field types.
 */
final class ImportColumnFactory
{
    /**
     * @var array<string, ColumnConfiguratorInterface> Column configurators by field type
     */
    private array $configurators = [];

    /**
     * Constructor that registers the default column configurators.
     */
    public function __construct(
        private readonly SelectColumnConfigurator $selectColumnConfigurator,
        private readonly MultiSelectColumnConfigurator $multiSelectColumnConfigurator,
        private readonly BasicColumnConfigurator $basicColumnConfigurator,
    ) {
        $this->registerDefaultConfigurators();
    }

    /**
     * Create an import column for a custom field.
     *
     * @param  CustomField  $customField  The custom field to create an import column for
     * @return ImportColumn The created import column
     *
     * @throws UnsupportedColumnTypeException If the field type is not supported
     */
    public function create(CustomField $customField): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_' . $customField->code)
            ->label($customField->name);

        // Configure the column based on the field type
        $this->configureColumnByFieldType($column, $customField);

        // Apply validation rules
        $this->applyValidationRules($column, $customField);

        return $column;
    }

    /**
     * Register a column configurator for a specific field type.
     *
     * @param  string  $fieldType  The field type to register the configurator for
     * @param  ColumnConfiguratorInterface  $configurator  The configurator to use
     */
    public function registerConfigurator(string $fieldType, ColumnConfiguratorInterface $configurator): self
    {
        $this->configurators[$fieldType] = $configurator;

        return $this;
    }

    /**
     * Configure a column based on the field type.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     *
     * @throws UnsupportedColumnTypeException If the field type is not supported
     */
    private function configureColumnByFieldType(ImportColumn $column, CustomField $customField): void
    {
        $fieldType = $customField->type;

        if (isset($this->configurators[$fieldType])) {
            $this->configurators[$fieldType]->configure($column, $customField);

            return;
        }

        throw new UnsupportedColumnTypeException($fieldType);
    }

    /**
     * Apply validation rules to a column.
     *
     * @param  ImportColumn  $column  The column to apply validation rules to
     * @param  CustomField  $customField  The custom field containing validation rules
     */
    private function applyValidationRules(ImportColumn $column, CustomField $customField): void
    {
        $rules = $customField->validation_rules->toCollection()
            ->map(
                fn (ValidationRuleData $rule): string => $rule->parameters === []
                    ? $rule->name
                    : $rule->name . ':' . implode(',', $rule->parameters)
            )
            ->filter()
            ->toArray();

        if ($rules !== []) {
            $column->rules($rules);
        }
    }

    /**
     * Register the default column configurators.
     */
    private function registerDefaultConfigurators(): void
    {
        // Get all field types from the manager
        $fieldTypes = [
            'text', 'number', 'link', 'textarea', 'checkbox', 'checkbox_list',
            'radio', 'rich_editor', 'markdown_editor', 'tags_input', 'color_picker',
            'toggle', 'toggle_buttons', 'currency', 'date', 'datetime', 'select', 'multi_select',
        ];

        // Register basic column configurators
        foreach ($fieldTypes as $type) {
            $this->configurators[$type] = $this->basicColumnConfigurator;
        }

        // Register specific configurators for complex types
        $this->registerConfigurator('select', $this->selectColumnConfigurator);
        $this->registerConfigurator('radio', $this->selectColumnConfigurator);

        $this->registerConfigurator('multi_select', $this->multiSelectColumnConfigurator);
        $this->registerConfigurator('checkbox_list', $this->multiSelectColumnConfigurator);
        $this->registerConfigurator('tags_input', $this->multiSelectColumnConfigurator);
        $this->registerConfigurator('toggle_buttons', $this->multiSelectColumnConfigurator);
    }
}
