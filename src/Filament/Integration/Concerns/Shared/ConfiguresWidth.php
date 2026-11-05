<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Shared;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Trait providing width/column span configuration for components.
 * ABOUTME: Standardizes how components handle width settings across forms and infolists.
 *
 * @internal
 */
trait ConfiguresWidth
{
    /**
     * Configure width/column span for a form field.
     */
    protected function configureFieldWidth(Field $field, CustomField $customField): Field
    {
        if ($customField->settings->span !== null) {
            $field->columnSpan($customField->settings->span);
        }

        return $field;
    }

    /**
     * Configure width/column span for an infolist entry.
     */
    protected function configureEntryWidth(Entry $entry, CustomField $customField): Entry
    {
        if ($customField->settings->span !== null) {
            $entry->columnSpan($customField->settings->span);
        }

        return $entry;
    }
}
