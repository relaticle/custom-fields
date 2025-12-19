<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem;

use Closure;
use InvalidArgumentException;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Spatie\LaravelData\Data;

/**
 * Schema builder for defining field type capabilities and behaviors.
 * Provides a chainable API for configuring field type features.
 */
class FieldSchema
{
    private FieldDataType $dataType;

    // Field identity
    private string $key = '';

    private string $label = '';

    private string $icon = '';

    // Component definitions
    private string|Closure|null $formComponent = null;

    private string|Closure|null $tableColumn = null;

    private string|Closure|null $tableFilter = null;

    private string|Closure|null $infolistEntry = null;

    // Field properties
    private int $priority = 500;

    private array $availableValidationRules = [];

    private array $defaultValidationRules = [];

    // Capabilities
    private bool $searchable = true;

    private bool $sortable = true;

    private bool $filterable = false;

    private bool $encryptable = false;

    private bool $acceptsArbitraryValues = false;

    protected bool $withoutUserOptions = false;

    private ?string $settingsDataClass = null;

    private string|Closure|null $settingsSchema = null;

    private ?string $importExample = null;

    private ?Closure $importTransformer = null;

    private ?Closure $exportTransformer = null;

    public function __construct(FieldDataType $dataType)
    {
        $this->dataType = $dataType;
    }

    /**
     * Create a configurator for a specific data type.
     */
    public static function for(FieldDataType $dataType): self
    {
        return new self($dataType);
    }

    // ========== Data Type Specific Factory Methods ==========

    /**
     * Configure for text-based fields (STRING, TEXT)
     */
    public static function text(): self
    {
        return new self(FieldDataType::TEXT);
    }

    /**
     * Configure for string fields (shorter text)
     */
    public static function string(): self
    {
        return new self(FieldDataType::STRING);
    }

    /**
     * Configure for numeric fields
     */
    public static function numeric(): self
    {
        return new self(FieldDataType::NUMERIC);
    }

    /**
     * Configure for float fields
     */
    public static function float(): self
    {
        return new self(FieldDataType::FLOAT);
    }

    /**
     * Configure for date fields
     */
    public static function date(): self
    {
        return new self(FieldDataType::DATE);
    }

    /**
     * Configure for datetime fields
     */
    public static function dateTime(): self
    {
        return new self(FieldDataType::DATE_TIME);
    }

    /**
     * Configure for boolean fields
     */
    public static function boolean(): self
    {
        return new self(FieldDataType::BOOLEAN);
    }

    /**
     * Configure for single choice fields (select, radio, etc.)
     */
    public static function singleChoice(): self
    {
        return new self(FieldDataType::SINGLE_CHOICE);
    }

    /**
     * Configure for multi-choice fields (checkboxes, multi-select, etc.)
     */
    public static function multiChoice(): self
    {
        return new self(FieldDataType::MULTI_CHOICE);
    }

    // ========== Field Identity Configuration Methods ==========

    /**
     * Set the field key
     */
    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set the field label
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the field icon
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    // ========== Component Configuration Methods ==========

    /**
     * Set the form component for this field type
     */
    public function formComponent(string|Closure $component): self
    {
        $this->formComponent = $component;

        return $this;
    }

    /**
     * Set the table column for this field type
     */
    public function tableColumn(string|Closure $column): self
    {
        $this->tableColumn = $column;

        return $this;
    }

    /**
     * Set the table filter for this field type
     */
    public function tableFilter(string|Closure $filter): self
    {
        $this->tableFilter = $filter;

        return $this;
    }

    /**
     * Set the infolist entry for this field type
     */
    public function infolistEntry(string|Closure $entry): self
    {
        $this->infolistEntry = $entry;

        return $this;
    }

    /**
     * Set the priority for field ordering
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set available validation rules for this field type (user selectable)
     */
    public function availableValidationRules(array $rules): self
    {
        $this->availableValidationRules = $rules;

        return $this;
    }

    /**
     * Set default validation rules that are always applied
     */
    public function defaultValidationRules(array $rules): self
    {
        $this->defaultValidationRules = array_map(
            fn (ValidationRule|string $rule): string => $rule instanceof ValidationRule ? $rule->value : $rule,
            $rules
        );

        return $this;
    }

    // ========== Common Capability Methods ==========

    /**
     * Configure searchability in tables
     */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Configure sortability in tables
     */
    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * Configure filterability in tables
     */
    public function filterable(bool $filterable = true): self
    {
        $this->filterable = $filterable;

        return $this;
    }

    /**
     * Configure encryption capability
     */
    public function encryptable(bool $encryptable = true): self
    {
        $this->encryptable = $encryptable;

        return $this;
    }

    /**
     * Configure whether field accepts arbitrary values (like tags input)
     */
    public function withArbitraryValues(bool $accepts = true): self
    {
        $this->acceptsArbitraryValues = $accepts;

        return $this;
    }

    // ========== Data Type Specific Methods (from DataTypeConfigurators) ==========

    /**
     * Enable encryption for this field (text fields)
     */
    public function encrypted(): self
    {
        $this->encryptable();

        return $this;
    }

    /**
     * Configure as a long text field (textarea)
     */
    public function longText(): self
    {
        return $this;
    }

    /**
     * Allow users to create new options on the fly (choice fields)
     */
    public function allowArbitraryValues(): self
    {
        $this->withArbitraryValues();

        return $this;
    }

    /**
     * Field doesn't need user-configured options (choice fields)
     * This disables database options UI and enables dynamic extraction from components
     */
    public function withoutUserOptions(): self
    {
        $this->withoutUserOptions = true;

        return $this;
    }

    // ========== Export Configuration ==========

    /**
     * Get the field key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the field label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the field icon
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get the data type for this configuration
     */
    public function getDataType(): FieldDataType
    {
        return $this->dataType;
    }

    /**
     * Get the form component
     */
    public function getFormComponent(): string|Closure|null
    {
        return $this->formComponent;
    }

    /**
     * Get the priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the available validation rules (user selectable)
     */
    public function getAvailableValidationRules(): array
    {
        return $this->availableValidationRules;
    }

    /**
     * Get the default validation rules (always applied)
     */
    public function getDefaultValidationRules(): array
    {
        return $this->defaultValidationRules;
    }

    /**
     * Check if field is searchable
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Check if field is sortable
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Check if field is filterable
     */
    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    /**
     * Check if field is encryptable
     */
    public function isEncryptable(): bool
    {
        return $this->encryptable;
    }

    /**
     * Check if field accepts arbitrary values
     */
    public function acceptsArbitraryValues(): bool
    {
        return $this->acceptsArbitraryValues;
    }

    public function withSettings(string $dataClass, string|Closure $schema): self
    {
        if (! is_subclass_of($dataClass, Data::class)) {
            throw new InvalidArgumentException('Settings data class must extend '.Data::class);
        }

        $this->settingsDataClass = $dataClass;
        $this->settingsSchema = $schema;

        return $this;
    }

    /**
     * Set import example value for templates
     */
    public function importExample(string $example): self
    {
        $this->importExample = $example;

        return $this;
    }

    /**
     * Set custom import column transformer
     */
    public function importTransformer(Closure $transformer): self
    {
        $this->importTransformer = $transformer;

        return $this;
    }

    /**
     * Set custom export value transformer
     */
    public function exportTransformer(Closure $transformer): self
    {
        $this->exportTransformer = $transformer;

        return $this;
    }

    /**
     * Get import example
     */
    public function getImportExample(): ?string
    {
        return $this->importExample;
    }

    /**
     * Get import transformer
     */
    public function getImportTransformer(): ?Closure
    {
        return $this->importTransformer;
    }

    /**
     * Get export transformer
     */
    public function getExportTransformer(): ?Closure
    {
        return $this->exportTransformer;
    }

    public function data(): FieldTypeData
    {
        return new FieldTypeData(
            key: $this->key,
            label: $this->label,
            icon: $this->icon,
            priority: $this->priority,
            dataType: $this->dataType,
            tableColumn: $this->tableColumn,
            tableFilter: $this->tableFilter,
            formComponent: $this->formComponent,
            infolistEntry: $this->infolistEntry,
            searchable: $this->searchable,
            sortable: $this->sortable,
            filterable: $this->filterable,
            encryptable: $this->encryptable,
            withoutUserOptions: $this->withoutUserOptions,
            acceptsArbitraryValues: $this->acceptsArbitraryValues,
            validationRules: $this->availableValidationRules,
            settingsDataClass: $this->settingsDataClass,
            settingsSchema: $this->settingsSchema
        );
    }
}
