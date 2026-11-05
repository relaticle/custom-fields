<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Tables;

use Filament\Tables\Columns\Column;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Trait providing label configuration for table columns.
 * ABOUTME: Ensures consistent label handling across all column types.
 *
 * @internal
 */
trait ConfiguresColumnLabel
{
    /**
     * Configure label for a column.
     */
    protected function configureLabel(Column $column, CustomField $customField): Column
    {
        return $column->label($customField->name);
    }
}
