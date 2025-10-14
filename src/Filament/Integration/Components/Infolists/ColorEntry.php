<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\ColorEntry as BaseColorEntry;
use Filament\Infolists\Components\Entry;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

class ColorEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): Entry
    {
        return BaseColorEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
