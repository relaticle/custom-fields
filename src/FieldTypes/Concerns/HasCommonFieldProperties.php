<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes\Concerns;

use Relaticle\CustomFields\Enums\ValidationRule;

/**
 * Provides default implementations for common field type properties.
 * This trait can be used by field type implementations to reduce boilerplate.
 */
trait HasCommonFieldProperties
{
    public function getTableFilterClass(): ?string
    {
        return null;
    }

    /**
     * Determine if this field type is filterable in tables.
     * Default: true (most fields can be filtered)
     */
    public function isFilterable(): bool
    {
        return false;
    }

    /**
     * Determine if this field type is sortable in tables.
     * Default: true (most fields can be sorted)
     */
    public function isSortable(): bool
    {
        return true;
    }

    /**
     * Determine if this field type is searchable in tables.
     * Default: true (most fields can be searched)
     */
    public function isSearchable(): bool
    {
        return true;
    }

    /**
     * Determine if this field type supports encryption.
     * Default: true (most fields can be encrypted)
     */
    public function isEncryptable(): bool
    {
        return true;
    }

    /**
     * Get the priority for field type ordering in the admin panel.
     * Lower numbers appear first.
     * Default: 100 (neutral priority)
     */
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * Get allowed validation rules for this field type.
     * Default: empty array (no validation rules)
     *
     * @return array<int, ValidationRule>
     */
    public function allowedValidationRules(): array
    {
        return [];
    }
}
