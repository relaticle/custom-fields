<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Contracts\FieldImportExportInterface;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\FieldTypes\Concerns\HasImportExportDefaults;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\CurrencyComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Currency fields
 * ABOUTME: Provides Currency functionality with appropriate validation rules
 */
class CurrencyFieldType implements FieldImportExportInterface, FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;
    use HasImportExportDefaults;

    public function getKey(): string
    {
        return 'currency';
    }

    public function getLabel(): string
    {
        return 'Currency';
    }

    public function getIcon(): string
    {
        return 'mdi-currency-usd';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::FLOAT;
    }

    public function getFormComponentClass(): string
    {
        return CurrencyComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return TextColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return TextEntry::class;
    }

    public function getPriority(): int
    {
        return 25;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::NUMERIC,
            ValidationRule::DECIMAL,
            ValidationRule::MIN,
            ValidationRule::MAX,
            ValidationRule::BETWEEN,
            ValidationRule::GT,
            ValidationRule::GTE,
        ];
    }

    /**
     * Provide a custom example for currency fields.
     */
    public function getImportExample(): ?string
    {
        return '99.99';
    }

    /**
     * Configure import column with currency-specific handling.
     */
    public function configureImportColumn(ImportColumn $column): void
    {
        $column->numeric()->castStateUsing(function ($state): ?float {
            if (blank($state)) {
                return null;
            }

            // Remove currency symbols and formatting chars
            if (is_string($state)) {
                $state = preg_replace('/[^0-9.-]/', '', $state);
            }

            return round(floatval($state), 2);
        });
    }
}
