<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Contracts;

use Filament\Tables\Filters\BaseFilter;
use Relaticle\CustomFields\Models\CustomField;

interface TableFilterInterface
{
    public function make(CustomField $customField, ?string $relationName = null): BaseFilter;
}
