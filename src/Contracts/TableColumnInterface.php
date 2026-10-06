<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Contracts;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\CustomField;

interface TableColumnInterface
{
    public function make(CustomField $customField, ?Model $record = null): Column;
}
