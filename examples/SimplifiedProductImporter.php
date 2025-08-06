<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Relaticle\CustomFields\Facades\CustomFields;

/**
 * Simplified Product Importer - Minimal Hooks Approach
 *
 * This example shows the simplest way to import custom fields
 * in Filament v4, requiring only ONE hook.
 */
class SimplifiedProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * Define columns including custom fields.
     *
     * The custom field columns handle their own data storage
     * using fillRecordUsing callbacks built into the ImportColumnFactory.
     */
    public static function getColumns(): array
    {
        return [
            // Standard model columns
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('sku')
                ->requiredMapping()
                ->rules(['required', 'unique:products,sku']),

            ImportColumn::make('price')
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),

            // Custom field columns
            // These automatically use fillRecordUsing to store data temporarily
            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns(),
        ];
    }

    /**
     * Resolve record for create or update.
     */
    public function resolveRecord(): ?Product
    {
        return Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }

    /**
     * ONLY HOOK NEEDED!
     *
     * Save the custom fields after the record is saved.
     * The ImporterBuilder automatically detects and saves
     * any pendingCustomFieldData that was stored by the columns.
     */
    protected function afterSave(): void
    {
        // This method automatically handles pendingCustomFieldData
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues($this->record);
    }

    /**
     * Customize completion message.
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and '.
                number_format($import->successful_rows).' '.
                str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.
                     str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

/**
 * Alternative: Traditional Two-Hook Approach
 *
 * If you prefer the explicit two-hook approach or need more control.
 */
class TraditionalProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        // Same as above
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('sku')
                ->requiredMapping()
                ->rules(['required', 'unique:products,sku']),

            ImportColumn::make('price')
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),

            ...CustomFields::importer()
                ->forModel(static::getModel())
                ->columns(),
        ];
    }

    public function resolveRecord(): ?Product
    {
        return Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }

    /**
     * Filter out custom fields before filling the model.
     *
     * This prevents Filament from trying to set non-existent attributes.
     */
    protected function beforeFill(): void
    {
        $this->data = CustomFields::importer()
            ->filterCustomFieldsFromData($this->data);
    }

    /**
     * Save custom fields after the record is saved.
     *
     * Uses originalData which contains the unfiltered import data.
     */
    protected function afterSave(): void
    {
        CustomFields::importer()
            ->forModel($this->record)
            ->saveCustomFieldValues(
                $this->record,
                $this->originalData,
                filament()->getTenant()
            );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and '.
                number_format($import->successful_rows).' '.
                str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.
                     str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
