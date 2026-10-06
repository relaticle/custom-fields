<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\CustomField;

final class EmailEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField, ?Model $record = null): ViewEntry
    {
        return ViewEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->view('custom-fields::infolists.email-entry')
            ->state(fn (mixed $record) => $record->getCustomFieldValue($customField));
    }
}
