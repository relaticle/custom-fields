<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Infolists;

use Filament\Infolists\Components\Entry;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Trait providing state retrieval configuration for infolist entries.
 * ABOUTME: Standardizes how entries retrieve custom field values from records.
 *
 * @internal
 */
trait ConfiguresInfolistState
{
    /**
     * Configure state retrieval for an entry.
     */
    protected function configureState(Entry $entry, CustomField $customField): Entry
    {
        return $entry->getStateUsing(
            fn (HasCustomFields $record): mixed => $record->getCustomFieldValue($customField)
        );
    }
}
