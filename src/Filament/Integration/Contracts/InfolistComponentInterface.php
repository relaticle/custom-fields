<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Contracts;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\CustomField;

interface InfolistComponentInterface
{
    public function make(CustomField $customField, ?Model $record = null): Entry;
}
