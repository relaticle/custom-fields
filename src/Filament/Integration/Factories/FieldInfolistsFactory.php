<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Closure;
use Filament\Infolists\Components\Entry;
use InvalidArgumentException;
use Relaticle\CustomFields\Models\CustomField;

class FieldInfolistsFactory
{
    public function create(CustomField $customField): Entry
    {
        $infolistEntryDefinition = $customField->typeData->infolistEntry;

        if ($infolistEntryDefinition === null) {
            throw new InvalidArgumentException(sprintf("Field type '%s' does not support infolist entries.", $customField->type));
        }

        // Handle inline component (Closure)
        if ($infolistEntryDefinition instanceof Closure) {
            $entry = $infolistEntryDefinition($customField);
        } else {
            // Handle traditional component class
            $component = app($infolistEntryDefinition);
            $entry = $component->make($customField);
        }

        return $entry
            ->columnSpan($customField->width->getSpanValue())
            ->inlineLabel(false);
    }
}
