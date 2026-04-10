<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Filament\Integration\Concerns\Shared\ConfiguresCurrencyFormatting;
use Relaticle\CustomFields\Models\CustomField;

final class CurrencyEntry extends AbstractInfolistEntry
{
    use ConfiguresCurrencyFormatting;

    public function make(CustomField $customField): BaseTextEntry
    {
        $entry = BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));

        $this->applyCurrencyFormatting($entry, $customField);

        return $entry;
    }
}
