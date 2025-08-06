<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports\ValueConverters;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;

/**
 * Interface for converting custom field values from import format to storage format.
 */
interface ValueConverterInterface
{
    /**
     * Convert custom field values from import format to storage format.
     *
     * @param  HasCustomFields  $record  The model record
     * @param  array<string, mixed>  $customFieldsData  The custom fields data
     * @param  Model|null  $tenant  Optional tenant for multi-tenancy support
     * @return array<string, mixed> The converted custom fields data
     */
    public function convertValues(HasCustomFields $record, array $customFieldsData, ?Model $tenant = null): array;
}
