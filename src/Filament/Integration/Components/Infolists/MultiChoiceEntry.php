<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Filament\Integration\Concerns\Shared\ConfiguresBadgeColors;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValueResolver\LookupMultiValueResolver;

class MultiChoiceEntry extends AbstractInfolistEntry
{
    use ConfiguresBadgeColors;

    public function __construct(
        private readonly LookupMultiValueResolver $valueResolver
    ) {}

    public function make(CustomField $customField): Entry
    {
        $entry = BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name);

        $entry = $this->applyBadgeColorsIfEnabled($entry, $customField);

        return $entry->state(fn (mixed $record): array => $this->valueResolver->resolve($record, $customField));
    }
}
