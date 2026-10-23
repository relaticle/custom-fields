<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

final class HtmlEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField, ?Model $record = null): BaseTextEntry
    {
        return BaseTextEntry::make($customField->getFieldName())
            ->html()
            ->label($customField->name)
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
