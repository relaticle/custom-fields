<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Actions\Exports\ExportColumn;
use Relaticle\CustomFields\Contracts\ValueResolverInterface;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Factory for creating Filament export columns from custom fields.
 * ABOUTME: Handles value resolution and formatting for CSV/Excel exports.
 */
final readonly class ExportColumnFactory
{
    public function __construct(
        private ValueResolverInterface $valueResolver
    ) {}

    public function create(CustomField $customField): ExportColumn
    {
        return ExportColumn::make($customField->getFieldName())
            ->label($customField->name)
            ->state(function (mixed $record) use ($customField) {
                return $this->valueResolver->resolve(
                    record: $record,
                    customField: $customField,
                    exportable: true
                );
            });
    }
}
