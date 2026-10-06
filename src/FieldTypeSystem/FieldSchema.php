<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem;

use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\FieldTypeSystem\Concerns\ConfiguresCapabilities;
use Relaticle\CustomFields\FieldTypeSystem\Concerns\ConfiguresComponents;
use Relaticle\CustomFields\FieldTypeSystem\Concerns\ConfiguresIdentity;
use Relaticle\CustomFields\FieldTypeSystem\Concerns\ConfiguresImportExport;
use Relaticle\CustomFields\FieldTypeSystem\Concerns\ConfiguresValidationRules;

/**
 * Schema builder for defining field type capabilities and behaviors.
 * Provides a chainable API for configuring field type features.
 */
final class FieldSchema
{
    use ConfiguresCapabilities;
    use ConfiguresComponents;
    use ConfiguresIdentity;
    use ConfiguresImportExport;
    use ConfiguresValidationRules;

    private FieldDataType $dataType;

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
     * Configure for file upload fields.
     * Stores file paths in string_value but skips string column validation constraints,
     * since validation runs against the uploaded file, not the stored path.
     */
    public static function file(): self
    {
        return new self(FieldDataType::FILE);
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

    /**
     * Get the data type for this configuration
     */
    public function getDataType(): FieldDataType
    {
        return $this->dataType;
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
            requiresRelationship: $this->requiresRelationship,
            supportsPairing: $this->supportsPairing,
            carriesOptionCategories: $this->carriesOptionCategories,
            acceptsArbitraryValues: $this->acceptsArbitraryValues,
            supportsMultiValue: $this->supportsMultiValue,
            supportsUniqueConstraint: $this->supportsUniqueConstraint,
            validationCapabilities: $this->validationCapabilities,
            settingsDataClass: $this->settingsDataClass,
            settingsSchema: $this->settingsSchema,
            visibilityOperators: $this->visibilityOperators,
            systemOnly: $this->systemOnly
        );
    }
}
