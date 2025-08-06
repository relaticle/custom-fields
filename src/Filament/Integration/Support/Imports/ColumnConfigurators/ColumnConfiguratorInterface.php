<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators;

use Filament\Actions\Imports\ImportColumn;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Interface for column configurators.
 */
interface ColumnConfiguratorInterface
{
    /**
     * Configure an import column based on a custom field.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    public function configure(ImportColumn $column, CustomField $customField): void;
}
