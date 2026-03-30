<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\TextEntry;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

final class DateTimeEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): TextEntry
    {
        $isDateTime = $customField->isDateTimeField();

        $format = $isDateTime
            ? (CustomFields::dateTimeDisplayFormat() ?? 'Y-m-d H:i:s')
            : (CustomFields::dateDisplayFormat() ?? 'Y-m-d');

        $entry = TextEntry::make($customField->getFieldName())
            ->placeholder($format);

        $isDateTime
            ? $entry->dateTime($format)
            : $entry->date($format);

        return $entry
            ->label($customField->name)
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
