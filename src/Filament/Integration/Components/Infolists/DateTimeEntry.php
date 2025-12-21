<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

final class DateTimeEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): TextEntry
    {
        return TextEntry::make($customField->getFieldName())
            ->dateTime('Y-m-d H:i:s')
            ->placeholder('Y-m-d H:i:s')
            ->label($customField->name)
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
