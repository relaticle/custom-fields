<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\IconEntry as BaseIconEntry;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

final class BooleanEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): BaseIconEntry
    {
        return BaseIconEntry::make($customField->getFieldName())
            ->boolean()
            ->label($customField->name)
            ->state(fn (mixed $record): bool => (bool) $record->getCustomFieldValue($customField));
    }
}
