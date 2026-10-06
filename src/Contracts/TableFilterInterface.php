<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Contracts;

use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\CustomField;

interface TableFilterInterface
{
    public function make(CustomField $customField, ?Model $record = null, ?string $through = null): BaseFilter;
}
